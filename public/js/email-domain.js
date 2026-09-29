(function () {
    'use strict';

    var KNOWN_DOMAINS = ['gmail.com', 'yahoo.com', 'yahoo.co.id', 'outlook.com', 'icloud.com'];

    document.querySelectorAll('[data-email-field]').forEach(function (group) {
        var local = group.querySelector('[data-email-local]');
        var domainSelect = group.querySelector('[data-email-domain]');
        var domainOther = group.querySelector('[data-email-domain-other]');
        var preview = group.querySelector('[data-email-preview]');

        // Domain selalu dibandingkan dan ditampilkan dalam huruf kecil,
        // persis seperti logika artboard Form.dc.html.
        function normalizeDomain(value) {
            return value.trim().toLowerCase();
        }

        function currentDomain() {
            return normalizeDomain(domainSelect.value === 'lainnya' ? domainOther.value : domainSelect.value);
        }

        function updatePreview() {
            var localVal = local.value.trim();
            var domain = currentDomain();
            preview.textContent = (localVal && domain) ? (localVal + '@' + domain) : '—';
        }

        function toggleCustomDomain() {
            var showCustom = domainSelect.value === 'lainnya';
            domainOther.hidden = !showCustom;
        }

        function splitPastedEmail(value) {
            var at = value.indexOf('@');
            if (at === -1) {
                return null;
            }
            return { local: value.slice(0, at), domain: normalizeDomain(value.slice(at + 1)) };
        }

        // Kotak "Lainnya…" ikut disimpan huruf kecil; posisi kursor dijaga
        // supaya tidak melompat ke akhir saat mengetik.
        function forceLowercase(input) {
            var lowered = input.value.toLowerCase();

            if (lowered === input.value) {
                return;
            }

            var start = input.selectionStart;
            var end = input.selectionEnd;

            input.value = lowered;

            if (start !== null) {
                input.setSelectionRange(start, end);
            }
        }

        local.addEventListener('input', function () {
            var split = splitPastedEmail(local.value);

            if (split) {
                local.value = split.local;

                if (KNOWN_DOMAINS.indexOf(split.domain) !== -1) {
                    domainSelect.value = split.domain;
                    domainOther.hidden = true;
                } else if (split.domain) {
                    domainSelect.value = 'lainnya';
                    domainOther.hidden = false;
                    domainOther.value = split.domain;
                }
            }

            updatePreview();
        });

        domainSelect.addEventListener('change', function () {
            toggleCustomDomain();
            updatePreview();
        });

        domainOther.addEventListener('input', function () {
            forceLowercase(domainOther);
            updatePreview();
        });

        domainOther.addEventListener('blur', function () {
            domainOther.value = normalizeDomain(domainOther.value);
            updatePreview();
        });

        forceLowercase(domainOther);
        toggleCustomDomain();
        updatePreview();
    });
})();
