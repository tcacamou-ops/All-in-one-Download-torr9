<?php
namespace AllI1D\Tr4ker\Components;

use AllI1D\Helpers\Crypto;

class Credentials {
    public function get_html(): string {
        ob_start();
        $this->render();
        return ob_get_clean() ?: '';
    }

    public function render() {
        echo '<label for="tr4ker_api_key">' . __('Tr4ker API Key', 'all-in-one-download-torr9') . '</label>';
        echo '<input type="password" id="tr4ker_api_key" name="tr4ker_api_key" placeholder="' . esc_attr( __( 'Tr4ker API Key', 'all-in-one-download-torr9' ) ) . '" required value="' . esc_attr( Crypto::decrypt( get_option( 'alli1d_tr4ker_api_key', '' ) ) ) . '" />';
        echo '<br /><br />';
        echo '<button type="button" id="submit-tr4ker-credentials">' . __('Save', 'all-in-one-download-torr9') . '</button>';
        echo '<div id="url-message" style="margin-top: 10px;"></div>';
    }
}