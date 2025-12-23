<?php if ($page === 'correo-postal'): ?>
    <?php
    $settings = get_settings();
    $postalEnabled = ($settings['postal']['enabled'] ?? 'off') === 'on';
    $postalEntries = postal_load_entries();
    ksort($postalEntries);
    ?>

    <div class="tab-pane active">
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
            <div>
                <h2 class="mb-1">Correo Postal</h2>
                <p class="text-muted mb-0">Gestiona suscriptores y exporta la libreta postal.</p>
            </div>
        </div>

        <?php if ($postalFeedback): ?>
            <div class="alert alert-<?= htmlspecialchars($postalFeedback['type'], ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($postalFeedback['message'], ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-body">
                <h3 class="h5 mb-3">Preferencias</h3>
                <form method="post">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="postal_enabled" id="postal_enabled" value="1" <?= $postalEnabled ? 'checked' : '' ?>>
                        <label class="form-check-label" for="postal_enabled">Activar lista de correo postal</label>
                    </div>
                    <button type="submit" name="save_postal_settings" class="btn btn-outline-primary">Guardar preferencias</button>
                </form>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
                    <h3 class="h5 mb-0">Libreta postal</h3>
                    <div class="btn-group">
                        <form method="post" class="d-inline">
                            <button type="submit" name="download_postal_csv" class="btn btn-outline-secondary">Descargar CSV</button>
                        </form>
                        <form method="post" class="d-inline">
                            <button type="submit" name="download_postal_pdf" class="btn btn-outline-secondary">Descargar PDF etiquetas</button>
                        </form>
                    </div>
                </div>

                <div class="card mb-3 border-0 shadow-sm">
                    <div class="card-body">
                        <h4 class="h6 mb-3">Anadir suscriptor postal</h4>
                        <form method="post" class="row">
                            <input type="hidden" name="postal_update" value="1">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="small">Email</label>
                                    <input type="email" name="postal_email" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="small">Contraseña (opcional)</label>
                                    <input type="password" name="postal_password" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="small">Nombre</label>
                                    <input type="text" name="postal_name" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="small">Dirección</label>
                                    <input type="text" name="postal_address" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="small">Población</label>
                                    <input type="text" name="postal_city" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="small">Código Postal</label>
                                    <input type="text" name="postal_postal_code" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="small">Provincia/Región</label>
                                    <input type="text" name="postal_region" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="small">País</label>
                                    <input type="text" name="postal_country" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-outline-primary">Guardar suscriptor</button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if (empty($postalEntries)): ?>
                    <p class="text-muted mb-0">Todavía no hay suscriptores postales.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Dirección</th>
                                    <th>Población</th>
                                    <th>CP</th>
                                    <th>Provincia/Región</th>
                                    <th>País</th>
                                    <th>Email</th>
                                    <th class="text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($postalEntries as $entry): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($entry['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($entry['address'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($entry['city'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($entry['postal_code'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($entry['region'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($entry['country'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($entry['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="text-right">
                                            <details class="mb-2">
                                                <summary class="btn btn-sm btn-outline-primary">Editar</summary>
                                                <form method="post" class="mt-2">
                                                    <input type="hidden" name="postal_email" value="<?= htmlspecialchars($entry['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                    <div class="form-group">
                                                        <label class="small">Nombre</label>
                                                        <input type="text" name="postal_name" class="form-control" value="<?= htmlspecialchars($entry['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="small">Dirección</label>
                                                        <input type="text" name="postal_address" class="form-control" value="<?= htmlspecialchars($entry['address'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="small">Población</label>
                                                        <input type="text" name="postal_city" class="form-control" value="<?= htmlspecialchars($entry['city'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="small">Código Postal</label>
                                                        <input type="text" name="postal_postal_code" class="form-control" value="<?= htmlspecialchars($entry['postal_code'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="small">Provincia/Región</label>
                                                        <input type="text" name="postal_region" class="form-control" value="<?= htmlspecialchars($entry['region'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="small">País</label>
                                                        <input type="text" name="postal_country" class="form-control" value="<?= htmlspecialchars($entry['country'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="small">Nueva contraseña (opcional)</label>
                                                        <input type="password" name="postal_password" class="form-control">
                                                    </div>
                                                    <button type="submit" name="postal_update" class="btn btn-sm btn-primary">Guardar cambios</button>
                                                </form>
                                            </details>
                                            <form method="post" onsubmit="return confirm('¿Eliminar esta dirección?');">
                                                <input type="hidden" name="postal_email" value="<?= htmlspecialchars($entry['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                <button type="submit" name="postal_delete" class="btn btn-sm btn-outline-danger">Borrar</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>
