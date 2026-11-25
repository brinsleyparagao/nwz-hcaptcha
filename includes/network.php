<?php
namespace NIBWOZ\HCaptcha\Network;
use function NIBWOZ\HCaptcha\Config\get;
defined( 'ABSPATH' ) || exit;
// Versão 1.2.27: impede que o IP da borda seja tratado como visitante sem confiança ativada.
function matches( string $ip, string $rule ): bool {
    $ip = trim( $ip ); $rule = trim( $rule );
    $binary = @inet_pton( $ip );
    if ( false === $binary || false === filter_var( $ip, FILTER_VALIDATE_IP ) ) { return false; }
    $parts = explode( '/', $rule );
    if ( count( $parts ) > 2 || false === filter_var( $parts[0], FILTER_VALIDATE_IP ) ) { return false; }
    $network = @inet_pton( $parts[0] );
    if ( false === $network || strlen( $binary ) !== strlen( $network ) ) { return false; }
    if ( 1 === count( $parts ) ) { return hash_equals( $binary, $network ); }
    if ( ! ctype_digit( $parts[1] ) || strlen( $parts[1] ) > 3 ) { return false; }
    $bits = (int) $parts[1]; $max = 8 * strlen( $binary );
    if ( $bits > $max ) { return false; }
    $bytes = intdiv( $bits, 8 ); $remainder = $bits % 8;
    if ( substr( $binary, 0, $bytes ) !== substr( $network, 0, $bytes ) ) { return false; }
    return 0 === $remainder || ( ( ord( $binary[ $bytes ] ) ^ ord( $network[ $bytes ] ) ) & ( 0xff << ( 8 - $remainder ) ) ) === 0;
}
function in_rules( string $ip, $rules ): bool {
    if ( ! is_array( $rules ) ) { return false; }
    foreach ( $rules as $rule ) { if ( is_string( $rule ) && matches( $ip, $rule ) ) { return true; } }
    return false;
}
// https://www.cloudflare.com/ips/ — conferir a lista antes de distribuir atualizações.
function cloudflare_ranges(): array {
    return [ '173.245.48.0/20','103.21.244.0/22','103.22.200.0/22','103.31.4.0/22','141.101.64.0/18','108.162.192.0/18','190.93.240.0/20','188.114.96.0/20','197.234.240.0/22','198.41.128.0/17','162.158.0.0/15','104.16.0.0/13','104.24.0.0/14','172.64.0.0/13','131.0.72.0/22','2400:cb00::/32','2606:4700::/32','2803:f800::/32','2405:b500::/32','2405:8100::/32','2a06:98c0::/29','2c0f:f248::/32' ];
}
function same_ip( string $left, string $right ): bool {
    $left_binary = @inet_pton( $left );
    $right_binary = @inet_pton( $right );
    return false !== $left_binary && false !== $right_binary && strlen( $left_binary ) === strlen( $right_binary ) && hash_equals( $left_binary, $right_binary );
}
function has_cloudflare_ray( array $server ): bool {
    $ray = trim( (string) ( $server['HTTP_CF_RAY'] ?? '' ) );
    return '' !== $ray && strlen( $ray ) <= 128 && 1 === preg_match( '/^[A-Za-z0-9-]+$/', $ray );
}
/** Diagnóstico da requisição atual; cabeçalhos indiretos nunca autorizam bypass ou confiança automática. */
function cloudflare_status( ?array $server = null ): string {
    $server = $server ?? $_SERVER;
    $remote = trim( (string) ( $server['REMOTE_ADDR'] ?? '' ) );
    if ( false === filter_var( $remote, FILTER_VALIDATE_IP ) ) { return 'unknown'; }
    $cf = trim( (string) ( $server['HTTP_CF_CONNECTING_IP'] ?? '' ) );
    if ( in_rules( $remote, cloudflare_ranges() ) ) {
        return false !== filter_var( $cf, FILTER_VALIDATE_IP ) ? 'verified' : 'missing_header';
    }
    if ( false === filter_var( $cf, FILTER_VALIDATE_IP ) || ! has_cloudflare_ray( $server ) ) { return 'not_detected'; }
    return same_ip( $remote, $cf ) ? 'restored' : 'intermediate_proxy';
}
function client_ip( ?array $server = null ): string {
    $server = $server ?? $_SERVER;
    $remote = trim( (string) ( $server['REMOTE_ADDR'] ?? '' ) );
    if ( false === filter_var( $remote, FILTER_VALIDATE_IP ) ) { return ''; }
    if ( in_rules( $remote, cloudflare_ranges() ) ) {
        if ( true !== get( 'network.trust_cloudflare' ) ) { return ''; }
        $cf = trim( (string) ( $server['HTTP_CF_CONNECTING_IP'] ?? '' ) );
        // Um cabeçalho inválido não autoriza bypass por IP de borda.
        return false !== filter_var( $cf, FILTER_VALIDATE_IP ) ? $cf : '';
    }
    $trusted = get( 'network.trusted_proxy_cidrs', [] );
    if ( true === get( 'network.trust_forwarded_headers' ) && in_rules( $remote, $trusted ) ) {
        $xff = (string) ( $server['HTTP_X_FORWARDED_FOR'] ?? '' );
        $hops = array_map( 'trim', explode( ',', $xff ) );
        if ( ! $xff || count( $hops ) > 32 ) { return ''; }
        foreach ( $hops as $hop ) { if ( false === filter_var( $hop, FILTER_VALIDATE_IP ) ) { return ''; } }
        for ( $i = count( $hops ) - 1; $i >= 0; --$i ) {
            if ( ! in_rules( $hops[ $i ], $trusted ) ) { return $hops[ $i ]; }
        }
        return ''; // Cadeia inteiramente confiável, sem cliente identificável.
    }
    return $remote;
}
function bypass( string $context ): bool {
    $ip = client_ip();
    if ( true === get( 'bypass_logged_admins' ) && is_user_logged_in() && current_user_can( 'manage_options' ) ) { return true; }
    return '' !== $ip && in_rules( $ip, get( 'trusted_ips', [] ) );
}
function required( string $context ): bool {
    return \NIBWOZ\HCaptcha\Config\enabled( $context ) && ! bypass( $context );
}
