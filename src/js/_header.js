// document.addEventListener('DOMContentLoaded', function () {

// let popupBg = document.querySelector('.popup__bg');
// let popup = document.querySelector('.popup');
// let openPopupButtons = document.querySelectorAll('.open-popup');
// let closePopupButton = document.querySelector('.close-popup');

// openPopupButtons.forEach((button) => {
//     button.addEventListener('click', (e) => {
//         e.preventDefault();
//         popupBg.classList.add('active');
//         popup.classList.add('active');
//     })
// });

// closePopupButton.addEventListener('click',() => {
//     popupBg.classList.remove('active');
//     popup.classList.remove('active');
// });

// document.addEventListener('click', (e) => {
//     if(e.target === popupBg) {
//         popupBg.classList.remove('active');
//         popup.classList.remove('active');
//     }
// });

// });
// document.addEventListener('DOMContentLoaded', function () {
//   const menuItems = document.querySelectorAll('.menu > li');

//   menuItems.forEach(item => {
//     const submenu = item.querySelector('.sub-menu');
//     let timeout;

//     if (!submenu) return;

   
//     item.addEventListener('mouseenter', () => {
//       clearTimeout(timeout);
//       submenu.style.display = 'flex';
//     });

    
//     item.addEventListener('mouseleave', () => {
//       timeout = setTimeout(() => {
//         submenu.style.display = 'none';
//       }, 300); 
//     });

    
//     submenu.addEventListener('mouseenter', () => {
//       clearTimeout(timeout);
//     });

    
//     submenu.addEventListener('mouseleave', () => {
//       timeout = setTimeout(() => {
//         submenu.style.display = 'none';
//       }, 300);
//     });
//   });
// });
document.addEventListener('DOMContentLoaded', function () {
  const toggle = document.getElementById('mobile-menu-toggle');
  if (!toggle) return;

  // Закрытие всего мобильного меню
  const closeEntireMenu = () => {
    toggle.checked = false;
    // Закрыть все кастомные подменю
    document.querySelectorAll('.menu-item-opportunities').forEach(item => {
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

      const parentItem = button.closest('.menu-item-opportunities');
      if (!parentItem) return;

      const submenu = parentItem.querySelector('.mobile-submenu');
      const arrow = button.querySelector('.arrow-icon');

      // Закрыть все остальные подменю
      document.querySelectorAll('.menu-item-opportunities').forEach(item => {
        if (item !== parentItem) {
          item.classList.remove('active');
          const otherSubmenu = item.querySelector('.mobile-submenu');
          const otherArrow = item.querySelector('.arrow-icon');
          if (otherSubmenu) otherSubmenu.classList.remove('active');
          if (otherArrow) otherArrow.classList.remove('rotated');
        }
      });

      // Переключить текущее
      parentItem.classList.toggle('active');
      if (submenu) submenu.classList.toggle('active');
      if (arrow) arrow.classList.toggle('rotated');
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
    const isClickInside =
      e.target.closest('.burger-menu') ||
      e.target.closest('.burger-menu-button') ||
      e.target.closest('.mobile-header-buttons') ||
      e.target.closest('#mobile-menu-toggle');

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