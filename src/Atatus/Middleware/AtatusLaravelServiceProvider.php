<?php
namespace Atatus\Middleware;
use Illuminate\Support\ServiceProvider;

class AtatusLaravelServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/config/atatus.php' => config_path('atatus.php'),
        ]);
    }
    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function register()
    {
        // $this->mergeConfigFrom(
        //     __DIR__.'/config/atatus.php', 'atatus'
        // );
    }
}
