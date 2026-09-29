/**
 * Mode "alat scanner": alat 2D mode keyboard mengetikkan isi QR lalu Enter.
 * Aturan persis docs/arsitektur.md Fase 4: buffer karakter, jeda > 50 ms
 * mereset buffer, Enter mengirim, panjang harus 48.
 *
 * Penghalusan yang diminta pemilik proyek: rentetan berkecepatan alat tetap
 * diperlakukan sebagai scan MESKI fokus sedang di kotak pencarian manual —
 * yang diabaikan hanyalah ketikan berkecepatan manusia. Kotak pencarian
 * dikosongkan dan pencariannya tidak dikirim.
 */
(function () {
    'use strict';

    /** Jeda antar karakter di atas ini berarti ini ketikan manusia, bukan alat. */
    var MAX_GAP_MS = 50;

    /** Panjang token tiket (Str::random(48), aturan 7 CLAUDE.md). */
    var TOKEN_LENGTH = 48;

    var buffer = '';
    var lastAt = 0;
    var fromField = false;

    function isTypingField(node) {
        if (!node || !node.tagName) {
            return false;
        }

        var tag = node.tagName.toLowerCase();

        return tag === 'input' || tag === 'textarea' || node.isContentEditable === true;
    }

    function reset() {
        buffer = '';
        fromField = false;
    }

    function onKeydown(event) {
        if (!window.Scanner || window.Scanner.mode() !== 'hardware') {
            return;
        }

        if (event.ctrlKey || event.altKey || event.metaKey) {
            return;
        }

        var now = (window.performance && window.performance.now) ? window.performance.now() : Date.now();
        var gap = now - lastAt;

        if (event.key === 'Enter') {
            var isScannerBurst = buffer.length === TOKEN_LENGTH && gap <= MAX_GAP_MS;
            var value = buffer;
            var typedInField = fromField;

            reset();
            lastAt = now;

            if (!isScannerBurst) {
                // Ketikan manusia: biarkan form pencarian manual bekerja normal.
                return;
            }

            // Rentetan alat menang atas kotak pencarian.
            event.preventDefault();
            event.stopPropagation();

            if (typedInField) {
                window.Scanner.clearManualInput();
            }

            if (window.Scanner.isBusy()) {
                return;
            }

            window.Scanner.submitScan(value, 'hardware');

            return;
        }

        // Hanya karakter tunggal yang dianggap isi QR (bukan Shift, Tab, F5, dll).
        if (event.key.length !== 1) {
            return;
        }

        if (gap > MAX_GAP_MS) {
            buffer = '';
            fromField = false;
        }

        buffer += event.key;
        lastAt = now;

        if (isTypingField(event.target)) {
            fromField = true;
        }
    }

    document.addEventListener('keydown', onKeydown, true);
})();
