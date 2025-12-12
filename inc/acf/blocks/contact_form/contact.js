document.addEventListener('DOMContentLoaded', () => {
    // Получаем классы из конфига
    const { form: formClass, counter: counterClass, error: errorClass } = window.contactFormClasses || {};
    
    // --- ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ ВАЛИДАЦИИ ---
    
    // Показать ошибку
    const showError = (input, message) => {
        const wrapper = input.closest('label') || input.parentElement;
        const errorContainer = wrapper.querySelector(`.${errorClass}`) || wrapper.querySelector('.contact-error');
        
        if (errorContainer) {
            errorContainer.textContent = message || 'Ошибка заполнения';
        }
        input.classList.add('input-error'); // Класс для красной рамки инпута (нужно добавить в CSS)
    };

    // Очистить ошибку
    const clearError = (input) => {
        const wrapper = input.closest('label') || input.parentElement;
        const errorContainer = wrapper.querySelector(`.${errorClass}`) || wrapper.querySelector('.contact-error');
        
        if (errorContainer) {
            errorContainer.textContent = '';
        }
        input.classList.remove('input-error');
    };

    // Слушатель ввода для очистки ошибок в реальном времени
    const attachInputListener = (input) => {
        input.addEventListener('input', () => {
            if (input.value.trim() !== '') {
                clearError(input);
            }
        });
        // Для select или других типов событий, если нужно
        input.addEventListener('change', () => clearError(input));
    };


    // --- СЧЁТЧИК СИМВОЛОВ ---
    const textarea = document.querySelector(`.${formClass} textarea`) || document.querySelector('.contact-form textarea');
    if (textarea) {
        const counter = textarea.parentElement.querySelector(`.${counterClass}`) || textarea.parentElement.querySelector('.contact-counter');
        const max = parseInt(textarea.getAttribute('maxlength')) || 500;
        const update = () => counter && (counter.textContent = `${textarea.value.length}/${max}`);
        
        textarea.addEventListener('input', update);
        update();
        attachInputListener(textarea); // Подключаем очистку ошибок
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

        // Форматирование при потере фокуса
        phoneInput.addEventListener('blur', () => {
            try {
                const full = iti.getNumber();
                if (full) phoneInput.value = full;
            } catch (_) {}
            
            // Валидация телефона при потере фокуса (опционально)
            if (iti.isValidNumber()) {
                clearError(phoneInput);
            }
        });

        attachInputListener(phoneInput);
    }

    // Подключаем слушатели очистки ошибок ко всем остальным полям
    const allInputs = document.querySelectorAll(`.${formClass} input`);
    allInputs.forEach(input => {
        if (input !== phoneInput && input !== textarea) {
            attachInputListener(input);
        }
    });


    // --- AJAX ОТПРАВКА С ВАЛИДАЦИЕЙ ---
    const formEl = document.querySelector(`.${formClass}`);
    if (!formEl) return;

    formEl.addEventListener('submit', async (e) => {
        e.preventDefault(); 

        let isFormValid = true;
        let firstInvalidInput = null;

        // 1. Проверка стандартных полей (text, email, textarea)
        // Ищем только те поля, у которых есть атрибут required (выставленный через PHP ACF)
        const requiredInputs = formEl.querySelectorAll('[required]');
        
        requiredInputs.forEach(input => {
            const val = input.value.trim();
            
            // Если поле пустое
            if (!val) {
                const msg = input.dataset.msg || 'Заполните поле';
                showError(input, msg);
                isFormValid = false;
                if (!firstInvalidInput) firstInvalidInput = input;
            } else {
                // Если это Email, можно добавить простую проверку формата
                if (input.type === 'email' && !input.value.includes('@')) {
                     showError(input, 'Некорректный Email');
                     isFormValid = false;
                     if (!firstInvalidInput) firstInvalidInput = input;
                } else {
                    clearError(input);
                }
            }
        });

        // 2. Проверка Телефона (Специфичная логика intl-tel-input)
        // Если поле телефона обязательное (required) ИЛИ если оно заполнено (чтобы проверить формат)
        if (phoneInput && iti) {
            const isPhoneRequired = phoneInput.hasAttribute('required');
            const phoneVal = phoneInput.value.trim();

            if (isPhoneRequired && !phoneVal) {
                showError(phoneInput, phoneInput.dataset.msg || 'Заполните поле');
                isFormValid = false;
                if (!firstInvalidInput) firstInvalidInput = phoneInput;
            } else if (phoneVal && !iti.isValidNumber()) {
                // Если номер введен, но он некорректный (даже если поле необязательное)
                showError(phoneInput, 'Некоректний номер телефону');
                isFormValid = false;
                if (!firstInvalidInput) firstInvalidInput = phoneInput;
            } else {
                // Всё ок, записываем полный номер
                phoneInput.value = iti.getNumber();
                clearError(phoneInput);
            }
        }

        // Если есть ошибки — прерываем отправку и скроллим к первому ошибочному полю
        if (!isFormValid) {
            if (firstInvalidInput) {
                firstInvalidInput.focus();
            }
            return; 
        }

        // --- Если валидация прошла успешно, отправляем данные ---
        const formData = new FormData(formEl);
        formData.append('action', 'send_contact_form');
        // Убедитесь, что ContactFormAjax определен (обычно через wp_localize_script)
        if (typeof ContactFormAjax !== 'undefined') {
             formData.append('_ajax_nonce', ContactFormAjax.nonce);
             
             fetch(ContactFormAjax.url, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('Ваше повідомлення відправлено!'); // Можно заменить на красивый попап
                    formEl.reset();
                    // Очищаем классы ошибок после сброса
                    formEl.querySelectorAll('.input-error').forEach(el => el.classList.remove('input-error'));
                } else {
                    alert('Помилка: ' + (data.message || 'Невідома помилка'));
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