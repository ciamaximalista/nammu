<?php
/**
 * Nammu — panel de administración. Acciones POST: Correo postal: configuración, libreta de direcciones, importación y exportación.
 *
 * Se incluye desde admin.php dentro del ámbito global, una vez validado el token CSRF,
 * cuando la petición trae alguna de las claves de formulario de este grupo.
 */

if (isset($_POST['save_postal_settings'])) {
        $enabled = isset($_POST['postal_enabled']) ? 'on' : 'off';
        try {
            $config = load_config_file();
            if (!isset($config['postal'])) {
                $config['postal'] = [];
            }
            $config['postal']['enabled'] = $enabled;
            save_config_file($config);
            $_SESSION['postal_feedback'] = [
                'type' => 'success',
                'message' => 'Preferencias de correo postal guardadas.',
            ];
        } catch (Throwable $e) {
            $_SESSION['postal_feedback'] = [
                'type' => 'danger',
                'message' => 'No se pudieron guardar las preferencias: ' . $e->getMessage(),
            ];
        }
        header('Location: admin.php?page=correo-postal');
        exit;
} elseif (isset($_POST['postal_update'])) {
        $entries = postal_load_entries();
        $email = postal_normalize_email((string) ($_POST['postal_email'] ?? ''));
        $entryId = trim((string) ($_POST['postal_id'] ?? ''));
        $passwordRaw = trim((string) ($_POST['postal_password'] ?? ''));
        $passwordHash = $passwordRaw !== '' ? password_hash($passwordRaw, PASSWORD_DEFAULT) : null;
        try {
            $entries = postal_upsert_entry([
                'email' => $email,
                'id' => $entryId,
                'name' => $_POST['postal_name'] ?? '',
                'address' => $_POST['postal_address'] ?? '',
                'city' => $_POST['postal_city'] ?? '',
                'postal_code' => $_POST['postal_postal_code'] ?? '',
                'region' => $_POST['postal_region'] ?? '',
                'country' => $_POST['postal_country'] ?? '',
            ], $passwordHash, $entries);
            postal_save_entries($entries);
            if ($email !== '') {
                admin_maybe_add_to_mailing_list($email);
            }
            $_SESSION['postal_feedback'] = [
                'type' => 'success',
                'message' => 'Dirección postal actualizada.',
            ];
        } catch (Throwable $e) {
            $_SESSION['postal_feedback'] = [
                'type' => 'danger',
                'message' => 'No se pudo guardar: ' . $e->getMessage(),
            ];
        }
        header('Location: admin.php?page=correo-postal');
        exit;
} elseif (isset($_POST['postal_delete'])) {
        $entries = postal_load_entries();
        $email = postal_normalize_email((string) ($_POST['postal_email'] ?? ''));
        $entryId = trim((string) ($_POST['postal_id'] ?? ''));
        $deleteKey = $email !== '' ? $email : $entryId;
        if ($deleteKey === '') {
            $_SESSION['postal_feedback'] = [
                'type' => 'danger',
                'message' => 'Falta el identificador para borrar.',
            ];
            header('Location: admin.php?page=correo-postal');
            exit;
        }
        $entries = postal_delete_entry($deleteKey, $entries);
        try {
            postal_save_entries($entries);
            $_SESSION['postal_feedback'] = [
                'type' => 'success',
                'message' => 'Dirección eliminada.',
            ];
        } catch (Throwable $e) {
            $_SESSION['postal_feedback'] = [
                'type' => 'danger',
                'message' => 'No se pudo eliminar: ' . $e->getMessage(),
            ];
        }
        header('Location: admin.php?page=correo-postal');
        exit;
} elseif (isset($_POST['download_postal_csv'])) {
        $entries = postal_load_entries();
        $csv = postal_csv_export($entries);
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="correos-postales.csv"');
        echo $csv;
        exit;
} elseif (isset($_POST['download_postal_pdf'])) {
        $entries = postal_load_entries();
        $theme = nammu_template_settings();
        $fontName = $theme['fonts']['body'] ?? 'Helvetica';
        $pdf = postal_build_labels_pdf($entries, $fontName);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="correos-postales.pdf"');
        echo $pdf;
        exit;
} elseif (isset($_POST['import_postal_csv'])) {
        $file = $_FILES['postal_csv'] ?? null;
        if (!$file || !is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $_SESSION['postal_feedback'] = [
                'type' => 'danger',
                'message' => 'No se pudo leer el archivo CSV.',
            ];
            header('Location: admin.php?page=correo-postal');
            exit;
        }
        if (!is_uploaded_file($file['tmp_name'] ?? '')) {
            $_SESSION['postal_feedback'] = [
                'type' => 'danger',
                'message' => 'Archivo CSV invalido.',
            ];
            header('Location: admin.php?page=correo-postal');
            exit;
        }
        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            $_SESSION['postal_feedback'] = [
                'type' => 'danger',
                'message' => 'No se pudo abrir el archivo CSV.',
            ];
            header('Location: admin.php?page=correo-postal');
            exit;
        }
        $firstLine = fgets($handle);
        rewind($handle);
        $delimiter = ',';
        if (is_string($firstLine) && substr_count($firstLine, ';') > substr_count($firstLine, ',')) {
            $delimiter = ';';
        }
        $headers = fgetcsv($handle, 0, $delimiter);
        if ($headers === false) {
            fclose($handle);
            $_SESSION['postal_feedback'] = [
                'type' => 'danger',
                'message' => 'El CSV esta vacio.',
            ];
            header('Location: admin.php?page=correo-postal');
            exit;
        }
        $map = admin_postal_csv_column_map($headers);
        $defaultOrder = ['email', 'name', 'address', 'city', 'postal_code', 'region', 'country'];
        $hasHeader = count($map) >= 2 && isset($map['email']);
        if (!$hasHeader) {
            $map = array_flip($defaultOrder);
            rewind($handle);
        }
        $entries = postal_load_entries();
        $imported = 0;
        $skipped = 0;
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $emailIndex = $map['email'] ?? null;
            $data = [
                'email' => '',
                'id' => '',
                'name' => isset($map['name'], $row[$map['name']]) ? $row[$map['name']] : '',
                'address' => isset($map['address'], $row[$map['address']]) ? $row[$map['address']] : '',
                'city' => isset($map['city'], $row[$map['city']]) ? $row[$map['city']] : '',
                'postal_code' => isset($map['postal_code'], $row[$map['postal_code']]) ? $row[$map['postal_code']] : '',
                'region' => isset($map['region'], $row[$map['region']]) ? $row[$map['region']] : '',
                'country' => isset($map['country'], $row[$map['country']]) ? $row[$map['country']] : '',
            ];
            $email = ($emailIndex !== null && isset($row[$emailIndex])) ? postal_normalize_email((string) $row[$emailIndex]) : '';
            $data['email'] = $email;
            if ($email === '') {
                $data['id'] = 'id-' . bin2hex(random_bytes(6));
            }
            try {
                $entries = postal_upsert_entry($data, null, $entries);
                if ($email !== '') {
                    admin_maybe_add_to_mailing_list($email);
                }
                $imported++;
            } catch (Throwable $e) {
                $skipped++;
            }
        }
        fclose($handle);
        try {
            postal_save_entries($entries);
            $_SESSION['postal_feedback'] = [
                'type' => 'success',
                'message' => 'Importacion completada. Nuevos: ' . $imported . '. Omitidos: ' . $skipped . '.',
            ];
        } catch (Throwable $e) {
            $_SESSION['postal_feedback'] = [
                'type' => 'danger',
                'message' => 'No se pudo guardar la libreta: ' . $e->getMessage(),
            ];
        }
        header('Location: admin.php?page=correo-postal');
        exit;
}
