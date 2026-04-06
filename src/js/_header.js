document.addEventListener('DOMContentLoaded', () => {

  const toggle = document.getElementById('mobile-menu-toggle');
  if (toggle) {
    const closeMenu = () => {
      toggle.checked = false;
      document.body.classList.remove('menu-open');
    };

    toggle.addEventListener('change', () => {
      document.body.classList.toggle('menu-open', toggle.checked);
    });

    document.addEventListener('click', e => {
      const inside = e.target.closest(
        '.burger-menu, .burger-menu-button, .mobile-header-buttons, #mobile-menu-toggle'
      );
      if (!inside && toggle.checked) closeMenu();
    });

    document.addEventListener('keydown', e => {
      if (e.key === 'Escape' && toggle.checked) closeMenu();
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

  // document.querySelectorAll('.mobile-header-buttons .search-icon').forEach(el => {
  //   el.addEventListener('click', e => {
  //     e.preventDefault();
  //     alert('Пошук (мобільна)');
  //   });
  // });

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
  const formSpeechRef = formSearch.querySelector('.speech');
  
  formClearRef.addEventListener('click', () => {
    formSearchInputRef.value = "";
    formSearchInputRef.focus();
  })

  document.querySelectorAll('.menu-item-search, .search-icon').forEach(el => {
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
    // alert('Голосовой ввод не поддерживается в этом браузере 😢');
    console.warn('SpeechRecognition API is not supported in this browser.');
  } else {
    const recognition = new SpeechRecognition();
  
    recognition.lang = 'ru-RU'; // язык
    recognition.interimResults = false; // только финальный текст
    recognition.continuous = false; // одна фраза
  
    if (formSpeechRef){
      formSpeechRef.addEventListener('click', () => {
        console.log('record start');
        recognition.start();
      });

    }
  
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

