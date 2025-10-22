<?php
/**
 * Conditional Logic Model
 *
 * @package ViraCode
 */

namespace ViraCode\Models;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ConditionalLogicModel Class
 *
 * Handles CRUD operations for conditional logic rules in the custom table.
 */
class ConditionalLogicModel {

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
		$this->table_name = $wpdb->prefix . 'vira_snippet_conditions';
	}

	/**
	 * Create the conditional logic rules table.
	 *
	 * @return void
	 */
	public function createTable() {
		$charset_collate = $this->wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			snippet_id bigint(20) UNSIGNED NOT NULL,
			rule_group int(11) NOT NULL DEFAULT 0,
			condition_type varchar(50) NOT NULL,
			condition_operator varchar(20) NOT NULL DEFAULT 'equals',
			condition_value text,
			logic_operator varchar(10) NOT NULL DEFAULT 'AND',
			priority int(11) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY snippet_id (snippet_id),
			KEY condition_type (condition_type),
			KEY rule_group (rule_group),
			KEY snippet_rule_group (snippet_id, rule_group),
			KEY snippet_priority (snippet_id, priority),
			KEY type_operator (condition_type, condition_operator),
			KEY snippet_type (snippet_id, condition_type)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Add additional indexes for performance optimization.
		$this->addPerformanceIndexes();
	}

	/**
	 * Add performance-optimized indexes.
	 *
	 * @return void
	 */
	protected function addPerformanceIndexes() {
		// Check if indexes already exist to avoid duplicate key errors.
		$existing_indexes = $this->getExistingIndexes();

		$indexes_to_add = array(
			'idx_snippet_group_priority' => "CREATE INDEX idx_snippet_group_priority ON {$this->table_name} (snippet_id, rule_group, priority)",
			'idx_condition_lookup'       => "CREATE INDEX idx_condition_lookup ON {$this->table_name} (condition_type, condition_operator)",
			'idx_active_rules'          => "CREATE INDEX idx_active_rules ON {$this->table_name} (snippet_id, condition_type, rule_group)",
		);

		foreach ( $indexes_to_add as $index_name => $sql ) {
			if ( ! in_array( $index_name, $existing_indexes, true ) ) {
				$this->wpdb->query( $sql );
			}
		}
	}

	/**
	 * Get existing indexes on the table.
	 *
	 * @return array
	 */
	protected function getExistingIndexes() {
		$query = "SHOW INDEX FROM {$this->table_name}";
		$results = $this->wpdb->get_results( $query, ARRAY_A );

		$indexes = array();
		foreach ( $results as $result ) {
			$indexes[] = $result['Key_name'];
		}

		return array_unique( $indexes );
	}

	/**
	 * Get all rules for a specific snippet.
	 *
	 * @param int $snippet_id Snippet ID.
	 * @return array
	 */
	public function getRules( $snippet_id ) {
		$query = $this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE snippet_id = %d ORDER BY rule_group ASC, priority ASC",
			$snippet_id
		);

		$results = $this->wpdb->get_results( $query, ARRAY_A );

		return apply_filters( 'vira_code/conditional_logic_rules_retrieved', $results, $snippet_id );
	}

	/**
	 * Batch get rules for multiple snippets.
	 *
	 * @param array $snippet_ids Array of snippet IDs.
	 * @return array Associative array of snippet_id => rules.
	 */
	public function batchGetRules( $snippet_ids ) {
		if ( empty( $snippet_ids ) ) {
			return array();
		}

		// Sanitize snippet IDs.
		$snippet_ids = array_map( 'absint', $snippet_ids );
		$snippet_ids = array_filter( $snippet_ids );

		if ( empty( $snippet_ids ) ) {
			return array();
		}

		// Create placeholders for IN clause.
		$placeholders = implode( ',', array_fill( 0, count( $snippet_ids ), '%d' ) );

		$query = $this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE snippet_id IN ({$placeholders}) ORDER BY snippet_id ASC, rule_group ASC, priority ASC",
			...$snippet_ids
		);

		$results = $this->wpdb->get_results( $query, ARRAY_A );

		// Group results by snippet_id.
		$grouped_results = array();
		foreach ( $snippet_ids as $snippet_id ) {
			$grouped_results[ $snippet_id ] = array();
		}

		foreach ( $results as $rule ) {
			$snippet_id = $rule['snippet_id'];
			$grouped_results[ $snippet_id ][] = $rule;
		}

		return apply_filters( 'vira_code/conditional_logic_batch_rules_retrieved', $grouped_results, $snippet_ids );
	}

	/**
	 * Validate that a snippet exists.
	 *
	 * @param int $snippet_id Snippet ID.
	 * @return bool
	 */
	public function validateSnippetExists( $snippet_id ) {
		$snippets_table = $this->wpdb->prefix . 'vira_snippets';
		
		$query = $this->wpdb->prepare(
			"SELECT id FROM {$snippets_table} WHERE id = %d LIMIT 1",
			$snippet_id
		);

		return (bool) $this->wpdb->get_var( $query );
	}

	/**
	 * Save rules for a specific snippet.
	 *
	 * @param int   $snippet_id Snippet ID.
	 * @param array $rules      Array of rule data.
	 * @return bool
	 */
	public function saveRules( $snippet_id, $rules ) {
		// Validate snippet exists.
		if ( ! $this->validateSnippetExists( $snippet_id ) ) {
			return false;
		}
		// Start transaction.
		$this->wpdb->query( 'START TRANSACTION' );

		try {
			// Delete existing rules for this snippet.
			$this->deleteRules( $snippet_id );

			// Insert new rules.
			foreach ( $rules as $rule ) {
				$rule_data = $this->sanitizeRuleData( $rule );
				$rule_data['snippet_id'] = $snippet_id;

				$inserted = $this->wpdb->insert(
					$this->table_name,
					$rule_data,
					$this->getFormatArray( $rule_data )
				);

				if ( false === $inserted ) {
					throw new \Exception( 'Failed to insert rule: ' . $this->wpdb->last_error );
				}
			}

			// Commit transaction.
			$this->wpdb->query( 'COMMIT' );

			do_action( 'vira_code/conditional_logic_rules_saved', $snippet_id, $rules );

			return true;

		} catch ( \Exception $e ) {
			// Rollback transaction.
			$this->wpdb->query( 'ROLLBACK' );

			do_action( 'vira_code/conditional_logic_rules_save_failed', $snippet_id, $rules, $e->getMessage() );

			return false;
		}
	}

	/**
	 * Delete all rules for a specific snippet.
	 *
	 * @param int $snippet_id Snippet ID.
	 * @return bool
	 */
	public function deleteRules( $snippet_id ) {
		$deleted = $this->wpdb->delete(
			$this->table_name,
			array( 'snippet_id' => $snippet_id ),
			array( '%d' )
		);

		if ( false !== $deleted ) {
			do_action( 'vira_code/conditional_logic_rules_deleted', $snippet_id );
			return true;
		}

		return false;
	}

	/**
	 * Get rules by condition type.
	 *
	 * @param string $type Condition type.
	 * @return array
	 */
	public function getRulesByType( $type ) {
		$query = $this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE condition_type = %s ORDER BY snippet_id ASC, rule_group ASC, priority ASC",
			$type
		);

		$results = $this->wpdb->get_results( $query, ARRAY_A );

		return apply_filters( 'vira_code/conditional_logic_rules_by_type_retrieved', $results, $type );
	}

	/**
	 * Get a single rule by ID.
	 *
	 * @param int $rule_id Rule ID.
	 * @return array|null
	 */
	public function getRuleById( $rule_id ) {
		$query = $this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE id = %d LIMIT 1",
			$rule_id
		);

		$result = $this->wpdb->get_row( $query, ARRAY_A );

		return apply_filters( 'vira_code/conditional_logic_rule_retrieved', $result, $rule_id );
	}

	/**
	 * Update a single rule.
	 *
	 * @param int   $rule_id Rule ID.
	 * @param array $data    Rule data.
	 * @return bool
	 */
	public function updateRule( $rule_id, $data ) {
		$data = $this->sanitizeRuleData( $data );

		// Always update the updated_at timestamp.
		$data['updated_at'] = current_time( 'mysql' );

		$data = apply_filters( 'vira_code/conditional_logic_rule_before_update', $data, $rule_id );

		$updated = $this->wpdb->update(
			$this->table_name,
			$data,
			array( 'id' => $rule_id ),
			$this->getFormatArray( $data ),
			array( '%d' )
		);

		if ( false !== $updated ) {
			do_action( 'vira_code/conditional_logic_rule_updated', $rule_id, $data );
			return true;
		}

		return false;
	}

	/**
	 * Delete a single rule.
	 *
	 * @param int $rule_id Rule ID.
	 * @return bool
	 */
	public function deleteRule( $rule_id ) {
		$rule = $this->getRuleById( $rule_id );

		if ( ! $rule ) {
			return false;
		}

		do_action( 'vira_code/conditional_logic_rule_before_delete', $rule_id, $rule );

		$deleted = $this->wpdb->delete(
			$this->table_name,
			array( 'id' => $rule_id ),
			array( '%d' )
		);

		if ( $deleted ) {
			do_action( 'vira_code/conditional_logic_rule_deleted', $rule_id, $rule );
			return true;
		}

		return false;
	}

	/**
	 * Get count of rules for a snippet.
	 *
	 * @param int $snippet_id Snippet ID.
	 * @return int
	 */
	public function getRuleCount( $snippet_id ) {
		$query = $this->wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->table_name} WHERE snippet_id = %d",
			$snippet_id
		);

		return (int) $this->wpdb->get_var( $query );
	}

	/**
	 * Get snippets that have conditional logic rules.
	 *
	 * @return array
	 */
	public function getSnippetsWithRules() {
		$query = "SELECT DISTINCT snippet_id FROM {$this->table_name} ORDER BY snippet_id ASC";

		$results = $this->wpdb->get_col( $query );

		return apply_filters( 'vira_code/snippets_with_conditional_logic', $results );
	}

	/**
	 * Get snippets with rules using optimized JOIN query.
	 *
	 * @return array Array of snippet data with rule counts.
	 */
	public function getSnippetsWithRulesOptimized() {
		$snippets_table = $this->wpdb->prefix . 'vira_snippets';

		$query = "
			SELECT 
				s.id,
				s.title,
				s.status,
				COUNT(c.id) as rule_count,
				COUNT(DISTINCT c.condition_type) as condition_types_count
			FROM {$snippets_table} s
			INNER JOIN {$this->table_name} c ON s.id = c.snippet_id
			WHERE s.status = 'active'
			GROUP BY s.id, s.title, s.status
			ORDER BY s.id ASC
		";

		$results = $this->wpdb->get_results( $query, ARRAY_A );

		return apply_filters( 'vira_code/snippets_with_conditional_logic_optimized', $results );
	}

	/**
	 * Get rule statistics for performance monitoring.
	 *
	 * @return array
	 */
	public function getRuleStatistics() {
		$query = "
			SELECT 
				COUNT(*) as total_rules,
				COUNT(DISTINCT snippet_id) as snippets_with_rules,
				COUNT(DISTINCT condition_type) as unique_condition_types,
				AVG(rules_per_snippet.rule_count) as avg_rules_per_snippet
			FROM {$this->table_name}
			CROSS JOIN (
				SELECT snippet_id, COUNT(*) as rule_count
				FROM {$this->table_name}
				GROUP BY snippet_id
			) as rules_per_snippet
		";

		$result = $this->wpdb->get_row( $query, ARRAY_A );

		return apply_filters( 'vira_code/conditional_logic_statistics', $result );
	}

	/**
	 * Get rules by condition type with optimized query.
	 *
	 * @param string $type      Condition type.
	 * @param int    $limit     Limit results.
	 * @param int    $offset    Offset for pagination.
	 * @return array
	 */
	public function getRulesByTypeOptimized( $type, $limit = 100, $offset = 0 ) {
		$query = $this->wpdb->prepare(
			"SELECT c.*, s.title as snippet_title, s.status as snippet_status
			FROM {$this->table_name} c
			INNER JOIN {$this->wpdb->prefix}vira_snippets s ON c.snippet_id = s.id
			WHERE c.condition_type = %s
			ORDER BY c.snippet_id ASC, c.rule_group ASC, c.priority ASC
			LIMIT %d OFFSET %d",
			$type,
			$limit,
			$offset
		);

		$results = $this->wpdb->get_results( $query, ARRAY_A );

		return apply_filters( 'vira_code/conditional_logic_rules_by_type_optimized', $results, $type );
	}

	/**
	 * Batch get rule counts for multiple snippets.
	 *
	 * @param array $snippet_ids Array of snippet IDs.
	 * @return array Associative array of snippet_id => count.
	 */
	public function batchGetRuleCounts( $snippet_ids ) {
		if ( empty( $snippet_ids ) ) {
			return array();
		}

		// Sanitize snippet IDs.
		$snippet_ids = array_map( 'absint', $snippet_ids );
		$snippet_ids = array_filter( $snippet_ids );

		if ( empty( $snippet_ids ) ) {
			return array();
		}

		// Create placeholders for IN clause.
		$placeholders = implode( ',', array_fill( 0, count( $snippet_ids ), '%d' ) );

		$query = $this->wpdb->prepare(
			"SELECT snippet_id, COUNT(*) as rule_count 
			FROM {$this->table_name} 
			WHERE snippet_id IN ({$placeholders}) 
			GROUP BY snippet_id",
			...$snippet_ids
		);

		$results = $this->wpdb->get_results( $query, ARRAY_A );

		// Convert to associative array.
		$counts = array();
		foreach ( $snippet_ids as $snippet_id ) {
			$counts[ $snippet_id ] = 0; // Default to 0.
		}

		foreach ( $results as $result ) {
			$counts[ $result['snippet_id'] ] = (int) $result['rule_count'];
		}

		return apply_filters( 'vira_code/conditional_logic_batch_rule_counts', $counts, $snippet_ids );
	}

	/**
	 * Get rules with snippet data in a single optimized query.
	 *
	 * @param int $snippet_id Snippet ID.
	 * @return array
	 */
	public function getRulesWithSnippetData( $snippet_id ) {
		$snippets_table = $this->wpdb->prefix . 'vira_snippets';

		$query = $this->wpdb->prepare(
			"SELECT 
				c.*,
				s.title as snippet_title,
				s.status as snippet_status,
				s.location as snippet_location
			FROM {$this->table_name} c
			INNER JOIN {$snippets_table} s ON c.snippet_id = s.id
			WHERE c.snippet_id = %d
			ORDER BY c.rule_group ASC, c.priority ASC",
			$snippet_id
		);

		$results = $this->wpdb->get_results( $query, ARRAY_A );

		return apply_filters( 'vira_code/conditional_logic_rules_with_snippet_data', $results, $snippet_id );
	}

	/**
	 * Delete rules with optimized batch operation.
	 *
	 * @param array $rule_ids Array of rule IDs to delete.
	 * @return bool
	 */
	public function batchDeleteRules( $rule_ids ) {
		if ( empty( $rule_ids ) ) {
			return true;
		}

		// Sanitize rule IDs.
		$rule_ids = array_map( 'absint', $rule_ids );
		$rule_ids = array_filter( $rule_ids );

		if ( empty( $rule_ids ) ) {
			return true;
		}

		// Create placeholders for IN clause.
		$placeholders = implode( ',', array_fill( 0, count( $rule_ids ), '%d' ) );

		$query = $this->wpdb->prepare(
			"DELETE FROM {$this->table_name} WHERE id IN ({$placeholders})",
			...$rule_ids
		);

		$result = $this->wpdb->query( $query );

		if ( false !== $result ) {
			do_action( 'vira_code/conditional_logic_batch_rules_deleted', $rule_ids );
			return true;
		}

		return false;
	}

	/**
	 * Check if a snippet has conditional logic rules.
	 *
	 * @param int $snippet_id Snippet ID.
	 * @return bool
	 */
	public function hasRules( $snippet_id ) {
		return $this->getRuleCount( $snippet_id ) > 0;
	}

	/**
	 * Sanitize rule data.
	 *
	 * @param array $data Raw rule data.
	 * @return array
	 */
	protected function sanitizeRuleData( $data ) {
		$sanitized = array();

		if ( isset( $data['snippet_id'] ) ) {
			$sanitized['snippet_id'] = absint( $data['snippet_id'] );
		}

		if ( isset( $data['rule_group'] ) ) {
			$sanitized['rule_group'] = absint( $data['rule_group'] );
		}

		if ( isset( $data['condition_type'] ) ) {
			$sanitized['condition_type'] = sanitize_text_field( $data['condition_type'] );
		}

		if ( isset( $data['condition_operator'] ) ) {
			$sanitized['condition_operator'] = sanitize_text_field( $data['condition_operator'] );
		}

		if ( isset( $data['condition_value'] ) ) {
			// Don't sanitize condition value too aggressively as it may contain JSON or regex patterns.
			$sanitized['condition_value'] = wp_kses_post( $data['condition_value'] );
		}

		if ( isset( $data['logic_operator'] ) ) {
			$valid_operators = array( 'AND', 'OR' );
			$operator = strtoupper( sanitize_text_field( $data['logic_operator'] ) );
			$sanitized['logic_operator'] = in_array( $operator, $valid_operators, true ) ? $operator : 'AND';
		}

		if ( isset( $data['priority'] ) ) {
			$sanitized['priority'] = absint( $data['priority'] );
		}

		if ( isset( $data['created_at'] ) ) {
			$sanitized['created_at'] = $data['created_at'];
		}

		if ( isset( $data['updated_at'] ) ) {
			$sanitized['updated_at'] = $data['updated_at'];
		}

		return apply_filters( 'vira_code/conditional_logic_rule_sanitized', $sanitized, $data );
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
				case 'snippet_id':
				case 'rule_group':
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

	/**
	 * Handle cascade delete when a snippet is deleted.
	 * This should be called when a snippet is deleted to maintain referential integrity.
	 *
	 * @param int $snippet_id Snippet ID.
	 * @return bool
	 */
	public function handleSnippetDeletion( $snippet_id ) {
		return $this->deleteRules( $snippet_id );
	}

	/**
	 * Static method to handle snippet deletion hook.
	 * This is registered as a WordPress action to maintain referential integrity.
	 *
	 * @param int   $snippet_id Snippet ID.
	 * @param array $snippet    Snippet data.
	 * @return void
	 */
	public static function onSnippetDeleted( $snippet_id, $snippet ) {
		$model = new self();
		$model->handleSnippetDeletion( $snippet_id );
	}

	/**
	 * Register WordPress hooks for maintaining referential integrity.
	 *
	 * @return void
	 */
	public static function registerHooks() {
		add_action( 'vira_code/snippet_deleted', array( __CLASS__, 'onSnippetDeleted' ), 10, 2 );
	}

	/**
	 * Drop the table (for uninstall).
	 *
	 * @return bool
	 */
	public function dropTable() {
		$query = "DROP TABLE IF EXISTS {$this->table_name}";
		$result = $this->wpdb->query( $query );

		return false !== $result;
	}
}