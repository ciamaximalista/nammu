// Nammu admin — pestaña Plantilla: selectores de Google Fonts, cabecera, portada, buscador, suscripción y footer.
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('template-settings');
            if (!form) {
                return;
            }

            const apiKey = form.dataset.googleFontsKey || '';
            const titleSelect = document.getElementById('title_font');
            const bodySelect = document.getElementById('body_font');
            const noteSelect = document.getElementById('note_font');
            const codeSelect = document.getElementById('code_font');
            const quoteSelect = document.getElementById('quote_font');
            const fontsAlert = document.getElementById('fonts-alert');

            const currentTitleFont = titleSelect ? titleSelect.dataset.currentFont || '' : '';
            const currentBodyFont = bodySelect ? bodySelect.dataset.currentFont || '' : '';
            const currentNoteFont = noteSelect ? noteSelect.dataset.currentFont || '' : '';
            const currentCodeFont = codeSelect ? codeSelect.dataset.currentFont || '' : '';
            const currentQuoteFont = quoteSelect ? quoteSelect.dataset.currentFont || '' : '';

            function fillSelect(selectElement, fonts, current) {
                if (!selectElement) return;
                selectElement.innerHTML = '';
                const placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = 'Selecciona una fuente';
                selectElement.appendChild(placeholder);

                let currentFound = false;
                const orderedFonts = Array.isArray(fonts) ? fonts.slice().sort(function(a, b) {
                    return (a.family || '').localeCompare(b.family || '', 'es', { sensitivity: 'base' });
                }) : [];

                orderedFonts.forEach(function(font) {
                    if (!font || !font.family) {
                        return;
                    }
                    const option = document.createElement('option');
                    option.value = font.family;
                    option.textContent = font.family;
                    if (font.family === current) {
                        option.selected = true;
                        currentFound = true;
                    }
                    selectElement.appendChild(option);
                });

                if (current && !currentFound) {
                    const option = document.createElement('option');
                    option.value = current;
                    option.textContent = current + ' (actual)';
                    option.selected = true;
                    selectElement.appendChild(option);
                }
            }

            if (apiKey && (titleSelect || bodySelect || noteSelect || codeSelect || quoteSelect)) {
                fetch('https://www.googleapis.com/webfonts/v1/webfonts?key=' + encodeURIComponent(apiKey) + '&sort=popularity')
                    .then(function(response) {
                        if (!response.ok) {
                            throw new Error('No se pudo cargar Google Fonts (HTTP ' + response.status + ')');
                        }
                        return response.json();
                    })
                    .then(function(data) {
                        const fonts = data && Array.isArray(data.items) ? data.items : [];
                        fillSelect(titleSelect, fonts, currentTitleFont);
                        fillSelect(bodySelect, fonts, currentBodyFont);
                        fillSelect(noteSelect, fonts, currentNoteFont);
                        fillSelect(codeSelect, fonts, currentCodeFont);
                        fillSelect(quoteSelect, fonts, currentQuoteFont);
                    })
                    .catch(function(error) {
                        if (fontsAlert) {
                            fontsAlert.innerHTML = '<div class="alert alert-warning mt-3">No se pudieron cargar las fuentes desde Google Fonts. Verifica tu API Key en Configuración.<br><small>' + error.message + '</small></div>';
                        }
                    });
            } else if (fontsAlert) {
                fontsAlert.innerHTML = '<div class="alert alert-info mt-3">Configura tu API Key de Google Fonts en la pestaña Configuración para elegir fuentes personalizadas.</div>';
            }

            form.querySelectorAll('[data-color-field]').forEach(function(container) {
                const picker = container.querySelector('.template-color-picker');
                const input = container.querySelector('.template-color-input');
                if (!picker || !input) {
                    return;
                }

                picker.addEventListener('input', function() {
                    input.value = picker.value;
                });

                input.addEventListener('input', function() {
                    const value = input.value.trim();
                    if (/^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(value)) {
                        picker.value = value;
                    }
                });
            });

            var clearLogoBtn = document.getElementById('clear-logo-image');
            if (clearLogoBtn) {
                clearLogoBtn.addEventListener('click', function() {
                    var logoInput = document.getElementById('logo_image');
                    if (logoInput) {
                        logoInput.value = '';
                    }
                });
            }

            var clearSocialBtn = document.getElementById('clear-social-image');
            if (clearSocialBtn) {
                clearSocialBtn.addEventListener('click', function() {
                    var socialInput = document.getElementById('social_home_image');
                    if (socialInput) {
                        socialInput.value = '';
                    }
                });
            }
            var clearSocialPodcastBtn = document.getElementById('clear-social-podcast-image');
            if (clearSocialPodcastBtn) {
                clearSocialPodcastBtn.addEventListener('click', function() {
                    var socialPodcastInput = document.getElementById('social_podcast_image');
                    if (socialPodcastInput) {
                        socialPodcastInput.value = '';
                    }
                });
            }

            var layoutOptions = form.querySelectorAll('.home-layout-option');
            function refreshLayoutSelection() {
                layoutOptions.forEach(function(option) {
                    var radio = option.querySelector('input[type="radio"]');
                    option.classList.toggle('active', radio && radio.checked);
                });
            }
            layoutOptions.forEach(function(option) {
                var radio = option.querySelector('input[type="radio"]');
                if (radio) {
                    radio.addEventListener('change', refreshLayoutSelection);
                }
            });
            refreshLayoutSelection();

            var cardStyleOptions = form.querySelectorAll('.home-card-style-option[data-card-style-option]');
            var homeContentOptions = form.querySelectorAll('.home-card-style-option[data-home-content-option]');
            var fullImageOptionsContainer = form.querySelector('[data-full-image-options]');
            var fullImageModeOptions = form.querySelectorAll('.home-card-style-option[data-full-image-mode]');
            var searchModeOptions = form.querySelectorAll('.home-card-style-option[data-search-mode-option]');
            var searchPositionOptions = form.querySelectorAll('.home-card-style-option[data-search-position-option]');
            var searchFloatingOptions = form.querySelectorAll('.home-card-style-option[data-search-floating-option]');
            var searchFediverseFloatingCtaOptions = form.querySelectorAll('.home-card-style-option[data-search-fediverse-floating-cta-option]');
            var subscriptionModeOptions = form.querySelectorAll('.home-card-style-option[data-subscription-mode-option]');
            var subscriptionPositionOptions = form.querySelectorAll('.home-card-style-option[data-subscription-position-option]');
            var subscriptionFloatingOptions = form.querySelectorAll('.home-card-style-option[data-subscription-floating-option]');
            var footerLogoOptions = form.querySelectorAll('.home-card-style-option[data-footer-logo-option]');
            var headerButtonsOptions = form.querySelectorAll('.home-card-style-option[data-header-buttons-option]');
            var searchPositionContainer = form.querySelector('[data-search-position]');
            var subscriptionPositionContainer = form.querySelector('[data-subscription-position]');
            function refreshCardStyleSelection() {
                var activeStyle = '';
                cardStyleOptions.forEach(function(option) {
                    var radio = option.querySelector('input[type="radio"]');
                    option.classList.toggle('active', radio && radio.checked);
                    if (radio && radio.checked) {
                        activeStyle = radio.value;
                    }
                });
                if (fullImageOptionsContainer) {
                    fullImageOptionsContainer.style.display = activeStyle === 'full' ? '' : 'none';
                }
            }
            cardStyleOptions.forEach(function(option) {
                var radio = option.querySelector('input[type="radio"]');
                if (radio) {
                    radio.addEventListener('change', refreshCardStyleSelection);
                }
            });
            refreshCardStyleSelection();
            function refreshHomeContentSelection() {
                homeContentOptions.forEach(function(option) {
                    var radio = option.querySelector('input[type="radio"]');
                    option.classList.toggle('active', radio && radio.checked);
                });
            }
            homeContentOptions.forEach(function(option) {
                var radio = option.querySelector('input[type="radio"]');
                if (radio) {
                    radio.addEventListener('change', refreshHomeContentSelection);
                }
            });
            refreshHomeContentSelection();

            function refreshFullImageModeSelection() {
                fullImageModeOptions.forEach(function(option) {
                    var radio = option.querySelector('input[type="radio"]');
                    option.classList.toggle('active', radio && radio.checked);
                });
            }
            fullImageModeOptions.forEach(function(option) {
                var radio = option.querySelector('input[type="radio"]');
                if (radio) {
                    radio.addEventListener('change', refreshFullImageModeSelection);
                }
            });
            refreshFullImageModeSelection();

            function refreshHeaderButtonsSelection() {
                headerButtonsOptions.forEach(function(option) {
                    var radio = option.querySelector('input[type="radio"]');
                    option.classList.toggle('active', radio && radio.checked);
                });
            }
            headerButtonsOptions.forEach(function(option) {
                var radio = option.querySelector('input[type="radio"]');
                if (radio) {
                    radio.addEventListener('change', refreshHeaderButtonsSelection);
                }
            });
            refreshHeaderButtonsSelection();

            function refreshSearchModeSelection() {
                var activeMode = 'none';
                searchModeOptions.forEach(function(option) {
                    var radio = option.querySelector('input[type="radio"]');
                    var checked = radio && radio.checked;
                    if (checked && radio) {
                        activeMode = radio.value;
                    }
                    option.classList.toggle('active', checked);
                });
                if (searchPositionContainer) {
                    searchPositionContainer.style.display = activeMode === 'none' ? 'none' : '';
                }
            }
            function refreshSearchPositionSelection() {
                searchPositionOptions.forEach(function(option) {
                    var radio = option.querySelector('input[type="radio"]');
                    option.classList.toggle('active', radio && radio.checked);
                });
            }
            function refreshSearchFloatingSelection() {
                searchFloatingOptions.forEach(function(option) {
                    var radio = option.querySelector('input[type="radio"]');
                    option.classList.toggle('active', radio && radio.checked);
                });
            }
            function refreshSearchFediverseFloatingCtaSelection() {
                searchFediverseFloatingCtaOptions.forEach(function(option) {
                    var radio = option.querySelector('input[type="radio"]');
                    option.classList.toggle('active', radio && radio.checked);
                });
            }
            function refreshFooterLogoSelection() {
                footerLogoOptions.forEach(function(option) {
                    var radio = option.querySelector('input[type="radio"]');
                    option.classList.toggle('active', radio && radio.checked);
                });
            }
            searchModeOptions.forEach(function(option) {
                var radio = option.querySelector('input[type="radio"]');
                if (radio) {
                    radio.addEventListener('change', function() {
                        refreshSearchModeSelection();
                        refreshSearchPositionSelection();
                    });
                }
            });
            searchPositionOptions.forEach(function(option) {
                var radio = option.querySelector('input[type="radio"]');
                if (radio) {
                    radio.addEventListener('change', refreshSearchPositionSelection);
                }
            });
            refreshSearchModeSelection();
            refreshSearchPositionSelection();
            searchFloatingOptions.forEach(function(option) {
                var radio = option.querySelector('input[type="radio"]');
                if (radio) {
                    radio.addEventListener('change', refreshSearchFloatingSelection);
                }
            });
            refreshSearchFloatingSelection();
            searchFediverseFloatingCtaOptions.forEach(function(option) {
                var radio = option.querySelector('input[type="radio"]');
                if (radio) {
                    radio.addEventListener('change', refreshSearchFediverseFloatingCtaSelection);
                }
            });
            refreshSearchFediverseFloatingCtaSelection();

            function refreshSubscriptionModeSelection() {
                var activeMode = 'none';
                subscriptionModeOptions.forEach(function(option) {
                    var radio = option.querySelector('input[type="radio"]');
                    var checked = radio && radio.checked;
                    if (checked && radio) {
                        activeMode = radio.value;
                    }
                    option.classList.toggle('active', checked);
                });
                if (subscriptionPositionContainer) {
                    subscriptionPositionContainer.style.display = activeMode === 'none' ? 'none' : '';
                }
            }
            function refreshSubscriptionPositionSelection() {
                subscriptionPositionOptions.forEach(function(option) {
                    var radio = option.querySelector('input[type="radio"]');
                    option.classList.toggle('active', radio && radio.checked);
                });
            }
            function refreshSubscriptionFloatingSelection() {
                subscriptionFloatingOptions.forEach(function(option) {
                    var radio = option.querySelector('input[type="radio"]');
                    option.classList.toggle('active', radio && radio.checked);
                });
            }
            subscriptionModeOptions.forEach(function(option) {
                var radio = option.querySelector('input[type="radio"]');
                if (radio) {
                    radio.addEventListener('change', function() {
                        refreshSubscriptionModeSelection();
                        refreshSubscriptionPositionSelection();
                    });
                }
            });
            subscriptionPositionOptions.forEach(function(option) {
                var radio = option.querySelector('input[type="radio"]');
                if (radio) {
                    radio.addEventListener('change', refreshSubscriptionPositionSelection);
                }
            });
            subscriptionFloatingOptions.forEach(function(option) {
                var radio = option.querySelector('input[type="radio"]');
                if (radio) {
                    radio.addEventListener('change', refreshSubscriptionFloatingSelection);
                }
            });
            refreshSubscriptionModeSelection();
            refreshSubscriptionPositionSelection();
            refreshSubscriptionFloatingSelection();

            footerLogoOptions.forEach(function(option) {
                var radio = option.querySelector('input[type="radio"]');
                if (radio) {
                    radio.addEventListener('change', refreshFooterLogoSelection);
                }
            });
            refreshFooterLogoSelection();

            var blocksOptions = form.querySelectorAll('.home-card-style-option[data-blocks-option]');
            function refreshBlocksSelection() {
                blocksOptions.forEach(function(option) {
                    var radio = option.querySelector('input[type="radio"]');
                    option.classList.toggle('active', radio && radio.checked);
                });
            }
            blocksOptions.forEach(function(option) {
                var radio = option.querySelector('input[type="radio"]');
                if (radio) {
                    radio.addEventListener('change', refreshBlocksSelection);
                }
            });
            refreshBlocksSelection();

            var firstRowToggle = document.getElementById('home_first_row_enabled');
            var firstRowOptions = document.querySelector('[data-first-row-options]');
            var firstRowFill = document.querySelector('[data-first-row-fill]');
            var firstRowAlign = document.querySelector('[data-first-row-align]');
            var firstRowStyle = document.querySelector('[data-first-row-style]');
            function toggleFirstRowOptions() {
                var show = firstRowToggle && firstRowToggle.checked;
                if (firstRowOptions) {
                    firstRowOptions.style.display = show ? '' : 'none';
                }
                if (firstRowFill) {
                    firstRowFill.style.display = show ? '' : 'none';
                }
                if (firstRowAlign) {
                    var colsRadio = firstRowOptions ? firstRowOptions.querySelector('input[name="home_first_row_columns"]:checked') : null;
                    var showAlign = show && colsRadio && parseInt(colsRadio.value, 10) === 1;
                    firstRowAlign.style.display = showAlign ? '' : 'none';
                }
                if (firstRowStyle) {
                    var colsRadioStyle = firstRowOptions ? firstRowOptions.querySelector('input[name="home_first_row_columns"]:checked') : null;
                    var showStyle = show && colsRadioStyle && parseInt(colsRadioStyle.value, 10) === 1;
                    firstRowStyle.style.display = showStyle ? '' : 'none';
                }
                if (!show && firstRowOptions) {
                    var mainChecked = form.querySelector('input[name="home_columns"]:checked');
                    var firstRowRadios = firstRowOptions.querySelectorAll('input[name="home_first_row_columns"]');
                    if (mainChecked) {
                        firstRowRadios.forEach(function(radio) {
                            var isActive = radio.value === mainChecked.value;
                            radio.checked = isActive;
                            var label = radio.closest('.home-layout-option');
                            if (label) {
                                label.classList.toggle('active', isActive);
                            }
                        });
                    }
                }
            }
            if (firstRowToggle) {
                firstRowToggle.addEventListener('change', toggleFirstRowOptions);
            }
            if (firstRowOptions) {
                var firstRowRadios = firstRowOptions.querySelectorAll('input[name="home_first_row_columns"]');
                firstRowRadios.forEach(function(radio) {
                    radio.addEventListener('change', function() {
                        firstRowOptions.querySelectorAll('.home-layout-option').forEach(function(opt) {
                            var r = opt.querySelector('input[type="radio"]');
                            opt.classList.toggle('active', r && r.checked);
                        });
                        toggleFirstRowOptions();
                    });
                });
            }
            toggleFirstRowOptions();

            var entryTocToggle = form.querySelector('[data-entry-toc-toggle]');
            var entryTocOptions = form.querySelector('[data-entry-toc-options]');
            if (entryTocToggle && entryTocOptions) {
                function refreshEntryTocOptions() {
                    var checked = entryTocToggle.querySelector('input[name="entry_toc_auto"]:checked');
                    var shouldShow = checked && checked.value === 'on';
                    entryTocOptions.style.display = shouldShow ? '' : 'none';
            }
            entryTocToggle.querySelectorAll('input[name="entry_toc_auto"]').forEach(function(radio) {
                radio.addEventListener('change', refreshEntryTocOptions);
            });
            refreshEntryTocOptions();

            var firstRowToggle = form.querySelector('#home_first_row_enabled');
            var firstRowOptions = form.querySelector('[data-first-row-options]');
            var firstRowFill = form.querySelector('[data-first-row-fill]');
            function toggleFirstRowOptions() {
                var show = firstRowToggle && firstRowToggle.checked;
                if (firstRowOptions) {
                    firstRowOptions.style.display = show ? '' : 'none';
                }
                if (firstRowFill) {
                    firstRowFill.style.display = show ? '' : 'none';
                }
                if (!show && firstRowOptions) {
                    var mainChecked = form.querySelector('input[name=\"home_columns\"]:checked');
                    var firstRowRadios = firstRowOptions.querySelectorAll('input[name=\"home_first_row_columns\"]');
                    if (mainChecked) {
                        firstRowRadios.forEach(function(radio) {
                            var isActive = radio.value === mainChecked.value;
                            radio.checked = isActive;
                            var label = radio.closest('.home-layout-option');
                            if (label) {
                                label.classList.toggle('active', isActive);
                            }
                        });
                    }
                }
            }
            if (firstRowToggle) {
                firstRowToggle.addEventListener('change', toggleFirstRowOptions);
            }
            if (firstRowOptions) {
                var firstRowRadios = firstRowOptions.querySelectorAll('input[name=\"home_first_row_columns\"]');
                firstRowRadios.forEach(function(radio) {
                    radio.addEventListener('change', function() {
                        firstRowOptions.querySelectorAll('.home-layout-option').forEach(function(opt) {
                            var r = opt.querySelector('input[type=\"radio\"]');
                            opt.classList.toggle('active', r && r.checked);
                        });
                    });
                });
            }
            toggleFirstRowOptions();
            }

            var postsInput = document.getElementById('home_per_page');
            var postsAllToggle = document.getElementById('home_per_page_all');
            if (postsInput && postsAllToggle) {
                if (!postsInput.dataset.lastValue) {
                    postsInput.dataset.lastValue = postsInput.value || '';
                }
                postsInput.addEventListener('input', function() {
                    postsInput.dataset.lastValue = postsInput.value;
                });
                function syncPostsInputState() {
                    if (postsAllToggle.checked) {
                        if (postsInput.value !== '') {
                            postsInput.dataset.lastValue = postsInput.value;
                        }
                        postsInput.value = '';
                        postsInput.setAttribute('disabled', 'disabled');
                    } else {
                        postsInput.removeAttribute('disabled');
                        if (postsInput.value === '' && postsInput.dataset.lastValue) {
                            postsInput.value = postsInput.dataset.lastValue;
                        }
                    }
                }
                postsAllToggle.addEventListener('change', syncPostsInputState);
                syncPostsInputState();
            }

            var headerOptions = form.querySelectorAll('.home-header-option');
            var headerGraphicContainer = form.querySelector('[data-header-graphic]');
            var headerGraphicModeContainer = form.querySelector('[data-header-graphic-mode]');
            var headerTextContainer = form.querySelector('[data-header-text]');
            var headerOrderContainer = form.querySelector('[data-header-order]');
            var headerTypeInputs = form.querySelectorAll('input[name="home_header_type"]');

            function getHeaderGraphicModeOptions() {
                if (!headerGraphicModeContainer) {
                    return [];
                }
                return Array.prototype.slice.call(headerGraphicModeContainer.querySelectorAll('.home-card-style-option[data-header-mode-option]'));
            }

            function getHeaderTextOptions() {
                if (!headerTextContainer) {
                    return [];
                }
                return Array.prototype.slice.call(headerTextContainer.querySelectorAll('.home-card-style-option[data-header-text-option]'));
            }

            function getHeaderOrderOptions() {
                if (!headerOrderContainer) {
                    return [];
                }
                return Array.prototype.slice.call(headerOrderContainer.querySelectorAll('.home-card-style-option[data-header-order-option]'));
            }

            function refreshHeaderSelection() {
                var activeType = 'none';
                headerOptions.forEach(function(option) {
                    var radio = option.querySelector('input[type="radio"]');
                    var checked = radio && radio.checked;
                    option.classList.toggle('active', checked);
                    if (checked && radio) {
                        activeType = radio.value;
                    }
                });

                var showImageConfig = (activeType === 'graphic' || activeType === 'mixed');
                var showTextConfig = (activeType === 'text' || activeType === 'mixed');
                if (headerGraphicContainer) {
                    headerGraphicContainer.style.display = showImageConfig ? '' : 'none';
                }
                if (headerGraphicModeContainer) {
                    headerGraphicModeContainer.style.display = showImageConfig ? '' : 'none';
                }
                if (headerTextContainer) {
                    headerTextContainer.style.display = showTextConfig ? '' : 'none';
                }
                if (headerOrderContainer) {
                    headerOrderContainer.style.display = activeType === 'mixed' ? '' : 'none';
                }

                refreshHeaderModeSelection();
                refreshHeaderTextSelection();
                refreshHeaderOrderSelection();
            }

            headerTypeInputs.forEach(function(input) {
                input.addEventListener('change', refreshHeaderSelection);
            });

            function refreshHeaderModeSelection() {
                getHeaderGraphicModeOptions().forEach(function(option) {
                    var radio = option.querySelector('input[type="radio"]');
                    option.classList.toggle('active', radio && radio.checked);
                });
            }

            getHeaderGraphicModeOptions().forEach(function(option) {
                var radio = option.querySelector('input[type="radio"]');
                if (radio) {
                    radio.addEventListener('change', refreshHeaderModeSelection);
                }
            });

            function refreshHeaderTextSelection() {
                getHeaderTextOptions().forEach(function(option) {
                    var radio = option.querySelector('input[type="radio"]');
                    option.classList.toggle('active', radio && radio.checked);
                });
            }

            getHeaderTextOptions().forEach(function(option) {
                var radio = option.querySelector('input[type="radio"]');
                if (radio) {
                    radio.addEventListener('change', refreshHeaderTextSelection);
                }
            });

            function refreshHeaderOrderSelection() {
                getHeaderOrderOptions().forEach(function(option) {
                    var radio = option.querySelector('input[type="radio"]');
                    option.classList.toggle('active', radio && radio.checked);
                });
            }

            getHeaderOrderOptions().forEach(function(option) {
                var radio = option.querySelector('input[type="radio"]');
                if (radio) {
                    radio.addEventListener('change', refreshHeaderOrderSelection);
                }
            });

            refreshHeaderSelection();

            var cornerOptions = form.querySelectorAll('.home-card-style-option[data-corners-option]');
            function refreshCornerSelection() {
                cornerOptions.forEach(function(option) {
                    var radio = option.querySelector('input[type="radio"]');
                    option.classList.toggle('active', radio && radio.checked);
                });
            }
            cornerOptions.forEach(function(option) {
                var radio = option.querySelector('input[type="radio"]');
                if (radio) {
                    radio.addEventListener('change', refreshCornerSelection);
                }
            });
            refreshCornerSelection();

            var clearHeaderBtn = document.getElementById('clear-header-image');
            if (clearHeaderBtn) {
                clearHeaderBtn.addEventListener('click', function() {
                    var headerInput = document.getElementById('home_header_image');
                    if (headerInput) {
                        headerInput.value = '';
                    }
                });
            }
        });
