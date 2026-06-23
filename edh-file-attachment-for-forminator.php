<?php
/**
 * Plugin Name:     EDH File Attachments for Forminator
 * Description:     Configure Media Library file attachments for Forminator form notification emails from a settings page.
 * Author URI:      https://encode.host
 * Text Domain:     edh-file-attachment-for-forminator
 * Domain Path:     /languages
 * Version:         1.0.0
 *
 * @package         EDH_Forminator_Attachments
 */

if (!defined('ABSPATH')) {
    exit;
}

define('EDH_FORMINATOR_ATTACHMENTS_FILE', __FILE__);
define('EDH_FORMINATOR_ATTACHMENTS_DIR', plugin_dir_path(__FILE__));
define('EDH_FORMINATOR_ATTACHMENTS_URL', plugin_dir_url(__FILE__));

add_action('plugins_loaded', 'edh_forminator_attachments_init');

function edh_forminator_attachments_init()
{
    if (!class_exists('Forminator_API')) {
        add_action('admin_notices', 'edh_forminator_attachments_missing_notice');
        return;
    }

    require_once EDH_FORMINATOR_ATTACHMENTS_DIR . 'includes/class-edh-forminator-attachments.php';

    EDH_Forminator_Attachments::instance();
}

function edh_forminator_attachments_missing_notice()
{
    if (!current_user_can('activate_plugins')) {
        return;
    }
    ?>
    <div class="notice notice-error">
        <p>
            <?php esc_html_e('EDH File Attachments for Forminator requires the Forminator plugin to be installed and active.', 'edh-file-attachment-for-forminator'); ?>
        </p>
    </div>
    <?php
}
