<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class AgentController extends Controller
{
    public function index(Request $request)
    {
        $orgId = $request->user()->organization_id;

        $agents = User::where('organization_id', $orgId)
            ->select('id', 'name', 'email', 'avatar_url')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $agents,
        ]);
    }
}
