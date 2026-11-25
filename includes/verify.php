<?php
namespace NIBWOZ\HCaptcha\Verify;
use function NIBWOZ\HCaptcha\Config\get;
use function NIBWOZ\HCaptcha\Network\required;
use function NIBWOZ\HCaptcha\Network\client_ip;
use function NIBWOZ\HCaptcha\Logger\write;
defined( 'ABSPATH' ) || exit;
function token(): string {
    // Token do hCaptcha enviado pelo formulário protegido; a autenticidade é conferida na API do hCaptcha, não por nonce.
    $raw = isset( $_POST['h-captcha-response'] ) && is_string( $_POST['h-captcha-response'] ) ? sanitize_text_field( wp_unslash( $_POST['h-captcha-response'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Runs inside core login/registration/comment handlers; the hCaptcha token itself is the anti-forgery check.
    return trim( $raw );
}
function check( string $context ): bool {
    if ( ! required( $context ) ) { return true; }
    $secret = get( 'secret_key', '' ); $site = get( 'site_key', '' );
    if ( ! is_string( $secret ) || '' === trim( $secret ) || ! is_string( $site ) || '' === trim( $site ) ) { write( 'configured_incorrectly', $context ); return false; }
    $token = token();
    if ( '' === $token || strlen( $token ) > 4096 ) { write( 'missing_token', $context ); return false; }
    // Uma mesma requisição pode acionar mais de um hook do mesmo formulário.
    static $verified = []; static $used = [];
    $fingerprint = hash( 'sha256', $token ); $key = $context . ':' . $fingerprint;
    if ( array_key_exists( $key, $verified ) ) { return $verified[ $key ]; }
    if ( isset( $used[ $fingerprint ] ) ) { write( 'invalid_token', $context, 'reused' ); return false; }
    $used[ $fingerprint ] = true;
    $body = [ 'secret' => $secret, 'response' => $token, 'sitekey' => $site ];
    $ip = client_ip(); if ( '' !== $ip ) { $body['remoteip'] = $ip; }
    $response = wp_remote_post( 'https://api.hcaptcha.com/siteverify', [
        'timeout' => 10, 'redirection' => 0,
        'headers' => [ 'Accept' => 'application/json', 'Content-Type' => 'application/x-www-form-urlencoded' ],
        'body' => $body,
    ] );
    if ( is_wp_error( $response ) ) { write( 'api_error', $context, 'transport' ); return $verified[ $key ] = ( false === get( 'security.fail_closed' ) ); }
    $status = (int) wp_remote_retrieve_response_code( $response );
    $raw = wp_remote_retrieve_body( $response );
    $data = 200 === $status && strlen( $raw ) < 16384 ? json_decode( $raw, true ) : null;
    if ( ! is_array( $data ) || ! isset( $data['success'] ) || ! is_bool( $data['success'] ) ) {
        write( 'api_error', $context, 'response' ); return $verified[ $key ] = ( false === get( 'security.fail_closed' ) );
    }
    write( $data['success'] ? 'verified' : 'invalid_token', $context );
    return $verified[ $key ] = $data['success'];
}
function message(): string {
    return __( 'Could not validate hCaptcha. Complete the verification and try again.', 'nibwoz-hcaptcha' );
}
