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
  const opportunitiesBtn = document.getElementById('opportunities-button-mobile');
  const mobileSubmenu = document.getElementById('mobile-submenu');
  const burgerMenu = document.querySelector('.burger-menu');
  const mobileHeaderButtons = document.querySelector('.mobile-header-buttons');

  if (!toggle || !burgerMenu) return;

  const closeMenu = () => {
    toggle.checked = false;
    if (opportunitiesBtn) opportunitiesBtn.classList.remove('active');
    if (mobileSubmenu) mobileSubmenu.classList.remove('active');
    if (mobileHeaderButtons) mobileHeaderButtons.classList.remove('active');
  };

  
  if (opportunitiesBtn && mobileSubmenu) {
    opportunitiesBtn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      opportunitiesBtn.classList.toggle('active');
      mobileSubmenu.classList.toggle('active');
    });
  }

  
  document.addEventListener('click', (e) => {
    const isClickInside = 
      toggle.contains(e.target) ||
      document.querySelector('.burger-menu-button')?.contains(e.target) ||
      burgerMenu.contains(e.target) ||
      mobileHeaderButtons?.contains(e.target);

    if (!isClickInside && toggle.checked) {
      closeMenu();
    }
  });
});