const btn = document.getElementById('load-more');
const container = document.querySelector('.cards-list');

if (btn && container) {
    btn.addEventListener('click', () => {
        let page = parseInt(btn.dataset.page, 10) + 1;
        const perPage = btn.dataset.perPage;
        const category = btn.dataset.category;
        const categorySlug = btn.dataset.categorySlug;
        const searchTerm = btn.dataset.search || '';
        const textLoadMore = btn.dataset.textLoadmore || 'Load more';
        const textLoading = btn.dataset.textLoading || 'Loading...';
        // dataset values are strings — Boolean('0') is true, so compare explicitly
        const noDesc = btn.dataset.nodesc === '1';
        const cardVersion = btn.dataset.cardVersion || '1';

        btn.disabled = true;
        btn.textContent = textLoading;

        // post_type is derived server-side from current_path — never sent by the client
        const params = new URLSearchParams({
            action: 'load_news',
            _wpnonce: btn.dataset.nonce || '',
            page_num: page,
            per_page: perPage,
            category: category,
            category_slug: categorySlug,
            search: searchTerm,
            nodesc: noDesc ? '1' : '0',  //в карточках может не быть описания
            card_version: cardVersion,
            current_path: window.location.pathname,
            sortby: new URL(window.location).searchParams.get('sortby') || '',
            order: new URL(window.location).searchParams.get('order') || '',
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

                // Если достигли конца — прячем так же, как при первом рендере
                if (resPage >= resTotalPage) {
                    btn.style.display = 'none';
                }

                // -----------------------------
                // Обновляем пагинацию
                // -----------------------------
                const prevLink = document.getElementById('pagination-prev');
                const nextLink = document.getElementById('pagination-next');

                if (prevLink) {
                    prevLink.href = updateQueryString(prevLink.href, 'page_num', resPage - 1);
                    prevLink.setAttribute('data-link-disabled', resPage > 1 ? '0' : '1');
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
                if (searchTerm) {
                    url.searchParams.set('search', searchTerm);
                }
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
