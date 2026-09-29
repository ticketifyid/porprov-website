/**
 * Inti halaman scanner (Fase 7): pemilih mode, pengiriman ke server, panel
 * hasil, pencarian manual, dan umpan balik suara/getar.
 *
 * scanner-camera.js dan scanner-hardware.js memakai window.Scanner dari sini
 * dan tidak memanggil fetch sendiri, supaya flag busy dan panel hasil hanya
 * punya satu pemilik.
 */
(function () {
    'use strict';

    /** Batas satu permintaan; di lapangan jaringan seluler bisa menggantung. */
    var TIMEOUT_MS = 8000;

    var STORAGE_MODE = 'scanner.mode';
    var STORAGE_SOUND = 'scanner.sound';

    var el = {};
    var state = {
        busy: false,
        mode: 'camera',
        sound: true,
        pending: null, // registrasi yang menunggu konfirmasi (kamera/manual)
    };

    // ---------- penyimpanan preferensi (boleh gagal: mode privat) ----------

    function readStore(key, fallback) {
        try {
            var value = window.localStorage.getItem(key);
            return value === null ? fallback : value;
        } catch (e) {
            return fallback;
        }
    }

    function writeStore(key, value) {
        try {
            window.localStorage.setItem(key, value);
        } catch (e) {
            /* diabaikan */
        }
    }

    // ---------- umpan balik suara & getar ----------

    var audioCtx = null;

    function beep(steps) {
        if (!state.sound) {
            return;
        }

        try {
            var Ctx = window.AudioContext || window.webkitAudioContext;
            if (!Ctx) {
                return;
            }
            audioCtx = audioCtx || new Ctx();
            if (audioCtx.state === 'suspended') {
                audioCtx.resume();
            }

            var start = audioCtx.currentTime;

            steps.forEach(function (step) {
                var osc = audioCtx.createOscillator();
                var gain = audioCtx.createGain();
                osc.type = 'square';
                osc.frequency.value = step.hz;
                gain.gain.value = 0.08;
                osc.connect(gain).connect(audioCtx.destination);
                osc.start(start + step.at / 1000);
                osc.stop(start + (step.at + step.ms) / 1000);
            });
        } catch (e) {
            /* umpan balik bukan fungsi kritis */
        }
    }

    function vibrate(pattern) {
        try {
            if (navigator.vibrate) {
                navigator.vibrate(pattern);
            }
        } catch (e) {
            /* diabaikan */
        }
    }

    /** Pola berbeda supaya petugas tahu hasilnya tanpa melihat layar. */
    function feedback(kind) {
        if (kind === 'success') {
            beep([{ hz: 988, ms: 120, at: 0 }]);
            vibrate([90]);
        } else if (kind === 'already_redeemed') {
            beep([{ hz: 320, ms: 160, at: 0 }, { hz: 320, ms: 160, at: 240 }]);
            vibrate([200, 100, 200]);
        } else if (kind === 'not_found') {
            beep([{ hz: 180, ms: 420, at: 0 }]);
            vibrate([420]);
        } else if (kind === 'error') {
            beep([
                { hz: 520, ms: 90, at: 0 },
                { hz: 520, ms: 90, at: 150 },
                { hz: 520, ms: 90, at: 300 },
            ]);
            vibrate([100, 80, 100, 80, 100]);
        }
    }

    // ---------- pengiriman ----------

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    /**
     * Kirim JSON dengan batas waktu. Mengembalikan objek respons, atau null
     * kalau gagal (panel "Koneksi gagal" sudah ditampilkan di sini).
     * Flag busy SELALU dilepas di finally.
     */
    function post(url, body) {
        if (state.busy) {
            return Promise.resolve(null);
        }

        state.busy = true;

        var controller = new AbortController();
        var timer = window.setTimeout(function () {
            controller.abort();
        }, TIMEOUT_MS);

        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(body),
            credentials: 'same-origin',
            signal: controller.signal,
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
            .catch(function () {
                showConnectionError();
                return null;
            })
            .finally(function () {
                window.clearTimeout(timer);
                state.busy = false;
            });
    }

    // ---------- panel hasil ----------

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function identityBlock(registration) {
        return (
            '<p class="scan-result__detail">' +
            '<strong>' + escapeHtml(registration.name) + '</strong><br>' +
            '<span class="scan-result__code">' + escapeHtml(registration.code) + '</span>' +
            (registration.regency ? '<br>' + escapeHtml(registration.regency) : '') +
            '</p>'
        );
    }

    function nextButton() {
        return '<div class="scan-result__actions">' +
            '<button type="button" class="scan-btn scan-btn--next" data-scan-action="next">Scan berikutnya</button>' +
            '</div>';
    }

    function renderPanel(variant, html) {
        el.result.className = 'scan-result scan-result--' + variant;
        el.result.innerHTML = html;
        el.result.hidden = false;
        el.result.scrollIntoView({ block: 'nearest' });
    }

    function showConnectionError() {
        state.pending = null;
        renderPanel(
            'neutral',
            '<p class="scan-result__headline">Koneksi gagal. Silakan scan ulang.</p>' + nextButton()
        );
        feedback('error');
    }

    function showNotFound(message) {
        state.pending = null;
        renderPanel(
            'neutral',
            '<p class="scan-result__headline">QR tidak dikenal</p>' +
            '<p class="scan-result__detail">' + escapeHtml(message || 'Coba pencarian manual di bawah.') + '</p>' +
            nextButton()
        );
        feedback('not_found');
    }

    function showSuccess(registration) {
        state.pending = null;
        renderPanel(
            'success',
            '<p class="scan-result__headline">Serahkan' +
            '<span class="scan-result__qty">' + escapeHtml(registration.ticket_qty) + '</span>' +
            'gelang</p>' +
            identityBlock(registration) +
            nextButton()
        );
        feedback('success');
    }

    function showAlreadyRedeemed(registration, recentSelf) {
        state.pending = null;

        if (recentSelf) {
            // Petugas ini sendiri baru menukarnya (alat scanner kadang mengirim
            // dua kali) — bukan kesalahan, jadi jangan tampil merah.
            renderPanel(
                'info',
                '<p class="scan-result__headline">Baru saja Anda tukar pukul ' +
                escapeHtml(registration.redeemed_at_label) + ' (' + escapeHtml(registration.ticket_qty) + ' gelang)</p>' +
                identityBlock(registration) +
                nextButton()
            );
        } else {
            renderPanel(
                'danger',
                '<p class="scan-result__headline">Sudah ditukar</p>' +
                '<p class="scan-result__detail">Pukul <strong>' + escapeHtml(registration.redeemed_at_label) + '</strong>' +
                (registration.redeemed_by_name ? ' oleh <strong>' + escapeHtml(registration.redeemed_by_name) + '</strong>' : '') +
                '</p>' +
                identityBlock(registration) +
                nextButton()
            );
        }

        feedback('already_redeemed');
    }

    function showPendingConfirm(registration, method) {
        state.pending = { id: registration.id, method: method, value: registration.value || null };

        renderPanel(
            'info',
            '<p class="scan-result__headline">Periksa data</p>' +
            identityBlock(registration) +
            '<p class="scan-result__detail">Jumlah gelang: <strong>' + escapeHtml(registration.ticket_qty) + '</strong></p>' +
            '<div class="scan-result__actions">' +
            '<button type="button" class="scan-btn scan-btn--confirm" data-scan-action="confirm">' +
            'Serahkan ' + escapeHtml(registration.ticket_qty) + ' gelang</button>' +
            '<button type="button" class="scan-btn scan-btn--next" data-scan-action="next">Batal</button>' +
            '</div>'
        );
    }

    function clearResult() {
        state.pending = null;
        el.result.hidden = true;
        el.result.innerHTML = '';
        document.dispatchEvent(new CustomEvent('scanner:reset'));
    }

    /** Satu pintu untuk semua respons server. */
    function handleResponse(data, method, scannedValue) {
        if (!data) {
            return;
        }

        if (data.result === 'not_found') {
            showNotFound();
        } else if (data.result === 'success') {
            showSuccess(data.registration);
        } else if (data.result === 'already_redeemed') {
            showAlreadyRedeemed(data.registration, data.recent_self === true);
        } else if (data.result === 'pending_confirm') {
            var registration = data.registration;
            registration.value = scannedValue;
            showPendingConfirm(registration, method);
        }
    }

    // ---------- aksi ----------

    function submitScan(value, method) {
        return post('/scan', { value: value, method: method }).then(function (data) {
            handleResponse(data, method, value);
            return data;
        });
    }

    function confirmRedeem() {
        if (!state.pending) {
            return;
        }

        var pending = state.pending;

        post('/scan/' + pending.id + '/redeem', {
            method: pending.method,
            value: pending.value,
        }).then(function (data) {
            handleResponse(data, pending.method, pending.value);
        });
    }

    // ---------- pencarian manual ----------

    function renderCandidates(candidates, keyword) {
        el.candidates.innerHTML = candidates
            .map(function (candidate) {
                var meta = escapeHtml(candidate.code) +
                    ' · ' + escapeHtml(candidate.ticket_qty) + ' gelang' +
                    (candidate.regency ? ' · ' + escapeHtml(candidate.regency) : '') +
                    (candidate.is_redeemed
                        ? ' · sudah ditukar ' + escapeHtml(candidate.redeemed_at_label)
                        : '');

                return '<li class="scan-candidate">' +
                    '<span>' +
                    '<span class="scan-candidate__name">' + escapeHtml(candidate.name) + '</span><br>' +
                    '<span class="scan-candidate__meta">' + meta + '</span>' +
                    '</span>' +
                    '<button type="button" class="scan-candidate__pick"' +
                    ' data-scan-pick="' + escapeHtml(candidate.id) + '"' +
                    ' data-scan-qty="' + escapeHtml(candidate.ticket_qty) + '"' +
                    ' data-scan-name="' + escapeHtml(candidate.name) + '"' +
                    ' data-scan-code="' + escapeHtml(candidate.code) + '"' +
                    ' data-scan-regency="' + escapeHtml(candidate.regency || '') + '"' +
                    ' data-scan-keyword="' + escapeHtml(keyword) + '">Pilih</button>' +
                    '</li>';
            })
            .join('');
    }

    function searchManual() {
        var keyword = el.manualInput.value.trim();

        if (keyword === '') {
            return;
        }

        el.manualNote.textContent = 'Mencari…';
        el.candidates.innerHTML = '';

        post('/scan/cari', { query: keyword }).then(function (data) {
            if (!data) {
                el.manualNote.textContent = '';
                return;
            }

            if (data.result === 'too_short') {
                el.manualNote.textContent = data.message;
                return;
            }

            if (data.result === 'not_found') {
                el.manualNote.textContent = 'Tidak ada data yang cocok.';
                showNotFound('Tidak ada data yang cocok dengan "' + keyword + '".');
                return;
            }

            el.manualNote.textContent = data.candidates.length + ' data ditemukan. Pilih satu untuk dikonfirmasi.';
            renderCandidates(data.candidates, keyword);
        });
    }

    function pickCandidate(button) {
        el.candidates.innerHTML = '';
        el.manualNote.textContent = '';

        showPendingConfirm(
            {
                id: button.getAttribute('data-scan-pick'),
                name: button.getAttribute('data-scan-name'),
                code: button.getAttribute('data-scan-code'),
                regency: button.getAttribute('data-scan-regency'),
                ticket_qty: button.getAttribute('data-scan-qty'),
                value: button.getAttribute('data-scan-keyword'),
            },
            'manual'
        );
    }

    // ---------- mode ----------

    function applyMode(mode) {
        state.mode = mode === 'hardware' ? 'hardware' : 'camera';
        writeStore(STORAGE_MODE, state.mode);

        el.modeButtons.forEach(function (button) {
            button.setAttribute('aria-pressed', button.getAttribute('data-scan-mode') === state.mode ? 'true' : 'false');
        });

        el.camera.hidden = state.mode !== 'camera';
        el.hardware.hidden = state.mode !== 'hardware';

        document.dispatchEvent(new CustomEvent('scanner:mode', { detail: { mode: state.mode } }));
    }

    function applySound(on) {
        state.sound = on;
        writeStore(STORAGE_SOUND, on ? '1' : '0');
        el.soundToggle.setAttribute('aria-pressed', on ? 'true' : 'false');
        el.soundToggle.textContent = on ? 'Suara: nyala' : 'Suara: mati';
    }

    // ---------- init ----------

    function init() {
        el.result = document.getElementById('scan-result');

        if (!el.result) {
            return;
        }

        el.camera = document.getElementById('scanner-camera');
        el.hardware = document.getElementById('scanner-hardware');
        el.manualInput = document.getElementById('manual-input');
        el.manualForm = document.getElementById('manual-form');
        el.manualNote = document.getElementById('manual-note');
        el.candidates = document.getElementById('manual-candidates');
        el.soundToggle = document.getElementById('sound-toggle');
        el.modeButtons = Array.prototype.slice.call(document.querySelectorAll('[data-scan-mode]'));

        el.modeButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                applyMode(button.getAttribute('data-scan-mode'));
            });
        });

        el.soundToggle.addEventListener('click', function () {
            applySound(!state.sound);
        });

        el.manualForm.addEventListener('submit', function (event) {
            event.preventDefault();
            searchManual();
        });

        el.result.addEventListener('click', function (event) {
            var action = event.target.getAttribute && event.target.getAttribute('data-scan-action');

            if (action === 'confirm') {
                confirmRedeem();
            } else if (action === 'next') {
                clearResult();
            }
        });

        el.candidates.addEventListener('click', function (event) {
            if (event.target.hasAttribute && event.target.hasAttribute('data-scan-pick')) {
                pickCandidate(event.target);
            }
        });

        applySound(readStore(STORAGE_SOUND, '1') !== '0');
        applyMode(readStore(STORAGE_MODE, 'camera'));
    }

    window.Scanner = {
        init: init,
        submitScan: submitScan,
        clearResult: clearResult,
        feedback: feedback,
        isBusy: function () {
            return state.busy;
        },
        mode: function () {
            return state.mode;
        },
        hasPending: function () {
            return state.pending !== null;
        },
        clearManualInput: function () {
            if (el.manualInput) {
                el.manualInput.value = '';
            }
            if (el.manualNote) {
                el.manualNote.textContent = '';
            }
        },
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
