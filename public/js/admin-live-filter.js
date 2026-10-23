/**
 * Pencarian dan filter langsung (tanpa tombol Cari) di daftar admin.
 * Form dengan atribut data-live-filter mengambil ulang halaman yang sama lewat
 * fetch tiap input berubah (ketikan di-debounce), lalu mengganti isi elemen
 * [data-live-results] dan [data-live-reset] dari respons. Fokus kolom cari
 * tidak hilang. Kalau fetch gagal, form dikirim biasa.
 */
(function () {
    var form = document.querySelector('form[data-live-filter]');
    var results = document.querySelector('[data-live-results]');

    if (!form || !results || !window.fetch || !window.URLSearchParams) {
        return;
    }

    var reset = document.querySelector('[data-live-reset]');
    var timer = null;
    var controller = null;

    function buildUrl() {
        var params = new URLSearchParams(new FormData(form));

        Array.from(params.keys()).forEach(function (key) {
            if (params.get(key) === '') {
                params.delete(key);
            }
        });

        var query = params.toString();

        return window.location.pathname + (query ? '?' + query : '');
    }

    function refresh() {
        var url = buildUrl();

        if (controller) {
            controller.abort();
        }
        controller = new AbortController();
        results.style.opacity = '0.5';
        results.setAttribute('aria-busy', 'true');

        fetch(url, { signal: controller.signal, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.text();
            })
            .then(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var newResults = doc.querySelector('[data-live-results]');
                var newReset = doc.querySelector('[data-live-reset]');

                if (!newResults) {
                    throw new Error('Respons tidak lengkap');
                }

                results.innerHTML = newResults.innerHTML;
                results.style.opacity = '';
                results.setAttribute('aria-busy', 'false');

                if (reset && newReset) {
                    reset.innerHTML = newReset.innerHTML;
                }

                window.history.replaceState(null, '', url);
            })
            .catch(function (error) {
                if (error.name === 'AbortError') {
                    return;
                }
                form.submit();
            });
    }

    form.addEventListener('input', function (event) {
        if (event.target.tagName === 'SELECT') {
            return;
        }
        clearTimeout(timer);
        timer = setTimeout(refresh, 300);
    });

    form.addEventListener('change', function (event) {
        if (event.target.tagName === 'SELECT') {
            clearTimeout(timer);
            refresh();
        }
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        clearTimeout(timer);
        refresh();
    });
})();
