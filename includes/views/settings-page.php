<?php
/**
 * Settings page view.
 *
 * Expects $rules, $forms, $edit_rule and $this (EDH_Forminator_Attachments) in scope.
 *
 * @package EDH_Forminator_Attachments
 */

if (!defined('ABSPATH')) {
    exit;
}

$message = isset($_GET['edh_message']) ? sanitize_key(wp_unslash($_GET['edh_message'])) : '';

$edit_attachment_names = array();
if ($edit_rule) {
    foreach ($edit_rule['attachment_ids'] as $attachment_id) {
        $title = get_the_title($attachment_id);
        if ($title) {
            $edit_attachment_names[$attachment_id] = $title;
        }
    }
}
?>
<div class="wrap">
    <h1><?php esc_html_e('Forminator Attachments', 'edh-file-attachment-for-forminator'); ?></h1>

    <?php if ('saved' === $message) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Rule saved.', 'edh-file-attachment-for-forminator'); ?></p></div>
    <?php elseif ('deleted' === $message) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Rule deleted.', 'edh-file-attachment-for-forminator'); ?></p></div>
    <?php elseif ('invalid' === $message) : ?>
        <div class="notice notice-error is-dismissible"><p><?php esc_html_e('Please choose a form, an email and at least one file.', 'edh-file-attachment-for-forminator'); ?></p></div>
    <?php endif; ?>

    <?php if (empty($forms)) : ?>
        <div class="notice notice-warning"><p><?php esc_html_e('No Forminator forms were found.', 'edh-file-attachment-for-forminator'); ?></p></div>
    <?php endif; ?>

    <h2><?php esc_html_e('Existing Rules', 'edh-file-attachment-for-forminator'); ?></h2>
    <table class="widefat striped">
        <thead>
            <tr>
                <th><?php esc_html_e('Form', 'edh-file-attachment-for-forminator'); ?></th>
                <th><?php esc_html_e('Recipient Email', 'edh-file-attachment-for-forminator'); ?></th>
                <th><?php esc_html_e('Attached Files', 'edh-file-attachment-for-forminator'); ?></th>
                <th><?php esc_html_e('Actions', 'edh-file-attachment-for-forminator'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rules)) : ?>
                <tr>
                    <td colspan="4"><?php esc_html_e('No rules configured yet.', 'edh-file-attachment-for-forminator'); ?></td>
                </tr>
            <?php else : ?>
                <?php foreach ($rules as $rule) : ?>
                    <tr>
                        <td><?php echo esc_html($this->get_form_name_by_id($rule['form_id'])); ?> (#<?php echo (int) $rule['form_id']; ?>)</td>
                        <td><?php echo esc_html($rule['recipient']); ?></td>
                        <td>
                            <?php
                            $names = array();
                            foreach ($rule['attachment_ids'] as $attachment_id) {
                                $title = get_the_title($attachment_id);
                                $names[] = $title ? $title : sprintf('#%d', $attachment_id);
                            }
                            echo esc_html(implode(', ', $names));
                            ?>
                        </td>
                        <td>
                            <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(array('page' => EDH_Forminator_Attachments::PAGE_SLUG, 'edit' => $rule['id']), admin_url('options-general.php')), EDH_Forminator_Attachments::NONCE_ACTION)); ?>">
                                <?php esc_html_e('Edit', 'edh-file-attachment-for-forminator'); ?>
                            </a>
                            |
                            <a
                                href="<?php echo esc_url(wp_nonce_url(add_query_arg(array('action' => 'edh_delete_attachment_rule', 'rule_id' => $rule['id']), admin_url('admin-post.php')), EDH_Forminator_Attachments::NONCE_ACTION)); ?>"
                                onclick="return confirm('<?php echo esc_js(__('Delete this rule?', 'edh-file-attachment-for-forminator')); ?>');"
                            >
                                <?php esc_html_e('Delete', 'edh-file-attachment-for-forminator'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <h2><?php echo $edit_rule ? esc_html__('Edit Rule', 'edh-file-attachment-for-forminator') : esc_html__('Add New Rule', 'edh-file-attachment-for-forminator'); ?></h2>

    <form
        method="post"
        action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
        id="edh-attachment-rule-form"
        data-edit-recipient="<?php echo esc_attr($edit_rule ? $edit_rule['recipient'] : ''); ?>"
        data-edit-attachment-ids="<?php echo esc_attr($edit_rule ? implode(',', $edit_rule['attachment_ids']) : ''); ?>"
        data-edit-attachment-names="<?php echo esc_attr(wp_json_encode($edit_attachment_names)); ?>"
    >
        <?php wp_nonce_field(EDH_Forminator_Attachments::NONCE_ACTION); ?>
        <input type="hidden" name="action" value="edh_save_attachment_rule" />
        <input type="hidden" name="rule_id" value="<?php echo esc_attr($edit_rule ? $edit_rule['id'] : ''); ?>" />

        <table class="form-table">
            <tr>
                <th scope="row"><label for="edh-form-select"><?php esc_html_e('Form', 'edh-file-attachment-for-forminator'); ?></label></th>
                <td>
                    <select id="edh-form-select" name="form_id" required>
                        <option value=""><?php esc_html_e('— Select a form —', 'edh-file-attachment-for-forminator'); ?></option>
                        <?php foreach ($forms as $form_id => $form_name) : ?>
                            <option value="<?php echo (int) $form_id; ?>" <?php selected($edit_rule ? (int) $edit_rule['form_id'] : '', $form_id); ?>>
                                <?php echo esc_html($form_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="edh-recipient-select"><?php esc_html_e('Notification Email', 'edh-file-attachment-for-forminator'); ?></label></th>
                <td>
                    <select id="edh-recipient-select" name="recipient" required>
                        <option value=""><?php esc_html_e('Select a form first…', 'edh-file-attachment-for-forminator'); ?></option>
                    </select>
                    <p class="description"><?php esc_html_e('The emails configured for this form\'s notifications.', 'edh-file-attachment-for-forminator'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Attached Files', 'edh-file-attachment-for-forminator'); ?></th>
                <td>
                    <input type="hidden" name="attachment_ids" id="edh-attachment-ids" value="<?php echo esc_attr($edit_rule ? implode(',', $edit_rule['attachment_ids']) : ''); ?>" />
                    <p>
                        <button type="button" class="button" id="edh-select-files-button"><?php esc_html_e('Select Files', 'edh-file-attachment-for-forminator'); ?></button>
                    </p>
                    <ul id="edh-selected-files-list"></ul>
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="submit" class="button button-primary">
                <?php echo $edit_rule ? esc_html__('Update Rule', 'edh-file-attachment-for-forminator') : esc_html__('Save Rule', 'edh-file-attachment-for-forminator'); ?>
            </button>
            <?php if ($edit_rule) : ?>
                <a class="button" href="<?php echo esc_url(admin_url('options-general.php?page=' . EDH_Forminator_Attachments::PAGE_SLUG)); ?>"><?php esc_html_e('Cancel', 'edh-file-attachment-for-forminator'); ?></a>
            <?php endif; ?>
        </p>
    </form>
</div>
