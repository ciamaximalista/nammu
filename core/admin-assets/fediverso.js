// Nammu admin — pestaña Fediverso: pestañas AJAX, refresco de fragmentos, acciones sobre publicaciones y formularios.
    (function () {
        var root = document.querySelector('[data-fediverse-admin]');
        if (!root || root.dataset.fediverseBound === '1') {
            return;
        }
        root.dataset.fediverseBound = '1';

        var panel = root.querySelector('[data-fediverse-tab-panel]');
        var pollTimer = null;
        var timelineScrollStorageKey = 'nammuFediverseTimelineScroll';

        function currentTab() {
            return root.getAttribute('data-active-tab') || 'home';
        }

        function setActiveTab(tab) {
            root.setAttribute('data-active-tab', tab);
            if (panel) {
                panel.setAttribute('data-fediverse-tab', tab);
            }
            root.querySelectorAll('[data-fediverse-tab-link]').forEach(function (link) {
                link.classList.toggle('active', link.getAttribute('data-fediverse-tab-link') === tab);
            });
        }

        function extractPanel(html) {
            var startMarker = '<!-- FEDIVERSE_TAB_PANEL_START -->';
            var endMarker = '<!-- FEDIVERSE_TAB_PANEL_END -->';
            var start = html.indexOf(startMarker);
            var end = html.indexOf(endMarker);
            if (start === -1 || end === -1 || end <= start) {
                return html;
            }
            return html.slice(start + startMarker.length, end).trim();
        }

        function buildUrl(tab, extraParams) {
            var url = new URL(window.location.href);
            url.searchParams.set('page', 'fediverso');
            url.searchParams.set('tab', tab);
            Object.keys(extraParams || {}).forEach(function (key) {
                if (extraParams[key] === null) {
                    url.searchParams.delete(key);
                } else {
                    url.searchParams.set(key, extraParams[key]);
                }
            });
            return url.toString();
        }

        function restoreTimelineScrollAfterAction() {
            var stored = null;
            try {
                stored = sessionStorage.getItem(timelineScrollStorageKey);
                if (stored !== null) {
                    sessionStorage.removeItem(timelineScrollStorageKey);
                }
            } catch (error) {
                stored = null;
            }
            if (!stored) {
                return;
            }
            try {
                var payload = JSON.parse(stored);
                var currentUrl = window.location.pathname + window.location.search;
                if (!payload || payload.url !== currentUrl) {
                    return;
                }
                var y = Math.max(0, parseInt(payload.scrollY, 10) || 0);
                window.requestAnimationFrame(function () {
                    window.scrollTo(0, y);
                });
            } catch (error) {
            }
        }

        function rememberTimelineScrollForAction(form) {
            if (!form || !form.closest('.fediverse-timeline')) {
                return;
            }
            var actionNames = [
                'fediverse_like_item',
                'fediverse_unlike_item',
                'fediverse_boost_item',
                'fediverse_unboost_item',
                'fediverse_reply_item',
                'fediverse_delete_reply_item',
                'fediverse_hide_incoming_reply'
            ];
            var hasTimelineAction = actionNames.some(function (name) {
                return !!form.querySelector('[name="' + name + '"]');
            });
            if (!hasTimelineAction) {
                return;
            }
            try {
                sessionStorage.setItem(timelineScrollStorageKey, JSON.stringify({
                    url: window.location.pathname + window.location.search,
                    scrollY: window.scrollY || window.pageYOffset || 0
                }));
            } catch (error) {
            }
        }

        function scrollToPageTop() {
            window.requestAnimationFrame(function () {
                window.scrollTo(0, 0);
            });
        }

        function loadTab(tab, pushState, extraParams, knownVersion, scrollMode) {
            if (!panel) {
                return;
            }
            panel.setAttribute('aria-busy', 'true');
            var params = Object.assign({fediverse_fragment: '1'}, extraParams || {});
            return fetch(buildUrl(tab, params), {
                headers: {'X-Requested-With': 'XMLHttpRequest'},
                credentials: 'same-origin'
            }).then(function (response) {
                return Promise.all([response.text(), response.headers.get('X-Fediverse-Version') || knownVersion || '']);
            }).then(function (payload) {
                var html = payload[0];
                var responseVersion = payload[1];
                panel.innerHTML = extractPanel(html);
                panel.setAttribute('aria-busy', 'false');
                setActiveTab(tab);
                root.setAttribute('data-active-version', responseVersion || '');
                if (pushState) {
                    var nextUrl = new URL(window.location.href);
                    nextUrl.searchParams.set('page', 'fediverso');
                    nextUrl.searchParams.set('tab', tab);
                    if (extraParams && extraParams.timeline_page) {
                        nextUrl.searchParams.set('timeline_page', extraParams.timeline_page);
                    } else {
                        nextUrl.searchParams.delete('timeline_page');
                    }
                    window.history.pushState({fediverseTab: tab, fediverseParams: extraParams || {}}, '', nextUrl.toString());
                }
                if (scrollMode === 'top') {
                    scrollToPageTop();
                }
            }).catch(function () {
                panel.setAttribute('aria-busy', 'false');
            });
        }

        function pollState() {
            fetch(buildUrl(currentTab(), {fediverse_state: '1'}), {
                headers: {'X-Requested-With': 'XMLHttpRequest'},
                credentials: 'same-origin'
            }).then(function (response) {
                return response.json();
            }).then(function (payload) {
                var versions = payload.versions || {};
                var tab = currentTab();
                if (!versions[tab]) {
                    return;
                }
                var currentVersion = root.getAttribute('data-active-version') || '';
                if (currentVersion !== versions[tab]) {
                    var pageParam = null;
                    if (tab === 'home') {
                        var url = new URL(window.location.href);
                        pageParam = url.searchParams.get('timeline_page') || null;
                    }
                    loadTab(tab, false, pageParam ? {timeline_page: pageParam} : {}, versions[tab]);
                }
            }).catch(function () {
            }).finally(function () {
                window.clearTimeout(pollTimer);
                pollTimer = window.setTimeout(pollState, 12000);
            });
        }

        root.addEventListener('click', function (event) {
            var tabLink = event.target.closest('[data-fediverse-tab-link]');
            if (tabLink) {
                event.preventDefault();
                loadTab(tabLink.getAttribute('data-fediverse-tab-link') || 'home', true, {}, null, 'top');
                return;
            }
            var paginationLink = event.target.closest('.fediverse-pagination a');
            if (paginationLink && panel && currentTab() === 'home') {
                event.preventDefault();
                var pageUrl = new URL(paginationLink.href, window.location.origin);
                var timelinePage = pageUrl.searchParams.get('timeline_page') || '1';
                loadTab('home', true, {timeline_page: timelinePage}, null, 'top');
            }
        });

        root.addEventListener('submit', function (event) {
            if (event.defaultPrevented) {
                return;
            }
            rememberTimelineScrollForAction(event.target);
        });

        window.addEventListener('popstate', function () {
            var url = new URL(window.location.href);
            if (url.searchParams.get('page') !== 'fediverso') {
                return;
            }
            var tab = url.searchParams.get('tab') || 'home';
            var extraParams = {};
            if (tab === 'home' && url.searchParams.get('timeline_page')) {
                extraParams.timeline_page = url.searchParams.get('timeline_page');
            }
            loadTab(tab, false, extraParams);
        });

        restoreTimelineScrollAfterAction();
        pollState();
    })();
