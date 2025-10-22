<?php
/**
 * Base Service Provider
 *
 * @package ViraCode
 */

namespace ViraCode\Providers;

use ViraCode\App;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ServiceProvider Abstract Class
 *
 * Base class for all service providers.
 */
abstract class ServiceProvider {

	/**
	 * The application instance.
	 *
	 * @var App
	 */
	protected $app;

	/**
	 * Constructor.
	 *
	 * @param App $app Application instance.
	 */
	public function __construct( App $app ) {
		$this->app = $app;
	}

	/**
	 * Register the service provider.
	 *
	 * This method is called when the provider is registered.
	 * Use this to bind things into the container.
	 *
	 * @return void
	 */
	abstract public function register();

	/**
	 * Boot the service provider.
	 *
	 * This method is called after all providers have been registered.
	 * Use this to register hooks, filters, and other WordPress functionality.
	 *
	 * @return void
	 */
	public function boot() {
		// Optional boot method - can be overridden by child classes.
	}
}
