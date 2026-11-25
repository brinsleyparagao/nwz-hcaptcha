<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// A configuração pertence a cada site; a limpeza só ocorre quando foi autorizada no painel.
$nibwoz_hcaptcha_option = 'nibwoz_hcaptcha_settings';
$nibwoz_hcaptcha_cleanup = static function () use ( $nibwoz_hcaptcha_option ): void {
    $settings = get_option( $nibwoz_hcaptcha_option, [] );
    if ( ! is_array( $settings ) || empty( $settings['uninstall_cleanup'] ) ) { return; }
    delete_option( $nibwoz_hcaptcha_option );
};
if ( is_multisite() ) {
    $nibwoz_hcaptcha_site_ids = get_sites( [ 'fields' => 'ids', 'number' => 0 ] );
    foreach ( $nibwoz_hcaptcha_site_ids as $nibwoz_hcaptcha_site_id ) {
        switch_to_blog( $nibwoz_hcaptcha_site_id );
        $nibwoz_hcaptcha_cleanup();
        restore_current_blog();
    }
} else {
    $nibwoz_hcaptcha_cleanup();
}
