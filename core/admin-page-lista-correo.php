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
        $mailingAutoNewsletter = ($mailingSettings['auto_newsletter'] ?? 'off') === 'on';
        $mailingFormat = $mailingSettings['format'] ?? 'html';
        $mailingEntries = admin_load_mailing_subscriber_entries();
        $mailingCount = count($mailingEntries);
        $mailingTokens = admin_load_mailing_tokens();
        $isConnected = !empty($mailingTokens['refresh_token']);
        $canConnect = $mailingGmail !== '' && $mailingClientId !== '' && $mailingClientSecret !== '';
        $subsPerPage = 25;
        $subsPage = (int) ($_GET['subs_page'] ?? 1);
        if ($subsPage < 1) {
            $subsPage = 1;
        }
        $subsTotalPages = $mailingCount > 0 ? (int) ceil($mailingCount / $subsPerPage) : 1;
        if ($subsPage > $subsTotalPages) {
            $subsPage = $subsTotalPages;
        }
        $subsOffset = ($subsPage - 1) * $subsPerPage;
        $mailingEntriesPage = array_slice($mailingEntries, $subsOffset, $subsPerPage);
        ?>

        <p class="text-muted">Configura y consulta aquí la futura lista de correo. Usaremos tu cuenta de Gmail (SMTP con OAuth2 sobre SSL/TLS, puerto 465) para enviar mensajes cuando el módulo esté activo.</p>

        <?php if ($mailingFeedback !== null): ?>
            <div class="alert alert-<?= htmlspecialchars($mailingFeedback['type'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($mailingFeedback['message'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php if ($mailingGmail === ''): ?>
            <div class="alert alert-warning">
                Aún no has configurado una dirección de Gmail. Ve a <a href="?page=configuracion#mailing" class="alert-link">Configuración &gt; Correo de la lista</a> y guárdala para continuar.
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
                            <label class="sr-only" for="subscriber_email">Correo</label>
                            <input type="email" class="form-control form-control-sm mr-2 mb-2" name="subscriber_email" id="subscriber_email" placeholder="correo@ejemplo.com" required>
                            <button type="submit" class="btn btn-sm btn-primary mb-2">Añadir</button>
                        </form>
                    </div>
                    <?php if (empty($mailingEntries)): ?>
                        <p class="text-muted mb-0">Aún no hay suscriptores. Añade el primero usando el formulario.</p>
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
                                        $hasAvisos = !empty($prefs['posts']) || !empty($prefs['itineraries']);
                                        $hasNewsletter = !empty($prefs['newsletter']);
                                        if ($hasAvisos && $hasNewsletter) {
                                            $label = 'Todo';
                                        } elseif ($hasNewsletter) {
                                            $label = 'Newsletter';
                                        } else {
                                            $label = 'Avisos';
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
                                        <a class="page-link" href="?page=lista-correo&amp;subs_page=<?= $subsPage - 1 ?>#suscriptores">Anterior</a>
                                    </li>
                                    <?php for ($pageIndex = 1; $pageIndex <= $subsTotalPages; $pageIndex++): ?>
                                        <li class="page-item <?= $pageIndex === $subsPage ? 'active' : '' ?>">
                                            <a class="page-link" href="?page=lista-correo&amp;subs_page=<?= $pageIndex ?>#suscriptores"><?= $pageIndex ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?= $subsPage >= $subsTotalPages ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?page=lista-correo&amp;subs_page=<?= $subsPage + 1 ?>#suscriptores">Siguiente</a>
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

        <a class="btn btn-outline-primary" href="?page=configuracion#mailing">Editar correo de la lista</a>

    </div>

<?php endif; ?>
