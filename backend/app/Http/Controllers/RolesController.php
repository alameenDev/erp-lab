<?php

namespace App\Http\Controllers;

use App\Services\AccountAccess;
use Illuminate\Http\Request;

class RolesController extends Controller
{
    /** Account editors need role choices without gaining role-administration access. */
    public function index(Request $request, AccountAccess $access)
    {
        $actor = $request->user();
        abort_unless($actor->can('users create') || $actor->can('users edit') || $actor->can('roles view'), 403);
        $roles = $access->roles($actor)->with('permissions')->orderBy('name')->get()
            ->filter(fn ($role) => $access->canAssign($actor, $role))->values();
        return response()->json($roles);
    }
}
