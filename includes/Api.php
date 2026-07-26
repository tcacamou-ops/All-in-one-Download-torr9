<?php
namespace AllI1D\Tr4ker;

use AllI1D\Tr4ker\Api\CredentialsApi;

class Api
{
    public static $instance = null;

    public static $route_namespace = 'tr4ker/v1';

    public CredentialsApi $credentials_api;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        $this->credentials_api = new CredentialsApi(self::$route_namespace);
    }

    public function get_data() {
        $data = [
            'routes' => $this->get_routes(),
        ];
        return $data;
    }

    public function get_routes() {
        $routes = [];
        $routes = array_merge($this->credentials_api->get_routes(), $routes);
        return $routes;
    }
}
