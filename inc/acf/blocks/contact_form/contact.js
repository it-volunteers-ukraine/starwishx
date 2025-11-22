document.addEventListener('DOMContentLoaded', () => {
    const textarea = document.querySelector('.contact-form textarea');
    if (!textarea) return;

    const counter = textarea.parentElement.querySelector('.contact-counter');
    if (!counter) return;

    const max = parseInt(textarea.getAttribute('maxlength')); // БЕЗ 500

    const update = () => {
        const current = textarea.value.length;
        counter.textContent = `${current} / ${max}`;
    };

    textarea.addEventListener('input', update);
    update();
});
