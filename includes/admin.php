<?php
namespace NIBWOZ\HCaptcha\Admin;
use function NIBWOZ\HCaptcha\Config\all;
use function NIBWOZ\HCaptcha\Config\defaults;
use function NIBWOZ\HCaptcha\Network\client_ip;
use function NIBWOZ\HCaptcha\Network\cloudflare_status;
use function NIBWOZ\HCaptcha\Network\in_rules;
use const NIBWOZ\HCaptcha\Config\OPTION;
use NIBWOZ\UI\V1_2_0\Ui;
defined( 'ABSPATH' ) || exit;
require_once dirname( __DIR__ ) . '/assets/nibwoz-ui/Ui.php';
// O nome completo permanece no plugin e no título; o menu tem rótulo abreviado.

function boot(): void {
    add_action( 'admin_menu', __NAMESPACE__ . '\\menu' );
    add_action( 'admin_init', __NAMESPACE__ . '\\register' );
    add_action( 'admin_notices', __NAMESPACE__ . '\\notice' );
    add_action( 'admin_post_nibwoz_hcaptcha_reset', __NAMESPACE__ . '\\reset' );
    add_action( 'admin_post_nibwoz_hcaptcha_save_uninstall', __NAMESPACE__ . '\\save_uninstall' );
    add_action( 'admin_post_nibwoz_hcaptcha_allow_current_ip', __NAMESPACE__ . '\\allow_current_ip' );
    add_action( 'wp_ajax_nibwoz_hcaptcha_reveal_secret', __NAMESPACE__ . '\\reveal_secret' );
    add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\assets' );
    add_filter( 'removable_query_args', __NAMESPACE__ . '\\removable_args' );
    add_filter( 'plugin_action_links_' . plugin_basename( NIBWOZ_HCAPTCHA_FILE ), __NAMESPACE__ . '\\links' );
}
function menu(): void {
    add_management_page( 'NIBWOZ hCaptcha', 'NWZ hCaptcha', 'manage_options', 'nibwoz-hcaptcha', __NAMESPACE__ . '\\page' );
}
function reveal_secret(): void {
    if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( [ 'message' => __( 'Access denied.', 'nibwoz-hcaptcha' ) ], 403 ); }
    check_ajax_referer( 'nibwoz_hcaptcha_reveal_secret', 'nonce' );
    nocache_headers();
    wp_send_json_success( [ 'secret' => (string) ( all()['secret_key'] ?? '' ) ] );
}
function assets( string $hook ): void {
    if ( 'tools_page_nibwoz-hcaptcha' !== $hook ) { return; }
    $ui = Ui::enqueue( 'nwz-hc', plugins_url( 'assets/nibwoz-ui/', NIBWOZ_HCAPTCHA_FILE ) );
    wp_enqueue_style( 'nibwoz-hcaptcha-admin', plugins_url( 'assets/admin.css', NIBWOZ_HCAPTCHA_FILE ), [ $ui ], NIBWOZ_HCAPTCHA_VERSION );
    wp_enqueue_script( 'nibwoz-hcaptcha-admin', plugins_url( 'assets/admin.js', NIBWOZ_HCAPTCHA_FILE ), [], NIBWOZ_HCAPTCHA_VERSION, [ 'in_footer' => true, 'strategy' => 'defer' ] );
    wp_add_inline_script( 'nibwoz-hcaptcha-admin', 'window.nibwozHCaptchaAdmin=' . wp_json_encode( [
        'show' => __( 'Show secret key', 'nibwoz-hcaptcha' ),
        'hide' => __( 'Hide secret key', 'nibwoz-hcaptcha' ),
        'hidden' => __( 'Key hidden.', 'nibwoz-hcaptcha' ),
        'visible' => __( 'Key visible.', 'nibwoz-hcaptcha' ),
        'loading' => __( 'Loading saved key…', 'nibwoz-hcaptcha' ),
        'loaded' => __( 'Saved key visible. Click again to hide.', 'nibwoz-hcaptcha' ),
        'failed' => __( 'Could not show the saved key. Reload the page and try again.', 'nibwoz-hcaptcha' ),
    ] ) . ';', 'before' );
}
function register(): void {
    register_setting( 'nibwoz_hcaptcha', OPTION, [
        'type' => 'array', 'show_in_rest' => false, 'sanitize_callback' => __NAMESPACE__ . '\\sanitize',
    ] );
}
function links( array $links ): array {
    array_unshift( $links, '<a href="' . esc_url( admin_url( 'tools.php?page=nibwoz-hcaptcha' ) ) . '">' . esc_html__( 'Settings', 'nibwoz-hcaptcha' ) . '</a>' );
    return $links;
}
// Aviso global dispensável; na tela do plugin o mesmo alerta aparece no kit.
function notice(): void {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    if ( $screen && 'tools_page_nibwoz-hcaptcha' === $screen->id ) { return; }
    $saved = get_option( OPTION, null );
    if ( ! is_array( $saved ) && is_file( WP_CONTENT_DIR . '/nibwoz-hcaptcha-config.php' ) ) {
        echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'NIBWOZ hCaptcha: the old PHP configuration file is still in use. Review and save under Tools → NWZ hCaptcha before removing the file.', 'nibwoz-hcaptcha' ) . '</p></div>';
    } elseif ( '' === trim( (string) ( all()['site_key'] ?? '' ) ) || '' === trim( (string) ( all()['secret_key'] ?? '' ) ) ) {
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'NIBWOZ hCaptcha: set both keys under Tools → NWZ hCaptcha. Protected forms reject submissions until both keys are set.', 'nibwoz-hcaptcha' ) . '</p></div>';
    }
}
function contexts(): array {
    return [
        'wp_login' => __( 'WordPress: Login', 'nibwoz-hcaptcha' ), 'wp_register' => __( 'WordPress: Registration', 'nibwoz-hcaptcha' ),
        'wp_lost_password' => __( 'WordPress: Lost password', 'nibwoz-hcaptcha' ), 'wp_reset_password' => __( 'WordPress: Reset password', 'nibwoz-hcaptcha' ),
        'wp_comments' => __( 'WordPress: Comments', 'nibwoz-hcaptcha' ), 'wc_login' => __( 'WooCommerce: Login', 'nibwoz-hcaptcha' ),
        'wc_register' => __( 'WooCommerce: Registration', 'nibwoz-hcaptcha' ), 'wc_lost_password' => __( 'WooCommerce: Lost password', 'nibwoz-hcaptcha' ),
        'wc_reset_password' => __( 'WooCommerce: Reset password', 'nibwoz-hcaptcha' ), 'wc_checkout' => __( 'WooCommerce: Classic checkout', 'nibwoz-hcaptcha' ),
    ];
}
function scalar( $value, string $kind, $fallback ) {
    if ( ! is_scalar( $value ) ) { return $fallback; }
    if ( 'bool' === $kind ) { return '1' === (string) $value; }
    if ( 'spacing' === $kind ) { return max( 0, min( 100, (int) $value ) ); }
    if ( 'scale' === $kind ) { return max( 70, min( 130, (int) $value ) ); }
    $value = trim( (string) $value );
    $allowed = [
        'theme' => [ 'light', 'dark' ], 'size' => [ 'normal', 'compact' ],
        'alignment' => [ 'left', 'center', 'right' ], 'mode' => [ 'visible', 'invisible' ],
        'render' => [ 'auto', 'explicit' ],
    ];
    if ( 'language' === $kind ) {
        return 'auto' === $value || preg_match( '/^[a-z]{2,3}(?:-[A-Z]{2})?$/D', $value ) ? $value : $fallback;
    }
    return in_array( $value, $allowed[ $kind ] ?? [], true ) ? $value : $fallback;
}
function lines( $raw ): array {
    if ( is_array( $raw ) ) {
        $raw = implode( "\n", array_filter( $raw, 'is_string' ) );
    }
    if ( ! is_string( $raw ) ) { return []; }
    $entries = preg_split( '/[\r\n,]+/', $raw );
    $valid = [];
    foreach ( $entries as $entry ) {
        $entry = trim( $entry );
        if ( '' === $entry ) { continue; }
        $parts = explode( '/', $entry );
        $ip = filter_var( $parts[0], FILTER_VALIDATE_IP );
        $max = false !== $ip && false !== strpos( $ip, ':' ) ? 128 : 32;
        if ( false === $ip || count( $parts ) > 2 ||
            ( 2 === count( $parts ) && ( ! ctype_digit( $parts[1] ) || (int) $parts[1] > $max ) ) ) { continue; }
        $valid[] = $entry;
    }
    return array_values( array_unique( $valid ) );
}
function sanitize( $raw ): array {
    $previous = all();
    if ( ! is_array( $raw ) ) { return $previous; }
    $clean = defaults();
    foreach ( [ 'site_key', 'secret_key' ] as $key ) {
        $value = isset( $raw[ $key ] ) && is_string( $raw[ $key ] ) ? trim( $raw[ $key ] ) : '';
        // Em branco só significa "manter a atual" no formulário; gravações internas (reset) gravam o valor recebido.
        if ( 'secret_key' === $key && '' === $value && ! internal_write() ) { $value = (string) ( $previous[ $key ] ?? '' ); }
        if ( '' !== $value && ( strlen( $value ) > 512 || preg_match( '/[\x00-\x20\x7f]/', $value ) ) ) {
            add_settings_error( OPTION, 'invalid_' . $key, __( 'Invalid key. The previous configuration was kept.', 'nibwoz-hcaptcha' ), 'error' );
            return $previous;
        }
        $clean[ $key ] = $value;
    }
    $clean['trusted_ips'] = lines( $raw['trusted_ips'] ?? '' );
    foreach ( [ 'bypass_logged_admins', 'unknown_contexts_enabled', 'debug' ] as $key ) {
        $clean[ $key ] = isset( $raw[ $key ] ) && is_scalar( $raw[ $key ] ) && '1' === (string) $raw[ $key ];
    }
    $clean['uninstall_cleanup'] = array_key_exists( 'uninstall_cleanup', $raw )
        ? is_scalar( $raw['uninstall_cleanup'] ) && '1' === (string) $raw['uninstall_cleanup']
        : ! empty( $previous['uninstall_cleanup'] );
    foreach ( contexts() as $context => $label ) {
        $clean['contexts'][ $context ] = isset( $raw['contexts'][ $context ] ) && is_scalar( $raw['contexts'][ $context ] ) && '1' === (string) $raw['contexts'][ $context ];
    }
    foreach ( [ 'appearance', 'behavior', 'security', 'network' ] as $group ) {
        foreach ( $clean[ $group ] as $key => $default ) {
            if ( is_bool( $default ) ) { $clean[ $group ][ $key ] = isset( $raw[ $group ][ $key ] ) && is_scalar( $raw[ $group ][ $key ] ) && '1' === (string) $raw[ $group ][ $key ]; }
            elseif ( is_array( $default ) ) { $clean[ $group ][ $key ] = lines( $raw[ $group ][ $key ] ?? '' ); }
            else { $clean[ $group ][ $key ] = scalar( $raw[ $group ][ $key ] ?? $default, 0 === strpos( $key, 'spacing_' ) ? 'spacing' : $key, $default ); }
        }
    }
    foreach ( contexts() as $context => $label ) {
        foreach ( [ 'size', 'scale', 'alignment' ] as $key ) {
            $value = $raw['context_appearance'][ $context ][ $key ] ?? 'inherit';
            if ( 'inherit' !== $value && '' !== $value ) {
                $clean['context_appearance'][ $context ][ $key ] = scalar( $value, $key, $clean['appearance'][ $key ] );
            }
        }
    }
    $clean['context_behavior'] = [];
    return $clean;
}
function internal_write( ?bool $active = null ): bool {
    static $state = false;
    if ( null !== $active ) { $state = $active; }
    return $state;
}
// Gravação feita pelo próprio plugin; o sanitize_callback do register_setting continua validando os dados.
function store( array $config ): bool {
    internal_write( true );
    try { return update_option( OPTION, $config, false ); } finally { internal_write( false ); }
}
function reset(): void {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Access denied.', 'nibwoz-hcaptcha' ), '', [ 'response' => 403 ] ); }
    check_admin_referer( 'nibwoz_hcaptcha_reset' );
    $confirmed = isset( $_POST['confirm_reset'] ) && is_scalar( $_POST['confirm_reset'] ) && '1' === sanitize_text_field( wp_unslash( (string) $_POST['confirm_reset'] ) );
    if ( ! $confirmed ) { wp_die( esc_html__( 'Confirm the restore before continuing.', 'nibwoz-hcaptcha' ), '', [ 'response' => 400 ] ); }
    $keep = isset( $_POST['keep_keys'] ) && is_scalar( $_POST['keep_keys'] ) && '1' === sanitize_text_field( wp_unslash( (string) $_POST['keep_keys'] ) );
    store( reset_values( $keep ) );
    wp_safe_redirect( add_query_arg( [ 'view' => 'about', 'nwz_hc_reset' => $keep ? 'kept' : 'cleared' ], admin_url( 'tools.php?page=nibwoz-hcaptcha' ) ) );
    exit;
}
function reset_values( bool $keep ): array {
    $clean = defaults();
    if ( $keep ) {
        $old = all();
        foreach ( [ 'site_key', 'secret_key' ] as $key ) {
            $clean[ $key ] = is_string( $old[ $key ] ?? null ) ? $old[ $key ] : '';
        }
    }
    return $clean;
}
function save_uninstall(): void {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Access denied.', 'nibwoz-hcaptcha' ), '', [ 'response' => 403 ] ); }
    check_admin_referer( 'nibwoz_hcaptcha_save_uninstall' );
    $config = all();
    $config['uninstall_cleanup'] = isset( $_POST['uninstall_cleanup'] ) && is_scalar( $_POST['uninstall_cleanup'] ) && '1' === sanitize_text_field( wp_unslash( (string) $_POST['uninstall_cleanup'] ) );
    store( $config );
    wp_safe_redirect( add_query_arg( [ 'view' => 'about', 'nwz_hc_uninstall' => 'saved' ], admin_url( 'tools.php?page=nibwoz-hcaptcha' ) ) );
    exit;
}
function allow_current_ip(): void {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Access denied.', 'nibwoz-hcaptcha' ), '', [ 'response' => 403 ] ); }
    $ip = isset( $_POST['current_ip'] ) && is_string( $_POST['current_ip'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['current_ip'] ) ) ) : '';
    // O nonce é emitido para o IP que o servidor exibiu na página. Uma alteração no POST invalida a assinatura.
    check_admin_referer( 'nibwoz_hcaptcha_allow_current_ip_' . $ip );
    $status = 'unavailable';
    if ( false !== filter_var( $ip, FILTER_VALIDATE_IP ) ) {
        $config = all();
        $rules = $config['trusted_ips'] ?? [];
        $status = in_rules( $ip, $rules ) ? 'existing' : 'added';
        if ( 'added' === $status ) {
            $config = allow_current_ip_values( $config, $ip );
            if ( ! store( $config ) ) { $status = 'failed'; }
        }
    }
    wp_safe_redirect( add_query_arg( [ 'view' => 'network', 'nwz_hc_whitelist' => $status ], admin_url( 'tools.php?page=nibwoz-hcaptcha' ) ) );
    exit;
}
function allow_current_ip_values( array $config, string $ip ): array {
    if ( false === filter_var( $ip, FILTER_VALIDATE_IP ) ) { return $config; }
    $rules = $config['trusted_ips'] ?? [];
    if ( in_rules( $ip, $rules ) ) { return $config; }
    $config['trusted_ips'] = lines( implode( "\n", array_merge( is_array( $rules ) ? $rules : [], [ $ip ] ) ) );
    return $config;
}
function options( string $key ): array {
    switch ( $key ) {
        case 'theme': return [ 'light' => __( 'Light', 'nibwoz-hcaptcha' ), 'dark' => __( 'Dark', 'nibwoz-hcaptcha' ) ];
        case 'size': return [ 'normal' => __( 'Normal', 'nibwoz-hcaptcha' ), 'compact' => __( 'Compact', 'nibwoz-hcaptcha' ) ];
        case 'alignment': return [ 'left' => __( 'Left', 'nibwoz-hcaptcha' ), 'center' => __( 'Center', 'nibwoz-hcaptcha' ), 'right' => __( 'Right', 'nibwoz-hcaptcha' ) ];
        case 'mode': return [ 'visible' => __( 'Visible', 'nibwoz-hcaptcha' ), 'invisible' => __( 'Invisible', 'nibwoz-hcaptcha' ) ];
        case 'render': return [ 'auto' => __( 'Automatic', 'nibwoz-hcaptcha' ), 'explicit' => __( 'Explicit (requires an integration call)', 'nibwoz-hcaptcha' ) ];
    }
    return [];
}
function title( string $key ): string {
    $titles = [
        'theme' => __( 'Theme', 'nibwoz-hcaptcha' ), 'size' => __( 'Size', 'nibwoz-hcaptcha' ), 'scale' => __( 'Widget scale (%)', 'nibwoz-hcaptcha' ), 'language' => __( 'Language (auto, pt-BR)', 'nibwoz-hcaptcha' ),
        'alignment' => __( 'Alignment', 'nibwoz-hcaptcha' ), 'spacing_top' => __( 'Space above (px)', 'nibwoz-hcaptcha' ), 'spacing_bottom' => __( 'Space below (px)', 'nibwoz-hcaptcha' ),
        'mode' => __( 'Mode', 'nibwoz-hcaptcha' ), 'render' => __( 'Rendering', 'nibwoz-hcaptcha' ), 'reset_on_expire' => __( 'Reset on expiry', 'nibwoz-hcaptcha' ),
        'show_error_messages' => __( 'Show errors', 'nibwoz-hcaptcha' ), 'recaptcha_compat' => __( 'reCAPTCHA compatibility', 'nibwoz-hcaptcha' ),
        'fail_closed' => __( 'Block submissions when hCaptcha verification fails', 'nibwoz-hcaptcha' ), 'trust_cloudflare' => __( 'Trust Cloudflare', 'nibwoz-hcaptcha' ),
        'trust_forwarded_headers' => __( 'Trust X-Forwarded-For', 'nibwoz-hcaptcha' ), 'trusted_proxy_cidrs' => __( 'Trusted proxies (IP/CIDR)', 'nibwoz-hcaptcha' ),
    ];
    return $titles[ $key ] ?? $key;
}
// Versão 1.3.1: telas no kit NIBWOZ UI 1.2.0 (assets/nibwoz-ui/, cópia sem edição); confirmações em toast.
function field_id( string $name ): string {
    return 'nwz-hc-' . trim( (string) preg_replace( '/[^a-z0-9]+/', '-', strtolower( $name ) ), '-' );
}
function toggle( string $name, bool $checked, string $label, string $help = '' ): void {
    Ui::toggle( [ 'id' => field_id( $name ), 'name' => OPTION . $name, 'checked' => $checked, 'label' => $label, 'help' => $help ] );
}
function select_field( string $name, string $label, $value, array $choices, string $help = '' ): void {
    $id = field_id( $name );
    echo '<div class="nwz-field"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label><select id="' . esc_attr( $id ) . '" name="' . esc_attr( OPTION . $name ) . '"' . ( '' !== $help ? ' aria-describedby="' . esc_attr( $id ) . '-help"' : '' ) . '>';
    foreach ( $choices as $choice => $choice_label ) {
        echo '<option value="' . esc_attr( (string) $choice ) . '"' . selected( (string) $choice, (string) $value, false ) . '>' . esc_html( $choice_label ) . '</option>';
    }
    echo '</select>';
    if ( '' !== $help ) { echo '<small id="' . esc_attr( $id ) . '-help">' . esc_html( $help ) . '</small>'; }
    echo '</div>';
}
function text_field( string $name, string $label, string $value, string $help = '', string $class = '' ): void {
    $id = field_id( $name );
    echo '<div class="nwz-field"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label><input type="text" id="' . esc_attr( $id ) . '" name="' . esc_attr( OPTION . $name ) . '" value="' . esc_attr( $value ) . '" class="' . esc_attr( $class ) . '" spellcheck="false" autocomplete="off"' . ( '' !== $help ? ' aria-describedby="' . esc_attr( $id ) . '-help"' : '' ) . '>';
    if ( '' !== $help ) { echo '<small id="' . esc_attr( $id ) . '-help">' . esc_html( $help ) . '</small>'; }
    echo '</div>';
}
function lines_field( string $name, string $label, $value, string $help ): void {
    $id = field_id( $name );
    echo '<div class="nwz-field"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label><textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( OPTION . $name ) . '" rows="4" spellcheck="false" aria-describedby="' . esc_attr( $id ) . '-help">' . esc_textarea( implode( "\n", is_array( $value ) ? $value : [] ) ) . '</textarea><small id="' . esc_attr( $id ) . '-help">' . esc_html( $help ) . '</small></div>';
}
function number_field( string $name, string $label, $value, int $min, int $max, string $unit, string $help ): void {
    Ui::number( [ 'id' => field_id( $name ), 'name' => OPTION . $name, 'value' => (string) $value, 'min' => $min, 'max' => $max, 'label' => $label, 'help' => $help, 'unit' => $unit ] );
}
// Estado neutro (sem sucesso nem erro), para opções cujo padrão seguro é desligado.
function state( string $text ): string {
    return '<span class="nwz-hc-state">' . esc_html( $text ) . '</span>';
}
function views(): array {
    return [
        'overview' => [ __( 'Home', 'nibwoz-hcaptcha' ), 'dashicons-admin-home' ],
        'keys' => [ __( 'Access keys', 'nibwoz-hcaptcha' ), 'dashicons-lock' ],
        'contexts' => [ __( 'Protected forms', 'nibwoz-hcaptcha' ), 'dashicons-forms' ],
        'appearance' => [ __( 'Appearance', 'nibwoz-hcaptcha' ), 'dashicons-admin-appearance' ],
        'network' => [ __( 'Security and whitelist', 'nibwoz-hcaptcha' ), 'dashicons-shield' ],
        'diagnostics' => [ __( 'Diagnostics', 'nibwoz-hcaptcha' ), 'dashicons-search' ],
        'about' => [ __( 'About', 'nibwoz-hcaptcha' ), 'dashicons-info-outline' ],
    ];
}
// Endereços antigos continuam abrindo a seção que absorveu o conteúdo.
function active_view( string $requested ): string {
    $aliases = [ 'exceptions' => 'network', 'security' => 'network', 'reset' => 'about', 'behavior' => 'appearance', 'overrides' => 'appearance' ];
    $requested = $aliases[ $requested ] ?? $requested;
    return isset( views()[ $requested ] ) ? $requested : 'overview';
}
function query_flag( string $key ): string {
    // Somente leitura de parâmetros de exibição após redirecionamentos do próprio plugin; nada é gravado.
    return isset( $_GET[ $key ] ) && is_string( $_GET[ $key ] ) ? sanitize_key( wp_unslash( $_GET[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only flag set by this plugin's own redirects; no state change.
}
function panel_open( string $id, string $active ): void {
    echo '<section class="nwz-hc-panel" data-hc-view-panel="' . esc_attr( $id ) . '"' . ( $id === $active ? '' : ' hidden' ) . '>';
}
// Parâmetros de retorno do plugin saem da URL (wp_admin_canonical_url), para recarregar não repetir o toast.
function removable_args( array $args ): array {
    return array_merge( $args, [ 'nwz_hc_reset', 'nwz_hc_whitelist', 'nwz_hc_uninstall' ] );
}
// Confirmações em toast (um por vez, no centro da tela); erros e avisos que exigem leitura continuam fixos.
function status_notices(): void {
    // Versão 1.3.4: o toast de salvamento vem do parâmetro settings-updated (padrão do kit), não da mensagem
    // temporária do WordPress, que some se a página de retorno for pedida mais de uma vez.
    $toast = null;
    $failed = false;
    foreach ( get_settings_errors( OPTION ) as $error ) {
        if ( in_array( $error['type'] ?? '', [ 'success', 'updated' ], true ) ) { continue; }
        $failed = $failed || 'error' === ( $error['type'] ?? '' );
        Ui::notice( 'error' === ( $error['type'] ?? '' ) ? 'error' : 'info', [ wp_strip_all_tags( (string) ( $error['message'] ?? '' ) ) ] );
    }
    if ( 'true' === query_flag( 'settings-updated' ) && ! $failed ) { $toast = [ __( 'Settings saved.', 'nibwoz-hcaptcha' ), 'success' ]; }
    $reset = query_flag( 'nwz_hc_reset' );
    if ( 'kept' === $reset ) { $toast = [ __( 'Settings restored. The keys were kept.', 'nibwoz-hcaptcha' ), 'success' ]; }
    if ( 'cleared' === $reset ) { $toast = [ __( 'Settings restored. Both keys were deleted.', 'nibwoz-hcaptcha' ), 'success' ]; }
    $status = query_flag( 'nwz_hc_whitelist' );
    if ( 'added' === $status ) { $toast = [ __( 'Current IP added to the whitelist.', 'nibwoz-hcaptcha' ), 'success' ]; }
    if ( 'existing' === $status ) { $toast = [ __( 'The current IP is already covered by the whitelist.', 'nibwoz-hcaptcha' ), 'info' ]; }
    if ( 'unavailable' === $status ) { Ui::notice( 'error', [ __( 'Could not identify a valid IP. The whitelist was not changed.', 'nibwoz-hcaptcha' ) ] ); }
    if ( 'failed' === $status ) { Ui::notice( 'error', [ __( 'Could not save the IP to the whitelist.', 'nibwoz-hcaptcha' ) ] ); }
    if ( 'saved' === query_flag( 'nwz_hc_uninstall' ) ) { $toast = [ __( 'Uninstall preference saved.', 'nibwoz-hcaptcha' ), 'success' ]; }
    if ( $toast ) { Ui::toast( $toast[0], $toast[1] ); }
    if ( ! is_array( get_option( OPTION, null ) ) && is_file( WP_CONTENT_DIR . '/nibwoz-hcaptcha-config.php' ) ) {
        Ui::notice( 'info', [ __( 'The values shown come from the old PHP file. Saving stores them in the database. After confirming everything works, remove the old file.', 'nibwoz-hcaptcha' ) ] );
    }
}
function cloudflare_texts( string $status, bool $trusted ): array {
    $labels = [ 'verified' => __( 'Detected', 'nibwoz-hcaptcha' ), 'restored' => __( 'Detected', 'nibwoz-hcaptcha' ), 'intermediate_proxy' => __( 'Review proxy', 'nibwoz-hcaptcha' ), 'missing_header' => __( 'Review server', 'nibwoz-hcaptcha' ), 'not_detected' => __( 'Not detected', 'nibwoz-hcaptcha' ), 'unknown' => __( 'Inconclusive', 'nibwoz-hcaptcha' ) ];
    $messages = [
        'verified' => __( 'The request came through an official Cloudflare range and carried a valid visitor IP.', 'nibwoz-hcaptcha' ),
        'restored' => __( 'Cloudflare was identified and the server already passes the real visitor IP to WordPress.', 'nibwoz-hcaptcha' ),
        'intermediate_proxy' => __( 'There are signs of Cloudflare, but another proxy sits between it and the server.', 'nibwoz-hcaptcha' ),
        'missing_header' => __( 'The origin is Cloudflare, but the header with the visitor IP is missing or invalid.', 'nibwoz-hcaptcha' ),
        'not_detected' => __( 'This request did not show a verifiable Cloudflare origin.', 'nibwoz-hcaptcha' ),
        'unknown' => __( 'Detection could not be completed for this request.', 'nibwoz-hcaptcha' ),
    ];
    $advice = [
        'verified' => $trusted ? __( 'Trust is enabled and the visitor IP can be used.', 'nibwoz-hcaptcha' ) : __( 'Enable trust to use the visitor IP.', 'nibwoz-hcaptcha' ),
        'restored' => __( 'The IP was already restored by the server; additional trust is not needed.', 'nibwoz-hcaptcha' ),
        'intermediate_proxy' => __( 'Review the proxy chain before enabling trust.', 'nibwoz-hcaptcha' ),
        'missing_header' => __( 'Fix header forwarding before enabling trust.', 'nibwoz-hcaptcha' ),
        'not_detected' => __( 'Keep trust disabled if this is the normal traffic path.', 'nibwoz-hcaptcha' ),
        'unknown' => __( 'Confirm the server configuration before enabling trust.', 'nibwoz-hcaptcha' ),
    ];
    $status = isset( $labels[ $status ] ) ? $status : 'unknown';
    return [ $labels[ $status ], $messages[ $status ], $advice[ $status ] ];
}
function page(): void {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Access denied.', 'nibwoz-hcaptcha' ) ); }
    $config = all();
    $active = active_view( query_flag( 'view' ) );
    $base_url = admin_url( 'tools.php?page=nibwoz-hcaptcha' );
    $kit_url = plugins_url( 'assets/nibwoz-ui/', NIBWOZ_HCAPTCHA_FILE );
    $key_ready = '' !== trim( (string) $config['site_key'] ) && '' !== trim( (string) $config['secret_key'] );
    $contexts = contexts();
    $enabled = count( array_filter( $config['contexts'], static function ( $value ) { return true === $value; } ) );
    $fail_closed = ! empty( $config['security']['fail_closed'] );
    $current_ip = client_ip();
    $cf_status = cloudflare_status();
    $cf = cloudflare_texts( $cf_status, ! empty( $config['network']['trust_cloudflare'] ) );
    $items = [];
    foreach ( views() as $id => $view ) { $items[] = [ $view[0], add_query_arg( 'view', $id, $base_url ), $view[1], $id === $active ]; }
    Ui::shell_open( [
        'title' => 'NIBWOZ hCaptcha',
        'product' => 'NWZ hCaptcha',
        /* translators: %s: plugin version number. */
        'version' => sprintf( __( 'Version %s', 'nibwoz-hcaptcha' ), NIBWOZ_HCAPTCHA_VERSION ),
        'wordmark_url' => $kit_url . 'nibwoz-wordmark.svg',
        'nav_label' => __( 'hCaptcha sections', 'nibwoz-hcaptcha' ),
        'items' => $items,
        'save' => [ 'form' => 'nwz-hc-save-form', 'label' => __( 'Save settings', 'nibwoz-hcaptcha' ), 'dirty' => __( 'Unsaved changes', 'nibwoz-hcaptcha' ) ],
        'leave' => __( 'There are unsaved changes on this screen.', 'nibwoz-hcaptcha' ),
        'class' => 'nwz-hc-admin',
    ] );
    status_notices();

    panel_open( 'overview', $active );
    Ui::page_head( __( 'Home', 'nibwoz-hcaptcha' ), __( 'Configuration summary', 'nibwoz-hcaptcha' ), __( 'This summary shows the currently saved values. Changes made in the sections appear after saving.', 'nibwoz-hcaptcha' ) );
    if ( ! $key_ready ) { Ui::notice( 'error', [ __( 'Protected forms reject submissions until both keys are set.', 'nibwoz-hcaptcha' ) ] ); }
    echo '<div class="nwz-stat-grid">';
    Ui::stat( __( 'Access keys', 'nibwoz-hcaptcha' ), $key_ready ? __( 'Configured', 'nibwoz-hcaptcha' ) : __( 'Incomplete', 'nibwoz-hcaptcha' ), $key_ready ? __( 'Site key and Secret key are set.', 'nibwoz-hcaptcha' ) : __( 'Set both keys to protect the forms.', 'nibwoz-hcaptcha' ), $key_ready ? 'green' : 'orange' );
    /* translators: 1: enabled forms, 2: total forms. */
    Ui::stat( __( 'Protected forms', 'nibwoz-hcaptcha' ), sprintf( __( '%1$d of %2$d active', 'nibwoz-hcaptcha' ), $enabled, count( $contexts ) ), __( 'Native WordPress and WooCommerce forms.', 'nibwoz-hcaptcha' ), 'blue' );
    $ip_count = count( $config['trusted_ips'] );
    /* translators: %d: number of exempt IPs or CIDR ranges. */
    Ui::stat( __( 'Whitelist', 'nibwoz-hcaptcha' ), sprintf( _n( '%d exempt IP/CIDR', '%d exempt IPs/CIDRs', $ip_count, 'nibwoz-hcaptcha' ), $ip_count ), ! empty( $config['bypass_logged_admins'] ) ? __( 'Logged-in administrators exempt', 'nibwoz-hcaptcha' ) : __( 'Logged-in administrators not exempt', 'nibwoz-hcaptcha' ), 'dark' );
    Ui::stat( __( 'Service failure', 'nibwoz-hcaptcha' ), $fail_closed ? __( 'Block', 'nibwoz-hcaptcha' ) : __( 'Allow', 'nibwoz-hcaptcha' ), $fail_closed ? __( 'hCaptcha service failure or invalid response: form blocked', 'nibwoz-hcaptcha' ) : __( 'hCaptcha service failure or invalid response: form allowed', 'nibwoz-hcaptcha' ), $fail_closed ? 'green' : 'orange' );
    echo '</div><section class="nwz-card"><h3>' . esc_html__( 'Protected forms', 'nibwoz-hcaptcha' ) . '</h3><ul class="nwz-chips">';
    foreach ( $contexts as $context => $label ) {
        $on = true === ( $config['contexts'][ $context ] ?? false );
        echo '<li class="' . ( $on ? 'is-on' : 'is-off' ) . '"><span class="dashicons ' . ( $on ? 'dashicons-yes' : 'dashicons-minus' ) . '" aria-hidden="true"></span>' . esc_html( $label ) . '<span class="screen-reader-text"> ' . esc_html( $on ? __( 'Enabled', 'nibwoz-hcaptcha' ) : __( 'Disabled', 'nibwoz-hcaptcha' ) ) . '</span></li>';
    }
    echo '</ul></section><div class="nwz-grid-2"><section class="nwz-card"><h3>' . esc_html__( 'Widget', 'nibwoz-hcaptcha' ) . '</h3>';
    Ui::dl( [
        [ title( 'theme' ), esc_html( options( 'theme' )[ $config['appearance']['theme'] ] ?? $config['appearance']['theme'] ) ],
        [ title( 'size' ), esc_html( options( 'size' )[ $config['appearance']['size'] ] ?? $config['appearance']['size'] ) ],
        [ title( 'scale' ), esc_html( (int) $config['appearance']['scale'] . '%' ) ],
        [ title( 'mode' ), esc_html( options( 'mode' )[ $config['behavior']['mode'] ] ?? $config['behavior']['mode'] ) ],
        [ title( 'render' ), esc_html( options( 'render' )[ $config['behavior']['render'] ] ?? $config['behavior']['render'] ) ],
    ] );
    echo '</section><section class="nwz-card"><h3>' . esc_html__( 'Network', 'nibwoz-hcaptcha' ) . '</h3>';
    Ui::dl( [
        [ __( 'Cloudflare', 'nibwoz-hcaptcha' ), state( ! empty( $config['network']['trust_cloudflare'] ) ? __( 'Cloudflare trusted', 'nibwoz-hcaptcha' ) : __( 'Cloudflare not trusted', 'nibwoz-hcaptcha' ) ) ],
        [ 'X-Forwarded-For', state( ! empty( $config['network']['trust_forwarded_headers'] ) ? __( 'X-Forwarded-For trusted', 'nibwoz-hcaptcha' ) : __( 'X-Forwarded-For not trusted', 'nibwoz-hcaptcha' ) ) ],
        [ __( 'Diagnostic log', 'nibwoz-hcaptcha' ), state( ! empty( $config['debug'] ) ? __( 'Diagnostic log on', 'nibwoz-hcaptcha' ) : __( 'Diagnostic log off', 'nibwoz-hcaptcha' ) ) ],
    ] );
    echo '</section></div></section>';

    echo '<form id="nwz-hc-save-form" method="post" action="options.php" data-nwz-settings>';
    settings_fields( 'nibwoz_hcaptcha' );

    panel_open( 'keys', $active );
    Ui::page_head( __( 'Credentials', 'nibwoz-hcaptcha' ), __( 'Access keys', 'nibwoz-hcaptcha' ), __( 'Use the keys from the same site in hCaptcha. Leave the secret key empty to keep the current one.', 'nibwoz-hcaptcha' ) );
    echo '<section class="nwz-card"><h3>' . esc_html__( 'hCaptcha keys', 'nibwoz-hcaptcha' ) . '</h3><div class="nwz-hc-keys">';
    text_field( '[site_key]', __( 'Site key', 'nibwoz-hcaptcha' ), (string) $config['site_key'], __( 'Public key shown in the widget.', 'nibwoz-hcaptcha' ), 'nwz-hc-key' );
    // Versão 1.3.2: componente "Campo com botão" do kit; o botão do kit tem texto visível e alterna o tipo do campo.
    echo '<div class="nwz-inline-field"><label for="nwz-hc-secret-key">' . esc_html__( 'Secret key (leave empty to keep the current one)', 'nibwoz-hcaptcha' ) . '</label><div><input id="nwz-hc-secret-key" class="nwz-hc-key" name="' . esc_attr( OPTION . '[secret_key]' ) . '" type="password" value="" placeholder="' . esc_attr( '' !== (string) $config['secret_key'] ? '••••••••••••••••' : __( 'Not configured', 'nibwoz-hcaptcha' ) ) . '" autocomplete="new-password" spellcheck="false" aria-describedby="nwz-hc-secret-status"><button type="button" class="button nwz-hc-reveal-secret" aria-pressed="false" aria-controls="nwz-hc-secret-key" data-hc-secret-url="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '" data-hc-secret-nonce="' . esc_attr( wp_create_nonce( 'nibwoz_hcaptcha_reveal_secret' ) ) . '"><span class="dashicons dashicons-visibility" aria-hidden="true"></span><span class="nwz-hc-reveal-label">' . esc_html__( 'Show secret key', 'nibwoz-hcaptcha' ) . '</span></button></div><small id="nwz-hc-secret-status" role="status" aria-live="polite">' . esc_html__( 'The saved key is loaded only when you use the show button.', 'nibwoz-hcaptcha' ) . '</small></div>';
    echo '</div><p class="description">' . esc_html__( 'The show button requires administrator permission. Hiding a retrieved key clears the field; when empty, the saved key is kept.', 'nibwoz-hcaptcha' ) . '</p></section></section>';

    panel_open( 'contexts', $active );
    Ui::page_head( __( 'Coverage', 'nibwoz-hcaptcha' ), __( 'Protected forms', 'nibwoz-hcaptcha' ), __( 'Choose the native forms where the challenge is required.', 'nibwoz-hcaptcha' ) );
    echo '<div class="nwz-grid-2">';
    foreach ( [ 'wp_' => 'WordPress', 'wc_' => 'WooCommerce' ] as $prefix => $platform ) {
        echo '<section class="nwz-card"><h3>' . esc_html( $platform ) . '</h3>';
        foreach ( $contexts as $context => $label ) {
            if ( 0 !== strpos( $context, $prefix ) ) { continue; }
            toggle( '[contexts][' . $context . ']', true === ( $config['contexts'][ $context ] ?? false ), $label, __( 'Requires the challenge on this form, except for configured exemptions.', 'nibwoz-hcaptcha' ) );
        }
        echo '</section>';
    }
    echo '<section class="nwz-card nwz-span-all"><h3>' . esc_html__( 'Custom adapters', 'nibwoz-hcaptcha' ) . '</h3>';
    toggle( '[unknown_contexts_enabled]', ! empty( $config['unknown_contexts_enabled'] ), __( 'Other contexts from custom adapters', 'nibwoz-hcaptcha' ), __( 'Allows requiring the challenge in contexts integrated by custom adapters.', 'nibwoz-hcaptcha' ) );
    echo '</section></div></section>';

    panel_open( 'appearance', $active );
    Ui::page_head( __( 'Widget and forms', 'nibwoz-hcaptcha' ), __( 'Appearance', 'nibwoz-hcaptcha' ), __( 'Configure the widget presentation, behavior and per-form adjustments.', 'nibwoz-hcaptcha' ) );
    $a = $config['appearance']; $b = $config['behavior'];
    echo '<div class="nwz-grid-2"><section class="nwz-card"><h3>' . esc_html__( 'Appearance', 'nibwoz-hcaptcha' ) . '</h3><div class="nwz-fields-2">';
    select_field( '[appearance][theme]', title( 'theme' ), $a['theme'], options( 'theme' ) );
    select_field( '[appearance][size]', title( 'size' ), $a['size'], options( 'size' ) );
    number_field( '[appearance][scale]', __( 'Widget scale', 'nibwoz-hcaptcha' ), $a['scale'], 70, 130, '%', __( 'From 70 to 130.', 'nibwoz-hcaptcha' ) );
    select_field( '[appearance][alignment]', title( 'alignment' ), $a['alignment'], options( 'alignment' ) );
    number_field( '[appearance][spacing_top]', __( 'Space above', 'nibwoz-hcaptcha' ), $a['spacing_top'], 0, 100, 'px', __( 'From 0 to 100.', 'nibwoz-hcaptcha' ) );
    number_field( '[appearance][spacing_bottom]', __( 'Space below', 'nibwoz-hcaptcha' ), $a['spacing_bottom'], 0, 100, 'px', __( 'From 0 to 100.', 'nibwoz-hcaptcha' ) );
    echo '</div>';
    text_field( '[appearance][language]', __( 'Language', 'nibwoz-hcaptcha' ), (string) $a['language'], __( 'Use auto to follow the visitor, or a code such as pt-BR.', 'nibwoz-hcaptcha' ) );
    echo '</section><section class="nwz-card"><h3>' . esc_html__( 'Behavior', 'nibwoz-hcaptcha' ) . '</h3><div class="nwz-fields-2">';
    select_field( '[behavior][mode]', title( 'mode' ), $b['mode'], options( 'mode' ) );
    select_field( '[behavior][render]', title( 'render' ), $b['render'], options( 'render' ) );
    echo '</div>';
    toggle( '[behavior][reset_on_expire]', ! empty( $b['reset_on_expire'] ), title( 'reset_on_expire' ), __( 'Resets the challenge when the token expires.', 'nibwoz-hcaptcha' ) );
    toggle( '[behavior][show_error_messages]', ! empty( $b['show_error_messages'] ), title( 'show_error_messages' ), __( 'Shows challenge error messages to the visitor.', 'nibwoz-hcaptcha' ) );
    toggle( '[behavior][recaptcha_compat]', ! empty( $b['recaptcha_compat'] ), title( 'recaptcha_compat' ), __( 'Enables client compatibility with reCAPTCHA integrations.', 'nibwoz-hcaptcha' ) );
    echo '</section></div><section class="nwz-card"><h3>' . esc_html__( 'Per-form adjustments', 'nibwoz-hcaptcha' ) . '</h3><p class="description">' . esc_html__( 'Change only size, scale or alignment when a form needs to differ from the general default. Other settings remain global.', 'nibwoz-hcaptcha' ) . '</p><div class="nwz-hc-overrides">';
    foreach ( $contexts as $context => $label ) {
        $values = is_array( $config['context_appearance'][ $context ] ?? null ) ? $config['context_appearance'][ $context ] : [];
        $count = count( array_intersect_key( $values, array_flip( [ 'size', 'scale', 'alignment' ] ) ) );
        /* translators: %d: number of per-form overrides. */
        $summary = $label . ' — ' . ( $count ? sprintf( _n( '%d override', '%d overrides', $count, 'nibwoz-hcaptcha' ), $count ) : __( 'Using the general default', 'nibwoz-hcaptcha' ) );
        echo '<details class="nwz-details"><summary>' . esc_html( $summary ) . '</summary><div class="nwz-fields-3">';
        select_field( '[context_appearance][' . $context . '][size]', title( 'size' ), $values['size'] ?? 'inherit', [ 'inherit' => __( 'General default', 'nibwoz-hcaptcha' ) ] + options( 'size' ) );
        $scale_id = field_id( '[context_appearance][' . $context . '][scale]' );
        echo '<div class="nwz-field"><label for="' . esc_attr( $scale_id ) . '">' . esc_html__( 'Scale (%)', 'nibwoz-hcaptcha' ) . '</label><input type="number" id="' . esc_attr( $scale_id ) . '" name="' . esc_attr( OPTION . '[context_appearance][' . $context . '][scale]' ) . '" value="' . esc_attr( (string) ( $values['scale'] ?? '' ) ) . '" placeholder="' . esc_attr__( 'General default', 'nibwoz-hcaptcha' ) . '" min="70" max="130"></div>';
        select_field( '[context_appearance][' . $context . '][alignment]', title( 'alignment' ), $values['alignment'] ?? 'inherit', [ 'inherit' => __( 'General default', 'nibwoz-hcaptcha' ) ] + options( 'alignment' ) );
        echo '</div><p class="description">' . esc_html__( 'Theme, language, spacing and behavior follow the general settings.', 'nibwoz-hcaptcha' ) . '</p></details>';
    }
    echo '</div></section></section>';

    panel_open( 'network', $active );
    Ui::page_head( __( 'Protection and access', 'nibwoz-hcaptcha' ), __( 'Security and whitelist', 'nibwoz-hcaptcha' ), __( 'Control exemptions, traffic origin and failure handling.', 'nibwoz-hcaptcha' ) );
    echo '<div class="nwz-grid-2"><section class="nwz-card"><h3>' . esc_html__( 'Your access and exemptions', 'nibwoz-hcaptcha' ) . '</h3><p>' . esc_html__( 'Check the detected IP before granting an exemption. Dynamic IPs can change.', 'nibwoz-hcaptcha' ) . '</p>';
    echo '<div class="nwz-hc-current-ip"><div><span>' . esc_html__( 'Current detected IP', 'nibwoz-hcaptcha' ) . '</span><code>' . esc_html( '' !== $current_ip ? $current_ip : __( 'Unavailable', 'nibwoz-hcaptcha' ) ) . '</code></div>';
    if ( '' !== $current_ip && ! in_rules( $current_ip, $config['trusted_ips'] ) ) {
        echo '<button type="submit" form="nwz-hc-allow-ip-form" class="button" data-nwz-busy-on-submit><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>' . esc_html__( 'Add IP to whitelist', 'nibwoz-hcaptcha' ) . '</button>';
    } elseif ( '' !== $current_ip ) {
        echo wp_kses_post( Ui::badge( true, __( 'Already in the whitelist', 'nibwoz-hcaptcha' ), '' ) );
    }
    echo '</div><p class="description">' . esc_html__( 'The IP shown uses the proxy options already saved. Quick add saves only this IP immediately; unsaved changes in the other fields are not applied.', 'nibwoz-hcaptcha' ) . '</p>';
    toggle( '[bypass_logged_admins]', ! empty( $config['bypass_logged_admins'] ), __( 'Exempt logged-in administrators', 'nibwoz-hcaptcha' ), __( 'When enabled, administrators who are already logged in skip the challenge.', 'nibwoz-hcaptcha' ) );
    echo '</section><section class="nwz-card"><h3>' . esc_html__( 'Exempt IPs', 'nibwoz-hcaptcha' ) . '</h3>';
    lines_field( '[trusted_ips]', __( 'IP/CIDR whitelist (one entry per line)', 'nibwoz-hcaptcha' ), $config['trusted_ips'], __( 'IPs or ranges entered here do not need to solve the challenge. Add one entry per line.', 'nibwoz-hcaptcha' ) );
    echo '</section></div>';
    echo '<section class="nwz-card nwz-hc-cloudflare is-' . esc_attr( $cf_status ) . '"><div class="nwz-card-head"><div class="nwz-hc-cloudflare-title"><img src="' . esc_url( plugins_url( 'assets/cloudflare-mark.svg', NIBWOZ_HCAPTCHA_FILE ) ) . '" width="48" height="30" alt=""><div><span>' . esc_html__( 'Detected network', 'nibwoz-hcaptcha' ) . '</span><h3>Cloudflare</h3></div></div>';
    echo wp_kses_post( in_array( $cf_status, [ 'verified', 'restored' ], true ) ? Ui::badge( true, $cf[0], '' ) : state( $cf[0] ) );
    echo '</div><p>' . esc_html( $cf[1] ) . '</p><p class="description">' . esc_html( $cf[2] ) . '</p>';
    toggle( '[network][trust_cloudflare]', ! empty( $config['network']['trust_cloudflare'] ), __( 'Trust the IP reported by Cloudflare', 'nibwoz-hcaptcha' ), __( 'Detection only reports the status; this option is never changed automatically.', 'nibwoz-hcaptcha' ) );
    echo '</section><div class="nwz-grid-2"><section class="nwz-card"><h3>' . esc_html__( 'Verification failure', 'nibwoz-hcaptcha' ) . '</h3>';
    toggle( '[security][fail_closed]', $fail_closed, title( 'fail_closed' ), __( 'Blocks submission if hCaptcha does not respond or returns invalid data.', 'nibwoz-hcaptcha' ) );
    echo '</section><section class="nwz-card"><h3>' . esc_html__( 'Own proxy', 'nibwoz-hcaptcha' ) . '</h3><p class="description">' . esc_html__( 'Use only for Nginx, HAProxy or another proxy under your control.', 'nibwoz-hcaptcha' ) . '</p>';
    toggle( '[network][trust_forwarded_headers]', ! empty( $config['network']['trust_forwarded_headers'] ), title( 'trust_forwarded_headers' ) );
    lines_field( '[network][trusted_proxy_cidrs]', title( 'trusted_proxy_cidrs' ), $config['network']['trusted_proxy_cidrs'], __( 'One proxy IP or CIDR per line; never enter the visitor IP.', 'nibwoz-hcaptcha' ) );
    echo '</section></div></section>';

    panel_open( 'diagnostics', $active );
    Ui::page_head( __( 'Diagnostics', 'nibwoz-hcaptcha' ), __( 'Environment and status', 'nibwoz-hcaptcha' ), __( 'Read-only checks of this installation. Nothing here contacts external services.', 'nibwoz-hcaptcha' ) );
    $compatible = version_compare( get_bloginfo( 'version' ), '7.0', '>=' ) && version_compare( PHP_VERSION, '8.3', '>=' );
    echo '<div class="nwz-grid-2"><section class="nwz-card"><div class="nwz-card-head"><h3>' . esc_html__( 'Environment', 'nibwoz-hcaptcha' ) . '</h3>' . wp_kses_post( Ui::badge( $compatible, __( 'Compatible', 'nibwoz-hcaptcha' ), __( 'Below the requirements', 'nibwoz-hcaptcha' ) ) ) . '</div>';
    Ui::dl( [
        [ 'WordPress', esc_html( get_bloginfo( 'version' ) ) ],
        [ 'PHP', esc_html( PHP_VERSION ) ],
        [ __( 'Installation', 'nibwoz-hcaptcha' ), esc_html( is_multisite() ? 'Multisite' : __( 'Single site', 'nibwoz-hcaptcha' ) ) ],
        [ __( 'Requirements', 'nibwoz-hcaptcha' ), esc_html__( 'WordPress 7.0 or later, PHP 8.3 or later', 'nibwoz-hcaptcha' ) ],
    ] );
    echo '</section><section class="nwz-card"><h3>' . esc_html__( 'Protection', 'nibwoz-hcaptcha' ) . '</h3>';
    Ui::dl( [
        [ __( 'Access keys', 'nibwoz-hcaptcha' ), Ui::badge( $key_ready, __( 'Configured', 'nibwoz-hcaptcha' ), __( 'Incomplete', 'nibwoz-hcaptcha' ) ) ],
        /* translators: 1: enabled forms, 2: total forms. */
        [ __( 'Protected forms', 'nibwoz-hcaptcha' ), esc_html( sprintf( __( '%1$d of %2$d active', 'nibwoz-hcaptcha' ), $enabled, count( $contexts ) ) ) ],
        [ __( 'Service failure', 'nibwoz-hcaptcha' ), Ui::badge( $fail_closed, __( 'Block', 'nibwoz-hcaptcha' ), __( 'Allow', 'nibwoz-hcaptcha' ) ) ],
        [ __( 'Legacy PHP file', 'nibwoz-hcaptcha' ), Ui::badge( ! is_file( WP_CONTENT_DIR . '/nibwoz-hcaptcha-config.php' ), __( 'Not present', 'nibwoz-hcaptcha' ), __( 'Present', 'nibwoz-hcaptcha' ) ) ],
    ] );
    echo '</section><section class="nwz-card"><h3>' . esc_html__( 'Network', 'nibwoz-hcaptcha' ) . '</h3>';
    Ui::dl( [
        [ __( 'Cloudflare', 'nibwoz-hcaptcha' ), esc_html( $cf[0] ) ],
        [ __( 'Current detected IP', 'nibwoz-hcaptcha' ), '<code>' . esc_html( '' !== $current_ip ? $current_ip : __( 'Unavailable', 'nibwoz-hcaptcha' ) ) . '</code>' ],
        [ __( 'Whitelist', 'nibwoz-hcaptcha' ), state( '' !== $current_ip && in_rules( $current_ip, $config['trusted_ips'] ) ? __( 'Current IP exempt', 'nibwoz-hcaptcha' ) : __( 'Current IP not exempt', 'nibwoz-hcaptcha' ) ) ],
    ] );
    echo '</section><section class="nwz-card"><h3>' . esc_html__( 'Diagnostic log', 'nibwoz-hcaptcha' ) . '</h3><p class="description">' . esc_html__( 'Logs only the verification context and result, without IPs, tokens or keys.', 'nibwoz-hcaptcha' ) . '</p>';
    toggle( '[debug]', ! empty( $config['debug'] ), __( 'Diagnostic log', 'nibwoz-hcaptcha' ), __( 'Enable while investigating and disable when done.', 'nibwoz-hcaptcha' ) );
    echo '</section></div></section>';
    echo '</form>';
    echo '<form id="nwz-hc-allow-ip-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="nibwoz_hcaptcha_allow_current_ip"><input type="hidden" name="current_ip" value="' . esc_attr( $current_ip ) . '">';
    wp_nonce_field( 'nibwoz_hcaptcha_allow_current_ip_' . $current_ip );
    echo '</form>';

    panel_open( 'about', $active );
    Ui::page_head( __( 'Information', 'nibwoz-hcaptcha' ), __( 'About', 'nibwoz-hcaptcha' ), __( 'Development, environment, support and storage of NIBWOZ hCaptcha.', 'nibwoz-hcaptcha' ) );
    echo '<div class="nwz-grid-2"><section class="nwz-card"><h3>' . esc_html__( 'Development', 'nibwoz-hcaptcha' ) . '</h3>';
    Ui::dl( [
        [ __( 'Developer', 'nibwoz-hcaptcha' ), 'Brinsley Aragão' ],
        [ __( 'Company', 'nibwoz-hcaptcha' ), 'NIBWOZ' ],
        [ __( 'Installed version', 'nibwoz-hcaptcha' ), esc_html( NIBWOZ_HCAPTCHA_VERSION ) ],
        [ __( 'Interface kit', 'nibwoz-hcaptcha' ), esc_html( 'NIBWOZ UI ' . Ui::VERSION ) ],
    ] );
    echo '</section><section class="nwz-card"><h3>' . esc_html__( 'Support and documentation', 'nibwoz-hcaptcha' ) . '</h3><p>' . esc_html__( 'For questions about the plugin or services, use the official company channels.', 'nibwoz-hcaptcha' ) . '</p><p><a href="mailto:falecom@nibwoz.com">falecom@nibwoz.com</a></p><div class="nwz-actions"><a class="button" href="https://central.nibwoz.com/knowledgebase" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-book" aria-hidden="true"></span>' . esc_html__( 'Knowledge base', 'nibwoz-hcaptcha' ) . '</a><a class="button" href="https://central.nibwoz.com/submitticket.php?step=2&amp;deptid=1" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-sos" aria-hidden="true"></span>' . esc_html__( 'Open a ticket', 'nibwoz-hcaptcha' ) . '</a></div></section>';
    echo '<section class="nwz-card"><h3>' . esc_html__( 'About NIBWOZ', 'nibwoz-hcaptcha' ) . '</h3><p>' . esc_html__( 'Hosting, WordPress, domains, development and technical support.', 'nibwoz-hcaptcha' ) . '</p><div class="nwz-actions"><a class="button button-primary" href="https://nibwoz.com/" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-admin-site-alt3" aria-hidden="true"></span>' . esc_html__( 'NIBWOZ website', 'nibwoz-hcaptcha' ) . '</a><a class="button" href="https://nibwoz.com/sobre/" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-building" aria-hidden="true"></span>' . esc_html__( 'About the company', 'nibwoz-hcaptcha' ) . '</a></div></section>';
    echo '<section class="nwz-card"><h3>' . esc_html__( 'Storage and connections', 'nibwoz-hcaptcha' ) . '</h3><ul class="nwz-list"><li>' . esc_html__( 'Settings are stored in this WordPress database; the secret key is not displayed in the panel.', 'nibwoz-hcaptcha' ) . '</li><li>' . esc_html__( 'The plugin loads the hCaptcha script and sends the token to the hCaptcha API for validation. It does not send settings or telemetry to NIBWOZ.', 'nibwoz-hcaptcha' ) . '</li><li>' . esc_html__( 'The diagnostic log, if enabled, is kept in the server temporary folder. Uninstalling does not remove the log or the legacy PHP file.', 'nibwoz-hcaptcha' ) . '</li></ul><p><a href="https://nibwoz.com/politica-de-privacidade/" target="_blank" rel="noopener noreferrer">' . esc_html__( 'NIBWOZ privacy policy', 'nibwoz-hcaptcha' ) . '</a></p></section></div>';
    echo '<div class="nwz-grid-2"><section class="nwz-card nwz-card-critical"><h3>' . esc_html__( 'Uninstall', 'nibwoz-hcaptcha' ) . '</h3><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="nibwoz_hcaptcha_save_uninstall">';
    wp_nonce_field( 'nibwoz_hcaptcha_save_uninstall' );
    Ui::toggle( [ 'id' => 'nwz-hc-uninstall-cleanup', 'name' => 'uninstall_cleanup', 'checked' => ! empty( $config['uninstall_cleanup'] ), 'label' => __( 'Delete all data on uninstall', 'nibwoz-hcaptcha' ), 'help' => __( 'When enabled, deleting the plugin from the dashboard removes all settings from the database, including the Site key and Secret key.', 'nibwoz-hcaptcha' ) ] );
    echo '<p class="description">' . esc_html__( 'Off is the safe default: deactivating or deleting the plugin keeps the data for a possible reinstall. The legacy PHP file and the temporary log are not removed.', 'nibwoz-hcaptcha' ) . '</p><div class="nwz-actions"><button class="button" type="submit" data-nwz-busy-on-submit><span class="dashicons dashicons-saved" aria-hidden="true"></span>' . esc_html__( 'Save preference', 'nibwoz-hcaptcha' ) . '</button></div></form></section>';
    echo '<section class="nwz-card nwz-danger"><h3>' . esc_html__( 'Restore default settings', 'nibwoz-hcaptcha' ) . '</h3><p>' . esc_html__( 'Restores forms, appearance, security, network, whitelist, diagnostics and the uninstall preference.', 'nibwoz-hcaptcha' ) . '</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="nibwoz_hcaptcha_reset">';
    wp_nonce_field( 'nibwoz_hcaptcha_reset' );
    Ui::toggle( [ 'id' => 'nwz-hc-keep-keys', 'name' => 'keep_keys', 'checked' => true, 'label' => __( 'Keep current Site key and Secret key', 'nibwoz-hcaptcha' ) ] );
    echo '<label class="nwz-hc-confirm" for="nwz-hc-confirm-reset"><input type="checkbox" id="nwz-hc-confirm-reset" name="confirm_reset" value="1" required><span>' . esc_html__( 'I understand that all other settings will be restored; if I uncheck the option above, both keys will also be deleted.', 'nibwoz-hcaptcha' ) . '</span></label>';
    echo '<div class="nwz-actions"><button class="button nwz-button-danger" type="submit" data-nwz-busy-on-submit><span class="dashicons dashicons-image-rotate" aria-hidden="true"></span>' . esc_html__( 'Restore default settings', 'nibwoz-hcaptcha' ) . '</button></div></form></section></div></section>';
    Ui::shell_close();
}
