<?php
namespace AllI1D\Torr9\Components;

use AllI1D\Helpers\Crypto;

class Credentials {
    public function get_html(): string {
        ob_start();
        $this->render();
        return ob_get_clean() ?: '';
    }

    public function render() {
        echo '<label for="torr9_api_key">' . __('Torr9 API Key', 'all-in-one-download-torr9') . '</label>';
        echo '<input type="password" id="torr9_api_key" name="torr9_api_key" placeholder="' . esc_attr( __( 'Torr9 API Key', 'all-in-one-download-torr9' ) ) . '" required value="' . esc_attr( Crypto::decrypt( get_option( 'alli1d_torr9_api_key', '' ) ) ) . '" />';
        echo '<br /><br />';
        echo '<label for="torr9_full_token">' . __('Torr9 Full Token', 'all-in-one-download-torr9') . '</label>';
        echo '<input type="password" id="torr9_full_token" name="torr9_full_token" placeholder="' . esc_attr( __( 'Torr9 Full Token', 'all-in-one-download-torr9' ) ) . '" required value="' . esc_attr( Crypto::decrypt( get_option( 'alli1d_torr9_full_token', '' ) ) ) . '" />';
        echo '<br /><br />';
        echo '<button type="button" id="submit-torr9-credentials">' . __('Save', 'all-in-one-download-torr9') . '</button>';
        echo '<div id="url-message" style="margin-top: 10px;"></div>';
    }
}