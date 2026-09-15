// Nammu admin — barra de herramientas Markdown del editor, callouts y modales de Nisaba, Telex e Ideas.
        document.addEventListener('DOMContentLoaded', function() {
            var toolbars = document.querySelectorAll('[data-markdown-toolbar]');
            if (!toolbars.length) {
                return;
            }
            var lastFocusedTextarea = null;
            var calloutTarget = null;
            var nisabaTarget = null;
            var telexTarget = null;
            document.addEventListener('focusin', function(event) {
                if (event.target && event.target.tagName === 'TEXTAREA' && event.target.id !== 'calloutBody') {
                    lastFocusedTextarea = event.target;
                }
            });

            toolbars.forEach(function(toolbar) {
                var targetSelector = toolbar.getAttribute('data-target');
                var textarea = null;
                if (targetSelector) {
                    textarea = document.querySelector(targetSelector);
                }
                if (!textarea) {
                    var sibling = toolbar.nextElementSibling;
                    if (sibling && sibling.tagName === 'TEXTAREA') {
                        textarea = sibling;
                    }
                }
                if (!textarea) {
                    toolbar.querySelectorAll('button').forEach(function(btn) {
                        btn.disabled = true;
                    });
                    return;
                }

                textarea.addEventListener('keydown', function(event) {
                    if (!(event.ctrlKey || event.metaKey)) {
                        return;
                    }
                    var key = event.key ? event.key.toLowerCase() : '';
                    var action = null;
                    if (key === 'b') {
                        action = 'bold';
                    } else if (key === 'i') {
                        action = 'italic';
                    } else if (key === 'k') {
                        action = 'link';
                    }
                    if (action) {
                        event.preventDefault();
                        applyMarkdownAction(textarea, action);
                    }
                });

                toolbar.addEventListener('click', function(event) {
                    var button = findActionButton(event.target, toolbar);
                    if (!button) {
                        return;
                    }
                    event.preventDefault();
                    var action = button.getAttribute('data-md-action');
                    if (action) {
                        if (['callout', 'nisaba', 'telex', 'ideas'].indexOf(action) !== -1) {
                            event.stopPropagation();
                            if (typeof event.stopImmediatePropagation === 'function') {
                                event.stopImmediatePropagation();
                            }
                        }
                        applyMarkdownAction(textarea, action);
                    }
                });
            });

            function findActionButton(element, container) {
                if (!element) {
                    return null;
                }
                if (typeof element.closest === 'function') {
                    var closest = element.closest('button[data-md-action]');
                    if (closest && container.contains(closest)) {
                        return closest;
                    }
                }
                while (element && element !== container) {
                    if (element.matches && element.matches('button[data-md-action]')) {
                        return element;
                    }
                    element = element.parentElement;
                }
                return null;
            }

            function applyMarkdownAction(textarea, action) {
                switch (action) {
                    case 'bold':
                        wrapSelection(textarea, '**', '**', 'Texto en negrita');
                        break;
                    case 'italic':
                        wrapSelection(textarea, '*', '*', 'Texto en cursiva');
                        break;
                    case 'strike':
                        wrapSelection(textarea, '~~', '~~', 'Texto tachado');
                        break;
                    case 'code':
                        wrapSelection(textarea, '`', '`', 'codigo');
                        break;
                    case 'sup':
                        wrapSelection(textarea, '^', '^', 'superíndice');
                        break;
                    case 'link':
                        insertLink(textarea);
                        break;
                    case 'quote':
                        applyLinePrefix(textarea, '> ', 'Texto citado');
                        break;
                    case 'ul':
                        applyUnorderedList(textarea);
                        break;
                    case 'ol':
                        applyOrderedList(textarea);
                        break;
                    case 'heading':
                        insertHeading(textarea);
                        break;
                    case 'code-block':
                        insertCodeBlock(textarea);
                        break;
                    case 'hr':
                        insertHorizontalRule(textarea);
                        break;
                    case 'table':
                        insertTable(textarea);
                        break;
                    case 'callout':
                        openCalloutModal(textarea);
                        break;
                    case 'nisaba':
                        openNisabaModal(textarea);
                        break;
                    case 'telex':
                        openTelexModal(textarea);
                        break;
                    case 'ideas':
                        openIdeasModal();
                        break;
                    default:
                        break;
                }
            }

            function getRange(textarea) {
                var start = typeof textarea.selectionStart === 'number' ? textarea.selectionStart : textarea.value.length;
                var end = typeof textarea.selectionEnd === 'number' ? textarea.selectionEnd : start;
                return {
                    start: start,
                    end: end,
                    text: textarea.value.slice(start, end)
                };
            }

            function replaceSelection(textarea, replacement, selectionStartOffset, selectionEndOffset) {
                var previousScrollTop = textarea.scrollTop;
                var value = textarea.value;
                var range = getRange(textarea);
                textarea.value = value.slice(0, range.start) + replacement + value.slice(range.end);
                var selStart = range.start + (typeof selectionStartOffset === 'number' ? selectionStartOffset : replacement.length);
                var selEnd = range.start + (typeof selectionEndOffset === 'number' ? selectionEndOffset : replacement.length);
                setSelection(textarea, selStart, selEnd, previousScrollTop);
            }

            function setSelection(textarea, start, end, previousScrollTop) {
                var scrollTop = typeof previousScrollTop === 'number' ? previousScrollTop : textarea.scrollTop;
                textarea.focus();
                if (typeof textarea.setSelectionRange === 'function') {
                    textarea.setSelectionRange(start, end);
                }
                textarea.scrollTop = scrollTop;
                triggerInput(textarea);
            }

            function triggerInput(textarea) {
                try {
                    var event = new Event('input', { bubbles: true });
                    textarea.dispatchEvent(event);
                } catch (err) {
                    var legacyEvent = document.createEvent('Event');
                    legacyEvent.initEvent('input', true, true);
                    textarea.dispatchEvent(legacyEvent);
                }
            }

            function wrapSelection(textarea, before, after, placeholder) {
                var range = getRange(textarea);
                var selected = range.text || placeholder;
                var replacement = before + selected + after;
                replaceSelection(textarea, replacement, before.length, before.length + selected.length);
            }

            function applyLinePrefix(textarea, prefix, placeholder) {
                var range = getRange(textarea);
                var text = range.text || placeholder;
                var lines = text.split(/\r?\n/);
                var transformed = lines.map(function(line) {
                    var clean = line.replace(/^\s*>+\s?/, '');
                    if (clean.trim() === '' && text === placeholder) {
                        clean = placeholder;
                    }
                    return prefix + clean;
                }).join('\n');
                replaceSelection(textarea, transformed, 0, transformed.length);
            }

            function applyUnorderedList(textarea) {
                var range = getRange(textarea);
                var text = range.text || 'Elemento de lista';
                var lines = text.split(/\r?\n/);
                var transformed = lines.map(function(line) {
                    var clean = line.replace(/^\s*([-*+]|\d+\.)\s*/, '').trim();
                    if (clean === '') {
                        clean = 'Elemento de lista';
                    }
                    return '- ' + clean;
                }).join('\n');
                replaceSelection(textarea, transformed, 0, transformed.length);
            }

            function applyOrderedList(textarea) {
                var range = getRange(textarea);
                var text = range.text || 'Elemento de lista';
                var lines = text.split(/\r?\n/);
                var counter = 1;
                var transformed = lines.map(function(line) {
                    var clean = line.replace(/^\s*\d+\.?\s*/, '').trim();
                    if (clean === '') {
                        clean = 'Elemento ' + counter;
                    }
                    var current = counter + '. ' + clean;
                    counter += 1;
                    return current;
                }).join('\n');
                replaceSelection(textarea, transformed, 0, transformed.length);
            }

            function insertHeading(textarea) {
                var range = getRange(textarea);
                var text = range.text || 'Título de sección';
                var parts = text.split(/\r?\n/);
                var firstLine = parts.shift() || 'Título de sección';
                firstLine = firstLine.replace(/^#{1,6}\s*/, '');
                var heading = '## ' + firstLine;
                if (parts.length) {
                    parts.unshift(heading);
                    var replacement = parts.join('\n');
                    replaceSelection(textarea, replacement, 3, heading.length);
                } else {
                    replaceSelection(textarea, heading, 3, heading.length);
                }
            }

            function insertCodeBlock(textarea) {
                var range = getRange(textarea);
                var text = range.text || 'Tu código aquí';
                var replacement = '```\n' + text + '\n```\n';
                replaceSelection(textarea, replacement, 4, 4 + text.length);
            }

            function insertHorizontalRule(textarea) {
                var insertText = '\n\n---\n\n';
                replaceSelection(textarea, insertText, insertText.length, insertText.length);
            }

            function insertTable(textarea) {
                var rowsInput = window.prompt('Número de filas (sin contar cabecera):', '3');
                var colsInput = window.prompt('Número de columnas:', '3');
                var rows = parseInt(rowsInput, 10);
                var cols = parseInt(colsInput, 10);
                if (!rows || rows < 1 || !cols || cols < 1) {
                    return;
                }
                var headerCells = [];
                for (var c = 1; c <= cols; c++) {
                    headerCells.push('Columna ' + c);
                }
                var header = '| ' + headerCells.join(' | ') + ' |\n';
                var separator = '| ' + headerCells.map(function() { return '---'; }).join(' | ') + ' |\n';
                var body = '';
                for (var r = 1; r <= rows; r++) {
                    var rowCells = [];
                    for (var cc = 1; cc <= cols; cc++) {
                        rowCells.push('Dato ' + r + '.' + cc);
                    }
                    body += '| ' + rowCells.join(' | ') + ' |\n';
                }
                var tableMarkdown = '\n' + header + separator + body + '\n';
                replaceSelection(textarea, tableMarkdown, tableMarkdown.length, tableMarkdown.length);
            }

            function fallbackTextarea() {
                if (lastFocusedTextarea && lastFocusedTextarea.tagName === 'TEXTAREA' && lastFocusedTextarea.id !== 'calloutBody') {
                    return lastFocusedTextarea;
                }
                var active = document.activeElement;
                if (active && active.tagName === 'TEXTAREA' && active.id !== 'calloutBody') {
                    return active;
                }
                return document.querySelector('[data-markdown-editor]') || document.querySelector('#content_edit, #content_publish, #itinerary_content, #topic_content') || document.querySelector('textarea');
            }

            function showBootstrapModal(modal) {
                if (!modal) {
                    return;
                }
                if (window.jQuery && window.jQuery.fn && typeof window.jQuery.fn.modal === 'function') {
                    window.jQuery(modal).modal('show');
                    return;
                }
                modal.classList.add('show');
                modal.style.display = 'block';
                modal.removeAttribute('aria-hidden');
                document.body.classList.add('modal-open');
            }

            function hideBootstrapModal(modal) {
                if (!modal) {
                    return;
                }
                if (window.jQuery && window.jQuery.fn && typeof window.jQuery.fn.modal === 'function') {
                    window.jQuery(modal).modal('hide');
                    return;
                }
                modal.classList.remove('show');
                modal.style.display = 'none';
                modal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('modal-open');
            }

            function openCalloutModal(textarea) {
                var target = textarea;
                if (!target || target.tagName !== 'TEXTAREA') {
                    if (document.activeElement && document.activeElement.tagName === 'TEXTAREA') {
                        target = document.activeElement;
                    } else if (lastFocusedTextarea && lastFocusedTextarea.tagName === 'TEXTAREA') {
                        target = lastFocusedTextarea;
                    } else {
                        target = document.querySelector('[data-markdown-editor]') || document.querySelector('textarea');
                    }
                }
                if (target && target.id === 'calloutBody') {
                    target = null;
                }
                calloutTarget = target || fallbackTextarea();
                var modal = document.getElementById('calloutModal');
                var titleInput = document.getElementById('calloutTitle');
                var bodyInput = document.getElementById('calloutBody');
                if (modal) {
                    if (titleInput && titleInput.value.trim() === '') {
                        titleInput.value = 'Aviso';
                    }
                    if (bodyInput) {
                        bodyInput.value = '';
                    }
                    showBootstrapModal(modal);
                } else {
                    // Fallback a prompts si el modal no está disponible
                    var title = window.prompt('Título del aviso/caja', 'Aviso') || 'Aviso';
                    var bodyRaw = window.prompt('Contenido del aviso (texto o enlaces)', '') || '';
                    var lines = bodyRaw.split(/\n+/).map(function(line) { return line.trim(); }).filter(function(line) { return line !== ''; });
                    if (!lines.length) {
                        lines = ['Contenido del aviso.'];
                    }
                    var bodyHtml = lines.map(function(line) { return '  <p>' + line + '</p>'; }).join('\n');
                    var callout = '\n\n<div class="callout-box">\n  <h4>' + title + '</h4>\n' + bodyHtml + '\n</div>\n\n';
                    replaceSelection(calloutTarget, callout, callout.length, callout.length);
                    calloutTarget = null;
                }
            }

            function openNisabaModal(textarea) {
                var modal = document.getElementById('nisabaModal');
                if (!modal) {
                    return;
                }
                var target = textarea;
                if (!target || target.tagName !== 'TEXTAREA') {
                    if (document.activeElement && document.activeElement.tagName === 'TEXTAREA') {
                        target = document.activeElement;
                    } else if (lastFocusedTextarea && lastFocusedTextarea.tagName === 'TEXTAREA') {
                        target = lastFocusedTextarea;
                    } else {
                        target = fallbackTextarea();
                    }
                }
                if (target && target.id === 'calloutBody') {
                    target = null;
                }
                nisabaTarget = target || fallbackTextarea();
                if (window.jQuery && typeof window.jQuery.fn.modal === 'function') {
                    window.jQuery(modal).modal('show');
                } else {
                    modal.classList.add('show');
                    modal.style.display = 'block';
                    modal.removeAttribute('aria-hidden');
                }
            }

            function openTelexModal(textarea) {
                var modal = document.getElementById('telexModal');
                if (!modal) {
                    return;
                }
                var target = textarea;
                if (!target || target.tagName !== 'TEXTAREA') {
                    if (document.activeElement && document.activeElement.tagName === 'TEXTAREA') {
                        target = document.activeElement;
                    } else if (lastFocusedTextarea && lastFocusedTextarea.tagName === 'TEXTAREA') {
                        target = lastFocusedTextarea;
                    } else {
                        target = fallbackTextarea();
                    }
                }
                if (target && target.id === 'calloutBody') {
                    target = null;
                }
                telexTarget = target || fallbackTextarea();
                if (window.jQuery && typeof window.jQuery.fn.modal === 'function') {
                    window.jQuery(modal).modal('show');
                } else {
                    modal.classList.add('show');
                    modal.style.display = 'block';
                    modal.removeAttribute('aria-hidden');
                }
            }

            function openIdeasModal() {
                var modal = document.getElementById('ideasModal');
                if (!modal) {
                    return;
                }
                if (window.jQuery && typeof window.jQuery.fn.modal === 'function') {
                    window.jQuery(modal).modal('show');
                } else {
                    modal.classList.add('show');
                    modal.style.display = 'block';
                    modal.removeAttribute('aria-hidden');
                }
            }

            function decodeBase64Utf8(value) {
                if (!value) {
                    return '';
                }
                try {
                    return decodeURIComponent(escape(window.atob(value)));
                } catch (err) {
                    try {
                        return window.atob(value);
                    } catch (fallbackErr) {
                        return '';
                    }
                }
            }

            function nisabaNormalizeQuotes(html) {
                if (!html) {
                    return '';
                }
                function decodeEntities(value) {
                    var textarea = document.createElement('textarea');
                    textarea.innerHTML = value;
                    return textarea.value;
                }

                function normalizeParagraph(paragraph) {
                    var text = (paragraph.textContent || '').trim();
                    if (!text || text.charAt(0) !== '«' || text.charAt(text.length - 1) !== '»') {
                        return false;
                    }
                    var inner = paragraph.innerHTML || '';
                    var first = inner.indexOf('«');
                    var last = inner.lastIndexOf('»');
                    if (first !== -1) {
                        inner = inner.slice(0, first) + inner.slice(first + 1);
                        if (last > first) {
                            last = inner.lastIndexOf('»');
                        }
                    }
                    if (last !== -1) {
                        inner = inner.slice(0, last) + inner.slice(last + 1);
                    }
                    inner = inner.trim();
                    paragraph.innerHTML = '> ' + inner;
                    return true;
                }

                function normalizeLines(value) {
                    var withBreaks = value.replace(/<br\s*\/?>/gi, '\n');
                    var normalizedEntities = withBreaks.replace(/&laquo;/gi, '«').replace(/&raquo;/gi, '»');
                    if (!/<[^>]+>/.test(normalizedEntities)) {
                        normalizedEntities = decodeEntities(normalizedEntities);
                    }
                    var changed = false;
                    var output = normalizedEntities.replace(/(^|\n)\s*«([^»]+)»\s*(?=\n|$)/g, function(match, prefix, inner) {
                        changed = true;
                        return prefix + '> ' + inner.trim();
                    });
                    return { text: output, changed: changed };
                }

                var wrapper = document.createElement('div');
                wrapper.innerHTML = html;
                var paragraphs = wrapper.querySelectorAll('p');
                var didChange = false;
                paragraphs.forEach(function(paragraph) {
                    if (normalizeParagraph(paragraph)) {
                        didChange = true;
                    }
                });

                if (!didChange && paragraphs.length === 0 && html.indexOf('&lt;') !== -1) {
                    var decoded = decodeEntities(html);
                    var decodedWrapper = document.createElement('div');
                    decodedWrapper.innerHTML = decoded;
                    decodedWrapper.querySelectorAll('p').forEach(function(paragraph) {
                        if (normalizeParagraph(paragraph)) {
                            didChange = true;
                        }
                    });
                    if (didChange) {
                        return decodedWrapper.innerHTML;
                    }
                }

                var normalized = normalizeLines(html);
                if (normalized.changed) {
                    return normalized.text;
                }

                if (!didChange) {
                    normalized = normalizeLines(wrapper.innerHTML);
                    if (normalized.changed) {
                        return normalized.text;
                    }
                }

                return wrapper.innerHTML;
            }

            function escapeHtml(value) {
                return (value || '').replace(/[&<>"]/g, function(char) {
                    switch (char) {
                        case '&': return '&amp;';
                        case '<': return '&lt;';
                        case '>': return '&gt;';
                        case '"': return '&quot;';
                        default: return char;
                    }
                });
            }

            var calloutInsertButton = document.getElementById('calloutInsert');
            if (calloutInsertButton) {
                calloutInsertButton.addEventListener('click', function(event) {
                    event.preventDefault();
                    event.stopPropagation();
                    if (typeof event.stopImmediatePropagation === 'function') {
                        event.stopImmediatePropagation();
                    }
                    var modal = document.getElementById('calloutModal');
                    var titleInput = document.getElementById('calloutTitle');
                    var bodyInput = document.getElementById('calloutBody');
                    var target = calloutTarget || fallbackTextarea();
                    if (!target || target.id === 'calloutBody') {
                        target = document.querySelector('[data-markdown-editor]') || document.querySelector('#content_edit, #content_publish, #itinerary_content, #topic_content');
                    }
                    if (!target || target.tagName !== 'TEXTAREA') {
                        hideBootstrapModal(modal);
                        return;
                    }
                    var title = titleInput && titleInput.value.trim() !== '' ? titleInput.value.trim() : 'Aviso';
                    var bodyRaw = bodyInput ? bodyInput.value : '';
                    var lines = bodyRaw.split(/\n+/).map(function(line) {
                        return line.trim();
                    }).filter(function(line) {
                        return line !== '';
                    });
                    if (!lines.length) {
                        lines = ['Contenido del aviso.'];
                    }
                    var bodyHtml = lines.map(function(line) {
                        return '  <p>' + line + '</p>';
                    }).join('\n');
                    var callout = '\n\n<div class="callout-box">\n  <h4>' + title + '</h4>\n' + bodyHtml + '\n</div>\n\n';
                    replaceSelection(target, callout, callout.length, callout.length);
                    calloutTarget = null;
                    hideBootstrapModal(modal);
                }, true);
            }

            var nisabaInsertButton = document.getElementById('nisabaInsert');
            if (nisabaInsertButton) {
                nisabaInsertButton.addEventListener('click', function() {
                    var modal = document.getElementById('nisabaModal');
                    var target = nisabaTarget || fallbackTextarea();
                    if (!modal || !target) {
                        return;
                    }
                    var selections = modal.querySelectorAll('[data-nisaba-item]');
                    var blocks = [];
                    selections.forEach(function(input) {
                        if (!input.checked) {
                            return;
                        }
                        var title = input.getAttribute('data-note-title') || 'Nota de Nisaba';
                        var link = input.getAttribute('data-note-link') || '';
                        var noteDomain = input.getAttribute('data-note-domain') || '';
                        var contentEncoded = input.getAttribute('data-note-content') || '';
                        var content = decodeBase64Utf8(contentEncoded);
                        content = content.replace(/&gt;|&#62;/gi, '>');
                        content = content.replace(/&laquo;/gi, '«').replace(/&raquo;/gi, '»');
                        content = nisabaNormalizeQuotes(content);
                        content = content.replace(/&gt;|&#62;/gi, '>');
                        var safeTitle = escapeHtml(title);
                        var sourceLine = '';
                        if (link) {
                            var safeLink = escapeHtml(link);
                            var hostLabel = noteDomain || '';
                            if (!hostLabel) {
                                try {
                                    hostLabel = new URL(link).hostname || '';
                                } catch (err) {
                                    hostLabel = link.replace(/^https?:\/\//i, '').split('/')[0];
                                }
                            }
                            hostLabel = hostLabel.replace(/^www\\./i, '');
                            sourceLine = '\n<p><strong>Fuente</strong>: <a href="' + safeLink + '" target="_blank" rel="noopener">' + escapeHtml(hostLabel) + '</a></p>';
                        }
                        blocks.push('\n\n<h3>' + safeTitle + '</h3>\n' + content + sourceLine + '\n');
                    });
                    if (!blocks.length) {
                        return;
                    }
                    var insertText = blocks.join('\n');
                    replaceSelection(target, insertText, insertText.length, insertText.length);
                    if (window.jQuery && typeof window.jQuery.fn.modal === 'function') {
                        window.jQuery(modal).modal('hide');
                    } else {
                        modal.classList.remove('show');
                        modal.style.display = 'none';
                        modal.setAttribute('aria-hidden', 'true');
                    }
                });
            }

            var telexInsertButton = document.getElementById('telexInsert');
            if (telexInsertButton) {
                telexInsertButton.addEventListener('click', function() {
                    var modal = document.getElementById('telexModal');
                    var target = telexTarget || fallbackTextarea();
                    if (!modal || !target) {
                        return;
                    }
                    var selections = modal.querySelectorAll('[data-telex-item]');
                    var blocks = [];
                    selections.forEach(function(input) {
                        if (!input.checked) {
                            return;
                        }
                        var title = input.getAttribute('data-note-title') || 'Nota de Telex';
                        var link = input.getAttribute('data-note-link') || '';
                        var noteDomain = input.getAttribute('data-note-domain') || '';
                        var contentEncoded = input.getAttribute('data-note-content') || '';
                        var content = decodeBase64Utf8(contentEncoded);
                        content = content.replace(/&gt;|&#62;/gi, '>');
                        content = content.replace(/&laquo;/gi, '«').replace(/&raquo;/gi, '»');
                        content = nisabaNormalizeQuotes(content);
                        content = content.replace(/&gt;|&#62;/gi, '>');
                        var safeTitle = escapeHtml(title);
                        var sourceLine = '';
                        if (link) {
                            var safeLink = escapeHtml(link);
                            var hostLabel = noteDomain || '';
                            if (!hostLabel) {
                                try {
                                    hostLabel = new URL(link).hostname || '';
                                } catch (err) {
                                    hostLabel = link.replace(/^https?:\/\//i, '').split('/')[0];
                                }
                            }
                            hostLabel = hostLabel.replace(/^www\\./i, '');
                            sourceLine = '\n<p><strong>Fuente</strong>: <a href="' + safeLink + '" target="_blank" rel="noopener">' + escapeHtml(hostLabel) + '</a></p>';
                        }
                        blocks.push('\n\n<h3>' + safeTitle + '</h3>\n' + content + sourceLine + '\n');
                    });
                    if (!blocks.length) {
                        return;
                    }
                    var insertText = blocks.join('\n');
                    replaceSelection(target, insertText, insertText.length, insertText.length);
                    if (window.jQuery && typeof window.jQuery.fn.modal === 'function') {
                        window.jQuery(modal).modal('hide');
                    } else {
                        modal.classList.remove('show');
                        modal.style.display = 'none';
                        modal.setAttribute('aria-hidden', 'true');
                    }
                });
            }

            function insertLink(textarea) {
                var range = getRange(textarea);
                var label = range.text || 'Texto del enlace';
                var defaultUrl = '';
                if (range.text && /^https?:\/\//i.test(range.text.trim())) {
                    defaultUrl = range.text.trim();
                }
                var url = window.prompt('Introduce la URL del enlace', defaultUrl || 'https://');
                if (!url) {
                    return;
                }
                var replacement = '[' + label + '](' + url + ')';
                replaceSelection(textarea, replacement, 1, 1 + label.length);
            }
        });
