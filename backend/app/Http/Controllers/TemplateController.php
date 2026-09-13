<?php

namespace App\Http\Controllers;

use App\Models\Template;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TemplateController extends Controller
{
    public function index(Request $request)
    {
        $templateQuery = Template::query();

        // Multi-tenant filtering
        $authUser = Auth::user();
        if ($authUser && $authUser->role_id != 1) {
            $users_ids = $this->getTenantUserIds();
            $templateQuery->whereIn('lab_id_fk', $users_ids);
        }

        if ($request->has('name')) {
            $templateQuery->where('name', 'ilike', '%' . $request->name . '%');
        }
        if ($request->has('type')) {
            $templateQuery->where('type', $request->type);
        }
        if ($request->has('test_or_culture_id')) {
            $templateQuery->where(function ($q) use ($request) {
                $q->where('test_id_fk', $request->test_or_culture_id)->orWhere('culture_id_fk', $request->test_or_culture_id);
            });
        }
        $templateQuery->with(['test', 'culture', 'package', 'test_group']);
        $templateQuery->latest();
        $templates = $templateQuery->get();
        if ($templates->isEmpty()) {
            return response()->json([], 200);
        }
        $template = $templates->map(function ($template) {
            $type = '';
            $test_or_culture_id = '';
            $test_or_culture_name = '';
            switch ($template->type) {
                case 'test':
                    $type = 'test';
                    $test_or_culture_id = $template->test_id_fk;
                    $test_or_culture_name = $template->test?->name;
                    break;
                case 'culture':
                    $type = 'culture';
                    $test_or_culture_id = $template->culture_id_fk;
                    $test_or_culture_name = $template->culture?->name;
                    break;
                case 'package':
                    $type = 'package';
                    $test_or_culture_id = $template->package_id_fk;
                    $test_or_culture_name = $template->package?->name;
                    break;
                case 'test_group':
                    $type = 'test_group';
                    $test_or_culture_id = $template->test_group_id_fk;
                    $test_or_culture_name = $template->test_group?->name;
                    break;

            }

            return [
                'id' => $template->id,
                'name' => $template->name,
                'content' => $template->content,
                'test_or_culture_id' => $test_or_culture_id,
                'test_or_culture_name' => $test_or_culture_name,
                'type' => $type,
                'created_at' => $template->created_at,
                'updated_at' => $template->updated_at,
            ];
        });

        return response()->json($template);
    }

    public function show(Request $request)
    {
        $template = Template::with(['test', 'culture', 'package', 'test_group'])->firstWhere('id', $request->id);
        if (!$template) {
            return response()->json(['message' => 'Template not found'], 404);
        }

        // Tenant check
        $authUser = Auth::user();
        if ($authUser && $authUser->role_id != 1) {
            $users_ids = $this->getTenantUserIds();
            if (!in_array($template->lab_id_fk, $users_ids)) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        // Transform single template (not collection)
        $type = '';
        $test_or_culture_id = '';
        $test_or_culture_name = '';
        switch ($template->type) {
            case 'test':
                $type = 'test';
                $test_or_culture_id = $template->test_id_fk;
                $test_or_culture_name = $template->test?->name;
                break;
            case 'culture':
                $type = 'culture';
                $test_or_culture_id = $template->culture_id_fk;
                $test_or_culture_name = $template->culture?->name;
                break;
            case 'package':
                $type = 'package';
                $test_or_culture_id = $template->package_id_fk;
                $test_or_culture_name = $template->package?->name;
                break;
            case 'test_group':
                $type = 'test_group';
                $test_or_culture_id = $template->test_group_id_fk;
                $test_or_culture_name = $template->test_group?->name;
                break;
        }
        $result = [
            'id' => $template->id,
            'name' => $template->name,
            'content' => $template->content,
            'test_or_culture_id' => $test_or_culture_id,
            'test_or_culture_name' => $test_or_culture_name,
            'type' => $type,
            'created_at' => $template->created_at,
            'updated_at' => $template->updated_at,
        ];
        return response()->json($result);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'content' => 'required|array',
            'test_or_culture_id' => 'required|integer',
            'type' => 'required|string',
        ]);
        switch ($request->type) {
            case 'test':
                $template = Template::create([
                    'name' => $request->name,
                    'content' => $request['content'],
                    'test_id_fk' => $request->test_or_culture_id,
                    'type' => $request->type,
                    'lab_id_fk' => Auth::id(),
                ]);
                break;
            case 'culture':
                $template = Template::create([
                    'name' => $request->name,
                    'content' => $request['content'],
                    'culture_id_fk' => $request->test_or_culture_id,
                    'type' => $request->type,
                    'lab_id_fk' => Auth::id(),
                ]);
                break;
            case 'package':
                $template = Template::create([
                    'name' => $request->name,
                    'content' => $request['content'],
                    'package_id_fk' => $request->test_or_culture_id,
                    'type' => $request->type,
                    'lab_id_fk' => Auth::id(),
                ]);
                break;
            case 'test_group':
                $template = Template::create([
                    'name' => $request->name,
                    'content' => $request['content'],
                    'lab_id_fk' => Auth::id(),
                ]);
        }
        if ($template) {
            return response()->json(['message' => 'Template created successfully'], 201);
        }

        return response()->json(['message' => 'Template creation failed'], 400);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'content' => 'required|array',
            'test_or_culture_id' => 'required|integer',
            'type' => 'required|string',
        ]);

        $template = Template::find($id);
        if (!$template) {
            return response()->json(['message' => 'Template not found'], 404);
        }

        // Tenant check
        $authUser = Auth::user();
        if ($authUser && $authUser->role_id != 1) {
            $users_ids = $this->getTenantUserIds();
            if (!in_array($template->lab_id_fk, $users_ids)) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        switch ($request->type) {
            case 'test':
                $template->update([
                    'name' => $request->name,
                    'content' => $request['content'],
                    'test_id_fk' => $request->test_or_culture_id,
                    'type' => $request->type,
                ]);
                break;
            case 'culture':
                $template->update([
                    'name' => $request->name,
                    'content' => $request['content'],
                    'culture_id_fk' => $request->test_or_culture_id,
                    'type' => $request->type,
                ]);
                break;
            case 'package':
                $template->update([
                    'name' => $request->name,
                    'content' => $request['content'],
                    'package_id_fk' => $request->test_or_culture_id,
                    'type' => $request->type,
                ]);
                break;
            case 'test_group':
                $template->update([
                    'name' => $request->name,
                    'content' => $request['content'],
                    'test_group_id_fk' => $request->test_or_culture_id,
                    'type' => $request->type,
                ]);
                break;
        }
        if ($template) {
            return response()->json(['message' => 'Template updated successfully'], 200);
        }

        return response()->json(['message' => 'Template update failed'], 400);
    }

    public function destroy($id)
    {
        $template = Template::firstWhere('id', $id);
        if (!$template) {
            return response()->json(['message' => 'Template not found'], 404);
        }

        // Tenant check
        $authUser = Auth::user();
        if ($authUser && $authUser->role_id != 1) {
            $users_ids = $this->getTenantUserIds();
            if (!in_array($template->lab_id_fk, $users_ids)) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        $template->delete();

        return response()->json(['message' => 'Template deleted successfully']);
    }
}
