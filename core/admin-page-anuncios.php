<?php if ($page === 'anuncios'): ?>
    <?php
    $settings = get_settings();
    $ads = $settings['ads'] ?? [];
    $adsEnabled = ($ads['enabled'] ?? 'off') === 'on';
    $adsScope = $ads['scope'] ?? 'home';
    $adsText = trim((string) ($ads['text'] ?? ''));
    $adsImage = trim((string) ($ads['image'] ?? ''));
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
                <h2 class="mb-1">Anuncios</h2>
                <p class="text-muted mb-0">Crea un anuncio flotante para portada o todo el sitio.</p>
            </div>
        </div>

        <?php if ($adsFeedback): ?>
            <div class="alert alert-<?= htmlspecialchars($adsFeedback['type'], ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($adsFeedback['message'], ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-body">
                <form method="post">
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
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>
