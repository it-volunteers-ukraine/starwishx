document.addEventListener('DOMContentLoaded', () => {
    // --- СЧЁТЧИК СИМВОЛОВ (уже настроен ранее) ---
    const { form, counter: counterClass } = window.contactFormClasses || {};
    const textarea = document.querySelector(`.${form} textarea`) || document.querySelector('.contact-form textarea');
    if (textarea) {
        const counter = textarea.parentElement.querySelector(`.${counterClass}`) || textarea.parentElement.querySelector('.contact-counter');
        const max = parseInt(textarea.getAttribute('maxlength')) || 500;
        const update = () => counter && (counter.textContent = `${textarea.value.length} / ${max}`);
        textarea.addEventListener('input', update);
        update();
    }

    // --- PHONE: intl-tel-input ---
    const phoneInput = document.querySelector('.contact-phone-input');
    if (phoneInput && window.intlTelInput) {
        const iti = window.intlTelInput(phoneInput, {
            initialCountry: "auto",
            geoIpLookup: function(callback) {
                // простая ip-lookup — можно заменить или убрать
                fetch("https://ipapi.co/json/")
                    .then(res => res.json())
                    .then(data => callback(data.country_code))
                    .catch(() => callback("UA")); // Украина по умолчанию
            },
            utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/js/utils.js",
            separateDialCode: true,
            preferredCountries: ["ua","pl","us","gb"]
        });

        // при потере фокуса — записать полный номер в поле
        phoneInput.addEventListener("blur", () => {
            try {
                const full = iti.getNumber(); // E.164, например +380501234567
                if (full) phoneInput.value = full;
            } catch (e) { /* ignore */ }
        });

        // при сабмите формы можно дополнительно валидировать
        const formEl = phoneInput.closest('form');
        if (formEl) {
            formEl.addEventListener('submit', (e) => {
                // пример: если нужно, блокировать отправку при некорректном номере
                if (!iti.isValidNumber()) {
                    e.preventDefault();
                    phoneInput.setCustomValidity("Некорректный номер телефона");
                    phoneInput.reportValidity();
                } else {
                    phoneInput.setCustomValidity("");
                }
            });
        }
    }
});
