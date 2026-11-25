=== NIBWOZ hCaptcha ===
Contributors: brinsleyparagao
Tags: hcaptcha, captcha, spam, login, woocommerce
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 8.3
Stable tag: 1.3.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

hCaptcha for WordPress and classic WooCommerce forms: login, registration, password reset, comments and checkout, with IP whitelist and Cloudflare.

== Description ==

NIBWOZ hCaptcha adds the hCaptcha challenge to the native forms of WordPress and classic WooCommerce, and verifies every submission on the server before WordPress processes it.

Protected forms:

* WordPress: login, registration, lost password, reset password and comments.
* WooCommerce (classic templates): login, registration, lost password, reset password and checkout.

Features:

* Choose which forms require the challenge.
* Visible or invisible widget, light or dark theme, normal or compact size, scale, alignment and spacing, with per-form size, scale and alignment overrides.
* Whitelist of IPs and CIDR ranges, with a quick button to add the current IP, and an option to exempt logged-in administrators.
* Cloudflare detection, with an optional setting to trust the visitor IP reported by Cloudflare, and support for trusted reverse proxies.
* Fail-closed option: block submissions when the hCaptcha service does not respond or returns invalid data.
* Optional diagnostic log that records only the form context and the result, without IPs, tokens or keys.
* English and Brazilian Portuguese included.

The plugin needs an hCaptcha account. Create a site in the hCaptcha dashboard and copy its Site key and Secret key.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`, or install the zip file under Plugins → Add New → Upload Plugin.
2. Activate the plugin.
3. Go to Tools → NWZ hCaptcha → Access keys and enter the Site key and Secret key of your hCaptcha site.
4. Under Protected forms, choose where the challenge is required.

Until both keys are set, protected forms reject submissions. Add your own IP to the whitelist first if you need to sign in while configuring.

== Frequently Asked Questions ==

= Do I need an hCaptcha account? =

Yes. The Site key and the Secret key come from the hCaptcha dashboard.

= Can I use it together with another hCaptcha plugin? =

No. If another hCaptcha integration is active, this plugin suspends its protection and shows a notice, to avoid two verifications on the same form.

= My site is behind Cloudflare. What should I enable? =

Open Security and whitelist. When the Cloudflare block shows "Detected", enable "Trust the IP reported by Cloudflare" so the whitelist and the verification use the real visitor IP. The plugin never changes this option on its own.

= What happens if hCaptcha is unavailable? =

With "Block submissions when hCaptcha verification fails" enabled (the default), protected forms are blocked until the service answers again. With it disabled, submissions are allowed.

= Does uninstalling remove my settings? =

Only if you enable "Delete all data on uninstall" under About. Otherwise the settings stay in the database for a future reinstall.

== External services ==

This plugin connects to hCaptcha, a service by Intuition Machines, Inc., to show the challenge and to verify that it was solved.

* On pages with a protected form, the visitor's browser loads the hCaptcha script from `https://js.hcaptcha.com/1/api.js`. hCaptcha receives the usual browser request data, such as the IP address and user agent, and the data its challenge collects.
* When a protected form is submitted, your server sends the hCaptcha response token, your Secret key, your Site key and, when it can be identified, the visitor IP to `https://api.hcaptcha.com/siteverify`.

Nothing is sent before a protected form is displayed or submitted, and nothing is sent to NIBWOZ. hCaptcha terms of service: https://www.hcaptcha.com/terms — privacy policy: https://www.hcaptcha.com/privacy

== Privacy ==

The plugin stores its settings, including the hCaptcha keys and the whitelist, in the WordPress options table. It does not create cookies and does not collect statistics. The optional diagnostic log is written to the server temporary folder and contains only the date, the form context and the result code.

== Changelog ==

= 1.3.4 =
* The "Settings saved" confirmation always appears after saving, even when the browser or another plugin requests the page more than once.

= 1.3.3 =
* The Secret key field has the same rounded border as the other fields.
* When the key is hidden automatically after switching windows, the status message says so.

= 1.3.2 =
* The Secret key field uses the kit's field-with-button component: a visible Show/Hide secret key button next to the field.
* The login screen loads nothing from hCaptcha when the visitor is exempt (whitelisted IP or logged-in administrator).
* The legacy PHP configuration file is read only once per request.
* About screen without the repeated brand banner.

= 1.3.1 =
* Interface updated to the NIBWOZ UI 1.2.0 kit: confirmations appear as a temporary message in the center of the screen, and buttons show an update icon while busy.
* Reloading the page no longer repeats a confirmation.

= 1.3.0 =
* New interface based on the NIBWOZ UI 1.1.0 kit: sidebar navigation, single Save button, unsaved-changes indicator, brand colors and fonts served locally.
* New Diagnostics section with environment, protection and network checks.
* Home summary now shows translated widget values.
* readme.txt in the WordPress.org format; license declared in the plugin header.
* Input sanitization and escaping reviewed with Plugin Check.

= 1.2.34 =
* Source strings in English with a complete Brazilian Portuguese translation.

= 1.2.33 =
* Requires WordPress 7.0 and PHP 8.3.

= 1.2.32 =
* Test files only run from the command line.

= 1.2.31 =
* Restoring defaults without keeping the keys now also deletes the Secret key.

== Upgrade Notice ==

= 1.3.4 =
Reliable save confirmation. Settings are kept.
