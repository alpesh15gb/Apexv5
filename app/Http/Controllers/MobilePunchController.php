<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\DailyAttendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MobilePunchController extends Controller
{
    /**
     * Show Mobile Login Page
     */
    public function showLogin()
    {
        return view('mobile.login');
    }

    /**
     * Handle Mobile Login
     */
    public function login(Request $request)
    {
        $request->validate([
            'emp_code' => 'required',
            'password' => 'required',
        ]);

        $employee = Employee::where('device_emp_code', $request->emp_code)->first();

        // Temporary: If no password set, allow 1234
        if ($employee) {
            if (!$employee->password && $request->password === '1234') {
                // First time login success
                session(['mobile_emp_id' => $employee->id]);
                return redirect()->route('mobile.punch');
            }

            if ($employee->password && Hash::check($request->password, $employee->password)) {
                session(['mobile_emp_id' => $employee->id]);
                return redirect()->route('mobile.punch');
            }
        }

        return back()->with('error', 'Invalid Credentials');
    }

    /**
     * Show Punch Interface
     */
    public function showPunch()
    {
        if (!session('mobile_emp_id')) {
            return redirect()->route('mobile.login');
        }

        $employee = Employee::find(session('mobile_emp_id'));
        $today = Carbon::now('Asia/Kolkata')->format('Y-m-d');

        // Check today's status
        $attendance = DailyAttendance::where('employee_id', $employee->id)
            ->where('date', $today)
            ->first();

        return view('mobile.punch', compact('employee', 'attendance'));
    }

    /**
     * Handle Punch Submission
     */
    public function storePunch(Request $request)
    {
        $request->validate([
            'image' => 'required', // Base64
            'lat' => 'required',
            'long' => 'required',
            'type' => 'required|in:check_in,check_out' // Which button was pressed
        ]);

        $employee = Employee::find(session('mobile_emp_id'));
        $today = Carbon::now('Asia/Kolkata')->format('Y-m-d');
        $now = Carbon::now('Asia/Kolkata');

        // 1. Save Image
        $imageParts = explode(";base64,", $request->image);
        $imageTypeAux = explode("image/", $imageParts[0]);
        $imageType = $imageTypeAux[1];
        $imageBase64 = base64_decode($imageParts[1]);
        $fileName = 'punches/' . $today . '/' . $employee->id . '_' . time() . '.' . $imageType;

        Storage::disk('public')->put($fileName, $imageBase64);
        $imageUrl = 'storage/' . $fileName;

        // 2. Find or Create Attendance
        $attendance = DailyAttendance::firstOrCreate(
            ['employee_id' => $employee->id, 'date' => $today],
            ['status' => 'Absent'] // Default
        );

        if ($request->type === 'check_in') {
            if (!$attendance->in_time) {
                // Save as string to prevent UTC conversion (Wall Clock Time)
                $attendance->in_time = $now->format('Y-m-d H:i:s');
                $attendance->in_image = $imageUrl;
                $attendance->in_lat = $request->lat;
                $attendance->in_long = $request->long;
                $attendance->status = 'Present'; // Update status
                $attendance->save();
                return response()->json(['success' => true, 'message' => 'Punch In Successful']);
            }
        } else {
            // Check Out
            $attendance->out_time = $now->format('Y-m-d H:i:s');
            $attendance->out_image = $imageUrl;
            $attendance->out_lat = $request->lat;
            $attendance->out_long = $request->long;
            // Calculate duration logic here if needed, or rely on existing services
            $attendance->save();
            return response()->json(['success' => true, 'message' => 'Punch Out Successful']);
        }

        return response()->json(['success' => false, 'message' => 'Punch already exists or invalid state']);
    }

    public function logout()
    {
        session()->forget('mobile_emp_id');
        return redirect()->route('mobile.login');
    }
}
