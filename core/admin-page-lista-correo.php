<?php if ($page === 'lista-correo'): ?>

    <div class="tab-pane active">

        <h2>Lista de Correo</h2>

        <?php
        $mailingSettings = $settings['mailing'] ?? [];
        $mailingGmail = $mailingSettings['gmail_address'] ?? '';
        $mailingStatus = $mailingSettings['status'] ?? 'disconnected';
        $mailingSubscribers = admin_load_mailing_subscribers();
        $mailingCount = count($mailingSubscribers);
        $mailingTokens = admin_load_mailing_tokens();
        $isConnected = !empty($mailingTokens['refresh_token']);
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
                        <a class="btn btn-sm btn-primary <?= $mailingGmail === '' ? 'disabled' : '' ?>" href="admin.php?page=lista-correo&amp;gmail_auth=1">Conectar con Google</a>
                        <a class="btn btn-sm btn-outline-danger <?= !$isConnected ? 'disabled' : '' ?>" href="admin.php?page=lista-correo&amp;gmail_disconnect=1" onclick="return confirm('¿Desconectar y borrar tokens?');">Desconectar</a>
                        <a class="btn btn-sm btn-outline-secondary" href="?page=configuracion#mailing">Editar configuración</a>
                    </div>
                    <?php if ($isConnected): ?>
                        <div class="alert alert-success mt-3 mb-0">
                            Tokens guardados. Usa esta cuenta para enviar cuando activemos el envío de campañas.
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning mt-3 mb-0">
                            Autoriza con Google para obtener el refresh token y habilitar envíos.
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
                    <?php if (empty($mailingSubscribers)): ?>
                        <p class="text-muted mb-0">Aún no hay suscriptores. Añade el primero usando el formulario.</p>
                    <?php else: ?>
                        <ul class="list-group">
                            <?php foreach ($mailingSubscribers as $subscriber): ?>
                                <li class="list-group-item d-flex align-items-center justify-content-between">
                                    <span><?= htmlspecialchars($subscriber, ENT_QUOTES, 'UTF-8') ?></span>
                                    <form method="post" class="mb-0" onsubmit="return confirm('¿Eliminar este correo de la lista?');">
                                        <input type="hidden" name="remove_subscriber" value="1">
                                        <input type="hidden" name="subscriber_email" value="<?= htmlspecialchars($subscriber, ENT_QUOTES, 'UTF-8') ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <h5>Próximos pasos</h5>
        <ul>
            <li>Autenticar con Google la cuenta guardada para obtener permisos de envío SMTP.</li>
            <li>Configurar la captación y gestión de usuarios de la lista.</li>
            <li>Habilitar creación de campañas y envíos desde el panel.</li>
        </ul>

        <a class="btn btn-outline-primary" href="?page=configuracion#mailing">Editar correo de la lista</a>

    </div>

<?php endif; ?>
