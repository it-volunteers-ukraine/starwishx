document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.getElementById('mobile-menu-toggle');
    if (!toggle) return;

    
    const closeEntireMenu = () => {
        toggle.checked = false;
        
        document.querySelectorAll('.menu-item-custom-grid').forEach(item => {
            item.classList.remove('active');
            const submenu = item.querySelector('.mobile-submenu');
            if (submenu) submenu.classList.remove('active');
        });
        
        document.querySelectorAll('.mobile-submenu-item.active').forEach(el => {
            el.classList.remove('active');
        });
    };

    
    document.querySelectorAll('.opportunities-toggle').forEach(button => {
        console.log('JS нашёл кнопку:', button);
        button.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const parentItem = button.closest('.menu-item-custom-grid');
            if (!parentItem) return;
            const submenu = parentItem.querySelector('.mobile-submenu');

            
            document.querySelectorAll('.menu-item-custom-grid').forEach(item => {
                if (item !== parentItem) {
                    item.classList.remove('active');
                    const otherSubmenu = item.querySelector('.mobile-submenu');
                    if (otherSubmenu) otherSubmenu.classList.remove('active');
                }
            });

            
            const isExpanded = parentItem.classList.contains('active');
            parentItem.classList.toggle('active');
            if (submenu) submenu.classList.toggle('active');
            button.setAttribute('aria-expanded', !isExpanded); 
        });
    });

   
    document.querySelectorAll('.mobile-submenu-item').forEach(item => {
        item.addEventListener('click', (e) => {
            e.stopPropagation();
            
            document.querySelectorAll('.mobile-submenu-item').forEach(el => {
                el.classList.remove('active');
            });
           
            item.classList.add('active');
        });
    });

    
    document.addEventListener('click', (e) => {
        const isClickInside = e.target.closest('.burger-menu') || e.target.closest('.burger-menu-button') || e.target.closest('.mobile-header-buttons') || e.target.closest('#mobile-menu-toggle');
        if (!isClickInside && toggle.checked) {
            closeEntireMenu();
        }
    });

    
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && toggle.checked) {
            closeEntireMenu();
        }
    });
});