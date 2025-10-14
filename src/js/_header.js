document.addEventListener('DOMContentLoaded', () => {
  /* ---------- мобильное меню ---------- */
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
        e.preventDefault();
        e.stopPropagation();
        const parent = btn.closest('.menu-item-custom-grid');
        if (!parent) return;
        const submenu = parent.querySelector('.mobile-submenu');

        document.querySelectorAll('.menu-item-custom-grid').forEach(item => {
          if (item !== parent) {
            item.classList.remove('active');
            item.classList.remove('mobile-active');
            (item.querySelector('.mobile-submenu')?.classList.remove('active'));
          }
        });

        const expanded = parent.classList.contains('active');
        parent.classList.toggle('active');
        submenu?.classList.toggle('active');

        if (!expanded) {
          parent.classList.add('mobile-active');
        } else {
          parent.classList.remove('mobile-active');
        }

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

  /* ---------- активный пункт меню (десктоп) ---------- */
  const menuLinks = document.querySelectorAll(
    '.header .menu > li:not(.hide-mobile) > a'
  );
  const normalise = url => url.replace(/\/$/, '');
  const cur = normalise(window.location.href);

  menuLinks.forEach(link => {
    if (normalise(link.href) === cur) {
      link.classList.add('js-active');
    }
  });

  /* ---------- заглушки: поиск и переключение языка (десктоп) ---------- */
  document.querySelectorAll('.menu-item-search').forEach(el => {
    el.addEventListener('click', e => {
      e.preventDefault();
      alert('Здесь будет поиск');
    });
  });

  document.querySelectorAll('.menu-item-lang button').forEach(btn => {
    btn.addEventListener('click', e => {
      alert('Здесь будет переключение языка');
    });
  });

  /* ---------- заглушки: поиск и переключение языка (мобильная версия) ---------- */
  const mobileSearchIcon = document.querySelector('.mobile-header-buttons .search-icon');
  if (mobileSearchIcon) {
    mobileSearchIcon.addEventListener('click', e => {
      e.preventDefault();
      alert('Здесь будет поиск (мобильная версия)');
    });
  }

  document.querySelectorAll('.mobile-header-buttons .lang-btn').forEach(btn => {
    btn.addEventListener('click', e => {
      alert('Здесь будет переключение языка (мобильная версия)');
    });
  });
});
