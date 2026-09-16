// Nammu admin — Escritorio: gráficas y desplegables de estadísticas.
        (function() {
            function updateBlock(block) {
                function applyScopePeriod(scope, period) {
                    if (!scope || !period) {
                        return;
                    }
                    block.querySelectorAll('[data-stat-list][data-stat-scope="' + scope + '"][data-stat-period]').forEach(function(list) {
                        list.classList.toggle('d-none', list.getAttribute('data-stat-period') !== period);
                    });
                }

                var periodByScope = {};
                var modeByScope = {};

                block.querySelectorAll('[data-stat-toggle]').forEach(function(group) {
                    var scope = group.getAttribute('data-stat-scope') || '';
                    var modeOverride = group.getAttribute('data-stat-mode-current');
                    var periodOverride = group.getAttribute('data-stat-period-current');
                    var modeBtn = group.querySelector('[data-stat-mode].active');
                    var periodBtn = group.querySelector('[data-stat-period].active');
                    if (modeBtn) {
                        modeByScope[scope] = modeOverride || modeBtn.getAttribute('data-stat-mode');
                    }
                    if (periodBtn) {
                        periodByScope[scope] = periodOverride || periodBtn.getAttribute('data-stat-period');
                    }
                });

                block.querySelectorAll('[data-stat-list]').forEach(function(list) {
                    var scope = list.getAttribute('data-stat-scope') || '';
                    var mode = modeByScope[scope] || null;
                    var period = periodByScope[scope] || null;
                    var match = true;
                    if (mode && list.hasAttribute('data-stat-mode') && list.getAttribute('data-stat-mode') !== mode) {
                        match = false;
                    }
                    if (period && list.hasAttribute('data-stat-period') && list.getAttribute('data-stat-period') !== period) {
                        match = false;
                    }
                    list.classList.toggle('d-none', !match);
                });

                Object.keys(periodByScope).forEach(function(scope) {
                    applyScopePeriod(scope, periodByScope[scope]);
                });

                block.querySelectorAll('table[data-stat-list]').forEach(function(table) {
                    var wrapper = table.closest('.table-responsive');
                    if (!wrapper) {
                        return;
                    }
                    var hasVisibleTable = false;
                    wrapper.querySelectorAll('table[data-stat-list]').forEach(function(item) {
                        if (!item.classList.contains('d-none')) {
                            hasVisibleTable = true;
                        }
                    });
                    wrapper.classList.toggle('d-none', !hasVisibleTable);
                });
            }

            function applyScopePeriod(block, scope, period) {
                if (!scope || !period) {
                    return;
                }
                block.querySelectorAll('[data-stat-list][data-stat-scope="' + scope + '"][data-stat-period]').forEach(function(list) {
                    list.classList.toggle('d-none', list.getAttribute('data-stat-period') !== period);
                });
            }

            document.querySelectorAll('.dashboard-stat-block').forEach(function(block) {
                updateBlock(block);
                block.querySelectorAll('[data-stat-toggle][data-stat-scope]').forEach(function(group) {
                    var scope = group.getAttribute('data-stat-scope') || '';
                    var periodBtn = group.querySelector('[data-stat-period].active');
                    if (periodBtn) {
                        applyScopePeriod(block, scope, periodBtn.getAttribute('data-stat-period'));
                    }
                });
            });

            document.addEventListener('click', function(event) {
                var btn = event.target.closest('[data-stat-mode], [data-stat-period]');
                if (!btn) {
                    return;
                }
                var group = btn.closest('[data-stat-toggle]');
                if (!group) {
                    return;
                }
                event.preventDefault();
                group.querySelectorAll('[data-stat-mode], [data-stat-period]').forEach(function(item) {
                    item.classList.toggle('active', item === btn);
                });
                if (btn.hasAttribute('data-stat-period')) {
                    group.setAttribute('data-stat-period-current', btn.getAttribute('data-stat-period'));
                }
                if (btn.hasAttribute('data-stat-mode')) {
                    group.setAttribute('data-stat-mode-current', btn.getAttribute('data-stat-mode'));
                }
                var block = group.closest('.dashboard-stat-block');
                if (block) {
                    updateBlock(block);
                    var scope = group.getAttribute('data-stat-scope') || '';
                    if (scope && btn.hasAttribute('data-stat-period')) {
                        applyScopePeriod(block, scope, btn.getAttribute('data-stat-period'));
                    }
                }
            });
            // GSC toggle uses CSS radios.
        })();
