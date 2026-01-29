const btn = document.getElementById('load-more');
const container = document.querySelector('.cards-list');

if (btn && container) {

    btn.addEventListener('click', () => {

        let page = parseInt(btn.dataset.page, 10) + 1;
        let perPage = btn.dataset.perPage;
        let category = btn.dataset.category;
        let postType = btn.dataset.postType

        btn.disabled = true;
        btn.textContent = 'Loading...';

        const params = new URLSearchParams({
            // load-news - искать динамически из url, наверное переделать на slug тсраницы
            action: 'load_news',
            page: page,
            per_page: perPage,
            category: category,
        });
        console.log(THEME_AJAX.url + '?' + params.toString());
        fetch(THEME_AJAX.url + '?' + params.toString())
            .then(r => r.json())
            .then(res => {

                if (!res.success) return;
                console.log(res);

                container.insertAdjacentHTML('beforeend', res.data.html);

                btn.dataset.page = page;
                btn.disabled = false;
                btn.textContent = 'Load more';

                if (page >= res.data.max_pages) {
                    btn.remove();
                }

                // update URL
                const url = new URL(window.location);
                url.searchParams.set('post_type', postType);
                url.searchParams.set('page_num', page);
                url.searchParams.set('per_page', perPage);
                
                history.replaceState({}, '', url);
            });
    });
}
