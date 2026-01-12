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
    $whatsappSettings = $settings['whatsapp'] ?? ['token' => '', 'channel' => '', 'auto_post' => 'off', 'recipient' => ''];
    $whatsappAutoEnabled = ($whatsappSettings['auto_post'] ?? 'off') === 'on';
    $facebookSettings = $settings['facebook'] ?? ['token' => '', 'channel' => '', 'auto_post' => 'off'];
    $facebookAutoEnabled = ($facebookSettings['auto_post'] ?? 'off') === 'on';
    $twitterSettings = $settings['twitter'] ?? ['token' => '', 'channel' => '', 'auto_post' => 'off'];
    $twitterAutoEnabled = ($twitterSettings['auto_post'] ?? 'off') === 'on';
    $twitterApiKey = $twitterSettings['api_key'] ?? '';
    $twitterApiSecret = $twitterSettings['api_secret'] ?? '';
    $twitterAccessToken = $twitterSettings['access_token'] ?? '';
    $twitterAccessSecret = $twitterSettings['access_secret'] ?? '';
    $instagramSettings = $settings['instagram'] ?? ['token' => '', 'channel' => '', 'auto_post' => 'off'];
    $instagramAutoEnabled = ($instagramSettings['auto_post'] ?? 'off') === 'on';
    $podcastServices = $settings['podcast_services'] ?? [];
    $podcastSpotify = trim((string) ($podcastServices['spotify'] ?? ''));
    $podcastIvoox = trim((string) ($podcastServices['ivoox'] ?? ''));
    $podcastApple = trim((string) ($podcastServices['apple'] ?? ''));
    $podcastGoogle = trim((string) ($podcastServices['google'] ?? ''));
    $podcastYouTube = trim((string) ($podcastServices['youtube_music'] ?? ''));
    $indexnowSettings = $settings['indexnow'] ?? [];
    $indexnowEnabled = ($indexnowSettings['enabled'] ?? 'off') === 'on';
    $indexnowStatus = function_exists('admin_indexnow_status') ? admin_indexnow_status() : [];
    $indexnowKey = trim((string) ($indexnowStatus['key'] ?? ''));
    $indexnowKeyUrl = trim((string) ($indexnowStatus['key_url'] ?? ''));
    $indexnowFileOk = (bool) ($indexnowStatus['file_ok'] ?? false);
    $siteBaseUrl = function_exists('nammu_base_url') ? nammu_base_url() : '';
    $podcastFeedUrl = $siteBaseUrl !== '' ? rtrim($siteBaseUrl, '/') . '/podcast.xml' : '/podcast.xml';
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

                    <div class="form-group">
                        <label for="social_default_description">Descripción por defecto</label>
                        <textarea name="social_default_description" id="social_default_description" class="form-control" rows="3" placeholder="Resumen que aparecerá al compartir la portada en redes sociales."><?= htmlspecialchars($socialDefaultDescription, ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="social_home_image">Imagen de la portada para redes sociales y plataformas de podcast</label>
                        <div class="input-group">
                            <input type="text" name="social_home_image" id="social_home_image" class="form-control" value="<?= htmlspecialchars($socialHomeImage, ENT_QUOTES, 'UTF-8') ?>" placeholder="assets/imagen-portada.jpg" readonly>
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-secondary" data-toggle="modal" data-target="#imageModal" data-target-type="field" data-target-input="social_home_image" data-target-prefix="assets/">Seleccionar imagen</button>
                                <button type="button" class="btn btn-outline-danger" id="clear-social-image">Quitar</button>
                            </div>
                        </div>
                        <small class="form-text text-muted">Se utilizará como imagen por defecto para la portada y cuando una entrada no tenga imagen destacada.</small>
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

                    <h4 class="mt-4">WhatsApp (opcional)</h4>
                    <p class="text-muted">Usa la API de WhatsApp Business Cloud para avisar a tus contactos o grupos. <a href="#" data-toggle="modal" data-target="#whatsappHelpModal">Ver guía rápida</a></p>
                    <div class="form-group">
                        <label for="whatsapp_token">Token del bot o app</label>
                        <input type="text" name="whatsapp_token" id="whatsapp_token" class="form-control" value="<?= htmlspecialchars($whatsappSettings['token'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Token de acceso">
                        <small class="form-text text-muted">Token generado en Meta Developers para tu número de WhatsApp Business.</small>
                    </div>
                    <div class="form-group">
                        <label for="whatsapp_channel">ID del número de WhatsApp Business</label>
                        <input type="text" name="whatsapp_channel" id="whatsapp_channel" class="form-control" value="<?= htmlspecialchars($whatsappSettings['channel'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Ej: 123456789012345">
                        <small class="form-text text-muted">Identificador del número conectado en la API de WhatsApp Cloud (phone number ID).</small>
                    </div>
                    <div class="form-group">
                        <label for="whatsapp_recipient">Número destino</label>
                        <input type="text" name="whatsapp_recipient" id="whatsapp_recipient" class="form-control" value="<?= htmlspecialchars($whatsappSettings['recipient'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Ej: 34600111222">
                        <small class="form-text text-muted">Número (con prefijo internacional, sin +) al que se enviará el mensaje.</small>
                    </div>
                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" name="whatsapp_auto" id="whatsapp_auto" value="1" <?= $whatsappAutoEnabled ? 'checked' : '' ?>>
                        <label for="whatsapp_auto" class="form-check-label">Enviar automáticamente cada nueva entrada o itinerario publicado</label>
                    </div>
                    <div class="modal fade" id="whatsappHelpModal" tabindex="-1" role="dialog" aria-labelledby="whatsappHelpModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-lg" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="whatsappHelpModalLabel">Guía rápida para WhatsApp</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <ol class="mb-3">
                                        <li>Activa WhatsApp Business Cloud en Meta Developers.</li>
                                        <li>Obtén el token y el <code>phone number ID</code>.</li>
                                        <li>Indica el número destino en formato internacional (sin +).</li>
                                    </ol>
                                    <p class="mb-0">WhatsApp suele exigir plantillas aprobadas para mensajes fuera de ventana de 24h.</p>
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
                                        <li>Crea una app en Meta Developers y habilita Graph API.</li>
                                        <li>Genera un token con permisos para publicar en la página.</li>
                                        <li>Introduce el ID de la página y el token.</li>
                                    </ol>
                                    <p class="mb-0">Puedes obtener el ID de página en la configuración avanzada de Facebook.</p>
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
                        <label for="twitter_token">Token / Bearer</label>
                        <input type="text" name="twitter_token" id="twitter_token" class="form-control" value="<?= htmlspecialchars($twitterSettings['token'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Bearer ...">
                        <small class="form-text text-muted">Generado desde el portal de desarrolladores de Twitter.</small>
                    </div>
                    <div class="form-group">
                        <label for="twitter_channel">Canal o usuario destino</label>
                        <input type="text" name="twitter_channel" id="twitter_channel" class="form-control" value="<?= htmlspecialchars($twitterSettings['channel'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="@usuario o ID">
                        <small class="form-text text-muted">Usa el nombre de usuario (sin @) o el ID numérico.</small>
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
                                    <ol class="mb-3">
                                        <li>Crea un proyecto en el portal de desarrolladores de X.</li>
                                        <li>Genera un Bearer Token o token con permisos de publicación.</li>
                                        <li>Introduce el token y el usuario o ID de la cuenta.</li>
                                    </ol>
                                    <p class="mb-0">Si el texto supera 280 caracteres se truncará.</p>
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
                                        <li>Convierte tu cuenta en Business o Creator y vincúlala a una página de Facebook.</li>
                                        <li>En Meta Developers crea una app con permisos: <code>instagram_basic</code>, <code>instagram_content_publish</code> y <code>pages_show_list</code>.</li>
                                        <li>Genera un access token de larga duración y consigue el ID de la cuenta de Instagram conectada.</li>
                                        <li>Introduce el token y el ID en este formulario.</li>
                                    </ol>
                                    <p class="mb-0">El enlace se incluye en el texto del post, pero Instagram no lo hace clicable.</p>
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
                        <div class="form-group">
                            <label for="podcast_google">Google Podcasts</label>
                            <input type="text" name="podcast_google" id="podcast_google" class="form-control" value="<?= htmlspecialchars($podcastGoogle, ENT_QUOTES, 'UTF-8') ?>" placeholder="URL o ID en Google Podcasts">
                        </div>
                        <div class="form-group mb-0">
                            <label for="podcast_youtube_music">YouTube Music</label>
                            <input type="text" name="podcast_youtube_music" id="podcast_youtube_music" class="form-control" value="<?= htmlspecialchars($podcastYouTube, ENT_QUOTES, 'UTF-8') ?>" placeholder="URL o ID en YouTube Music">
                        </div>
                    </div>

                    <button type="submit" name="save_social" class="btn btn-outline-primary">Guardar redes sociales</button>
                </div>
            </div>
        </form>

        <form method="post" class="mt-4" id="twitter-media">
            <div class="card">
                <div class="card-body">
                    <h4 class="h6 text-uppercase mb-2">Twitter / X (imágenes en podcasts)</h4>
                    <p class="text-muted mb-3">Estas credenciales se usan para subir imágenes cuando compartes un podcast. <a href="#" data-toggle="modal" data-target="#twitterMediaHelpModal">Ver guía rápida</a></p>
                    <div class="form-group">
                        <label for="twitter_api_key">API Key (Consumer Key)</label>
                        <input type="text" name="twitter_api_key" id="twitter_api_key" class="form-control" value="<?= htmlspecialchars($twitterApiKey, ENT_QUOTES, 'UTF-8') ?>" placeholder="API Key">
                    </div>
                    <div class="form-group">
                        <label for="twitter_api_secret">API Secret (Consumer Secret)</label>
                        <input type="text" name="twitter_api_secret" id="twitter_api_secret" class="form-control" value="<?= htmlspecialchars($twitterApiSecret, ENT_QUOTES, 'UTF-8') ?>" placeholder="API Secret">
                    </div>
                    <div class="form-group">
                        <label for="twitter_access_token">Access Token (Read/Write)</label>
                        <input type="text" name="twitter_access_token" id="twitter_access_token" class="form-control" value="<?= htmlspecialchars($twitterAccessToken, ENT_QUOTES, 'UTF-8') ?>" placeholder="Access Token">
                    </div>
                    <div class="form-group">
                        <label for="twitter_access_secret">Access Token Secret</label>
                        <input type="text" name="twitter_access_secret" id="twitter_access_secret" class="form-control" value="<?= htmlspecialchars($twitterAccessSecret, ENT_QUOTES, 'UTF-8') ?>" placeholder="Access Token Secret">
                    </div>
                    <div class="text-right">
                        <button type="submit" name="save_twitter_media" class="btn btn-outline-primary">Guardar Twitter / X (media)</button>
                    </div>
                </div>
            </div>
        </form>

        <div class="modal fade" id="twitterMediaHelpModal" tabindex="-1" role="dialog" aria-labelledby="twitterMediaHelpModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="twitterMediaHelpModalLabel">Guía rápida: credenciales para subir imágenes en X</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <ol class="mb-3">
                            <li>Entra en <strong>X Developer Portal</strong> y crea un proyecto + app.</li>
                            <li>En la app, activa el acceso <strong>OAuth 1.0a</strong> y permisos <strong>Read and Write</strong>.</li>
                            <li>Genera y copia <strong>API Key</strong> y <strong>API Secret</strong>.</li>
                            <li>Genera <strong>Access Token</strong> y <strong>Access Token Secret</strong> (usuario propietario).</li>
                            <li>Pega esos cuatro valores aquí y guarda.</li>
                        </ol>
                        <p class="mb-0 text-muted">Estas credenciales se usan únicamente para subir imágenes cuando compartes podcasts.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>

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
