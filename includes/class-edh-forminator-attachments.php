<?php
/**
 * Core plugin class: settings page, rule storage, and the Forminator/wp_mail hooks
 * that actually attach the configured files.
 *
 * @package EDH_Forminator_Attachments
 */

if (!defined('ABSPATH')) {
    exit;
}

class EDH_Forminator_Attachments
{
    const OPTION_NAME = 'edh_forminator_attachment_rules';
    const HEADER_NAME = 'X-EDH-Forminator-Form-ID';
    const PAGE_SLUG = 'edh-forminator-attachments';
    const NONCE_ACTION = 'edh_forminator_attachments';

    private static $instance = null;

    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('admin_menu', array($this, 'register_settings_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('admin_post_edh_save_attachment_rule', array($this, 'handle_save_rule'));
        add_action('admin_post_edh_delete_attachment_rule', array($this, 'handle_delete_rule'));
        add_action('wp_ajax_edh_get_form_notifications', array($this, 'ajax_get_form_notifications'));

        add_filter('forminator_custom_form_mail_admin_headers', array($this, 'tag_headers_with_form_id'), 10, 5);
        add_filter('wp_mail', array($this, 'attach_configured_files'), 10, 1);

        add_filter('plugin_action_links_' . plugin_basename(EDH_FORMINATOR_ATTACHMENTS_FILE), array($this, 'add_settings_link'));
    }

    public function add_settings_link($links)
    {
        $url = admin_url('options-general.php?page=' . self::PAGE_SLUG);
        $settings_link = '<a href="' . esc_url($url) . '">' . esc_html__('Settings', 'edh-file-attachment-for-forminator') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /* ------------------------------------------------------------------ */
    /* Rule storage                                                       */
    /* ------------------------------------------------------------------ */

    private function get_rules()
    {
        $rules = get_option(self::OPTION_NAME, array());
        return is_array($rules) ? $rules : array();
    }

    private function save_rules(array $rules)
    {
        update_option(self::OPTION_NAME, $rules);
    }

    private function find_rule($rule_id)
    {
        foreach ($this->get_rules() as $rule) {
            if ($rule['id'] === $rule_id) {
                return $rule;
            }
        }
        return null;
    }

    /* ------------------------------------------------------------------ */
    /* Settings page                                                      */
    /* ------------------------------------------------------------------ */

    public function register_settings_page()
    {
        add_options_page(
            __('Forminator Attachments', 'edh-file-attachment-for-forminator'),
            __('Forminator Attachments', 'edh-file-attachment-for-forminator'),
            'manage_options',
            self::PAGE_SLUG,
            array($this, 'render_settings_page')
        );
    }

    public function enqueue_assets($hook)
    {
        if ('settings_page_' . self::PAGE_SLUG !== $hook) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_script(
            'edh-forminator-attachments-admin',
            EDH_FORMINATOR_ATTACHMENTS_URL . 'assets/js/admin.js',
            array('jquery'),
            '1.1.3',
            true
        );
        wp_localize_script('edh-forminator-attachments-admin', 'edhForminatorAttachments', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce(self::NONCE_ACTION),
            'i18n'    => array(
                'loading'      => __('Loading…', 'edh-file-attachment-for-forminator'),
                'selectForm'   => __('Select a form first…', 'edh-file-attachment-for-forminator'),
                'noEmails'     => __('No notification emails are configured for this form.', 'edh-file-attachment-for-forminator'),
                'selectFiles'  => __('Select Files', 'edh-file-attachment-for-forminator'),
                'chooseFiles'  => __('Choose Files to Attach', 'edh-file-attachment-for-forminator'),
                'useFiles'     => __('Use these files', 'edh-file-attachment-for-forminator'),
                'remove'       => __('Remove', 'edh-file-attachment-for-forminator'),
            ),
        ));
    }

    public function render_settings_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $rules = $this->get_rules();
        $forms = $this->get_form_choices();
        $edit_rule = null;

        if (isset($_GET['edit']) && isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), self::NONCE_ACTION)) {
            $edit_rule = $this->find_rule(sanitize_text_field(wp_unslash($_GET['edit'])));
        }

        require EDH_FORMINATOR_ATTACHMENTS_DIR . 'includes/views/settings-page.php';
    }

    private function get_form_choices()
    {
        $choices = array();

        if (!class_exists('Forminator_API') || !method_exists('Forminator_API', 'get_forms')) {
            return $choices;
        }

        $forms = Forminator_API::get_forms(null, 1, 999);
        if (!is_array($forms)) {
            return $choices;
        }

        foreach ($forms as $form) {
            if (!isset($form->id)) {
                continue;
            }
            $choices[(int) $form->id] = $this->get_form_name($form);
        }

        return $choices;
    }

    private function get_form_name($form)
    {
        if (isset($form->settings['formName']) && '' !== $form->settings['formName']) {
            return $form->settings['formName'];
        }
        if (isset($form->name) && '' !== $form->name) {
            return $form->name;
        }
        /* translators: %d: form ID */
        return sprintf(__('Form #%d', 'edh-file-attachment-for-forminator'), (int) $form->id);
    }

    public function get_form_name_by_id($form_id)
    {
        static $cache = array();
        $form_id = (int) $form_id;

        if (isset($cache[$form_id])) {
            return $cache[$form_id];
        }

        /* translators: %d: form ID */
        $name = sprintf(__('Form #%d', 'edh-file-attachment-for-forminator'), $form_id);

        if (class_exists('Forminator_API') && method_exists('Forminator_API', 'get_form')) {
            try {
                $form = Forminator_API::get_form($form_id);
                if ($form) {
                    $name = $this->get_form_name($form);
                }
            } catch (Exception $e) {
                // Form no longer exists — fall back to the generic label above.
            }
        }

        $cache[$form_id] = $name;
        return $name;
    }

    /* ------------------------------------------------------------------ */
    /* AJAX: notifications for a given form                               */
    /* ------------------------------------------------------------------ */

    public function ajax_get_form_notifications()
    {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'edh-file-attachment-for-forminator')), 403);
        }

        $form_id = isset($_POST['form_id']) ? absint($_POST['form_id']) : 0;
        if (!$form_id) {
            wp_send_json_error(array('message' => __('Invalid form.', 'edh-file-attachment-for-forminator')));
        }

        $options = $this->get_form_notification_options($form_id);

        wp_send_json_success(array('options' => $options));
    }

    /**
     * Best-effort list of {value,label} recipient options for a form's
     * configured notifications. Used only to populate the settings UI —
     * actual attachment matching always happens against the real resolved
     * recipient address at send time, not against this parsed list.
     */
    private function get_form_notification_options($form_id)
    {
        $options = array();

        if (!class_exists('Forminator_API') || !method_exists('Forminator_API', 'get_form')) {
            return $options;
        }

        try {
            $form = Forminator_API::get_form($form_id);
        } catch (Exception $e) {
            return $options;
        }

        if (!$form) {
            return $options;
        }

        $notifications = array();
        if (isset($form->notifications) && is_array($form->notifications)) {
            $notifications = $form->notifications;
        } elseif (isset($form->settings['notifications']) && is_array($form->settings['notifications'])) {
            $notifications = $form->settings['notifications'];
        }

        $seen = array();

        foreach (array_values($notifications) as $index => $notification) {
            $notification = (array) $notification;
            $name = !empty($notification['label'])
                ? $notification['label']
                : (!empty($notification['name'])
                    ? $notification['name']
                    /* translators: %d: notification position within the form */
                    : sprintf(__('Notification %d', 'edh-file-attachment-for-forminator'), $index + 1));

            foreach ($this->resolve_notification_recipients($notification) as $recipient) {
                $address = strtolower(trim($recipient['address']));
                if ('' === $address || isset($seen[$address])) {
                    continue;
                }
                $seen[$address] = true;

                $label = $recipient['dynamic']
                    ? sprintf(
                        /* translators: 1: notification name, 2: raw recipient value */
                        __('%1$s — %2$s (dynamic, set from form field)', 'edh-file-attachment-for-forminator'),
                        $name,
                        $recipient['address']
                    )
                    : sprintf(
                        /* translators: 1: notification name, 2: recipient email address */
                        __('%1$s — %2$s', 'edh-file-attachment-for-forminator'),
                        $name,
                        $address
                    );

                $options[] = array(
                    'value'   => $address,
                    'label'   => $label,
                    'dynamic' => $recipient['dynamic'],
                );
            }
        }

        return $options;
    }

    /**
     * Resolve a notification's recipient(s).
     *
     * On the Forminator installs this has been tested against, the actual
     * recipient lives in `recipients` — either a literal email address
     * (including when it's just the resolved default admin email), or a
     * merge tag like "{email-1}" when the notification sends to whatever
     * address was entered into a form field. `email-recipients` has been
     * observed holding a type marker (e.g. "default") rather than an
     * address, but is still checked as a fallback for installs/configs
     * where it holds a literal address instead.
     */
    private function resolve_notification_recipients(array $notification)
    {
        $recipients = isset($notification['recipients']) ? trim((string) $notification['recipients']) : '';

        if (is_email($recipients)) {
            return array(array('address' => $recipients, 'dynamic' => false));
        }

        if (in_array($recipients, array('', 'default', 'admin_email'), true)) {
            return array(array(
                'address' => get_option('admin_email'),
                'dynamic' => false,
            ));
        }

        if (!empty($notification['email-recipients']) && false !== strpos($notification['email-recipients'], '@')) {
            $out = array();
            foreach (explode(',', $notification['email-recipients']) as $address) {
                $address = trim($address);
                if ('' === $address) {
                    continue;
                }
                $out[] = array(
                    'address' => $address,
                    'dynamic' => !is_email($address),
                );
            }
            if (!empty($out)) {
                return $out;
            }
        }

        // Anything else (e.g. a "{field-slug}" merge tag) is resolved
        // per-submission from a form field — there's no fixed address.
        return array(array('address' => $recipients, 'dynamic' => true));
    }

    /* ------------------------------------------------------------------ */
    /* Save / delete rule                                                 */
    /* ------------------------------------------------------------------ */

    public function handle_save_rule()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'edh-file-attachment-for-forminator'));
        }
        check_admin_referer(self::NONCE_ACTION, '_wpnonce');

        $form_id = isset($_POST['form_id']) ? absint($_POST['form_id']) : 0;
        $recipient = isset($_POST['recipient']) ? strtolower(sanitize_text_field(wp_unslash($_POST['recipient']))) : '';
        $rule_id = isset($_POST['rule_id']) ? sanitize_text_field(wp_unslash($_POST['rule_id'])) : '';
        $is_dynamic = !empty($_POST['recipient_dynamic']);

        $attachment_ids = array();
        if (!empty($_POST['attachment_ids'])) {
            foreach (explode(',', sanitize_text_field(wp_unslash($_POST['attachment_ids']))) as $id) {
                $id = absint($id);
                if ($id && 'attachment' === get_post_type($id)) {
                    $attachment_ids[] = $id;
                }
            }
        }

        $redirect = add_query_arg(array('page' => self::PAGE_SLUG), admin_url('options-general.php'));

        if (!$form_id || '' === $recipient || empty($attachment_ids)) {
            wp_safe_redirect(add_query_arg('edh_message', 'invalid', $redirect));
            exit;
        }

        $rules = $this->get_rules();
        $rule = array(
            'id'             => $rule_id ? $rule_id : uniqid('r', true),
            'form_id'        => $form_id,
            'recipient'      => $recipient,
            'dynamic'        => $is_dynamic,
            'attachment_ids' => array_values(array_unique($attachment_ids)),
        );

        $updated = false;
        foreach ($rules as $index => $existing) {
            if ($existing['id'] === $rule['id']) {
                $rules[$index] = $rule;
                $updated = true;
                break;
            }
        }
        if (!$updated) {
            $rules[] = $rule;
        }

        $this->save_rules($rules);

        wp_safe_redirect(add_query_arg('edh_message', 'saved', $redirect));
        exit;
    }

    public function handle_delete_rule()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'edh-file-attachment-for-forminator'));
        }
        check_admin_referer(self::NONCE_ACTION, '_wpnonce');

        $rule_id = isset($_GET['rule_id']) ? sanitize_text_field(wp_unslash($_GET['rule_id'])) : '';
        $redirect = add_query_arg(array('page' => self::PAGE_SLUG), admin_url('options-general.php'));

        if ('' !== $rule_id) {
            $rules = array_values(array_filter($this->get_rules(), function ($rule) use ($rule_id) {
                return $rule['id'] !== $rule_id;
            }));
            $this->save_rules($rules);
        }

        wp_safe_redirect(add_query_arg('edh_message', 'deleted', $redirect));
        exit;
    }

    /* ------------------------------------------------------------------ */
    /* Debug logging (only active when WP_DEBUG is enabled)               */
    /* ------------------------------------------------------------------ */

    private function log($message)
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[EDH Forminator Attachments] ' . $message); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- intentional, gated behind WP_DEBUG.
        }
    }

    /* ------------------------------------------------------------------ */
    /* Mail filters                                                       */
    /* ------------------------------------------------------------------ */

    public function tag_headers_with_form_id($headers, $custom_form, $data, $entry, $cls)
    {
        $form_id = isset($custom_form->id) ? (int) $custom_form->id : 0;
        $has_rules = $form_id && $this->form_has_rules($form_id);

        $this->log(sprintf(
            'forminator_custom_form_mail_admin_headers fired for form #%d — has saved rule(s): %s',
            $form_id,
            $has_rules ? 'yes' : 'no'
        ));

        if ($has_rules) {
            $headers[] = self::HEADER_NAME . ': ' . $form_id;
            $this->log(sprintf('Tagged headers for form #%d with %s', $form_id, self::HEADER_NAME));
        }

        return $headers;
    }

    private function form_has_rules($form_id)
    {
        foreach ($this->get_rules() as $rule) {
            if ((int) $rule['form_id'] === (int) $form_id) {
                return true;
            }
        }
        return false;
    }

    public function attach_configured_files($args)
    {
        if (empty($args['headers'])) {
            $this->log('wp_mail fired with no headers at all; cannot be a tagged Forminator notification, skipping.');
            return $args;
        }

        $form_id = $this->extract_form_id_from_headers($args['headers']);
        if (!$form_id) {
            $this->log('wp_mail fired but no ' . self::HEADER_NAME . ' header was found; either this isn\'t a Forminator notification, or tag_headers_with_form_id() didn\'t tag it (check the log line above for "has saved rule(s)").');
            return $args;
        }

        $recipients = $this->normalize_recipients(isset($args['to']) ? $args['to'] : '');
        $this->log(sprintf(
            'wp_mail tagged for form #%d, resolved recipient(s): %s',
            $form_id,
            $recipients ? implode(', ', $recipients) : '(none)'
        ));

        if (empty($recipients)) {
            $this->log('No recipients could be resolved from $args[\'to\']; skipping.');
            return $args;
        }

        $rules_for_form = array();
        foreach ($this->get_rules() as $rule) {
            if ((int) $rule['form_id'] === $form_id) {
                $rules_for_form[] = $rule;
            }
        }

        // Specific (literal-address) rules take precedence: a rule only
        // matches this particular send if its configured recipient is one
        // of the addresses this email is actually going to.
        $attachment_ids = array();
        $matched_specific = false;
        foreach ($rules_for_form as $rule) {
            if (!empty($rule['dynamic'])) {
                continue;
            }
            if (!in_array($rule['recipient'], $recipients, true)) {
                $this->log(sprintf(
                    'Rule recipient "%s" does not match resolved recipient(s) (%s) for form #%d.',
                    $rule['recipient'],
                    implode(', ', $recipients),
                    $form_id
                ));
                continue;
            }
            $matched_specific = true;
            foreach ($rule['attachment_ids'] as $id) {
                $attachment_ids[] = (int) $id;
            }
        }

        // Dynamic rules (recipient set from a form field, e.g. "{email-1}")
        // can never be matched by literal address — there's no way to know
        // it ahead of time. They're used as a fallback only when no
        // specific rule already claimed this particular send, so a
        // form's admin-notification rule and its dynamic client-notification
        // rule don't both fire on the same email.
        if (!$matched_specific) {
            foreach ($rules_for_form as $rule) {
                if (empty($rule['dynamic'])) {
                    continue;
                }
                $this->log(sprintf(
                    'No specific rule matched; applying dynamic rule "%s" as fallback for form #%d.',
                    $rule['recipient'],
                    $form_id
                ));
                foreach ($rule['attachment_ids'] as $id) {
                    $attachment_ids[] = (int) $id;
                }
            }
        }

        if (empty($attachment_ids)) {
            $this->log(sprintf(
                'No matching rule produced any attachment IDs for form #%d / recipient(s) %s (%d saved rule(s) exist for this form).',
                $form_id,
                implode(', ', $recipients),
                count($rules_for_form)
            ));
            return $args;
        }

        $attachments = array();
        if (!empty($args['attachments'])) {
            $attachments = is_array($args['attachments']) ? $args['attachments'] : array($args['attachments']);
        }

        $added = array();
        foreach (array_unique($attachment_ids) as $id) {
            $path = get_attached_file($id);
            if ($path && file_exists($path)) {
                $attachments[] = $path;
                $added[] = $path;
            } else {
                $this->log(sprintf('Attachment #%d resolved to a missing/invalid file path ("%s"); skipped.', $id, $path ? $path : '(empty)'));
            }
        }

        $this->log(sprintf(
            'Attached %d file(s) to the form #%d notification for %s: %s',
            count($added),
            $form_id,
            implode(', ', $recipients),
            $added ? implode(', ', $added) : '(none)'
        ));

        $args['attachments'] = $attachments;

        return $args;
    }

    private function extract_form_id_from_headers($headers)
    {
        $lines = is_array($headers) ? $headers : explode("\n", str_replace("\r\n", "\n", $headers));

        foreach ($lines as $line) {
            if (!is_string($line)) {
                continue;
            }
            if (false === stripos($line, self::HEADER_NAME)) {
                continue;
            }
            $parts = explode(':', $line, 2);
            if (isset($parts[1])) {
                return (int) trim($parts[1]);
            }
        }

        return 0;
    }

    private function normalize_recipients($to)
    {
        $addresses = is_array($to) ? $to : explode(',', (string) $to);
        $out = array();

        foreach ($addresses as $address) {
            $address = trim($address);
            if (preg_match('/<([^>]+)>/', $address, $matches)) {
                $address = $matches[1];
            }
            $address = strtolower(trim($address));
            if ('' !== $address) {
                $out[] = $address;
            }
        }

        return $out;
    }
}
