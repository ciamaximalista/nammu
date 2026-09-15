// Nammu admin — utilidades de formularios: saneado de slugs y confirmación antes de publicar.
        document.addEventListener('DOMContentLoaded', function() {
            var slugInputs = document.querySelectorAll('[data-slug-input]');
            if (!slugInputs.length) {
                return;
            }
            function sanitizeSlug(value, trimEdges) {
                var normalized = (value || '').toString().toLowerCase();
                normalized = normalized.replace(/[^a-z0-9-]+/g, '-');
                normalized = normalized.replace(/-{2,}/g, '-');
                if (trimEdges) {
                    normalized = normalized.replace(/^-+/, '').replace(/-+$/, '');
                } else {
                    normalized = normalized.replace(/^-+/, '');
                }
                return normalized;
            }
            slugInputs.forEach(function(input) {
                var applySanitizedValue = function() {
                    var sanitized = sanitizeSlug(input.value, false);
                    if (input.value !== sanitized) {
                        input.value = sanitized;
                    }
                };
                var applyTrimmedValue = function() {
                    var sanitized = sanitizeSlug(input.value, true);
                    if (input.value !== sanitized) {
                        input.value = sanitized;
                    }
                };
                input.addEventListener('input', applySanitizedValue);
                input.addEventListener('blur', applyTrimmedValue);
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            var confirmButtons = document.querySelectorAll('[data-confirm-publish]');
            if (!confirmButtons.length) {
                return;
            }
            confirmButtons.forEach(function(button) {
                button.addEventListener('click', function(event) {
                    var label = button.getAttribute('data-confirm-label') || button.textContent || '';
                    label = label.replace(/\s+/g, ' ').trim();
                    var action = label !== '' ? (label.charAt(0).toLowerCase() + label.slice(1)) : 'continuar';
                    if (!window.confirm('¿Estás seguro de querer ' + action + '?')) {
                        event.preventDefault();
                        var form = button.closest('form');
                        if (form) {
                            var notice = form.querySelector('[data-publish-cancelled]');
                            if (notice) {
                                notice.classList.remove('d-none');
                            }
                        }
                    }
                });
            });
        });
