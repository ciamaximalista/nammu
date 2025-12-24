<?php if ($page === 'anuncios'): ?>
    <?php
    $settings = get_settings();
    $ads = $settings['ads'] ?? [];
    $adsEnabled = ($ads['enabled'] ?? 'off') === 'on';
    $adsScope = $ads['scope'] ?? 'home';
    $adsText = trim((string) ($ads['text'] ?? ''));
    $adsImage = trim((string) ($ads['image'] ?? ''));
    $pushEnabled = ($ads['push_enabled'] ?? 'off') === 'on';
    $pushPosts = ($ads['push_posts'] ?? 'off') === 'on';
    $pushItineraries = ($ads['push_itineraries'] ?? 'off') === 'on';
    $telegramSettings = $settings['telegram'] ?? ['token' => '', 'channel' => '', 'auto_post' => 'off'];
    $telegramAutoEnabled = ($telegramSettings['auto_post'] ?? 'off') === 'on';
    $whatsappSettings = $settings['whatsapp'] ?? ['token' => '', 'channel' => '', 'auto_post' => 'off', 'recipient' => ''];
    $whatsappAutoEnabled = ($whatsappSettings['auto_post'] ?? 'off') === 'on';
    $facebookSettings = $settings['facebook'] ?? ['token' => '', 'channel' => '', 'auto_post' => 'off'];
    $facebookAutoEnabled = ($facebookSettings['auto_post'] ?? 'off') === 'on';
    $twitterSettings = $settings['twitter'] ?? ['token' => '', 'channel' => '', 'auto_post' => 'off'];
    $twitterAutoEnabled = ($twitterSettings['auto_post'] ?? 'off') === 'on';
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
                        <p class="text-muted text-uppercase small mb-2">Preferencias de envio</p>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="push_posts" id="push_posts" value="1" <?= $pushPosts ? 'checked' : '' ?>>
                            <label class="form-check-label" for="push_posts">Enviar aviso de cada nueva entrada publicada</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="push_itineraries" id="push_itineraries" value="1" <?= $pushItineraries ? 'checked' : '' ?>>
                            <label class="form-check-label" for="push_itineraries">Enviar aviso de cada nuevo itinerario publicado</label>
                        </div>
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
                    <p class="text-muted">Conecta un bot y un canal/grupo para compartir automáticamente las nuevas entradas.</p>
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
                        <label for="telegram_auto" class="form-check-label">Enviar automáticamente cada nueva entrada publicada</label>
                    </div>

                    <h4 class="mt-4">WhatsApp (opcional)</h4>
                    <p class="text-muted">Usa la API de WhatsApp Business Cloud para avisar a tus contactos o grupos.</p>
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
                        <label for="whatsapp_auto" class="form-check-label">Enviar automáticamente cada nueva entrada publicada</label>
                    </div>

                    <h4 class="mt-4">Facebook (opcional)</h4>
                    <p class="text-muted">Comparte tus entradas en una página o grupo usando la Graph API.</p>
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
                        <label for="facebook_auto" class="form-check-label">Enviar automáticamente cada nueva entrada publicada</label>
                    </div>

                    <h4 class="mt-4">Twitter / X (opcional)</h4>
                    <p class="text-muted">Publica un tweet con el título y enlace de cada entrada.</p>
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
                        <label for="twitter_auto" class="form-check-label">Enviar automáticamente cada nueva entrada publicada</label>
                    </div>

                    <button type="submit" name="save_social" class="btn btn-outline-primary">Guardar redes sociales</button>
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
