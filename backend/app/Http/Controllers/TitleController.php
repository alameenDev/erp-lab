<?php

namespace App\Http\Controllers;

use App\Models\Title;
use Illuminate\Http\Request;

class TitleController extends Controller
{
    public function index()
    {
        $titles = Title::all();

        return response()->json($titles);
    }

    public function show(Request $request)
    {
        $title = Title::find($request->id);
        if (!$title) {
            return response()->json(['message' => 'Title not found'], 404);
        }

        return response()->json($title);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'title' => 'required|string',
        ]);

        $title = Title::create($validatedData);

        return response()->json($title, 201);
    }

    public function update(Request $request)
    {
        $title = Title::find($request->id);
        if (!$title) {
            return response()->json(['message' => 'Title not found'], 404);
        }

        $validatedData = $request->validate([
            'title' => 'required|string',
        ]);

        $title->update($validatedData);

        return response()->json($title);
    }

    public function destroy(Request $request)
    {
        $title = Title::find($request->id);
        if (!$title) {
            return response()->json(['message' => 'Title not found'], 404);
        }

        $title->delete();

        return response()->json(['message' => 'Title deleted successfully']);
    }
}
