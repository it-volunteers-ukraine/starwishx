document.addEventListener('DOMContentLoaded', () => {
    // Получаем классы из окна
    const { form: formClass, counter: counterClass, error: errorClass } =
    window.contactFormConfig.classes || {};

        

    // Глобальный конфиг (исправление cfg → window.contactFormConfig)
    const cfg = window.contactFormConfig || { messages: {} };

    // --- ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ ВАЛИДАЦИИ ---

    const showError = (input, message) => {
        const wrapper = input.closest('label') || input.parentElement;
        const errorContainer =
            wrapper.querySelector(`.${errorClass}`) ||
            wrapper.querySelector('.contact-error');

        if (errorContainer) {
            errorContainer.textContent = message || 'Ошибка заполнения';
        }
        input.classList.add(cfg.classes.inputError);
    };

    const clearError = (input) => {
        const wrapper = input.closest('label') || input.parentElement;
        const errorContainer =
            wrapper.querySelector(`.${errorClass}`) ||
            wrapper.querySelector('.contact-error');

        if (errorContainer) {
            errorContainer.textContent = '';
        }
        input.classList.remove(cfg.classes.inputError);
    };

    const attachInputListener = (input) => {
        input.addEventListener('input', () => {
            if (input.value.trim() !== '') {
                clearError(input);
            }
        });
        input.addEventListener('change', () => clearError(input));
    };

    // --- СЧЁТЧИК СИМВОЛОВ ---
    const textarea =
        document.querySelector(`.${formClass} textarea`) ||
        document.querySelector('.contact-form textarea');

    if (textarea) {
        const counter =
            textarea.parentElement.querySelector(`.${counterClass}`) ||
            textarea.parentElement.querySelector('.contact-counter');
        const max = parseInt(textarea.getAttribute('maxlength')) || 500;

        const update = () =>
            counter && (counter.textContent = `${textarea.value.length}/${max}`);

        textarea.addEventListener('input', update);
        update();

        attachInputListener(textarea);
    }

    // --- PHONE (intl-tel-input) ---
    const phoneInput = document.querySelector('.contact-phone-input');
    let iti = null;

    if (phoneInput && window.intlTelInput) {
        iti = window.intlTelInput(phoneInput, {
            initialCountry: 'auto',
            geoIpLookup: function (callback) {
                fetch('https://ipapi.co/json/')
                    .then((res) => res.json())
                    .then((data) => callback(data.country_code))
                    .catch(() => callback('UA'));
            },
            utilsScript:
                'https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/js/utils.js',
            separateDialCode: false,
            nationalMode: false,
            preferredCountries: ['ua', 'pl', 'us', 'gb'],
        });

        phoneInput.addEventListener('blur', () => {
            try {
                const full = iti.getNumber();
                if (full) phoneInput.value = full;
            } catch (_) {}

            if (iti.isValidNumber()) {
                clearError(phoneInput);
            }
        });

        attachInputListener(phoneInput);
    }

    // Очистка ошибок остальных input
    const allInputs = document.querySelectorAll(`.${formClass} input`);
    allInputs.forEach((input) => {
        if (input !== phoneInput && input !== textarea) {
            attachInputListener(input);
        }
    });

    // --- AJAX + Валидация ---
    const formEl = document.querySelector(`.${formClass}`);
    if (!formEl) return;

    formEl.addEventListener('submit', async (e) => {
        e.preventDefault();

        let isFormValid = true;
        let firstInvalidInput = null;

        const requiredInputs = formEl.querySelectorAll('[required]');

        requiredInputs.forEach((input) => {
            const val = input.value.trim();

            if (!val) {
                const msg = input.dataset.msg || 'Заполните поле';
                showError(input, msg);
                isFormValid = false;
                if (!firstInvalidInput) firstInvalidInput = input;
            } else {
                if (input.type === 'email' && !input.value.includes('@')) {
                    showError(input, cfg.messages.email);
                    isFormValid = false;
                    if (!firstInvalidInput) firstInvalidInput = input;
                } else {
                    clearError(input);
                }
            }
        });

        // Проверка телефона
        if (phoneInput && iti) {
            const isPhoneRequired = phoneInput.hasAttribute('required');
            const phoneVal = phoneInput.value.trim();

            if (isPhoneRequired && !phoneVal) {
                showError(
                    phoneInput,
                    phoneInput.dataset.msg || 'Заполните поле'
                );
                isFormValid = false;
                if (!firstInvalidInput) firstInvalidInput = phoneInput;
            } else if (phoneVal && !iti.isValidNumber()) {
                showError(phoneInput, cfg.messages.phone);
                isFormValid = false;
                if (!firstInvalidInput) firstInvalidInput = phoneInput;
            } else {
                phoneInput.value = iti.getNumber();
                clearError(phoneInput);
            }
        }

        if (!isFormValid) {
            if (firstInvalidInput) {
                firstInvalidInput.focus();
            }
            return;
        }

        // --- Отправка ---
        const formData = new FormData(formEl);
        formData.append('action', 'send_contact_form');

        if (typeof ContactFormAjax !== 'undefined') {
            formData.append('_ajax_nonce', ContactFormAjax.nonce);

            fetch(ContactFormAjax.url, {
                method: 'POST',
                body: formData,
            })
                .then((res) => res.json())
                .then((data) => {
                    if (data.success) {
                        alert('Ваше повідомлення відправлено!');
                        formEl.reset();
                        formEl
                            .querySelectorAll('.' + cfg.classes.inputError)
                            .forEach((el) =>
                                el.classList.remove(cfg.classes.inputError)
                            );
                    } else {
                        alert(
                            'Помилка: ' + (data.message || 'Невідома помилка')
                        );
                    }
                })
                .catch(() => {
                    alert('Сталася помилка під час відправки.');
                });
        } else {
            console.error('ContactFormAjax object is missing');
        }
    });
});
