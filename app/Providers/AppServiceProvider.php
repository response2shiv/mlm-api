<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App;
use App\Services\BinaryPlanService;
use App\Services\HoldingTankService;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind('mailgun.client', function () {
            return \Http\Adapter\Guzzle6\Client::createWithConfig([]);
        });
        // Add application custom services
        App::singleton('ibu.service.binary_plan_tree', BinaryPlanService::class);
        App::singleton('ibu.service.holding_tank', HoldingTankService::class);

        if (config('app.env') != 'local') {
            URL::forceScheme('https');
        }
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {

    }
}
