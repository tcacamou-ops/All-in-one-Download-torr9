<?php
/**
 * Plugin Name: All-in-one Download Torr9
 * Plugin URI: https://github.com/tcacamou-ops/All-in-one-Download-torr9
 * Description: Add-on for All-in-one Download that allows downloading torrents from Torr9.
 * Version: 0.0.4
 * Author: tcacamou
 * Author URI: https://github.com/tcacamou-ops
 * Text Domain: all-in-one-download-torr9
 * Domain Path: /languages
 */

namespace AllI1D\Torr9;

use AllI1D\Torr9\Components\Credentials;
use AllI1D\Torr9\Filters\Torr9Movies;
use AllI1D\Torr9\Filters\Torr9TvShows;
use AllI1D\Torr9\Filters\Status;
use AllI1D\Helpers\Crypto;
use honemo\updater\Updater;

// Security: prevent direct file access.
if (!defined('ABSPATH')) {
    exit;
}

// Define the plugin absolute path constant.
if (!defined('AllI1D_TORR9_DIR')) {
    define('AllI1D_TORR9_DIR', plugin_dir_path(__FILE__));
}

// Define the plugin URL constant.
if (!defined('AllI1D_TORR9_URL')) {
    define('AllI1D_TORR9_URL', plugin_dir_url(__FILE__));
}

// Include the Composer autoloader.
require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';

class Plugin {
    public function __construct() {
        $this->initialize_admin();
        $this->initialize_api();
        $this->initialize_filters();
    }

    private function initialize_admin() {
        if ( is_admin() ) {
            new Admin();
            $updater = new Updater(
                __FILE__,                                      // Main plugin file.
                'https://github.com/tcacamou-ops/All-in-one-Download-torr9'  // Repository URL.
            );

            $updater->init();
        }
    }

    private function initialize_api() {
        Api::get_instance();
    }

    private function initialize_filters() {
        $Torr9ApiMovies  = new Torr9Movies();
        $Torr9ApiTvShows = new Torr9TvShows();
        add_filter( 'alli1d_process_tvshow', [$Torr9ApiTvShows, 'process_tv_show'] );
        add_filter( 'alli1d_process_movie', [$Torr9ApiMovies, 'process_movie'] );
        add_filter( 'alli1d_process_status', [Status::class, 'process_status'] );
        add_filter( 'alli1d_provider_settings_modals', [$this, 'register_modal'] );
        add_action( 'admin_init', [$this, 'migrate_credentials_encryption'] );
    }

    public function migrate_credentials_encryption(): void {
        $migrated_key = 'alli1d_torr9_credentials_encrypted_v1';
        if ( get_option( $migrated_key ) ) {
            return;
        }
        $api_key = get_option( 'alli1d_torr9_api_key', '' );
        if ( '' !== $api_key && 0 !== strpos( $api_key, 'enc:' ) ) {
            update_option( 'alli1d_torr9_api_key', Crypto::encrypt( $api_key ) );
        }
        $full_token = get_option( 'alli1d_torr9_full_token', '' );
        if ( '' !== $full_token && 0 !== strpos( $full_token, 'enc:' ) ) {
            update_option( 'alli1d_torr9_full_token', Crypto::encrypt( $full_token ) );
        }
        update_option( $migrated_key, true );
    }

    public function register_modal( array $modals ): array {
        $credentials = new Credentials();
        $modals['Torr9'] = [
            'title' => __( 'Torr9 Settings', 'all-in-one-download-torr9' ),
            'html'  => $credentials->get_html(),
        ];
        return $modals;
    }
}


// Initialize the plugin.
new Plugin();