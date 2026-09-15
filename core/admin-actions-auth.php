<?php
/**
 * Nammu — panel de administración. Acciones POST: Registro inicial, inicio/cierre de sesión y cambio de credenciales.
 *
 * Se incluye desde admin.php dentro del ámbito global, una vez validado el token CSRF,
 * cuando la petición trae alguna de las claves de formulario de este grupo.
 */

if (isset($_POST['register'])) {
        if (!$user_exists) {
            try {
                register_user($_POST['username'], $_POST['password']);
                header('Location: admin.php');
                exit;
            } catch (Throwable $e) {
                $error = 'No se pudo crear el usuario inicial. Comprueba los permisos de la carpeta config/ y vuelve a intentarlo. Detalle: ' . $e->getMessage();
            }
        } else {
            $error = 'Ya existe un usuario registrado.';
        }
} elseif (isset($_POST['login'])) {
        if (verify_user($_POST['username'], $_POST['password'])) {
            $_SESSION['loggedin'] = true;
            header('Location: admin.php?page=dashboard');
            exit;
        } else {
            $error = 'Usuario o contraseña incorrectos.';
        }
} elseif (isset($_POST['logout'])) {
        session_destroy();
        header('Location: admin.php');
        exit;
} elseif (isset($_POST['update_account'])) {
        $currentPassword = $_POST['current_password'] ?? '';
        $newUsername = trim($_POST['new_username'] ?? '');
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        $userData = get_user_data();
        $feedback = null;
        if (!$userData) {
            $feedback = ['type' => 'danger', 'message' => 'No existe un usuario configurado.'];
        } elseif ($currentPassword === '' || !password_verify($currentPassword, $userData['password'])) {
            $feedback = ['type' => 'danger', 'message' => 'La contraseña actual no es correcta.'];
        } elseif ($newUsername === '') {
            $feedback = ['type' => 'danger', 'message' => 'El nombre de usuario no puede estar vacío.'];
        } elseif ($newPassword !== '' && $newPassword !== $confirmPassword) {
            $feedback = ['type' => 'danger', 'message' => 'Las nuevas contraseñas no coinciden.'];
        } else {
            $passwordHash = $userData['password'];
            if ($newPassword !== '') {
                $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            }
            try {
                write_user_file($newUsername, $passwordHash);
                $feedback = ['type' => 'success', 'message' => 'Los datos de acceso se actualizaron correctamente.'];
            } catch (Throwable $e) {
                $feedback = ['type' => 'danger', 'message' => 'No se pudo actualizar la cuenta. ' . $e->getMessage()];
            }
        }

        $_SESSION['account_feedback'] = $feedback;
        header('Location: admin.php?page=configuracion');
        exit;
}
