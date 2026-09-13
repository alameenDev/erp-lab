<?php

namespace App\Http\Controllers;

use App\Models\Nationality;
use Illuminate\Http\Request;

class NationalityController extends Controller
{
    public function index()
    {
        $nationalities = Nationality::all();

        return response()->json($nationalities);
    }

    public function show($id)
    {
        $nationality = Nationality::find($id);
        if (!$nationality) {
            return response()->json(['message' => 'Nationality not found'], 404);
        }

        return response()->json($nationality);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'country_name' => 'required|string',
        ]);

        $nationality = Nationality::create($validatedData);

        return response()->json($nationality, 201);
    }

    public function update(Request $request, $id)
    {
        $nationality = Nationality::find($id);
        if (!$nationality) {
            return response()->json(['message' => 'Nationality not found'], 404);
        }

        $validatedData = $request->validate([
            'country_name' => 'required|string',
        ]);

        $nationality->update($validatedData);

        return response()->json($nationality);
    }

    public function destroy($id)
    {
        $nationality = Nationality::find($id);
        if (!$nationality) {
            return response()->json(['message' => 'Nationality not found'], 404);
        }

        $nationality->delete();

        return response()->json(['message' => 'Nationality deleted successfully']);
    }
}
