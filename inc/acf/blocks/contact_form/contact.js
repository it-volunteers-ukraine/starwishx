document.addEventListener('DOMContentLoaded', () => {
    const { form, counter: counterClass } = window.contactFormClasses;

    const textarea = document.querySelector(`.${form} textarea`);
    if (!textarea) return;

    const counter = textarea.parentElement.querySelector(`.${counterClass}`);
    if (!counter) return;

    const max = parseInt(textarea.getAttribute('maxlength'));

    const update = () => {
        counter.textContent = `${textarea.value.length} / ${max}`;
    };

    textarea.addEventListener('input', update);
    update();
});
