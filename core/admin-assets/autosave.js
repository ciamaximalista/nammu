// Nammu admin — autoguardado local del editor (localStorage) y restauración de borradores.
        document.addEventListener('DOMContentLoaded', function() {
            var prefix = 'nammuEditorAutosave:';

            // El envío del editor no llegó a guardarse (sesión caducada): las copias locales marcadas como enviadas
            // vuelven a ser recuperables. Se ejecuta tanto en la pantalla de acceso como al volver al editor.
            if (document.querySelector('[data-nammu-submission-lost]')) {
                try {
                    Object.keys(localStorage).forEach(function(key) {
                        if (key.indexOf(prefix) !== 0) {
                            return;
                        }
                        var payload = JSON.parse(localStorage.getItem(key) || '{}');
                        if (payload && payload.submitted_at) {
                            delete payload.submitted_at;
                            localStorage.setItem(key, JSON.stringify(payload));
                        }
                    });
                } catch (error) {
                }
            }

            var form = null;
            var context = '';
            if (document.getElementById('content_edit')) {
                form = document.getElementById('content_edit').closest('form');
                context = 'edit';
            } else if (document.getElementById('content_publish')) {
                form = document.getElementById('content_publish').closest('form');
                context = 'publish';
            }
            if (!form || !context) {
                return;
            }

            // Latido: mantiene viva la sesión de PHP mientras el editor está abierto, para que un texto largo no se
            // encuentre con la pantalla de acceso al pulsar Guardar.
            window.setInterval(function() {
                try {
                    fetch('admin.php?session_ping=1', { credentials: 'same-origin', cache: 'no-store' }).catch(function() {});
                } catch (error) {
                }
            }, 5 * 60 * 1000);

            var maxAge = 14 * 24 * 60 * 60 * 1000;
            var fieldNames = [
                'title',
                'type',
                'category',
                'date',
                'publish_at_date',
                'publish_at_time',
                'image',
                'description',
                'content',
                'filename',
                'new_filename',
                'related_slugs',
                'lang',
                'page_visibility',
                'audio',
                'video',
                'audio_length',
                'audio_duration',
                'social_broadcast_image'
            ];

            function storageAvailable() {
                try {
                    var testKey = prefix + 'test';
                    localStorage.setItem(testKey, '1');
                    localStorage.removeItem(testKey);
                    return true;
                } catch (error) {
                    return false;
                }
            }

            if (!storageAvailable()) {
                return;
            }

            function fieldValue(name) {
                var field = form.querySelector('[name="' + name + '"]');
                if (!field) {
                    return '';
                }
                if (field.type === 'radio') {
                    var checked = form.querySelector('[name="' + name + '"]:checked');
                    return checked ? checked.value : '';
                }
                return field.value || '';
            }

            function collectFields() {
                var fields = {};
                fieldNames.forEach(function(name) {
                    if (form.querySelector('[name="' + name + '"]')) {
                        fields[name] = fieldValue(name);
                    }
                });
                return fields;
            }

            function hasMeaningfulContent(fields) {
                return ['title', 'description', 'content', 'filename', 'new_filename'].some(function(name) {
                    return fields[name] && fields[name].toString().trim() !== '';
                });
            }

            function currentStorageKey() {
                var params = new URLSearchParams(window.location.search);
                var page = params.get('page') || context;
                var file = fieldValue('filename') || params.get('file') || '';
                if (context === 'publish') {
                    file = 'new';
                }
                return prefix + window.location.pathname + ':' + page + ':' + file;
            }

            function pruneOldAutosaves() {
                var now = Date.now();
                Object.keys(localStorage).forEach(function(key) {
                    if (key.indexOf(prefix) !== 0) {
                        return;
                    }
                    try {
                        var payload = JSON.parse(localStorage.getItem(key) || '{}');
                        var savedAt = parseInt(payload.saved_at || '0', 10) || 0;
                        if (!savedAt || now - savedAt > maxAge) {
                            localStorage.removeItem(key);
                        }
                    } catch (error) {
                        localStorage.removeItem(key);
                    }
                });
            }

            function saveAutosave(extra) {
                var fields = collectFields();
                if (!hasMeaningfulContent(fields)) {
                    return;
                }
                var now = extra && extra.submitted_at ? extra.submitted_at : Date.now();
                var payload = {
                    context: context,
                    href: window.location.href,
                    saved_at: now,
                    fields: fields
                };
                if (extra && extra.submitted_at) {
                    payload.submitted_at = extra.submitted_at;
                }
                try {
                    localStorage.setItem(currentStorageKey(), JSON.stringify(payload));
                } catch (error) {
                    // El autosave local no debe bloquear la edicion normal.
                }
            }

            function sameFields(fields) {
                return Object.keys(fields || {}).every(function(name) {
                    return fieldValue(name) === (fields[name] || '');
                });
            }

            function restoreFields(fields) {
                Object.keys(fields || {}).forEach(function(name) {
                    var inputs = form.querySelectorAll('[name="' + name + '"]');
                    if (!inputs.length) {
                        return;
                    }
                    inputs.forEach(function(input) {
                        if (input.type === 'radio') {
                            input.checked = input.value === fields[name];
                        } else {
                            input.value = fields[name] || '';
                        }
                        try {
                            input.dispatchEvent(new Event('input', { bubbles: true }));
                            input.dispatchEvent(new Event('change', { bubbles: true }));
                        } catch (error) {
                        }
                    });
                });
                if (fields.type) {
                    var typeButton = form.querySelector('[data-type-option="' + fields.type + '"]');
                    if (typeButton) {
                        typeButton.click();
                    }
                }
            }

            function maybeRestoreAutosave() {
                var raw = localStorage.getItem(currentStorageKey());
                if (!raw) {
                    return;
                }
                var payload = null;
                try {
                    payload = JSON.parse(raw);
                } catch (error) {
                    localStorage.removeItem(currentStorageKey());
                    return;
                }
                var savedAt = parseInt(payload.saved_at || '0', 10) || 0;
                var submittedAt = parseInt(payload.submitted_at || '0', 10) || 0;
                if (!savedAt || Date.now() - savedAt > maxAge || submittedAt >= savedAt) {
                    return;
                }
                if (!payload.fields || sameFields(payload.fields)) {
                    return;
                }
                var serverMtimeInput = form.querySelector('[name="server_mtime"]');
                var serverMtime = serverMtimeInput ? (parseInt(serverMtimeInput.value || '0', 10) || 0) * 1000 : 0;
                if (serverMtime && savedAt <= serverMtime + 1000) {
                    return;
                }
                var when = new Date(savedAt).toLocaleString();
                if (window.confirm('Hay una copia local no guardada de este editor (' + when + '). ¿Quieres recuperarla?')) {
                    restoreFields(payload.fields);
                    saveAutosave();
                }
            }

            pruneOldAutosaves();
            maybeRestoreAutosave();

            var saveTimer = null;
            form.addEventListener('input', function() {
                window.clearTimeout(saveTimer);
                saveTimer = window.setTimeout(function() {
                    saveAutosave();
                }, 800);
            });
            form.addEventListener('change', function() {
                saveAutosave();
            });
            form.addEventListener('submit', function() {
                saveAutosave({ submitted_at: Date.now() });
            });
            window.setInterval(function() {
                saveAutosave();
            }, 5000);
        });
