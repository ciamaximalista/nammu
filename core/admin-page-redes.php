<?php if ($page === 'redes'): ?>
    <?php
    $settings = get_settings();
    $availableNetworks = admin_social_broadcast_available_networks($settings);
    $labels = admin_social_broadcast_labels();
    $rssConfig = admin_social_rss_settings($settings);
    $cronCommand = '*/5 * * * * php ' . __DIR__ . '/../admin.php --run-scheduled >> ' . __DIR__ . '/../backups/cron.log 2>&1';
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
                <p class="text-muted mb-0">Escribe un mensaje y envíalo al <strong>Fediverso</strong> <span class="align-middle d-inline-block social-fediverse-inline-icon"><?= $fediverseIcon ?></span> y, opcionalmente, a las redes sociales que quieras entre las que ya tengas configuradas.</p>
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
                        <div class="col-lg-8 mb-3 mb-lg-0">
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
                                <label for="social_broadcast_image">Imagen opcional</label>
                                <div class="input-group">
                                    <textarea name="social_broadcast_image" id="social_broadcast_image" class="form-control" rows="4" readonly><?= htmlspecialchars($socialBroadcastImage ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-outline-secondary" data-toggle="modal" data-target="#imageModal" data-target-type="field" data-target-input="social_broadcast_image" data-target-prefix="" data-target-multi="1" data-target-max-items="<?= $socialBroadcastMaxImages ?>">Añadir imagen</button>
                                    </div>
                                </div>
                                <small class="form-text text-muted">Puedes añadir hasta <?= $socialBroadcastMaxImages ?> imágenes, una por línea. X y Bluesky usarán varias; en las demás redes Nammu usará la primera cuando haga falta. Si marcas Instagram, Nammu la adaptará automáticamente a 1080x1080.</small>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <label class="d-block">Enviar también a</label>
                            <?php if (!empty($availableNetworks)): ?>
                                <div class="border rounded p-3 h-100">
                                    <?php $guidanceMap = admin_social_broadcast_guidance(); ?>
                                    <?php foreach ($availableNetworks as $networkKey => $networkData): ?>
                                        <div class="form-check mb-3 social-network-option" data-limit="<?= (int) $networkData['limit'] ?>" data-guidance="<?= htmlspecialchars((string) ($networkData['guidance'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                            <input class="form-check-input" type="checkbox" name="social_networks[]" id="social_network_<?= htmlspecialchars($networkKey, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars($networkKey, ENT_QUOTES, 'UTF-8') ?>" <?= in_array($networkKey, $socialBroadcastNetworks ?? [], true) ? 'checked' : '' ?>>
                                            <label class="form-check-label d-block" for="social_network_<?= htmlspecialchars($networkKey, ENT_QUOTES, 'UTF-8') ?>">
                                                <strong><?= htmlspecialchars($networkData['label'], ENT_QUOTES, 'UTF-8') ?></strong><br>
                                                <small class="text-muted"><?= htmlspecialchars((string) ($networkData['guidance'] ?? ($guidanceMap[$networkKey] ?? '')), ENT_QUOTES, 'UTF-8') ?></small>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="border rounded p-3 h-100">
                                    <small class="text-muted d-block">Aunque no tengas redes adicionales configuradas, esta nota se guardará igualmente en el perfil del Fediverso y en <code>noticias.xml</code>.</small>
                                </div>
                            <?php endif; ?>
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

        <form method="post" class="mt-4">
            <div class="card">
                <div class="card-body">
                    <h3 class="h5 mb-2">Integración con Nisaba, Telex, etc.</h3>
                    <p class="text-muted small mb-3">Agrega las RSS de tus notas en Nisaba, tus selecciones en Telex u otros servicios similares, para centralizar en <?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?> los contenidos que selecciones con los comentarios y descripciones que les agregues. Se enviarán al fediverso, aparecerán en <code>noticias.xml</code> y tendrán un tratamiento gráfico distintivo en tu página de perfil.</p>
                    <?php if (!empty($socialRssFeedback)): ?>
                        <div class="alert alert-<?= htmlspecialchars($socialRssFeedback['type'] ?? 'info', ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($socialRssFeedback['message'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endif; ?>
                    <div class="form-group">
                        <label for="social_rss_feeds">Feeds RSS</label>
                        <textarea
                            name="social_rss_feeds"
                            id="social_rss_feeds"
                            class="form-control"
                            rows="6"
                            placeholder="Una URL RSS por línea"
                        ><?= htmlspecialchars($socialRssFeedsRaw ?? $rssConfig['feeds'], ENT_QUOTES, 'UTF-8') ?></textarea>
                        <small class="form-text text-muted">Añade una URL RSS o Atom por línea. Cuando aparezca una entrada nueva, Nammu enviará automáticamente su título y su enlace.</small>
                    </div>

                    <label class="d-block">También puedes enviar tus contenidos seleccionados a...</label>
                    <?php if (!empty($availableNetworks)): ?>
                        <div class="row">
                            <?php foreach ($availableNetworks as $networkKey => $networkData): ?>
                                <?php $checked = in_array($networkKey, $socialRssNetworks ?? $rssConfig['networks'], true); ?>
                                <div class="col-md-6 col-xl-4 mb-3">
                                    <div class="form-check border rounded p-3 h-100">
                                        <input class="form-check-input" type="checkbox" name="social_rss_networks[]" id="social_rss_network_<?= htmlspecialchars($networkKey, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars($networkKey, ENT_QUOTES, 'UTF-8') ?>" <?= $checked ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="social_rss_network_<?= htmlspecialchars($networkKey, ENT_QUOTES, 'UTF-8') ?>">
                                            <strong><?= htmlspecialchars($networkData['label'], ENT_QUOTES, 'UTF-8') ?></strong><br>
                                            <small class="text-muted">Enviará título + enlace</small>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-secondary">Configura primero al menos una red en <a href="admin.php?page=anuncios">Difusión</a>.</div>
                    <?php endif; ?>

                    <div class="form-group mt-3">
                        <label for="social_rss_cron">Instrucción para el cron</label>
                        <input type="text" id="social_rss_cron" class="form-control" value="<?= htmlspecialchars($cronCommand, ENT_QUOTES, 'UTF-8') ?>" readonly>
                        <small class="form-text text-muted">Edita el cron con <code>sudo crontab -u www-data -e</code> y añade esta línea. Como estás editando el crontab de <code>www-data</code>, la línea no debe incluir la columna <code>www-data</code>. Así el chequeo y el envío se ejecutarán cada 5 minutos.</small>
                    </div>

                    <button type="submit" name="save_social_rss_settings" class="btn btn-outline-primary">Guardar RSS de enlaces seleccionados y comentados por ti</button>
                </div>
            </div>
        </form>
    </div>

    <style>
        .social-fediverse-inline-icon {
            width: 0.95rem;
            height: 0.95rem;
            line-height: 1;
            vertical-align: -0.1em;
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
