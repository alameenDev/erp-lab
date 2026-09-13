<?php

namespace App\Http\Controllers;

use App\Models\AnswerType;

class AnswerTypeController extends Controller
{
    public function index()
    {
        $answerTypes = AnswerType::all();

        return response()->json($answerTypes);
    }
}
