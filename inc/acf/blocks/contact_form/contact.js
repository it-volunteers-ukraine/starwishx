document.addEventListener('DOMContentLoaded', () => {
    const textarea = document.querySelector('.contact-form textarea');
    if (!textarea) return;

    const counter = textarea.parentElement.querySelector('.contact-counter');
    if (!counter) return;

    const update = () => {
        const current = textarea.value.length;
        const max = parseInt(textarea.getAttribute('maxlength')) || 500;
        counter.textContent = `${current} / ${max}`;
    };

    textarea.addEventListener('input', update);
    update();
});
