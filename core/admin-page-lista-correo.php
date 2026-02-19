<?php if ($page === 'lista-correo'): ?>

    <div class="tab-pane active">

        <h2>Lista de Correo</h2>

        <?php
        $mailingSettings = $settings['mailing'] ?? [];
        $mailingGmail = $mailingSettings['gmail_address'] ?? '';
        $mailingStatus = $mailingSettings['status'] ?? 'disconnected';
        $mailingClientId = $mailingSettings['client_id'] ?? '';
        $mailingClientSecret = $mailingSettings['client_secret'] ?? '';
        $mailingAutoPosts = ($mailingSettings['auto_posts'] ?? 'off') === 'on';
        $mailingAutoItineraries = ($mailingSettings['auto_itineraries'] ?? 'off') === 'on';
        $mailingAutoPodcast = ($mailingSettings['auto_podcast'] ?? 'off') === 'on';
        $mailingAutoNewsletter = ($mailingSettings['auto_newsletter'] ?? 'off') === 'on';
        $mailingFormat = $mailingSettings['format'] ?? 'html';
        $mailingEntries = admin_load_mailing_subscriber_entries();
        $mailingCount = count($mailingEntries);
        $mailingTokens = admin_load_mailing_tokens();
        $isConnected = !empty($mailingTokens['refresh_token']);
        $canConnect = $mailingGmail !== '' && $mailingClientId !== '' && $mailingClientSecret !== '';
        $subsQuery = trim((string) ($_GET['subs_q'] ?? ''));
        $mailingFilteredEntries = $mailingEntries;
        if ($subsQuery !== '') {
            $mailingFilteredEntries = array_values(array_filter($mailingEntries, static function ($subscriber) use ($subsQuery): bool {
                $email = (string) ($subscriber['email'] ?? '');
                return $email !== '' && stripos($email, $subsQuery) !== false;
            }));
        }
        $mailingFilteredCount = count($mailingFilteredEntries);
        $subsPerPage = 25;
        $subsPage = (int) ($_GET['subs_page'] ?? 1);
        if ($subsPage < 1) {
            $subsPage = 1;
        }
        $subsTotalPages = $mailingFilteredCount > 0 ? (int) ceil($mailingFilteredCount / $subsPerPage) : 1;
        if ($subsPage > $subsTotalPages) {
            $subsPage = $subsTotalPages;
        }
        $subsOffset = ($subsPage - 1) * $subsPerPage;
        $mailingEntriesPage = array_slice($mailingFilteredEntries, $subsOffset, $subsPerPage);
        $subsQueryParam = $subsQuery !== '' ? '&amp;subs_q=' . rawurlencode($subsQuery) : '';
        ?>

        <p class="text-muted">Configura y consulta aquí la futura lista de correo. Usaremos tu cuenta de Gmail (SMTP con OAuth2 sobre SSL/TLS, puerto 465) para enviar mensajes cuando el módulo esté activo.</p>

        <div class="card mb-3" id="mailing">
            <div class="card-body">
                <h5 class="card-title mb-3">Correo de la lista (Gmail)</h5>
                <p class="text-muted mb-3">Indica la dirección de Gmail que se usará para enviar correos a la lista. El envío utiliza el servidor de Gmail con autenticación OAuth2.</p>
                <form method="post">
                    <div class="form-group">
                        <label for="mailing_gmail">Dirección de Gmail</label>
                        <input type="email" name="mailing_gmail" id="mailing_gmail" class="form-control" value="<?= htmlspecialchars($mailingGmail, ENT_QUOTES, 'UTF-8') ?>" placeholder="tunombre@gmail.com">
                        <small class="form-text text-muted">Servidor: smtp.gmail.com · Puerto: 465 · Seguridad: SSL/TLS · Método: OAuth2.</small>
                    </div>
                    <div class="form-group">
                        <label for="mailing_client_id">Google Client ID</label>
                        <input type="text" name="mailing_client_id" id="mailing_client_id" class="form-control" value="<?= htmlspecialchars($mailingClientId, ENT_QUOTES, 'UTF-8') ?>" placeholder="xxxxxxxx.apps.googleusercontent.com">
                        <small class="form-text text-muted">Credenciales OAuth 2.0 (tipo aplicación web) desde Google Cloud Console. <a href="#" data-toggle="modal" data-target="#gmailOAuthHelpModal">Ver guía rápida</a></small>
                    </div>
                    <div class="form-group">
                        <label for="mailing_client_secret">Google Client Secret</label>
                        <input type="text" name="mailing_client_secret" id="mailing_client_secret" class="form-control" value="<?= htmlspecialchars($mailingClientSecret, ENT_QUOTES, 'UTF-8') ?>" placeholder="********">
                    </div>
                    <div class="form-group">
                        <label>Estado</label>
                        <div>
                            <?php if ($mailingStatus === 'connected'): ?>
                                <span class="badge badge-success">Conectado</span>
                            <?php elseif ($mailingStatus === 'pending' && $mailingGmail !== ''): ?>
                                <span class="badge badge-warning">Pendiente de conectar</span>
                            <?php else: ?>
                                <span class="badge badge-secondary">Sin configurar</span>
                            <?php endif; ?>
                        </div>
                        <small class="form-text text-muted">Necesitarás autorizar con Google desde esta misma pestaña.</small>
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                        <button type="submit" name="save_mailing" class="btn btn-outline-primary mr-2 mb-2">Guardar correo de la lista</button>
                        <a class="btn btn-outline-secondary mb-2 <?= $mailingGmail === '' || $mailingClientId === '' || $mailingClientSecret === '' ? 'disabled' : '' ?>" href="<?= $mailingGmail !== '' && $mailingClientId !== '' && $mailingClientSecret !== '' ? 'admin.php?page=lista-correo&gmail_auth=1' : '#' ?>">Conectar con Google</a>
                    </div>
                    <?php if (!empty($mailingGmail)): ?>
                        <div class="alert alert-info mb-0">
                            <?= htmlspecialchars($mailingGmail, ENT_QUOTES, 'UTF-8') ?> está guardado. Conecta aquí para autorizar envíos con OAuth2.
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning mb-0">Aún no hay dirección configurada. Guárdala para habilitar la autenticación con Google.</div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <?php if ($mailingFeedback !== null): ?>
            <div class="alert alert-<?= htmlspecialchars($mailingFeedback['type'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($mailingFeedback['message'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php if ($mailingGmail === ''): ?>
            <div class="alert alert-warning">
                Aún no has configurado una dirección de Gmail. Completa el bloque <a href="#mailing" class="alert-link">Correo de la lista</a> para continuar.
            </div>
        <?php else: ?>
            <div class="card mb-3">
                <div class="card-body">
                    <h5 class="card-title mb-3">Cuenta de envío</h5>
                    <p class="mb-2"><strong>Dirección:</strong> <?= htmlspecialchars($mailingGmail, ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="mb-2"><strong>Servidor:</strong> smtp.gmail.com · Puerto 465 · SSL/TLS · OAuth2</p>
                    <p class="mb-0">
                        <strong>Estado:</strong>
                        <?php if ($isConnected): ?>
                            <span class="badge badge-success">Conectado</span>
                        <?php elseif ($mailingStatus === 'pending'): ?>
                            <span class="badge badge-warning">Pendiente de conectar con Google</span>
                        <?php else: ?>
                            <span class="badge badge-secondary">Desconectado</span>
                        <?php endif; ?>
                    </p>
                    <div class="mt-3 d-flex flex-wrap" style="gap: 0.5rem;">
                        <a class="btn btn-sm btn-primary <?= $canConnect ? '' : 'disabled' ?>" href="<?= $canConnect ? 'admin.php?page=lista-correo&amp;gmail_auth=1' : '#'; ?>">Conectar con Google</a>
                        <a class="btn btn-sm btn-outline-danger <?= !$isConnected ? 'disabled' : '' ?>" href="admin.php?page=lista-correo&amp;gmail_disconnect=1" onclick="return confirm('¿Desconectar y borrar tokens?');">Desconectar</a>
                        <a class="btn btn-sm btn-outline-secondary" href="?page=configuracion#mailing">Editar configuración</a>
                    </div>
                    <?php if ($isConnected): ?>
                        <div class="alert alert-success mt-3 mb-0">
                            Tokens guardados. Usa esta cuenta para enviar cuando activemos el envío de campañas.
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning mt-3 mb-0">
                            <?php if (!$canConnect): ?>
                                Completa el correo, Client ID y Client Secret en Configuración y vuelve a conectar.
                            <?php else: ?>
                                Autoriza con Google para obtener el refresh token y habilitar envíos.
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($mailingGmail !== ''): ?>
            <div class="card mb-4" id="suscriptores">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
                        <div>
                            <h5 class="mb-1">Suscriptores</h5>
                            <small class="text-muted">Total: <?= (int) $mailingCount ?></small>
                        </div>
                        <form method="post" class="form-inline">
                            <input type="hidden" name="add_subscriber" value="1">
                            <label class="sr-only" for="subscriber_email">Correos</label>
                            <textarea class="form-control form-control-sm mr-2 mb-2" name="subscriber_email" id="subscriber_email" rows="2" placeholder="correo1@ejemplo.com, correo2@ejemplo.com&#10;correo3@ejemplo.com" required></textarea>
                            <button type="submit" class="btn btn-sm btn-primary mb-2">Añadir</button>
                        </form>
                    </div>
                    <p class="text-muted small mb-3">Puedes añadir uno o varios correos separados por comas o por saltos de línea.</p>
                    <form method="get" class="form-inline mb-3">
                        <input type="hidden" name="page" value="lista-correo">
                        <label class="sr-only" for="subs_q">Buscar correo</label>
                        <input type="text" class="form-control form-control-sm mr-2 mb-2" name="subs_q" id="subs_q" value="<?= htmlspecialchars($subsQuery, ENT_QUOTES, 'UTF-8') ?>" placeholder="Buscar por correo">
                        <button type="submit" class="btn btn-sm btn-outline-primary mr-2 mb-2">Buscar</button>
                        <?php if ($subsQuery !== ''): ?>
                            <a class="btn btn-sm btn-outline-secondary mb-2" href="?page=lista-correo#suscriptores">Limpiar</a>
                        <?php endif; ?>
                    </form>
                    <?php if ($subsQuery !== ''): ?>
                        <p class="text-muted small">Mostrando <?= (int) $mailingFilteredCount ?> de <?= (int) $mailingCount ?> suscriptores.</p>
                    <?php endif; ?>
                    <?php if (empty($mailingFilteredEntries)): ?>
                        <?php if ($mailingCount === 0): ?>
                            <p class="text-muted mb-0">Aún no hay suscriptores. Añade el primero usando el formulario.</p>
                        <?php else: ?>
                            <p class="text-muted mb-0">No hay resultados para esa búsqueda.</p>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-borderless mb-0">
                                <thead>
                                    <tr>
                                        <th scope="col">Correo</th>
                                        <th scope="col">Suscripcion</th>
                                        <th scope="col" class="text-right">Accion</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($mailingEntriesPage as $subscriber): ?>
                                        <?php
                                        $prefs = $subscriber['prefs'] ?? admin_mailing_default_prefs();
                                        $hasPosts = !empty($prefs['posts']);
                                        $hasItineraries = !empty($prefs['itineraries']);
                                        $hasPodcast = !empty($prefs['podcast']);
                                        $hasNewsletter = !empty($prefs['newsletter']);
                                        $labels = [];
                                        if ($hasPosts) {
                                            $labels[] = 'Entradas';
                                        }
                                        if ($hasItineraries) {
                                            $labels[] = 'Itinerarios';
                                        }
                                        if ($hasPodcast) {
                                            $labels[] = 'Podcast';
                                        }
                                        if ($hasNewsletter) {
                                            $labels[] = 'Newsletter';
                                        }
                                        if (count($labels) === 4) {
                                            $label = 'Todo';
                                        } elseif (!empty($labels)) {
                                            $label = implode(' + ', $labels);
                                        } else {
                                            $label = '—';
                                        }
                                        ?>
                                        <tr>
                                            <td><?= htmlspecialchars((string) ($subscriber['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="text-right">
                                                <form method="post" class="mb-0" onsubmit="return confirm('¿Eliminar este correo de la lista?');">
                                                    <input type="hidden" name="remove_subscriber" value="1">
                                                    <input type="hidden" name="subscriber_email" value="<?= htmlspecialchars((string) ($subscriber['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($subsTotalPages > 1): ?>
                            <nav class="mt-3" aria-label="Paginacion de suscriptores">
                                <ul class="pagination pagination-sm mb-0">
                                    <li class="page-item <?= $subsPage <= 1 ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?page=lista-correo&amp;subs_page=<?= $subsPage - 1 ?><?= $subsQueryParam ?>#suscriptores">Anterior</a>
                                    </li>
                                    <?php for ($pageIndex = 1; $pageIndex <= $subsTotalPages; $pageIndex++): ?>
                                        <li class="page-item <?= $pageIndex === $subsPage ? 'active' : '' ?>">
                                            <a class="page-link" href="?page=lista-correo&amp;subs_page=<?= $pageIndex ?><?= $subsQueryParam ?>#suscriptores"><?= $pageIndex ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?= $subsPage >= $subsTotalPages ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?page=lista-correo&amp;subs_page=<?= $subsPage + 1 ?><?= $subsQueryParam ?>#suscriptores">Siguiente</a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card mb-4" id="preferencias">
                <div class="card-body">
                    <h5 class="mb-3">Preferencias de envío</h5>
                    <form method="post">
                        <div class="form-check mb-2">
                            <input type="checkbox" class="form-check-input" name="mailing_auto_posts" id="mailing_auto_posts" value="1" <?= $mailingAutoPosts ? 'checked' : '' ?>>
                            <label class="form-check-label" for="mailing_auto_posts">Enviar aviso de cada nueva entrada publicada</label>
                        </div>
                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" name="mailing_auto_itineraries" id="mailing_auto_itineraries" value="1" <?= $mailingAutoItineraries ? 'checked' : '' ?>>
                            <label class="form-check-label" for="mailing_auto_itineraries">Enviar aviso de cada nuevo itinerario publicado</label>
                        </div>
                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" name="mailing_auto_podcast" id="mailing_auto_podcast" value="1" <?= $mailingAutoPodcast ? 'checked' : '' ?>>
                            <label class="form-check-label" for="mailing_auto_podcast">Enviar aviso de cada nuevo episodio de podcast</label>
                        </div>
                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" name="mailing_auto_newsletter" id="mailing_auto_newsletter" value="1" <?= $mailingAutoNewsletter ? 'checked' : '' ?>>
                            <label class="form-check-label" for="mailing_auto_newsletter">Activar newsletter</label>
                        </div>
                        <div class="form-group">
                            <label for="mailing_format">Enviar aviso en formato</label>
                            <select name="mailing_format" id="mailing_format" class="form-control">
                                <option value="html" <?= $mailingFormat === 'html' ? 'selected' : '' ?>>HTML</option>
                                <option value="text" <?= $mailingFormat === 'text' ? 'selected' : '' ?>>Texto</option>
                            </select>
                        </div>
                        <button type="submit" name="save_mailing_flags" class="btn btn-outline-primary">Guardar preferencias</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <div class="modal fade" id="gmailOAuthHelpModal" tabindex="-1" role="dialog" aria-labelledby="gmailOAuthHelpModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="gmailOAuthHelpModalLabel">Guía rápida: Client ID y Secret (Gmail)</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <ol class="mb-3">
                            <li>En <strong>Google Cloud Console</strong>, crea o selecciona un proyecto.</li>
                            <li>En <strong>APIs y servicios &gt; Biblioteca</strong>, habilita <strong>Gmail API</strong>.</li>
                            <li>En <strong>APIs y servicios &gt; Pantalla de consentimiento OAuth</strong>, configura la app (tipo interno/público).</li>
                            <li>En <strong>Credenciales</strong>, crea un <strong>ID de cliente de OAuth</strong> tipo <strong>Aplicación web</strong>.</li>
                            <li>Añade como <strong>URI de redirección autorizada</strong>: <code>https://tu-dominio/admin.php?page=lista-correo&amp;gmail_callback=1</code></li>
                            <li>Copia el <strong>Client ID</strong> y el <strong>Client Secret</strong> y pégalos aquí.</li>
                        </ol>
                        <p class="mb-0 text-muted">Después conecta con Google desde esta misma pestaña para autorizar el envío.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>

    </div>

<?php endif; ?>
