<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SystemConfigSeeder extends Seeder
{
    public function run(): void
    {
        $configs = [
            ['key' => 'working_start_time', 'value' => '08:00', 'description' => 'Daily work start time'],
            ['key' => 'ot_start_time', 'value' => '17:30', 'description' => 'OT eligibility starts after this time'],
            ['key' => 'default_working_hours', 'value' => '8', 'description' => 'Normal working hours Mon-Thu'],
            ['key' => 'friday_working_hours', 'value' => '7', 'description' => 'Working hours on Friday'],
            ['key' => 'late_rounding_minutes', 'value' => '30', 'description' => 'Late time rounded in blocks of N minutes'],
            ['key' => 'ot_rounding_hours', 'value' => '1', 'description' => 'OT rounded to whole hours for timesheet'],
            ['key' => 'lunch_break_minutes', 'value' => '60', 'description' => 'Lunch break duration in minutes'],
            ['key' => 'ot_claim_limit', 'value' => '500.00', 'description' => 'Maximum OT claim per month (RM)'],
            ['key' => 'ot_pre_approval_deadline', 'value' => '16:30', 'description' => 'OT pre-approval must be submitted before this time'],
            ['key' => 'ot_payroll_submit_day', 'value' => '5', 'description' => 'Day of month to submit OT claims to payroll'],
            ['key' => 'desknet_api_url', 'value' => config('services.desknet.api_url', ''), 'description' => 'Desknet NEO AppSuite API URL'],
            ['key' => 'desknet_api_key', 'value' => '', 'description' => 'Desknet API access key (set via .env)'],
            ['key' => 'desknet_sync_cron', 'value' => '0 1 * * *', 'description' => 'Cron schedule for Desknet sync (default: daily 1AM)'],
            ['key' => 'desknet_sync_enabled', 'value' => '1', 'description' => 'Enable/disable automatic Desknet sync'],
        ];

        foreach ($configs as $config) {
            DB::table('system_config')->updateOrInsert(
                ['key' => $config['key']],
                $config
            );
        }
    }
}
