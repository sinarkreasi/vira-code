<?php
/**
 * Snippet Model
 *
 * @package ViraCode
 */

namespace ViraCode\Models;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SnippetModel Class
 *
 * Handles CRUD operations for snippets in the custom table.
 */
class SnippetModel {

	/**
	 * Database table name.
	 *
	 * @var string
	 */
	protected $table_name;

	/**
	 * WordPress database object.
	 *
	 * @var \wpdb
	 */
	protected $wpdb;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb       = $wpdb;
		$this->table_name = $wpdb->prefix . 'vira_snippets';
	}

	/**
	 * Create the snippets table.
	 *
	 * @return void
	 */
	public function createTable() {
		$charset_collate = $this->wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			title varchar(255) NOT NULL,
			description text,
			code longtext NOT NULL,
			type varchar(20) NOT NULL DEFAULT 'php',
			scope varchar(20) NOT NULL DEFAULT 'frontend',
			status varchar(20) NOT NULL DEFAULT 'inactive',
			tags varchar(255),
			category varchar(100),
			priority int(11) NOT NULL DEFAULT 10,
			error_message text,
			last_error_at datetime,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY type (type),
			KEY scope (scope),
			KEY status (status),
			KEY priority (priority)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Get all snippets.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function getAll( $args = array() ) {
		$defaults = array(
			'type'     => '',
			'scope'    => '',
			'status'   => '',
			'category' => '',
			'orderby'  => 'priority',
			'order'    => 'ASC',
			'limit'    => -1,
			'offset'   => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		$where = array( '1=1' );
		$where_values = array();

		if ( ! empty( $args['type'] ) ) {
			$where[] = 'type = %s';
			$where_values[] = $args['type'];
		}

		if ( ! empty( $args['scope'] ) ) {
			$where[] = 'scope = %s';
			$where_values[] = $args['scope'];
		}

		if ( ! empty( $args['status'] ) ) {
			$where[] = 'status = %s';
			$where_values[] = $args['status'];
		}

		if ( ! empty( $args['category'] ) ) {
			$where[] = 'category = %s';
			$where_values[] = $args['category'];
		}

		$where_clause = implode( ' AND ', $where );

		$orderby = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] );
		if ( ! $orderby ) {
			$orderby = 'priority ASC';
		}

		$limit_clause = '';
		if ( $args['limit'] > 0 ) {
			$limit_clause = $this->wpdb->prepare( 'LIMIT %d OFFSET %d', $args['limit'], $args['offset'] );
		}

		$query = "SELECT * FROM {$this->table_name} WHERE {$where_clause} ORDER BY {$orderby} {$limit_clause}";

		if ( ! empty( $where_values ) ) {
			$query = $this->wpdb->prepare( $query, $where_values );
		}

		$results = $this->wpdb->get_results( $query, ARRAY_A );

		return apply_filters( 'vira_code/snippets_retrieved', $results, $args );
	}

	/**
	 * Get a single snippet by ID.
	 *
	 * @param int $id Snippet ID.
	 * @return array|null
	 */
	public function getById( $id ) {
		$query = $this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE id = %d LIMIT 1",
			$id
		);

		$result = $this->wpdb->get_row( $query, ARRAY_A );

		return apply_filters( 'vira_code/snippet_retrieved', $result, $id );
	}

	/**
	 * Get snippets by scope and type.
	 *
	 * @param string $scope Snippet scope.
	 * @param string $type  Snippet type.
	 * @return array
	 */
	public function getByScope( $scope, $type = '' ) {
		$args = array(
			'scope'  => $scope,
			'status' => 'active',
		);

		if ( ! empty( $type ) ) {
			$args['type'] = $type;
		}

		return $this->getAll( $args );
	}

	/**
	 * Create a new snippet.
	 *
	 * @param array $data Snippet data.
	 * @return int|false Snippet ID on success, false on failure.
	 */
	public function create( $data ) {
		$data = $this->sanitizeData( $data );

		// Set created_at if not provided.
		if ( empty( $data['created_at'] ) ) {
			$data['created_at'] = current_time( 'mysql' );
		}

		// Set updated_at if not provided.
		if ( empty( $data['updated_at'] ) ) {
			$data['updated_at'] = current_time( 'mysql' );
		}

		$data = apply_filters( 'vira_code/snippet_before_create', $data );

		$inserted = $this->wpdb->insert(
			$this->table_name,
			$data,
			$this->getFormatArray( $data )
		);

		if ( $inserted ) {
			$snippet_id = $this->wpdb->insert_id;
			do_action( 'vira_code/snippet_created', $snippet_id, $data );
			do_action( 'vira_code/snippet_saved', $snippet_id, $data );
			return $snippet_id;
		}

		return false;
	}

	/**
	 * Update a snippet.
	 *
	 * @param int   $id   Snippet ID.
	 * @param array $data Snippet data.
	 * @return bool
	 */
	public function update( $id, $data ) {
		$data = $this->sanitizeData( $data );

		// Always update the updated_at timestamp.
		$data['updated_at'] = current_time( 'mysql' );

		$data = apply_filters( 'vira_code/snippet_before_update', $data, $id );

		$updated = $this->wpdb->update(
			$this->table_name,
			$data,
			array( 'id' => $id ),
			$this->getFormatArray( $data ),
			array( '%d' )
		);

		if ( false !== $updated ) {
			do_action( 'vira_code/snippet_updated', $id, $data );
			do_action( 'vira_code/snippet_saved', $id, $data );
			return true;
		}

		return false;
	}

	/**
	 * Delete a snippet.
	 *
	 * @param int $id Snippet ID.
	 * @return bool
	 */
	public function delete( $id ) {
		$snippet = $this->getById( $id );

		if ( ! $snippet ) {
			return false;
		}

		do_action( 'vira_code/snippet_before_delete', $id, $snippet );

		$deleted = $this->wpdb->delete(
			$this->table_name,
			array( 'id' => $id ),
			array( '%d' )
		);

		if ( $deleted ) {
			do_action( 'vira_code/snippet_deleted', $id, $snippet );
			return true;
		}

		return false;
	}

	/**
	 * Update snippet status.
	 *
	 * @param int    $id     Snippet ID.
	 * @param string $status New status.
	 * @return bool
	 */
	public function updateStatus( $id, $status ) {
		$valid_statuses = array( 'active', 'inactive', 'error' );

		if ( ! in_array( $status, $valid_statuses, true ) ) {
			return false;
		}

		return $this->update( $id, array( 'status' => $status ) );
	}

	/**
	 * Toggle snippet status between active and inactive.
	 *
	 * @param int $id Snippet ID.
	 * @return bool
	 */
	public function toggleStatus( $id ) {
		$snippet = $this->getById( $id );

		if ( ! $snippet ) {
			return false;
		}

		$new_status = ( 'active' === $snippet['status'] ) ? 'inactive' : 'active';

		return $this->updateStatus( $id, $new_status );
	}

	/**
	 * Log snippet error.
	 *
	 * @param int    $id            Snippet ID.
	 * @param string $error_message Error message.
	 * @return bool
	 */
	public function logError( $id, $error_message ) {
		return $this->update(
			$id,
			array(
				'status'        => 'error',
				'error_message' => $error_message,
				'last_error_at' => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Clear snippet error.
	 *
	 * @param int $id Snippet ID.
	 * @return bool
	 */
	public function clearError( $id ) {
		return $this->update(
			$id,
			array(
				'error_message' => null,
				'last_error_at' => null,
			)
		);
	}

	/**
	 * Get total count of snippets.
	 *
	 * @param array $args Query arguments.
	 * @return int
	 */
	public function count( $args = array() ) {
		$where = array( '1=1' );
		$where_values = array();

		if ( ! empty( $args['type'] ) ) {
			$where[] = 'type = %s';
			$where_values[] = $args['type'];
		}

		if ( ! empty( $args['scope'] ) ) {
			$where[] = 'scope = %s';
			$where_values[] = $args['scope'];
		}

		if ( ! empty( $args['status'] ) ) {
			$where[] = 'status = %s';
			$where_values[] = $args['status'];
		}

		$where_clause = implode( ' AND ', $where );

		$query = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_clause}";

		if ( ! empty( $where_values ) ) {
			$query = $this->wpdb->prepare( $query, $where_values );
		}

		return (int) $this->wpdb->get_var( $query );
	}

	/**
	 * Search snippets by title or description.
	 *
	 * @param string $search_term Search term.
	 * @param array  $args        Additional query arguments.
	 * @return array
	 */
	public function search( $search_term, $args = array() ) {
		$search_term = '%' . $this->wpdb->esc_like( $search_term ) . '%';

		$query = $this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE (title LIKE %s OR description LIKE %s OR tags LIKE %s)",
			$search_term,
			$search_term,
			$search_term
		);

		if ( ! empty( $args['status'] ) ) {
			$query .= $this->wpdb->prepare( ' AND status = %s', $args['status'] );
		}

		$query .= ' ORDER BY priority ASC';

		return $this->wpdb->get_results( $query, ARRAY_A );
	}

	/**
	 * Sanitize snippet data.
	 *
	 * @param array $data Raw data.
	 * @return array
	 */
	protected function sanitizeData( $data ) {
		$sanitized = array();

		if ( isset( $data['title'] ) ) {
			$sanitized['title'] = sanitize_text_field( $data['title'] );
		}

		if ( isset( $data['description'] ) ) {
			$sanitized['description'] = sanitize_textarea_field( $data['description'] );
		}

		if ( isset( $data['code'] ) ) {
			// Don't sanitize code content - it needs to remain as-is.
			$sanitized['code'] = $data['code'];
		}

		if ( isset( $data['type'] ) ) {
			$sanitized['type'] = sanitize_text_field( $data['type'] );
		}

		if ( isset( $data['scope'] ) ) {
			$sanitized['scope'] = sanitize_text_field( $data['scope'] );
		}

		if ( isset( $data['status'] ) ) {
			$sanitized['status'] = sanitize_text_field( $data['status'] );
		}

		if ( isset( $data['tags'] ) ) {
			$sanitized['tags'] = sanitize_text_field( $data['tags'] );
		}

		if ( isset( $data['category'] ) ) {
			$sanitized['category'] = sanitize_text_field( $data['category'] );
		}

		if ( isset( $data['priority'] ) ) {
			$sanitized['priority'] = absint( $data['priority'] );
		}

		if ( isset( $data['error_message'] ) ) {
			$sanitized['error_message'] = sanitize_textarea_field( $data['error_message'] );
		}

		if ( isset( $data['last_error_at'] ) ) {
			$sanitized['last_error_at'] = $data['last_error_at'];
		}

		if ( isset( $data['created_at'] ) ) {
			$sanitized['created_at'] = $data['created_at'];
		}

		if ( isset( $data['updated_at'] ) ) {
			$sanitized['updated_at'] = $data['updated_at'];
		}

		return apply_filters( 'vira_code/snippet_sanitized', $sanitized, $data );
	}

	/**
	 * Get format array for wpdb operations.
	 *
	 * @param array $data Data array.
	 * @return array
	 */
	protected function getFormatArray( $data ) {
		$formats = array();

		foreach ( $data as $key => $value ) {
			switch ( $key ) {
				case 'id':
				case 'priority':
					$formats[] = '%d';
					break;
				default:
					$formats[] = '%s';
					break;
			}
		}

		return $formats;
	}

	/**
	 * Get table name.
	 *
	 * @return string
	 */
	public function getTableName() {
		return $this->table_name;
	}
}
