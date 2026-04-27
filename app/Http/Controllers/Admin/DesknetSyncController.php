<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DesknetSyncLog;
use App\Services\DesknetSyncService;
use Illuminate\Http\Request;

class DesknetSyncController extends Controller
{
    public function index()
    {
        $logs = DesknetSyncLog::with('triggeredBy')
            ->orderBy('started_at', 'desc')
            ->paginate(20);

        $lastStaffSync = DesknetSyncLog::where('sync_type', 'staff')
            ->where('status', 'success')
            ->orderBy('completed_at', 'desc')
            ->first();

        $lastProjectSync = DesknetSyncLog::where('sync_type', 'project_codes')
            ->where('status', 'success')
            ->orderBy('completed_at', 'desc')
            ->first();

        return view('admin.desknet-sync.index', compact('logs', 'lastStaffSync', 'lastProjectSync'));
    }

    public function test(Request $request, DesknetSyncService $service)
    {
        $result = $service->testConnection();

        if ($result['success']) {
            return redirect()->route('admin.desknet-sync.index')
                ->with('success', "Connection OK via {$result['method']}. API responded successfully.");
        }

        $errorMsg = $result['error'];
        if (isset($result['details'])) {
            foreach ($result['details'] as $method => $detail) {
                if (isset($detail['status'])) {
                    $errorMsg .= " | {$method}: HTTP {$detail['status']}";
                    if (isset($detail['body_preview'])) {
                        $errorMsg .= " — " . \Illuminate\Support\Str::limit($detail['body_preview'], 200);
                    }
                } elseif (isset($detail['error'])) {
                    $errorMsg .= " | {$method}: {$detail['error']}";
                }
            }
        }

        return redirect()->route('admin.desknet-sync.index')
            ->with('error', $errorMsg);
    }

    public function run(Request $request, DesknetSyncService $service)
    {
        $type = $request->input('type', 'all');
        $userId = $request->user()->id;

        if ($type === 'staff') {
            $service->syncStaff($userId, 'manual');
        } elseif ($type === 'project_codes') {
            $service->syncProjectCodes($userId, 'manual');
        } else {
            $service->syncAll($userId, 'manual');
        }

        return redirect()->route('admin.desknet-sync.index')
            ->with('success', "Desknet sync ({$type}) completed. Check the log below for results.");
    }
}
