<?php if ($page === 'configuracion'): ?>

    <div class="tab-pane active">

        <h2>Configuración</h2>

        <?php if ($accountFeedback !== null): ?>
            <div class="alert alert-<?= $accountFeedback['type'] === 'success' ? 'success' : 'danger' ?>">
                <?= htmlspecialchars($accountFeedback['message'], ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>
        <?php if (isset($searchConsoleFeedback) && $searchConsoleFeedback !== null): ?>
            <div class="alert alert-<?= $searchConsoleFeedback['type'] === 'success' ? 'success' : 'danger' ?>">
                <?= htmlspecialchars($searchConsoleFeedback['message'], ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>
        <?php if (isset($bingWebmasterFeedback) && $bingWebmasterFeedback !== null): ?>
            <div class="alert alert-<?= $bingWebmasterFeedback['type'] === 'success' ? 'success' : 'danger' ?>">
                <?= htmlspecialchars($bingWebmasterFeedback['message'], ENT_QUOTES, 'UTF-8') ?>
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
        $twitterApiKey = $twitterSettings['api_key'] ?? '';
        $twitterApiSecret = $twitterSettings['api_secret'] ?? '';
        $twitterAccessToken = $twitterSettings['access_token'] ?? '';
        $twitterAccessSecret = $twitterSettings['access_secret'] ?? '';
        $mailingSettings = $settings['mailing'] ?? [];
        $mailingGmail = $mailingSettings['gmail_address'] ?? '';
        $mailingClientId = $mailingSettings['client_id'] ?? '';
        $mailingClientSecret = $mailingSettings['client_secret'] ?? '';
        $mailingStatus = $mailingSettings['status'] ?? 'disconnected';
        $siteLang = $settings['site_lang'] ?? 'es';
        $searchConsoleSettings = $settings['search_console'] ?? [];
        $gscProperty = $searchConsoleSettings['property'] ?? '';
        $gscClientId = $searchConsoleSettings['client_id'] ?? '';
        $gscClientSecret = $searchConsoleSettings['client_secret'] ?? '';
        $gscRefreshToken = $searchConsoleSettings['refresh_token'] ?? '';
        $bingWebmasterSettings = $settings['bing_webmaster'] ?? [];
        $bingSiteUrl = $bingWebmasterSettings['site_url'] ?? '';
        $bingClientId = $bingWebmasterSettings['client_id'] ?? '';
        $bingClientSecret = $bingWebmasterSettings['client_secret'] ?? '';
        $bingRefreshToken = $bingWebmasterSettings['refresh_token'] ?? '';
        $bingAccessToken = $bingWebmasterSettings['access_token'] ?? '';
        $bingApiKey = $bingWebmasterSettings['api_key'] ?? '';
        $bingRedirectUri = function_exists('admin_bing_oauth_redirect_uri') ? admin_bing_oauth_redirect_uri() : '';
        $languageOptions = [
            'es' => 'Español',
            'ca' => 'Català',
            'eu' => 'Euskera',
            'gl' => 'Galego',
            'en' => 'English',
            'fr' => 'Français',
            'it' => 'Italiano',
            'pt' => 'Português',
            'de' => 'Deutsch',
        ];
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
                <label for="social_default_description">Descripción por defecto</label>
                <textarea name="social_default_description" id="social_default_description" class="form-control" rows="3" placeholder="Resumen que aparecerá al compartir la portada en redes sociales."><?= htmlspecialchars($socialDefaultDescription, ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>

            <div class="form-group">
                <label for="site_url">URL pública (opcional)</label>
                <input type="url" name="site_url" id="site_url" class="form-control" value="<?= htmlspecialchars($settings['site_url'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="https://tusitio.com">
                <small class="form-text text-muted">Úsala para fijar la URL del sitio en los feeds RSS y enlaces absolutos. Déjala vacía para usar automáticamente el host de la petición.</small>
            </div>

            <div class="form-group">
                <label for="site_lang">Lengua del blog</label>
                <select name="site_lang" id="site_lang" class="form-control">
                    <?php foreach ($languageOptions as $code => $label): ?>
                        <option value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>" <?= $siteLang === $code ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="form-text text-muted">Se usa como idioma por defecto en la portada y nuevas entradas.</small>
            </div>

            <div class="text-right mb-4">
                <button type="submit" name="save_settings" class="btn btn-primary">Guardar configuración general</button>
            </div>

        </form>

        <form method="post" class="mt-4">
            <h4 class="mt-2">Google Fonts</h4>
            <p class="text-muted mb-3">Gestiona la API Key que permite cargar tipografías desde Google Fonts.</p>
            <div class="form-group">
                <label for="google_fonts_api">API Key de Google Fonts</label>
                <input type="text" name="google_fonts_api" id="google_fonts_api" class="form-control" value="<?= htmlspecialchars($settings['google_fonts_api'] ?? '') ?>" placeholder="AIza...">
                <small class="form-text text-muted">Introduce tu clave API para cargar fuentes personalizadas desde Google Fonts. <a href="#" data-toggle="modal" data-target="#googleFontsHelpModal">Ver guía rápida</a></small>
            </div>
            <div class="text-right mb-4">
                <button type="submit" name="save_google_fonts" class="btn btn-outline-primary">Guardar Google Fonts</button>
            </div>
        </form>

        <form method="post" class="mt-4">
            <h4 class="mt-2">Google Search Console</h4>
            <p class="text-muted mb-3">Conecta Search Console para acceder a datos de búsquedas.</p>
            <div class="form-group">
                <label for="gsc_property">Google Search Console (propiedad)</label>
                <input type="url" name="gsc_property" id="gsc_property" class="form-control" value="<?= htmlspecialchars($gscProperty, ENT_QUOTES, 'UTF-8') ?>" placeholder="https://tusitio.com/">
                <small class="form-text text-muted">Introduce la propiedad exacta verificada en Search Console. <a href="#" data-toggle="modal" data-target="#gscHelpModal">Ver guía rápida</a></small>
            </div>
            <div class="form-group">
                <label for="gsc_client_id">Google Client ID (Search Console)</label>
                <input type="text" name="gsc_client_id" id="gsc_client_id" class="form-control" value="<?= htmlspecialchars($gscClientId, ENT_QUOTES, 'UTF-8') ?>" placeholder="xxxxxxxx.apps.googleusercontent.com">
            </div>
            <div class="form-group">
                <label for="gsc_client_secret">Google Client Secret (Search Console)</label>
                <input type="text" name="gsc_client_secret" id="gsc_client_secret" class="form-control" value="<?= htmlspecialchars($gscClientSecret, ENT_QUOTES, 'UTF-8') ?>" placeholder="********">
            </div>
            <div class="form-group">
                <label for="gsc_refresh_token">Refresh Token (Search Console)</label>
                <input type="text" name="gsc_refresh_token" id="gsc_refresh_token" class="form-control" value="<?= htmlspecialchars($gscRefreshToken, ENT_QUOTES, 'UTF-8') ?>" placeholder="1//0g...">
                <small class="form-text text-muted">Necesario para consultar datos de búsquedas desde el servidor.</small>
            </div>
            <div class="d-flex flex-wrap align-items-center justify-content-end gap-2 mb-4">
                <button type="submit" name="test_gsc" class="btn btn-outline-primary mb-2">Guardar y probar</button>
            </div>
        </form>

        <form method="post" class="mt-4">
            <h4 class="mt-2">Microsoft Bing Webmaster Tools</h4>
            <p class="text-muted mb-3">Conecta Bing Webmaster Tools con OAuth para acceder a datos de búsquedas.</p>
            <div class="form-group">
                <label for="bing_site_url">Bing Webmaster Tools (sitio)</label>
                <input type="url" name="bing_site_url" id="bing_site_url" class="form-control" value="<?= htmlspecialchars($bingSiteUrl, ENT_QUOTES, 'UTF-8') ?>" placeholder="https://tusitio.com/">
                <small class="form-text text-muted">Introduce la URL exacta verificada en Bing Webmaster Tools. <a href="#" data-toggle="modal" data-target="#bingHelpModal">Ver guía rápida</a></small>
            </div>
            <div class="form-group">
                <label for="bing_client_id">Client ID (Bing OAuth)</label>
                <input type="text" name="bing_client_id" id="bing_client_id" class="form-control" value="<?= htmlspecialchars($bingClientId, ENT_QUOTES, 'UTF-8') ?>" placeholder="xxxxxxxxxxxxxxxxxxxx">
            </div>
            <div class="form-group">
                <label for="bing_client_secret">Client Secret (Bing OAuth)</label>
                <input type="text" name="bing_client_secret" id="bing_client_secret" class="form-control" value="<?= htmlspecialchars($bingClientSecret, ENT_QUOTES, 'UTF-8') ?>" placeholder="********">
            </div>
            <div class="form-group">
                <label for="bing_api_key">API Key (Bing Webmaster Tools)</label>
                <input type="text" name="bing_api_key" id="bing_api_key" class="form-control" value="<?= htmlspecialchars($bingApiKey, ENT_QUOTES, 'UTF-8') ?>" placeholder="xxxxxxxxxxxxxxxxxxxx">
                <small class="form-text text-muted">Algunos endpoints de estadísticas siguen requiriendo API Key.</small>
            </div>
            <div class="form-group">
                <label for="bing_redirect_uri">Redirect URI (Bing OAuth)</label>
                <input type="text" id="bing_redirect_uri" class="form-control" value="<?= htmlspecialchars($bingRedirectUri, ENT_QUOTES, 'UTF-8') ?>" readonly>
                <small class="form-text text-muted">Copia esta URL como redirect URI en la app de Bing.</small>
            </div>
            <div class="form-group">
                <label>Estado OAuth</label>
                <div class="form-control-plaintext text-muted">
                    <?= ($bingRefreshToken !== '' || $bingAccessToken !== '') ? 'Conectado' : 'Sin conectar' ?>
                </div>
            </div>
            <div class="d-flex flex-wrap align-items-center justify-content-end gap-2 mb-4">
                <button type="submit" name="test_bing" class="btn btn-outline-primary mb-2">Guardar y probar</button>
                <a href="admin.php?bing_oauth=start" class="btn btn-outline-secondary mb-2">Conectar con Bing</a>
            </div>
        </form>

        <div class="modal fade" id="googleFontsHelpModal" tabindex="-1" role="dialog" aria-labelledby="googleFontsHelpModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="googleFontsHelpModalLabel">Guía rápida: API Key de Google Fonts</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <ol class="mb-0">
                            <li>Entra en <strong>Google Cloud Console</strong> y crea un proyecto nuevo.</li>
                            <li>Ve a <strong>APIs y servicios &gt; Biblioteca</strong> y habilita <strong>Google Fonts Developer API</strong>.</li>
                            <li>En <strong>APIs y servicios &gt; Credenciales</strong> crea una <strong>Clave de API</strong>.</li>
                            <li>Copia la clave y pégala en este campo.</li>
                        </ol>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="gscHelpModal" tabindex="-1" role="dialog" aria-labelledby="gscHelpModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="gscHelpModalLabel">Guía rápida: Google Search Console API</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <ol class="mb-3">
                            <li>Verifica tu sitio en <strong>Google Search Console</strong> y copia la propiedad exacta.</li>
                            <li>En <strong>Google Cloud Console</strong>, crea o selecciona un proyecto.</li>
                            <li>Habilita <strong>Google Search Console API</strong> en <strong>APIs y servicios &gt; Biblioteca</strong>.</li>
                            <li>Configura la <strong>Pantalla de consentimiento OAuth</strong>.</li>
                            <li>Crea un <strong>ID de cliente OAuth</strong> tipo <strong>Aplicación web</strong>.</li>
                            <li>Ve a <a href="https://developers.google.com/oauthplayground" target="_blank" rel="noopener">OAuth 2.0 Playground</a> y abre el icono de ajustes (⚙️).</li>
                            <li>Activa <strong>Use your own OAuth credentials</strong> y pega tu Client ID y Client Secret.</li>
                            <li>En <strong>Step 1</strong>, añade el scope <code>https://www.googleapis.com/auth/webmasters.readonly</code> y pulsa <strong>Authorize APIs</strong>.</li>
                            <li>En <strong>Step 2</strong>, pulsa <strong>Exchange authorization code for tokens</strong>.</li>
                            <li>Copia el <strong>refresh_token</strong> generado y pégalo aquí.</li>
                            <li>Pega también la propiedad, el Client ID y el Client Secret.</li>
                        </ol>
                        <p class="mb-0 text-muted">El proyecto debe tener acceso a la propiedad en Search Console.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="bingHelpModal" tabindex="-1" role="dialog" aria-labelledby="bingHelpModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="bingHelpModalLabel">Guía rápida: Bing Webmaster Tools (OAuth)</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <ol class="mb-3">
                            <li>Verifica tu sitio en <strong>Bing Webmaster Tools</strong>.</li>
                            <li>Entra en <strong>Settings &gt; API Access</strong> y crea una aplicación OAuth.</li>
                            <li>Configura el <strong>Redirect URI</strong> con este valor: <strong><?= htmlspecialchars($bingRedirectUri, ENT_QUOTES, 'UTF-8') ?></strong>.</li>
                            <li>Copia el <strong>Client ID</strong> y el <strong>Client Secret</strong>.</li>
                            <li>Pega esos datos aquí y pulsa <strong>Conectar con Bing</strong> para autorizar la cuenta.</li>
                        </ol>
                        <p class="mb-0 text-muted">El scope necesario es <strong>webmaster.manage</strong> y los refresh tokens se rotan en cada uso. Si los datos no aparecen, añade también la <strong>API Key</strong> del panel (algunos endpoints la siguen exigiendo).</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>

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
