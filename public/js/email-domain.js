(function () {
    'use strict';

    var KNOWN_DOMAINS = ['gmail.com', 'yahoo.com', 'yahoo.co.id', 'outlook.com', 'icloud.com'];

    document.querySelectorAll('[data-email-field]').forEach(function (group) {
        var local = group.querySelector('[data-email-local]');
        var domainSelect = group.querySelector('[data-email-domain]');
        var domainOther = group.querySelector('[data-email-domain-other]');
        var preview = group.querySelector('[data-email-preview]');

        function currentDomain() {
            return domainSelect.value === 'lainnya' ? domainOther.value.trim() : domainSelect.value;
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
            return { local: value.slice(0, at), domain: value.slice(at + 1) };
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

        domainOther.addEventListener('input', updatePreview);

        toggleCustomDomain();
        updatePreview();
    });
})();
