<?php
/**
 * Nammu — panel de administración. Estado de la petición para las vistas: mensajes y formularios de Redes/Publicar y del
 * Fediverso recuperados de la sesión tras una redirección. Se incluye desde admin.php (ámbito global).
 */
$socialBroadcastFeedback = null;
$socialBroadcastText = '';
$socialBroadcastImage = '';
$socialBroadcastActuality = false;
$socialBroadcastNetworks = [];
$fediverseFeedback = null;
$fediverseActorInput = '';
$fediverseMessageRecipient = '';
$fediverseMessageText = '';
$fediverseInspectUrl = '';
$fediverseInspectResult = null;
$fediverseRedirect = false;
$fediverseRedirectState = [];
$notesFeedback = $_SESSION['notes_feedback'] ?? null;
if ($notesFeedback !== null) {
    unset($_SESSION['notes_feedback']);
}
$newsFeedback = $_SESSION['news_feedback'] ?? null;
if ($newsFeedback !== null) {
    unset($_SESSION['news_feedback']);
}
if (!empty($_SESSION['fediverse_feedback'])) {
    $fediverseFeedback = is_array($_SESSION['fediverse_feedback']) ? $_SESSION['fediverse_feedback'] : null;
    unset($_SESSION['fediverse_feedback']);
}
if (!empty($_SESSION['fediverse_state']) && is_array($_SESSION['fediverse_state'])) {
    $fediverseRedirectState = $_SESSION['fediverse_state'];
    unset($_SESSION['fediverse_state']);
    $fediverseActorInput = (string) ($fediverseRedirectState['actor_input'] ?? $fediverseActorInput);
    $fediverseMessageRecipient = (string) ($fediverseRedirectState['message_recipient'] ?? $fediverseMessageRecipient);
    $fediverseMessageText = (string) ($fediverseRedirectState['message_text'] ?? $fediverseMessageText);
}
if ($isLoggedIn && $page === 'publish') {
    require_once NAMMU_ROOT . '/core/admin-redes.php';
    if (!empty($_SESSION['social_broadcast_feedback'])) {
        $socialBroadcastFeedback = is_array($_SESSION['social_broadcast_feedback']) ? $_SESSION['social_broadcast_feedback'] : null;
        unset($_SESSION['social_broadcast_feedback']);
    }
    if (!empty($_SESSION['social_broadcast_state']) && is_array($_SESSION['social_broadcast_state'])) {
        $socialBroadcastState = $_SESSION['social_broadcast_state'];
        unset($_SESSION['social_broadcast_state']);
        $socialBroadcastText = (string) ($socialBroadcastState['message_text'] ?? '');
        $socialBroadcastImage = (string) ($socialBroadcastState['image'] ?? '');
        $socialBroadcastActuality = !empty($socialBroadcastState['actuality']);
        $socialBroadcastNetworks = is_array($socialBroadcastState['networks'] ?? null) ? $socialBroadcastState['networks'] : [];
    }
}
