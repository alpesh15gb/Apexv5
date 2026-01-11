<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\PunchImportService;
use Illuminate\Support\Facades\Log;

class SyncController extends Controller
{
    protected $punchService;
    protected $empService;

    public function __construct(PunchImportService $punchService, \App\Services\EmployeeImportService $empService)
    {
        $this->punchService = $punchService;
        $this->empService = $empService;
    }

    public function store(Request $request)
    {
        // 1. Simple Token Authentication
        $token = $request->bearerToken() ?? $request->input('token');
        $expected = env('SYNC_API_TOKEN', 'secret-token');

        if ($token !== $expected) {
            Log::warning('Unauthorized sync attempt. Received: ' . substr($token, 0, 5) . '... Expected: ' . substr($expected, 0, 5) . '...');
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

    public function storeEmployees(Request $request)
    {
        // 1. Simple Token Authentication (Duplicated for now, could be middleware)
        $token = $request->bearerToken() ?? $request->input('token');
        $expected = env('SYNC_API_TOKEN', 'secret-token');

        if ($token !== $expected) {
            Log::warning('Unauthorized employee sync attempt.');
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $employees = $request->input('employees');

        if (!is_array($employees) || empty($employees)) {
            return response()->json(['message' => 'No employees provided'], 400);
        }

        try {
            $stats = $this->empService->processBatch($employees);
            return response()->json(array_merge(['message' => 'Employee Sync successful'], $stats));
        } catch (\Exception $e) {
            Log::error('Employee Sync API Error: ' . $e->getMessage());
            return response()->json(['message' => 'Server Error: ' . $e->getMessage()], 500);
        }
    }
}
