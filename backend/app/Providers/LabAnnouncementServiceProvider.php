<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class LabAnnouncementServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(base_path('routes/lab-announcements.php'));
    }
}
