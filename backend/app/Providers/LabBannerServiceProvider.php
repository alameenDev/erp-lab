<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class LabBannerServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../../routes/lab-banners.php');
    }
}
