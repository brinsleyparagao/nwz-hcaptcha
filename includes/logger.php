<?php
namespace NIBWOZ\HCaptcha\Logger;
use function NIBWOZ\HCaptcha\Config\get;
defined( 'ABSPATH' ) || exit;
function write( string $event, string $context = '', string $code = '' ): void {
    if ( true !== get( 'debug' ) ) { return; }
    // Apenas valores fixos internos; nenhum token, segredo, IP, mensagem remota ou corpo de resposta.
    if ( ! in_array( $event, [ 'missing_token', 'invalid_token', 'api_error', 'configured_incorrectly', 'conflict', 'verified' ], true ) ) { return; }
    $tmp = realpath( sys_get_temp_dir() );
    $webroot = realpath( ABSPATH );
    if ( ! $tmp || ( $webroot && ( $tmp === $webroot || 0 === strpos( $tmp . '/', rtrim( $webroot, '/' ) . '/' ) ) ) ) { return; }
    // Diretório temporário fora da raiz web, com nome isolado por instalação.
    $salt = defined( 'AUTH_KEY' ) ? AUTH_KEY : ABSPATH;
    $dir = $tmp . '/nibwoz-hcaptcha-' . substr( hash( 'sha256', ABSPATH . $salt ), 0, 20 );
    if ( ! is_dir( $dir ) && ! @mkdir( $dir, 0700, true ) ) { return; } // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir, WordPress.PHP.NoSilencedErrors.Discouraged -- Private append-only log in the system temp dir (outside the web root) needs flock/append, which WP_Filesystem does not offer.
    if ( ! is_file( $dir . '/index.php' ) ) { @file_put_contents( $dir . '/index.php', "<?php\n// Silence.\n" ); } // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPress.PHP.NoSilencedErrors.Discouraged -- Private append-only log in the system temp dir (outside the web root) needs flock/append, which WP_Filesystem does not offer.
    $file = $dir . '/nibwoz-hcaptcha-debug.log';
    $stream = @fopen( $file, 'c+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.PHP.NoSilencedErrors.Discouraged -- Private append-only log in the system temp dir (outside the web root) needs flock/append, which WP_Filesystem does not offer.
    if ( ! $stream ) { return; }
    if ( flock( $stream, LOCK_EX ) ) {
        if ( fstat( $stream )['size'] > 1048576 ) { ftruncate( $stream, 0 ); rewind( $stream ); }
        fseek( $stream, 0, SEEK_END );
        fwrite( $stream, gmdate( 'c' ) . ' ' . $event . ' ' . preg_replace( '/[^a-z0-9_]/', '', $context ) . ' ' . preg_replace( '/[^a-z0-9_-]/', '', $code ) . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Private append-only log in the system temp dir (outside the web root) needs flock/append, which WP_Filesystem does not offer.
        flock( $stream, LOCK_UN );
    }
    fclose( $stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Private append-only log in the system temp dir (outside the web root) needs flock/append, which WP_Filesystem does not offer.
    @chmod( $file, 0600 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod, WordPress.PHP.NoSilencedErrors.Discouraged -- Private append-only log in the system temp dir (outside the web root) needs flock/append, which WP_Filesystem does not offer.
}
