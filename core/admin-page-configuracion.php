<?php if ($page === 'configuracion'): ?>

    <div class="tab-pane active">

        <h2>Configuración</h2>

        <?php if ($accountFeedback !== null): ?>
            <div class="alert alert-<?= $accountFeedback['type'] === 'success' ? 'success' : 'danger' ?>">
                <?= htmlspecialchars($accountFeedback['message'], ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>
        <?php
        $telegramSettings = $settings['telegram'] ?? ['token' => '', 'channel' => '', 'auto_post' => 'off'];
        $telegramAutoEnabled = ($telegramSettings['auto_post'] ?? 'off') === 'on';
        $whatsappSettings = $settings['whatsapp'] ?? ['token' => '', 'channel' => '', 'auto_post' => 'off'];
        $whatsappAutoEnabled = ($whatsappSettings['auto_post'] ?? 'off') === 'on';
        $facebookSettings = $settings['facebook'] ?? ['token' => '', 'channel' => '', 'auto_post' => 'off'];
        $facebookAutoEnabled = ($facebookSettings['auto_post'] ?? 'off') === 'on';
        $twitterSettings = $settings['twitter'] ?? ['token' => '', 'channel' => '', 'auto_post' => 'off'];
        $twitterAutoEnabled = ($twitterSettings['auto_post'] ?? 'off') === 'on';
        ?>

        <form method="post">

            <div class="form-group">
                <label class="d-block">Modo de funcionamiento</label>
                <div class="btn-group btn-group-sm btn-group-toggle" role="group" data-toggle="buttons">
                    <?php
                    $modeIsAlpha = ($settings['sort_order'] ?? 'date') === 'alpha';
                    ?>
                    <label class="btn btn-outline-primary <?= !$modeIsAlpha ? 'active' : '' ?>">
                        <input type="radio"
                            name="sort_order"
                            id="sort_order_date"
                            value="date"
                            class="sr-only"
                            autocomplete="off"
                            <?= !$modeIsAlpha ? 'checked' : '' ?>>
                        Modo Blog
                    </label>
                    <label class="btn btn-outline-primary <?= $modeIsAlpha ? 'active' : '' ?>">
                        <input type="radio"
                            name="sort_order"
                            id="sort_order_alpha"
                            value="alpha"
                            class="sr-only"
                            autocomplete="off"
                            <?= $modeIsAlpha ? 'checked' : '' ?>>
                        Modo Diccionario
                    </label>
                </div>
                <small class="form-text text-muted">El modo blog ordena por fecha, el modo diccionario agrupa las entradas por orden alfabético.</small>
            </div>

            <div class="form-group">
                <label for="site_author">Nombre del autor u organización</label>
                <input type="text" name="site_author" id="site_author" class="form-control" value="<?= htmlspecialchars($settings['site_author'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Fundación Repoblación">
            </div>

            <div class="form-group">
                <label for="site_name">Nombre del blog</label>
                <input type="text" name="site_name" id="site_name" class="form-control" value="<?= htmlspecialchars($settings['site_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Memoria">
            </div>

            <div class="form-group">

                <label for="google_fonts_api">API Key de Google Fonts</label>

                <input type="text" name="google_fonts_api" id="google_fonts_api" class="form-control" value="<?= htmlspecialchars($settings['google_fonts_api'] ?? '') ?>" placeholder="AIza...">

                <small class="form-text text-muted">Introduce tu clave API para cargar fuentes personalizadas desde Google Fonts.</small>

            </div>

            <div class="text-right mb-4">
                <button type="submit" name="save_settings" class="btn btn-primary">Guardar configuración general</button>
            </div>

        </form>

        <form method="post">

            <h4 class="mt-4">Integración con Redes Sociales</h4>

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

            <div class="text-right mb-4">
                <button type="submit" name="save_social" class="btn btn-outline-primary">Guardar redes sociales</button>
            </div>

        </form>

            <hr class="my-5">
            <h3>Cuenta de acceso</h3>
            <p class="text-muted">Actualiza las credenciales utilizadas para acceder al panel. Necesitas confirmar la contraseña actual.</p>

            <form method="post" autocomplete="off">
                <div class="form-group">
                    <label for="new_username">Nombre de usuario</label>
                    <input type="text" name="new_username" id="new_username" class="form-control" value="<?= htmlspecialchars($currentUsername, ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="form-group">
                    <label for="current_password">Contraseña actual</label>
                    <input type="password" name="current_password" id="current_password" class="form-control" required>
                    <small class="form-text text-muted">Se utiliza para verificar que eres la persona autorizada.</small>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="new_password">Nueva contraseña</label>
                        <input type="password" name="new_password" id="new_password" class="form-control" autocomplete="new-password" placeholder="Deja en blanco para mantener la actual">
                    </div>
                    <div class="form-group col-md-6">
                        <label for="confirm_password">Confirmar nueva contraseña</label>
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control" autocomplete="new-password" placeholder="Repite la nueva contraseña">
                    </div>
                </div>
                <button type="submit" name="update_account" class="btn btn-outline-primary">Actualizar cuenta</button>
            </form>

        </div>

<?php endif; ?>
