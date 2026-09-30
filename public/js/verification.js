/*
 * Verifikasi keamanan form pendaftaran.
 *
 * - Tombol Daftar aktif hanya jika ada token Turnstile ATAU captcha cadangan
 *   sudah terisi 5 karakter. Token kedaluwarsa -> tombol nonaktif lagi.
 * - Turnstile error (data-error-callback), api.js gagal dimuat, atau widget
 *   tidak muncul dalam 15 detik -> panel captcha gambar dibuka.
 *
 * File ini dimuat SEBELUM api.js Turnstile, karena callback di atribut
 * data-* dan onerror memanggil fungsi global di bawah.
 */
(function () {
  'use strict';

  var CAPTCHA_LENGTH = 5;
  var LOAD_TIMEOUT_MS = 15000;

  var hasToken = false;
  var form = null;
  var button = null;
  var wait = null;
  var panel = null;
  var image = null;
  var input = null;
  var retry = null;

  function captchaFilled() {
    return !!panel && !panel.hidden && !!input && input.value.replace(/\s+/g, '').length === CAPTCHA_LENGTH;
  }

  function update() {
    if (!button) {
      return;
    }

    var ready = hasToken || captchaFilled();

    button.disabled = !ready;

    if (wait) {
      wait.hidden = ready;
    }
  }

  function loadImage() {
    if (!image) {
      return;
    }

    var src = image.getAttribute('data-src');
    image.src = src + (src.indexOf('?') === -1 ? '?' : '&') + 't=' + Date.now();

    if (input) {
      input.value = '';
    }
  }

  function openPanel(turnstileUnavailable) {
    if (!panel) {
      return;
    }

    if (turnstileUnavailable && retry) {
      retry.hidden = true;
    }

    if (panel.hidden) {
      panel.hidden = false;
      loadImage();
    }

    update();
  }

  window.porprovTurnstileOk = function () {
    hasToken = true;

    if (panel) {
      panel.hidden = true;
    }

    update();
  };

  window.porprovTurnstileExpired = function () {
    hasToken = false;
    update();
  };

  window.porprovTurnstileError = function () {
    hasToken = false;
    openPanel(false);

    // Ditangani sendiri: Turnstile tidak perlu melempar error ke konsol.
    return true;
  };

  window.porprovTurnstileLoadFailed = function () {
    openPanel(true);
  };

  function init() {
    form = document.querySelector('[data-verify-form]');

    if (!form) {
      return;
    }

    button = form.querySelector('button[type="submit"]');
    wait = form.querySelector('[data-verify-wait]');
    panel = form.querySelector('[data-captcha-panel]');

    if (panel) {
      image = panel.querySelector('[data-captcha-image]');
      input = panel.querySelector('input[name="captcha"]');
      retry = panel.querySelector('[data-captcha-retry]');

      var refresh = panel.querySelector('[data-captcha-refresh]');

      if (refresh) {
        refresh.addEventListener('click', function () {
          loadImage();
          update();

          if (input) {
            input.focus();
          }
        });
      }

      if (input) {
        input.addEventListener('input', function () {
          var start = input.selectionStart;
          input.value = input.value.toUpperCase();

          try {
            input.setSelectionRange(start, start);
          } catch (e) {
            // Beberapa browser menolak setSelectionRange; abaikan.
          }

          update();
        });
      }

      if (retry) {
        retry.addEventListener('click', function () {
          hasToken = false;
          update();

          if (window.turnstile) {
            window.turnstile.reset();
          }
        });
      }

      // Kembali dari server dengan error captcha: panel sudah terbuka,
      // tampilkan gambar baru (kode lama sudah hangus).
      if (!panel.hidden) {
        loadImage();
      }
    }

    // Script Turnstile diblokir (mis. browser di dalam aplikasi) tanpa
    // memicu onerror: buka jalur cadangan setelah batas waktu.
    window.setTimeout(function () {
      if (!window.turnstile && !hasToken) {
        openPanel(true);
      }
    }, LOAD_TIMEOUT_MS);

    update();
  }

  // Dimuat di akhir <body> (sesudah form), jadi form sudah ada di DOM dan
  // init() pasti selesai sebelum callback Turnstile mana pun terpanggil.
  init();
})();
