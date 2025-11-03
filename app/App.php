<?php
/**
 * Main Application Class
 *
 * @package ViraCode
 */

namespace ViraCode;

use ViraCode\Providers\AdminServiceProvider;
use ViraCode\Providers\ApiServiceProvider;
use ViraCode\Providers\HookServiceProvider;
use ViraCode\Providers\ConditionalLogicServiceProvider;
use ViraCode\Providers\FileStorageServiceProvider;

if (!defined("ABSPATH")) {
    exit();
}

/**
 * App Class
 *
 * Main application class that handles plugin initialization
 * and service provider registration.
 */
class App
{
    /**
     * The single instance of the class.
     *
     * @var App|null
     */
    private static $instance = null;

    /**
     * Service providers.
     *
     * @var array
     */
    protected $providers = [];

    /**
     * Booted status.
     *
     * @var bool
     */
    protected $booted = false;

    /**
     * Admin controller instance.
     *
     * @var \ViraCode\Http\Controllers\AdminController|null
     */
    public $adminController = null;

    /**
     * API controller instance.
     *
     * @var \ViraCode\Http\Controllers\ApiController|null
     */
    public $apiController = null;

    /**
     * Snippet executor instance.
     *
     * @var \ViraCode\Services\SnippetExecutor|null
     */
    public $snippetExecutor = null;

    /**
     * Safe mode hook instance.
     *
     * @var \ViraCode\Hooks\SafeModeHook|null
     */
    public $safeModeHook = null;

    /**
     * Conditional logic service instance.
     *
     * @var \ViraCode\Services\ConditionalLogicService|null
     */
    public $conditionalLogicService = null;

    /**
     * Conditional logic performance monitor instance.
     *
     * @var \ViraCode\Services\ConditionalLogicPerformanceMonitor|null
     */
    public $conditionalLogicPerformanceMonitor = null;

    /**
     * Conditional logic debugger instance.
     *
     * @var \ViraCode\Services\ConditionalLogicDebugger|null
     */
    public $conditionalLogicDebugger = null;

    /**
     * File storage service instance.
     *
     * @var \ViraCode\Services\FileStorageService|null
     */
    public $fileStorageService = null;

    /**
     * File loader service instance.
     *
     * @var \ViraCode\Services\FileLoaderService|null
     */
    public $fileLoaderService = null;

    /**
     * Migration service instance.
     *
     * @var \ViraCode\Services\MigrationService|null
     */
    public $migrationService = null;

    /**
     * Snippet manager service instance.
     *
     * @var \ViraCode\Services\SnippetManagerService|null
     */
    public $snippetManagerService = null;

    /**
     * Migration controller instance.
     *
     * @var \ViraCode\Http\MigrationController|null
     */
    public $migrationController = null;

    /**
     * Library file storage service instance.
     *
     * @var \ViraCode\Services\LibraryFileStorageService|null
     */
    public $libraryFileStorageService = null;

    /**
     * Private constructor to prevent direct instantiation.
     */
    private function __construct()
    {
        // Private constructor for singleton pattern.
    }

    /**
     * Get the singleton instance.
     *
     * @return App
     */
    public static function getInstance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Boot the application.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->booted) {
            return;
        }

        $this->registerProviders();
        $this->bootProviders();

        $this->booted = true;

        do_action("vira_code/app_booted", $this);
    }

    /**
     * Register service providers.
     *
     * @return void
     */
    protected function registerProviders()
    {
        $providers = [
            AdminServiceProvider::class,
            ApiServiceProvider::class,
            HookServiceProvider::class,
            ConditionalLogicServiceProvider::class,
            FileStorageServiceProvider::class,
        ];

        $providers = apply_filters("vira_code/service_providers", $providers);

        foreach ($providers as $provider) {
            $this->registerProvider($provider);
        }
    }

    /**
     * Register a single service provider.
     *
     * @param string $provider Provider class name.
     * @return void
     */
    protected function registerProvider($provider)
    {
        if (!class_exists($provider)) {
            return;
        }

        $instance = new $provider($this);
        $instance->register();

        $this->providers[] = $instance;
    }

    /**
     * Boot all registered service providers.
     *
     * @return void
     */
    protected function bootProviders()
    {
        foreach ($this->providers as $provider) {
            if (method_exists($provider, "boot")) {
                $provider->boot();
            }
        }
    }

    /**
     * Get all registered providers.
     *
     * @return array
     */
    public function getProviders()
    {
        return $this->providers;
    }

    /**
     * Check if the app is booted.
     *
     * @return bool
     */
    public function isBooted()
    {
        return $this->booted;
    }

    /**
     * Prevent cloning of the instance.
     *
     * @return void
     */
    private function __clone()
    {
        // Prevent cloning.
    }

    /**
     * Prevent unserializing of the instance.
     *
     * @return void
     */
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize singleton");
    }
}
