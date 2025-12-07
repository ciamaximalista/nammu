<?php if ($page === 'template'): ?>

                            <?php
                            $defaults = get_default_template_settings();
                            $templateSettings = $settings['template'] ?? $defaults;
                            $fontTitle = $templateSettings['fonts']['title'] ?? '';
                            $fontBody = $templateSettings['fonts']['body'] ?? '';
                            $fontCode = $templateSettings['fonts']['code'] ?? '';
                            $fontQuote = $templateSettings['fonts']['quote'] ?? '';
                            $entryTemplateToc = $templateSettings['entry']['toc'] ?? ($defaults['entry']['toc'] ?? ['auto' => 'off', 'min_headings' => 3]);
                            $entryTocAutoEnabled = ($entryTemplateToc['auto'] ?? 'off') === 'on';
                            $entryTocMinHeadings = (int) ($entryTemplateToc['min_headings'] ?? 3);
                            if (!in_array($entryTocMinHeadings, [2, 3, 4], true)) {
                                $entryTocMinHeadings = 3;
                            }
                            $templateColors = $templateSettings['colors'] ?? [];
                            $templateImages = $templateSettings['images'] ?? [];
                            $logoImage = $templateImages['logo'] ?? '';
                            $footerMd = $templateSettings['footer'] ?? '';
                            $footerLogoPosition = $templateSettings['footer_logo'] ?? $defaults['footer_logo'];
                            if (!in_array($footerLogoPosition, ['none', 'top', 'bottom'], true)) {
                                $footerLogoPosition = $defaults['footer_logo'];
                            }
                            $templateHome = $templateSettings['home'] ?? [];
                            $colorLabels = [
                                'h1' => 'Color H1',
                                'h2' => 'Color H2',
                                'h3' => 'Color H3',
                                'intro' => 'Color de Entradilla',
                                'text' => 'Color de Texto',
                                'background' => 'Color de Fondo',
                                'highlight' => 'Color de Cajas Destacadas',
                                'accent' => 'Color Destacado',
                                'brand' => 'Color de Cabecera',
                                'code_background' => 'Color de fondo de código',
                                'code_text' => 'Color del código',
                            ];
                            $templateGlobal = $templateSettings['global'] ?? $defaults['global'];
                            $homeColumns = (int)($templateHome['columns'] ?? $defaults['home']['columns']);
                            if ($homeColumns < 1 || $homeColumns > 3) {
                                $homeColumns = $defaults['home']['columns'];
                            }
                            $homeFirstRowEnabled = (($templateHome['first_row_enabled'] ?? $defaults['home']['first_row_enabled'] ?? 'off') === 'on');
                            $homeFirstRowColumns = (int)($templateHome['first_row_columns'] ?? $defaults['home']['first_row_columns'] ?? $homeColumns);
                            if ($homeFirstRowColumns < 1 || $homeFirstRowColumns > 3) {
                                $homeFirstRowColumns = $homeColumns;
                            }
                            $homeFirstRowFill = (($templateHome['first_row_fill'] ?? $defaults['home']['first_row_fill'] ?? 'off') === 'on');
                            $homeFirstRowAlign = $templateHome['first_row_align'] ?? ($defaults['home']['first_row_align'] ?? 'left');
                            if (!in_array($homeFirstRowAlign, ['left', 'center'], true)) {
                                $homeFirstRowAlign = 'left';
                            }
                            $homePerPageValue = $templateHome['per_page'] ?? $defaults['home']['per_page'];
                            $homePerPageNumeric = is_numeric($homePerPageValue) ? (int) $homePerPageValue : '';
                            $homePerPageAll = !is_numeric($homePerPageValue) || $homePerPageValue === 'all';
                            $homeBlocksMode = $templateHome['blocks'] ?? $defaults['home']['blocks'];
                            if (!in_array($homeBlocksMode, ['boxed', 'flat'], true)) {
                                $homeBlocksMode = $defaults['home']['blocks'];
                            }
                            $cardStylesAllowed = ['full', 'square-right', 'square-tall-right', 'circle-right'];
                            $homeCardStyle = $templateHome['card_style'] ?? $defaults['home']['card_style'];
                            if (!in_array($homeCardStyle, $cardStylesAllowed, true)) {
                                $homeCardStyle = $defaults['home']['card_style'];
                            }
                            $homeFullImageMode = $templateHome['full_image_mode'] ?? $defaults['home']['full_image_mode'];
                            if (!in_array($homeFullImageMode, ['natural', 'crop'], true)) {
                                $homeFullImageMode = $defaults['home']['full_image_mode'];
                            }
                            $homeHeaderConfig = array_merge($defaults['home']['header'], $templateHome['header'] ?? []);
                            $homeHeaderTypes = ['none', 'graphic', 'text', 'mixed'];
                            $homeHeaderType = in_array($homeHeaderConfig['type'], $homeHeaderTypes, true) ? $homeHeaderConfig['type'] : $defaults['home']['header']['type'];
                            $homeHeaderImage = $homeHeaderConfig['image'] ?? '';
                            $homeHeaderModes = ['contain', 'cover'];
                            $homeHeaderMode = in_array($homeHeaderConfig['mode'], $homeHeaderModes, true) ? $homeHeaderConfig['mode'] : $defaults['home']['header']['mode'];
                            $textHeaderStyles = ['boxed', 'plain'];
                            $homeHeaderTextStyle = $homeHeaderConfig['text_style'] ?? $defaults['home']['header']['text_style'];
                            if (!in_array($homeHeaderTextStyle, $textHeaderStyles, true)) {
                                $homeHeaderTextStyle = $defaults['home']['header']['text_style'];
                            }
                            $homeHeaderOrder = $homeHeaderConfig['order'] ?? $defaults['home']['header']['order'];
                            if (!in_array($homeHeaderOrder, ['image-text', 'text-image'], true)) {
                                $homeHeaderOrder = $defaults['home']['header']['order'];
                            }
                            if (in_array($homeHeaderType, ['graphic', 'mixed'], true) && trim((string) $homeHeaderImage) === '') {
                                $homeHeaderType = $homeHeaderType === 'mixed' ? 'text' : 'none';
                                $homeHeaderMode = $defaults['home']['header']['mode'];
                            }
                            $allowedCorners = ['rounded', 'square'];
                            $globalCornerStyle = $templateGlobal['corners'] ?? $defaults['global']['corners'];
                            if (!in_array($globalCornerStyle, $allowedCorners, true)) {
                                $globalCornerStyle = $defaults['global']['corners'];
                            }
                            $searchDefaults = $defaults['search'] ?? ['mode' => 'single', 'position' => 'footer', 'floating' => 'off'];
                            $templateSearch = array_merge($searchDefaults, $templateSettings['search'] ?? []);
                            $searchModesAllowed = ['none', 'home', 'single', 'both'];
                            $searchPositionsAllowed = ['title', 'footer'];
                            $searchMode = in_array($templateSearch['mode'], $searchModesAllowed, true) ? $templateSearch['mode'] : $searchDefaults['mode'];
                            $searchPosition = in_array($templateSearch['position'], $searchPositionsAllowed, true) ? $templateSearch['position'] : $searchDefaults['position'];
                            $searchFloatingValue = $templateSearch['floating'] ?? $searchDefaults['floating'];
                            if (!in_array($searchFloatingValue, ['off', 'on'], true)) {
                                $searchFloatingValue = $searchDefaults['floating'];
                            }
                            $searchFloating = $searchFloatingValue;
                            ?>

                            <div class="tab-pane active">

                                <h2>Plantilla</h2>

                                <h3 class="mt-4">Colores y fuentes</h3>

                                <?php if (isset($_GET['saved']) && $_GET['saved'] === '1'): ?>
                                    <div class="alert alert-success">Configuración de plantilla guardada correctamente.</div>
                                <?php elseif (isset($_GET['error']) && $_GET['error'] === '1'): ?>
                                    <div class="alert alert-danger">No se pudieron guardar los cambios. Revisa los permisos del archivo de configuración.</div>
                                <?php endif; ?>

                                <form method="post" id="template-settings" data-google-fonts-key="<?= htmlspecialchars($settings['google_fonts_api'] ?? '') ?>">
                                    <h4 class="mt-3">Fuentes</h4>
                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                            <label for="title_font">Fuente de Título</label>
                                            <select name="title_font" id="title_font" class="form-control" data-current-font="<?= htmlspecialchars($fontTitle) ?>">
                                                <option value="">Selecciona una fuente</option>
                                                <?php if ($fontTitle): ?>
                                                    <option value="<?= htmlspecialchars($fontTitle) ?>" selected><?= htmlspecialchars($fontTitle) ?> (actual)</option>
                                                <?php endif; ?>
                                            </select>
                                            <small class="form-text text-muted">Las opciones se cargan desde Google Fonts usando tu API Key.</small>
                                        </div>
                                        <div class="form-group col-md-6">
                                            <label for="body_font">Fuente de Texto</label>
                                            <select name="body_font" id="body_font" class="form-control" data-current-font="<?= htmlspecialchars($fontBody) ?>">
                                                <option value="">Selecciona una fuente</option>
                                                <?php if ($fontBody): ?>
                                                    <option value="<?= htmlspecialchars($fontBody) ?>" selected><?= htmlspecialchars($fontBody) ?> (actual)</option>
                                                <?php endif; ?>
                                            </select>
                                            <small class="form-text text-muted">Recuerda incluir todos los pesos necesarios desde Google Fonts.</small>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                            <label for="code_font">Fuente para código</label>
                                            <select name="code_font" id="code_font" class="form-control" data-current-font="<?= htmlspecialchars($fontCode) ?>">
                                                <option value="">Selecciona una fuente</option>
                                                <?php if ($fontCode): ?>
                                                    <option value="<?= htmlspecialchars($fontCode) ?>" selected><?= htmlspecialchars($fontCode) ?> (actual)</option>
                                                <?php endif; ?>
                                            </select>
                                            <small class="form-text text-muted">Se aplicará a bloques `code` y `pre`.</small>
                                        </div>
                                        <div class="form-group col-md-6">
                                            <label for="quote_font">Fuente para citas</label>
                                            <select name="quote_font" id="quote_font" class="form-control" data-current-font="<?= htmlspecialchars($fontQuote) ?>">
                                                <option value="">Selecciona una fuente</option>
                                                <?php if ($fontQuote): ?>
                                                    <option value="<?= htmlspecialchars($fontQuote) ?>" selected><?= htmlspecialchars($fontQuote) ?> (actual)</option>
                                                <?php endif; ?>
                                            </select>
                                            <small class="form-text text-muted">Se utilizará en los bloques de cita (&gt;).</small>
                                        </div>
                                    </div>
                                    <div id="fonts-alert"></div>

                                    <h4 class="mt-4">Colores</h4>
                                    <p class="text-muted">Selecciona un color desde la paleta o escribe manualmente un valor hexadecimal, RGB, HSL, etc.</p>

                                    <div class="form-row">
                                        <?php foreach ($colorLabels as $colorKey => $label):
                                            $storedValue = $templateColors[$colorKey] ?? $defaults['colors'][$colorKey];
                                            $pickerValue = color_picker_value($storedValue, $defaults['colors'][$colorKey]);
                                        ?>
                                        <div class="form-group col-md-6" data-color-field="<?= $colorKey ?>">
                                            <label><?= $label ?></label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text p-0">
                                                        <input type="color"
                                                            class="template-color-picker"
                                                            value="<?= htmlspecialchars($pickerValue) ?>"
                                                            aria-label="<?= $label ?> (selector)">
                                                    </span>
                                                </div>
                                                <input type="text"
                                                    class="form-control template-color-input"
                                                    name="color_<?= $colorKey ?>"
                                                    value="<?= htmlspecialchars($storedValue) ?>"
                                                    placeholder="#000000 o rgb(0,0,0)">
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <h4 class="mt-4">Imágenes de referencia</h4>
                                    <div class="form-group">
                                        <label for="logo_image">Logo del blog</label>
                                        <div class="input-group">
                                            <input type="text" name="logo_image" id="logo_image" class="form-control" value="<?= htmlspecialchars($logoImage, ENT_QUOTES, 'UTF-8') ?>" placeholder="assets/logo.png" readonly>
                                            <div class="input-group-append">
                                                <button type="button" class="btn btn-outline-secondary" data-toggle="modal" data-target="#imageModal" data-target-type="field" data-target-input="logo_image" data-target-prefix="assets/">Seleccionar imagen</button>
                                                <button type="button" class="btn btn-outline-danger" id="clear-logo-image">Quitar</button>
                                            </div>
                                        </div>
                                        <small class="form-text text-muted">Idealmente un PNG o SVG cuadrado. Se mostrará como un círculo flotante en las páginas internas y se usará como favicon cuando sea compatible.</small>
                                    </div>

                                    <h4 class="mt-4">Portada</h4>
                                    <p class="text-muted">Configura la rejilla de la portada y cuántas entradas se muestran por página.</p>
                                    <div class="form-group">
                                        <label>Columnas de la rejilla</label>
                                        <div class="home-layout-options">
                                            <?php foreach ([1, 2, 3] as $cols): ?>
                                                <?php $isActive = ($homeColumns === $cols); ?>
                                                <label class="home-layout-option <?= $isActive ? 'active' : '' ?>">
                                                    <input type="radio"
                                                        name="home_columns"
                                                        value="<?= $cols ?>"
                                                        <?= $isActive ? 'checked' : '' ?>>
                                                    <span class="layout-figure columns-<?= $cols ?>">
                                                        <?php for ($i = 0; $i < $cols; $i++): ?>
                                                            <span class="layout-cell"></span>
                                                        <?php endfor; ?>
                                                    </span>
                                                    <span class="layout-caption"><?= $cols ?> <?= $cols === 1 ? 'columna' : 'columnas' ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="home_first_row_enabled">¿La primera fila de entradas debe ser diferente?</label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="home_first_row_enabled" id="home_first_row_enabled" value="1" <?= $homeFirstRowEnabled ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="home_first_row_enabled">Sí, quiero personalizar la primera fila</label>
                                        </div>
                                    </div>

                                    <div class="form-group" data-first-row-options <?= $homeFirstRowEnabled ? '' : 'style="display:none;"' ?>>
                                        <label>Entradas en la primera fila</label>
                                        <div class="home-layout-options">
                                            <?php foreach ([1, 2, 3] as $cols): ?>
                                                <?php $isActive = ($homeFirstRowColumns === $cols); ?>
                                                <label class="home-layout-option <?= $isActive ? 'active' : '' ?>">
                                                    <input type="radio"
                                                        name="home_first_row_columns"
                                                        value="<?= $cols ?>"
                                                        <?= $isActive ? 'checked' : '' ?>>
                                                    <span class="layout-figure columns-<?= $cols ?>">
                                                        <?php for ($i = 0; $i < $cols; $i++): ?>
                                                            <span class="layout-cell"></span>
                                                        <?php endfor; ?>
                                                    </span>
                                                    <span class="layout-caption"><?= $cols ?> <?= $cols === 1 ? 'entrada' : 'entradas' ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                        <small class="form-text text-muted">Si lo desactivas, la portada usará el número de columnas general en todas las filas.</small>
                                    </div>

                                    <div class="form-group" data-first-row-fill <?= $homeFirstRowEnabled ? '' : 'style="display:none;"' ?>>
                                        <label>¿Cuadrar la última fila de la portada para que no queden huecos?</label>
                                        <div class="custom-control custom-radio">
                                            <input type="radio" id="home_first_row_fill_on" name="home_first_row_fill" value="on" class="custom-control-input" <?= $homeFirstRowFill ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="home_first_row_fill_on">Sí, completar la última fila</label>
                                        </div>
                                        <div class="custom-control custom-radio">
                                            <input type="radio" id="home_first_row_fill_off" name="home_first_row_fill" value="off" class="custom-control-input" <?= !$homeFirstRowFill ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="home_first_row_fill_off">No, mantener la cuadrícula tal cual</label>
                                        </div>
                                        <small class="form-text text-muted">Al activarlo, añadiremos entradas en la última fila para evitar huecos cuando la primera fila tenga un ancho distinto.</small>
                                    </div>

                                    <div class="form-group" data-first-row-align <?= ($homeFirstRowEnabled && $homeFirstRowColumns === 1) ? '' : 'style="display:none;"' ?>>
                                        <label>Alineación del titular y texto de la primera fila</label>
                                        <div class="custom-control custom-radio">
                                            <input type="radio" id="home_first_row_align_left" name="home_first_row_align" value="left" class="custom-control-input" <?= $homeFirstRowAlign === 'left' ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="home_first_row_align_left">Izquierda (por defecto)</label>
                                        </div>
                                        <div class="custom-control custom-radio">
                                            <input type="radio" id="home_first_row_align_center" name="home_first_row_align" value="center" class="custom-control-input" <?= $homeFirstRowAlign === 'center' ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="home_first_row_align_center">Centrado</label>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label>Estilo de las tarjetas</label>
                                        <div class="home-card-style-options">
                                            <?php
                                            $cardStyleOptions = [
                                                'full' => [
                                                    'label' => 'Imagen completa',
                                                    'caption' => 'Miniatura a todo el ancho, texto debajo',
                                                ],
                                                'square-right' => [
                                                    'label' => 'Cuadrada a la derecha',
                                                    'caption' => 'Miniatura cuadrada flotante',
                                                ],
                                                'square-tall-right' => [
                                                    'label' => 'Vertical a la derecha',
                                                    'caption' => 'Mismo ancho que la cuadrada, ocupa toda la altura',
                                                ],
                                                'circle-right' => [
                                                    'label' => 'Circular a la derecha',
                                                    'caption' => 'Miniatura circular flotante',
                                                ],
                                            ];
                                            ?>
                                            <?php foreach ($cardStyleOptions as $styleKey => $info): ?>
                                                <?php $styleActive = ($homeCardStyle === $styleKey); ?>
                                                <label class="home-card-style-option <?= $styleActive ? 'active' : '' ?>" data-card-style-option="1">
                                                    <input type="radio"
                                                        name="home_card_style"
                                                        value="<?= htmlspecialchars($styleKey, ENT_QUOTES, 'UTF-8') ?>"
                                                        <?= $styleActive ? 'checked' : '' ?>>
                                                    <span class="card-style-figure card-style-<?= htmlspecialchars($styleKey, ENT_QUOTES, 'UTF-8') ?>">
                                                        <span class="card-thumb"></span>
                                                        <span class="card-lines">
                                                            <span class="line primary"></span>
                                                            <span class="line meta"></span>
                                                            <span class="line body"></span>
                                                        </span>
                                                    </span>
                                                    <span class="card-style-text">
                                                        <strong><?= htmlspecialchars($info['label'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                        <small><?= htmlspecialchars($info['caption'], ENT_QUOTES, 'UTF-8') ?></small>
                                                    </span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <div class="mt-3" data-full-image-options <?= $homeCardStyle === 'full' ? '' : 'style="display:none;"' ?>>
                                        <label>Imagen completa</label>
                                        <p class="text-muted">Selecciona cómo se ajustan las miniaturas cuando ocupan todo el ancho de la tarjeta.</p>
                                        <div class="home-card-style-options">
                                            <?php
                                            $fullImageModes = [
                                                'natural' => [
                                                    'label' => 'Respetar proporciones',
                                                    'caption' => 'Cada imagen mantiene su altura natural',
                                                    'figure' => 'full-mode-natural',
                                                ],
                                                'crop' => [
                                                    'label' => 'Recortar para igualar',
                                                    'caption' => 'Recorta para igualar la altura de todas las miniaturas',
                                                    'figure' => 'full-mode-crop',
                                                ],
                                            ];
                                            ?>
                                            <?php foreach ($fullImageModes as $modeKey => $info): ?>
                                                <?php $modeActive = ($homeFullImageMode === $modeKey); ?>
                                                <label class="home-card-style-option <?= $modeActive ? 'active' : '' ?>" data-full-image-mode="1">
                                                    <input type="radio"
                                                        name="home_card_full_mode"
                                                        value="<?= htmlspecialchars($modeKey, ENT_QUOTES, 'UTF-8') ?>"
                                                        <?= $modeActive ? 'checked' : '' ?>>
                                                    <span class="full-image-mode-figure <?= htmlspecialchars($info['figure'], ENT_QUOTES, 'UTF-8') ?>">
                                                        <span class="mode-column">
                                                            <span class="mode-thumb primary"></span>
                                                            <span class="mode-line title"></span>
                                                            <span class="mode-line text"></span>
                                                        </span>
                                                        <span class="mode-column">
                                                            <span class="mode-thumb secondary"></span>
                                                            <span class="mode-line title"></span>
                                                            <span class="mode-line text"></span>
                                                        </span>
                                                    </span>
                                                    <span class="card-style-text">
                                                        <strong><?= htmlspecialchars($info['label'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                        <small><?= htmlspecialchars($info['caption'], ENT_QUOTES, 'UTF-8') ?></small>
                                                    </span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <h5 class="mt-4">Bloques de portada</h5>
                                    <p class="text-muted">Elige si las entradas se muestran dentro de una tarjeta o directamente sobre el fondo.</p>
                                    <div class="home-card-style-options">
                                        <?php
                                        $homeBlockOptions = [
                                            'boxed' => [
                                                'label' => 'Con cajas',
                                                'caption' => 'Cada entrada se muestra dentro de una tarjeta',
                                                'figure' => 'blocks-boxed',
                                            ],
                                            'flat' => [
                                                'label' => 'Sin cajas',
                                                'caption' => 'El contenido descansa sobre el fondo',
                                                'figure' => 'blocks-flat',
                                            ],
                                        ];
                                        ?>
                                        <?php foreach ($homeBlockOptions as $blocksKey => $info): ?>
                                            <?php $blocksActive = ($homeBlocksMode === $blocksKey); ?>
                                            <label class="home-card-style-option <?= $blocksActive ? 'active' : '' ?>" data-blocks-option="1">
                                                <input type="radio"
                                                    name="home_blocks_mode"
                                                    value="<?= htmlspecialchars($blocksKey, ENT_QUOTES, 'UTF-8') ?>"
                                                    <?= $blocksActive ? 'checked' : '' ?>>
                                                <span class="home-blocks-figure <?= htmlspecialchars($info['figure'], ENT_QUOTES, 'UTF-8') ?>">
                                                    <span class="block block-thumb"></span>
                                                    <span class="block block-line"></span>
                                                    <span class="block block-line short"></span>
                                                </span>
                                                <span class="card-style-text">
                                                    <strong><?= htmlspecialchars($info['label'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                    <small><?= htmlspecialchars($info['caption'], ENT_QUOTES, 'UTF-8') ?></small>
                                                </span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>

                                    <h5 class="mt-4">Esquinas de las cajas</h5>
                                    <p class="text-muted">Controla cómo se redondean las esquinas de tarjetas y bloques en todo el sitio.</p>
                                    <div class="home-card-style-options" data-corners-options>
                                        <?php
                                        $cornerOptions = [
                                            'rounded' => [
                                                'label' => 'Redondeadas',
                                                'caption' => 'Esquinas suaves y redondeadas',
                                                'figure' => 'corner-rounded',
                                            ],
                                            'square' => [
                                                'label' => 'Cuadradas',
                                                'caption' => 'Esquinas rectas en ángulo',
                                                'figure' => 'corner-square',
                                            ],
                                        ];
                                        ?>
                                        <?php foreach ($cornerOptions as $cornerKey => $info): ?>
                                            <?php $cornerActive = ($globalCornerStyle === $cornerKey); ?>
                                            <label class="home-card-style-option <?= $cornerActive ? 'active' : '' ?>" data-corners-option="1">
                                                <input type="radio"
                                                    name="global_corners"
                                                    value="<?= htmlspecialchars($cornerKey, ENT_QUOTES, 'UTF-8') ?>"
                                                    <?= $cornerActive ? 'checked' : '' ?>>
                                                <span class="home-corner-figure <?= htmlspecialchars($info['figure'], ENT_QUOTES, 'UTF-8') ?>">
                                                    <span></span>
                                                </span>
                                                <span class="card-style-text">
                                                    <strong><?= htmlspecialchars($info['label'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                    <small><?= htmlspecialchars($info['caption'], ENT_QUOTES, 'UTF-8') ?></small>
                                                </span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>

                                    <h5 class="mt-4">Cabecera</h5>
                                    <p class="text-muted">Selecciona cómo se mostrará la cabecera en la portada.</p>
                                    <div class="mt-3" data-header-text <?= in_array($homeHeaderType, ['text', 'mixed'], true) ? '' : 'style="display:none;"' ?>>
                                        <label>Estilo</label>
                                        <div class="home-card-style-options">
                                            <?php
                                            $textHeaderOptions = [
                                                'boxed' => [
                                                    'label' => 'Cabecera en caja',
                                                    'caption' => 'Con fondo destacado similar al post individual',
                                                    'figure' => 'text-header-boxed',
                                                ],
                                                'plain' => [
                                                    'label' => 'Sobre el fondo',
                                                    'caption' => 'Sin caja, directamente sobre el fondo de la portada',
                                                    'figure' => 'text-header-plain',
                                                ],
                                            ];
                                            ?>
                                            <?php foreach ($textHeaderOptions as $textKey => $info): ?>
                                                <?php $textActive = ($homeHeaderTextStyle === $textKey); ?>
                                                <label class="home-card-style-option <?= $textActive ? 'active' : '' ?>" data-header-text-option="1">
                                                    <input type="radio"
                                                        name="home_header_text_style"
                                                        value="<?= htmlspecialchars($textKey, ENT_QUOTES, 'UTF-8') ?>"
                                                        <?= $textActive ? 'checked' : '' ?>>
                                                    <span class="home-header-text-figure <?= htmlspecialchars($info['figure'], ENT_QUOTES, 'UTF-8') ?>">
                                                        <span class="text-line title"></span>
                                                        <span class="text-line subtitle"></span>
                                                        <span class="text-line tagline"></span>
                                                    </span>
                                                    <span class="card-style-text">
                                                        <strong><?= htmlspecialchars($info['label'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                        <small><?= htmlspecialchars($info['caption'], ENT_QUOTES, 'UTF-8') ?></small>
                                                    </span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <label class="d-block mt-4 mb-2">Estructura de la cabecera</label>

                                    <div class="home-header-options" data-header-options>
                                        <?php
                                        $headerTypeOptions = [
                                            'none' => [
                                                'label' => 'Sin cabecera',
                                                'caption' => 'No se mostrará cabecera en la portada',
                                                'figure' => 'header-none',
                                            ],
                                            'graphic' => [
                                                'label' => 'Cabecera gráfica',
                                                'caption' => 'Mostrar una imagen a modo de cabecera',
                                                'figure' => 'header-graphic',
                                            ],
                                            'text' => [
                                                'label' => 'Cabecera de texto',
                                                'caption' => 'Usar cabecera similar a la de los artículos',
                                                'figure' => 'header-text',
                                            ],
                                            'mixed' => [
                                                'label' => 'Imagen + texto',
                                                'caption' => 'Combina imagen con la cabecera textual',
                                                'figure' => 'header-mixed',
                                            ],
                                        ];
                                        ?>
                                        <?php foreach ($headerTypeOptions as $typeKey => $info): ?>
                                            <?php $typeActive = ($homeHeaderType === $typeKey); ?>
                                            <label class="home-header-option <?= $typeActive ? 'active' : '' ?>">
                                                <input type="radio"
                                                    name="home_header_type"
                                                    value="<?= htmlspecialchars($typeKey, ENT_QUOTES, 'UTF-8') ?>"
                                                    <?= $typeActive ? 'checked' : '' ?>>
                                                <span class="home-header-figure <?= htmlspecialchars($info['figure'], ENT_QUOTES, 'UTF-8') ?>">
                                                    <span></span>
                                                </span>
                                                <span class="home-header-text">
                                                    <strong><?= htmlspecialchars($info['label'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                    <small><?= htmlspecialchars($info['caption'], ENT_QUOTES, 'UTF-8') ?></small>
                                                </span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>

                                    <div class="mt-3" data-header-order <?= $homeHeaderType === 'mixed' ? '' : 'style="display:none;"' ?>>
                                        <label>Orden de la cabecera</label>
                                        <div class="home-card-style-options">
                                            <?php
                                            $headerOrderOptions = [
                                                'image-text' => [
                                                    'label' => 'Imagen arriba, texto abajo',
                                                    'caption' => 'La imagen precede al bloque textual',
                                                    'figure' => 'order-image-text',
                                                ],
                                                'text-image' => [
                                                    'label' => 'Texto arriba, imagen abajo',
                                                    'caption' => 'El bloque textual queda por encima de la imagen',
                                                    'figure' => 'order-text-image',
                                                ],
                                            ];
                                            ?>
                                            <?php foreach ($headerOrderOptions as $orderKey => $info): ?>
                                                <?php $orderActive = ($homeHeaderOrder === $orderKey); ?>
                                                <label class="home-card-style-option <?= $orderActive ? 'active' : '' ?>" data-header-order-option="1">
                                                    <input type="radio"
                                                        name="home_header_order"
                                                        value="<?= htmlspecialchars($orderKey, ENT_QUOTES, 'UTF-8') ?>"
                                                        <?= $orderActive ? 'checked' : '' ?>>
                                                    <span class="home-header-order-figure <?= htmlspecialchars($info['figure'], ENT_QUOTES, 'UTF-8') ?>">
                                                        <span class="order-block image"></span>
                                                        <span class="order-block text"></span>
                                                    </span>
                                                    <span class="card-style-text">
                                                        <strong><?= htmlspecialchars($info['label'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                        <small><?= htmlspecialchars($info['caption'], ENT_QUOTES, 'UTF-8') ?></small>
                                                    </span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <div class="mt-3" data-header-graphic <?= in_array($homeHeaderType, ['graphic', 'mixed'], true) ? '' : 'style="display:none;"' ?>>
                                        <div class="form-group">
                                            <label for="home_header_image">Imagen de cabecera</label>
                                            <div class="input-group">
                                                <input type="text" name="home_header_image" id="home_header_image" class="form-control" value="<?= htmlspecialchars($homeHeaderImage ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="assets/cabecera.jpg" readonly>
                                                <div class="input-group-append">
                                                    <button type="button" class="btn btn-outline-secondary" data-toggle="modal" data-target="#imageModal" data-target-type="field" data-target-input="home_header_image" data-target-prefix="assets/">Seleccionar imagen</button>
                                                    <button type="button" class="btn btn-outline-danger" id="clear-header-image">Quitar</button>
                                                </div>
                                            </div>
                                            <small class="form-text text-muted">Selecciona una imagen desde Recursos para la cabecera de la portada.</small>
                                        </div>

                                            <div class="form-group" data-header-graphic-mode <?= in_array($homeHeaderType, ['graphic', 'mixed'], true) ? '' : 'style="display:none;"' ?>>
                                                <label>Estilo de la imagen</label>
                                                <div class="home-card-style-options">
                                                    <?php
                                                    $graphicModes = [
                                                        'contain' => [
                                                            'label' => 'Imagen centrada',
                                                            'caption' => '160px de alto, respeta la proporción original',
                                                            'figure' => 'contain',
                                                        ],
                                                        'cover' => [
                                                            'label' => 'Imagen a ancho completo',
                                                            'caption' => 'Recorta a 160px ocupando todo el ancho',
                                                            'figure' => 'cover',
                                                        ],
                                                    ];
                                                    ?>
                                                    <?php foreach ($graphicModes as $modeKey => $modeInfo): ?>
                                                        <?php $modeActive = ($homeHeaderMode === $modeKey); ?>
                                                        <label class="home-card-style-option <?= $modeActive ? 'active' : '' ?>" data-header-mode-option="1">
                                                            <input type="radio"
                                                                name="home_header_graphic_mode"
                                                                value="<?= htmlspecialchars($modeKey, ENT_QUOTES, 'UTF-8') ?>"
                                                                <?= $modeActive ? 'checked' : '' ?>>
                                                        <span class="header-graphic-figure <?= htmlspecialchars($modeInfo['figure'], ENT_QUOTES, 'UTF-8') ?>">
                                                            <span class="graphic-preview"></span>
                                                        </span>
                                                        <span class="card-style-text">
                                                            <strong><?= htmlspecialchars($modeInfo['label'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                            <small><?= htmlspecialchars($modeInfo['caption'], ENT_QUOTES, 'UTF-8') ?></small>
                                                        </span>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <h5 class="mt-4">Bucle</h5>
                                    <div class="form-group">
                                        <label for="home_per_page">Número de posts por página</label>
                                        <div class="input-group">
                                            <input type="number"
                                                min="1"
                                                class="form-control"
                                                name="home_per_page"
                                                id="home_per_page"
                                                value="<?= htmlspecialchars($homePerPageNumeric !== '' ? (string) $homePerPageNumeric : '', ENT_QUOTES, 'UTF-8') ?>"
                                                <?= $homePerPageAll ? 'disabled' : '' ?>>
                                            <div class="input-group-append">
                                                <div class="input-group-text">
                                                    <input type="checkbox"
                                                        name="home_per_page_all"
                                                        id="home_per_page_all"
                                                        value="1"
                                                        <?= $homePerPageAll ? 'checked' : '' ?>>
                                                    <label for="home_per_page_all" class="mb-0 ml-2">Todos</label>
                                                </div>
                                            </div>
                                        </div>
                                        <small class="form-text text-muted">Define cuántas entradas se muestran antes de paginar. Marca “Todos” para mostrar todas las entradas sin paginación.</small>
                                    </div>

                                    <h4 class="mt-4">Buscador</h4>
                                    <p class="text-muted">Decide si quieres mostrar una caja de búsqueda en el sitio y dónde se colocará.</p>
                                    <div class="home-card-style-options" data-search-mode-options>
                                        <?php
                                        $searchModeOptions = [
                                            'none' => [
                                                'label' => 'Sin caja de búsqueda',
                                                'caption' => 'No se muestra ningún buscador en el sitio',
                                                'figure' => 'search-none',
                                            ],
                                            'home' => [
                                                'label' => 'Sólo en la portada',
                                                'caption' => 'Una caja en la página principal',
                                                'figure' => 'search-home',
                                            ],
                                            'single' => [
                                                'label' => 'Sólo en las entradas',
                                                'caption' => 'Aparece en cada artículo',
                                                'figure' => 'search-single',
                                            ],
                                            'both' => [
                                                'label' => 'Portada y entradas',
                                                'caption' => 'Visible en todos los listados y artículos',
                                                'figure' => 'search-both',
                                            ],
                                        ];
                                        ?>
                                        <?php foreach ($searchModeOptions as $modeKey => $info): ?>
                                            <?php $modeActive = ($searchMode === $modeKey); ?>
                                            <label class="home-card-style-option <?= $modeActive ? 'active' : '' ?>" data-search-mode-option="1">
                                                <input type="radio"
                                                    name="search_mode"
                                                    value="<?= htmlspecialchars($modeKey, ENT_QUOTES, 'UTF-8') ?>"
                                                    <?= $modeActive ? 'checked' : '' ?>>
                                                <span class="search-mode-figure <?= htmlspecialchars($info['figure'], ENT_QUOTES, 'UTF-8') ?>">
                                                    <span class="search-box">
                                                        <span class="icon"></span>
                                                        <span class="line"></span>
                                                    </span>
                                                </span>
                                                <span class="card-style-text">
                                                    <strong><?= htmlspecialchars($info['label'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                    <small><?= htmlspecialchars($info['caption'], ENT_QUOTES, 'UTF-8') ?></small>
                                                </span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="mt-3" data-search-position <?= $searchMode === 'none' ? 'style="display:none;"' : '' ?>>
                                        <label>Ubicación de la caja</label>
                                        <div class="home-card-style-options">
                                            <?php
                                            $searchPositionOptions = [
                                                'title' => [
                                                    'label' => 'Bajo el título',
                                                    'caption' => 'Caja alineada con el encabezado principal',
                                                    'figure' => 'search-pos-title',
                                                ],
                                                'footer' => [
                                                    'label' => 'Sobre el footer',
                                                    'caption' => 'Bloque destacado antes del pie de página',
                                                    'figure' => 'search-pos-footer',
                                                ],
                                            ];
                                            ?>
                                            <?php foreach ($searchPositionOptions as $posKey => $info): ?>
                                                <?php $posActive = ($searchPosition === $posKey); ?>
                                                <label class="home-card-style-option <?= $posActive ? 'active' : '' ?>" data-search-position-option="1">
                                                    <input type="radio"
                                                        name="search_position"
                                                        value="<?= htmlspecialchars($posKey, ENT_QUOTES, 'UTF-8') ?>"
                                                        <?= $posActive ? 'checked' : '' ?>>
                                                    <span class="search-position-figure <?= htmlspecialchars($info['figure'], ENT_QUOTES, 'UTF-8') ?>">
                                                        <span class="search-box">
                                                            <span class="icon"></span>
                                                            <span class="line"></span>
                                                        </span>
                                                    </span>
                                                    <span class="card-style-text">
                                                        <strong><?= htmlspecialchars($info['label'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                        <small><?= htmlspecialchars($info['caption'], ENT_QUOTES, 'UTF-8') ?></small>
                                                    </span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <div class="mt-3" data-search-floating>
                                        <label>Buscador flotante</label>
                                        <p class="text-muted mb-2">Muestra una caja compacta flotando bajo el logotipo en páginas internas.</p>
                                        <div class="home-card-style-options">
                                            <?php
                                            $searchFloatingOptions = [
                                                'off' => [
                                                    'label' => 'Desactivado',
                                                    'caption' => 'No se muestra buscador flotante',
                                                    'figure' => 'search-float-off',
                                                ],
                                                'on' => [
                                                    'label' => 'Flotando en el margen',
                                                    'caption' => 'Caja ligera junto al logotipo en vistas interiores',
                                                    'figure' => 'search-float-on',
                                                ],
                                            ];
                                            ?>
                                            <?php foreach ($searchFloatingOptions as $floatKey => $info): ?>
                                                <?php $floatActive = ($searchFloating === $floatKey); ?>
                                                <label class="home-card-style-option <?= $floatActive ? 'active' : '' ?>" data-search-floating-option="1">
                                                    <input type="radio"
                                                        name="search_floating"
                                                        value="<?= htmlspecialchars($floatKey, ENT_QUOTES, 'UTF-8') ?>"
                                                        <?= $floatActive ? 'checked' : '' ?>>
                                                    <span class="search-floating-figure <?= htmlspecialchars($info['figure'], ENT_QUOTES, 'UTF-8') ?>">
                                                        <span class="search-box">
                                                            <span class="icon"></span>
                                                        </span>
                                                        <span class="search-hint"></span>
                                                    </span>
                                                    <span class="card-style-text">
                                                        <strong><?= htmlspecialchars($info['label'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                        <small><?= htmlspecialchars($info['caption'], ENT_QUOTES, 'UTF-8') ?></small>
                                                    </span>
                                                </label>
                                            <?php endforeach; ?>
                                    </div>

                                    <h4 class="mt-4">Entrada</h4>
                                    <p class="text-muted">Configura si las entradas mostrarán un índice de contenidos automáticamente.</p>
                                    <div class="form-group" data-entry-toc-toggle>
                                        <label class="d-block">Índice de contenidos por defecto</label>
                                        <div class="btn-group btn-group-sm btn-group-toggle" role="group" data-toggle="buttons">
                                            <label class="btn btn-outline-primary <?= $entryTocAutoEnabled ? 'active' : '' ?>">
                                                <input type="radio"
                                                    name="entry_toc_auto"
                                                    value="on"
                                                    class="sr-only"
                                                    autocomplete="off"
                                                    <?= $entryTocAutoEnabled ? 'checked' : '' ?>>
                                                Sí
                                            </label>
                                            <label class="btn btn-outline-primary <?= !$entryTocAutoEnabled ? 'active' : '' ?>">
                                                <input type="radio"
                                                    name="entry_toc_auto"
                                                    value="off"
                                                    class="sr-only"
                                                    autocomplete="off"
                                                    <?= !$entryTocAutoEnabled ? 'checked' : '' ?>>
                                                No
                                            </label>
                                        </div>
                                    </div>
                                    <div class="form-group" data-entry-toc-options <?= $entryTocAutoEnabled ? '' : 'style="display:none;"' ?>>
                                        <label for="entry_toc_min">Mostrar a partir de</label>
                                        <select name="entry_toc_min" id="entry_toc_min" class="form-control" style="max-width: 240px;">
                                            <option value="2" <?= $entryTocMinHeadings === 2 ? 'selected' : '' ?>>2 encabezados</option>
                                            <option value="3" <?= $entryTocMinHeadings === 3 ? 'selected' : '' ?>>3 encabezados</option>
                                            <option value="4" <?= $entryTocMinHeadings === 4 ? 'selected' : '' ?>>4 o más encabezados</option>
                                        </select>
                                    </div>
                                    <small class="form-text text-muted mb-3">Si desactivas el índice automático, puedes insertarlo manualmente dentro de cada entrada usando las etiquetas <code>[toc]</code> o <code>[TOC]</code>.</small>

                                    </div>

                                    <h4 class="mt-4">Footer</h4>
                                    <p class="text-muted mb-2">Decide si quieres mostrar el logotipo del sitio en el pie de página.</p>
                                    <div class="home-card-style-options" data-footer-logo-options>
                                        <?php
                                        $footerLogoOptions = [
                                            'none' => [
                                                'label' => 'Sin logo',
                                                'caption' => 'Sólo se mostrará el contenido del footer',
                                                'figure' => 'logo-none',
                                            ],
                                            'top' => [
                                                'label' => 'Logo arriba',
                                                'caption' => 'El logotipo aparecerá centrado sobre el footer',
                                                'figure' => 'logo-top',
                                            ],
                                            'bottom' => [
                                                'label' => 'Logo abajo',
                                                'caption' => 'El logotipo aparecerá centrado bajo el footer',
                                                'figure' => 'logo-bottom',
                                            ],
                                        ];
                                        ?>
                                        <?php foreach ($footerLogoOptions as $logoKey => $info): ?>
                                            <?php $logoActive = ($footerLogoPosition === $logoKey); ?>
                                            <label class="home-card-style-option <?= $logoActive ? 'active' : '' ?>" data-footer-logo-option="1">
                                                <input type="radio"
                                                    name="footer_logo_position"
                                                    value="<?= htmlspecialchars($logoKey, ENT_QUOTES, 'UTF-8') ?>"
                                                    <?= $logoActive ? 'checked' : '' ?>>
                                                <span class="footer-logo-figure <?= htmlspecialchars($info['figure'], ENT_QUOTES, 'UTF-8') ?>">
                                                    <span class="footer-logo-area"></span>
                                                    <span class="footer-logo-dot"></span>
                                                </span>
                                                <span class="card-style-text">
                                                    <strong><?= htmlspecialchars($info['label'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                    <small><?= htmlspecialchars($info['caption'], ENT_QUOTES, 'UTF-8') ?></small>
                                                </span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                    <p class="text-muted mt-3">Este contenido se mostrará al final de cada página. Introduce HTML directamente (por ejemplo, &lt;strong&gt;...&lt;/strong&gt; o enlaces con &lt;a&gt; ).</p>
                                    <div class="form-group">
                                        <label for="footer_md">Contenido del footer (HTML)</label>
                                        <textarea name="footer_md" id="footer_md" rows="6" class="form-control" placeholder="Bloque de contacto, enlaces legales..."><?= htmlspecialchars($footerMd ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                                    </div>

                                    <button type="submit" name="save_template" class="btn btn-primary">Guardar plantilla</button>
                                </form>

                            </div>

<?php endif; ?>
