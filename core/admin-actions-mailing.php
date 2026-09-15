<?php
/**
 * Nammu — panel de administración. Acciones POST: Lista de correo: configuración, suscriptores y envío manual de avisos.
 *
 * Se incluye desde admin.php dentro del ámbito global, una vez validado el token CSRF,
 * cuando la petición trae alguna de las claves de formulario de este grupo.
 */

if (isset($_POST['save_mailing'])) {
        $mailingGmail = trim($_POST['mailing_gmail'] ?? '');
        $mailingClientId = trim($_POST['mailing_client_id'] ?? '');
        $mailingClientSecret = trim($_POST['mailing_client_secret'] ?? '');
        try {
            $config = load_config_file();
            if ($mailingGmail !== '') {
                $config['mailing'] = [
                    'provider' => 'gmail',
                    'gmail_address' => $mailingGmail,
                    'client_id' => $mailingClientId,
                    'client_secret' => $mailingClientSecret,
                    'smtp_host' => 'smtp.gmail.com',
                    'smtp_port' => 465,
                    'auth_method' => 'oauth2',
                    'security' => 'ssl',
                    'status' => 'pending',
                ];
            } else {
                unset($config['mailing']);
                admin_delete_mailing_tokens();
            }
            save_config_file($config);
        } catch (Throwable $e) {
            $error = "Error guardando la configuración de correo: " . $e->getMessage();
        }
        header('Location: admin.php?page=lista-correo#mailing');
        exit;
} elseif (isset($_POST['add_subscriber'])) {
        $rawEmails = trim((string) ($_POST['subscriber_email'] ?? ''));
        $redirect = 'admin.php?page=lista-correo#suscriptores';
        if ($rawEmails === '') {
            $_SESSION['mailing_feedback'] = [
                'type' => 'danger',
                'message' => 'Introduce al menos un correo válido para suscribir.',
            ];
            header('Location: ' . $redirect);
            exit;
        }
        $tokens = preg_split('/[\r\n,]+/', $rawEmails) ?: [];
        $emails = [];
        $invalidCount = 0;
        foreach ($tokens as $token) {
            $email = admin_normalize_email($token);
            if ($email === '') {
                continue;
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $invalidCount++;
                continue;
            }
            $emails[$email] = true;
        }
        if (empty($emails)) {
            $_SESSION['mailing_feedback'] = [
                'type' => 'danger',
                'message' => 'No se detectaron direcciones de correo válidas.',
            ];
            header('Location: ' . $redirect);
            exit;
        }
        try {
            $subscribers = admin_load_mailing_subscriber_entries();
            $suppressed = admin_mailing_suppressed_map();
            $existing = [];
            foreach ($subscribers as $subscriber) {
                $existingEmail = admin_normalize_email((string) ($subscriber['email'] ?? ''));
                if ($existingEmail !== '') {
                    $existing[$existingEmail] = true;
                }
            }
            $addedCount = 0;
            $existsCount = 0;
            $suppressedCount = 0;
            foreach (array_keys($emails) as $email) {
                if (isset($suppressed[$email])) {
                    $suppressedCount++;
                    continue;
                }
                if (isset($existing[$email])) {
                    $existsCount++;
                    continue;
                }
                $subscribers[] = [
                    'email' => $email,
                    'prefs' => admin_mailing_default_prefs(),
                ];
                $existing[$email] = true;
                $addedCount++;
            }
            if ($addedCount > 0) {
                admin_save_mailing_subscriber_entries($subscribers);
            }
            if ($addedCount > 0) {
                $parts = [];
                $parts[] = $addedCount === 1 ? '1 suscriptor añadido.' : $addedCount . ' suscriptores añadidos.';
                if ($existsCount > 0) {
                    $parts[] = $existsCount === 1 ? '1 ya existía.' : $existsCount . ' ya existían.';
                }
                if ($suppressedCount > 0) {
                    $parts[] = $suppressedCount === 1 ? '1 dirección estaba suprimida por rebote duro y no se añadió.' : $suppressedCount . ' direcciones estaban suprimidas por rebote duro y no se añadieron.';
                }
                if ($invalidCount > 0) {
                    $parts[] = $invalidCount === 1 ? '1 correo inválido ignorado.' : $invalidCount . ' correos inválidos ignorados.';
                }
                $_SESSION['mailing_feedback'] = [
                    'type' => 'success',
                    'message' => implode(' ', $parts),
                ];
            } else {
                $parts = ['Todas las direcciones ya estaban en la lista.'];
                if ($suppressedCount > 0) {
                    $parts[] = $suppressedCount === 1 ? '1 dirección estaba suprimida por rebote duro.' : $suppressedCount . ' direcciones estaban suprimidas por rebote duro.';
                }
                if ($invalidCount > 0) {
                    $parts[] = $invalidCount === 1 ? '1 correo inválido ignorado.' : $invalidCount . ' correos inválidos ignorados.';
                }
                $_SESSION['mailing_feedback'] = [
                    'type' => 'info',
                    'message' => implode(' ', $parts),
                ];
            }
        } catch (Throwable $e) {
            $_SESSION['mailing_feedback'] = [
                'type' => 'danger',
                'message' => 'No se pudo guardar la lista: ' . $e->getMessage(),
            ];
        }
        header('Location: ' . $redirect);
        exit;
} elseif (isset($_POST['remove_subscriber'])) {
        $email = admin_normalize_email($_POST['subscriber_email'] ?? '');
        $redirect = 'admin.php?page=lista-correo#suscriptores';
        if ($email === '') {
            $_SESSION['mailing_feedback'] = [
                'type' => 'warning',
                'message' => 'No se recibió el correo a eliminar.',
            ];
            header('Location: ' . $redirect);
            exit;
        }
        try {
            $subscribers = admin_load_mailing_subscriber_entries();
            $filtered = array_values(array_filter($subscribers, static function ($item) use ($email) {
                return admin_normalize_email((string) ($item['email'] ?? '')) !== $email;
            }));
            admin_save_mailing_subscriber_entries($filtered);
            $_SESSION['mailing_feedback'] = [
                'type' => 'success',
                'message' => 'Suscriptor eliminado.',
            ];
        } catch (Throwable $e) {
            $_SESSION['mailing_feedback'] = [
                'type' => 'danger',
                'message' => 'No se pudo actualizar la lista: ' . $e->getMessage(),
            ];
        }
        header('Location: ' . $redirect);
        exit;
} elseif (isset($_POST['save_mailing_flags'])) {
        $autoPosts = isset($_POST['mailing_auto_posts']) ? 'on' : 'off';
        $autoItineraries = isset($_POST['mailing_auto_itineraries']) ? 'on' : 'off';
        $autoPodcast = isset($_POST['mailing_auto_podcast']) ? 'on' : 'off';
        $autoNewsletter = isset($_POST['mailing_auto_newsletter']) ? 'on' : 'off';
        $format = $_POST['mailing_format'] ?? 'html';
        $format = $format === 'text' ? 'text' : 'html';
        try {
            $config = load_config_file();
            if (!isset($config['mailing'])) {
                $config['mailing'] = [];
            }
            $config['mailing']['auto_posts'] = $autoPosts;
            $config['mailing']['auto_itineraries'] = $autoItineraries;
            $config['mailing']['auto_podcast'] = $autoPodcast;
            $config['mailing']['auto_newsletter'] = $autoNewsletter;
            $config['mailing']['format'] = $format;
            save_config_file($config);
            $_SESSION['mailing_feedback'] = [
                'type' => 'success',
                'message' => 'Preferencias de lista guardadas.',
            ];
        } catch (Throwable $e) {
            $_SESSION['mailing_feedback'] = [
                'type' => 'danger',
                'message' => 'No se pudieron guardar las preferencias: ' . $e->getMessage(),
            ];
        }
        header('Location: admin.php?page=lista-correo');
        exit;
} elseif (isset($_POST['send_mailing_post'])) {
        $filename = nammu_normalize_filename($_POST['mailing_filename'] ?? '');
        $template = $_POST['mailing_template'] ?? 'single';
        $redirect = 'admin.php?page=edit&template=' . urlencode($template);
        $settings = get_settings();
        if (!admin_is_mailing_ready($settings)) {
            $_SESSION['mailing_feedback'] = [
                'type' => 'danger',
                'message' => 'Configura Gmail y conecta con Google antes de enviar a la lista.',
            ];
            header('Location: ' . $redirect);
            exit;
        }
        $recipientType = admin_mailing_type_for_template($template);
        $subscribers = admin_mailing_recipients_for_type($recipientType, $settings);
        if (empty($subscribers)) {
            $_SESSION['mailing_feedback'] = [
                'type' => 'warning',
                'message' => 'No hay suscriptores en la lista.',
            ];
            header('Location: ' . $redirect);
            exit;
        }
        if ($filename === '') {
            $_SESSION['mailing_feedback'] = [
                'type' => 'danger',
                'message' => 'No se encontró la publicación a enviar.',
            ];
            header('Location: ' . $redirect);
            exit;
        }
        $postData = get_post_content($filename);
        if ($postData === null) {
            $_SESSION['mailing_feedback'] = [
                'type' => 'danger',
                'message' => 'No se pudo cargar la publicación seleccionada.',
            ];
            header('Location: ' . $redirect);
            exit;
        }
        $metadata = $postData['metadata'] ?? [];
        $title = $metadata['Title'] ?? pathinfo($filename, PATHINFO_FILENAME);
        $description = $metadata['Description'] ?? '';
        $slug = pathinfo($filename, PATHINFO_FILENAME);
        $imagePath = $metadata['Image'] ?? ($metadata['image'] ?? '');
        if ($template === 'podcast') {
            $audioPath = (string) ($metadata['Audio'] ?? '');
            $link = admin_public_asset_url($audioPath);
            if ($link === '') {
                $_SESSION['mailing_feedback'] = [
                    'type' => 'danger',
                    'message' => 'No se encontró el mp3 del podcast para enviar.',
                ];
                header('Location: ' . $redirect);
                exit;
            }
        } else {
            $link = admin_public_post_url($slug);
        }
        $context = $template === 'podcast' ? 'podcast' : 'post';
        $queueResult = admin_schedule_mailing_broadcast($context, $subscribers, [
            'filename' => $filename,
            'slug' => $slug,
            'title' => $title,
            'description' => $description,
            'image' => $imagePath,
            'audio' => $template === 'podcast' ? $audioPath : '',
            'template' => $template,
        ]);
        if (($queueResult['queued_recipients'] ?? 0) === 0) {
            $_SESSION['mailing_feedback'] = [
                'type' => 'danger',
                'message' => 'No se pudo encolar el aviso para la lista.',
            ];
        } else {
            $_SESSION['mailing_feedback'] = [
                'type' => 'success',
                'message' => 'Aviso encolado en ' . ((int) ($queueResult['queued_batches'] ?? 0)) . ' tanda' . (((int) ($queueResult['queued_batches'] ?? 0)) === 1 ? '' : 's') . ' para ' . ((int) ($queueResult['queued_recipients'] ?? 0)) . ' destinatario' . (((int) ($queueResult['queued_recipients'] ?? 0)) === 1 ? '' : 's') . '.',
            ];
        }
        header('Location: ' . $redirect);
        exit;
}
