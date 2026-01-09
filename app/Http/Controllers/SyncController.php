<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\PunchImportService;
use Illuminate\Support\Facades\Log;

class SyncController extends Controller
{
    protected $punchService;

    public function __construct(PunchImportService $punchService)
    {
        $this->punchService = $punchService;
    }

    public function store(Request $request)
    {
        // 1. Simple Token Authentication
        $token = $request->bearerToken() ?? $request->input('token');
        if ($token !== env('SYNC_API_TOKEN', 'secret-token')) {
            Log::warning('Unauthorized sync attempt.');
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $punches = $request->input('punches');

        if (!is_array($punches) || empty($punches)) {
            return response()->json(['message' => 'No punches provided'], 400);
        }

        try {
            $count = $this->punchService->processBatch($punches);
            return response()->json([
                'message' => 'Sync successful',
                'processed_count' => $count,
                'imported' => $count,
                'failed' => 0
            ]);
        } catch (\Exception $e) {
            Log::error('Sync API Error: ' . $e->getMessage());
            return response()->json(['message' => 'Server Error'], 500);
        }
    }
}
