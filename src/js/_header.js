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