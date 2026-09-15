<?php
/**
 * Nammu — panel de administración. Acciones POST: Itinerarios formativos y sus temas: guardar, publicar, borrar, reordenar y reiniciar estadísticas.
 *
 * Se incluye desde admin.php dentro del ámbito global, una vez validado el token CSRF,
 * cuando la petición trae alguna de las claves de formulario de este grupo.
 */

if (isset($_POST['save_itinerary']) || isset($_POST['save_itinerary_view']) || isset($_POST['publish_itinerary'])) {
        $viewItineraryAfterSave = isset($_POST['save_itinerary_view']);
        $publishItineraryNow = isset($_POST['publish_itinerary']);
        $title = trim($_POST['itinerary_title'] ?? '');
        $description = trim($_POST['itinerary_description'] ?? '');
        $image = trim($_POST['itinerary_image'] ?? '');
        $content = $_POST['itinerary_content'] ?? '';
        $classChoice = $_POST['itinerary_class'] ?? '';
        $classCustom = $_POST['itinerary_class_custom'] ?? '';
        $itineraryQuizPayload = $_POST['itinerary_quiz_payload'] ?? '';
        $usageLogicInput = $_POST['itinerary_usage_logic'] ?? '';
        $statusInput = $_POST['itinerary_status'] ?? '';
        $slugInput = trim($_POST['itinerary_slug'] ?? '');
        $originalSlugInput = trim($_POST['itinerary_original_slug'] ?? '');
        $mode = $_POST['itinerary_mode'] ?? '';
        $orderInput = (int) ($_POST['itinerary_order'] ?? 0);
        $previousStatus = 'draft';
        if ($originalSlugInput !== '') {
            $existingItinerary = admin_load_itinerary($originalSlugInput);
            if ($existingItinerary) {
                $previousStatus = $existingItinerary->getStatus();
            }
        }
        if ($slugInput === '' && $title !== '') {
            $slugInput = $title;
        }
        $slug = ItineraryRepository::normalizeSlug($slugInput);
        $originalSlug = ItineraryRepository::normalizeSlug($originalSlugInput);
        $redirectBase = 'admin.php?page=itinerario';
        if ($originalSlug !== '') {
            $redirectBase .= '&itinerary=' . urlencode($originalSlug);
        } elseif ($mode === 'new') {
            $redirectBase .= '&new=1';
        }
        if ($title === '') {
            $_SESSION['itinerary_feedback'] = ['type' => 'danger', 'message' => 'El título del itinerario es obligatorio.'];
            header('Location: ' . $redirectBase);
            exit;
        }
        $itineraryQuizResult = admin_parse_quiz_payload($itineraryQuizPayload);
        if ($itineraryQuizResult['error'] !== null) {
            $_SESSION['itinerary_feedback'] = ['type' => 'danger', 'message' => $itineraryQuizResult['error']];
            header('Location: ' . $redirectBase);
            exit;
        }
        if ($slug === '') {
            $_SESSION['itinerary_feedback'] = ['type' => 'danger', 'message' => 'El slug del itinerario no es válido.'];
            header('Location: ' . $redirectBase);
            exit;
        }
        $targetDir = ITINERARIES_DIR . '/' . $slug;
        if ($originalSlug === '' && is_dir($targetDir)) {
            $_SESSION['itinerary_feedback'] = ['type' => 'danger', 'message' => 'Ya existe un itinerario con ese slug.'];
            header('Location: ' . $redirectBase);
            exit;
        }
        if ($originalSlug !== '' && $originalSlug !== $slug) {
            $originalDir = ITINERARIES_DIR . '/' . $originalSlug;
            if (is_dir($targetDir)) {
                $_SESSION['itinerary_feedback'] = ['type' => 'danger', 'message' => 'Ya existe un itinerario con el slug solicitado.'];
                header('Location: ' . $redirectBase);
                exit;
            }
            if (is_dir($originalDir)) {
                if (!@rename($originalDir, $targetDir)) {
                    $_SESSION['itinerary_feedback'] = ['type' => 'danger', 'message' => 'No se pudo renombrar la carpeta del itinerario.'];
                    header('Location: ' . $redirectBase);
                    exit;
                }
            } else {
                if (!is_dir($targetDir)) {
                    nammu_ensure_directory($targetDir);
                }
            }
        }
        if ($orderInput <= 0) {
            $orderInput = admin_next_itinerary_order();
        }
        try {
            $classLabel = admin_normalize_itinerary_class_label($classChoice, $classCustom);
            $usageLogic = admin_normalize_itinerary_usage_logic($usageLogicInput);
            $statusValue = $publishItineraryNow
                ? 'published'
                : (strtolower(trim((string) $statusInput)) === 'draft' ? 'draft' : 'published');
            $saved = admin_itinerary_repository()->saveItinerary($slug, [
                'Title' => $title,
                'Description' => $description,
                'Image' => $image,
                'ItineraryClass' => $classLabel,
                'UsageLogic' => $usageLogic,
                'Status' => $statusValue,
                'Order' => $orderInput,
            ], $content, !empty($itineraryQuizResult['data']['questions']) ? $itineraryQuizResult['data'] : null);
            admin_regenerate_public_artifacts();
            $shouldDispatchPublication = $publishItineraryNow || ($statusValue === 'published' && ($mode === 'new' || $previousStatus === 'draft'));
            $shouldAutoMail = $statusValue === 'published' && $shouldDispatchPublication;
            if ($shouldAutoMail) {
                $settings = get_settings();
                $mailing = $settings['mailing'] ?? [];
                if (($mailing['auto_itineraries'] ?? 'off') === 'on' && admin_is_mailing_ready($settings)) {
                    $subscribers = admin_mailing_recipients_for_type('itineraries', $settings);
                    if (!empty($subscribers)) {
                        $link = admin_public_itinerary_url($saved->getSlug());
                        $payload = admin_prepare_mailing_payload('itinerario', $settings, $title, $description, $link, $image);
                        admin_schedule_mailing_broadcast('itinerary', $subscribers, [
                            'slug' => $saved->getSlug(),
                            'title' => $title,
                            'description' => $description,
                            'image' => $image,
                            'template' => 'itinerario',
                        ]);
                    }
                }
                $link = admin_public_itinerary_url($saved->getSlug());
                admin_maybe_enqueue_push_notification('itinerary', $title, $description, $link, $image);
                $imageUrl = admin_public_asset_url($image);
                admin_maybe_auto_post_to_social_networks($saved->getSlug(), $title, $description, $image, $link, $imageUrl);
                admin_maybe_send_indexnow([$link]);
            }
            if ($viewItineraryAfterSave) {
                $itineraryUrl = admin_public_itinerary_url($saved->getSlug());
                if ($statusValue === 'draft') {
                    $itineraryUrl .= (str_contains($itineraryUrl, '?') ? '&' : '?') . 'preview=1';
                }
                header('Location: ' . $itineraryUrl);
                exit;
            }
            $_SESSION['itinerary_feedback'] = [
                'type' => 'success',
                'message' => $publishItineraryNow
                    ? 'Itinerario publicado y enviado a los canales automáticos configurados.'
                    : 'Itinerario guardado correctamente.',
            ];
            header('Location: admin.php?page=itinerario&itinerary=' . urlencode($saved->getSlug()));
            exit;
        } catch (Throwable $e) {
            $_SESSION['itinerary_feedback'] = ['type' => 'danger', 'message' => 'No se pudo guardar el itinerario: ' . $e->getMessage()];
            header('Location: ' . $redirectBase);
            exit;
        }
} elseif (isset($_POST['save_itinerary_topic']) || isset($_POST['save_itinerary_topic_add']) || isset($_POST['save_itinerary_topic_view'])) {
        $redirectToNewForm = isset($_POST['save_itinerary_topic_add']);
        $viewTopicAfterSave = isset($_POST['save_itinerary_topic_view']);
        $itinerarySlug = ItineraryRepository::normalizeSlug($_POST['topic_itinerary_slug'] ?? '');
        $title = trim($_POST['topic_title'] ?? '');
        $description = trim($_POST['topic_description'] ?? '');
        $image = trim($_POST['topic_image'] ?? '');
        $content = $_POST['topic_content'] ?? '';
        $numberRequested = (int) ($_POST['topic_number'] ?? 1);
        $slugInput = trim($_POST['topic_slug'] ?? '');
        $originalSlugInput = trim($_POST['topic_original_slug'] ?? '');
        $mode = $_POST['topic_mode'] ?? '';
        $quizPayload = $_POST['topic_quiz_payload'] ?? '';
        $quizResult = admin_parse_quiz_payload($quizPayload);
        if ($quizResult['error'] !== null) {
            $_SESSION['itinerary_feedback'] = ['type' => 'danger', 'message' => $quizResult['error']];
            header('Location: ' . $redirectBase);
            exit;
        }
        $quizData = $quizResult['data'];
        if ($slugInput === '' && $title !== '') {
            $slugInput = $title;
        }
        $topicSlug = ItineraryRepository::normalizeSlug($slugInput);
        $originalSlug = ItineraryRepository::normalizeSlug($originalSlugInput);
        $redirectBase = 'admin.php?page=itinerario-tema';
        if ($itinerarySlug !== '') {
            $redirectBase .= '&itinerary=' . urlencode($itinerarySlug);
        }
        if ($redirectToNewForm) {
            $redirectBase .= '&topic=new';
        } elseif ($mode === 'new') {
            $redirectBase .= '&topic=new';
        } elseif ($originalSlug !== '') {
            $redirectBase .= '&topic=' . urlencode($originalSlug);
        }
        if ($itinerarySlug === '') {
            $_SESSION['itinerary_feedback'] = ['type' => 'danger', 'message' => 'Selecciona un itinerario antes de añadir un tema.'];
            header('Location: ' . $redirectBase);
            exit;
        }
        if ($title === '') {
            $_SESSION['itinerary_feedback'] = ['type' => 'danger', 'message' => 'El título del tema es obligatorio.'];
            header('Location: ' . $redirectBase);
            exit;
        }
        if ($topicSlug === '') {
            $_SESSION['itinerary_feedback'] = ['type' => 'danger', 'message' => 'El slug del tema no es válido.'];
            header('Location: ' . $redirectBase);
            exit;
        }
        try {
            $repository = admin_itinerary_repository();
            $itinerary = $repository->find($itinerarySlug);
            if ($itinerary === null) {
                $_SESSION['itinerary_feedback'] = ['type' => 'danger', 'message' => 'El itinerario seleccionado no existe.'];
                header('Location: ' . $redirectBase);
                exit;
            }
            $topics = $itinerary->getTopics();
            $filteredTopics = [];
            foreach ($topics as $topic) {
                if ($topic->getSlug() === $topicSlug && $topicSlug !== $originalSlug) {
                    $_SESSION['itinerary_feedback'] = ['type' => 'danger', 'message' => 'Ya existe un tema con ese slug en el itinerario.'];
                    header('Location: ' . $redirectBase);
                    exit;
                }
                if ($topic->getSlug() !== $originalSlug) {
                    $filteredTopics[] = $topic;
                }
            }
            $position = max(1, $numberRequested);
            $maxPosition = count($filteredTopics) + 1;
            if ($position > $maxPosition) {
                $position = $maxPosition;
            }
            $sequence = [];
            $inserted = false;
            foreach ($filteredTopics as $existingTopic) {
                if (!$inserted && count($sequence) === $position - 1) {
                    $sequence[] = ['type' => 'new'];
                    $inserted = true;
                }
                $sequence[] = ['type' => 'existing', 'topic' => $existingTopic];
            }
            if (!$inserted) {
                $sequence[] = ['type' => 'new'];
            }
            if ($originalSlug !== '' && $originalSlug !== $topicSlug) {
                $oldFile = ITINERARIES_DIR . '/' . $itinerarySlug . '/' . $originalSlug . '.md';
                if (is_file($oldFile)) {
                    @unlink($oldFile);
                }
                $oldQuiz = ITINERARIES_DIR . '/' . $itinerarySlug . '/' . $originalSlug . '.quiz.json';
                if (is_file($oldQuiz)) {
                    @unlink($oldQuiz);
                }
            }
            $newSaved = false;
            foreach ($sequence as $index => $entry) {
                $number = $index + 1;
                if ($entry['type'] === 'new') {
                    if ($newSaved) {
                        continue;
                    }
                    $metadata = [
                        'Title' => $title,
                        'Description' => $description,
                        'Number' => $number,
                        'Image' => $image,
                    ];
                    $repository->saveTopic($itinerarySlug, $topicSlug, $metadata, $content, !empty($quizData['questions']) ? $quizData : null);
                    $newSaved = true;
                } else {
                    /** @var ItineraryTopic $existingTopic */
                    $existingTopic = $entry['topic'];
                    $metadata = $existingTopic->getMetadata();
                    $metadata['Number'] = $number;
                    $repository->saveTopic(
                        $itinerarySlug,
                        $existingTopic->getSlug(),
                        $metadata,
                        $existingTopic->getContent(),
                        $existingTopic->getQuiz()
                    );
                }
            }
            admin_regenerate_public_artifacts();
            if ($itinerary->isPublished()) {
                $topicUrl = admin_public_itinerary_url($itinerarySlug) . '/' . rawurlencode($topicSlug);
                admin_maybe_send_indexnow([$topicUrl]);
            }
            if ($viewTopicAfterSave) {
                $topicUrl = admin_public_itinerary_url($itinerarySlug) . '/' . rawurlencode($topicSlug);
                if ($itinerary->isDraft()) {
                    $topicUrl .= (str_contains($topicUrl, '?') ? '&' : '?') . 'preview=1';
                }
                header('Location: ' . $topicUrl);
                exit;
            }
            $_SESSION['itinerary_feedback'] = ['type' => 'success', 'message' => 'Tema guardado correctamente.'];
            if ($redirectToNewForm) {
                header('Location: admin.php?page=itinerario-tema&itinerary=' . urlencode($itinerarySlug) . '&topic=new');
            } else {
                header('Location: admin.php?page=itinerario-tema&itinerary=' . urlencode($itinerarySlug) . '&topic=' . urlencode($topicSlug));
            }
            exit;
        } catch (Throwable $e) {
            $_SESSION['itinerary_feedback'] = ['type' => 'danger', 'message' => 'No se pudo guardar el tema: ' . $e->getMessage()];
            header('Location: ' . $redirectBase);
            exit;
        }
} elseif (isset($_POST['delete_itinerary'])) {
        $slug = ItineraryRepository::normalizeSlug($_POST['delete_itinerary_slug'] ?? '');
        $redirectBase = 'admin.php?page=itinerarios';
        if ($slug === '') {
            $_SESSION['itinerary_feedback'] = ['type' => 'danger', 'message' => 'No se pudo borrar el itinerario seleccionado.'];
            header('Location: ' . $redirectBase);
            exit;
        }
        $targetDir = ITINERARIES_DIR . '/' . $slug;
        if (!is_dir($targetDir)) {
            $_SESSION['itinerary_feedback'] = ['type' => 'warning', 'message' => 'El itinerario ya no existe.'];
            header('Location: ' . $redirectBase);
            exit;
        }
        if (admin_recursive_delete_path($targetDir)) {
            $_SESSION['itinerary_feedback'] = ['type' => 'success', 'message' => 'Itinerario borrado correctamente.'];
            admin_regenerate_public_artifacts();
        } else {
            $_SESSION['itinerary_feedback'] = ['type' => 'danger', 'message' => 'No se pudo borrar la carpeta del itinerario. Revisa los permisos.'];
        }
        header('Location: ' . $redirectBase);
        exit;
} elseif (isset($_POST['delete_itinerary_topic'])) {
        $itinerarySlug = ItineraryRepository::normalizeSlug($_POST['delete_topic_itinerary_slug'] ?? '');
        $topicSlug = ItineraryRepository::normalizeSlug($_POST['delete_topic_slug'] ?? '');
        $redirectBase = 'admin.php?page=itinerario';
        if ($itinerarySlug !== '') {
            $redirectBase .= '&itinerary=' . urlencode($itinerarySlug);
        }
        if ($itinerarySlug === '' || $topicSlug === '') {
            $_SESSION['itinerary_feedback'] = ['type' => 'danger', 'message' => 'No se pudo borrar el tema seleccionado.'];
            header('Location: ' . $redirectBase);
            exit;
        }
        $filePath = ITINERARIES_DIR . '/' . $itinerarySlug . '/' . $topicSlug . '.md';
        if (!is_file($filePath)) {
            $_SESSION['itinerary_feedback'] = ['type' => 'warning', 'message' => 'El tema ya no existe en el itinerario.'];
            header('Location: ' . $redirectBase);
            exit;
        }
        if (@unlink($filePath)) {
            $_SESSION['itinerary_feedback'] = ['type' => 'success', 'message' => 'Tema borrado correctamente.'];
            admin_regenerate_public_artifacts();
        } else {
            $_SESSION['itinerary_feedback'] = ['type' => 'danger', 'message' => 'No se pudo borrar el archivo del tema. Revisa los permisos.'];
        }
        header('Location: ' . $redirectBase);
        exit;
} elseif (isset($_POST['reset_itinerary_stats'])) {
        $slug = ItineraryRepository::normalizeSlug($_POST['reset_stats_slug'] ?? '');
        $redirectBase = 'admin.php?page=itinerarios';
        if ($slug === '') {
            $_SESSION['itinerary_feedback'] = ['type' => 'danger', 'message' => 'No se pudo identificar el itinerario para reiniciar estadísticas.'];
            header('Location: ' . $redirectBase);
            exit;
        }
        $itinerary = admin_load_itinerary($slug);
        if ($itinerary === null) {
            $_SESSION['itinerary_feedback'] = ['type' => 'warning', 'message' => 'El itinerario solicitado ya no existe.'];
            header('Location: ' . $redirectBase);
            exit;
        }
        try {
            admin_itinerary_repository()->resetItineraryStats($slug);
            $_SESSION['itinerary_feedback'] = ['type' => 'success', 'message' => 'Las estadísticas del itinerario se pusieron a cero.'];
        } catch (Throwable $e) {
            $_SESSION['itinerary_feedback'] = ['type' => 'danger', 'message' => 'No se pudieron reiniciar las estadísticas: ' . $e->getMessage()];
        }
        header('Location: ' . $redirectBase);
        exit;
} elseif (isset($_POST['reorder_itinerary'])) {
        $slug = ItineraryRepository::normalizeSlug($_POST['itinerary_slug'] ?? '');
        $direction = $_POST['direction'] ?? '';
        $redirectBase = 'admin.php?page=itinerarios';
        if ($slug === '' || ($direction !== 'up' && $direction !== 'down')) {
            $_SESSION['itinerary_feedback'] = ['type' => 'warning', 'message' => 'No se pudo reordenar el itinerario.'];
            header('Location: ' . $redirectBase);
            exit;
        }
        try {
            $repo = admin_itinerary_repository();
            $list = $repo->all();
            $count = count($list);
            $index = null;
            foreach ($list as $i => $item) {
                if ($item->getSlug() === $slug) {
                    $index = $i;
                    break;
                }
            }
            if ($index === null) {
                $_SESSION['itinerary_feedback'] = ['type' => 'warning', 'message' => 'Itinerario no encontrado para reordenar.'];
                header('Location: ' . $redirectBase);
                exit;
            }
            $swapWith = null;
            if ($direction === 'up' && $index > 0) {
                $swapWith = $index - 1;
            } elseif ($direction === 'down' && $index < $count - 1) {
                $swapWith = $index + 1;
            }
            if ($swapWith === null) {
                header('Location: ' . $redirectBase);
                exit;
            }
            $current = $list[$index];
            $other = $list[$swapWith];
            $orderCurrent = (int) ($current->getMetadata()['Order'] ?? ($index + 1));
            $orderOther = (int) ($other->getMetadata()['Order'] ?? ($swapWith + 1));
            $metaCurrent = $current->getMetadata();
            $metaOther = $other->getMetadata();
            $metaCurrent['Order'] = $orderOther;
            $metaOther['Order'] = $orderCurrent;
            $repo->saveItinerary($current->getSlug(), $metaCurrent, $current->getContent(), $current->getQuiz());
            $repo->saveItinerary($other->getSlug(), $metaOther, $other->getContent(), $other->getQuiz());
            admin_regenerate_public_artifacts();
            $_SESSION['itinerary_feedback'] = ['type' => 'success', 'message' => 'Orden actualizado.'];
        } catch (Throwable $e) {
            $_SESSION['itinerary_feedback'] = ['type' => 'danger', 'message' => 'No se pudo reordenar: ' . $e->getMessage()];
        }
        header('Location: ' . $redirectBase);
        exit;
}
