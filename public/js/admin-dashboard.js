/**
 * Penyegaran otomatis dashboard admin tiap 60 detik lewat
 * GET admin.dashboard.data (JSON), tanpa memuat ulang seluruh halaman.
 * Hanya dipakai di resources/views/admin/dashboard.blade.php.
 */
(function () {
    var root = document.getElementById('dashboard-root');

    if (!root) {
        return;
    }

    var dataUrl = root.dataset.dashboardDataUrl;

    function escapeHtml(value) {
        return (value === null || value === undefined ? '' : String(value)).replace(/[&<>"']/g, function (char) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char];
        });
    }

    function setBar(id, percent, danger) {
        var bar = document.getElementById(id);

        if (!bar) {
            return;
        }

        bar.style.width = percent + '%';
        bar.setAttribute('aria-valuenow', percent);

        if (danger !== undefined) {
            bar.classList.toggle('bg-danger', danger);
            bar.classList.toggle('bg-primary', !danger);
        }
    }

    function renderRecent(list) {
        var container = document.getElementById('dash-recent-list');

        if (!container) {
            return;
        }

        if (!list || list.length === 0) {
            container.innerHTML = '<div class="text-muted p-4">Belum ada pendaftar.</div>';

            return;
        }

        container.innerHTML = list.map(function (item) {
            return ''
                + '<div class="d-flex justify-content-between align-items-center py-2 border-bottom">'
                + '<div>'
                + '<div class="fw-bold">' + escapeHtml(item.name) + '</div>'
                + '<div class="text-muted fs-7">' + escapeHtml(item.code) + ' · ' + escapeHtml(item.regency || '-') + ' · ' + escapeHtml(item.ticket_qty) + ' tiket</div>'
                + '</div>'
                + '<a href="' + item.url + '" class="btn btn-sm btn-light-primary">Detail</a>'
                + '</div>';
        }).join('');
    }

    function applyMetrics(metrics) {
        document.querySelectorAll('[data-dash]').forEach(function (el) {
            var key = el.dataset.dash;

            if (Object.prototype.hasOwnProperty.call(metrics, key)) {
                el.textContent = metrics[key];
            }
        });

        setBar('quotaBar', metrics.quotaPercent, metrics.quotaPercent > 90);
        setBar('checkinBar', metrics.checkinPercent);
        renderRecent(metrics.recent);

        var updatedAt = document.getElementById('dash-updated-at');

        if (updatedAt) {
            updatedAt.textContent = 'Diperbarui pukul ' + metrics.updatedAt;
        }
    }

    function refresh() {
        fetch(dataUrl, { headers: { Accept: 'application/json' } })
            .then(function (response) {
                return response.ok ? response.json() : Promise.reject(response.status);
            })
            .then(applyMetrics)
            .catch(function (error) {
                console.error('Gagal menyegarkan data dashboard.', error);
            });
    }

    setInterval(refresh, 60000);
})();
