<?php
/**
 * Plugin Name: All-in-one Download Torr9
 * Plugin URI: https://github.com/tcacamou-ops/All-in-one-Download-torr9
 * Description: Add-on for All-in-one Download that allows downloading torrents from Torr9.
 * Version: 0.0.2
 * Author: tcacamou
 * Author URI: https://github.com/tcacamou-ops
 * Text Domain: all-in-one-download-torr9
 * Domain Path: /languages
 */

namespace AllI1D\Torr9;

use AllI1D\Torr9\Filters\Torr9Movies;
use AllI1D\Torr9\Filters\Torr9TvShows;
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
		$Torr9ApiMovies = new Torr9Movies();
		$Torr9ApiTvShows = new Torr9TvShows();
        add_filter( 'alli1d_process_tvshow', [$Torr9ApiTvShows,'process_tv_show']);
        add_filter( 'alli1d_process_movie', [$Torr9ApiMovies,'process_movie']);
    }
}


// Initialize the plugin.
new Plugin();