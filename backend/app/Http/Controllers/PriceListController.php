<?php

namespace App\Http\Controllers;

use App\Models\Culture;
use App\Models\Package;
use App\Models\PriceList;
use App\Models\PriceListRel;
use App\Models\Test;
use App\Models\TestGroup;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PriceListController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasPermissionTo('price list view')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $query = PriceList::with(['lab']);

        // Admin (role_id = 1) can see all price lists
        if ($user->role_id != 1) {
            $users_ids = $this->getTenantUserIds();
            $query->whereIn('lab_id_fk', $users_ids);
        }

        $PriceLists = $query->orderBy('created_at', 'desc')->get();
        if (count($PriceLists) == 0) {
            return response()->json($PriceLists, 200);
        }
        $PriceLists = $PriceLists->map(function ($PriceList) {
            return [
                'id' => $PriceList->id,
                'price_list_title' => $PriceList->name,
                'lab' => $PriceList->lab?->name,
                'discount' => $PriceList->discount,
            ];
        });

        return response()->json($PriceLists);
    }

    public function show(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasPermissionTo('price list view')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $PriceList = PriceList::with('priceListRels.test:id,name', 'priceListRels.testGroup:id,group_name', 'priceListRels.culture:id,name', 'priceListRels.package:id,name', 'lab:id,name')->find($request->id);
        if (! $PriceList) {
            return response()->json(['message' => 'price list not found'], 404);
        }

        $authUser = Auth::user();
        if ($authUser && $authUser->role_id != 1) {
            $users_ids = $this->getTenantUserIds();
            if (!in_array($PriceList->lab_id_fk, $users_ids)) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        $tests = [];
        $test_groups = [];
        $cultures = [];
        $packages = [];
        foreach ($PriceList->priceListRels as $priceListRel) {
            if ($priceListRel->test) {
                $tests[] = [
                    'price_list_rel_id' => $priceListRel->id,
                    'test_id' => $priceListRel->test?->id,
                    'name' => $priceListRel->test?->name,
                    'original_price' => $priceListRel->original_price,
                    'price_for_customer' => $priceListRel->price_for_customer,
                ];
            } elseif ($priceListRel->testGroup) {
                $test_groups[] = [
                    'price_list_rel_id' => $priceListRel->id,
                    'test_group_id' => $priceListRel->testGroup?->id,
                    'name' => $priceListRel->testGroup?->group_name,
                    'original_price' => $priceListRel->original_price,
                    'price_for_customer' => $priceListRel->price_for_customer,
                ];
            } elseif ($priceListRel->culture) {
                $cultures[] = [
                    'price_list_rel_id' => $priceListRel->id,
                    'culture_id' => $priceListRel->culture?->id,
                    'name' => $priceListRel->culture?->name,
                    'original_price' => $priceListRel->original_price,
                    'price_for_customer' => $priceListRel->price_for_customer,
                ];
            } elseif ($priceListRel->package) {
                $packages[] = [
                    'price_list_rel_id' => $priceListRel->id,
                    'package_id' => $priceListRel->package?->id,
                    'name' => $priceListRel->package?->name,
                    'original_price' => $priceListRel->original_price,
                    'price_for_customer' => $priceListRel->price_for_customer,
                ];
            }
        }
        $price_list_items = [
            'tests' => $tests,
            'test_groups' => $test_groups,
            'cultures' => $cultures,
            'packages' => $packages,
        ];

        return response()->json([
            'id' => $PriceList->id,
            'price_list_title' => $PriceList->name,
            'price_list_items' => $price_list_items,
            'lab' => $PriceList->lab?->name,
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasPermissionTo('price list create')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'name' => 'required|string',
            'discount' => 'nullable|numeric',
            'tests' => 'nullable|array',
            'tests.*.id' => 'required|exists:tests,id',
            'tests.*.price' => 'nullable|numeric',
            'groups' => 'nullable|array',
            'groups.*.id' => 'required|exists:test_groups,id',
            'groups.*.price' => 'nullable|numeric',
            'cultures' => 'nullable|array',
            'cultures.*.id' => 'required|exists:cultures,id',
            'cultures.*.price' => 'nullable|numeric',
            'packages' => 'nullable|array',
            'packages.*.id' => 'required|exists:packages,id',
            'packages.*.price' => 'nullable|numeric',
        ]);

        $PriceList = PriceList::create([
            'name' => $request->name,
            'lab_id_fk' => Auth::user()->id,
            'discount' => $request->discount,
        ]);
        if (! $PriceList) {
            return response()->json(['message' => 'Something went wrong'], 500);
        }

        // Batch load all entities to avoid N+1 queries
        $testsMap = collect();
        $groupsMap = collect();
        $culturesMap = collect();
        $packagesMap = collect();

        if ($request->tests) {
            $testIds = collect($request->tests)->pluck('id')->toArray();
            $testsMap = Test::whereIn('id', $testIds)->get()->keyBy('id');
        }
        if ($request->groups) {
            $groupIds = collect($request->groups)->pluck('id')->toArray();
            $groupsMap = TestGroup::whereIn('id', $groupIds)->get()->keyBy('id');
        }
        if ($request->cultures) {
            $cultureIds = collect($request->cultures)->pluck('id')->toArray();
            $culturesMap = Culture::whereIn('id', $cultureIds)->get()->keyBy('id');
        }
        if ($request->packages) {
            $packageIds = collect($request->packages)->pluck('id')->toArray();
            $packagesMap = Package::whereIn('id', $packageIds)->get()->keyBy('id');
        }

        if ($request->tests) {
            foreach ($request->tests as $test) {
                $testToAdd = $testsMap->get($test['id']);
                PriceListRel::create([
                    'price_list_id_fk' => $PriceList->id,
                    'lab_id_fk' => Auth::user()->id,
                    'test_id_fk' => $test['id'],
                    'original_price' => $testToAdd->price ?? 0,
                    'price_for_customer' => $test['price'] ?? 0,
                ]);
            }
        }
        if ($request->groups) {
            foreach ($request->groups as $test_group) {
                $groupToAdd = $groupsMap->get($test_group['id']);
                PriceListRel::create([
                    'price_list_id_fk' => $PriceList->id,
                    'lab_id_fk' => Auth::user()->id,
                    'test_group_id_fk' => $test_group['id'],
                    'original_price' => $groupToAdd->original_price ?? 0,
                    'price_for_customer' => $test_group['price'] ?? 0,
                ]);
            }
        }
        if ($request->cultures) {
            foreach ($request->cultures as $culture) {
                $cultureToAdd = $culturesMap->get($culture['id']);
                PriceListRel::create([
                    'price_list_id_fk' => $PriceList->id,
                    'lab_id_fk' => Auth::user()->id,
                    'culture_id_fk' => $culture['id'],
                    'original_price' => $cultureToAdd->price ?? 0,
                    'price_for_customer' => $culture['price'] ?? 0,
                ]);
            }
        }

        if ($request->packages) {
            foreach ($request->packages as $package) {
                $packageToAdd = $packagesMap->get($package['id']);
                PriceListRel::create([
                    'price_list_id_fk' => $PriceList->id,
                    'lab_id_fk' => Auth::user()->id,
                    'package_id_fk' => $package['id'],
                    'original_price' => $packageToAdd->price ?? 0,
                    'price_for_customer' => $package['price'] ?? 0,
                ]);
            }
        }
        ActivityLogController::storeActivity('إنشاء قائمة الأسعار', $PriceList);

        return response()->json($PriceList, 201);
    }

    public function update(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasPermissionTo('price list edit')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'name' => 'required|string',
            'discount' => 'nullable|numeric',
            'tests' => 'nullable|array',
            'tests.*.id' => 'required|exists:tests,id',
            'tests.*.price' => 'nullable|numeric',
            'groups' => 'nullable|array',
            'groups.*.id' => 'required|exists:test_groups,id',
            'groups.*.price' => 'nullable|numeric',
            'cultures' => 'nullable|array',
            'cultures.*.id' => 'required|exists:cultures,id',
            'cultures.*.price' => 'nullable|numeric',
            'packages' => 'nullable|array',
            'packages.*.id' => 'required|exists:packages,id',
            'packages.*.price' => 'nullable|numeric',
        ]);
        $PriceList = PriceList::find($request->id);
        if (! $PriceList) {
            return response()->json(['message' => 'price list not found'], 404);
        }

        $authUser = Auth::user();
        if ($authUser && $authUser->role_id != 1) {
            $users_ids = $this->getTenantUserIds();
            if (!in_array($PriceList->lab_id_fk, $users_ids)) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        $old_price_list = $PriceList->replicate();
        $PriceList->update([
            'name' => $request->name,
            'discount' => $request->discount,
        ]);
        if (! $PriceList) {
            return response()->json(['message' => 'Something went wrong'], 500);
        }

        // Batch load all entities to avoid N+1 queries
        $priceListRelIds = collect();
        $testIds = collect();
        $groupIds = collect();
        $cultureIds = collect();
        $packageIds = collect();

        if ($request->tests) {
            $priceListRelIds = $priceListRelIds->merge(collect($request->tests)->pluck('price_list_rel_id'));
            $testIds = collect($request->tests)->pluck('id');
        }
        if ($request->groups) {
            $priceListRelIds = $priceListRelIds->merge(collect($request->groups)->pluck('price_list_rel_id'));
            $groupIds = collect($request->groups)->pluck('id');
        }
        if ($request->cultures) {
            $priceListRelIds = $priceListRelIds->merge(collect($request->cultures)->pluck('price_list_rel_id'));
            $cultureIds = collect($request->cultures)->pluck('id');
        }
        if ($request->packages) {
            $priceListRelIds = $priceListRelIds->merge(collect($request->packages)->pluck('price_list_rel_id'));
            $packageIds = collect($request->packages)->pluck('id');
        }

        $priceListRelsMap = PriceListRel::whereIn('id', $priceListRelIds->filter()->toArray())->get()->keyBy('id');
        $testsMap = $testIds->isNotEmpty() ? Test::whereIn('id', $testIds->toArray())->get()->keyBy('id') : collect();
        $groupsMap = $groupIds->isNotEmpty() ? TestGroup::whereIn('id', $groupIds->toArray())->get()->keyBy('id') : collect();
        $culturesMap = $cultureIds->isNotEmpty() ? Culture::whereIn('id', $cultureIds->toArray())->get()->keyBy('id') : collect();
        $packagesMap = $packageIds->isNotEmpty() ? Package::whereIn('id', $packageIds->toArray())->get()->keyBy('id') : collect();

        if ($request->tests) {
            foreach ($request->tests as $item) {
                $price_list_rel = $priceListRelsMap->get($item['price_list_rel_id'] ?? null);
                if ($price_list_rel && $price_list_rel->original_price == null) {
                    $testToAdd = $testsMap->get($item['id']);
                    $price_list_rel->update([
                        'original_price' => $testToAdd->price ?? 0,
                        'price_for_customer' => $item['price'] ?? 0,
                    ]);
                } elseif ($price_list_rel) {
                    $price_list_rel->update([
                        'price_for_customer' => $item['price'] ?? 0,
                    ]);
                } else {
                    $testToAdd = $testsMap->get($item['id']);
                    PriceListRel::create([
                        'price_list_id_fk' => $PriceList->id,
                        'lab_id_fk' => $PriceList->lab_id_fk,
                        'test_id_fk' => $item['id'],
                        'original_price' => $testToAdd->price ?? 0,
                        'price_for_customer' => $item['price'] ?? 0,
                    ]);
                }
            }
        }
        if ($request->groups) {
            foreach ($request->groups as $item) {
                $price_list_rel = $priceListRelsMap->get($item['price_list_rel_id'] ?? null);
                if ($price_list_rel && $price_list_rel->original_price == null) {
                    $groupToAdd = $groupsMap->get($item['id']);
                    $price_list_rel->update([
                        'original_price' => $groupToAdd->original_price ?? 0,
                        'price_for_customer' => $item['price'] ?? 0,
                    ]);
                } elseif ($price_list_rel) {
                    $price_list_rel->update([
                        'price_for_customer' => $item['price'] ?? 0,
                    ]);
                } else {
                    $groupToAdd = $groupsMap->get($item['id']);
                    PriceListRel::create([
                        'price_list_id_fk' => $PriceList->id,
                        'lab_id_fk' => $PriceList->lab_id_fk,
                        'test_group_id_fk' => $item['id'],
                        'original_price' => $groupToAdd->original_price ?? 0,
                        'price_for_customer' => $item['price'] ?? 0,
                    ]);
                }
            }
        }
        if ($request->cultures) {
            foreach ($request->cultures as $item) {
                $price_list_rel = $priceListRelsMap->get($item['price_list_rel_id'] ?? null);
                if ($price_list_rel && $price_list_rel->original_price == null) {
                    $cultureToAdd = $culturesMap->get($item['id']);
                    $price_list_rel->update([
                        'original_price' => $cultureToAdd->price ?? 0,
                        'price_for_customer' => $item['price'] ?? 0,
                    ]);
                } elseif ($price_list_rel) {
                    $price_list_rel->update([
                        'price_for_customer' => $item['price'] ?? 0,
                    ]);
                } else {
                    $cultureToAdd = $culturesMap->get($item['id']);
                    PriceListRel::create([
                        'price_list_id_fk' => $PriceList->id,
                        'lab_id_fk' => $PriceList->lab_id_fk,
                        'culture_id_fk' => $item['id'],
                        'original_price' => $cultureToAdd->price ?? 0,
                        'price_for_customer' => $item['price'] ?? 0,
                    ]);
                }
            }
        }
        if ($request->packages) {
            foreach ($request->packages as $item) {
                $price_list_rel = $priceListRelsMap->get($item['price_list_rel_id'] ?? null);
                if ($price_list_rel && $price_list_rel->original_price == null) {
                    $packageToAdd = $packagesMap->get($item['id']);
                    $price_list_rel->update([
                        'original_price' => $packageToAdd->price ?? 0,
                        'price_for_customer' => $item['price'] ?? 0,
                    ]);
                } elseif ($price_list_rel) {
                    $price_list_rel->update([
                        'price_for_customer' => $item['price'] ?? 0,
                    ]);
                } else {
                    $packageToAdd = $packagesMap->get($item['id']);
                    PriceListRel::create([
                        'price_list_id_fk' => $PriceList->id,
                        'lab_id_fk' => $PriceList->lab_id_fk,
                        'package_id_fk' => $item['id'],
                        'original_price' => $packageToAdd->price ?? 0,
                        'price_for_customer' => $item['price'] ?? 0,
                    ]);
                }
            }
        }
        ActivityLogController::updateActivity('تعديل قائمة الأسعار', $old_price_list, $PriceList);

        return response()->json($PriceList);
    }

    public function destroy(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasPermissionTo('price list delete')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $PriceList = PriceList::find($request->id);
        if (! $PriceList) {
            return response()->json(['message' => 'price list not found'], 404);
        }

        $authUser = Auth::user();
        if ($authUser && $authUser->role_id != 1) {
            $users_ids = $this->getTenantUserIds();
            if (!in_array($PriceList->lab_id_fk, $users_ids)) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        ActivityLogController::deleteActivity('حذف قائمة الأسعار', $PriceList);
        $PriceList->delete();

        return response()->json(['message' => 'price list deleted successfully']);
    }
}
