<?php

namespace App\Http\Controllers;

use App\Models\ResultStatus;

class ResultStatusController extends Controller
{
    public function index()
    {
        $result_statuses = ResultStatus::all();

        return response()->json($result_statuses);
    }
}
