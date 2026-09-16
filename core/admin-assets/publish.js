// Nammu admin — pestaña Publicar: formulario de difusión en RRSS/Fediverso, contador e imagen adjunta.
document.addEventListener('DOMContentLoaded', function() {
    var typeToggle = document.querySelector('[data-type-toggle]');
    var typeValueInput = document.querySelector('[data-type-value]');
    if (!typeToggle || !typeValueInput) {
        return;
    }
    var podcastOnly = document.querySelectorAll('.podcast-only');
    var nonPodcast = document.querySelectorAll('.non-podcast');
    var entryOnly = document.querySelectorAll('.entry-only');
    var pageOnly = document.querySelectorAll('.page-only');
    var entryPodcastOnly = document.querySelectorAll('.entry-podcast-only');
    var titleGroup = document.querySelector('.title-group');
    var dateGroup = document.querySelector('.date-group');
    var langGroup = document.querySelector('.lang-group');
    var scheduleGroup = document.querySelector('.schedule-group');
    var imageGroup = document.querySelector('.image-group');
    var messageImagesGroup = document.querySelector('.message-images-group');
    var slugGroup = document.querySelector('.slug-group');
    var markdownToolbar = document.querySelector('.markdown-toolbar[data-markdown-toolbar]');
    var insertResourceButton = document.querySelector('[data-target-editor="#content_publish"]');
    var insertResourceHelp = insertResourceButton ? insertResourceButton.nextElementSibling : null;
    var titleLabel = document.querySelector('label[for="title"]');
    var descriptionLabel = document.querySelector('label[for="description"]');
    var descriptionGroup = document.querySelector('.description-group');
    var messageHelp = document.querySelector('.message-help');
    var imageLabel = document.querySelector('label[for="image"]');
    var slugLabel = document.querySelector('label[for="filename"]');
    var publishButton = document.querySelector('[data-publish-button]');
    var draftButton = document.querySelector('[data-draft-button="1"]');
    var viewButton = document.querySelector('[data-view-button="1"]');
    var audioInput = document.getElementById('audio');
    var durationInput = document.getElementById('audio_duration');
    var lengthInput = document.getElementById('audio_length');
    var newsletterButton = document.querySelector('[data-newsletter-button="1"]');
    var contentLabel = document.querySelector('label[for="content_publish"]');
    var titleInput = document.getElementById('title');

    function formatDuration(seconds) {
        if (!Number.isFinite(seconds) || seconds <= 0) {
            return '';
        }
        var total = Math.floor(seconds);
        var hours = Math.floor(total / 3600);
        var minutes = Math.floor((total % 3600) / 60);
        var secs = total % 60;
        return String(hours).padStart(2, '0') + ':' + String(minutes).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
    }

    function trimLeadingSlashes(value) {
        var output = value || '';
        while (output.charAt(0) === '/' || output.charAt(0) === '\\') {
            output = output.slice(1);
        }
        return output;
    }

    function resolveAudioUrl(value) {
        if (!value) {
            return '';
        }
        if (value.indexOf('http://') === 0 || value.indexOf('https://') === 0) {
            return value;
        }
        if (value.indexOf('assets/') === 0) {
            return '/' + trimLeadingSlashes(value);
        }
        return '/assets/' + trimLeadingSlashes(value);
    }

    function updateAudioMetadata() {
        if (!audioInput || !audioInput.value) {
            return;
        }
        var url = resolveAudioUrl(audioInput.value.trim());
        if (!url) {
            return;
        }
        if (lengthInput && !lengthInput.value) {
            fetch(url, { method: 'HEAD' })
                .then(function(response) {
                    var length = response.headers.get('content-length');
                    if (length && lengthInput && !lengthInput.value) {
                        lengthInput.value = length;
                    }
                })
                .catch(function() {});
        }
        if (durationInput && !durationInput.value) {
            var audioProbe = new Audio();
            audioProbe.preload = 'metadata';
            audioProbe.addEventListener('loadedmetadata', function() {
                var formatted = formatDuration(audioProbe.duration);
                if (formatted && durationInput && !durationInput.value) {
                    durationInput.value = formatted;
                }
                audioProbe.src = '';
            });
            audioProbe.addEventListener('error', function() {
                audioProbe.src = '';
            });
            audioProbe.src = url;
        }
    }

    function togglePodcastFields() {
        var typeValue = typeValueInput.value || 'Entrada';
        var isPodcast = typeValue === 'Podcast';
        var isEntry = typeValue === 'Entrada';
        var isPage = typeValue === 'Página';
        var isNewsletter = typeValue === 'Newsletter';
        var isMessage = typeValue === 'Nota';
        podcastOnly.forEach(function(el) {
            el.classList.toggle('d-none', !isPodcast);
        });
        nonPodcast.forEach(function(el) {
            el.classList.toggle('d-none', isPodcast);
        });
        entryOnly.forEach(function(el) {
            el.classList.toggle('d-none', !isEntry);
        });
        pageOnly.forEach(function(el) {
            el.classList.toggle('d-none', !isPage);
        });
        entryPodcastOnly.forEach(function(el) {
            el.classList.toggle('d-none', !(isEntry || isPodcast));
        });
        if (titleGroup) {
            titleGroup.classList.toggle('d-none', isMessage);
        }
        if (dateGroup) {
            dateGroup.classList.toggle('d-none', isMessage);
        }
        if (langGroup) {
            langGroup.classList.toggle('d-none', isMessage);
        }
        if (scheduleGroup) {
            scheduleGroup.classList.toggle('d-none', isMessage);
        }
        if (imageGroup) {
            imageGroup.classList.toggle('d-none', isMessage);
        }
        if (messageImagesGroup) {
            messageImagesGroup.classList.toggle('d-none', !isMessage);
        }
        if (slugGroup) {
            slugGroup.classList.toggle('d-none', isMessage);
        }
        if (markdownToolbar) {
            markdownToolbar.classList.toggle('d-none', isMessage);
        }
        if (insertResourceButton) {
            insertResourceButton.classList.toggle('d-none', isMessage);
        }
        if (insertResourceHelp && insertResourceHelp.classList.contains('form-text')) {
            insertResourceHelp.classList.toggle('d-none', isMessage);
        }
        if (titleLabel && titleLabel.dataset.podcastLabel && titleLabel.dataset.postLabel) {
            titleLabel.textContent = isPodcast ? titleLabel.dataset.podcastLabel : titleLabel.dataset.postLabel;
        }
        if (contentLabel) {
            contentLabel.textContent = isMessage ? (contentLabel.dataset.messageLabel || 'Nota') : (contentLabel.dataset.defaultLabel || 'Contenido (Markdown)');
        }
        if (descriptionLabel && descriptionLabel.dataset.podcastLabel && descriptionLabel.dataset.postLabel) {
            descriptionLabel.textContent = isNewsletter
                ? (descriptionLabel.dataset.newsletterLabel || descriptionLabel.dataset.podcastLabel)
                : (isPodcast ? descriptionLabel.dataset.podcastLabel : descriptionLabel.dataset.postLabel);
        }
        if (descriptionGroup) {
            descriptionGroup.classList.toggle('d-none', isMessage);
        }
        if (messageHelp) {
            messageHelp.classList.toggle('d-none', !isMessage);
        }
        if (imageLabel && imageLabel.dataset.podcastLabel && imageLabel.dataset.postLabel) {
            imageLabel.textContent = isPodcast ? imageLabel.dataset.podcastLabel : imageLabel.dataset.postLabel;
        }
        if (slugLabel && slugLabel.dataset.podcastLabel && slugLabel.dataset.postLabel) {
            slugLabel.textContent = isPodcast ? slugLabel.dataset.podcastLabel : slugLabel.dataset.postLabel;
        }
        if (publishButton && publishButton.dataset.podcastLabel && publishButton.dataset.postLabel && publishButton.dataset.messageLabel) {
            publishButton.textContent = isMessage ? publishButton.dataset.messageLabel : (isPodcast ? publishButton.dataset.podcastLabel : publishButton.dataset.postLabel);
        }
        if (publishButton) {
            publishButton.classList.toggle('d-none', isNewsletter);
        }
        if (draftButton) {
            draftButton.classList.toggle('d-none', isMessage);
        }
        if (viewButton) {
            viewButton.classList.toggle('d-none', isMessage);
        }
        if (newsletterButton) {
            newsletterButton.classList.toggle('d-none', !isNewsletter);
        }
        if (titleInput) {
            titleInput.required = !isMessage;
        }
        if (audioInput) {
            audioInput.required = isPodcast;
        }
        if (durationInput) {
            durationInput.required = isPodcast;
        }
    }

    var typeButtons = Array.prototype.slice.call(typeToggle.querySelectorAll('[data-type-option]'));

    function setTypeFromButton(button) {
        var value = button.getAttribute('data-type-option') || 'Entrada';
        typeValueInput.value = value;
        typeButtons.forEach(function(other) {
            var isActive = other === button;
            other.classList.toggle('active', isActive);
            other.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            if (isActive) {
                other.classList.add('btn-primary');
                other.classList.remove('btn-outline-primary');
            } else {
                other.classList.remove('btn-primary');
                other.classList.add('btn-outline-primary');
            }
        });
        togglePodcastFields();
    }

    typeToggle.addEventListener('click', function(event) {
        var target = event.target;
        if (target && target.closest) {
            var button = target.closest('[data-type-option]');
            if (button) {
                event.preventDefault();
                setTypeFromButton(button);
            }
        }
    });

    if (typeButtons.length) {
        var activeButton = typeButtons.find(function(button) {
            return button.classList.contains('active');
        }) || typeButtons[0];
        setTypeFromButton(activeButton);
    }
    if (audioInput) {
        audioInput.addEventListener('change', updateAudioMetadata);
    }
    togglePodcastFields();
    updateAudioMetadata();
});
