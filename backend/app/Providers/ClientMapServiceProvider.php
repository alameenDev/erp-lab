<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class ClientMapServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../../routes/client-map.php');
    }
}
