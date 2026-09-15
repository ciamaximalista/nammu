<?php
/**
 * Nammu — panel de administración. Acciones POST: Notas manuales y noticias agregadas de Actualidad (perfil del Fediverso).
 *
 * Se incluye desde admin.php dentro del ámbito global, una vez validado el token CSRF,
 * cuando la petición trae alguna de las claves de formulario de este grupo.
 */

if (isset($_POST['update_actuality_note'])) {
        if (!function_exists('nammu_actuality_update_manual_item') && is_file(NAMMU_ROOT . '/core/actualidad.php')) {
            require_once NAMMU_ROOT . '/core/actualidad.php';
        }
        $noteId = preg_replace('/[^a-f0-9]/i', '', (string) ($_POST['note_id'] ?? '')) ?? '';
        $noteText = trim((string) ($_POST['note_text'] ?? ''));
        $noteImagesRaw = trim((string) ($_POST['note_images'] ?? ''));
        $noteImages = array_values(array_filter(array_map('trim', preg_split('/\R+/', $noteImagesRaw) ?: [])));
        if ($noteId === '') {
            $_SESSION['notes_feedback'] = ['type' => 'danger', 'message' => 'No se pudo identificar la nota.'];
            header('Location: admin.php?page=edit&template=notes');
            exit;
        }
        if ($noteText === '') {
            $_SESSION['notes_feedback'] = ['type' => 'danger', 'message' => 'La nota no puede estar vacía.'];
            header('Location: admin.php?page=edit-note&id=' . urlencode($noteId));
            exit;
        }
        $config = load_config_file();
        $siteTitle = trim((string) (($config['site_name'] ?? '') ?: 'Nammu Blog'));
        $siteDescription = trim((string) (($config['site_description'] ?? '') ?: ''));
        $siteLang = trim((string) (($config['site_lang'] ?? '') ?: 'es'));
        $baseUrl = trim((string) ($config['site_url'] ?? ''));
        if ($baseUrl === '') {
            $baseUrl = nammu_base_url();
        }
        if (!function_exists('nammu_actuality_update_manual_item') || !nammu_actuality_update_manual_item($noteId, $noteText, $baseUrl, $siteTitle, $noteImages[0] ?? '', $noteImages)) {
            $_SESSION['notes_feedback'] = ['type' => 'danger', 'message' => 'No se pudo actualizar la nota.'];
            header('Location: admin.php?page=edit-note&id=' . urlencode($noteId));
            exit;
        }
        if (function_exists('nammu_actuality_replace_manual_item_in_snapshots')) {
            nammu_actuality_replace_manual_item_in_snapshots($noteId);
        } elseif (function_exists('nammu_actuality_rebuild_snapshot')) {
            nammu_actuality_rebuild_snapshot($baseUrl, $config, $siteTitle, $siteDescription, $siteLang);
        }
        if (function_exists('nammu_fediverse_save_fragments_cache_store')) {
            nammu_fediverse_save_fragments_cache_store([]);
        }
        $fediverseUpdateDelivered = null;
        if (!function_exists('nammu_fediverse_notify_followers_of_object_update') && is_file(NAMMU_ROOT . '/core/fediverso.php')) {
            require_once NAMMU_ROOT . '/core/fediverso.php';
        }
        if (function_exists('nammu_fediverse_notify_followers_of_object_update') && function_exists('nammu_fediverse_local_content_items')) {
            $fediverseItemId = rtrim($baseUrl, '/') . '/ap/objects/actualidad-' . rawurlencode($noteId);
            foreach (nammu_fediverse_local_content_items($config) as $localItem) {
                if (trim((string) ($localItem['id'] ?? '')) !== $fediverseItemId) {
                    continue;
                }
                $fediverseUpdateDelivered = nammu_fediverse_notify_followers_of_object_update($localItem, $config);
                break;
            }
        }
        $message = 'Nota actualizada.';
        if ($fediverseUpdateDelivered !== null) {
            $message .= ' Update federado: ' . (int) $fediverseUpdateDelivered . ' entrega' . ((int) $fediverseUpdateDelivered === 1 ? '' : 's') . '.';
        }
        $_SESSION['notes_feedback'] = ['type' => 'success', 'message' => $message];
        header('Location: admin.php?page=edit&template=notes');
        exit;
} elseif (isset($_POST['update_actuality_news'])) {
        if (!function_exists('nammu_actuality_update_news_item') && is_file(NAMMU_ROOT . '/core/actualidad.php')) {
            require_once NAMMU_ROOT . '/core/actualidad.php';
        }
        $newsId = preg_replace('/[^a-f0-9]/i', '', (string) ($_POST['news_id'] ?? '')) ?? '';
        $newsTitle = trim((string) ($_POST['news_title'] ?? ''));
        $newsText = trim((string) ($_POST['news_text'] ?? ''));
        $newsLink = trim((string) ($_POST['news_link'] ?? ''));
        $newsImagesRaw = trim((string) ($_POST['news_images'] ?? ''));
        $newsImages = array_values(array_filter(array_map('trim', preg_split('/\R+/', $newsImagesRaw) ?: [])));
        if ($newsId === '') {
            $_SESSION['news_feedback'] = ['type' => 'danger', 'message' => 'No se pudo identificar la noticia.'];
            header('Location: admin.php?page=edit&template=news');
            exit;
        }
        if ($newsTitle === '' || $newsText === '' || $newsLink === '') {
            $_SESSION['news_feedback'] = ['type' => 'danger', 'message' => 'La noticia debe tener título, texto y enlace.'];
            header('Location: admin.php?page=edit-news&id=' . urlencode($newsId));
            exit;
        }
        $config = load_config_file();
        $siteTitle = trim((string) (($config['site_name'] ?? '') ?: 'Nammu Blog'));
        $siteDescription = trim((string) (($config['site_description'] ?? '') ?: ''));
        $siteLang = trim((string) (($config['site_lang'] ?? '') ?: 'es'));
        $baseUrl = trim((string) ($config['site_url'] ?? ''));
        if ($baseUrl === '') {
            $baseUrl = nammu_base_url();
        }
        if (!function_exists('nammu_actuality_update_news_item') || !nammu_actuality_update_news_item($newsId, $newsTitle, $newsText, $newsLink, $baseUrl, $newsImages[0] ?? '', $newsImages)) {
            $_SESSION['news_feedback'] = ['type' => 'danger', 'message' => 'No se pudo actualizar la noticia.'];
            header('Location: admin.php?page=edit-news&id=' . urlencode($newsId));
            exit;
        }
        if (function_exists('nammu_actuality_replace_news_item_in_snapshots')) {
            nammu_actuality_replace_news_item_in_snapshots($newsId, $baseUrl, $config, $siteTitle, $siteDescription, $siteLang);
        } elseif (function_exists('nammu_actuality_rebuild_snapshot')) {
            nammu_actuality_rebuild_snapshot($baseUrl, $config, $siteTitle, $siteDescription, $siteLang);
        }
        $fediverseUpdateDelivered = null;
        if (!function_exists('nammu_fediverse_notify_followers_of_object_update') && is_file(NAMMU_ROOT . '/core/fediverso.php')) {
            require_once NAMMU_ROOT . '/core/fediverso.php';
        }
        if (function_exists('nammu_fediverse_notify_followers_of_object_update') && function_exists('nammu_fediverse_local_content_items')) {
            $fediverseItemId = rtrim($baseUrl, '/') . '/ap/objects/actualidad-' . rawurlencode($newsId);
            foreach (nammu_fediverse_local_content_items($config) as $localItem) {
                if (trim((string) ($localItem['id'] ?? '')) !== $fediverseItemId) {
                    continue;
                }
                $fediverseUpdateDelivered = nammu_fediverse_notify_followers_of_object_update($localItem, $config);
                break;
            }
        }
        $message = 'Noticia actualizada.';
        if ($fediverseUpdateDelivered !== null) {
            $message .= ' Update federado: ' . (int) $fediverseUpdateDelivered . ' entrega' . ((int) $fediverseUpdateDelivered === 1 ? '' : 's') . '.';
        }
        $_SESSION['news_feedback'] = ['type' => 'success', 'message' => $message];
        header('Location: admin.php?page=edit&template=news');
        exit;
} elseif (isset($_POST['delete_actuality_note'])) {
        if (!function_exists('nammu_actuality_delete_manual_item') && is_file(NAMMU_ROOT . '/core/actualidad.php')) {
            require_once NAMMU_ROOT . '/core/actualidad.php';
        }
        if (!function_exists('nammu_fediverse_enqueue_delete_local_item') && is_file(NAMMU_ROOT . '/core/fediverso.php')) {
            require_once NAMMU_ROOT . '/core/fediverso.php';
        }
        $noteId = preg_replace('/[^a-f0-9]/i', '', (string) ($_POST['delete_note_id'] ?? '')) ?? '';
        if ($noteId === '') {
            $_SESSION['notes_feedback'] = ['type' => 'danger', 'message' => 'No se pudo identificar la nota para borrarla.'];
            header('Location: admin.php?page=edit&template=notes');
            exit;
        }
        $config = load_config_file();
        $baseUrl = trim((string) ($config['site_url'] ?? ''));
        if ($baseUrl === '') {
            $baseUrl = nammu_base_url();
        }
        $deleteMessages = [];
        if (function_exists('nammu_fediverse_enqueue_delete_local_item')) {
            $fediverseItemId = rtrim($baseUrl, '/') . '/ap/objects/actualidad-' . rawurlencode($noteId);
            $fediverseDelete = nammu_fediverse_enqueue_delete_local_item($fediverseItemId, $config);
            if (!empty($fediverseDelete['message'])) {
                $deleteMessages[] = trim((string) $fediverseDelete['message']);
            }
        }
        if (!function_exists('nammu_actuality_delete_manual_item') || !nammu_actuality_delete_manual_item($noteId)) {
            $_SESSION['notes_feedback'] = ['type' => 'warning', 'message' => 'La nota ya no existe o no se pudo borrar.'];
            header('Location: admin.php?page=edit&template=notes');
            exit;
        }
        if (function_exists('nammu_actuality_remove_manual_item_from_snapshots')) {
            nammu_actuality_remove_manual_item_from_snapshots($noteId);
        }
        if (function_exists('nammu_fediverse_remove_local_item_from_home_snapshot')) {
            $fediverseItemId = rtrim($baseUrl, '/') . '/ap/objects/actualidad-' . rawurlencode($noteId);
            nammu_fediverse_remove_local_item_from_home_snapshot($fediverseItemId);
        }
        if (function_exists('nammu_fediverse_save_fragments_cache_store')) {
            nammu_fediverse_save_fragments_cache_store([]);
        }
        $_SESSION['notes_feedback'] = ['type' => 'success', 'message' => trim('Nota borrada. ' . implode(' ', array_filter($deleteMessages)))];
        header('Location: admin.php?page=edit&template=notes');
        exit;
} elseif (isset($_POST['delete_actuality_news'])) {
        if (!function_exists('nammu_actuality_delete_news_item') && is_file(NAMMU_ROOT . '/core/actualidad.php')) {
            require_once NAMMU_ROOT . '/core/actualidad.php';
        }
        if (!function_exists('nammu_fediverse_enqueue_delete_local_item') && is_file(NAMMU_ROOT . '/core/fediverso.php')) {
            require_once NAMMU_ROOT . '/core/fediverso.php';
        }
        $newsId = preg_replace('/[^a-f0-9]/i', '', (string) ($_POST['delete_news_id'] ?? '')) ?? '';
        if ($newsId === '') {
            $_SESSION['news_feedback'] = ['type' => 'danger', 'message' => 'No se pudo identificar la noticia para borrarla.'];
            header('Location: admin.php?page=edit&template=news');
            exit;
        }
        $config = load_config_file();
        $siteTitle = trim((string) (($config['site_name'] ?? '') ?: 'Nammu Blog'));
        $siteDescription = trim((string) (($config['site_description'] ?? '') ?: ''));
        $siteLang = trim((string) (($config['site_lang'] ?? '') ?: 'es'));
        $baseUrl = trim((string) ($config['site_url'] ?? ''));
        if ($baseUrl === '') {
            $baseUrl = nammu_base_url();
        }
        $deleteMessages = [];
        if (function_exists('nammu_fediverse_enqueue_delete_local_item')) {
            $fediverseItemId = rtrim($baseUrl, '/') . '/ap/objects/actualidad-' . rawurlencode($newsId);
            $fediverseDelete = nammu_fediverse_enqueue_delete_local_item($fediverseItemId, $config);
            if (!empty($fediverseDelete['message'])) {
                $deleteMessages[] = trim((string) $fediverseDelete['message']);
            }
        }
        if (!function_exists('nammu_actuality_delete_news_item') || !nammu_actuality_delete_news_item($newsId)) {
            $_SESSION['news_feedback'] = ['type' => 'warning', 'message' => 'La noticia ya no existe o no se pudo borrar.'];
            header('Location: admin.php?page=edit&template=news');
            exit;
        }
        if (function_exists('nammu_actuality_remove_news_item_from_snapshots')) {
            nammu_actuality_remove_news_item_from_snapshots($newsId);
        }
        if (function_exists('nammu_fediverse_remove_local_item_from_home_snapshot')) {
            $fediverseItemId = rtrim($baseUrl, '/') . '/ap/objects/actualidad-' . rawurlencode($newsId);
            nammu_fediverse_remove_local_item_from_home_snapshot($fediverseItemId);
        }
        if (function_exists('nammu_fediverse_save_fragments_cache_store')) {
            nammu_fediverse_save_fragments_cache_store([]);
        }
        $_SESSION['news_feedback'] = ['type' => 'success', 'message' => trim('Noticia borrada. ' . implode(' ', array_filter($deleteMessages)))];
        header('Location: admin.php?page=edit&template=news');
        exit;
}
