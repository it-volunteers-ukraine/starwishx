document.addEventListener('DOMContentLoaded', () => {

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


    toggle.addEventListener('change', () => {
      document.body.classList.toggle('menu-open', toggle.checked);
    });


    document.querySelectorAll('.opportunities-toggle').forEach(btn =>
      btn.addEventListener('click', e => {
        e.preventDefault(); e.stopPropagation();
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


  document.querySelectorAll('.menu-item-lang button').forEach(btn => {
    btn.addEventListener('click', () => {
      alert('Мова (десктоп)');
    });
  });

  document.querySelectorAll('.mobile-header-buttons .search-icon').forEach(el => {
    el.addEventListener('click', e => {
      e.preventDefault();
      alert('Пошук (мобільна)');
    });
  });

  document.querySelectorAll('.mobile-header-buttons .lang-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      alert('Мова (мобільна)');
    });
  });


  // modal form
  const searchModalRef = document.getElementById('searchModal');
  const formSearch = document.getElementById('form-search');
  const formSearchInputRef = formSearch.querySelector('.search-input');
  const formClearRef = formSearch.querySelector('.form-clear-btn');
  const formSpeachRef = formSearch.querySelector('.speech');
  
  formClearRef.addEventListener('click', () => {
    formSearchInputRef.value = "";
    formSearchInputRef.focus();
  })

  document.querySelectorAll('.menu-item-search').forEach(el => {
    el.addEventListener('click', e => {
      e.preventDefault();
      // alert('Пошук (десктоп)');
      searchModalRef.classList.add('active');
      setTimeout(() => {
        formSearchInputRef.focus();
      }, 50);

    });
  });

  searchModalRef.addEventListener('click', e => {
    if (e.target === searchModalRef) {
      closeModal(searchModalRef, formSearchInputRef)
    }
  })
  searchModalRef.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
      closeModal(searchModalRef, formSearchInputRef)
    }
  })

  const closeModal = (modalRef, input) => {
    modalRef.classList.remove('active');
    input.value = "";
  }

  const SpeechRecognition =
    window.SpeechRecognition || window.webkitSpeechRecognition;
  
  if (!SpeechRecognition) {
    alert('Голосовой ввод не поддерживается в этом браузере 😢');
  } else {
    const recognition = new SpeechRecognition();
  
    recognition.lang = 'ru-RU'; // язык
    recognition.interimResults = false; // только финальный текст
    recognition.continuous = false; // одна фраза
  
    formSpeachRef.addEventListener('click', () => {
      console.log('record start');
      recognition.start();
    });
  
    recognition.onresult = e => {
      const text = e.results[0][0].transcript;
      formSearchInputRef.value = text;
      formSearchInputRef.focus();
    };
  
    recognition.onerror = e => {
      console.log('Ошибка:', e.error);
    };
  }
});

