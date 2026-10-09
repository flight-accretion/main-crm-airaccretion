<?php

namespace App\Services;

use App\Models\Country;
use App\Models\ExtraService;
use App\Models\Product;
use App\Models\Service;
use App\Models\UserType;
use Illuminate\Support\Facades\Cache;

class MasterDataCacheService
{
    public function activeProducts()
    {
        return Cache::remember('crm.master.products.active', $this->ttl(), function () {
            return Product::where('status', 1)->orderBy('product')->get();
        });
    }

    public function activeServices()
    {
        return Cache::remember('crm.master.services.active', $this->ttl(), function () {
            return Service::where('status', 1)->orderBy('service')->get();
        });
    }

    public function activeExtraServices()
    {
        return Cache::remember('crm.master.extra_services.active', $this->ttl(), function () {
            return ExtraService::where('status', 1)->orderBy('extra_service')->get();
        });
    }

    public function countries()
    {
        return Cache::remember('crm.master.countries', $this->ttl(), function () {
            return Country::orderBy('name')->get();
        });
    }

    public function activeUserTypes()
    {
        return Cache::remember('crm.master.user_types.active', $this->ttl(), function () {
            return UserType::where('status', 1)->orderBy('user_type')->get();
        });
    }

    public function clear(): void
    {
        foreach ([
            'crm.master.products.active', 'crm.master.services.active',
            'crm.master.extra_services.active', 'crm.master.countries',
            'crm.master.user_types.active', 'active_products', 'active_services',
        ] as $key) {
            Cache::forget($key);
        }
    }

    private function ttl(): int
    {
        return max(1, (int) config('crm.master_cache_ttl', 900));
    }
}
