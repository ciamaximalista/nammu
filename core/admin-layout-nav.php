<?php
/**
 * Nammu — panel de administración. Barra de navegación del panel y avisos globales ($error, $mailingFeedback).
 */
?>
                    <nav class="navbar navbar-expand-lg navbar-light bg-light mb-4">

                        <a class="navbar-brand" href="?page=dashboard"><img src="nammu.png" alt="Nammu Logo" style="max-width: 100px;"></a>
                        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#adminNavbar" aria-controls="adminNavbar" aria-expanded="false" aria-label="Mostrar navegación">
                            <span class="navbar-toggler-icon"></span>
                        </button>

                        <div class="collapse navbar-collapse" id="adminNavbar">

                            <ul class="navbar-nav mr-auto">

                                <li class="nav-item <?= $page === 'dashboard' ? 'active' : '' ?>">
                                    <a class="nav-link" href="?page=dashboard" title="Escritorio Nammu" aria-label="Escritorio Nammu">
                                        <svg width="44" height="44" viewBox="0 0 24 24" fill="none" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M4 4h7v7H4V4zm9 0h7v4h-7V4zm0 6h7v10h-7V10zm-9 3h7v7H4v-7z" fill="currentColor"/>
                                        </svg>
                                    </a>
                                </li>

                                <li class="nav-item <?= $page === 'publish' ? 'active' : '' ?>">
                                    <a class="nav-link" href="?page=publish" title="Publicar" aria-label="Publicar">
                                        <svg width="44" height="44" viewBox="0 0 24 24" fill="none" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M14 4l6 6-9.5 9.5H4v-6.5L13.5 4H14z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                            <path d="M12.5 5.5l6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                            <path d="M8.5 15.5l5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                            <path d="M4 20h6.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                        </svg>
                                    </a>
                                </li>

                                <li class="nav-item <?= in_array($page, ['edit', 'edit-post', 'edit-note', 'edit-news'], true) ? 'active' : '' ?>">
                                    <a class="nav-link" href="?page=edit" title="Editar" aria-label="Editar">
                                        <svg width="44" height="44" viewBox="0 0 24 24" fill="none" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M4 20h4l10-10-4-4L4 16v4z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                            <path d="M13 6l4 4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                        </svg>
                                    </a>
                                </li>

                                <li class="nav-item <?= $page === 'resources' ? 'active' : '' ?>">
                                    <a class="nav-link" href="?page=resources" title="Recursos" aria-label="Recursos">
                                        <svg width="44" height="44" viewBox="0 0 24 24" fill="none" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
                                            <rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="2"/>
                                            <circle cx="9" cy="10" r="2" fill="currentColor"/>
                                            <path d="M5 17l4-4 3 3 3-3 4 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </a>
                                </li>

                                <li class="nav-item <?= $page === 'template' ? 'active' : '' ?>">
                                    <a class="nav-link" href="?page=template" title="Plantilla" aria-label="Plantilla">
                                        <svg width="44" height="44" viewBox="0 0 24 24" fill="none" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M4 4h16v16H4V4z" stroke="currentColor" stroke-width="2"/>
                                            <path d="M4 9h16M9 4v16" stroke="currentColor" stroke-width="2"/>
                                        </svg>
                                    </a>
                                </li>

                                <li class="nav-item <?= ($page === 'itinerarios' || $page === 'itinerario' || $page === 'itinerario-tema') ? 'active' : '' ?>">
                                    <a class="nav-link" href="?page=itinerarios" title="Itinerarios" aria-label="Itinerarios">
                                        <svg width="44" height="44" viewBox="0 0 24 24" fill="none" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M4 5H10C11.1046 5 12 5.89543 12 7V19H4C2.89543 19 2 18.1046 2 17V7C2 5.89543 2.89543 5 4 5Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                            <path d="M20 5H14C12.8954 5 12 5.89543 12 7V19H20C21.1046 19 22 18.1046 22 17V7C22 5.89543 21.1046 5 20 5Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                            <line x1="12" y1="7" x2="12" y2="19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                        </svg>
                                    </a>
                                </li>

                                <li class="nav-item <?= $page === 'lista-correo' ? 'active' : '' ?>">
                                    <a class="nav-link" href="?page=lista-correo" title="Lista" aria-label="Lista">
                                        <svg width="44" height="44" viewBox="0 0 24 24" fill="none" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M4 6h16v12H4V6z" stroke="currentColor" stroke-width="2"/>
                                            <path d="M4 7l8 6 8-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </a>
                                </li>

                                <li class="nav-item <?= $page === 'correo-postal' ? 'active' : '' ?>">
                                    <a class="nav-link" href="?page=correo-postal" title="Correo Postal" aria-label="Correo Postal">
                                        <svg width="44" height="44" viewBox="-55 -55 407 407" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
                                            <path fill="currentColor" d="M149.999,162.915v120.952c0,7.253,5.74,13.133,12.993,13.133c7.253,0,12.993-5.88,12.993-13.133V162.915h100.813c7.253,0,13.128-6.401,13.128-13.654V74.254c0-19.599-7.78-38.348-21.912-52.364C253.934,7.926,235.386,0,215.783,0H80.675C40.091,0,7.074,33.626,7.074,74.026v75.236c0,7.253,5.88,13.654,13.133,13.654H149.999z M33.06,135.929V74.026c0-25.918,21.376-47.003,47.476-47.003c26.1,0,47.474,21.188,47.474,47.231v61.675H33.06z M263.94,135.929H154.997V74.254c0-18.05-7.285-35.274-18.135-48.267h78.922c25.955,0,48.156,22.51,48.156,48.267V135.929z"/>
                                            <path fill="currentColor" d="M80.036,58.311c-7.253,0-12.993,5.88-12.993,13.133v1.052c0,7.253,5.74,13.133,12.993,13.133c7.253,0,12.993-5.88,12.993-13.133v-1.052C93.029,64.19,87.289,58.311,80.036,58.311z"/>
                                        </svg>
                                    </a>
                                </li>

                                <li class="nav-item <?= $page === 'anuncios' ? 'active' : '' ?>">
                                    <a class="nav-link" href="?page=anuncios" title="Difusión" aria-label="Difusión">
                                        <svg width="44" height="44" viewBox="0 0 24 24" fill="none" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M4 10v4l8 2V6l-8 2z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                            <path d="M12 6l8-2v16l-8-2" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                            <path d="M6 14l2 6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                        </svg>
                                    </a>
                                </li>

                                <li class="nav-item <?= $page === 'fediverso' ? 'active' : '' ?>">
                                    <a class="nav-link" href="?page=fediverso" title="Fediverso" aria-label="Fediverso">
                                        <?= function_exists('nammu_fediverse_glyph_svg') ? nammu_fediverse_glyph_svg(48) : nammu_fediverse_symbol_svg(44) ?>
                                    </a>
                                </li>

                                <li class="nav-item <?= $page === 'configuracion' ? 'active' : '' ?>">
                                    <a class="nav-link" href="?page=configuracion" title="Configuración" aria-label="Configuración">
                                        <svg width="44" height="44" viewBox="0 0 24 24" fill="none" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M12 8a4 4 0 100 8 4 4 0 000-8z" stroke="currentColor" stroke-width="2"/>
                                            <path d="M3 12h3M18 12h3M12 3v3M12 18v3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M5.6 18.4l2.1-2.1M16.3 7.7l2.1-2.1" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                        </svg>
                                    </a>
                                </li>

                            </ul>

                            <form method="post" class="form-inline my-2 my-lg-0 ml-lg-3">
                                <div class="d-flex flex-column align-items-stretch">
                                    <button type="button" class="btn btn-outline-secondary btn-sm mb-2" id="adminThemeToggle" aria-pressed="false">Modo oscuro</button>
                                    <button type="submit" name="logout" class="btn btn-outline-danger my-2 my-sm-0">Cerrar</button>
                                    <a href="index.php" class="btn btn-outline-secondary btn-sm mt-2">Portada</a>
                                </div>
                            </form>

                        </div>

                    </nav>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger mb-3"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                    <?php if (!empty($mailingFeedback)): ?>
                        <div class="alert alert-<?= htmlspecialchars($mailingFeedback['type'], ENT_QUOTES, 'UTF-8') ?> mb-3"><?= htmlspecialchars($mailingFeedback['message'], ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
