<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use Illuminate\Http\JsonResponse;

class AgentController extends Controller
{
    //

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Agent::all()
        ],200);
    }
}
