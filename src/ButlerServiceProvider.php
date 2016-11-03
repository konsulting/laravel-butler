<?php

namespace Konsulting\Butler;

use Illuminate\Support\ServiceProvider;

class ButlerServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadViewsFrom(__DIR__.'/../views', 'butler');

        $this->publishes([
            __DIR__ . '/../config/butler.php' => config_path('butler.php'),
        ], 'config');

        $this->publishes([
            __DIR__.'/../views' => resource_path('views/vendor/butler'),
        ], 'views');

        $this->loadMigrationsFrom(__DIR__.'/../migrations');
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/butler.php', 'butler');

        $this->app->singleton('butler', function ($app) {
            return new Butler(
                $app['config']['butler.providers'],
                $app['config']['butler.route_map']
            );
        });

        $this->app->singleton('butler_user_provider', function ($app) {
            $class = $app['config']['butler.user_provider'];

            $provider = new $class($app['config']['butler.user_class']);
            $provider->canCreateUsers($app['config']['butler.can_create_users']);

            return $provider;
        });
    }
}
