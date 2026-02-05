console.log("~!!!!!");
const btn = document.getElementById('load-more');
const container = document.querySelector('.cards-list');


// if (!btn) return;
// if (!btn && !btn.dataset) {

// console.log('textLoadMore: ', textLoadMore);
// console.log('textLoading: ', textLoading);

// if (btn && btn.dataset?.textLoadmore != null && btn.dataset?.textLoading != null && container) {
if (btn?.dataset?.textLoadmore && container) {
    const textLoadMore = btn.dataset.textLoadmore;
    const textLoading = btn.dataset.textLoading;
    btn.addEventListener('click', () => {
        let page = parseInt(btn.dataset.page, 10) + 1;
        const perPage = btn.dataset.perPage;
        const category = btn.dataset.category;
        const postType = btn.dataset.postType;

        btn.disabled = true;
        btn.textContent = textLoading;

        const params = new URLSearchParams({
            action: 'load_news',
            page: page,
            per_page: perPage,
            category: category,
        });

        fetch(THEME_AJAX.url + '?' + params.toString())
            .then(r => r.json())
            .then(res => {
                const resTotalPage = res.data.total_pages;
                const resPage = res.data.page;

                if (!res.success) return;

                // Добавляем карточки
                container.insertAdjacentHTML('beforeend', res.data.html);

                // Обновляем dataset страницы
                btn.dataset.page = page;
                btn.disabled = false;
                btn.textContent = textLoadMore;

                // Если достигли конца
                if (resPage >= resTotalPage) {
                    btn.classList.add('hidden');
                }

                // -----------------------------
                // Обновляем пагинацию
                // -----------------------------
                const prevLink = document.getElementById('pagination-prev');
                const nextLink = document.getElementById('pagination-next');

                if (prevLink) {
                    prevLink.href = updateQueryString(prevLink.href, 'page_num', resPage - 1);
                    prevLink.setAttribute('data-link-disabled', resPage > 1 ? 0 : 1);
                    console.log('resPage', resPage, typeof resPage);
                    // prevLink.classList.toggle('link-disabled', page < 1);
                }

                if (nextLink) {
                    nextLink.href = updateQueryString(nextLink.href, 'page_num', resPage + 1);
                    nextLink.setAttribute('data-link-disabled', resPage >= resTotalPage ? '1' : '0');
                }

                // Номера страниц
                const startNum = resPage - 1;
                for (let i = 1; i <= 3; i++) {
                    const pageLink = document.getElementById(`pagination-${i}`);
                    if (!pageLink) continue;

                    const newPageNum = startNum + i - 1;

                    pageLink.innerText = newPageNum;
                    pageLink.href = updateQueryString(pageLink.href, 'page_num', newPageNum);

                    // Класс активной страницы — последняя загруженная
                    pageLink.setAttribute('data-is-active', newPageNum <= resPage ? '1' : '0');

                    // Деактивируем если больше total_pages
                    pageLink.setAttribute('data-link-disabled', newPageNum > resTotalPage ? '1' : '0');

                }

                // -----------------------------
                // Обновляем URL браузера
                // -----------------------------
                const url = new URL(window.location);
                url.searchParams.set('page_num', page);
                url.searchParams.set('per_page', perPage);
                history.replaceState({}, '', url);
            });
    });
}

// -----------------------------
// Хелпер для изменения query param в ссылке
// -----------------------------
function updateQueryString(url, key, value) {
    const u = new URL(url, window.location.origin);
    u.searchParams.set(key, value);
    return u.toString();
}
