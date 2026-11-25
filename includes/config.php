<?php
namespace NIBWOZ\HCaptcha\Config;
defined( 'ABSPATH' ) || exit;
// Versão 1.2.2: opções do banco e escala proporcional por contexto.
const OPTION = 'nibwoz_hcaptcha_settings';
function defaults(): array {
    return [
        'site_key' => '', 'secret_key' => '', 'trusted_ips' => [],
        'bypass_logged_admins' => true, 'unknown_contexts_enabled' => true,
        'contexts' => array_fill_keys( [ 'wp_login', 'wp_register', 'wp_lost_password', 'wp_reset_password', 'wp_comments', 'wc_login', 'wc_register', 'wc_lost_password', 'wc_reset_password', 'wc_checkout' ], true ),
        'appearance' => [ 'theme' => 'light', 'size' => 'normal', 'scale' => 100, 'language' => 'auto', 'alignment' => 'center', 'spacing_top' => 16, 'spacing_bottom' => 20 ],
        'behavior' => [ 'mode' => 'visible', 'render' => 'auto', 'reset_on_expire' => true, 'show_error_messages' => true, 'recaptcha_compat' => false ],
        'context_appearance' => [],
        'context_behavior' => [],
        'security' => [ 'fail_closed' => true ],
        'network' => [ 'trust_cloudflare' => false, 'trust_forwarded_headers' => false, 'trusted_proxy_cidrs' => [] ],
        'debug' => false,
        'uninstall_cleanup' => false,
    ];
}
function all(): array {
    $saved = get_option( OPTION, null );
    if ( is_array( $saved ) ) { return array_replace_recursive( defaults(), $saved ); }
    // Versão 1.3.2: o arquivo PHP legado é lido uma única vez por requisição (antes, a cada leitura de configuração).
    static $legacy = null;
    if ( null === $legacy ) {
        $path = WP_CONTENT_DIR . '/nibwoz-hcaptcha-config.php';
        $loaded = is_file( $path ) ? require $path : [];
        $legacy = is_array( $loaded ) ? $loaded : [];
    }
    return array_replace_recursive( defaults(), $legacy );
}
function get( string $key, $fallback = null ) {
    $value = all();
    foreach ( explode( '.', $key ) as $part ) {
        if ( ! is_array( $value ) || ! array_key_exists( $part, $value ) ) { return $fallback; }
        $value = $value[ $part ];
    }
    return $value;
}
function enabled( string $context ): bool {
    $contexts = get( 'contexts', [] );
    return is_array( $contexts ) && array_key_exists( $context, $contexts ) ? true === $contexts[ $context ] : true === get( 'unknown_contexts_enabled' );
}
function appearance( string $context ): array {
    $base = get( 'appearance', [] );
    $overrides = get( 'context_appearance.' . $context, [] );
    $allowed = [];
    if ( is_array( $overrides ) ) {
        foreach ( [ 'size', 'scale', 'alignment' ] as $key ) {
            if ( array_key_exists( $key, $overrides ) ) { $allowed[ $key ] = $overrides[ $key ]; }
        }
    }
    $a = array_merge( is_array( $base ) ? $base : [], $allowed );
    $a['theme'] = in_array( $a['theme'] ?? '', [ 'dark', 'light' ], true ) ? $a['theme'] : 'light';
    $a['size'] = in_array( $a['size'] ?? '', [ 'normal', 'compact' ], true ) ? $a['size'] : 'normal';
    $a['scale'] = max( 70, min( 130, (int) ( $a['scale'] ?? 100 ) ) );
    $a['language'] = preg_match( '/^[a-z]{2,3}(?:-[A-Z]{2})?$/', (string) ( $a['language'] ?? '' ) ) ? $a['language'] : 'auto';
    $a['alignment'] = in_array( $a['alignment'] ?? '', [ 'left', 'center', 'right' ], true ) ? $a['alignment'] : 'center';
    foreach ( [ 'spacing_top', 'spacing_bottom' ] as $key ) { $a[ $key ] = max( 0, min( 100, (int) ( $a[ $key ] ?? 0 ) ) ); }
    return $a;
}
function behavior( string $context ): array {
    $base = get( 'behavior', [] );
    $b = is_array( $base ) ? $base : [];
    $b['mode'] = 'invisible' === ( $b['mode'] ?? '' ) ? 'invisible' : 'visible';
    $b['render'] = 'explicit' === ( $b['render'] ?? '' ) ? 'explicit' : 'auto';
    return $b;
}
