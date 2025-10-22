<?php
/**
 * Page Type Condition Evaluator
 *
 * @package ViraCode
 */

namespace ViraCode\Services\Evaluators;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PageTypeEvaluator Class
 *
 * Evaluates page type conditions using WordPress conditional functions.
 */
class PageTypeEvaluator extends AbstractConditionEvaluator {

	/**
	 * Condition type identifier.
	 *
	 * @var string
	 */
	protected $type = 'page_type';

	/**
	 * Supported operators for page type conditions.
	 *
	 * @var array
	 */
	protected $supported_operators = array( 'equals', 'not_equals' );

	/**
	 * Human-readable description.
	 *
	 * @var string
	 */
	protected $description = 'Evaluate conditions based on WordPress page types (home, single, archive, etc.)';

	/**
	 * Supported page types.
	 *
	 * @var array
	 */
	protected $supported_page_types = array(
		'home',
		'front_page',
		'single',
		'page',
		'archive',
		'category',
		'tag',
		'author',
		'date',
		'search',
		'404',
		'attachment',
		'singular',
		'admin',
		'feed',
		'trackback',
		'preview',
		'customize_preview',
	);

	/**
	 * Evaluate page type condition.
	 *
	 * @param array $condition Condition data containing type, operator, and value.
	 * @param array $context   Current context data for evaluation.
	 * @return bool
	 */
	protected function doEvaluate( array $condition, array $context = array() ) {
		$page_type = $condition['value'];
		$operator = $condition['operator'];

		// Get current page type from context or WordPress functions.
		$current_page_type = $this->getCurrentPageType( $context );

		// Handle custom post type conditions.
		if ( ! in_array( $page_type, $this->supported_page_types, true ) ) {
			$current_page_type = $this->getCustomPostType( $context );
		}

		// Apply operator logic.
		return $this->applyOperator( $current_page_type, $page_type, $operator );
	}

	/**
	 * Validate page type value.
	 *
	 * @param mixed  $value    Condition value to validate.
	 * @param string $operator Operator being used.
	 * @return array
	 */
	protected function validateValue( $value, $operator ) {
		$result = array(
			'valid'  => true,
			'errors' => array(),
		);

		if ( empty( $value ) ) {
			$result['valid'] = false;
			$result['errors'][] = 'Page type value cannot be empty';
			return $result;
		}

		if ( ! is_string( $value ) ) {
			$result['valid'] = false;
			$result['errors'][] = 'Page type value must be a string';
			return $result;
		}

		// Allow custom post types in addition to supported page types.
		$all_post_types = get_post_types( array(), 'names' );
		$valid_types = array_merge( $this->supported_page_types, $all_post_types );

		if ( ! in_array( $value, $valid_types, true ) ) {
			$result['valid'] = false;
			$result['errors'][] = "Invalid page type '{$value}'. Supported types: " . implode( ', ', $this->supported_page_types );
		}

		return $result;
	}

	/**
	 * Get current page type using WordPress conditional functions.
	 *
	 * @param array $context Current context data.
	 * @return string
	 */
	protected function getCurrentPageType( $context ) {
		// Check if page type is provided in context (for testing).
		if ( isset( $context['page_type'] ) ) {
			return $context['page_type'];
		}

		// Use WordPress conditional functions to determine page type.
		if ( is_home() ) {
			return 'home';
		}

		if ( is_front_page() ) {
			return 'front_page';
		}

		if ( is_single() ) {
			return 'single';
		}

		if ( is_page() ) {
			return 'page';
		}

		if ( is_category() ) {
			return 'category';
		}

		if ( is_tag() ) {
			return 'tag';
		}

		if ( is_author() ) {
			return 'author';
		}

		if ( is_date() ) {
			return 'date';
		}

		if ( is_archive() ) {
			return 'archive';
		}

		if ( is_search() ) {
			return 'search';
		}

		if ( is_404() ) {
			return '404';
		}

		if ( is_attachment() ) {
			return 'attachment';
		}

		if ( is_singular() ) {
			return 'singular';
		}

		if ( is_admin() ) {
			return 'admin';
		}

		if ( is_feed() ) {
			return 'feed';
		}

		if ( is_trackback() ) {
			return 'trackback';
		}

		if ( is_preview() ) {
			return 'preview';
		}

		if ( is_customize_preview() ) {
			return 'customize_preview';
		}

		// Default fallback.
		return 'unknown';
	}

	/**
	 * Get custom post type for current page.
	 *
	 * @param array $context Current context data.
	 * @return string
	 */
	protected function getCustomPostType( $context ) {
		// Check if custom post type is provided in context.
		if ( isset( $context['post_type'] ) ) {
			return $context['post_type'];
		}

		// Get current post type.
		global $post;
		if ( $post && isset( $post->post_type ) ) {
			return $post->post_type;
		}

		// Try to get from query.
		$queried_object = get_queried_object();
		if ( $queried_object && isset( $queried_object->post_type ) ) {
			return $queried_object->post_type;
		}

		// Check if we're on a custom post type archive.
		if ( is_post_type_archive() ) {
			return get_query_var( 'post_type' );
		}

		return 'unknown';
	}

	/**
	 * Get supported page types for validation and UI.
	 *
	 * @return array
	 */
	public function getSupportedPageTypes() {
		return $this->supported_page_types;
	}

	/**
	 * Get all available page types including custom post types.
	 *
	 * @return array
	 */
	public function getAllPageTypes() {
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		return array_merge( $this->supported_page_types, $post_types );
	}
}