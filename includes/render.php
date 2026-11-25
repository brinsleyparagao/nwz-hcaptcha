<?php
namespace NIBWOZ\HCaptcha\Render;
use function NIBWOZ\HCaptcha\Config\get;
use function NIBWOZ\HCaptcha\Config\appearance;
use function NIBWOZ\HCaptcha\Config\behavior;
use function NIBWOZ\HCaptcha\Network\required;
defined( 'ABSPATH' ) || exit;
// Versão 1.2.28: carrega o SDK no rodapé apenas quando um formulário renderiza o widget.
function styles(): void {
    wp_enqueue_style( 'nibwoz-hcaptcha', plugins_url( 'assets/hcaptcha.css', NIBWOZ_HCAPTCHA_FILE ), [], NIBWOZ_HCAPTCHA_VERSION );
}
function assets(): void {
    static $done = false;
    if ( $done ) { return; }
    if ( ! is_string( get( 'site_key' ) ) || '' === trim( get( 'site_key' ) ) || ! is_string( get( 'secret_key' ) ) || '' === trim( get( 'secret_key' ) ) ) { return; }
    $done = true;
    styles();
    wp_enqueue_script( 'nibwoz-hcaptcha-client', plugins_url( 'assets/hcaptcha.js', NIBWOZ_HCAPTCHA_FILE ), [], NIBWOZ_HCAPTCHA_VERSION, true );
    $compat = true === get( 'behavior.recaptcha_compat' ) ? 'on' : 'off';
    wp_enqueue_script( 'nibwoz-hcaptcha-sdk', 'https://js.hcaptcha.com/1/api.js?render=explicit&onload=nibwozHCaptchaOnload&recaptchacompat=' . $compat, [ 'nibwoz-hcaptcha-client' ], null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- hCaptcha serves and versions its own SDK; a ver query string would bypass its cache policy.
    wp_add_inline_script( 'nibwoz-hcaptcha-client', 'window.nibwozHCaptchaSettings=' . wp_json_encode( [
        'resetOnExpire' => true === get( 'behavior.reset_on_expire' ),
        'showErrors' => true === get( 'behavior.show_error_messages' ),
        'error' => __( 'hCaptcha verification failed. Please try again.', 'nibwoz-hcaptcha' ),
    ] ) . ';', 'before' );
}
function html( string $context ): string {
    if ( ! required( $context ) ) { return ''; }
    if ( ! is_string( get( 'site_key' ) ) || '' === trim( get( 'site_key' ) ) || ! is_string( get( 'secret_key' ) ) || '' === trim( get( 'secret_key' ) ) ) {
        return '<p role="alert">' . esc_html__( 'hCaptcha is not configured. Contact the site administrator.', 'nibwoz-hcaptcha' ) . '</p>';
    }
    assets(); $a = appearance( $context ); $b = behavior( $context );
    $style = sprintf( '--nibwoz-top:%dpx;--nibwoz-bottom:%dpx;', $a['spacing_top'], $a['spacing_bottom'] );
    return '<div class="nibwoz-hcaptcha" data-context="' . esc_attr( $context ) . '" data-sitekey="' . esc_attr( get( 'site_key' ) ) . '" data-theme="' . esc_attr( $a['theme'] ) . '" data-size="' . esc_attr( 'invisible' === $b['mode'] ? 'invisible' : $a['size'] ) . '" data-scale="' . esc_attr( (string) $a['scale'] ) . '" data-language="' . esc_attr( $a['language'] ) . '" data-mode="' . esc_attr( $b['mode'] ) . '" data-render="' . esc_attr( $b['render'] ) . '" data-align="' . esc_attr( $a['alignment'] ) . '" style="' . esc_attr( $style ) . '"><div class="nibwoz-hcaptcha-frame"><div class="nibwoz-hcaptcha-widget"></div></div><span class="nibwoz-hcaptcha-error" role="alert" aria-live="polite"></span></div>';
}
function output( string $context ): void { echo html( $context ); } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Every attribute and text in html() is escaped with esc_attr()/esc_html__().
