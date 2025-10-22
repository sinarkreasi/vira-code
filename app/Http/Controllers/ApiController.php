<?php
/**
 * API Controller
 *
 * @package ViraCode
 */

namespace ViraCode\Http\Controllers;

use ViraCode\Models\SnippetModel;
use ViraCode\Services\SnippetExecutor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ApiController Class
 *
 * Handles REST API requests for snippet operations.
 */
class ApiController {

	/**
	 * Snippet model instance.
	 *
	 * @var SnippetModel
	 */
	protected $model;

	/**
	 * Snippet executor instance.
	 *
	 * @var SnippetExecutor
	 */
	protected $executor;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->model    = new SnippetModel();
		$this->executor = new SnippetExecutor();
	}

	/**
	 * Check permission for API requests.
	 *
	 * @return bool
	 */
	public function checkPermission() {
		return \ViraCode\vira_code_user_can_manage();
	}

	/**
	 * Get all snippets.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function getSnippets( $request ) {
		$params = $request->get_params();

		$args = array(
			'type'     => isset( $params['type'] ) ? sanitize_text_field( $params['type'] ) : '',
			'scope'    => isset( $params['scope'] ) ? sanitize_text_field( $params['scope'] ) : '',
			'status'   => isset( $params['status'] ) ? sanitize_text_field( $params['status'] ) : '',
			'category' => isset( $params['category'] ) ? sanitize_text_field( $params['category'] ) : '',
			'orderby'  => isset( $params['orderby'] ) ? sanitize_text_field( $params['orderby'] ) : 'priority',
			'order'    => isset( $params['order'] ) ? sanitize_text_field( $params['order'] ) : 'ASC',
			'limit'    => isset( $params['limit'] ) ? absint( $params['limit'] ) : -1,
			'offset'   => isset( $params['offset'] ) ? absint( $params['offset'] ) : 0,
		);

		$snippets = $this->model->getAll( $args );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $snippets,
				'total'   => $this->model->count( $args ),
			)
		);
	}

	/**
	 * Get single snippet.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function getSnippet( $request ) {
		$snippet_id = $request->get_param( 'id' );
		$snippet    = $this->model->getById( $snippet_id );

		if ( ! $snippet ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Snippet not found.', 'vira-code' ),
				),
				404
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $snippet,
			)
		);
	}

	/**
	 * Create new snippet.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function createSnippet( $request ) {
		$data = array(
			'title'       => $request->get_param( 'title' ),
			'description' => $request->get_param( 'description' ),
			'code'        => $request->get_param( 'code' ),
			'type'        => $request->get_param( 'type' ),
			'scope'       => $request->get_param( 'scope' ),
			'status'      => $request->get_param( 'status' ) ?: 'inactive',
			'tags'        => $request->get_param( 'tags' ) ?: '',
			'category'    => $request->get_param( 'category' ) ?: '',
			'priority'    => $request->get_param( 'priority' ) ?: 10,
		);

		// Test PHP snippets before saving.
		if ( 'php' === $data['type'] ) {
			$test_result = $this->executor->test( array_merge( array( 'id' => 0 ), $data ) );
			if ( ! $test_result['valid'] ) {
				return new \WP_REST_Response(
					array(
						'success' => false,
						'message' => __( 'PHP validation failed.', 'vira-code' ),
						'errors'  => $test_result['errors'],
					),
					400
				);
			}
		}

		$snippet_id = $this->model->create( $data );

		if ( $snippet_id ) {
			$snippet = $this->model->getById( $snippet_id );
			return rest_ensure_response(
				array(
					'success' => true,
					'message' => __( 'Snippet created successfully!', 'vira-code' ),
					'data'    => $snippet,
				)
			);
		}

		return new \WP_REST_Response(
			array(
				'success' => false,
				'message' => __( 'Failed to create snippet.', 'vira-code' ),
			),
			500
		);
	}

	/**
	 * Update snippet.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function updateSnippet( $request ) {
		$snippet_id = $request->get_param( 'id' );

		// Check if snippet exists.
		$existing = $this->model->getById( $snippet_id );
		if ( ! $existing ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Snippet not found.', 'vira-code' ),
				),
				404
			);
		}

		$data = array();

		// Only update provided fields.
		if ( $request->has_param( 'title' ) ) {
			$data['title'] = $request->get_param( 'title' );
		}

		if ( $request->has_param( 'description' ) ) {
			$data['description'] = $request->get_param( 'description' );
		}

		if ( $request->has_param( 'code' ) ) {
			$data['code'] = $request->get_param( 'code' );
		}

		if ( $request->has_param( 'type' ) ) {
			$data['type'] = $request->get_param( 'type' );
		}

		if ( $request->has_param( 'scope' ) ) {
			$data['scope'] = $request->get_param( 'scope' );
		}

		if ( $request->has_param( 'status' ) ) {
			$data['status'] = $request->get_param( 'status' );
		}

		if ( $request->has_param( 'tags' ) ) {
			$data['tags'] = $request->get_param( 'tags' );
		}

		if ( $request->has_param( 'category' ) ) {
			$data['category'] = $request->get_param( 'category' );
		}

		if ( $request->has_param( 'priority' ) ) {
			$data['priority'] = $request->get_param( 'priority' );
		}

		// Test PHP snippets before saving if code or type changed.
		if ( isset( $data['code'] ) || isset( $data['type'] ) ) {
			$test_data = array_merge( $existing, $data );
			if ( 'php' === $test_data['type'] ) {
				$test_result = $this->executor->test( $test_data );
				if ( ! $test_result['valid'] ) {
					return new \WP_REST_Response(
						array(
							'success' => false,
							'message' => __( 'PHP validation failed.', 'vira-code' ),
							'errors'  => $test_result['errors'],
						),
						400
					);
				}
			}
		}

		$result = $this->model->update( $snippet_id, $data );

		if ( $result ) {
			$snippet = $this->model->getById( $snippet_id );
			return rest_ensure_response(
				array(
					'success' => true,
					'message' => __( 'Snippet updated successfully!', 'vira-code' ),
					'data'    => $snippet,
				)
			);
		}

		return new \WP_REST_Response(
			array(
				'success' => false,
				'message' => __( 'Failed to update snippet.', 'vira-code' ),
			),
			500
		);
	}

	/**
	 * Delete snippet.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function deleteSnippet( $request ) {
		$snippet_id = $request->get_param( 'id' );

		// Check if snippet exists.
		$snippet = $this->model->getById( $snippet_id );
		if ( ! $snippet ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Snippet not found.', 'vira-code' ),
				),
				404
			);
		}

		$result = $this->model->delete( $snippet_id );

		if ( $result ) {
			return rest_ensure_response(
				array(
					'success' => true,
					'message' => __( 'Snippet deleted successfully!', 'vira-code' ),
				)
			);
		}

		return new \WP_REST_Response(
			array(
				'success' => false,
				'message' => __( 'Failed to delete snippet.', 'vira-code' ),
			),
			500
		);
	}

	/**
	 * Toggle snippet status.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function toggleSnippet( $request ) {
		$snippet_id = $request->get_param( 'id' );

		// Check if snippet exists.
		$snippet = $this->model->getById( $snippet_id );
		if ( ! $snippet ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Snippet not found.', 'vira-code' ),
				),
				404
			);
		}

		$result = $this->model->toggleStatus( $snippet_id );

		if ( $result ) {
			$updated_snippet = $this->model->getById( $snippet_id );
			return rest_ensure_response(
				array(
					'success' => true,
					'message' => __( 'Snippet status updated!', 'vira-code' ),
					'data'    => $updated_snippet,
				)
			);
		}

		return new \WP_REST_Response(
			array(
				'success' => false,
				'message' => __( 'Failed to update snippet status.', 'vira-code' ),
			),
			500
		);
	}

	/**
	 * Execute snippet for testing.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function executeSnippet( $request ) {
		$snippet_id = $request->get_param( 'id' );

		// Check if snippet exists.
		$snippet = $this->model->getById( $snippet_id );
		if ( ! $snippet ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Snippet not found.', 'vira-code' ),
				),
				404
			);
		}

		// Execute snippet.
		$result = $this->executor->execute( $snippet );

		return rest_ensure_response(
			array(
				'success' => $result['success'],
				'message' => $result['success'] ? __( 'Snippet executed successfully!', 'vira-code' ) : __( 'Snippet execution failed.', 'vira-code' ),
				'output'  => $result['output'],
				'error'   => $result['error'],
			)
		);
	}

	/**
	 * Get snippet types.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function getSnippetTypes( $request ) {
		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => \ViraCode\vira_code_snippet_types(),
			)
		);
	}

	/**
	 * Get snippet scopes.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function getSnippetScopes( $request ) {
		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => \ViraCode\vira_code_snippet_scopes(),
			)
		);
	}

	/**
	 * Get execution logs.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function getLogs( $request ) {
		$limit = $request->get_param( 'limit' ) ?: 100;
		$logs  = \ViraCode\vira_code_get_logs( absint( $limit ) );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $logs,
				'total'   => count( $logs ),
			)
		);
	}

	/**
	 * Clear execution logs.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function clearLogs( $request ) {
		$result = \ViraCode\vira_code_clear_logs();

		if ( $result ) {
			return rest_ensure_response(
				array(
					'success' => true,
					'message' => __( 'Logs cleared successfully!', 'vira-code' ),
				)
			);
		}

		return new \WP_REST_Response(
			array(
				'success' => false,
				'message' => __( 'Failed to clear logs.', 'vira-code' ),
			),
			500
		);
	}
}
