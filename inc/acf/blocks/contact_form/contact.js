document.addEventListener('DOMContentLoaded', () => {

    // --- СЧЁТЧИК СИМВОЛОВ ---
    const { form, counter: counterClass } = window.contactFormClasses || {};
    const textarea = document.querySelector(`.${form} textarea`) || document.querySelector('.contact-form textarea');

    if (textarea) {
        const counter =
            textarea.parentElement.querySelector(`.${counterClass}`) ||
            textarea.parentElement.querySelector('.contact-counter');

        const max = parseInt(textarea.getAttribute('maxlength')) || 500;

        const update = () => counter && (counter.textContent = `${textarea.value.length}/${max}`);

        textarea.addEventListener('input', update);
        update();
    }

    // --- PHONE: intl-tel-input ---
    const phoneInput = document.querySelector('.contact-phone-input');
    let iti = null;

    if (phoneInput && window.intlTelInput) {
        iti = window.intlTelInput(phoneInput, {
            initialCountry: 'auto',
            geoIpLookup: function(callback) {
                fetch('https://ipapi.co/json/')
                    .then(res => res.json())
                    .then(data => callback(data.country_code))
                    .catch(() => callback('UA'));
            },
            utilsScript: 'https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/js/utils.js',
            separateDialCode: false,
            nationalMode: false,
            preferredCountries: ['ua', 'pl', 'us', 'gb']
        });

        phoneInput.addEventListener('blur', () => {
            try {
                const full = iti.getNumber();
                if (full) phoneInput.value = full;
            } catch (_) {}
        });
    }

    // --- AJAX ОТПРАВКА ---
    const formEl = document.querySelector(`.${window.contactFormClasses.form}`);
    if (!formEl) return;

    formEl.addEventListener('submit', async (e) => {
        e.preventDefault(); // блокируем переход на URL

        // Валидация телефона
        if (iti && !iti.isValidNumber()) {
            phoneInput.setCustomValidity('Некоректний номер телефону');
            phoneInput.reportValidity();
            return;
        } else if (iti) {
            phoneInput.setCustomValidity('');
            phoneInput.value = iti.getNumber();
        }

        const formData = new FormData(formEl);
        formData.append('action', 'send_contact_form');
        formData.append('_ajax_nonce', ContactFormAjax.nonce);

        fetch(ContactFormAjax.url, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('Ваше повідомлення відправлено!');
                formEl.reset();
            } else {
                alert('Помилка: ' + (data.message || 'Невідома помилка'));
            }
        })
        .catch(() => {
            alert('Сталася помилка під час відправки.');
        });
    });

});
