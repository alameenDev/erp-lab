<?php

namespace App\Console\Commands;

use App\Services\Sync\SyncExchange;
use Illuminate\Console\Command;

class SyncExchangeCommand extends Command
{
    protected $signature = 'lab-sync:exchange {peer : Configured staging peer ID}';
    protected $description = 'Exchange a bounded staging batch; does not apply clinical data';

    public function handle(SyncExchange $exchange): int
    {
        if (!config('lab_sync.enabled')) {
            $this->error('Sync transport is disabled. Clinical synchronization is not available in this release.');
            return self::FAILURE;
        }
        if (!ctype_digit((string) $this->argument('peer')) || (int) $this->argument('peer') < 1) {
            $this->error('Invalid peer ID.');
            return self::FAILURE;
        }
        try {
            $result = $exchange->run((int) $this->argument('peer'));
            $this->info('Staged transport: sent '.$result['sent'].', received '.$result['received'].'. Clinical data was NOT applied.');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Exchange did not complete. Events remain queued; inspect peer configuration and connectivity.');
            return self::FAILURE;
        }
    }
}
