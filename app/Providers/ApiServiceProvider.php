<?php
/**
 * API Service Provider
 *
 * @package ViraCode
 */

namespace ViraCode\Providers;

use ViraCode\Http\Controllers\ApiController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ApiServiceProvider Class
 *
 * Handles registration of REST API endpoints.
 */
class ApiServiceProvider extends ServiceProvider {

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register() {
		// Register API controller.
		$this->app->apiController = new ApiController();
	}

	/**
	 * Boot the service provider.
	 *
	 * @return void
	 */
	public function boot() {
		// Register REST API routes.
		add_action( 'rest_api_init', array( $this, 'registerRoutes' ) );
	}

	/**
	 * Register REST API routes.
	 *
	 * @return void
	 */
	public function registerRoutes() {
		$namespace = 'vira/v1';
		$controller = $this->app->apiController;

		// Get all snippets.
		register_rest_route(
			$namespace,
			'/snippets',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $controller, 'getSnippets' ),
				'permission_callback' => array( $controller, 'checkPermission' ),
			)
		);

		// Get single snippet.
		register_rest_route(
			$namespace,
			'/snippets/(?P<id>\d+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $controller, 'getSnippet' ),
				'permission_callback' => array( $controller, 'checkPermission' ),
				'args'                => array(
					'id' => array(
						'validate_callback' => function( $param ) {
							return is_numeric( $param );
						},
					),
				),
			)
		);

		// Create snippet.
		register_rest_route(
			$namespace,
			'/snippets',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $controller, 'createSnippet' ),
				'permission_callback' => array( $controller, 'checkPermission' ),
				'args'                => $this->getSnippetSchema(),
			)
		);

		// Update snippet.
		register_rest_route(
			$namespace,
			'/snippets/(?P<id>\d+)',
			array(
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => array( $controller, 'updateSnippet' ),
				'permission_callback' => array( $controller, 'checkPermission' ),
				'args'                => array_merge(
					array(
						'id' => array(
							'validate_callback' => function( $param ) {
								return is_numeric( $param );
							},
						),
					),
					$this->getSnippetSchema()
				),
			)
		);

		// Delete snippet.
		register_rest_route(
			$namespace,
			'/snippets/(?P<id>\d+)',
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array( $controller, 'deleteSnippet' ),
				'permission_callback' => array( $controller, 'checkPermission' ),
				'args'                => array(
					'id' => array(
						'validate_callback' => function( $param ) {
							return is_numeric( $param );
						},
					),
				),
			)
		);

		// Toggle snippet status.
		register_rest_route(
			$namespace,
			'/snippets/(?P<id>\d+)/toggle',
			array(
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => array( $controller, 'toggleSnippet' ),
				'permission_callback' => array( $controller, 'checkPermission' ),
				'args'                => array(
					'id' => array(
						'validate_callback' => function( $param ) {
							return is_numeric( $param );
						},
					),
				),
			)
		);

		// Execute snippet (for testing).
		register_rest_route(
			$namespace,
			'/snippets/(?P<id>\d+)/execute',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $controller, 'executeSnippet' ),
				'permission_callback' => array( $controller, 'checkPermission' ),
				'args'                => array(
					'id' => array(
						'validate_callback' => function( $param ) {
							return is_numeric( $param );
						},
					),
				),
			)
		);

		// Get snippet types.
		register_rest_route(
			$namespace,
			'/snippet-types',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $controller, 'getSnippetTypes' ),
				'permission_callback' => array( $controller, 'checkPermission' ),
			)
		);

		// Get snippet scopes.
		register_rest_route(
			$namespace,
			'/snippet-scopes',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $controller, 'getSnippetScopes' ),
				'permission_callback' => array( $controller, 'checkPermission' ),
			)
		);

		// Get execution logs.
		register_rest_route(
			$namespace,
			'/logs',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $controller, 'getLogs' ),
				'permission_callback' => array( $controller, 'checkPermission' ),
			)
		);

		// Clear logs.
		register_rest_route(
			$namespace,
			'/logs',
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array( $controller, 'clearLogs' ),
				'permission_callback' => array( $controller, 'checkPermission' ),
			)
		);

		do_action( 'vira_code/api_routes_registered', $namespace, $controller );
	}

	/**
	 * Get snippet schema for validation.
	 *
	 * @return array
	 */
	protected function getSnippetSchema() {
		return array(
			'title'       => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function( $param ) {
					return ! empty( $param );
				},
			),
			'code'        => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => function( $param ) {
					return $param; // Raw code - sanitized in model.
				},
			),
			'description' => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
				'default'           => '',
			),
			'type'        => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function( $param ) {
					return in_array( $param, array( 'php', 'js', 'css', 'html' ), true );
				},
			),
			'scope'       => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function( $param ) {
					return in_array( $param, array( 'frontend', 'admin', 'both', 'everywhere' ), true );
				},
			),
			'status'      => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function( $param ) {
					return in_array( $param, array( 'active', 'inactive', 'error' ), true );
				},
				'default'           => 'inactive',
			),
			'tags'        => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			),
			'category'    => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			),
			'priority'    => array(
				'required'          => false,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 10,
			),
		);
	}
}
