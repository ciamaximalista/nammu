<?php
/**
 * Nammu — panel de administración. Acciones POST: Biblioteca de recursos: subida, edición de imágenes, etiquetas y borrado.
 *
 * Se incluye desde admin.php dentro del ámbito global, una vez validado el token CSRF,
 * cuando la petición trae alguna de las claves de formulario de este grupo.
 */

if (isset($_POST['upload_asset'])) {
        $filesField = $_FILES['asset_files'] ?? ($_FILES['asset_file'] ?? null);
        $redirectTarget = 'admin.php?page=resources';
        $redirectUrlRaw = trim((string) ($_POST['redirect_url'] ?? ''));
        $redirectPageRaw = trim((string) ($_POST['redirect_page'] ?? ''));
        $redirectAnchorRaw = trim((string) ($_POST['redirect_anchor'] ?? ''));
        $redirectAnchor = '';
        if ($redirectAnchorRaw !== '' && preg_match('/^[A-Za-z0-9_-]+$/', $redirectAnchorRaw)) {
            $redirectAnchor = '#' . $redirectAnchorRaw;
        }
        $pattern = '/^page=[a-z0-9._%\-\/&=]+$/i';
        if ($redirectUrlRaw !== '' && preg_match($pattern, $redirectUrlRaw)) {
            $redirectTarget = 'admin.php?' . $redirectUrlRaw;
        } else {
            $allowedPages = ['resources','publish','edit','edit-post','template','itinerarios','itinerario','configuracion','correo-postal','anuncios'];
            if (in_array($redirectPageRaw, $allowedPages, true)) {
                $redirectTarget = 'admin.php?page=' . $redirectPageRaw;
                if ($redirectPageRaw === 'edit-post') {
                    $redirectFileRaw = trim((string) ($_POST['redirect_file'] ?? ''));
                    $safeRedirectFile = nammu_normalize_filename($redirectFileRaw);
                    if ($safeRedirectFile !== '') {
                        $redirectTarget .= '&file=' . urlencode($safeRedirectFile);
                    }
                }
            }
        }
        if ($redirectAnchor !== '') {
            $redirectTarget .= $redirectAnchor;
        }
        $autosavePayloadRaw = $_POST['autosave_payload'] ?? '';
        $autosaveResult = ['saved' => false, 'filename' => '', 'message' => ''];
        if (is_string($autosavePayloadRaw) && trim($autosavePayloadRaw) !== '') {
            $autosaveResult = admin_autosave_from_payload($autosavePayloadRaw);
            if ($autosaveResult['saved'] && $autosaveResult['filename'] !== '') {
                $redirectTarget = 'admin.php?page=edit-post&file=' . urlencode($autosaveResult['filename']) . $redirectAnchor;
            }
            if ($autosaveResult['message'] !== '') {
                $_SESSION['edit_feedback'] = [
                    'type' => $autosaveResult['saved'] ? 'success' : 'warning',
                    'message' => $autosaveResult['message'],
                ];
            }
        }
        $targetTypeRaw = $_POST['target_type'] ?? '';
        $targetInputRaw = $_POST['target_input'] ?? '';
        $targetEditorRaw = $_POST['target_editor'] ?? '';
        $targetPrefixRaw = $_POST['target_prefix'] ?? '';
        $targetType = in_array($targetTypeRaw, ['field', 'editor'], true) ? $targetTypeRaw : '';
        $targetInput = preg_match('/^[A-Za-z0-9_-]+$/', $targetInputRaw) ? $targetInputRaw : '';
        $targetEditor = '';
        if (is_string($targetEditorRaw) && trim($targetEditorRaw) !== '') {
            $targetEditor = substr($targetEditorRaw, 0, 200);
        }
        $targetPrefix = '';
        if (is_string($targetPrefixRaw)) {
            $targetPrefix = substr($targetPrefixRaw, 0, 100);
        }
        $selectionStartRaw = $_POST['target_selection_start'] ?? '';
        $selectionEndRaw = $_POST['target_selection_end'] ?? '';
        $selectionScrollRaw = $_POST['target_selection_scroll'] ?? '';
        $selection = null;
        if ($selectionStartRaw !== '' && $selectionEndRaw !== '') {
            $selection = [
                'start' => max(0, (int) $selectionStartRaw),
                'end' => max(0, (int) $selectionEndRaw),
                'scrollTop' => max(0, (int) $selectionScrollRaw),
            ];
        }
        $normalizedFiles = [];
        if ($filesField !== null) {
            if (is_array($filesField['name'])) {
                $count = count($filesField['name']);
                for ($i = 0; $i < $count; $i++) {
                    $normalizedFiles[] = [
                        'name' => $filesField['name'][$i] ?? '',
                        'type' => $filesField['type'][$i] ?? '',
                        'tmp_name' => $filesField['tmp_name'][$i] ?? '',
                        'error' => $filesField['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                        'size' => $filesField['size'][$i] ?? 0,
                    ];
                }
            } else {
                $normalizedFiles[] = [
                    'name' => $filesField['name'] ?? '',
                    'type' => $filesField['type'] ?? '',
                    'tmp_name' => $filesField['tmp_name'] ?? '',
                    'error' => $filesField['error'] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $filesField['size'] ?? 0,
                ];
            }
        }
        $uploads = [];
        foreach ($normalizedFiles as $file) {
            $name = trim((string) ($file['name'] ?? ''));
            $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($name === '' && $error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $uploads[] = $file;
        }
        $savedAssets = [];
        if (empty($uploads)) {
            $feedback = ['type' => 'warning', 'message' => 'No se seleccionó ningún archivo.'];
        } else {
            $allowedExtensions = nammu_allowed_media_extensions();
            $successCount = 0;
            $errorMessages = [];
            foreach ($uploads as $file) {
                $originalName = $file['name'] ?? 'archivo';
                $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
                if ($errorCode === UPLOAD_ERR_INI_SIZE || $errorCode === UPLOAD_ERR_FORM_SIZE) {
                    $errorMessages[] = $originalName . ': supera el tamaño máximo permitido por el servidor (' . nammu_upload_limits_label() . ').';
                    continue;
                }
                if ($errorCode === UPLOAD_ERR_NO_FILE) {
                    $errorMessages[] = $originalName . ': no se seleccionó correctamente en el formulario.';
                    continue;
                }
                if ($errorCode !== UPLOAD_ERR_OK) {
                    $errorMessages[] = $originalName . ': error al subir (código ' . $errorCode . ').';
                    continue;
                }
                $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedExtensions, true)) {
                    $errorMessages[] = $originalName . ': formato no permitido. Usa imágenes o vídeos compatibles (jpg, png, mp4, webm...).';
                    continue;
                }
                $base = nammu_slugify(pathinfo($originalName, PATHINFO_FILENAME));
                if ($base === '') {
                    $base = 'archivo';
                }
                $targetName = $base . '.' . $ext;
                $targetName = nammu_unique_asset_filename($targetName);
                $targetPath = ASSETS_DIR . '/' . $targetName;
                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    if (function_exists('nammu_apply_shared_permissions')) {
                        nammu_apply_shared_permissions($targetPath, 0664, dirname($targetPath));
                    } else {
                        @chmod($targetPath, 0664);
                    }
                    $successCount++;
                    nammu_generate_webp_variant_for_asset($targetPath);
                    $savedAssets[] = nammu_admin_media_item_payload($targetName);
                } else {
                    $errorMessages[] = $originalName . ': no se pudo mover el archivo. Revisa los permisos de la carpeta assets/.';
                }
            }
            if ($successCount > 0 && empty($errorMessages)) {
                $feedback = [
                    'type' => 'success',
                    'message' => $successCount === 1 ? 'Archivo subido correctamente.' : $successCount . ' archivos subidos correctamente.',
                ];
            } elseif ($successCount > 0) {
                $feedback = [
                    'type' => 'warning',
                    'message' => ($successCount === 1 ? '1 archivo subido correctamente. ' : $successCount . ' archivos subidos correctamente. ') . 'Errores: ' . implode(' ', $errorMessages),
                ];
            } else {
                $feedback = [
                    'type' => 'danger',
                    'message' => 'No se pudieron subir los archivos. Detalles: ' . implode(' ', $errorMessages),
                ];
            }
        }
        if (!empty($savedAssets) || (is_string($autosavePayloadRaw) && trim($autosavePayloadRaw) !== '')) {
            $_SESSION['asset_apply'] = [
                'mode' => $targetType,
                'input' => $targetInput,
                'editor' => $targetEditor,
                'prefix' => $targetPrefix,
                'anchor' => $redirectAnchor,
                'selection' => $selection,
                'return_to_modal' => in_array($targetType, ['field', 'editor'], true),
                'files' => $savedAssets,
                'restore_payload' => is_string($autosavePayloadRaw) ? $autosavePayloadRaw : '',
            ];
        }
        $_SESSION['asset_feedback'] = $feedback;
        if (nammu_admin_wants_json_response()) {
            nammu_admin_send_json_response([
                'ok' => !empty($savedAssets) && ($feedback['type'] ?? '') !== 'danger',
                'feedback' => $feedback,
                'assets' => $savedAssets,
            ]);
        }
        if (!empty($redirectTarget)) {
            header('Location: ' . $redirectTarget);
        } else {
            $redirectPage = isset($_POST['redirect_p']) ? max(1, (int) $_POST['redirect_p']) : 1;
            $redirectSearch = isset($_POST['redirect_search']) ? trim((string) $_POST['redirect_search']) : '';
            $redirectParams = 'page=resources';
            if ($redirectPage > 1) {
                $redirectParams .= '&p=' . $redirectPage;
            }
            if ($redirectSearch !== '') {
                $redirectParams .= '&search=' . urlencode($redirectSearch);
            }
            header('Location: admin.php?' . $redirectParams);
        }
        exit;
} elseif (isset($_POST['save_edited_image'])) {
        $image_data = $_POST['image_data'] ?? '';
        $image_name = $_POST['image_name'] ?? '';
        $tagsInput = $_POST['image_tags'] ?? '';
        $redirectPage = isset($_POST['redirect_p']) ? max(1, (int) $_POST['redirect_p']) : 1;
        $redirectSearch = isset($_POST['redirect_search']) ? trim((string) $_POST['redirect_search']) : '';

        if ($image_data && $image_name) {
            $image_name = preg_replace('/[^A-Za-z0-9\._-]/ ', '', basename($image_name));

            list($type, $image_data) = explode(';', $image_data);
            list(, $image_data)      = explode(',', $image_data);
            $image_data = base64_decode($image_data);

            $target_path = ASSETS_DIR . '/' . $image_name;

            if (function_exists('nammu_atomic_write_file')) {
                nammu_atomic_write_file($target_path, $image_data);
                nammu_apply_shared_permissions($target_path, 0664, dirname($target_path));
            } else {
                file_put_contents($target_path, $image_data, LOCK_EX);
                @chmod($target_path, 0664);
            }
            nammu_generate_webp_variant_for_asset($target_path);
            update_media_tags_entry($image_name, parse_media_tags_input($tagsInput));
            $_SESSION['asset_feedback'] = [
                'type' => 'success',
                'message' => 'Imagen guardada y etiquetas actualizadas.',
            ];
        }

        $redirectParams = 'page=resources';
        if ($redirectPage > 1) {
            $redirectParams .= '&p=' . $redirectPage;
        }
        if ($redirectSearch !== '') {
            $redirectParams .= '&search=' . urlencode($redirectSearch);
        }

        header('Location: admin.php?' . $redirectParams);
        exit;
} elseif (isset($_POST['update_image_tags'])) {
        $targetRelative = $_POST['original_image'] ?? '';
        $normalizedTarget = normalize_media_tag_key($targetRelative);
        $redirectPage = isset($_POST['redirect_p']) ? max(1, (int) $_POST['redirect_p']) : 1;
        $redirectSearch = isset($_POST['redirect_search']) ? trim((string) $_POST['redirect_search']) : '';
        $redirectTarget = 'admin.php?page=resources';
        $redirectUrlRaw = trim((string) ($_POST['redirect_url'] ?? ''));
        $redirectPageRaw = trim((string) ($_POST['redirect_page'] ?? ''));
        $redirectAnchorRaw = trim((string) ($_POST['redirect_anchor'] ?? ''));
        $redirectAnchor = '';
        if ($redirectAnchorRaw !== '' && preg_match('/^[A-Za-z0-9_-]+$/', $redirectAnchorRaw)) {
            $redirectAnchor = '#' . $redirectAnchorRaw;
        }
        $pattern = '/^page=[a-z0-9._%\-\/&=]+$/i';
        if ($redirectUrlRaw !== '' && preg_match($pattern, $redirectUrlRaw)) {
            $redirectTarget = 'admin.php?' . $redirectUrlRaw;
        } else {
            $allowedPages = ['resources','publish','edit','edit-post','template','itinerarios','itinerario','configuracion','correo-postal','anuncios'];
            if (in_array($redirectPageRaw, $allowedPages, true)) {
                $redirectTarget = 'admin.php?page=' . $redirectPageRaw;
                if ($redirectPageRaw === 'edit-post') {
                    $redirectFileRaw = trim((string) ($_POST['redirect_file'] ?? ''));
                    $safeRedirectFile = nammu_normalize_filename($redirectFileRaw);
                    if ($safeRedirectFile !== '') {
                        $redirectTarget .= '&file=' . urlencode($safeRedirectFile);
                    }
                }
            }
        }
        if ($redirectAnchor !== '') {
            $redirectTarget .= $redirectAnchor;
        }
        if ($normalizedTarget !== '') {
            update_media_tags_entry($normalizedTarget, parse_media_tags_input($_POST['image_tags'] ?? ''));
            $_SESSION['asset_feedback'] = [
                'type' => 'success',
                'message' => 'Etiquetas guardadas correctamente.',
            ];
        } else {
            $_SESSION['asset_feedback'] = [
                'type' => 'warning',
                'message' => 'No se pudo actualizar las etiquetas del recurso seleccionado.',
            ];
        }
        if (nammu_admin_wants_json_response()) {
            nammu_admin_send_json_response([
                'ok' => $normalizedTarget !== '',
                'feedback' => $_SESSION['asset_feedback'],
                'asset' => $normalizedTarget !== '' ? nammu_admin_media_item_payload($normalizedTarget) : null,
            ]);
        }
        $returnToModal = isset($_POST['return_to_modal']) && $_POST['return_to_modal'] === '1';
        if ($returnToModal) {
            $selection = [
                'start' => isset($_POST['target_selection_start']) ? (int) $_POST['target_selection_start'] : null,
                'end' => isset($_POST['target_selection_end']) ? (int) $_POST['target_selection_end'] : null,
                'scrollTop' => isset($_POST['target_selection_scroll']) ? (int) $_POST['target_selection_scroll'] : null,
            ];
            $_SESSION['asset_apply'] = [
                'mode' => trim((string) ($_POST['target_type'] ?? '')),
                'input' => trim((string) ($_POST['target_input'] ?? '')),
                'editor' => trim((string) ($_POST['target_editor'] ?? '')),
                'prefix' => trim((string) ($_POST['target_prefix'] ?? '')),
                'anchor' => $redirectAnchor,
                'selection' => $selection,
                'return_to_modal' => true,
                'files' => [],
                'restore_payload' => '',
            ];
            header('Location: ' . $redirectTarget);
            exit;
        }
        $redirectParams = 'page=resources';
        if ($redirectPage > 1) {
            $redirectParams .= '&p=' . $redirectPage;
        }
        if ($redirectSearch !== '') {
            $redirectParams .= '&search=' . urlencode($redirectSearch);
        }
        header('Location: admin.php?' . $redirectParams);
        exit;
} elseif (isset($_POST['delete_tag_global'])) {
        $tagToDelete = nammu_normalize_tag($_POST['delete_tag_choice'] ?? '');
        $redirectPage = isset($_POST['redirect_p']) ? max(1, (int) $_POST['redirect_p']) : 1;
        $redirectSearch = isset($_POST['redirect_search']) ? trim((string) $_POST['redirect_search']) : '';
        if ($tagToDelete === '') {
            $_SESSION['asset_feedback'] = [
                'type' => 'warning',
                'message' => 'Selecciona una etiqueta para borrar.',
            ];
        } else {
            $map = load_media_tags();
            $changed = false;
            foreach ($map as $key => $tags) {
                if (!is_array($tags)) {
                    continue;
                }
                $filtered = [];
                foreach ($tags as $tag) {
                    $normalized = nammu_normalize_tag((string) $tag);
                    if ($normalized === '' || $normalized === $tagToDelete) {
                        $changed = $changed || $normalized === $tagToDelete;
                        continue;
                    }
                    $filtered[] = $normalized;
                }
                if (empty($filtered)) {
                    unset($map[$key]);
                    $changed = true;
                } elseif (count($filtered) !== count($tags)) {
                    $map[$key] = array_values(array_unique($filtered));
                    $changed = true;
                }
            }
            if ($changed) {
                save_media_tags($map);
                $_SESSION['asset_feedback'] = [
                    'type' => 'success',
                    'message' => 'Etiqueta borrada de todos los recursos.',
                ];
            } else {
                $_SESSION['asset_feedback'] = [
                    'type' => 'warning',
                    'message' => 'La etiqueta no estaba asignada a ningún recurso.',
                ];
            }
        }
        $redirectParams = 'page=resources';
        if ($redirectPage > 1) {
            $redirectParams .= '&p=' . $redirectPage;
        }
        if ($redirectSearch !== '') {
            $redirectParams .= '&search=' . urlencode($redirectSearch);
        }
        header('Location: admin.php?' . $redirectParams);
        exit;
} elseif (isset($_POST['delete_asset'])) {
        $file_to_delete = $_POST['delete_asset'] ?? ($_POST['file_to_delete'] ?? '');
        $redirectPage = isset($_POST['redirect_p']) ? max(1, (int) $_POST['redirect_p']) : 1;
        $redirectSearch = isset($_POST['redirect_search']) ? trim((string) $_POST['redirect_search']) : '';
        $file_to_delete = ltrim((string) $file_to_delete, '/');
        $feedback = null;
        if ($file_to_delete !== '' && strpos($file_to_delete, '..') === false) {
            $filepath = ASSETS_DIR . '/' . $file_to_delete;
            if (file_exists($filepath)) {
                if (@unlink($filepath)) {
                    delete_media_tags_entry($file_to_delete);
                    $feedback = ['type' => 'success', 'message' => 'Recurso borrado correctamente.'];
                } else {
                    $feedback = ['type' => 'warning', 'message' => 'No se pudo borrar el archivo. Revisa permisos.'];
                }
            } else {
                $feedback = ['type' => 'warning', 'message' => 'El recurso ya no existe.'];
            }
        } else {
            $feedback = ['type' => 'warning', 'message' => 'Recurso no válido para borrar.'];
        }
        $_SESSION['asset_feedback'] = $feedback;
        $redirectParams = 'page=resources';
        if ($redirectPage > 1) {
            $redirectParams .= '&p=' . $redirectPage;
        }
        if ($redirectSearch !== '') {
            $redirectParams .= '&search=' . urlencode($redirectSearch);
        }
        header('Location: admin.php?' . $redirectParams);
        exit;
}
