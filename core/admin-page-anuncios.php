<?php if ($page === 'anuncios'): ?>
    <?php
    $settings = get_settings();
    $ads = $settings['ads'] ?? [];
    $adsEnabled = ($ads['enabled'] ?? 'off') === 'on';
    $adsScope = $ads['scope'] ?? 'home';
    $adsText = trim((string) ($ads['text'] ?? ''));
    $adsImage = trim((string) ($ads['image'] ?? ''));
    $adsLink = trim((string) ($ads['link'] ?? ''));
    $adsLinkLabel = trim((string) ($ads['link_label'] ?? ''));
    $pushEnabled = ($ads['push_enabled'] ?? 'off') === 'on';
    $pushPosts = ($ads['push_posts'] ?? 'off') === 'on';
    $pushItineraries = ($ads['push_itineraries'] ?? 'off') === 'on';
    $pushStatus = function_exists('nammu_push_dependencies_status') ? nammu_push_dependencies_status() : ['ok' => false, 'message' => 'Sistema no disponible.'];
    $pushAvailable = (bool) ($pushStatus['ok'] ?? false);
    $pushPublicKey = $pushAvailable && function_exists('nammu_push_public_key') ? nammu_push_public_key() : '';
    $pushSubscriberCount = $pushAvailable && function_exists('nammu_push_subscriber_count') ? nammu_push_subscriber_count() : 0;
    $pushQueueCount = $pushAvailable && function_exists('nammu_push_queue_count') ? nammu_push_queue_count() : 0;
    $telegramSettings = $settings['telegram'] ?? ['token' => '', 'channel' => '', 'auto_post' => 'off'];
    $telegramAutoEnabled = ($telegramSettings['auto_post'] ?? 'off') === 'on';
    $facebookSettings = $settings['facebook'] ?? ['token' => '', 'channel' => '', 'auto_post' => 'off', 'app_secret' => ''];
    $facebookAutoEnabled = ($facebookSettings['auto_post'] ?? 'off') === 'on';
    $facebookAppId = trim((string) (($settings['social']['facebook_app_id'] ?? '') ?: ''));
    $facebookTokenDebug = null;
    if (function_exists('admin_facebook_debug_token')) {
        $facebookToken = trim((string) ($facebookSettings['token'] ?? ''));
        $facebookAppSecret = trim((string) ($facebookSettings['app_secret'] ?? ''));
        if ($facebookToken !== '' && $facebookAppId !== '' && $facebookAppSecret !== '') {
            $facebookTokenDebug = admin_facebook_debug_token($facebookToken, $facebookAppId, $facebookAppSecret);
        }
    }
    $twitterSettings = $settings['twitter'] ?? ['api_key' => '', 'api_secret' => '', 'access_token' => '', 'access_secret' => '', 'auto_post' => 'off'];
    $twitterAutoEnabled = ($twitterSettings['auto_post'] ?? 'off') === 'on';
    $linkedinSettings = $settings['linkedin'] ?? ['token' => '', 'author' => '', 'auto_post' => 'off'];
    $linkedinAutoEnabled = ($linkedinSettings['auto_post'] ?? 'off') === 'on';
    $blueskySettings = $settings['bluesky'] ?? ['service' => 'https://bsky.social', 'identifier' => '', 'app_password' => '', 'auto_post' => 'off'];
    $blueskyAutoEnabled = ($blueskySettings['auto_post'] ?? 'off') === 'on';
    $mastodonSettings = $settings['mastodon'] ?? ['instance' => '', 'handle' => '', 'access_token' => '', 'profile' => '', 'auto_post' => 'off'];
    $mastodonAutoEnabled = ($mastodonSettings['auto_post'] ?? 'off') === 'on';
    $instagramSettings = $settings['instagram'] ?? ['token' => '', 'channel' => '', 'profile' => '', 'auto_post' => 'off'];
    $instagramAutoEnabled = ($instagramSettings['auto_post'] ?? 'off') === 'on';
    $podcastServices = $settings['podcast_services'] ?? [];
    $podcastSpotify = trim((string) ($podcastServices['spotify'] ?? ''));
    $podcastIvoox = trim((string) ($podcastServices['ivoox'] ?? ''));
    $podcastApple = trim((string) ($podcastServices['apple'] ?? ''));
    $podcastYouTube = trim((string) ($podcastServices['youtube_music'] ?? ''));
    $indexnowSettings = $settings['indexnow'] ?? [];
    $indexnowEnabled = ($indexnowSettings['enabled'] ?? 'off') === 'on';
    $indexnowStatus = function_exists('admin_indexnow_status') ? admin_indexnow_status() : [];
    $indexnowKey = trim((string) ($indexnowStatus['key'] ?? ''));
    $indexnowKeyUrl = trim((string) ($indexnowStatus['key_url'] ?? ''));
    $indexnowFileOk = (bool) ($indexnowStatus['file_ok'] ?? false);
    $siteBaseUrl = function_exists('nammu_base_url') ? nammu_base_url() : '';
    $podcastFeedUrl = $siteBaseUrl !== '' ? rtrim($siteBaseUrl, '/') . '/podcast.xml' : '/podcast.xml';
    $podcastCategoryOptions = ['Arts', 'Business', 'Comedy', 'Education', 'Fiction', 'Government', 'History', 'Health & Fitness', 'Kids & Family', 'Leisure', 'Music', 'News', 'Religion & Spirituality', 'Science', 'Society & Culture', 'Sports', 'Technology', 'True Crime', 'TV & Film'];
    if ($adsImage !== '') {
        if (str_starts_with($adsImage, 'http')) {
            $adsImagePreview = $adsImage;
        } elseif (str_starts_with($adsImage, 'assets/')) {
            $adsImagePreview = $adsImage;
        } else {
            $adsImagePreview = 'assets/' . ltrim($adsImage, '/');
        }
    } else {
        $adsImagePreview = '';
    }
    ?>

    <div class="tab-pane active">
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
            <div>
                <h2 class="mb-1">Difusión</h2>
                <p class="text-muted mb-0">Crea anuncios flotantes, envía notificaciones push directamente a tus lectores e integra la actividad y los contenidos del blog en tus redes sociales.</p>
            </div>
        </div>

        <?php if ($adsFeedback): ?>
            <div class="alert alert-<?= htmlspecialchars($adsFeedback['type'], ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($adsFeedback['message'], ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>
        <?php if (!$pushAvailable): ?>
            <div class="alert alert-warning">
                <strong>Notificaciones push no disponibles.</strong>
                <?= htmlspecialchars($pushStatus['message'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                Para activarlas instala las dependencias con Composer:
                <code>composer require minishlink/web-push</code>
                y asegúrate de tener habilitada la extensión OpenSSL en PHP.
            </div>
        <?php endif; ?>

        <form method="post" class="mb-4">
            <div class="card">
                <div class="card-body">
                    <h3 class="h5 mb-3">Anuncios en el sitio</h3>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="ads_enabled" id="ads_enabled" value="1" <?= $adsEnabled ? 'checked' : '' ?>>
                        <label class="form-check-label" for="ads_enabled">Activar anuncios</label>
                    </div>

                    <div class="mb-3">
                        <label class="d-block mb-2">Mostrar anuncio en</label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="ads_scope" id="ads_scope_home" value="home" <?= $adsScope === 'home' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="ads_scope_home">Portada</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="ads_scope" id="ads_scope_all" value="all" <?= $adsScope === 'all' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="ads_scope_all">Todas las paginas</label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="ads_text">Texto del anuncio</label>
                        <textarea class="form-control" name="ads_text" id="ads_text" rows="4" placeholder="Escribe aqui el texto del anuncio (html)"><?= htmlspecialchars($adsText, ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="ads_link">Enlace</label>
                        <input type="url" name="ads_link" id="ads_link" class="form-control" value="<?= htmlspecialchars($adsLink, ENT_QUOTES, 'UTF-8') ?>" placeholder="https://ejemplo.com">
                    </div>

                    <div class="form-group">
                        <label for="ads_link_label">Nombre de la página enlazada</label>
                        <input type="text" name="ads_link_label" id="ads_link_label" class="form-control" value="<?= htmlspecialchars($adsLinkLabel, ENT_QUOTES, 'UTF-8') ?>" placeholder="Ej: Nuestra tienda">
                    </div>

                    <div class="form-group">
                        <label for="ads_image">Imagen del anuncio</label>
                        <div class="input-group">
                            <input type="text" name="ads_image" id="ads_image" class="form-control" value="<?= htmlspecialchars($adsImage, ENT_QUOTES, 'UTF-8') ?>" placeholder="assets/imagen.jpg" readonly>
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-secondary" data-toggle="modal" data-target="#imageModal" data-target-type="field" data-target-input="ads_image" data-target-prefix="assets/">Seleccionar imagen</button>
                            </div>
                        </div>
                        <small class="form-text text-muted">La imagen se adapta a la altura completa del banner.</small>
                        <?php if ($adsImagePreview !== ''): ?>
                            <div class="mt-3">
                                <img src="<?= htmlspecialchars($adsImagePreview, ENT_QUOTES, 'UTF-8') ?>" alt="Vista previa del anuncio" style="max-width: 280px; width: 100%; border-radius: 12px; box-shadow: 0 8px 18px rgba(0,0,0,0.12);">
                            </div>
                        <?php endif; ?>
                    </div>

                    <button type="submit" name="save_ads_settings" class="btn btn-outline-primary">Guardar anuncio</button>
                </div>
            </div>
        </form>

        <form method="post">
            <div class="card mb-4">
                <div class="card-body">
                    <h3 class="h5 mb-3">Notificaciones Push</h3>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="push_enabled" id="push_enabled" value="1" <?= $pushEnabled ? 'checked' : '' ?>>
                        <label class="form-check-label" for="push_enabled">Activar notificaciones push</label>
                    </div>

                    <div class="border rounded p-3 bg-light" id="push_preferences" <?= $pushEnabled ? '' : 'style="display:none;"' ?>>
                        <p class="text-muted text-uppercase small mb-2">Preferencias de envío</p>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="push_posts" id="push_posts" value="1" <?= $pushPosts ? 'checked' : '' ?>>
                            <label class="form-check-label" for="push_posts">Enviar aviso de cada nueva entrada publicada</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="push_itineraries" id="push_itineraries" value="1" <?= $pushItineraries ? 'checked' : '' ?>>
                            <label class="form-check-label" for="push_itineraries">Enviar aviso de cada nuevo itinerario publicado</label>
                        </div>
                    </div>
                    <div class="border rounded p-3 mt-3">
                        <p class="text-muted text-uppercase small mb-2">Estado del sistema</p>
                        <p class="mb-1"><strong>Dependencias:</strong> <?= $pushAvailable ? 'Disponibles' : 'No disponibles' ?></p>
                        <p class="mb-1"><strong>Clave VAPID:</strong> <?= $pushPublicKey !== '' ? 'Generada' : 'Pendiente' ?></p>
                        <p class="mb-1"><strong>Suscriptores:</strong> <?= (int) $pushSubscriberCount ?></p>
                        <p class="mb-0"><strong>Cola pendiente:</strong> <?= (int) $pushQueueCount ?></p>
                    </div>

                    <button type="submit" name="save_push_settings" class="btn btn-outline-primary mt-3">Guardar notificaciones push</button>
                </div>
            </div>
        </form>

        <form method="post">
            <div class="card mb-4">
                <div class="card-body">
                    <h3 class="h5 mb-3">Integración con Redes Sociales</h3>
                    <?php if (!empty($_SESSION['social_feedback'])): ?>
                        <?php $socialFeedback = $_SESSION['social_feedback']; unset($_SESSION['social_feedback']); ?>
                        <div class="alert alert-<?= htmlspecialchars($socialFeedback['type'] ?? 'info', ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($socialFeedback['message'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="social_default_description">Descripción por defecto</label>
                        <textarea name="social_default_description" id="social_default_description" class="form-control" rows="3" placeholder="Resumen que aparecerá al compartir la portada en redes sociales."><?= htmlspecialchars($socialDefaultDescription, ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="social_home_image">Imagen de la portada para redes sociales</label>
                        <div class="input-group">
                            <input type="text" name="social_home_image" id="social_home_image" class="form-control" value="<?= htmlspecialchars($socialHomeImage, ENT_QUOTES, 'UTF-8') ?>" placeholder="assets/imagen-portada.jpg" readonly>
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-secondary" data-toggle="modal" data-target="#imageModal" data-target-type="field" data-target-input="social_home_image" data-target-prefix="assets/">Seleccionar imagen</button>
                                <button type="button" class="btn btn-outline-danger" id="clear-social-image">Quitar</button>
                            </div>
                        </div>
                        <small class="form-text text-muted">Se utilizará como imagen por defecto para la portada y cuando una entrada no tenga imagen destacada.</small>
                    </div>

                    <div class="form-group">
                        <label for="social_podcast_image">Imagen de la portada para plataformas de podcast</label>
                        <div class="input-group">
                            <input type="text" name="social_podcast_image" id="social_podcast_image" class="form-control" value="<?= htmlspecialchars($socialPodcastImage, ENT_QUOTES, 'UTF-8') ?>" placeholder="assets/imagen-podcast.jpg" readonly>
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-secondary" data-toggle="modal" data-target="#imageModal" data-target-type="field" data-target-input="social_podcast_image" data-target-prefix="assets/">Seleccionar imagen</button>
                                <button type="button" class="btn btn-outline-danger" id="clear-social-podcast-image">Quitar</button>
                            </div>
                        </div>
                        <small class="form-text text-muted">Se usará en las tarjetas de la página de podcast y como imagen del canal en podcast.xml. Si está vacío, se usará la imagen de redes sociales.</small>
                    </div>

                    <div class="form-group">
                        <label for="social_podcast_category">Categoría del canal de podcast</label>
                        <select name="social_podcast_category" id="social_podcast_category" class="form-control">
                            <?php foreach ($podcastCategoryOptions as $podcastCategory): ?>
                                <option value="<?= htmlspecialchars($podcastCategory, ENT_QUOTES, 'UTF-8') ?>" <?= ($socialPodcastCategory === $podcastCategory) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($podcastCategory, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">Se usará en la etiqueta itunes:category de podcast.xml.</small>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="social_twitter">Usuario de Twitter / X</label>
                            <input type="text" name="social_twitter" id="social_twitter" class="form-control" value="<?= htmlspecialchars($socialTwitter, ENT_QUOTES, 'UTF-8') ?>" placeholder="usuario">
                            <small class="form-text text-muted">Introduce el usuario sin la @ inicial.</small>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="social_facebook_app_id">Facebook App ID</label>
                            <input type="text" name="social_facebook_app_id" id="social_facebook_app_id" class="form-control" value="<?= htmlspecialchars($socialFacebookAppId, ENT_QUOTES, 'UTF-8') ?>" placeholder="Opcional">
                        </div>
                    </div>

                    <h4 class="mt-4">Telegram (opcional)</h4>
                    <p class="text-muted">Conecta un bot y un canal/grupo para compartir automáticamente las nuevas entradas. <a href="#" data-toggle="modal" data-target="#telegramHelpModal">Ver guía rápida</a></p>
                    <div class="form-group">
                        <label for="telegram_token">Token del bot</label>
                        <input type="text" name="telegram_token" id="telegram_token" class="form-control" value="<?= htmlspecialchars($telegramSettings['token'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="123456789:ABCDef...">
                        <small class="form-text text-muted">Creado con @BotFather. Nunca compartas este token en público.</small>
                    </div>
                    <div class="form-group">
                        <label for="telegram_channel">Canal o grupo</label>
                        <input type="text" name="telegram_channel" id="telegram_channel" class="form-control" value="<?= htmlspecialchars($telegramSettings['channel'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="@nombre_canal">
                        <small class="form-text text-muted">Usa el @ del canal o el ID numérico del grupo donde el bot es administrador.</small>
                    </div>
                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" name="telegram_auto" id="telegram_auto" value="1" <?= $telegramAutoEnabled ? 'checked' : '' ?>>
                        <label for="telegram_auto" class="form-check-label">Enviar automáticamente cada nueva entrada o itinerario publicado</label>
                    </div>
                    <div class="modal fade" id="telegramHelpModal" tabindex="-1" role="dialog" aria-labelledby="telegramHelpModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-lg" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="telegramHelpModalLabel">Guía rápida para Telegram</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <ol class="mb-3">
                                        <li>Crea un bot con <code>@BotFather</code> y copia el token.</li>
                                        <li>Añade el bot como administrador en tu canal o grupo.</li>
                                        <li>Introduce el token y el @del canal o el ID numérico del grupo.</li>
                                    </ol>
                                    <p class="mb-0">Telegram permite enlaces clicables en los mensajes.</p>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cerrar</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <h4 class="mt-4">Facebook (opcional)</h4>
                    <p class="text-muted">Comparte tus entradas en una página o grupo usando la Graph API. <a href="#" data-toggle="modal" data-target="#facebookHelpModal">Ver guía rápida</a></p>
                    <div class="form-group">
                        <label for="facebook_token">Token de acceso</label>
                        <input type="text" name="facebook_token" id="facebook_token" class="form-control" value="<?= htmlspecialchars($facebookSettings['token'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="EAABsb...">
                        <small class="form-text text-muted">Usa un token con permisos para publicar en la página objetivo.</small>
                    </div>
                    <div class="form-group">
                        <label for="social_facebook_app_id_block">Facebook App ID</label>
                        <input type="text" name="social_facebook_app_id" id="social_facebook_app_id_block" class="form-control" value="<?= htmlspecialchars($facebookAppId, ENT_QUOTES, 'UTF-8') ?>" placeholder="Opcional">
                        <small class="form-text text-muted">Repite el App ID aquí si no lo has puesto en la parte superior.</small>
                    </div>
                    <div class="form-group">
                        <label for="facebook_app_secret">App Secret</label>
                        <input type="text" name="facebook_app_secret" id="facebook_app_secret" class="form-control" value="<?= htmlspecialchars($facebookSettings['app_secret'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Opcional pero recomendado">
                        <small class="form-text text-muted">Necesario para refrescar automáticamente el token cada día.</small>
                    </div>
                    <?php if (!is_array($facebookTokenDebug) && ($facebookSettings['token'] ?? '') !== '' && $facebookAppId !== '' && ($facebookSettings['app_secret'] ?? '') === ''): ?>
                        <div class="alert alert-warning">
                            Para ver el diagnóstico del token, añade el App Secret.
                        </div>
                    <?php elseif (!is_array($facebookTokenDebug) && ($facebookSettings['token'] ?? '') !== '' && ($facebookSettings['app_secret'] ?? '') !== '' && $facebookAppId === ''): ?>
                        <div class="alert alert-warning">
                            Para ver el diagnóstico del token, añade el Facebook App ID.
                        </div>
                    <?php elseif (is_array($facebookTokenDebug)): ?>
                        <?php if (isset($facebookTokenDebug['data'])): ?>
                            <?php
                            $facebookTokenData = $facebookTokenDebug['data'];
                            $facebookScopes = isset($facebookTokenData['scopes']) && is_array($facebookTokenData['scopes']) ? $facebookTokenData['scopes'] : [];
                            $facebookTokenType = (string) ($facebookTokenData['type'] ?? '');
                            $facebookExpiresAt = isset($facebookTokenData['expires_at']) ? (int) $facebookTokenData['expires_at'] : 0;
                            $facebookExpiresText = $facebookExpiresAt > 0 ? date('Y-m-d H:i', $facebookExpiresAt) : 'n/d';
                            ?>
                            <div class="alert alert-info">
                                Token: tipo <strong><?= htmlspecialchars($facebookTokenType, ENT_QUOTES, 'UTF-8') ?></strong>,
                                permisos <strong><?= htmlspecialchars(implode(', ', $facebookScopes), ENT_QUOTES, 'UTF-8') ?></strong>,
                                caduca <strong><?= htmlspecialchars($facebookExpiresText, ENT_QUOTES, 'UTF-8') ?></strong>.
                            </div>
                        <?php elseif (isset($facebookTokenDebug['error']['message'])): ?>
                            <div class="alert alert-warning">
                                <?= htmlspecialchars((string) $facebookTokenDebug['error']['message'], ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    <div class="form-group">
                        <label for="facebook_channel">ID de página o grupo</label>
                        <input type="text" name="facebook_channel" id="facebook_channel" class="form-control" value="<?= htmlspecialchars($facebookSettings['channel'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="1234567890">
                        <small class="form-text text-muted">Puedes obtenerlo desde la configuración avanzada de la página.</small>
                    </div>
                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" name="facebook_auto" id="facebook_auto" value="1" <?= $facebookAutoEnabled ? 'checked' : '' ?>>
                        <label for="facebook_auto" class="form-check-label">Enviar automáticamente cada nueva entrada o itinerario publicado</label>
                    </div>
                    <div class="modal fade" id="facebookHelpModal" tabindex="-1" role="dialog" aria-labelledby="facebookHelpModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-lg" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="facebookHelpModalLabel">Guía rápida para Facebook</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <ol class="mb-3">
                                        <li>Entra en Meta for Developers y crea una app.</li>
                                        <li>Cuando pregunte por casos de uso, filtra por “Todo” y selecciona “Otro. Tu app se creará en la experiencia antigua”.</li>
                                        <li>En tipo de app, selecciona “Empresa”.</li>
                                        <li>Si te pide conectar un portfolio empresarial, conéctalo (recomendado, sobre todo si luego usarás Instagram).</li>
                                        <li>Pon la app en Producción (Live).</li>
                                        <li>Abre Configuración &gt; Básica y copia el App ID.</li>
                                        <li>En el mismo sitio, en “App Secret”, pulsa “Mostrar”, confirma contraseña y copia el App Secret.</li>
                                        <li>En <code>business.facebook.com</code>, ve a Configuración del negocio &gt; Usuarios &gt; Usuarios del sistema y crea un usuario del sistema.</li>
                                        <li>En ese usuario del sistema, añade activos: la app (rol administrador) y la página de Facebook (control total o permisos para publicar).</li>
                                        <li>En ese mismo usuario del sistema, pulsa “Generar token”, elige la app y marca permisos: <code>pages_manage_posts</code>, <code>pages_read_engagement</code> y <code>pages_show_list</code> (si aparece).</li>
                                        <li>Importante: estos permisos del token se conceden en Business Manager (usuario del sistema), no con token de usuario en Graph Explorer.</li>
                                        <li>Obtén el Page Access Token final abriendo en tu navegador esta URL (sustituyendo valores):<br><code>https://graph.facebook.com/v17.0/TU_PAGE_ID?fields=access_token&amp;access_token=TU_TOKEN_DE_USUARIO_DE_SISTEMA</code></li>
                                        <li>De la respuesta JSON, copia el campo <code>access_token</code> (ese es el token de página que debe ir en Nammu).</li>
                                        <li>En este bloque de Nammu pega: App ID, App Secret, Token de acceso (token de página) e ID de página.</li>
                                        <li>Guarda y prueba con el botón Facebook en “Editar”.</li>
                                    </ol>
                                    <p class="mb-0">Nota: el “identificador de acceso” válido para publicar es el token de página, no el token de usuario. El ID de página se ve en Configuración de la página &gt; Información o en la URL de la página en Facebook.</p>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cerrar</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <h4 class="mt-4">Instagram (opcional)</h4>
                    <p class="text-muted">Requiere cuenta Business/Creator vinculada a una página de Facebook. Publica la imagen destacada con título y enlace. <a href="#" data-toggle="modal" data-target="#instagramHelpModal">Ver guía rápida</a></p>
                    <div class="form-group">
                        <label for="instagram_token">Token de acceso</label>
                        <input type="text" name="instagram_token" id="instagram_token" class="form-control" value="<?= htmlspecialchars($instagramSettings['token'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="EAAG...">
                        <small class="form-text text-muted">Token con permisos para publicar en Instagram Graph API.</small>
                    </div>
                    <div class="form-group">
                        <label for="instagram_channel">ID de cuenta de Instagram</label>
                        <input type="text" name="instagram_channel" id="instagram_channel" class="form-control" value="<?= htmlspecialchars($instagramSettings['channel'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="17841400000000000">
                        <small class="form-text text-muted">ID numérico de la cuenta de Instagram vinculada.</small>
                    </div>
                    <div class="form-group">
                        <label for="instagram_profile">Perfil público de Instagram (usuario o URL)</label>
                        <input type="text" name="instagram_profile" id="instagram_profile" class="form-control" value="<?= htmlspecialchars($instagramSettings['profile'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="@fundacionrepoblacion o https://www.instagram.com/fundacionrepoblacion/">
                        <small class="form-text text-muted">Se usa para enlazar el icono de Instagram en el footer.</small>
                    </div>
                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" name="instagram_auto" id="instagram_auto" value="1" <?= $instagramAutoEnabled ? 'checked' : '' ?>>
                        <label for="instagram_auto" class="form-check-label">Enviar automáticamente cada nueva entrada o itinerario publicado</label>
                    </div>

                    <div class="modal fade" id="instagramHelpModal" tabindex="-1" role="dialog" aria-labelledby="instagramHelpModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-lg" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="instagramHelpModalLabel">Guía rápida para Instagram</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <ol class="mb-3">
                                        <li>Convierte tu cuenta de Instagram a tipo <strong>Business</strong> o <strong>Creator</strong>.</li>
                                        <li>Vincula esa cuenta de Instagram a una página de Facebook que administres.</li>
                                        <li>En Meta for Developers, usa la misma app que ya usas para Facebook (tipo Empresa).</li>
                                        <li>Dentro de esa app, añade el producto <strong>Instagram</strong> / <strong>Instagram Graph API</strong> (el nombre puede variar según panel).</li>
                                        <li>Asegura que la app está en <strong>Producción (Live)</strong> y conectada al mismo Business Manager.</li>
                                        <li>En permisos del token, incluye: <code>instagram_basic</code>, <code>instagram_content_publish</code>, <code>pages_show_list</code> y <code>pages_read_engagement</code>.</li>
                                        <li>Genera un token válido para esa app y ese negocio (si usas usuario del sistema, debe tener acceso a la página vinculada).</li>
                                        <li>Obtén el ID de la cuenta de Instagram consultando la página vinculada en Graph API:<br><code>https://graph.facebook.com/v17.0/TU_PAGE_ID?fields=instagram_business_account&amp;access_token=TU_TOKEN</code></li>
                                        <li>Si la respuesta incluye <code>instagram_business_account.id</code>, ese es el ID que debes pegar en Nammu.</li>
                                        <li>También puedes ver el ID de cuenta de Instagram desde Meta Business (en la sección de cuentas de Instagram del negocio).</li>
                                        <li>Pega en este bloque el token y el ID de cuenta de Instagram.</li>
                                        <li>Guarda y prueba publicando desde “Editar”.</li>
                                    </ol>
                                    <p class="mb-0">Nota: Instagram muestra el enlace en el texto, pero normalmente no lo vuelve clicable en el pie del post.</p>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cerrar</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <h4 class="mt-4">Mastodon (opcional)</h4>
                    <p class="text-muted">Publica en tu instancia de Mastodon usando un token de acceso personal. <a href="#" data-toggle="modal" data-target="#mastodonHelpModal">Ver guía rápida</a></p>
                    <div class="form-group">
                        <label for="mastodon_instance">Instancia</label>
                        <input type="text" name="mastodon_instance" id="mastodon_instance" class="form-control" value="<?= htmlspecialchars($mastodonSettings['instance'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="https://mastodon.social">
                        <small class="form-text text-muted">La URL base de tu instancia, incluyendo https.</small>
                    </div>
                    <div class="form-group">
                        <label for="mastodon_handle">Usuario / handle</label>
                        <input type="text" name="mastodon_handle" id="mastodon_handle" class="form-control" value="<?= htmlspecialchars($mastodonSettings['handle'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="usuario">
                        <small class="form-text text-muted">Tu usuario de Mastodon (sin @). Se usa para enlazar el perfil en el footer.</small>
                    </div>
                    <div class="form-group">
                        <label for="mastodon_access_token">Token de acceso</label>
                        <input type="text" name="mastodon_access_token" id="mastodon_access_token" class="form-control" value="<?= htmlspecialchars($mastodonSettings['access_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Token personal">
                        <small class="form-text text-muted">Debe tener permisos de publicación (write:statuses).</small>
                    </div>
                    <div class="form-group">
                        <label for="mastodon_profile">URL del perfil (opcional)</label>
                        <input type="text" name="mastodon_profile" id="mastodon_profile" class="form-control" value="<?= htmlspecialchars($mastodonSettings['profile'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="https://tu-instancia/@usuario">
                        <small class="form-text text-muted">Si lo dejas vacío, se construirá con la instancia y el usuario.</small>
                    </div>
                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" name="mastodon_auto" id="mastodon_auto" value="1" <?= $mastodonAutoEnabled ? 'checked' : '' ?>>
                        <label for="mastodon_auto" class="form-check-label">Enviar automáticamente cada nueva entrada, itinerario o podcast publicado</label>
                    </div>
                    <div class="modal fade" id="mastodonHelpModal" tabindex="-1" role="dialog" aria-labelledby="mastodonHelpModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-lg" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="mastodonHelpModalLabel">Guía rápida para Mastodon</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <ol class="mb-3">
                                        <li>Entra en tu instancia &rarr; Preferencias &rarr; Desarrollo.</li>
                                        <li>Crea una nueva aplicación con permisos <code>write:statuses</code>.</li>
                                        <li>Copia el token de acceso y pégalo aquí.</li>
                                        <li>Indica la URL de la instancia y tu usuario.</li>
                                    </ol>
                                    <p class="mb-0">Las publicaciones incluirán el título y un enlace a la entrada.</p>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cerrar</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <h4 class="mt-4">Bluesky (opcional)</h4>
                    <p class="text-muted">Publica en tu perfil de Bluesky usando una contraseña de aplicación. <a href="#" data-toggle="modal" data-target="#blueskyHelpModal">Ver guía rápida</a></p>
                    <div class="form-group">
                        <label for="bluesky_service">Servidor (service)</label>
                        <input type="text" name="bluesky_service" id="bluesky_service" class="form-control" value="<?= htmlspecialchars($blueskySettings['service'] ?? 'https://bsky.social', ENT_QUOTES, 'UTF-8') ?>" placeholder="https://bsky.social">
                        <small class="form-text text-muted">Si no sabes cuál usar, deja el valor por defecto.</small>
                    </div>
                    <div class="form-group">
                        <label for="bluesky_identifier">Usuario / handle</label>
                        <input type="text" name="bluesky_identifier" id="bluesky_identifier" class="form-control" value="<?= htmlspecialchars($blueskySettings['identifier'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="usuario.bsky.social">
                        <small class="form-text text-muted">Tu handle de Bluesky (sin @). También puedes usar el email de la cuenta.</small>
                    </div>
                    <div class="form-group">
                        <label for="bluesky_app_password">Contraseña de aplicación</label>
                        <input type="text" name="bluesky_app_password" id="bluesky_app_password" class="form-control" value="<?= htmlspecialchars($blueskySettings['app_password'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="xxxx-xxxx-xxxx-xxxx">
                        <small class="form-text text-muted">Crea una contraseña de aplicación desde Bluesky y pégala aquí.</small>
                    </div>
                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" name="bluesky_auto" id="bluesky_auto" value="1" <?= $blueskyAutoEnabled ? 'checked' : '' ?>>
                        <label for="bluesky_auto" class="form-check-label">Enviar automáticamente cada nueva entrada o itinerario publicado</label>
                    </div>
                    <div class="modal fade" id="blueskyHelpModal" tabindex="-1" role="dialog" aria-labelledby="blueskyHelpModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-lg" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="blueskyHelpModalLabel">Guía rápida para Bluesky</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <ol class="mb-3">
                                        <li>Entra en Bluesky &rarr; Settings &rarr; App Passwords.</li>
                                        <li>Genera una nueva contraseña de aplicación y cópiala.</li>
                                        <li>Introduce tu handle y la contraseña en este formulario.</li>
                                    </ol>
                                    <p class="mb-0">Las publicaciones se enviarán como texto con el enlace a la entrada.</p>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cerrar</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <h4 class="mt-4">Twitter / X (opcional)</h4>
                    <p class="text-muted">Publica un tweet con el título y enlace de cada entrada. <a href="#" data-toggle="modal" data-target="#twitterHelpModal">Ver guía rápida</a></p>
                    <div class="form-group">
                        <label for="twitter_api_key">API Key</label>
                        <input type="text" name="twitter_api_key" id="twitter_api_key" class="form-control" value="<?= htmlspecialchars($twitterSettings['api_key'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="xxxxxxxxxxxxxxxxxxxx">
                        <small class="form-text text-muted">En X for Developers aparece dentro de <code>Keys and tokens</code> como <code>API Key</code> o <code>Consumer Key</code>.</small>
                    </div>
                    <div class="form-group">
                        <label for="twitter_api_secret">API Key Secret</label>
                        <input type="text" name="twitter_api_secret" id="twitter_api_secret" class="form-control" value="<?= htmlspecialchars($twitterSettings['api_secret'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx">
                        <small class="form-text text-muted">En X for Developers aparece como <code>API Key Secret</code> o <code>Consumer Secret</code>.</small>
                    </div>
                    <div class="form-group">
                        <label for="twitter_access_token">Access Token</label>
                        <input type="text" name="twitter_access_token" id="twitter_access_token" class="form-control" value="<?= htmlspecialchars($twitterSettings['access_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx">
                        <small class="form-text text-muted">Debe ser un token de usuario generado después de poner la app en <code>Read and write</code>.</small>
                    </div>
                    <div class="form-group">
                        <label for="twitter_access_secret">Access Token Secret</label>
                        <input type="text" name="twitter_access_secret" id="twitter_access_secret" class="form-control" value="<?= htmlspecialchars($twitterSettings['access_secret'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx">
                        <small class="form-text text-muted">Se genera junto al <code>Access Token</code>. Nammu no usa aquí el <code>Bearer Token</code>.</small>
                    </div>
                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" name="twitter_auto" id="twitter_auto" value="1" <?= $twitterAutoEnabled ? 'checked' : '' ?>>
                        <label for="twitter_auto" class="form-check-label">Enviar automáticamente cada nueva entrada o itinerario publicado</label>
                    </div>
                    <div class="modal fade" id="twitterHelpModal" tabindex="-1" role="dialog" aria-labelledby="twitterHelpModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-lg" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="twitterHelpModalLabel">Guía rápida para Twitter / X</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <h6 class="mb-2">1) Crear la app y activar autenticación de usuario</h6>
                                    <ol class="mb-3">
                                        <li>Entra en <code>https://console.x.com/</code> y crea o abre tu app.</li>
                                        <li>Ve a <strong>Settings</strong> &rarr; <strong>User authentication settings</strong>.</li>
                                        <li>Pulsa <strong>Set up</strong> o <strong>Edit</strong>.</li>
                                        <li>Activa <strong>OAuth 2.0</strong> y pon la app con permiso <strong>Read and write</strong>.</li>
                                        <li>En <strong>App type</strong> puedes usar <strong>Web App</strong> o <strong>Automated App or Bot</strong>.</li>
                                        <li>En <strong>Callback / Redirect URI</strong> pon una URL HTTPS exacta de tu blog, por ejemplo <code>https://memoria.repoblacion.ong/</code>.</li>
                                        <li>En <strong>Website URL</strong> pon la URL pública del blog.</li>
                                        <li>Guarda los cambios.</li>
                                    </ol>

                                    <h6 class="mb-2">2) Regenerar las credenciales correctas</h6>
                                    <ol class="mb-3">
                                        <li>Ve a <strong>Keys and tokens</strong>.</li>
                                        <li>Copia la <strong>API Key</strong> y la <strong>API Key Secret</strong>.</li>
                                        <li>En <strong>Authentication Tokens</strong>, genera o regenera <strong>Access Token and Secret</strong>.</li>
                                        <li>Si acabas de cambiar la app a <strong>Read and write</strong>, regenera estos tokens para que hereden ese permiso.</li>
                                    </ol>

                                    <h6 class="mb-2">3) Qué pegar en Nammu</h6>
                                    <ol class="mb-3">
                                        <li><strong>API Key</strong> &rarr; campo <strong>API Key</strong>.</li>
                                        <li><strong>API Key Secret</strong> &rarr; campo <strong>API Key Secret</strong>.</li>
                                        <li><strong>Access Token</strong> &rarr; campo <strong>Access Token</strong>.</li>
                                        <li><strong>Access Token Secret</strong> &rarr; campo <strong>Access Token Secret</strong>.</li>
                                    </ol>

                                    <h6 class="mb-2">4) Importante</h6>
                                    <ul class="mb-3">
                                        <li>El <strong>Bearer Token</strong> no es el dato correcto para publicar desde Nammu.</li>
                                        <li>Si X devuelve errores de permisos, revisa que la app siga en <strong>Read and write</strong> y vuelve a regenerar el <strong>Access Token</strong> y su <strong>Secret</strong>.</li>
                                        <li>Si cambias permisos o tipo de autenticación, espera unos minutos y vuelve a generar las credenciales.</li>
                                    </ul>

                                    <p class="mb-0">Los mensajes de X se recortarán a un máximo de 280 caracteres cuando haga falta.</p>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cerrar</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <h4 class="mt-4">LinkedIn (opcional)</h4>
                    <p class="text-muted">Publica en LinkedIn con la API oficial usando token y author URN. <a href="#" data-toggle="modal" data-target="#linkedinHelpModal">Ver guía rápida</a></p>
                    <div class="form-group">
                        <label for="linkedin_token">Token de acceso</label>
                        <input type="text" name="linkedin_token" id="linkedin_token" class="form-control" value="<?= htmlspecialchars($linkedinSettings['token'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="AQX...">
                        <small class="form-text text-muted">Debe tener permiso <code>w_member_social</code> (perfil) o <code>w_organization_social</code> (página).</small>
                    </div>
                    <div class="form-group">
                        <label for="linkedin_author">Author URN</label>
                        <input type="text" name="linkedin_author" id="linkedin_author" class="form-control" value="<?= htmlspecialchars($linkedinSettings['author'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="urn:li:person:xxxx o urn:li:organization:xxxx">
                    </div>
                    <div class="form-group">
                        <label for="social_linkedin">URL pública de perfil o página (footer)</label>
                        <input type="text" name="social_linkedin" id="social_linkedin" class="form-control" value="<?= htmlspecialchars($socialLinkedin, ENT_QUOTES, 'UTF-8') ?>" placeholder="https://www.linkedin.com/company/tu-proyecto/">
                    </div>
                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" name="linkedin_auto" id="linkedin_auto" value="1" <?= $linkedinAutoEnabled ? 'checked' : '' ?>>
                        <label for="linkedin_auto" class="form-check-label">Enviar automáticamente cada nueva entrada, podcast o itinerario publicado</label>
                    </div>
                    <div class="modal fade" id="linkedinHelpModal" tabindex="-1" role="dialog" aria-labelledby="linkedinHelpModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-lg" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="linkedinHelpModalLabel">Guía rápida para LinkedIn</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <h6 class="mb-2">1) Crear y preparar la app</h6>
                                    <ol class="mb-3">
                                        <li>Entra en <code>https://developer.linkedin.com/</code> y crea la app.</li>
                                        <li>Antes de continuar, verifica la app (empresa y correo) para que LinkedIn permita solicitar productos avanzados.</li>
                                        <li>En <strong>Auth</strong>, añade una Redirect URL exacta (ejemplo: <code>https://tu-dominio/</code>).</li>
                                        <li>Guarda <strong>Client ID</strong> y <strong>Client Secret</strong> (el Secret no se usa como token ni como URN).</li>
                                    </ol>

                                    <h6 class="mb-2">2) Elegir modo de publicación y permisos</h6>
                                    <p class="mb-2">Debes elegir si publicarás como perfil personal o como página de empresa:</p>
                                    <ul class="mb-3">
                                        <li><strong>Perfil personal</strong>: scopes recomendados <code>openid profile w_member_social</code>.</li>
                                        <li><strong>Página de empresa</strong>: scopes <code>w_organization_social rw_organization_admin</code> y además tu usuario debe ser admin de la página en LinkedIn.</li>
                                    </ul>
                                    <p class="mb-2"><strong>Importante para páginas:</strong> en la pestaña <strong>Products</strong> activa <strong>Community Management API</strong> (si no aparece, la app aún no es elegible).</p>
                                    <p class="mb-2"><strong>Importante:</strong> para habilitar Community Management API, no debes tener otros productos activos incompatibles en la app. Si LinkedIn no deja activarlo, elimina los productos no necesarios y deja Community Management API como producto principal.</p>
                                    <p class="mb-2">Community Management API es el que habilita scopes como <code>w_organization_social</code> y <code>rw_organization_admin</code>.</p>

                                    <h6 class="mb-2">3) Obtener authorization code (OAuth)</h6>
                                    <p class="mb-2">Abre en navegador esta URL (sustituye valores):</p>
                                    <pre class="bg-light p-2 rounded small mb-2"><code>https://www.linkedin.com/oauth/v2/authorization?response_type=code&amp;client_id=TU_CLIENT_ID&amp;redirect_uri=TU_REDIRECT_URI_URLENCODEADA&amp;scope=openid%20profile%20w_member_social</code></pre>
                                    <p class="mb-2">Para páginas de empresa cambia <code>scope</code> por:</p>
                                    <pre class="bg-light p-2 rounded small mb-2"><code>w_organization_social%20rw_organization_admin</code></pre>
                                    <p class="mb-3">Tras autorizar, LinkedIn redirige a tu URL con <code>?code=...</code>. Copia ese <code>code</code>.</p>

                                    <h6 class="mb-2">4) Cambiar code por access token (curl)</h6>
                                    <pre class="bg-light p-2 rounded small mb-3"><code>curl -s -X POST https://www.linkedin.com/oauth/v2/accessToken \
  --data-urlencode grant_type=authorization_code \
  --data-urlencode code='TU_CODE' \
  --data-urlencode redirect_uri='TU_REDIRECT_URI' \
  --data-urlencode client_id='TU_CLIENT_ID' \
  --data-urlencode client_secret='TU_CLIENT_SECRET'</code></pre>
                                    <p class="mb-3">El valor <code>access_token</code> de la respuesta es el que debes pegar en Nammu.</p>

                                    <h6 class="mb-2">5) Obtener Author URN</h6>
                                    <p class="mb-2"><strong>Perfil personal:</strong> con token que incluya <code>openid profile</code> consulta:</p>
                                    <pre class="bg-light p-2 rounded small mb-2"><code>curl -s -H "Authorization: Bearer TU_ACCESS_TOKEN" \
  https://api.linkedin.com/v2/userinfo</code></pre>
                                    <p class="mb-2">Toma el campo <code>sub</code> y construye:</p>
                                    <pre class="bg-light p-2 rounded small mb-3"><code>urn:li:person:SUB</code></pre>

                                    <p class="mb-2"><strong>Página de empresa:</strong> con token que incluya <code>w_organization_social rw_organization_admin</code> consulta:</p>
                                    <pre class="bg-light p-2 rounded small mb-2"><code>curl -s \
  -H "Authorization: Bearer TU_ACCESS_TOKEN" \
  -H "LinkedIn-Version: 202402" \
  -H "X-Restli-Protocol-Version: 2.0.0" \
  "https://api.linkedin.com/v2/organizationalEntityAcls?q=roleAssignee&amp;role=ADMINISTRATOR&amp;state=APPROVED"</code></pre>
                                    <p class="mb-2">Busca <code>organizationalTarget</code> y usa:</p>
                                    <pre class="bg-light p-2 rounded small mb-3"><code>urn:li:organization:ID_NUMERICO</code></pre>

                                    <h6 class="mb-2">6) Rellenar Nammu</h6>
                                    <ol class="mb-3">
                                        <li><strong>Token de acceso</strong>: pega el <code>access_token</code>.</li>
                                        <li><strong>Author URN</strong>: pega <code>urn:li:person:...</code> o <code>urn:li:organization:...</code>.</li>
                                        <li><strong>URL pública</strong>: pega tu perfil/página para mostrar el icono en el footer.</li>
                                        <li>Marca “Enviar automáticamente...” si quieres publicar al crear entradas, podcasts o itinerarios.</li>
                                    </ol>

                                    <h6 class="mb-2">7) Errores frecuentes</h6>
                                    <ul class="mb-0">
                                        <li><code>redirect_uri does not match</code>: la redirect URI no coincide exactamente (incluido slash final).</li>
                                        <li><code>Invalid Urn Format</code>: estás usando Client ID/Secret como URN. El URN debe ser de persona/organización real.</li>
                                        <li><code>ACCESS_DENIED</code>: faltan scopes o permisos de admin en la página.</li>
                                    </ul>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cerrar</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="border rounded p-3 bg-light mt-4">
                        <h4 class="h6 text-uppercase mb-2">Servicios de Podcast</h4>
                        <p class="text-muted mb-3">Usa esta URL para dar de alta el feed: <code><?= htmlspecialchars($podcastFeedUrl, ENT_QUOTES, 'UTF-8') ?></code></p>
                        <div class="form-group">
                            <label for="podcast_spotify">Spotify</label>
                            <input type="text" name="podcast_spotify" id="podcast_spotify" class="form-control" value="<?= htmlspecialchars($podcastSpotify, ENT_QUOTES, 'UTF-8') ?>" placeholder="URL o ID del podcast en Spotify">
                        </div>
                        <div class="form-group">
                            <label for="podcast_ivoox">iVoox</label>
                            <input type="text" name="podcast_ivoox" id="podcast_ivoox" class="form-control" value="<?= htmlspecialchars($podcastIvoox, ENT_QUOTES, 'UTF-8') ?>" placeholder="URL o ID del podcast en iVoox">
                        </div>
                        <div class="form-group">
                            <label for="podcast_apple">Apple Podcasts</label>
                            <input type="text" name="podcast_apple" id="podcast_apple" class="form-control" value="<?= htmlspecialchars($podcastApple, ENT_QUOTES, 'UTF-8') ?>" placeholder="URL o ID en Apple Podcasts">
                        </div>
                        <div class="form-group mb-0">
                            <label for="podcast_youtube_music">YouTube Music</label>
                            <input type="text" name="podcast_youtube_music" id="podcast_youtube_music" class="form-control" value="<?= htmlspecialchars($podcastYouTube, ENT_QUOTES, 'UTF-8') ?>" placeholder="URL o ID en YouTube Music">
                        </div>
                    </div>

                    <button type="submit" name="save_social" class="btn btn-outline-primary mt-3">Guardar redes sociales</button>
                </div>
            </div>
        </form>

        <form method="post" class="mt-4">
            <div class="card">
                <div class="card-body">
                    <h3 class="h5 mb-3">IndexNow</h3>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="indexnow_enabled" id="indexnow_enabled" value="1" <?= $indexnowEnabled ? 'checked' : '' ?>>
                        <label class="form-check-label" for="indexnow_enabled">Enviar nuevos contenidos a IndexNow</label>
                    </div>
                    <p class="text-muted mb-3">Se enviarán automáticamente nuevas entradas, páginas, itinerarios y temas a IndexNow, Bing, Naver, etc.</p>
                    <div class="border rounded p-3 bg-light">
                        <p class="text-muted text-uppercase small mb-2">Estado del sistema</p>
                        <p class="mb-1"><strong>Clave:</strong> <?= $indexnowKey !== '' ? 'Generada' : 'Pendiente' ?></p>
                        <?php if ($indexnowKeyUrl !== ''): ?>
                            <p class="mb-1"><strong>Archivo de verificación:</strong> <a href="<?= htmlspecialchars($indexnowKeyUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><?= htmlspecialchars($indexnowKeyUrl, ENT_QUOTES, 'UTF-8') ?></a></p>
                        <?php endif; ?>
                        <p class="mb-0"><strong>Archivo activo:</strong> <?= $indexnowFileOk ? 'Sí' : 'No' ?></p>
                    </div>
                    <button type="submit" name="save_indexnow_settings" class="btn btn-outline-primary mt-3">Guardar IndexNow</button>
                </div>
            </div>
        </form>

        <script>
            (function() {
                var toggle = document.getElementById('push_enabled');
                var box = document.getElementById('push_preferences');
                if (!toggle || !box) {
                    return;
                }
                toggle.addEventListener('change', function() {
                    box.style.display = toggle.checked ? '' : 'none';
                });
            })();
        </script>
    </div>
<?php endif; ?>
