document.addEventListener('DOMContentLoaded', () => {
    // Получаем настройки
    const cfg = window.contactFormConfig || { messages: {}, classes: {} };
    
    // Деструктуризация классов с фоллбэками
    const { 
        form: formClass = 'contact-form', 
        counter: counterClass = 'contact-counter', 
        error: errorClass = 'contact-error',
        inputError: inputErrorClass = 'input-error'
    } = cfg.classes || {};

    // Путь к спрайту (из PHP или дефолтный)
    const spritePath = cfg.spritePath || '/wp-content/themes/your-theme/assets/img/sprites.svg';

    // --- ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ ВАЛИДАЦИИ ---

    const showError = (input, message) => {
        const wrapper = input.closest('label') || input.parentElement;
        const errorContainer =
            wrapper.querySelector(`.${errorClass}`) ||
            wrapper.querySelector('.contact-error');

        if (errorContainer) {
            const msgText = message || 'Ошибка заполнения';
            
            // Генерируем HTML иконки
            // fill: currentColor позволяет иконке брать цвет текста ошибки (красный)
            const iconHtml = `
                <svg class="icon icon-required" aria-hidden="true" style="width: 16px; height: 16px; flex-shrink: 0; fill: currentColor;">
                    <use xlink:href="${spritePath}#icon-required"></use>
                </svg>
            `;

            // Вставляем иконку и текст внутри span для удобства
            errorContainer.innerHTML = `${iconHtml}<span>${msgText}</span>`;
            
            // Если в CSS нет display:flex, можно добавить тут принудительно, 
            // но лучше оставить это в CSS (см. пункт 1)
            errorContainer.style.display = 'flex'; 
        }
        input.classList.add(inputErrorClass);
    };

    const clearError = (input) => {
        const wrapper = input.closest('label') || input.parentElement;
        const errorContainer =
            wrapper.querySelector(`.${errorClass}`) ||
            wrapper.querySelector('.contact-error');

        if (errorContainer) {
            errorContainer.innerHTML = ''; // Очищаем весь HTML (иконку и текст)
        }
        input.classList.remove(inputErrorClass);
    };

    const attachInputListener = (input) => {
        input.addEventListener('input', () => {
            if (input.value.trim() !== '') {
                clearError(input);
            }
        });
        // change тоже полезен для автозаполнения
        input.addEventListener('change', () => {
             if (input.value.trim() !== '') clearError(input);
        });
    };

    // --- СЧЁТЧИК СИМВОЛОВ ---
    const textarea = document.querySelector(`.${formClass} textarea`);

    if (textarea) {
        const wrapper = textarea.closest('label') || textarea.parentElement;
        const counter = wrapper ? wrapper.querySelector(`.${counterClass}`) : null;
        
        const max = parseInt(textarea.getAttribute('maxlength')) || 500;

        const update = () =>
            counter && (counter.textContent = `${textarea.value.length}/${max}`);

        textarea.addEventListener('input', update);
        // Инициализация при загрузке
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
            utilsScript: 'https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/js/utils.js',
            separateDialCode: false,
            nationalMode: false,
            preferredCountries: ['ua', 'pl', 'us', 'gb'],
        });

        phoneInput.addEventListener('blur', () => {
            // При потере фокуса форматируем номер
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

    // --- AJAX + Валидация при отправке ---
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
                const msg = input.dataset.msg || cfg.messages.required || 'Заполните поле';
                showError(input, msg);
                isFormValid = false;
                if (!firstInvalidInput) firstInvalidInput = input;
            } else {
                if (input.type === 'email' && !input.value.includes('@')) {
                    showError(input, cfg.messages.email || 'Некорректный Email');
                    isFormValid = false;
                    if (!firstInvalidInput) firstInvalidInput = input;
                } else {
                    // Если поле заполнено и это не email с ошибкой — очищаем
                    // (для телефона отдельная проверка ниже)
                    if (input !== phoneInput) {
                        clearError(input);
                    }
                }
            }
        });

        // Проверка телефона (если он есть)
        if (phoneInput && iti) {
            const isPhoneRequired = phoneInput.hasAttribute('required');
            const phoneVal = phoneInput.value.trim();

            if (isPhoneRequired && !phoneVal) {
                // Ошибка уже показана в цикле выше, но можно обновить
                // showError(...) 
            } else if (phoneVal && !iti.isValidNumber()) {
                showError(phoneInput, cfg.messages.phone || 'Некорректный телефон');
                isFormValid = false;
                if (!firstInvalidInput) firstInvalidInput = phoneInput;
            } else {
                if (iti.isValidNumber()) {
                    phoneInput.value = iti.getNumber();
                    clearError(phoneInput);
                }
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

        const popupSuccess = document.getElementById('contact-popup-success');
        const popupError = document.getElementById('contact-popup-error');

        function showPopup(el) {
            if (!el) return;
            el.style.display = 'flex';
            try { document.activeElement.blur(); } catch (_) {}
            setTimeout(() => {
                el.style.display = 'none';
            }, 3000);
        }

        if (typeof ContactFormAjax !== 'undefined') {
            formData.append('_ajax_nonce', ContactFormAjax.nonce);

            fetch(ContactFormAjax.url, {
                method: 'POST',
                body: formData,
            })
            .then((res) => res.json())
            .then((data) => {
                if (data.success) {
                    showPopup(popupSuccess);
                    formEl.reset();
                    // Удаляем красную обводку
                    formEl.querySelectorAll(`.${inputErrorClass}`).forEach((el) => {
                        el.classList.remove(inputErrorClass);
                    });
                    // Очищаем тексты ошибок/иконки
                    formEl.querySelectorAll(`.${errorClass}`).forEach((el) => {
                         el.innerHTML = '';
                    });
                } else {
                    if (popupError) {
                        if (data.message) {
                            // Ищем текст внутри попапа, используя класс из конфига или дефолтный
                            const textClass = (cfg.classes && cfg.classes['contact-popup-text']) 
                                ? cfg.classes['contact-popup-text'] 
                                : 'contact-popup-text';
                            
                            const txt = popupError.querySelector(`.${textClass}`);
                            if (txt) txt.textContent = data.message;
                        }
                    }
                    showPopup(popupError);
                }
            })
            .catch(() => {
                showPopup(popupError);
            });
        } else {
            console.error('ContactFormAjax object is missing');
        }
    });
});