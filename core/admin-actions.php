<?php
/**
 * Nammu — panel de administración. Despachador de acciones POST.
 *
 * admin.php históricamente resolvía cada envío con una cadena if/elseif de 60 ramas sobre
 * isset($_POST['clave']). Ahora cada grupo de ramas vive en core/admin-actions-<grupo>.php y
 * este mapa, en el MISMO orden que tenía la cadena, decide qué fichero incluir: el primero
 * cuya clave venga en la petición. Dentro del fichero se conserva la cadena original.
 */

function admin_action_files(): array
{
    return [
        'register' => 'admin-actions-auth.php',
        'login' => 'admin-actions-auth.php',
        'logout' => 'admin-actions-auth.php',
        'send_newsletter' => 'admin-actions-content.php',
        'publish' => 'admin-actions-content.php',
        'save_draft' => 'admin-actions-content.php',
        'publish_and_view' => 'admin-actions-content.php',
        'resend_newsletter_edit' => 'admin-actions-content.php',
        'send_newsletter_custom_edit' => 'admin-actions-content.php',
        'send_newsletter_edit' => 'admin-actions-content.php',
        'update' => 'admin-actions-content.php',
        'update_and_view' => 'admin-actions-content.php',
        'publish_draft_entry' => 'admin-actions-content.php',
        'publish_draft_page' => 'admin-actions-content.php',
        'publish_draft_podcast' => 'admin-actions-content.php',
        'convert_to_draft' => 'admin-actions-content.php',
        'send_social_post' => 'admin-actions-social.php',
        'send_social_actuality' => 'admin-actions-social.php',
        'send_social_itinerary' => 'admin-actions-social.php',
        'save_itinerary' => 'admin-actions-itineraries.php',
        'save_itinerary_view' => 'admin-actions-itineraries.php',
        'publish_itinerary' => 'admin-actions-itineraries.php',
        'save_itinerary_topic' => 'admin-actions-itineraries.php',
        'save_itinerary_topic_add' => 'admin-actions-itineraries.php',
        'save_itinerary_topic_view' => 'admin-actions-itineraries.php',
        'delete_itinerary' => 'admin-actions-itineraries.php',
        'delete_itinerary_topic' => 'admin-actions-itineraries.php',
        'reset_itinerary_stats' => 'admin-actions-itineraries.php',
        'update_actuality_note' => 'admin-actions-actuality.php',
        'update_actuality_news' => 'admin-actions-actuality.php',
        'delete_actuality_note' => 'admin-actions-actuality.php',
        'delete_actuality_news' => 'admin-actions-actuality.php',
        'delete_post' => 'admin-actions-content.php',
        'reorder_itinerary' => 'admin-actions-itineraries.php',
        'upload_asset' => 'admin-actions-media.php',
        'save_edited_image' => 'admin-actions-media.php',
        'update_image_tags' => 'admin-actions-media.php',
        'delete_tag_global' => 'admin-actions-media.php',
        'delete_asset' => 'admin-actions-media.php',
        'recalculate_ordo' => 'admin-actions-content.php',
        'test_gsc' => 'admin-actions-settings.php',
        'test_bing' => 'admin-actions-settings.php',
        'save_backup_settings' => 'admin-actions-settings.php',
        'restore_stats_backup' => 'admin-actions-settings.php',
        'save_rejected_origins' => 'admin-actions-settings.php',
        'save_settings' => 'admin-actions-settings.php',
        'save_machine_files' => 'admin-actions-settings.php',
        'save_contact' => 'admin-actions-settings.php',
        'save_google_fonts' => 'admin-actions-settings.php',
        'save_nisaba' => 'admin-actions-settings.php',
        'save_telex' => 'admin-actions-settings.php',
        'save_social' => 'admin-actions-social.php',
        'save_mailing' => 'admin-actions-mailing.php',
        'add_subscriber' => 'admin-actions-mailing.php',
        'remove_subscriber' => 'admin-actions-mailing.php',
        'save_mailing_flags' => 'admin-actions-mailing.php',
        'save_ads_settings' => 'admin-actions-settings.php',
        'save_push_settings' => 'admin-actions-settings.php',
        'save_indexnow_settings' => 'admin-actions-settings.php',
        'save_postal_settings' => 'admin-actions-postal.php',
        'postal_update' => 'admin-actions-postal.php',
        'postal_delete' => 'admin-actions-postal.php',
        'download_postal_csv' => 'admin-actions-postal.php',
        'download_postal_pdf' => 'admin-actions-postal.php',
        'import_postal_csv' => 'admin-actions-postal.php',
        'send_mailing_post' => 'admin-actions-mailing.php',
        'update_account' => 'admin-actions-auth.php',
        'save_template' => 'admin-actions-settings.php',
    ];
}

/**
 * Devuelve el fichero de core/ que atiende la petición POST, o '' si ninguna clave coincide.
 */
function admin_action_file_for_request(array $post): string
{
    foreach (admin_action_files() as $key => $file) {
        if (isset($post[$key])) {
            return $file;
        }
    }
    return '';
}
