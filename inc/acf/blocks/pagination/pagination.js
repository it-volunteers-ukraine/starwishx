function updatePagination(page, perPage, totalPages) {
    const pagination = document.querySelector('.pagination');
    if (!pagination) return;

    // Prev / Next ссылки
    const prevLink = pagination.querySelector('a[rel="prev"]');
    const nextLink = pagination.querySelector('a[rel="next"]');

    if (prevLink) {
        prevLink.href = updateQueryString(prevLink.href, 'page_num', page - 1);
        prevLink.classList.toggle('link-disabled', page <= 1);
    }

    if (nextLink) {
        nextLink.href = updateQueryString(nextLink.href, 'page_num', page + 1);
        nextLink.classList.toggle('link-disabled', page >= totalPages);
    }

    // Номера страниц
    const numberLinks = pagination.querySelectorAll('a:not([rel])');
    const startNum = page === 1 ? 1 : page - 1;
    let i = 0;
    numberLinks.forEach(link => {
        const pageNum = startNum + i;
        link.textContent = pageNum;
        link.href = updateQueryString(link.href, 'page_num', pageNum);

        // Добавляем класс активной страницы только для **текущей последней загруженной**
        if (pageNum === page) {
            link.classList.add('selected');
        } else {
            link.classList.remove('selected');
        }

        // Деактивируем если > totalPages
        link.classList.toggle('link-disabled', pageNum > totalPages);
        i++;
    });
}

// Хелпер для изменения query param в ссылке
function updateQueryString(url, key, value) {
    const u = new URL(url, window.location.origin);
    u.searchParams.set(key, value);
    return u.toString();
}
