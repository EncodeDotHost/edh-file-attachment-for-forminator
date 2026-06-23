=== EDH File Attachments for Forminator ===
Contributors: encodehost
Tags: forminator, attachments, email, forms, media library
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.1.3
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Attach Media Library files to Forminator notification emails, configured per form and recipient from a settings page.

== Description ==

EDH File Attachments for Forminator lets you attach files from the WordPress Media Library to [Forminator](https://wordpress.org/plugins/forminator/) form notification emails — configured entirely from a settings page, no code changes required.

= Requirements =

* WordPress
* Forminator plugin installed and active

= Usage =

1. Go to **Settings → Forminator Attachments**.
2. Under "Add New Rule", pick a **Form**.
3. Pick one of that form's configured **notification emails** (the recipient address resolves automatically — e.g. the site admin email, or a custom address set on the notification).
4. Click **Select Files** and choose one or more files from the Media Library.
5. Click **Save Rule**.

Whenever that form is submitted and a notification email goes out to the matching recipient, the chosen files are attached automatically. You can add as many rules as you need, and edit or delete them at any time from the same page.

If Forminator isn't installed/active, the plugin shows an admin notice and otherwise does nothing.

== Installation ==

1. Upload the plugin to the `/wp-content/plugins/` directory, or install it through the WordPress plugins screen.
2. Activate the plugin.
3. Ensure Forminator is installed and active.
4. Go to Settings → Forminator Attachments to configure rules.

== Changelog ==

= 1.1.3 =
* Fixed recipient resolution to match this Forminator install's actual notification data: the literal address (or merge tag, for dynamic recipients) lives in `recipients`, and the notification's display name is `label`, not `email-recipients`/`name` as previously assumed.

= 1.1.2 =
* Added support for dynamic (form-field-based) notification recipients: matching now tries literal-address rules first, falling back to a dynamic rule only when no literal rule matches a given send, so an admin-notification rule and a dynamic client-notification rule on the same form no longer both fire on every email.
* Added debug logging through the mail-attachment pipeline, active only when `WP_DEBUG` is enabled.

= 1.1.1 =
* Fixed missing translators comments and unsanitized nonce input flagged by Plugin Check/PHPCS.
* Added GPLv3 license headers, a languages folder, and a WordPress-standard readme.txt.

= 1.1.0 =
* Fixed notification recipient resolution for Forminator's "default" recipient type.
* Use each notification's configured name (e.g. "Admin Email") as its label when available.
* Added a Settings link to the plugin's row on the Plugins page.

= 1.0.0 =
* Replaced the hardcoded form-ID-to-PDF mapping with a settings page under Settings → Forminator Attachments, where rules pairing a form, a notification recipient email, and Media Library files can be managed without editing code.
