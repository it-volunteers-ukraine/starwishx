document.addEventListener('DOMContentLoaded', () => {
    
    const cfg = window.contactFormConfig || { messages: {}, classes: {} };
    
        const { 
        form: formClass = 'contact-form', 
        counter: counterClass = 'contact-counter', 
        error: errorClass = 'contact-error',
        inputError: inputErrorClass = 'input-error'
    } = cfg.classes || {};

    
    const spritePath = cfg.spritePath;

 

    const showError = (input, message) => {
        const wrapper = input.closest('label') || input.parentElement;
        const errorContainer =
            wrapper.querySelector(`.${errorClass}`) ||
            wrapper.querySelector('.contact-error');

        if (errorContainer) {
            const msgText = message || 'Помилка заповнення';
            
           
            const iconHtml = `
                <svg>
                    <use xlink:href="${spritePath}#icon-error"></use>
                </svg>
            `;

            
            errorContainer.innerHTML = `${iconHtml}<span>${msgText}</span>`;
            
          
                   }
        input.classList.add(inputErrorClass);
    };

    const clearError = (input) => {
        const wrapper = input.closest('label') || input.parentElement;
        const errorContainer =
            wrapper.querySelector(`.${errorClass}`) ||
            wrapper.querySelector('.contact-error');

        if (errorContainer) {
            errorContainer.innerHTML = ''; 
        }
        input.classList.remove(inputErrorClass);
    };

    const attachInputListener = (input) => {
        input.addEventListener('input', () => {
            if (input.value.trim() !== '') {
                clearError(input);
            }
        });
        
        input.addEventListener('change', () => {
             if (input.value.trim() !== '') clearError(input);
        });
    };

    
    const textarea = document.querySelector(`.${formClass} textarea`);
    let counter = null;

    if (textarea) {
        const textareaWrapper = textarea.closest('[class*="textarea-wrapper"]') || textarea.parentElement;
        
        if (textareaWrapper) {
            counter = textareaWrapper.querySelector(`.${counterClass}`);
            if (!counter) {
                counter = textareaWrapper.querySelector('[class*="contact-counter"]');
            }
        }
        
        const max = parseInt(textarea.getAttribute('maxlength')) || 500;

        const update = () =>
            counter && (counter.textContent = `${textarea.value.length}/${max}`);

        textarea.addEventListener('input', update);
        update();
        attachInputListener(textarea);
    }

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

    const allInputs = document.querySelectorAll(`.${formClass} input`);
    allInputs.forEach((input) => {
        if (input !== phoneInput && input !== textarea) {
            attachInputListener(input);
        }
    });

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
                const msg = input.dataset.msg || cfg.messages.required || 'Заповніть поле';
                showError(input, msg);
                isFormValid = false;
                if (!firstInvalidInput) firstInvalidInput = input;
            } else {
                if (input.type === 'email' && !input.value.includes('@')) {
                    showError(input, cfg.messages.email || 'Некоректний Email');
                    isFormValid = false;
                    if (!firstInvalidInput) firstInvalidInput = input;
                } else {
                    if (input !== phoneInput) {
                        clearError(input);
                    }
                }
            }
        });

        if (phoneInput && iti) {
            const isPhoneRequired = phoneInput.hasAttribute('required');
            const phoneVal = phoneInput.value.trim();

            if (isPhoneRequired && !phoneVal) {
            } else if (phoneVal && !iti.isValidNumber()) {
                showError(phoneInput, cfg.messages.phone || 'Некоректний телефон');
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

        const formData = new FormData(formEl);
        formData.append('action', 'send_contact_form');

        const popupSuccess = document.getElementById('contact-popup-success');
        const popupError = document.getElementById('contact-popup-error');

        function showPopup(el) {
            if (!el) return;
            if (popupSuccess) popupSuccess.style.display = 'none';
            if (popupError) popupError.style.display = 'none';
            
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
                    
                    if (textarea && counter) {
                        counter.textContent = `0/${parseInt(textarea.getAttribute('maxlength')) || 500}`;
                    }
                    
                    formEl.querySelectorAll(`.${inputErrorClass}`).forEach((el) => {
                        el.classList.remove(inputErrorClass);
                    });
                    formEl.querySelectorAll(`.${errorClass}`).forEach((el) => {
                         el.innerHTML = '';
                    });
                } else {
                    if (popupError) {
                        if (data.message) {
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