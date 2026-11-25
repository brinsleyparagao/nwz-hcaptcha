<?php
namespace NIBWOZ\HCaptcha\Integrations;
// Versão 1.2.6: identifica fluxos do wp-login.php pelos hooks do núcleo, inclusive em URL personalizada.
use function NIBWOZ\HCaptcha\Render\styles;
use function NIBWOZ\HCaptcha\Network\required;
use function NIBWOZ\HCaptcha\Render\html;
use function NIBWOZ\HCaptcha\Render\output;
use function NIBWOZ\HCaptcha\Verify\check;
use function NIBWOZ\HCaptcha\Verify\message;
defined( 'ABSPATH' ) || exit;
function server( string $key ): string {
    return isset( $_SERVER[ $key ] ) && is_string( $_SERVER[ $key ] ) ? sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ) : '';
}
function script(): string { return basename( server( 'SCRIPT_NAME' ) ); }
function post(): bool { return 'POST' === strtoupper( server( 'REQUEST_METHOD' ) ); }
function core_login_action( string ...$actions ): bool {
    foreach ( $actions as $action ) {
        if ( did_action( 'login_form_' . $action ) > 0 ) { return true; }
    }
    return false;
}
function wp_login_request(): bool {
    $script = script();
    $action = isset( $_REQUEST['action'] ) && is_string( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : 'login'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Identifies the wp-login.php action only; core verifies the login itself.
    $woocommerce = isset( $_POST['woocommerce-login-nonce'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Read-only detection inside core/WooCommerce form handlers, which verify their own nonces; hCaptcha is checked separately.
    return post() && in_array( $action, [ 'login', '' ], true ) && ! $woocommerce
        && ( 'wp-login.php' === $script || core_login_action( 'login' ) );
}
function wc_reset_request(): bool { return post() && isset( $_POST['wc_reset_password'], $_POST['password_1'], $_POST['reset_key'] ); } // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Read-only detection inside core/WooCommerce form handlers, which verify their own nonces; hCaptcha is checked separately.
function boot(): void {
    add_action( 'login_enqueue_scripts', __NAMESPACE__ . '\\enqueue' );
    add_action( 'wp_enqueue_scripts', 'NIBWOZ\\HCaptcha\\Render\\styles' );
    add_action( 'login_form', static function () { output( 'wp_login' ); } );
    add_filter( 'authenticate', static function ( $user ) {
        if ( wp_login_request() && ! check( 'wp_login' ) ) { return new \WP_Error( 'nibwoz_hcaptcha_failed', message() ); }
        return $user;
    }, 30, 1 );
    add_action( 'register_form', static function () { output( 'wp_register' ); } );
    add_filter( 'registration_errors', static function ( $errors ) {
        if ( post() && ( 'wp-login.php' === script() || core_login_action( 'register' ) ) && ! check( 'wp_register' ) ) {
            $errors->add( 'nibwoz_hcaptcha_failed', message() );
        }
        return $errors;
    }, 30 );
    add_action( 'lostpassword_form', static function () { output( 'wp_lost_password' ); } );
    add_action( 'lostpassword_post', static function ( $errors ) {
        if ( ! post() ) { return; }
        if ( isset( $_POST['wc_reset_password'] ) ) { $context = 'wc_lost_password'; } // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Read-only detection inside core/WooCommerce form handlers, which verify their own nonces; hCaptcha is checked separately.
        elseif ( 'wp-login.php' === script() || core_login_action( 'lostpassword', 'retrievepassword' ) ) { $context = 'wp_lost_password'; }
        else { return; }
        if ( ! check( $context ) ) { $errors->add( 'nibwoz_hcaptcha_failed', message() ); }
    }, 30 );
    add_action( 'resetpass_form', static function () { output( 'wp_reset_password' ); } );
    add_action( 'validate_password_reset', static function ( $errors ) {
        if ( ! post() ) { return; }
        if ( wc_reset_request() ) { $context = 'wc_reset_password'; }
        elseif ( 'wp-login.php' === script() || core_login_action( 'rp', 'resetpass' ) ) { $context = 'wp_reset_password'; }
        else { return; }
        if ( ! check( $context ) ) { $errors->add( 'nibwoz_hcaptcha_failed', message() ); }
    }, 30 );
    add_action( 'comment_form_after_fields', static function () { output( 'wp_comments' ); } );
    add_action( 'comment_form_logged_in_after', static function () { output( 'wp_comments' ); } );
    add_filter( 'preprocess_comment', static function ( $comment ) {
        if ( is_admin() || 'wp-comments-post.php' !== script() || ! empty( $comment['comment_type'] ) && 'comment' !== $comment['comment_type'] ) { return $comment; }
        if ( ! check( 'wp_comments' ) ) { wp_die( esc_html( message() ), esc_html__( 'Verification failed', 'nibwoz-hcaptcha' ), [ 'response' => 403, 'back_link' => true ] ); }
        return $comment;
    }, 30 );
    add_action( 'woocommerce_login_form', static function () { output( 'wc_login' ); } );
    add_filter( 'woocommerce_process_login_errors', static function ( $errors ) { if ( ! check( 'wc_login' ) ) { $errors->add( 'nibwoz_hcaptcha_failed', message() ); } return $errors; }, 30 );
    add_action( 'woocommerce_register_form', static function () { output( 'wc_register' ); } );
    add_filter( 'woocommerce_process_registration_errors', static function ( $errors ) { if ( ! check( 'wc_register' ) ) { $errors->add( 'nibwoz_hcaptcha_failed', message() ); } return $errors; }, 30 );
    add_action( 'woocommerce_lostpassword_form', static function () { output( 'wc_lost_password' ); } );
    add_action( 'woocommerce_resetpassword_form', static function () { output( 'wc_reset_password' ); } );
    add_action( 'woocommerce_review_order_before_submit', static function () { output( 'wc_checkout' ); } );
    add_action( 'woocommerce_checkout_process', static function () {
        if ( ! check( 'wc_checkout' ) ) { wc_add_notice( message(), 'error' ); }
    }, 30 );
}
// Versão 1.3.2: IP isento (whitelist) ou administrador dispensado não carrega nada na tela de login.
// O SDK do hCaptcha é enfileirado por html() somente quando o widget é exibido.
function enqueue(): void {
    foreach ( [ 'wp_login', 'wp_register', 'wp_lost_password', 'wp_reset_password' ] as $context ) {
        if ( required( $context ) ) { styles(); return; }
    }
}
// Integrações externas: use Render\html($context) no formulário e Verify\check($context) no handler real.
