<?php
/**
 * Nammu — panel de administración. Fediverso: pestaña Configuración (identidad ActivityPub e inspector de objetos remotos).
 * Lo incluye core/admin-page-fediverso.php en el ámbito global de admin.php; comparte variables con las demás piezas.
 */
?>
<div class="card mb-4">
    <div class="card-body">
        <h3 class="h5 mb-3">Actor del blog</h3>
        <div class="row">
            <div class="col-lg-6 mb-3">
                <label class="font-weight-bold d-block mb-1">Cuenta ActivityPub</label>
                <code><?= htmlspecialchars($fediverseLocalHandle, ENT_QUOTES, 'UTF-8') ?></code>
            </div>
            <div class="col-lg-6 mb-3">
                <label class="font-weight-bold d-block mb-1">Actor URL</label>
                <a href="<?= htmlspecialchars($fediverseActorUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><?= htmlspecialchars($fediverseActorUrl, ENT_QUOTES, 'UTF-8') ?></a>
            </div>
            <div class="col-lg-6 mb-3">
                <label class="font-weight-bold d-block mb-1">WebFinger</label>
                <code><?= htmlspecialchars($fediverseBaseUrl . '/.well-known/webfinger?resource=' . rawurlencode($fediverseAcct), ENT_QUOTES, 'UTF-8') ?></code>
            </div>
            <div class="col-lg-6 mb-3">
                <label class="font-weight-bold d-block mb-1">Outbox</label>
                <a href="<?= htmlspecialchars(nammu_fediverse_outbox_url($fediverseConfig), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><?= htmlspecialchars(nammu_fediverse_outbox_url($fediverseConfig), ENT_QUOTES, 'UTF-8') ?></a>
            </div>
            <div class="col-lg-6 mb-0">
                <label class="font-weight-bold d-block mb-1">Seguidores federados</label>
                <strong><?= (int) count($fediverseFollowers) ?></strong>
            </div>
        </div>
    </div>
</div>
<div class="card">
    <div class="card-body">
        <h3 class="h5 mb-3">Inspector ActivityPub</h3>
        <form method="post" class="mb-3">
            <input type="hidden" name="fediverse_tab" value="settings">
            <label for="fediverse_inspect_url">URL del objeto</label>
            <div class="input-group">
                <input type="url" class="form-control" id="fediverse_inspect_url" name="fediverse_inspect_url" value="<?= htmlspecialchars((string) ($fediverseInspectUrl ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="https://ejemplo.org/ap/objects/post-slug">
                <div class="input-group-append">
                    <button type="submit" name="inspect_fediverse_object" class="btn btn-outline-secondary">Inspeccionar</button>
                </div>
            </div>
        </form>
        <?php if (is_array($fediverseInspectResult ?? null) && !empty($fediverseInspectResult['ok'])): ?>
            <div class="mb-3">
                <h4 class="h6">Objeto</h4>
                <pre class="fediverse-debug"><?= htmlspecialchars(json_encode($fediverseInspectResult['object'] ?? null, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?></pre>
            </div>
            <div class="mb-3">
                <h4 class="h6">Replies</h4>
                <pre class="fediverse-debug"><?= htmlspecialchars(json_encode(($fediverseInspectResult['object']['replies'] ?? null), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?></pre>
            </div>
            <div class="mb-3">
                <h4 class="h6">Colección</h4>
                <pre class="fediverse-debug"><?= htmlspecialchars(json_encode($fediverseInspectResult['replies'] ?? null, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?></pre>
            </div>
            <div>
                <h4 class="h6">Primera página</h4>
                <pre class="fediverse-debug"><?= htmlspecialchars(json_encode($fediverseInspectResult['replies_page'] ?? null, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?></pre>
            </div>
        <?php endif; ?>
    </div>
</div>
