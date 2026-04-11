<?php
namespace AllI1D\Torr9\Api;

class CredentialsApi {

    private $route_namespace;

    private $current_namespace = 'credentials';

    public function __construct(string $route_namespace) {
        $this->route_namespace = $route_namespace;
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function get_namespace(): string {
        return $this->route_namespace.'/'.$this->current_namespace;
    }

    public function check_permissions() :bool {
        return current_user_can('alli1d_admin');
    }

    public function get_routes():array {
        return [
            'credentials' => rest_url($this->get_namespace()),
        ];
    }

    public function register_routes() {
        register_rest_route(
            $this->route_namespace,
            $this->current_namespace,
            [
                'methods' => 'POST',
                'callback' => [$this, 'set_credentials'],
                'permission_callback' => [$this, 'check_permissions'],
            ]
        );
    }

    public function set_credentials($request) {
		$torr9_api_key = $request->get_param('torr9_api_key');
        if (empty($torr9_api_key)) {
            return new \WP_REST_Response(['status' => 'You need to specify an API key'], 400);
        }
        $torr9_full_token = $request->get_param('torr9_full_token');
        if (empty($torr9_full_token)) {
            return new \WP_REST_Response(['status' => 'You need to specify a full token'], 400);
        }
        update_option('alli1d_torr9_api_key', $torr9_api_key);
        update_option('alli1d_torr9_full_token', $torr9_full_token);
        return new \WP_REST_Response(['status' => 'success'], 200);
    }
}