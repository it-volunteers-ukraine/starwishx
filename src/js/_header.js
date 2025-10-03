document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.getElementById('mobile-menu-toggle');
    if (!toggle) return;

    // Закрытие всего мобильного меню
    const closeEntireMenu = () => {
        toggle.checked = false;
        // Закрыть все кастомные подменю
        document.querySelectorAll('.menu-item-custom-grid').forEach(item => {
            item.classList.remove('active');
            const submenu = item.querySelector('.mobile-submenu');
            if (submenu) submenu.classList.remove('active');
        });
        // Сброс активных пунктов подменю
        document.querySelectorAll('.mobile-submenu-item.active').forEach(el => {
            el.classList.remove('active');
        });
    };

    // Обработка переключения кастомных подменю (с классом .opportunities-toggle)
    document.querySelectorAll('.opportunities-toggle').forEach(button => {
        button.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const parentItem = button.closest('.menu-item-custom-grid');
            if (!parentItem) return;
            const submenu = parentItem.querySelector('.mobile-submenu');

            // Закрыть все остальные подменю
            document.querySelectorAll('.menu-item-custom-grid').forEach(item => {
                if (item !== parentItem) {
                    item.classList.remove('active');
                    const otherSubmenu = item.querySelector('.mobile-submenu');
                    if (otherSubmenu) otherSubmenu.classList.remove('active');
                }
            });

            // Переключить текущее
            const isExpanded = parentItem.classList.contains('active');
            parentItem.classList.toggle('active');
            if (submenu) submenu.classList.toggle('active');
            button.setAttribute('aria-expanded', !isExpanded); // A11y: toggle aria
        });
    });

    // Активация пункта подменю (для цвета #6839C8)
    document.querySelectorAll('.mobile-submenu-item').forEach(item => {
        item.addEventListener('click', (e) => {
            e.stopPropagation();
            // Снять .active со всех
            document.querySelectorAll('.mobile-submenu-item').forEach(el => {
                el.classList.remove('active');
            });
            // Добавить текущему
            item.classList.add('active');
        });
    });

    // Закрытие меню при клике вне его области
    document.addEventListener('click', (e) => {
        const isClickInside = e.target.closest('.burger-menu') || e.target.closest('.burger-menu-button') || e.target.closest('.mobile-header-buttons') || e.target.closest('#mobile-menu-toggle');
        if (!isClickInside && toggle.checked) {
            closeEntireMenu();
        }
    });

    // Закрытие по нажатию Escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && toggle.checked) {
            closeEntireMenu();
        }
    });
});