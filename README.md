# EDH File Attachments for Forminator

A WordPress plugin that lets you attach files from the Media Library to
[Forminator](https://wordpress.org/plugins/forminator/) form notification
emails — configured entirely from a settings page, no code changes required.

## Requirements

- WordPress
- [Forminator](https://wordpress.org/plugins/forminator/) plugin installed and active

## Usage

1. Go to **Settings → Forminator Attachments**.
2. Under "Add New Rule", pick a **Form**.
3. Pick one of that form's configured **notification emails** (the recipient
   address resolves automatically — e.g. the site admin email, or a custom
   address set on the notification).
4. Click **Select Files** and choose one or more files from the Media
   Library.
5. Click **Save Rule**.

Whenever that form is submitted and a notification email goes out to the
matching recipient, the chosen files are attached automatically. You can add
as many rules as you need, and edit or delete them at any time from the same
page.

If Forminator isn't installed/active, the plugin shows an admin notice and
otherwise does nothing.
