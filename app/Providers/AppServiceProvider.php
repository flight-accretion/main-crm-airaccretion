<?php

namespace App\Providers;

use App\Models\Client;
use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\LeadRide;
use App\Observers\ClientObserver;
use App\Observers\LeadFollowupObserver;
use App\Observers\LeadObserver;
use App\Observers\LeadRideObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Lead::observe(LeadObserver::class);
        Client::observe(ClientObserver::class);
        LeadFollowup::observe(LeadFollowupObserver::class);
        LeadRide::observe(LeadRideObserver::class);
    }
}
