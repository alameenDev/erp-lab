<?php
namespace App\Observers;
use App\Models\InvoiceTestRel;
use App\Services\InventoryService;
class InvoiceTestRelObserver { public function saved(InvoiceTestRel $relation):void { if(app()->runningInConsole()&&!app()->runningUnitTests())return; app(InventoryService::class)->consumeInitial($relation); } }
