<?php

namespace App\Http\Controllers;

use App\Models\ResultType;

class ResultTypeController extends Controller
{
    public function index()
    {
        $resultTypes = ResultType::all();

        return response()->json($resultTypes);
    }
}
