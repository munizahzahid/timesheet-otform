<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemConfig;
use Illuminate\Http\Request;

class SystemConfigController extends Controller
{
    public function index()
    {
        $configs = SystemConfig::orderBy('key')->get();

        $groups = [
            'Working Hours' => ['working_start_time', 'ot_start_time', 'default_working_hours', 'friday_working_hours'],
            'Rounding Rules' => ['late_rounding_minutes', 'ot_rounding_hours', 'lunch_break_minutes'],
            'OT Policies' => ['ot_claim_limit', 'ot_pre_approval_deadline', 'ot_payroll_submit_day'],
            'Desknet Integration' => ['desknet_api_url', 'desknet_api_key', 'desknet_sync_cron', 'desknet_sync_enabled'],
        ];

        return view('admin.settings.index', compact('configs', 'groups'));
    }

    public function update(Request $request)
    {
        $configs = $request->input('config', []);

        foreach ($configs as $key => $value) {
            SystemConfig::where('key', $key)->update([
                'value' => $value,
                'updated_at' => now(),
            ]);
        }

        return redirect()->route('admin.settings.index')
            ->with('success', 'Settings updated successfully.');
    }
}
