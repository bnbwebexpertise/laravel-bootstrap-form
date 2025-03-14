<?php

namespace Bnb\BootstrapForm;

use Illuminate\Support\ServiceProvider;

class BootstrapFormServiceProvider extends ServiceProvider
{

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;


    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config/config.php', 'bootstrap_form');

        $this->app->singleton('bootstrap_form', function ($app) {
            $form = (new FormBuilder($app['form']))->setSessionStore($app['session.store']);
            $class = config('bootstrap_form.builder_class');

            return new $class($app['html'], $form, $app['config']);
        });
    }


    /**
     * Boot the service provider.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/config/config.php' => config_path('bootstrap_form.php')
        ], 'config');
    }


    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return ['bootstrap_form'];
    }
}
