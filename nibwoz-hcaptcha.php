<?php
/**
 * Plugin Name: NIBWOZ hCaptcha
 * Description: Configurable hCaptcha protection for native WordPress and classic WooCommerce forms.
 * Version: 1.3.4
 * Plugin URI: https://nibwoz.com/
 * Requires at least: 7.0
 * Requires PHP: 8.3
 * Author: Brinsley Aragão / NIBWOZ
 * Author URI: https://nibwoz.com/sobre/
 * Text Domain: nibwoz-hcaptcha
 * Domain Path: /languages
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */
defined( 'ABSPATH' ) || exit;
define( 'NIBWOZ_HCAPTCHA_VERSION', '1.3.4' );
define( 'NIBWOZ_HCAPTCHA_FILE', __FILE__ );
// Traduções carregadas no init (WordPress 6.7+ avisa quando textos são traduzidos antes disso).
add_action( 'init', static function () {
    load_plugin_textdomain( 'nibwoz-hcaptcha', false, dirname( plugin_basename( NIBWOZ_HCAPTCHA_FILE ) ) . '/languages' ); // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- Installed from a zip outside WordPress.org, so bundled translations must be loaded explicitly.
} );
// Impede duas validações simultâneas nos mesmos formulários.
function nibwoz_hcaptcha_conflict(): bool {
    if ( function_exists( 'nibwoz_hcaptcha_config' ) ) { return true; }
    $active = (array) get_option( 'active_plugins', [] );
    if ( is_multisite() ) { $active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', [] ) ) ); }
    foreach ( $active as $plugin ) {
        if ( is_string( $plugin ) && false !== stripos( $plugin, 'hcaptcha' ) && 'nibwoz-hcaptcha/nibwoz-hcaptcha.php' !== $plugin ) { return true; }
    }
    return false;
}
register_activation_hook( __FILE__, static function () {
    if ( nibwoz_hcaptcha_conflict() ) { wp_die( esc_html__( 'Deactivate the previous NIBWOZ hCaptcha MU plugin and other hCaptcha integrations before activating.', 'nibwoz-hcaptcha' ) ); }
} );
if ( nibwoz_hcaptcha_conflict() ) {
    add_action( 'admin_notices', static function () { echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'NIBWOZ hCaptcha: conflict with an MU plugin or another hCaptcha plugin. Protection in this version is suspended.', 'nibwoz-hcaptcha' ) . '</p></div>'; } );
    return;
}
foreach ( [ 'config', 'network', 'logger', 'verify', 'render', 'integrations', 'admin' ] as $nibwoz_hcaptcha_component ) {
    require_once __DIR__ . '/includes/' . $nibwoz_hcaptcha_component . '.php';
}
\NIBWOZ\HCaptcha\Integrations\boot();
if ( is_admin() ) { \NIBWOZ\HCaptcha\Admin\boot(); }
