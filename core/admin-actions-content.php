<?php
/**
 * Nammu — panel de administración. Acciones POST: Entradas, páginas, podcasts y newsletters: crear, actualizar, publicar borradores, borrar y recalcular el orden.
 *
 * Se incluye desde admin.php dentro del ámbito global, una vez validado el token CSRF,
 * cuando la petición trae alguna de las claves de formulario de este grupo.
 */

use Nammu\Core\MarkdownConverter;

if (isset($_POST['send_newsletter'])) {
        $title = trim($_POST['title'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $dateInput = $_POST['date'] ?? '';
        $timestamp = $dateInput !== '' ? strtotime($dateInput) : time();
        if ($timestamp === false) {
            $timestamp = time();
        }
        $date = date('Y-m-d', $timestamp);
        $image = trim($_POST['image'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $lang = trim($_POST['lang'] ?? '');
        $filenameInput = trim($_POST['filename'] ?? '');
        $slugPattern = '/^[a-z0-9-]+$/i';
        if ($filenameInput !== '' && !preg_match($slugPattern, $filenameInput)) {
            $error = 'El slug solo puede contener letras, números y guiones medios.';
        }
        if ($filenameInput === '' && $title !== '') {
            $filenameInput = nammu_slugify($title);
        }
        if ($error === null) {
            $filename = nammu_unique_filename($filenameInput !== '' ? $filenameInput : 'newsletter');
        } else {
            $filename = '';
        }
        $content = $_POST['content'] ?? '';
        $settings = get_settings();
        if (!admin_is_mailing_ready($settings)) {
            $error = 'Configura el correo de la lista antes de enviar la newsletter.';
        }
        $mailing = $settings['mailing'] ?? [];
        if ($error === null && ($mailing['auto_newsletter'] ?? 'off') !== 'on') {
            $error = 'Activa la newsletter en Lista > Preferencias de envio.';
        }
        $subscribers = admin_mailing_recipients_for_type('newsletter', $settings);
        if ($error === null && empty($subscribers)) {
            $error = 'No hay suscriptores en la lista de correo.';
        }
        $newsletterSendResult = null;
        if ($error === null && $filename !== '') {
            $markdown = new MarkdownConverter();
            $contentHtml = $markdown->toHtml($content);
            $contentText = trim(strip_tags($contentHtml));
            $newsletterSendResult = admin_schedule_mailing_broadcast('newsletter', $subscribers, [
                'filename' => $filename . '.md',
                'title' => $title,
                'image' => $image,
                'content_html' => $contentHtml,
                'content_text' => $contentText,
            ]);
            if (($newsletterSendResult['queued_recipients'] ?? 0) === 0) {
                $error = 'No se pudo encolar la newsletter. Revisa la configuración de correo.';
            }
        }
        if ($error === null && $filename !== '') {
            $all_posts = get_all_posts_metadata();
            $max_ordo = 0;
            foreach ($all_posts as $post) {
                if (isset($post['metadata']['Ordo']) && (int)$post['metadata']['Ordo'] > $max_ordo) {
                    $max_ordo = (int)$post['metadata']['Ordo'];
                }
            }
            $ordo = $max_ordo + 1;
            $targetFilename = nammu_normalize_filename($filename . '.md');
            if ($targetFilename === '') {
                $error = 'El nombre de archivo no es válido.';
            } else {
                $filepath = CONTENT_DIR . '/' . $targetFilename;
                $file_content = "---
";
                $file_content .= "Title: " . $title . "
";
                $file_content .= "Template: newsletter
";
                $file_content .= "Category: " . $category . "
";
                $file_content .= "Date: " . $date . "
";
                $file_content .= "Image: " . $image . "
";
                $file_content .= "Description: " . $description . "
";
                if ($lang !== '') {
                    $file_content .= "Lang: " . $lang . "
";
                }
                $file_content .= "Status: newsletter
";
                $file_content .= "Ordo: " . $ordo . "
";
                $file_content .= "---

";
                $file_content .= $content;
                if (file_put_contents($filepath, $file_content, LOCK_EX) === false) {
                    $error = 'No se pudo guardar la newsletter. Revisa los permisos de la carpeta content/.';
                } else {
                    admin_chmod_content_file($filepath);
                    if (is_array($newsletterSendResult)) {
                        $queuedCount = (int) ($newsletterSendResult['queued_recipients'] ?? 0);
                        $batchCount = (int) ($newsletterSendResult['queued_batches'] ?? 0);
                        $_SESSION['mailing_feedback'] = [
                            'type' => 'success',
                            'message' => 'Newsletter encolada en ' . $batchCount . ' tanda' . ($batchCount === 1 ? '' : 's') . ' para ' . $queuedCount . ' destinatario' . ($queuedCount === 1 ? '' : 's') . '.',
                        ];
                    } else {
                        $_SESSION['mailing_feedback'] = [
                            'type' => 'success',
                            'message' => 'Newsletter encolada correctamente.',
                        ];
                    }
                    header('Location: admin.php?page=edit&template=newsletter&created=' . urlencode($targetFilename));
                    exit;
                }
            }
        }
} elseif (isset($_POST['publish']) || isset($_POST['save_draft']) || isset($_POST['publish_and_view'])) {
        $existing_post_data = null;
        $title = trim($_POST['title'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $dateInput = $_POST['date'] ?? '';
        $timestamp = $dateInput !== '' ? strtotime($dateInput) : time();
        if ($timestamp === false) {
            $timestamp = time();
        }
        $date = date('Y-m-d', $timestamp);
        $publishAtDate = trim($_POST['publish_at_date'] ?? '');
        $publishAtTime = trim($_POST['publish_at_time'] ?? '');
        $image = trim($_POST['image'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $lang = trim($_POST['lang'] ?? '');
        $audio = trim($_POST['audio'] ?? '');
        $video = trim($_POST['video'] ?? '');
        $audioLengthInput = trim($_POST['audio_length'] ?? '');
        $audioDuration = trim($_POST['audio_duration'] ?? '');
        $pageVisibilityInput = strtolower(trim((string) ($_POST['page_visibility'] ?? 'public')));
        $relatedSlugsFieldPresent = array_key_exists('related_slugs', $_POST);
        $relatedSlugsInput = trim((string) ($_POST['related_slugs'] ?? ''));
        $type = $_POST['type'] ?? 'Entrada';
        if ($type === 'Página') {
            $type = 'Página';
        } elseif ($type === 'Podcast') {
            $type = 'Podcast';
        } elseif ($type === 'Newsletter') {
            $type = 'Newsletter';
        } elseif ($type === 'Nota') {
            $type = 'Nota';
        } else {
            $type = 'Entrada';
        }
        if ($type === 'Nota') {
            require_once NAMMU_ROOT . '/core/admin-redes.php';
            $messageText = trim((string) ($_POST['content'] ?? ''));
            $messageImages = trim((string) ($_POST['social_broadcast_image'] ?? ''));
            $socialBroadcastState = admin_handle_social_broadcast_submission(get_settings(), $messageText, $messageImages);
            $_SESSION['social_broadcast_feedback'] = $socialBroadcastState['feedback'] ?? null;
            $_SESSION['social_broadcast_state'] = [
                'message_text' => (($socialBroadcastState['feedback']['type'] ?? '') === 'success') ? '' : (string) ($socialBroadcastState['message_text'] ?? $messageText),
                'image' => (($socialBroadcastState['feedback']['type'] ?? '') === 'success') ? '' : (string) ($socialBroadcastState['image'] ?? $messageImages),
                'actuality' => !empty($socialBroadcastState['actuality']),
                'networks' => is_array($socialBroadcastState['networks'] ?? null) ? $socialBroadcastState['networks'] : [],
            ];
            header('Location: admin.php?page=publish');
            exit;
        }
        $pageVisibility = ($type === 'Página' && $pageVisibilityInput === 'private') ? 'private' : 'public';
        $filenameInput = trim($_POST['filename'] ?? '');
        $viewAfterSave = isset($_POST['publish_and_view']);
        $isDraft = isset($_POST['save_draft']);
        if ($viewAfterSave) {
            // "Ver en la web" siempre previsualiza: guardar como borrador.
            $isDraft = true;
        }
        $statusValue = $isDraft ? 'draft' : 'published';
        $publishAtValue = '';
        $slugPattern = '/^[a-z0-9-]+$/i';

        if ($filenameInput !== '' && !preg_match($slugPattern, $filenameInput)) {
            $error = 'El slug solo puede contener letras, números y guiones medios.';
        }

        if ($filenameInput === '' && $title !== '') {
            $filenameInput = nammu_slugify($title);
        }

        $relatedSlugs = [];
        if ($type === 'Entrada' || $type === 'Podcast') {
            $existingRelatedRaw = '';
            if (is_array($existing_post_data) && isset($existing_post_data['metadata']) && is_array($existing_post_data['metadata'])) {
                $existingRelatedRaw = trim((string) ($existing_post_data['metadata']['Related'] ?? $existing_post_data['metadata']['related'] ?? ''));
            }
            if ($relatedSlugsInput === '' && $existingRelatedRaw !== '' && $relatedSlugsFieldPresent) {
                // Evita perder relacionados al actualizar campos no relacionados (por ejemplo, imagen).
                $relatedSlugs = admin_parse_related_slugs_input($existingRelatedRaw);
            } else {
                $relatedSlugs = admin_parse_related_slugs_input($relatedSlugsInput);
            }
            if ($relatedSlugsInput !== '' && count($relatedSlugs) < 2) {
                $error = 'Entradas o itinerarios relacionados: indica al menos 2 slugs válidos.';
            } elseif (count($relatedSlugs) > 8) {
                $error = 'Entradas o itinerarios relacionados: el máximo son 8 slugs.';
            }
        }

        if ($error === null) {
            $filename = nammu_unique_filename($filenameInput);
        } else {
            $filename = '';
        }

        $all_posts = get_all_posts_metadata();
        $max_ordo = 0;
        foreach ($all_posts as $post) {
            if (isset($post['metadata']['Ordo']) && (int)$post['metadata']['Ordo'] > $max_ordo) {
                $max_ordo = (int)$post['metadata']['Ordo'];
            }
        }
        $ordo = $max_ordo + 1;

        $content = $_POST['content'] ?? '';
        $audioLength = '';
        $audioUrl = '';
        if ($type === 'Podcast') {
            if ($audio === '') {
                $error = 'El archivo mp3 del podcast es obligatorio.';
            }
            if ($audio !== '' && !preg_match('/\.mp3$/i', $audio)) {
                $error = 'El audio del podcast debe ser un archivo mp3.';
            }
            if ($video !== '' && !preg_match('/\.mp4$/i', $video)) {
                $error = 'El vídeo del podcast debe ser un archivo mp4.';
            }
            if ($audioDuration === '') {
                $error = 'Indica la duración del episodio en formato hh:mm:ss.';
            }
            $category = '';
            if ($audio !== '') {
                $audioPath = ltrim($audio, '/');
                $audioPath = str_starts_with($audioPath, 'assets/') ? substr($audioPath, strlen('assets/')) : $audioPath;
                $candidate = ASSETS_DIR . '/' . $audioPath;
                if (is_file($candidate)) {
                    $audioLength = (string) filesize($candidate);
                }
            }
            if ($audioLength === '' && $audioLengthInput !== '' && ctype_digit($audioLengthInput)) {
                $audioLength = $audioLengthInput;
            }
        } elseif ($type === 'Newsletter') {
            $category = '';
        }

        if ($filename !== '') {
            $targetFilename = nammu_normalize_filename($filename . '.md');
            if ($targetFilename === '') {
                $error = 'El nombre de archivo no es válido.';
            } else {
                $filepath = CONTENT_DIR . '/' . $targetFilename;

                $file_content = "---
";
                $file_content .= "Title: " . $title . "
";
                $templateValue = $type === 'Página' ? 'page' : ($type === 'Podcast' ? 'podcast' : ($type === 'Newsletter' ? 'newsletter' : 'post'));
                $file_content .= "Template: " . $templateValue . "
";
                $file_content .= "Category: " . $category . "
";
                $file_content .= "Date: " . $date . "
";
                $file_content .= "Image: " . $image . "
";
                $file_content .= "Description: " . $description . "
";
                if ($type === 'Podcast') {
                    $file_content .= "Audio: " . $audio . "
";
                    if ($video !== '') {
                        $file_content .= "Video: " . $video . "
";
                    }
                    if ($audioLength !== '') {
                        $file_content .= "AudioLength: " . $audioLength . "
";
                    }
                    if ($audioDuration !== '') {
                        $file_content .= "AudioDuration: " . $audioDuration . "
";
                    }
                }
                if ($lang !== '') {
                    $file_content .= "Lang: " . $lang . "
";
                }
                if ($templateValue === 'page') {
                    $file_content .= "Visibility: " . $pageVisibility . "
";
                }
                if (!empty($relatedSlugs) && in_array($templateValue, ['post', 'podcast'], true)) {
                    $file_content .= "Related: " . implode(', ', $relatedSlugs) . "
";
                }
                $file_content .= "Status: " . $statusValue . "
";
                if ($isDraft && $publishAtDate !== '') {
                    $publishAtTime = $publishAtTime !== '' ? $publishAtTime : '00:00';
                    $publishTimestamp = strtotime($publishAtDate . ' ' . $publishAtTime);
                    if ($publishTimestamp !== false) {
                        $publishAtValue = date('Y-m-d H:i', $publishTimestamp);
                    }
                }
                if ($publishAtValue !== '') {
                    $file_content .= "PublishAt: " . $publishAtValue . "
";
                }
                $file_content .= "Ordo: " . $ordo . "
";
                $file_content .= "---

";
                $file_content .= $content;

                if (file_put_contents($filepath, $file_content, LOCK_EX) === false) {
                    $error = 'No se pudo guardar el contenido. Revisa los permisos de la carpeta content/.';
                } else {
                    admin_chmod_content_file($filepath);
                    if (!$isDraft && $type === 'Entrada') {
                        $imageUrl = admin_public_asset_url($image);
                        admin_maybe_auto_post_to_social_networks($targetFilename, $title, $description, $image, '', $imageUrl);
                        $settings = get_settings();
                        $mailing = $settings['mailing'] ?? [];
                        $slug = pathinfo($targetFilename, PATHINFO_FILENAME);
                        $link = admin_public_post_url($slug);
                        if (($mailing['auto_posts'] ?? 'off') === 'on' && admin_is_mailing_ready($settings)) {
                            $subscribers = admin_mailing_recipients_for_type('posts', $settings);
                            if (!empty($subscribers)) {
                                $payload = admin_prepare_mailing_payload('single', $settings, $title, $description, $link, $image);
                                admin_schedule_mailing_broadcast('post', $subscribers, [
                                    'filename' => $targetFilename,
                                    'slug' => $slug,
                                    'title' => $title,
                                    'description' => $description,
                                    'image' => $image,
                                    'template' => 'single',
                                ]);
                            }
                        }
                        admin_maybe_enqueue_push_notification('post', $title, $description, $link, $image);
                    }
                    if (!$isDraft && $type === 'Podcast') {
                        $audioUrl = admin_public_asset_url($audio);
                        $imageUrl = admin_public_asset_url($image);
                        $slug = pathinfo($targetFilename, PATHINFO_FILENAME);
                        $podcastUrl = $slug !== '' ? admin_public_podcast_url($slug) : '';
                        if ($podcastUrl !== '') {
                            admin_maybe_auto_post_to_social_networks($targetFilename, $title, $description, $image, $podcastUrl, $imageUrl);
                        }
                        $settings = get_settings();
                        $mailing = $settings['mailing'] ?? [];
                        if (($mailing['auto_podcast'] ?? 'off') === 'on' && admin_is_mailing_ready($settings) && $audioUrl !== '') {
                            $subscribers = admin_mailing_recipients_for_type('podcast', $settings);
                            if (!empty($subscribers)) {
                                $payload = admin_prepare_mailing_payload('podcast', $settings, $title, $description, $audioUrl, $image);
                                admin_schedule_mailing_broadcast('podcast', $subscribers, [
                                    'filename' => $targetFilename,
                                    'slug' => $slug,
                                    'title' => $title,
                                    'description' => $description,
                                    'image' => $image,
                                    'audio' => $audio,
                                    'template' => 'podcast',
                                ]);
                            }
                        }
                    }
                    if (!$isDraft) {
                        admin_regenerate_public_artifacts();
                    }
                    if (!$isDraft) {
                        $indexnowUrls = [];
                        $slug = pathinfo($targetFilename, PATHINFO_FILENAME);
                        if (in_array($type, ['Entrada', 'Página'], true) && $slug !== '' && !($type === 'Página' && $pageVisibility === 'private')) {
                            $indexnowUrls[] = admin_public_post_url($slug);
                        } elseif ($type === 'Podcast' && $slug !== '') {
                            $indexnowUrls[] = admin_public_podcast_url($slug);
                        }
                        if (!empty($indexnowUrls)) {
                            admin_maybe_send_indexnow($indexnowUrls);
                        }
                    }
                    if ($viewAfterSave) {
                        $slug = pathinfo($targetFilename, PATHINFO_FILENAME);
                        $viewUrl = '';
                        if ($slug !== '') {
                            if ($type === 'Podcast') {
                                $viewUrl = admin_public_podcast_url($slug);
                            } elseif ($type === 'Newsletter') {
                                $viewUrl = admin_public_newsletter_url($slug);
                            } elseif (in_array($type, ['Entrada', 'Página'], true)) {
                                $viewUrl = admin_public_post_url($slug);
                            }
                        }
                        if ($viewUrl !== '' && $isDraft) {
                            $viewUrl .= (str_contains($viewUrl, '?') ? '&' : '?') . 'preview=1';
                        }
                        if ($viewUrl !== '') {
                            header('Location: ' . $viewUrl);
                            exit;
                        }
                    }
                    $redirectTemplate = $isDraft ? 'draft' : ($type === 'Página' ? 'page' : ($type === 'Podcast' ? 'podcast' : ($type === 'Newsletter' ? 'newsletter' : 'single')));
                    header('Location: admin.php?page=edit&template=' . $redirectTemplate . '&created=' . urlencode($targetFilename));
                    exit;
                }
            }
        }
} elseif (isset($_POST['resend_newsletter_edit'])) {
        $title = trim($_POST['title'] ?? '');
        $editFilename = trim($_POST['filename'] ?? '');
        $image = trim($_POST['image'] ?? '');
        $content = $_POST['content'] ?? '';
        $settings = get_settings();
        if (!admin_is_mailing_ready($settings)) {
            $_SESSION['mailing_feedback'] = [
                'type' => 'danger',
                'message' => 'Configura el correo de la lista antes de reenviar la newsletter.',
            ];
            header('Location: admin.php?page=edit-post&file=' . urlencode($editFilename));
            exit;
        }
        $mailing = $settings['mailing'] ?? [];
        if (($mailing['auto_newsletter'] ?? 'off') !== 'on') {
            $_SESSION['mailing_feedback'] = [
                'type' => 'warning',
                'message' => 'Activa la newsletter en Lista > Preferencias de envio.',
            ];
            header('Location: admin.php?page=edit-post&file=' . urlencode($editFilename));
            exit;
        }
        $subscribers = admin_mailing_recipients_for_type('newsletter', $settings);
        if (empty($subscribers)) {
            $_SESSION['mailing_feedback'] = [
                'type' => 'warning',
                'message' => 'No hay suscriptores en la lista de correo.',
            ];
            header('Location: admin.php?page=edit-post&file=' . urlencode($editFilename));
            exit;
        }
        $markdown = new MarkdownConverter();
        $contentHtml = $markdown->toHtml($content);
        $contentText = trim(strip_tags($contentHtml));
        $queueResult = admin_schedule_mailing_broadcast('newsletter', $subscribers, [
            'filename' => $editFilename,
            'title' => $title,
            'image' => $image,
            'content_html' => $contentHtml,
            'content_text' => $contentText,
        ]);
        if (($queueResult['queued_recipients'] ?? 0) === 0) {
            $_SESSION['mailing_feedback'] = [
                'type' => 'danger',
                'message' => 'No se pudo encolar la newsletter. Revisa la configuración de correo.',
            ];
            header('Location: admin.php?page=edit-post&file=' . urlencode($editFilename));
            exit;
        }
        $_SESSION['mailing_feedback'] = [
            'type' => 'success',
            'message' => 'Newsletter reencolada en ' . ((int) ($queueResult['queued_batches'] ?? 0)) . ' tanda' . (((int) ($queueResult['queued_batches'] ?? 0)) === 1 ? '' : 's') . ' para ' . ((int) ($queueResult['queued_recipients'] ?? 0)) . ' destinatario' . (((int) ($queueResult['queued_recipients'] ?? 0)) === 1 ? '' : 's') . '.',
        ];
        header('Location: admin.php?page=edit-post&file=' . urlencode($editFilename));
        exit;
} elseif (isset($_POST['send_newsletter_custom_edit'])) {
        $title = trim($_POST['title'] ?? '');
        $editFilename = trim($_POST['filename'] ?? '');
        $image = trim($_POST['image'] ?? '');
        $content = $_POST['content'] ?? '';
        $rawRecipients = trim((string) ($_POST['custom_recipients'] ?? ''));
        $_SESSION['newsletter_custom_recipients'] = $rawRecipients;
        $settings = get_settings();
        if (!admin_is_mailing_ready($settings)) {
            $_SESSION['mailing_feedback'] = [
                'type' => 'danger',
                'message' => 'Configura el correo de la lista antes de enviar la newsletter.',
            ];
            header('Location: admin.php?page=edit-post&file=' . urlencode($editFilename));
            exit;
        }
        if ($rawRecipients === '') {
            $_SESSION['mailing_feedback'] = [
                'type' => 'warning',
                'message' => 'Indica al menos una dirección de email en "Enviar a...".',
            ];
            header('Location: admin.php?page=edit-post&file=' . urlencode($editFilename));
            exit;
        }
        $normalized = str_replace([',', ';', "\t"], ' ', $rawRecipients);
        $tokens = preg_split('/\s+/u', $normalized) ?: [];
        $recipientsMap = [];
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
            $recipientsMap[$email] = true;
        }
        $recipients = array_keys($recipientsMap);
        if (empty($recipients)) {
            $_SESSION['mailing_feedback'] = [
                'type' => 'warning',
                'message' => 'No se detectaron direcciones válidas en "Enviar a...".',
            ];
            header('Location: admin.php?page=edit-post&file=' . urlencode($editFilename));
            exit;
        }
        $markdown = new MarkdownConverter();
        $contentHtml = $markdown->toHtml($content);
        $contentText = trim(strip_tags($contentHtml));
        $queueResult = admin_schedule_mailing_broadcast('newsletter', $recipients, [
            'filename' => $editFilename,
            'title' => $title,
            'image' => $image,
            'content_html' => $contentHtml,
            'content_text' => $contentText,
        ]);
        if (($queueResult['queued_recipients'] ?? 0) === 0) {
            $_SESSION['mailing_feedback'] = [
                'type' => 'danger',
                'message' => 'No se pudo encolar la newsletter para los destinatarios indicados.',
            ];
            header('Location: admin.php?page=edit-post&file=' . urlencode($editFilename));
            exit;
        }
        $queuedCount = (int) ($queueResult['queued_recipients'] ?? 0);
        $batchCount = (int) ($queueResult['queued_batches'] ?? 0);
        $message = 'Newsletter encolada en ' . $batchCount . ' tanda' . ($batchCount === 1 ? '' : 's') . ' para ' . $queuedCount . ' destinatario' . ($queuedCount === 1 ? '' : 's');
        if ($invalidCount > 0) {
            $message .= '. ' . $invalidCount . ' dirección' . ($invalidCount === 1 ? '' : 'es') . ' inválida' . ($invalidCount === 1 ? '' : 's') . ' ignorada' . ($invalidCount === 1 ? '' : 's') . '.';
        } else {
            $message .= '.';
        }
        $_SESSION['mailing_feedback'] = [
            'type' => 'success',
            'message' => $message,
        ];
        unset($_SESSION['newsletter_custom_recipients']);
        header('Location: admin.php?page=edit-post&file=' . urlencode($editFilename));
        exit;
} elseif (isset($_POST['send_newsletter_edit'])) {
        $title = trim($_POST['title'] ?? '');
        $editFilename = trim($_POST['filename'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $dateInput = $_POST['date'] ?? '';
        $timestamp = $dateInput !== '' ? strtotime($dateInput) : time();
        if ($timestamp === false) {
            $timestamp = time();
        }
        $date = date('Y-m-d', $timestamp);
        $image = trim($_POST['image'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $lang = trim($_POST['lang'] ?? '');
        $content = $_POST['content'] ?? '';
        $settings = get_settings();
        if (!admin_is_mailing_ready($settings)) {
            $_SESSION['mailing_feedback'] = [
                'type' => 'danger',
                'message' => 'Configura el correo de la lista antes de enviar la newsletter.',
            ];
            header('Location: admin.php?page=edit-post&file=' . urlencode($editFilename));
            exit;
        }
        $mailing = $settings['mailing'] ?? [];
        if (($mailing['auto_newsletter'] ?? 'off') !== 'on') {
            $_SESSION['mailing_feedback'] = [
                'type' => 'warning',
                'message' => 'Activa la newsletter en Lista > Preferencias de envio.',
            ];
            header('Location: admin.php?page=edit-post&file=' . urlencode($editFilename));
            exit;
        }
        $subscribers = admin_mailing_recipients_for_type('newsletter', $settings);
        if (empty($subscribers)) {
            $_SESSION['mailing_feedback'] = [
                'type' => 'warning',
                'message' => 'No hay suscriptores en la lista de correo.',
            ];
            header('Location: admin.php?page=edit-post&file=' . urlencode($editFilename));
            exit;
        }
        $markdown = new MarkdownConverter();
        $contentHtml = $markdown->toHtml($content);
        $contentText = trim(strip_tags($contentHtml));
        $queueResult = admin_schedule_mailing_broadcast('newsletter', $subscribers, [
            'filename' => $editFilename,
            'title' => $title,
            'image' => $image,
            'content_html' => $contentHtml,
            'content_text' => $contentText,
        ]);
        if (($queueResult['queued_recipients'] ?? 0) === 0) {
            $_SESSION['mailing_feedback'] = [
                'type' => 'danger',
                'message' => 'No se pudo encolar la newsletter. Revisa la configuración de correo.',
            ];
            header('Location: admin.php?page=edit-post&file=' . urlencode($editFilename));
            exit;
        }
        $targetFilename = nammu_normalize_filename($editFilename);
        if ($targetFilename !== '') {
            $existing = get_post_content($targetFilename);
            $existingOrdo = '';
            if (is_array($existing) && isset($existing['metadata']) && is_array($existing['metadata'])) {
                $existingOrdo = trim((string) ($existing['metadata']['Ordo'] ?? ''));
            }
            if ($existingOrdo === '') {
                $allPosts = get_all_posts_metadata();
                $maxOrdo = 0;
                foreach ($allPosts as $post) {
                    if (isset($post['metadata']['Ordo']) && (int) $post['metadata']['Ordo'] > $maxOrdo) {
                        $maxOrdo = (int) $post['metadata']['Ordo'];
                    }
                }
                $existingOrdo = (string) ($maxOrdo + 1);
            }
            $filepath = CONTENT_DIR . '/' . $targetFilename;
            $fileContent = "---
";
            $fileContent .= "Title: " . $title . "
";
            $fileContent .= "Template: newsletter
";
            $fileContent .= "Category: " . $category . "
";
            $fileContent .= "Date: " . $date . "
";
            $fileContent .= "Image: " . $image . "
";
            $fileContent .= "Description: " . $description . "
";
            if ($lang !== '') {
                $fileContent .= "Lang: " . $lang . "
";
            }
            $fileContent .= "Status: newsletter
";
            $fileContent .= "Ordo: " . $existingOrdo . "
";
            $fileContent .= "---

";
            $fileContent .= $content;
            if (@file_put_contents($filepath, $fileContent, LOCK_EX) !== false) {
                admin_chmod_content_file($filepath);
            }
        }
        $_SESSION['mailing_feedback'] = [
            'type' => 'success',
            'message' => 'Newsletter encolada en ' . ((int) ($queueResult['queued_batches'] ?? 0)) . ' tanda' . (((int) ($queueResult['queued_batches'] ?? 0)) === 1 ? '' : 's') . ' para ' . ((int) ($queueResult['queued_recipients'] ?? 0)) . ' destinatario' . (((int) ($queueResult['queued_recipients'] ?? 0)) === 1 ? '' : 's') . '.',
        ];
        header('Location: admin.php?page=edit-post&file=' . urlencode($targetFilename !== '' ? $targetFilename : $editFilename));
        exit;
} elseif (isset($_POST['update']) || isset($_POST['update_and_view']) || isset($_POST['publish_draft_entry']) || isset($_POST['publish_draft_page']) || isset($_POST['publish_draft_podcast']) || isset($_POST['convert_to_draft'])) {
        $existing_post_data = null;
        $filename = $_POST['filename'] ?? '';
        $title = $_POST['title'] ?? '';
        $category = $_POST['category'] ?? '';
        $date = $_POST['date'] ? date('Y-m-d', strtotime($_POST['date'])) : date('Y-m-d');
        $publishAtDate = trim($_POST['publish_at_date'] ?? '');
        $publishAtTime = trim($_POST['publish_at_time'] ?? '');
        $image = $_POST['image'] ?? '';
        $description = $_POST['description'] ?? '';
        $lang = trim($_POST['lang'] ?? '');
        $audio = trim($_POST['audio'] ?? '');
        $video = trim($_POST['video'] ?? '');
        $audioLengthInput = trim($_POST['audio_length'] ?? '');
        $audioDuration = trim($_POST['audio_duration'] ?? '');
        $pageVisibilityInputRaw = strtolower(trim((string) ($_POST['page_visibility'] ?? '')));
        $relatedSlugsInput = trim((string) ($_POST['related_slugs'] ?? ''));
        $type = $_POST['type'] ?? null;
        $statusPosted = strtolower(trim($_POST['status'] ?? ''));
        $viewAfterSave = isset($_POST['update_and_view']);
        $publishDraftAsEntry = isset($_POST['publish_draft_entry']);
        $publishDraftAsPage = isset($_POST['publish_draft_page']);
        $publishDraftAsPodcast = isset($_POST['publish_draft_podcast']);
        $convertToDraft = isset($_POST['convert_to_draft']);

        // Preserve existing Ordo value on update
        $normalizedFilename = nammu_normalize_filename($filename);
        $previousStatus = 'published';
        $existingFediverseId = '';
        $previousFediverseTemplate = '';
        if ($normalizedFilename !== '') {
            $existing_post_data = get_post_content($normalizedFilename);
            $ordo = $existing_post_data['metadata']['Ordo'] ?? '';
            $previousStatus = strtolower($existing_post_data['metadata']['Status'] ?? 'published');
            $previousFediverseTemplate = strtolower(trim((string) ($existing_post_data['metadata']['Template'] ?? 'post')));
            if ($previousFediverseTemplate === 'single' || $previousFediverseTemplate === 'draft') {
                $previousFediverseTemplate = 'post';
            }
            $existingFediverseId = trim((string) (($existing_post_data['metadata']['FediverseId'] ?? '') ?: ($existing_post_data['metadata']['fediverse_id'] ?? '')));
            if ($lang === '') {
                $lang = trim((string) ($existing_post_data['metadata']['Lang'] ?? ''));
            }
            if ($type === null) {
                $currentTemplate = strtolower($existing_post_data['metadata']['Template'] ?? 'post');
                if ($currentTemplate === 'page') {
                    $type = 'Página';
                } elseif ($currentTemplate === 'podcast') {
                    $type = 'Podcast';
                } elseif ($currentTemplate === 'newsletter') {
                    $type = 'Newsletter';
                } else {
                    $type = 'Entrada';
                }
            }
        } else {
            $ordo = '';
        }

        if ($type === 'Página') {
            $type = 'Página';
        } elseif ($type === 'Podcast') {
            $type = 'Podcast';
        } elseif ($type === 'Newsletter') {
            $type = 'Newsletter';
        } else {
            $type = 'Entrada';
        }
        if ($publishDraftAsEntry) {
            $type = 'Entrada';
        } elseif ($publishDraftAsPage) {
            $type = 'Página';
        } elseif ($publishDraftAsPodcast) {
            $type = 'Podcast';
        }
        $existingVisibility = '';
        if (is_array($existing_post_data) && isset($existing_post_data['metadata']) && is_array($existing_post_data['metadata'])) {
            $existingVisibility = strtolower(trim((string) ($existing_post_data['metadata']['Visibility'] ?? '')));
        }
        $pageVisibility = 'public';
        if ($type === 'Página') {
            if ($pageVisibilityInputRaw === 'private' || ($pageVisibilityInputRaw === '' && $existingVisibility === 'private')) {
                $pageVisibility = 'private';
            }
        }
        $template = $type === 'Página' ? 'page' : ($type === 'Podcast' ? 'podcast' : ($type === 'Newsletter' ? 'newsletter' : 'post'));
        if ($publishDraftAsEntry || $publishDraftAsPage || $publishDraftAsPodcast) {
            $status = 'published';
        } elseif ($convertToDraft) {
            $status = 'draft';
        } else {
            if ($statusPosted === 'draft') {
                $status = 'draft';
            } elseif ($statusPosted === 'newsletter' && $type === 'Newsletter') {
                $status = 'newsletter';
            } else {
                $status = 'published';
            }
        }

        $newFilenameInput = trim($_POST['new_filename'] ?? '');
        $originalSlugBase = $normalizedFilename !== '' ? pathinfo($normalizedFilename, PATHINFO_FILENAME) : '';
        $originalSlugNormalized = $originalSlugBase !== '' ? nammu_slugify($originalSlugBase) : '';
        $content = $_POST['content'] ?? '';
        $audioLength = '';
        if ($type === 'Podcast') {
            if ($audio === '') {
                $error = 'El archivo mp3 del podcast es obligatorio.';
            }
            if ($audio !== '' && !preg_match('/\.mp3$/i', $audio)) {
                $error = 'El audio del podcast debe ser un archivo mp3.';
            }
            if ($video !== '' && !preg_match('/\.mp4$/i', $video)) {
                $error = 'El vídeo del podcast debe ser un archivo mp4.';
            }
            if ($audioDuration === '') {
                $error = 'Indica la duración del episodio en formato hh:mm:ss.';
            }
            $category = '';
            if ($audio !== '') {
                $audioPath = ltrim($audio, '/');
                $audioPath = str_starts_with($audioPath, 'assets/') ? substr($audioPath, strlen('assets/')) : $audioPath;
                $candidate = ASSETS_DIR . '/' . $audioPath;
                if (is_file($candidate)) {
                    $audioLength = (string) filesize($candidate);
                }
            }
            if ($audioLength === '' && $audioLengthInput !== '' && ctype_digit($audioLengthInput)) {
                $audioLength = $audioLengthInput;
            }
        } elseif ($type === 'Newsletter') {
            $category = '';
        }

        $relatedSlugs = [];
        if ($type === 'Entrada' || $type === 'Podcast') {
            $relatedSlugs = admin_parse_related_slugs_input($relatedSlugsInput);
            if ($relatedSlugsInput !== '' && count($relatedSlugs) < 2) {
                $error = 'Entradas o itinerarios relacionados: indica al menos 2 slugs válidos.';
            } elseif (count($relatedSlugs) > 8) {
                $error = 'Entradas o itinerarios relacionados: el máximo son 8 slugs.';
            }
        }

        $targetFilename = $normalizedFilename;
        $renameRequested = false;
        $slugPattern = '/^[a-z0-9-]+$/i';
        if ($newFilenameInput !== '' && !preg_match($slugPattern, $newFilenameInput)) {
            $error = 'El nuevo slug solo puede contener letras, números y guiones medios.';
        }
        if ($error === null && $newFilenameInput !== '') {
            $desiredSlug = nammu_slugify($newFilenameInput);
            if ($desiredSlug === '' && $title !== '') {
                $desiredSlug = nammu_slugify($title);
            }
            if ($desiredSlug === '' && $originalSlugNormalized !== '') {
                $desiredSlug = $originalSlugNormalized;
            }
            if ($desiredSlug === '') {
                $desiredSlug = 'entrada';
            }
            if ($desiredSlug === $originalSlugNormalized) {
                $newFilenameInput = '';
            } else {
                $candidateFilename = nammu_normalize_filename($desiredSlug . '.md');
                if ($candidateFilename === '') {
                    $error = 'El nombre de archivo proporcionado no es válido.';
                } elseif ($candidateFilename !== $normalizedFilename && file_exists(CONTENT_DIR . '/' . $candidateFilename)) {
                    $error = 'Ya existe otro contenido con ese nombre de archivo.';
                } else {
                    $targetFilename = $candidateFilename;
                    $renameRequested = $targetFilename !== $normalizedFilename;
                }
            }
        } elseif ($error === null && $newFilenameInput === '' && $normalizedFilename === '' && $title !== '') {
            $autoSlug = nammu_slugify($title);
            if ($autoSlug === '') {
                $autoSlug = 'entrada';
            }
            $targetFilename = nammu_unique_filename($autoSlug);
        }

        if ($targetFilename === '') {
            $error = 'No se pudo identificar el archivo a actualizar.';
        }

        if ($error === null) {
            $finalPath = CONTENT_DIR . '/' . $targetFilename;
            $publishAtValue = '';
            if ($status === 'draft' && $publishAtDate !== '') {
                $publishAtTime = $publishAtTime !== '' ? $publishAtTime : '00:00';
                $publishTimestamp = strtotime($publishAtDate . ' ' . $publishAtTime);
                if ($publishTimestamp !== false) {
                    $publishAtValue = date('Y-m-d H:i', $publishTimestamp);
                }
            }

            $file_content = "---
";
            $file_content .= "Title: " . $title . "
";
            $file_content .= "Template: " . $template . "
";
            $file_content .= "Category: " . $category . "
";
            $file_content .= "Date: " . $date . "
";
            $file_content .= "Image: " . $image . "
";
            $file_content .= "Description: " . $description . "
";
            if ($type === 'Podcast') {
                $file_content .= "Audio: " . $audio . "
";
                if ($video !== '') {
                    $file_content .= "Video: " . $video . "
";
                }
                if ($audioLength !== '') {
                    $file_content .= "AudioLength: " . $audioLength . "
";
                }
                if ($audioDuration !== '') {
                    $file_content .= "AudioDuration: " . $audioDuration . "
";
                }
            }
            if ($lang !== '') {
                $file_content .= "Lang: " . $lang . "
";
            }
            if ($template === 'page') {
                $file_content .= "Visibility: " . $pageVisibility . "
";
            }
            if (!empty($relatedSlugs) && in_array($template, ['post', 'podcast'], true)) {
                $file_content .= "Related: " . implode(', ', $relatedSlugs) . "
";
            }
            $fediverseIdToPreserve = $existingFediverseId;
            if ($fediverseIdToPreserve === ''
                && $renameRequested
                && $normalizedFilename !== ''
                && $previousStatus === 'published'
                && in_array($previousFediverseTemplate, ['post', 'page', 'podcast'], true)
            ) {
                $baseForFediverseId = function_exists('nammu_fediverse_base_url')
                    ? nammu_fediverse_base_url(load_config_file())
                    : rtrim(admin_base_url(), '/');
                $previousSlug = pathinfo($normalizedFilename, PATHINFO_FILENAME);
                if ($baseForFediverseId !== '' && $previousSlug !== '') {
                    $fediverseIdToPreserve = rtrim($baseForFediverseId, '/') . '/ap/objects/' . rawurlencode($previousFediverseTemplate) . '-' . rawurlencode($previousSlug);
                }
            }
            if ($fediverseIdToPreserve !== '') {
                $file_content .= "FediverseId: " . $fediverseIdToPreserve . "
";
            }
            $file_content .= "Status: " . $status . "
";
            if ($publishAtValue !== '') {
                $file_content .= "PublishAt: " . $publishAtValue . "
";
            }
            $file_content .= "Ordo: " . $ordo . "
";
            $file_content .= "---

";
            $file_content .= $content;

            $tempPath = tempnam(CONTENT_DIR, 'upd_');
            if ($tempPath === false) {
                $error = 'No se pudo crear un archivo temporal para actualizar el contenido.';
            } elseif (file_put_contents($tempPath, $file_content, LOCK_EX) === false) {
                @unlink($tempPath);
                $error = 'No se pudo guardar el contenido actualizado. Revisa los permisos de la carpeta content/.';
            } else {
                admin_chmod_content_file($tempPath);
                $writeSucceeded = false;
                if (@rename($tempPath, $finalPath)) {
                    $writeSucceeded = true;
                } else {
                    @unlink($tempPath);
                    if (file_put_contents($finalPath, $file_content, LOCK_EX) !== false) {
                        $writeSucceeded = true;
                    }
                }

                if ($writeSucceeded) {
                    admin_chmod_content_file($finalPath);
                    $shouldAutoShare = false;
                    $shouldAutoSharePodcast = false;
                    if ($template === 'post' && $status === 'published') {
                        if ($publishDraftAsEntry || $previousStatus === 'draft') {
                            $shouldAutoShare = true;
                        }
                    }
                    if ($template === 'podcast' && $status === 'published') {
                        if ($publishDraftAsPodcast || $previousStatus === 'draft') {
                            $shouldAutoSharePodcast = true;
                        }
                    }
                    if (
                        $status === 'published'
                        && $previousStatus === 'published'
                        && !$shouldAutoShare
                        && !$shouldAutoSharePodcast
                        && in_array($template, ['post', 'podcast', 'page'], true)
                    ) {
                        if (!function_exists('nammu_fediverse_mark_item_delivered_to_followers') && is_file(NAMMU_ROOT . '/core/fediverso.php')) {
                            require_once NAMMU_ROOT . '/core/fediverso.php';
                        }
                        if (function_exists('nammu_fediverse_mark_item_delivered_to_followers')) {
                            $fediverseConfig = load_config_file();
                            $fediverseObjectId = trim((string) ($fediverseIdToPreserve ?? ''));
                            if ($fediverseObjectId === '') {
                                $fediverseBaseUrl = function_exists('nammu_fediverse_base_url')
                                    ? nammu_fediverse_base_url($fediverseConfig)
                                    : rtrim(admin_base_url(), '/');
                                $fediverseSlug = pathinfo($targetFilename, PATHINFO_FILENAME);
                                if ($fediverseBaseUrl !== '' && $fediverseSlug !== '') {
                                    $fediverseObjectId = rtrim($fediverseBaseUrl, '/') . '/ap/objects/' . rawurlencode($template) . '-' . rawurlencode($fediverseSlug);
                                }
                            }
                            if ($fediverseObjectId !== '') {
                                nammu_fediverse_mark_item_delivered_to_followers($fediverseObjectId, $fediverseConfig);
                            }
                        }
                    }
                    if ($shouldAutoShare) {
                        $imageUrl = admin_public_asset_url($image);
                        admin_maybe_auto_post_to_social_networks($targetFilename, $title, $description, $image, '', $imageUrl);
                    }
                    if ($shouldAutoSharePodcast) {
                        $audioUrl = admin_public_asset_url($audio);
                        $imageUrl = admin_public_asset_url($image);
                        $slug = pathinfo($targetFilename, PATHINFO_FILENAME);
                        $podcastUrl = $slug !== '' ? admin_public_podcast_url($slug) : '';
                        if ($podcastUrl !== '') {
                            admin_maybe_auto_post_to_social_networks($targetFilename, $title, $description, $image, $podcastUrl, $imageUrl);
                        }
                        $settings = get_settings();
                        $mailing = $settings['mailing'] ?? [];
                        if (($mailing['auto_podcast'] ?? 'off') === 'on' && admin_is_mailing_ready($settings) && $audioUrl !== '') {
                            $subscribers = admin_mailing_recipients_for_type('podcast', $settings);
                            if (!empty($subscribers)) {
                                $payload = admin_prepare_mailing_payload('podcast', $settings, $title, $description, $audioUrl, $image);
                                admin_schedule_mailing_broadcast('podcast', $subscribers, [
                                    'filename' => $targetFilename,
                                    'slug' => $slug,
                                    'title' => $title,
                                    'description' => $description,
                                    'image' => $image,
                                    'audio' => $audio,
                                    'template' => 'podcast',
                                ]);
                            }
                        }
                    }
                    if ($shouldAutoShare) {
                        $settings = get_settings();
                        $mailing = $settings['mailing'] ?? [];
                        $slug = pathinfo($targetFilename, PATHINFO_FILENAME);
                        $link = admin_public_post_url($slug);
                        if (($mailing['auto_posts'] ?? 'off') === 'on' && admin_is_mailing_ready($settings)) {
                            $subscribers = admin_mailing_recipients_for_type('posts', $settings);
                            if (!empty($subscribers)) {
                                $payload = admin_prepare_mailing_payload('single', $settings, $title, $description, $link, $image);
                                admin_schedule_mailing_broadcast('post', $subscribers, [
                                    'filename' => $targetFilename,
                                    'slug' => $slug,
                                    'title' => $title,
                                    'description' => $description,
                                    'image' => $image,
                                    'template' => 'single',
                                ]);
                            }
                        }
                        admin_maybe_enqueue_push_notification('post', $title, $description, $link, $image);
                    }
                    if ($renameRequested && $normalizedFilename !== '' && $normalizedFilename !== $targetFilename) {
                        $previousPath = CONTENT_DIR . '/' . $normalizedFilename;
                        if ($previousPath !== $finalPath && is_file($previousPath)) {
                            @unlink($previousPath);
                        }
                    }
                    admin_regenerate_public_artifacts();
                    if ($status === 'published') {
                        $shouldIndexnow = $previousStatus === 'published' || $previousStatus === 'draft' || $publishDraftAsEntry || $publishDraftAsPage || $publishDraftAsPodcast || $renameRequested;
                        if ($shouldIndexnow) {
                            $indexnowUrls = [];
                            $slug = pathinfo($targetFilename, PATHINFO_FILENAME);
                            if (in_array($template, ['post', 'page'], true) && $slug !== '' && !($template === 'page' && $pageVisibility === 'private')) {
                                $indexnowUrls[] = admin_public_post_url($slug);
                            } elseif ($template === 'podcast') {
                                if ($slug !== '') {
                                    $indexnowUrls[] = admin_public_podcast_url($slug);
                                }
                            }
                            if (!empty($indexnowUrls)) {
                                admin_maybe_send_indexnow($indexnowUrls);
                            }
                        }
                    }
                    $redirectTemplate = $status === 'draft' ? 'draft' : ($template === 'page' ? 'page' : ($template === 'podcast' ? 'podcast' : ($template === 'newsletter' ? 'newsletter' : 'single')));
                    $feedbackMessage = 'Contenido actualizado correctamente.';
                    if ($publishDraftAsEntry) {
                        $feedbackMessage = 'Borrador publicado como entrada.';
                    } elseif ($publishDraftAsPage) {
                        $feedbackMessage = 'Borrador publicado como página.';
                    } elseif ($publishDraftAsPodcast) {
                        $feedbackMessage = 'Borrador publicado como podcast.';
                    } elseif ($convertToDraft) {
                        $feedbackMessage = 'Contenido pasado a borrador.';
                    }
                    if ($viewAfterSave) {
                        $slug = pathinfo($targetFilename, PATHINFO_FILENAME);
                        $viewUrl = '';
                        if ($slug !== '') {
                            if ($template === 'podcast') {
                                $viewUrl = admin_public_podcast_url($slug);
                            } elseif ($template === 'newsletter') {
                                $viewUrl = admin_public_newsletter_url($slug);
                            } elseif (in_array($template, ['post', 'page'], true)) {
                                $viewUrl = admin_public_post_url($slug);
                            }
                        }
                        if ($viewUrl !== '' && $status === 'draft') {
                            $viewUrl .= (str_contains($viewUrl, '?') ? '&' : '?') . 'preview=1';
                        }
                        if ($viewUrl !== '') {
                            header('Location: ' . $viewUrl);
                            exit;
                        }
                    }
                    $_SESSION['edit_feedback'] = [
                        'type' => 'success',
                        'message' => $feedbackMessage,
                    ];
                    header('Location: admin.php?page=edit-post&file=' . urlencode($targetFilename));
                    exit;
                } else {
                    $error = 'No se pudo guardar el contenido actualizado. Revisa los permisos de la carpeta content/.';
                }
            }
        }
} elseif (isset($_POST['delete_post'])) {
        $filename = $_POST['delete_filename'] ?? '';
        $filename = trim($filename);
        $templateTarget = $_POST['delete_template'] ?? 'single';
        $templateTarget = in_array($templateTarget, ['single', 'page', 'draft', 'newsletter', 'podcast', 'notes', 'news'], true) ? $templateTarget : 'single';
        $templateParam = urlencode($templateTarget);
        if ($filename !== '') {
            // Ensure only filenames from content directory are used
            $basename = basename($filename);
            $filepath = CONTENT_DIR . '/' . $basename;
            if (is_file($filepath)) {
                @unlink($filepath);
                admin_regenerate_public_artifacts();
                header('Location: admin.php?page=edit&template=' . $templateParam . '&deleted=' . urlencode($basename));
                exit;
            }
        }
        header('Location: admin.php?page=edit&template=' . $templateParam . '&deleted=0');
        exit;
} elseif (isset($_POST['recalculate_ordo'])) {
        $all_posts = get_all_posts_metadata();
        
        $single_posts = [];
        $postTemplates = ['single', 'post'];
        foreach ($all_posts as $post) {
            $templateValue = strtolower($post['metadata']['Template'] ?? '');
            if (!in_array($templateValue, $postTemplates, true)) {
                continue;
            }

            $date = $post['metadata']['Date'] ?? '01/01/1970';
            $dt = DateTime::createFromFormat('d/m/Y', $date);
            if ($dt) {
                $timestamp = $dt->getTimestamp();
            } else {
                $timestamp = strtotime($date);
            }
            if ($timestamp === false) {
                $timestamp = 0;
            }
            
            $single_posts[] = [
                'filename' => $post['filename'],
                'timestamp' => $timestamp,
            ];
        }

        usort($single_posts, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });

        $ordo = 1;
        foreach ($single_posts as $sorted_post) {
            $post_data = get_post_content($sorted_post['filename']);
            if ($post_data) {
                $post_data['metadata']['Ordo'] = $ordo;

                $file_content = "---
";
                foreach ($post_data['metadata'] as $key => $value) {
                    $file_content .= $key . ": " . $value . "
";
                }
                $file_content .= "---

";
                $file_content .= $post_data['content'];

                $sortedPath = CONTENT_DIR . '/' . $sorted_post['filename'];
                if (file_put_contents($sortedPath, $file_content, LOCK_EX) !== false) {
                    admin_chmod_content_file($sortedPath);
                }
                $ordo++;
            }
        }

        header('Location: admin.php?page=anuncios');
        exit;
}
