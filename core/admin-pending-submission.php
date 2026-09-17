<?php
/**
 * Nammu — panel de administración. Envío pendiente del editor.
 *
 * Si la sesión de PHP ha caducado mientras el autor escribía y llega el POST de Publicar / Guardar como borrador /
 * Actualizar, el token CSRF ya no vale y el panel muestra la pantalla de acceso. Para no perder el texto, el envío se
 * guarda en la sesión nueva y, en cuanto el autor vuelve a iniciar sesión, se escribe como borrador y se abre en el
 * editor. Nunca se publica ni se sobrescribe un archivo existente con un envío que no pudo autenticarse: si venía del
 * editor de una entrada ya guardada, los cambios van a un borrador aparte y el original queda intacto.
 *
 * Se carga desde admin.php; lo usan admin.php (stash), core/admin-actions-auth.php (restore) y las plantillas de acceso.
 */

const NAMMU_PENDING_SUBMISSION_KEY = 'nammu_pending_submission';
const NAMMU_PENDING_SUBMISSION_MAX_BYTES = 4 * 1024 * 1024;

/** Claves de formulario del editor de entradas cuyo envío merece conservarse. */
function admin_pending_submission_keys(): array
{
    return ['publish', 'save_draft', 'publish_and_view', 'update', 'update_and_view'];
}

/**
 * Guarda en la sesión el POST de un editor que llegó sin sesión iniciada. Devuelve true si había algo que conservar.
 */
function admin_pending_submission_stash(array $post): bool
{
    $isEditorSubmission = false;
    foreach (admin_pending_submission_keys() as $key) {
        if (isset($post[$key])) {
            $isEditorSubmission = true;
            break;
        }
    }
    if (!$isEditorSubmission) {
        return false;
    }
    $fields = [];
    foreach ($post as $name => $value) {
        if ($name === '_nammu_csrf' || !is_string($value)) {
            continue;
        }
        $fields[(string) $name] = $value;
    }
    $hasText = false;
    foreach (['title', 'description', 'content'] as $name) {
        if (trim((string) ($fields[$name] ?? '')) !== '') {
            $hasText = true;
            break;
        }
    }
    if (!$hasText) {
        return false;
    }
    $pending = [
        'context' => (isset($post['update']) || isset($post['update_and_view'])) ? 'edit' : 'publish',
        'fields' => $fields,
        'created_at' => time(),
    ];
    $encoded = json_encode($pending, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($encoded === false || strlen($encoded) > NAMMU_PENDING_SUBMISSION_MAX_BYTES) {
        return false;
    }
    $_SESSION[NAMMU_PENDING_SUBMISSION_KEY] = $pending;
    return true;
}

function admin_pending_submission_pending(): bool
{
    $pending = $_SESSION[NAMMU_PENDING_SUBMISSION_KEY] ?? null;
    return is_array($pending) && is_array($pending['fields'] ?? null);
}

/** Saca el envío pendiente de la sesión (y lo borra de ella). */
function admin_pending_submission_take(): ?array
{
    if (!admin_pending_submission_pending()) {
        return null;
    }
    $pending = $_SESSION[NAMMU_PENDING_SUBMISSION_KEY];
    unset($_SESSION[NAMMU_PENDING_SUBMISSION_KEY]);
    return $pending;
}

/**
 * Escribe el envío pendiente como borrador. Devuelve ['saved' => bool, 'filename' => string, 'message' => string].
 */
function admin_pending_submission_restore(array $pending): array
{
    $fields = is_array($pending['fields'] ?? null) ? $pending['fields'] : [];
    $context = ($pending['context'] ?? '') === 'edit' ? 'edit' : 'publish';
    $title = trim((string) ($fields['title'] ?? ''));
    $titleLabel = $title !== '' ? '«' . $title . '»' : 'el texto';
    $fields['status'] = 'draft';
    $originalFilename = '';
    if ($context === 'edit') {
        // Copia aparte: el original no se toca con un envío que llegó sin sesión.
        $originalFilename = nammu_normalize_filename((string) ($fields['filename'] ?? ''));
        $baseSlug = preg_replace('/\.md$/i', '', $originalFilename);
        $baseSlug = nammu_slugify((string) $baseSlug);
        $fields['filename'] = nammu_unique_filename(($baseSlug !== '' ? $baseSlug : 'borrador') . '-recuperado');
    } else {
        $slugCandidate = trim((string) ($fields['filename'] ?? ''));
        if ($slugCandidate === '' || !preg_match('/^[a-z0-9-]+$/i', $slugCandidate)) {
            $fields['filename'] = '';
        }
    }
    $result = admin_autosave_from_payload(json_encode(['context' => 'publish', 'fields' => $fields], JSON_UNESCAPED_UNICODE));
    if (!empty($result['saved']) && ($result['filename'] ?? '') !== '') {
        $result['message'] = $context === 'edit'
            ? "Tu sesión había caducado al actualizar {$titleLabel}. Para no perder los cambios se han guardado en este borrador aparte ({$result['filename']}); la entrada original ({$originalFilename}) no se ha tocado."
            : "Tu sesión había caducado al guardar {$titleLabel}. El texto se ha guardado como borrador; revísalo y publícalo cuando quieras.";
        return $result;
    }
    $result['saved'] = false;
    $result['message'] = "Tu sesión había caducado al guardar {$titleLabel} y no se pudo escribir el borrador"
        . (($result['message'] ?? '') !== '' ? ' (' . $result['message'] . ')' : '')
        . '. Si este navegador guardó una copia local, el editor te ofrecerá recuperarla.';
    return $result;
}
