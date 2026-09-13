<?php

namespace App\Http\Controllers;

use App\Models\AgeUnit;
use Illuminate\Http\Request;

class AgeUnitController extends Controller
{
    public function index()
    {
        $ageUnits = AgeUnit::all();

        return response()->json($ageUnits);
    }

    public function show($id)
    {
        $ageUnit = AgeUnit::find($id);

        if (! $ageUnit) {
            return response()->json(['message' => 'Age Unit not found'], 404);
        }

        return response()->json($ageUnit);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'unit_name' => 'required|string',
        ]);

        $ageUnit = AgeUnit::create($validatedData);

        return response()->json($ageUnit, 201);
    }

    public function update(Request $request, $id)
    {
        $ageUnit = AgeUnit::find($id);
        if (! $ageUnit) {
            return response()->json(['message' => 'Age Unit not found'], 404);
        }

        $validatedData = $request->validate([
            'unit_name' => 'required|string',
        ]);

        $ageUnit->update($validatedData);

        // Clear relevant caches when updating
        return response()->json($ageUnit);
    }

    public function destroy($id)
    {
        $ageUnit = AgeUnit::find($id);
        if (! $ageUnit) {
            return response()->json(['message' => 'Age Unit not found'], 404);
        }

        $ageUnit->delete();

        return response()->json(['message' => 'Age Unit deleted successfully']);
    }
}
