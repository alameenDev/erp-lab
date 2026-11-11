<?php
namespace App\Console\Commands;
use App\Demo\DemoIsolation;
use Illuminate\Console\Command;
class InitializeLearningDemo extends Command {
    protected $signature = 'demo:install';
    protected $description = 'Validate the isolated demo database before migrating and seeding reference data';
    public function handle(): int {
        DemoIsolation::assertConfigured();
        foreach ([['migrate',['--force'=>true]], ['db:seed',['--class'=>'ReferenceDataSeeder','--force'=>true]], ['db:seed',['--class'=>'PermissionsSeeder','--force'=>true]]] as [$command,$arguments]) {
            if ($this->call($command,$arguments) !== 0) return self::FAILURE;
        }
        return self::SUCCESS;
    }
}
