<?php if ($page === 'redes'): ?>
    <?php
    $settings = get_settings();
    $availableNetworks = admin_social_broadcast_available_networks($settings);
    $labels = admin_social_broadcast_labels();
    $siteName = trim((string) (($settings['site_name'] ?? '') ?: 'Nammu Blog'));
    $baseUrl = function_exists('nammu_base_url') ? rtrim((string) nammu_base_url(), '/') : '';
    $fediverseProfileUrl = function_exists('nammu_fediverse_profile_page_url') ? nammu_fediverse_profile_page_url($settings) : ($baseUrl . '/actualidad.php');
    $newsFeedUrl = ($baseUrl !== '' ? $baseUrl : '') . '/noticias.xml';
    $fediverseIcon = function_exists('nammu_footer_icon_svgs') ? (string) (nammu_footer_icon_svgs()['fediverse'] ?? '') : '';
    $socialBroadcastMaxImages = function_exists('admin_social_broadcast_max_images') ? (int) admin_social_broadcast_max_images() : 4;
    ?>
    <div class="tab-pane active">
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
            <div>
                <h2 class="mb-1">Redes</h2>
                <p class="text-muted mb-0">Escribe un mensaje y envíalo al <strong>Fediverso</strong> <span class="align-middle d-inline-block social-fediverse-inline-icon"><?= $fediverseIcon ?></span> y a todas las redes sociales que tengas configuradas.</p>
            </div>
        </div>

        <?php if (!empty($socialBroadcastFeedback)): ?>
            <div class="alert alert-<?= htmlspecialchars($socialBroadcastFeedback['type'] ?? 'info', ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($socialBroadcastFeedback['message'] ?? '', ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="post" class="mb-4">
            <div class="card mb-4">
                <div class="card-body">
                    <h3 class="h5 mb-3">Enviar mensaje</h3>
                    <p class="text-muted small mb-3">Este mensaje se enviará al <strong>Fediverso</strong> <span class="align-middle d-inline-block social-fediverse-inline-icon"><?= $fediverseIcon ?></span> y aparecerá en <a href="<?= htmlspecialchars($newsFeedUrl, ENT_QUOTES, 'UTF-8') ?>"><code>noticias.xml</code></a> y en <a href="<?= htmlspecialchars($fediverseProfileUrl, ENT_QUOTES, 'UTF-8') ?>">tu página de perfil del Fediverso</a> como una nota tipo post-it.</p>
                    <div class="row">
                        <div class="col-12">
                            <label for="social_broadcast_text">Mensaje</label>
                            <div class="btn-toolbar mb-2 flex-wrap" role="toolbar" aria-label="Formato del mensaje">
                                <div class="btn-group btn-group-sm mr-2 mb-2" role="group">
                                    <button type="button" class="btn btn-outline-secondary" id="social_broadcast_bold" title="Negrita" aria-label="Negrita"><strong>B</strong></button>
                                </div>
                            </div>
                            <textarea
                                name="social_broadcast_text"
                                id="social_broadcast_text"
                                class="form-control"
                                rows="10"
                                maxlength="63206"
                                placeholder="Escribe aquí tu mensaje"
                            ><?= htmlspecialchars($socialBroadcastText ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                            <div class="d-flex justify-content-end mt-2">
                                <small class="text-muted">
                                    <span id="social_broadcast_count">0</span> caracteres
                                </small>
                            </div>
                            <div class="form-group mt-3 mb-0">
                                <label for="social_broadcast_image">Adjuntos opcionales</label>
                                <div class="input-group">
                                    <textarea name="social_broadcast_image" id="social_broadcast_image" class="form-control" rows="4" readonly><?= htmlspecialchars($socialBroadcastImage ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-outline-secondary" data-toggle="modal" data-target="#imageModal" data-target-type="field" data-target-input="social_broadcast_image" data-target-prefix="" data-target-multi="1" data-target-max-items="<?= $socialBroadcastMaxImages ?>" data-target-accept="image,video">Añadir adjunto</button>
                                    </div>
                                </div>
                                <small class="form-text text-muted">Puedes añadir hasta <?= $socialBroadcastMaxImages ?> adjuntos, una ruta por línea. Las imágenes podrán salir también en redes; los vídeos se conservarán para el Fediverso y la página de perfil. Si marcas Instagram, Nammu solo usará una imagen.</small>
                            </div>
                        </div>
                    </div>
                    <button type="submit" name="send_social_broadcast" class="btn btn-primary mt-3">Enviar</button>
                </div>
            </div>
        </form>

        <div class="card">
            <div class="card-body">
                <h3 class="h5 mb-3">Estado de la configuración</h3>
                <?php if (!empty($availableNetworks)): ?>
                    <div class="row">
                        <?php foreach ($labels as $networkKey => $networkLabel): ?>
                            <?php $isConfigured = isset($availableNetworks[$networkKey]); ?>
                            <div class="col-md-6 col-xl-4 mb-3">
                                <div class="border rounded p-3 h-100">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <strong><?= htmlspecialchars($networkLabel, ENT_QUOTES, 'UTF-8') ?></strong>
                                        <span class="badge badge-<?= $isConfigured ? 'success' : 'secondary' ?>">
                                            <?= $isConfigured ? 'Configurada' : 'No configurada' ?>
                                        </span>
                                    </div>
                                    <div class="mt-2 text-muted small">
                                        <?= htmlspecialchars((string) (admin_social_broadcast_guidance()[$networkKey] ?? ('Máximo: ' . (int) (admin_social_broadcast_limits()[$networkKey] ?? 0) . ' caracteres')), ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-secondary mb-0">
                        Configura primero tus redes en <a href="admin.php?page=anuncios">Difusión</a>.
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <style>
        .social-fediverse-inline-icon {
            width: 0.95rem;
            height: 0.95rem;
            line-height: 1;
            vertical-align: -0.1em;
            --nammu-fediverse-bg: #0d6efd;
            --nammu-fediverse-fg: #ffffff;
        }
        .social-fediverse-inline-icon svg {
            display: block;
            width: 100%;
            height: 100%;
        }
        .social-network-option.is-over-limit {
            opacity: 0.55;
        }
        .social-network-option.is-over-limit strong,
        .social-network-option.is-over-limit small {
            color: #b02a37 !important;
        }
    </style>
    <script>
        (function () {
            var textarea = document.getElementById('social_broadcast_text');
            var counter = document.getElementById('social_broadcast_count');
            var boldButton = document.getElementById('social_broadcast_bold');
            if (!textarea || !counter) {
                return;
            }
            var options = Array.prototype.slice.call(document.querySelectorAll('.social-network-option'));
            var wrapSelection = function (prefix, suffix) {
                var start = textarea.selectionStart || 0;
                var end = textarea.selectionEnd || 0;
                var value = textarea.value;
                var selected = value.slice(start, end);
                var replacement = prefix + selected + suffix;
                textarea.value = value.slice(0, start) + replacement + value.slice(end);
                textarea.focus();
                textarea.setSelectionRange(start + prefix.length, start + prefix.length + selected.length);
                update();
            };
            var update = function () {
                var length = textarea.value.length;
                counter.textContent = String(length);
                options.forEach(function (option) {
                    var limit = parseInt(option.getAttribute('data-limit') || '0', 10);
                    option.classList.toggle('is-over-limit', limit > 0 && length > limit);
                });
            };
            if (boldButton) {
                boldButton.addEventListener('click', function () {
                    wrapSelection('**', '**');
                });
            }
            textarea.addEventListener('input', update);
            update();
        }());
    </script>
<?php endif; ?>
