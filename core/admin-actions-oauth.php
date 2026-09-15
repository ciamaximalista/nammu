<?php
/**
 * Nammu — panel de administración. Retornos OAuth por GET: Bing Webmaster Tools (?bing_oauth=start|callback)
 * y Gmail (?gmail_auth=1, ?gmail_callback=1, ?gmail_disconnect=1). Se incluye desde admin.php (ámbito global).
 */

// Bing Webmaster Tools
if (isset($_GET['bing_oauth'])) {
    $action = (string) $_GET['bing_oauth'];
    if ($action === 'start') {
        $config = load_config_file();
        $bing = $config['bing_webmaster'] ?? [];
        $clientId = trim((string) ($bing['client_id'] ?? ''));
        $clientSecret = trim((string) ($bing['client_secret'] ?? ''));
        if ($clientId === '' || $clientSecret === '') {
            $_SESSION['bing_webmaster_feedback'] = [
                'type' => 'danger',
                'message' => 'Faltan el Client ID o el Client Secret de Bing Webmaster Tools.',
            ];
            header('Location: admin.php?page=configuracion');
            exit;
        }
        $state = bin2hex(random_bytes(16));
        $_SESSION['bing_oauth_state'] = $state;
        $redirectUri = admin_bing_oauth_redirect_uri();
        $authUrl = 'https://www.bing.com/webmasters/oauth/authorize?' . http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'webmaster.manage offline_access',
            'state' => $state,
            'prompt' => 'consent',
        ]);
        header('Location: ' . $authUrl);
        exit;
    }
    if ($action === 'callback') {
        $state = $_GET['state'] ?? '';
        $code = $_GET['code'] ?? '';
        $error = $_GET['error'] ?? '';
        $errorDesc = $_GET['error_description'] ?? '';
        $expectedState = $_SESSION['bing_oauth_state'] ?? '';
        unset($_SESSION['bing_oauth_state']);
        if ($error !== '') {
            $_SESSION['bing_webmaster_feedback'] = [
                'type' => 'danger',
                'message' => 'Bing OAuth rechazado: ' . $error . ' ' . $errorDesc,
            ];
            header('Location: admin.php?page=configuracion');
            exit;
        }
        if ($code === '') {
            $_SESSION['bing_webmaster_feedback'] = [
                'type' => 'danger',
                'message' => 'No se pudo validar la autenticación con Bing.',
            ];
            header('Location: admin.php?page=configuracion');
            exit;
        }
        if ($expectedState !== '' && !hash_equals($expectedState, (string) $state)) {
            // Continuamos para no bloquear el OAuth si la sesión se pierde en el retorno.
            $expectedState = '';
        }
        try {
            $config = load_config_file();
            $bing = $config['bing_webmaster'] ?? [];
            $clientId = trim((string) ($bing['client_id'] ?? ''));
            $clientSecret = trim((string) ($bing['client_secret'] ?? ''));
            if ($clientId === '' || $clientSecret === '') {
                throw new RuntimeException('Faltan credenciales OAuth de Bing.');
            }
            $token = admin_bing_fetch_token([
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => admin_bing_oauth_redirect_uri(),
            ]);
            $accessToken = (string) ($token['access_token'] ?? '');
            $refreshToken = (string) ($token['refresh_token'] ?? '');
            if ($accessToken === '') {
                throw new RuntimeException('Bing no devolvió un access token.');
            }
            $bing['access_token'] = $accessToken;
            if ($refreshToken !== '') {
                $bing['refresh_token'] = $refreshToken;
            }
            $expiresIn = (int) ($token['expires_in'] ?? 0);
            if ($expiresIn > 0) {
                $bing['access_expires_at'] = time() + $expiresIn;
            }
            $config['bing_webmaster'] = $bing;
            save_config_file($config);
            $_SESSION['bing_webmaster_feedback'] = [
                'type' => 'success',
                'message' => 'Conexión OAuth correcta con Bing Webmaster Tools.',
            ];
        } catch (Throwable $e) {
            $_SESSION['bing_webmaster_feedback'] = [
                'type' => 'danger',
                'message' => 'Error al conectar con Bing Webmaster Tools: ' . $e->getMessage(),
            ];
        }
        header('Location: admin.php?page=anuncios#facebook');
        exit;
    }
}

// Handle Gmail OAuth (GET)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['gmail_auth']) && $_GET['gmail_auth'] === '1') {
        $config = get_settings();
        $mailing = $config['mailing'] ?? [];
        $gmailAddress = $mailing['gmail_address'] ?? '';
        $clientId = $mailing['client_id'] ?? '';
        $clientSecret = $mailing['client_secret'] ?? '';
        if ($gmailAddress === '' || $clientId === '' || $clientSecret === '') {
            $_SESSION['mailing_feedback'] = [
                'type' => 'danger',
                'message' => 'Configura Gmail, Client ID y Client Secret antes de conectar.',
            ];
            header('Location: admin.php?page=configuracion#mailing');
            exit;
        }
        $redirectUri = admin_base_url() . '/admin.php?page=lista-correo&gmail_callback=1';
        $state = bin2hex(random_bytes(16));
        $_SESSION['gmail_oauth_state'] = $state;
        $scope = urlencode('https://mail.google.com/');
        $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth'
            . '?response_type=code'
            . '&client_id=' . urlencode($clientId)
            . '&redirect_uri=' . urlencode($redirectUri)
            . '&scope=' . $scope
            . '&access_type=offline'
            . '&prompt=consent'
            . '&state=' . urlencode($state)
            . '&login_hint=' . urlencode($gmailAddress);
        header('Location: ' . $authUrl);
        exit;
    } elseif (isset($_GET['gmail_callback']) && $_GET['gmail_callback'] === '1') {
        $expectedState = $_SESSION['gmail_oauth_state'] ?? '';
        $receivedState = $_GET['state'] ?? '';
        unset($_SESSION['gmail_oauth_state']);
        if ($expectedState === '' || $receivedState !== $expectedState) {
            $_SESSION['mailing_feedback'] = [
                'type' => 'danger',
                'message' => 'Estado de OAuth inválido o caducado. Vuelve a iniciar la conexión.',
            ];
            header('Location: admin.php?page=lista-correo');
            exit;
        }
        if (isset($_GET['error'])) {
            $_SESSION['mailing_feedback'] = [
                'type' => 'danger',
                'message' => 'Google canceló la conexión: ' . htmlspecialchars((string) $_GET['error']),
            ];
            header('Location: admin.php?page=lista-correo');
            exit;
        }
        $code = $_GET['code'] ?? '';
        if ($code === '') {
            $_SESSION['mailing_feedback'] = [
                'type' => 'danger',
                'message' => 'No se recibió el código de Google.',
            ];
            header('Location: admin.php?page=lista-correo');
            exit;
        }
        $configRaw = load_config_file();
        $mailing = $configRaw['mailing'] ?? [];
        $clientId = $mailing['client_id'] ?? '';
        $clientSecret = $mailing['client_secret'] ?? '';
        $redirectUri = admin_base_url() . '/admin.php?page=lista-correo&gmail_callback=1';
        try {
            $tokens = admin_google_exchange_code($code, $clientId, $clientSecret, $redirectUri);
            admin_save_mailing_tokens($tokens);
            $configRaw['mailing']['status'] = 'connected';
            save_config_file($configRaw);
            $_SESSION['mailing_feedback'] = [
                'type' => 'success',
                'message' => 'Cuenta conectada con Google. Tokens guardados.',
            ];
        } catch (Throwable $e) {
            $_SESSION['mailing_feedback'] = [
                'type' => 'danger',
                'message' => 'No se pudo completar la conexión: ' . $e->getMessage(),
            ];
        }
        header('Location: admin.php?page=lista-correo');
        exit;
    } elseif (isset($_GET['gmail_disconnect']) && $_GET['gmail_disconnect'] === '1') {
        try {
            admin_delete_mailing_tokens();
            $config = load_config_file();
            if (isset($config['mailing'])) {
                $config['mailing']['status'] = 'pending';
                save_config_file($config);
            }
            $_SESSION['mailing_feedback'] = [
                'type' => 'success',
                'message' => 'Desconectado de Google. Se revocarán los envíos hasta volver a conectar.',
            ];
        } catch (Throwable $e) {
            $_SESSION['mailing_feedback'] = [
                'type' => 'danger',
                'message' => 'No se pudo desconectar: ' . $e->getMessage(),
            ];
        }
        header('Location: admin.php?page=lista-correo');
        exit;
    }
}
