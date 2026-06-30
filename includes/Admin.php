<?php

namespace AllI1D\Torr9;
use AllI1D\Torr9\Api;

class Admin {
    public function __construct() {
        add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts']);
    }

    public function admin_enqueue_scripts( string $hook ): void {
        if ( 'downloads_page_all-in-one-download-status' !== $hook ) {
            return;
        }
        wp_enqueue_script(
            'allI1d-torr9-admin',
            AllI1D_TORR9_URL . 'assets/js/components/credentials.js',
            ['jquery'],
            '1.0.0'
        );
        $api = Api::get_instance();
        wp_localize_script(
            'allI1d-torr9-admin',
            'allI1d_torr9',
            [
                'api' => $api->get_data(),
            ]
        );
    }
}
