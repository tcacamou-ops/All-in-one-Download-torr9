=== All-in-one Download Torr9 ===
Contributors: tcacamou
Tags: torrent, download, torr9, all-in-one-download
Requires at least: 5.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 0.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add-on for All-in-one Download that allows downloading torrents from Torr9.

== Description ==

All-in-one Download Torr9 is an add-on for the All-in-one Download plugin. It integrates with the Torr9 API to automatically search and download `.torrent` files for movies and TV shows.

Features:

* Automatically search for torrents matching a movie or TV show title via the Torr9 API.
* Support for season and episode filtering for TV shows.
* Language filtering (French audio: VFF, TRUEFRENCH, FRENCH).
* Downloads the best available torrent (sorted by seeders) and stores it in the WordPress uploads directory.
* Settings page in the WordPress admin to store your Torr9 API credentials.
* Auto-updates via GitHub releases.

== Requirements ==

* WordPress 5.0 or higher
* PHP 8.0 or higher
* All-in-one Download plugin (main plugin)
* A valid Torr9 API key and full token (https://www.torr9.net)

== Installation ==

1. Upload the `all-in-one-download-torr9` folder to the `/wp-content/plugins/` directory.
2. Run `composer install` inside the plugin folder to install dependencies.
3. Activate the plugin through the "Plugins" menu in WordPress.
4. Navigate to **All-in-one Download > Torr9** in the WordPress admin.
5. Enter your Torr9 API Key and Full Token, then click **Save**.

== Configuration ==

After activation, go to **All-in-one Download > Torr9** and fill in:

* **Torr9 API Key** — your personal API key from Torr9.
* **Torr9 Full Token** — your full authentication token from Torr9.

These credentials are stored securely as WordPress options and are used to authenticate requests to the Torr9 API.

== Frequently Asked Questions ==

= Where do I get my Torr9 API Key and Full Token? =

Log in to your account on https://www.torr9.net and navigate to your profile settings to retrieve your API key and full token.

= Where are the downloaded torrent files stored? =

Torrent files are saved to `wp-content/uploads/torr9/`.

= Does this plugin work independently? =

No. This plugin is an add-on and requires the All-in-one Download plugin to be installed and active.

== Changelog ==

= 0.0.1 =
* Initial release.
* Movie and TV show torrent search via Torr9 API.
* Admin settings page for API credentials.
* Auto-update support via GitHub.

== Upgrade Notice ==

= 0.0.1 =
Initial release.
