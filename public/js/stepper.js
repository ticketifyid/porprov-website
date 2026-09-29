(function () {
    'use strict';

    document.querySelectorAll('[data-stepper]').forEach(function (root) {
        var max = parseInt(root.dataset.max, 10) || 4;
        var min = 1;
        var hasServerError = root.dataset.hasError === 'true';

        var output = root.querySelector('[data-stepper-output]');
        var minusBtn = root.querySelector('[data-stepper-minus]');
        var plusBtn = root.querySelector('[data-stepper-plus]');
        var hiddenInput = root.querySelector('[data-stepper-input]');
        var quotaMsg = document.querySelector('[data-stepper-quota-msg="' + root.dataset.stepper + '"]');

        var qty = Math.max(min, Math.min(parseInt(output.textContent, 10) || 1, max));

        function render() {
            output.textContent = qty;

            if (hiddenInput) {
                hiddenInput.value = qty;
            }

            var minusDisabled = qty <= min;
            var plusDisabled = qty >= max;

            minusBtn.disabled = minusDisabled;
            plusBtn.disabled = plusDisabled;

            if (quotaMsg) {
                var showQuotaMsg = plusDisabled && max < 4 && !hasServerError;
                quotaMsg.hidden = !showQuotaMsg;
            }
        }

        minusBtn.addEventListener('click', function () {
            if (qty > min) {
                qty -= 1;
                render();
            }
        });

        plusBtn.addEventListener('click', function () {
            if (qty < max) {
                qty += 1;
                render();
            }
        });

        render();
    });
})();
