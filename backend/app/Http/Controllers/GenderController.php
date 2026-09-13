<?php

namespace App\Http\Controllers;

use App\Models\Gender;
use Illuminate\Http\Request;

class GenderController extends Controller
{
    public function index()
    {
        $genders = Gender::all();

        return response()->json($genders);
    }

    public function show($id)
    {
        $gender = Gender::find($id);
        if (!$gender) {
            return response()->json(['message' => 'Gender not found'], 404);
        }

        return response()->json($gender);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'gender_type' => 'required|string',
        ]);

        $gender = Gender::create($validatedData);

        return response()->json($gender, 201);
    }

    public function update(Request $request, $id)
    {
        $gender = Gender::find($id);
        if (!$gender) {
            return response()->json(['message' => 'Gender not found'], 404);
        }

        $validatedData = $request->validate([
            'gender_type' => 'required|string',
        ]);

        $gender->update($validatedData);

        return response()->json($gender);
    }

    public function destroy($id)
    {
        $gender = Gender::find($id);
        if (!$gender) {
            return response()->json(['message' => 'Gender not found'], 404);
        }

        $gender->delete();

        return response()->json(['message' => 'Gender deleted successfully']);
    }
}
