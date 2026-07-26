<?php
namespace AllI1D\Tr4ker\Pages;

use AllI1D\Tr4ker\Components\Credentials;

class Settings {
    public function render() {
        $credentials = new Credentials();
        echo '<div class="wrap">';
        echo '<h1>' . __('Tr4ker Settings', 'all-in-one-download-torr9') . '</h1>';
        $credentials->render();
        
        echo '</div>';
        
    }
}