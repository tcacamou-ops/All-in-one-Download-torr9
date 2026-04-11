<?php
namespace AllI1D\Torr9\Pages;

use AllI1D\Torr9\Components\Credentials;

class Settings {
    public function render() {
        $credentials = new Credentials();
        echo '<div class="wrap">';
        echo '<h1>' . __('Torr9 Settings', 'all-in-one-download-torr9') . '</h1>';
        $credentials->render();
        
        echo '</div>';
        
    }
}