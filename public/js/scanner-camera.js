/**
 * Mode kamera: html5-qrcode (di-self-host di js/vendor/) membaca QR lewat
 * kamera belakang HP, lalu POST /scan {method:"camera"} — dua langkah, jadi
 * penukaran baru terjadi setelah petugas menekan konfirmasi.
 *
 * Kamera hanya bisa diakses browser lewat HTTPS (atau localhost).
 */
(function () {
    'use strict';

    var reader = null;
    var running = false;
    var starting = false;

    function statusText(message) {
        var node = document.getElementById('camera-status');

        if (node) {
            node.textContent = message;
        }
    }

    function onDecoded(text) {
        if (!window.Scanner || window.Scanner.isBusy() || window.Scanner.hasPending()) {
            return;
        }

        // Hentikan pemindaian sampai petugas menutup panel hasil, supaya QR yang
        // sama tidak terkirim berulang-ulang.
        stop();
        window.Scanner.submitScan(text, 'camera');
    }

    function start() {
        if (running || starting || typeof Html5Qrcode === 'undefined') {
            return;
        }

        starting = true;
        reader = reader || new Html5Qrcode('reader', { verbose: false });

        reader
            .start(
                { facingMode: 'environment' },
                { fps: 10, qrbox: { width: 240, height: 240 } },
                onDecoded,
                function () {
                    /* frame tanpa QR: bukan error */
                }
            )
            .then(function () {
                running = true;
                starting = false;
                statusText('Arahkan kamera ke QR pada e-ticket.');
            })
            .catch(function (error) {
                starting = false;
                statusText(
                    'Kamera tidak bisa dibuka (' + error + '). Pastikan halaman dibuka lewat HTTPS ' +
                    'dan izin kamera diberikan, atau pakai mode Alat scanner / pencarian manual.'
                );
            });
    }

    function stop() {
        if (!reader || !running) {
            return;
        }

        running = false;

        reader.stop().catch(function () {
            /* diabaikan */
        });
    }

    document.addEventListener('scanner:mode', function (event) {
        if (event.detail.mode === 'camera') {
            start();
        } else {
            stop();
        }
    });

    document.addEventListener('scanner:reset', function () {
        if (window.Scanner && window.Scanner.mode() === 'camera') {
            start();
        }
    });
})();
