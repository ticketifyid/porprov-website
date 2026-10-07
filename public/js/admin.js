/**
 * Konfirmasi tindakan berbahaya di halaman admin (Metronic) lewat SweetAlert2
 * (sudah termasuk di metronic/plugins/global/plugins.bundle.js). Form apa pun
 * dengan atribut data-confirm akan dicegat submit-nya dan dimintakan
 * konfirmasi dulu; data-confirm-detail opsional untuk teks tambahan.
 *
 * Jatuh ke window.confirm() native kalau SweetAlert2 belum/tidak termuat,
 * supaya tombol tetap berfungsi.
 */
(function () {
    document.addEventListener('submit', function (event) {
        var form = event.target;

        if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-confirm')) {
            return;
        }

        if (form.dataset.confirmed === '1') {
            return;
        }

        event.preventDefault();

        var title = form.dataset.confirm;
        var text = form.dataset.confirmDetail || '';

        function proceed() {
            form.dataset.confirmed = '1';
            form.submit();
        }

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: title,
                text: text,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, lanjutkan',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#f1416c',
                cancelButtonColor: '#3699ff',
            }).then(function (result) {
                if (result.isConfirmed) {
                    proceed();
                }
            });
        } else if (window.confirm(text ? title + '\n\n' + text : title)) {
            proceed();
        }
    });
})();
