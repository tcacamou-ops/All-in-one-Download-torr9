<?php

namespace AllI1D\Torr9;
use AllI1D\Torr9\Pages\Settings;
use AllI1D\Torr9\Api;
use AllI1D\Components\ToastMessage;

class Admin {
    public function __construct() {
        add_action('admin_menu', [$this, 'register_admin_menu'], 99);
        add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts']);

    }

    public function register_admin_menu() {
        add_submenu_page(
            'all-in-one-download',
            __('Torr9 settings', 'all-in-one-download-torr9'),
            __('Torr9', 'all-in-one-download-torr9'),
            'alli1d_admin',
            'all-in-one-download-torr9',
            [$this, 'settings_page'],
            99,
        );
    }

    public function admin_enqueue_scripts() {
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

    public function settings_page() {
        $toastMessage = new ToastMessage();
        $toastMessage->render();
        $settings = new Settings();
        $settings->render();
    }
}