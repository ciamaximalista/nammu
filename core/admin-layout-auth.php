<?php
/**
 * Nammu — panel de administración. Formularios de registro inicial e inicio de sesión.
 */
?>
                <div class="auth-container">

                    <img src="nammu.png" alt="Nammu Logo" class="logo">

                    <?php if ($page === 'register'): ?>

                        <h2 class="text-center">Registrarse</h2>

                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>

                        <form method="post">

                            <div class="form-group">

                                <label for="username">Usuario</label>

                                <input type="text" name="username" id="username" class="form-control" required>

                            </div>

                            <div class="form-group">

                                <label for="password">Contraseña</label>

                                <input type="password" name="password" id="password" class="form-control" required>

                            </div>

                            <button type="submit" name="register" class="btn btn-primary btn-block">Registrarse</button>

                        </form>

                    <?php else: ?>

                        <h2 class="text-center">Iniciar sesión</h2>

                        <?php if ($error): ?>

                            <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>

                        <?php endif; ?>

                        <?php if (admin_pending_submission_pending()): ?>

                            <div class="alert alert-info">El texto que estabas escribiendo no se ha perdido: en cuanto inicies sesión se guardará como borrador y se abrirá en el editor.</div>

                        <?php endif; ?>

                        <form method="post">

                            <div class="form-group">

                                <label for="username">Usuario</label>

                                <input type="text" name="username" id="username" class="form-control" required>

                            </div>

                            <div class="form-group">

                                <label for="password">Contraseña</label>

                                <input type="password" name="password" id="password" class="form-control" required>

                            </div>

                            <button type="submit" name="login" class="btn btn-primary btn-block">Iniciar sesión</button>

                        </form>

                    <?php endif; ?>

                </div>
