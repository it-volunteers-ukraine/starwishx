document.addEventListener('DOMContentLoaded', () => {
  /* ---------- мобильное меню (остаётся прежним) ---------- */
  const toggle = document.getElementById('mobile-menu-toggle');
  if (toggle) {
    const closeEntireMenu = () => {
      toggle.checked = false;
      document.querySelectorAll('.menu-item-custom-grid').forEach(item => {
        item.classList.remove('active');
        const sm = item.querySelector('.mobile-submenu');
        if (sm) sm.classList.remove('active');
      });
      document.querySelectorAll('.mobile-submenu-item.active')
        .forEach(el => el.classList.remove('active'));
    };

    document.querySelectorAll('.opportunities-toggle').forEach(btn =>
      btn.addEventListener('click', e => {
        e.preventDefault(); e.stopPropagation();
        const parent = btn.closest('.menu-item-custom-grid');
        if (!parent) return;
        const submenu = parent.querySelector('.mobile-submenu');

        document.querySelectorAll('.menu-item-custom-grid').forEach(item => {
          if (item !== parent) {
            item.classList.remove('active');
            (item.querySelector('.mobile-submenu')?.classList.remove('active'));
          }
        });

        const expanded = parent.classList.contains('active');
        parent.classList.toggle('active');
        submenu?.classList.toggle('active');
        btn.setAttribute('aria-expanded', !expanded);
      })
    );

    document.querySelectorAll('.mobile-submenu-item').forEach(item =>
      item.addEventListener('click', e => {
        e.stopPropagation();
        document.querySelectorAll('.mobile-submenu-item')
          .forEach(el => el.classList.remove('active'));
        item.classList.add('active');
      })
    );

    document.addEventListener('click', e => {
      const inside = e.target.closest(
        '.burger-menu, .burger-menu-button, .mobile-header-buttons, #mobile-menu-toggle'
      );
      if (!inside && toggle.checked) closeEntireMenu();
    });

    document.addEventListener('keydown', e => {
      if (e.key === 'Escape' && toggle.checked) closeEntireMenu();
    });
  }

  /* ---------- активный пункт меню = ТЕКУЩАЯ СТРАНИЦА ---------- */
  const menuLinks = document.querySelectorAll(
    '.header .menu > li:not(.menu-item-lang):not(.menu-item-search) > a'
  );

  const normalise = url => url.replace(/\/$/, '');
  const cur = normalise(window.location.href);
  const isHome = window.location.pathname === '/' || window.location.pathname === '/index.php';

  menuLinks.forEach(link => {
    if (normalise(link.href) === cur && !isHome) {
      link.classList.add('js-active');
    }
  });
});