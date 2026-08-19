/**
 * News / search "Load more".
 *
 * Progressive enhancement over server-rendered pagination: the numbered
 * links work without this file, and this only appends the next page in
 * place. Cards come back as rendered HTML from news/v1/posts so the
 * markup, escaping and translations stay owned by PHP.
 *
 * File: inc/news/Assets/news-store.js
 */

import { store } from "@wordpress/interactivity";

const { state, actions } = store("news", {
    // `page` and `maxPages` are deliberately NOT declared here. The runtime
    // populates server state first with a non-overriding merge, then store()
    // merges this object over it *with* override — so redeclaring a hydrated
    // key resets it. Declaring maxPages: 1 made hasMore permanently false and
    // hid the button the moment the page hydrated.
    // See wp-includes/js/dist/script-modules/interactivity/index.js:
    //   populateServerData → deepMerge(st.state, state, false)
    //   store              → deepMerge(target.state, state)
    state: {
        loading: false,
        error: null,

        get hasMore() {
            return (state.page ?? 1) < (state.maxPages ?? 1);
        },

        get buttonLabel() {
            return state.loading ? state.i18n.loading : state.i18n.loadMore;
        },
    },

    actions: {
        *loadMore() {
            if (state.loading || !state.hasMore) {
                return;
            }

            state.loading = true;
            state.error = null;

            const params = new URLSearchParams({
                source: state.source,
                page: String(state.page + 1),
            });

            // Only the display contract travels over the wire — the post
            // types are decided server-side from `source`.
            const optional = {
                slug: state.slug,
                s: state.searchTerm,
                per_page: state.perPage,
                orderby: state.orderby,
                order: state.order,
            };

            for (const [key, value] of Object.entries(optional)) {
                if (value) {
                    params.set(key, String(value));
                }
            }

            try {
                const response = yield fetch(`${state.restUrl}?${params}`);

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const payload = yield response.json();
                const data = payload?.data ?? payload;

                appendCards(data.html);

                state.page = data.page;
                state.maxPages = data.max_pages;

                markPagesLoaded(data.page);
                syncUrl(data.page);
            } catch (e) {
                state.error = state.i18n.error;
            } finally {
                state.loading = false;
            }
        },

        dismissError() {
            state.error = null;
        },
    },
});

/**
 * The results grid lives outside this store's element, so it is looked up
 * by id rather than through getElement().
 */
function appendCards(html) {
    if (!html) {
        return;
    }

    const container = document.getElementById("sw-results");

    if (!container) {
        return;
    }

    container.insertAdjacentHTML("beforeend", html);
}

/**
 * Highlight every page number now on screen, not just the last one.
 *
 * After three clicks of "Show more" the reader is looking at pages 1-3 at
 * once, so 1, 2 and 3 all read as active. Arrows and the ellipsis carry no
 * number and are skipped.
 */
function markPagesLoaded(page) {
    const nav = document.querySelector(".sw-pagination__pages");

    if (!nav) {
        return;
    }

    for (const link of nav.querySelectorAll(".page-numbers")) {
        const number = parseInt(link.textContent.trim(), 10);

        if (!Number.isNaN(number)) {
            link.classList.toggle("is-loaded", number <= page);
        }
    }
}

/**
 * Keep the address bar on the last loaded page so a reload, a share or a
 * back-navigation lands where the reader actually is.
 */
function syncUrl(page) {
    const url = new URL(window.location);
    const base = url.pathname.replace(/\/page\/\d+\/?$/, "/");

    url.pathname = page > 1 ? `${base}page/${page}/` : base;

    window.history.replaceState({}, "", url);
}
