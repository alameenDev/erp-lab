<?php
namespace App\Http\Controllers;

use App\Models\{Patient,User};
use App\Services\{InventoryService,LoyaltyService};
use Illuminate\Http\Request;

class ReceptionLoyaltyController extends Controller
{
    public function show(Request $request, int $patient)
    {
        abort_unless($request->user()->hasPermissionTo('invoices create'),403);
        $patient=Patient::findOrFail($patient);
        $owner=User::findOrFail($patient->creator_id);
        $resolver=app(InventoryService::class);
        abort_unless($resolver->labId($request->user())===$resolver->labId($owner),403);
        $lab=User::findOrFail($resolver->labId($owner));
        $service=app(LoyaltyService::class);
        $config=$service->config($lab);
        return response()->json(['enabled'=>$config['enabled'],'balance'=>$service->balance($patient),'rewards'=>collect($config['redemption_catalog'])->filter(fn($reward)=>(int)($reward['discount_amount']??0)>0)->values()]);
    }
}
