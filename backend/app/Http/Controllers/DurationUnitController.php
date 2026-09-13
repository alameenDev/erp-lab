<?php

namespace App\Http\Controllers;

use App\Models\DurationUnit;

class DurationUnitController extends Controller
{
    public function index()
    {
        $duration_units = DurationUnit::all();

        return response()->json($duration_units, 200);
    }
}
