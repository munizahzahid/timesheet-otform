<?php

namespace App\Services;

use App\Models\Department;
use App\Models\DesknetSyncLog;
use App\Models\ProjectCode;
use App\Models\SystemConfig;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DesknetSyncService
{
    protected string $apiUrl;
    protected string $accessKey;
    protected int $projectCodesAppId;
    protected int $staffListAppId;

    public function __construct()
    {
        // Read from system_config DB table first, fall back to config/services.php
        $this->apiUrl = SystemConfig::getValue('desknet_api_url') ?: config('services.desknet.api_url', '');
        $this->accessKey = SystemConfig::getValue('desknet_api_key') ?: config('services.desknet.access_key', '');
        $this->projectCodesAppId = (int) (SystemConfig::getValue('desknet_project_codes_app_id') ?: config('services.desknet.project_codes_app_id', 308));
        $this->staffListAppId = (int) (SystemConfig::getValue('desknet_staff_list_app_id') ?: config('services.desknet.staff_list_app_id', 29));
    }

    /**
     * Test connection to Desknet API and return diagnostic info.
     * Uses X-Desknets-Auth header (header-based auth) and action=list_data parameter.
     */
    public function testConnection(): array
    {
        if (empty($this->apiUrl)) {
            return ['success' => false, 'error' => 'Desknet API URL is empty. Set it in System Settings.'];
        }
        if (empty($this->accessKey)) {
            return ['success' => false, 'error' => 'Desknet Access Key is empty. Set it in System Settings.'];
        }

        $params = [
            'action'  => 'list_data',
            'app_id'  => $this->projectCodesAppId,
            'rp'      => 5,
        ];

        // Try POST with X-Desknets-Auth header
        try {
            $response = Http::timeout(15)
                ->asForm()
                ->withHeaders(['X-Desknets-Auth' => $this->accessKey])
                ->post($this->apiUrl, $params);

            $result = [
                'status' => $response->status(),
                'ok' => $response->successful(),
                'body_preview' => Str::limit($response->body(), 500),
            ];

            if ($response->successful()) {
                return ['success' => true, 'method' => 'POST (header auth)', 'details' => $result];
            }

            // Build error message
            $error = "Desknet API error: HTTP {$response->status()}";
            if ($response->status() === 403) {
                $error .= " — Invalid access key or Desknet External Connection is disabled. "
                       . "Verify the X-Desknets-Auth key and check: System Admin → AppSuite → External Connection Settings.";
            } else {
                $error .= " - " . Str::limit($response->body(), 500);
            }

            return [
                'success' => false,
                'error' => $error,
                'api_url' => $this->apiUrl,
                'key_preview' => Str::limit($this->accessKey, 10) . '...',
                'details' => $result,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => 'Connection failed: ' . $e->getMessage(),
                'api_url' => $this->apiUrl,
            ];
        }
    }

    /**
     * Fetch records from a Desknet AppSuite database.
     * Uses X-Desknets-Auth header (header-based auth) and action=list_data parameter.
     */
    protected function fetchRecordDetail(int $appId, string $dataId): ?array
    {
        $params = [
            'action'  => 'get_data',
            'app_id'  => $appId,
            'data_id' => $dataId,
        ];

        Log::info("Desknet API: fetching record detail app_id={$appId} data_id={$dataId}");

        $response = Http::timeout(30)
            ->asForm()
            ->withHeaders(['X-Desknets-Auth' => $this->accessKey])
            ->post($this->apiUrl, $params);

        if (!$response->successful()) {
            Log::error("Desknet API failed to fetch record detail", [
                'app_id' => $appId,
                'data_id' => $dataId,
                'status' => $response->status(),
            ]);
            return null;
        }

        $data = $response->json();

        // Desknet returns: { status: 'ok', record: {...} }
        if (isset($data['record'])) {
            return $data['record'];
        }

        return null;
    }

    protected function fetchAppData(int $appId): array
    {
        $params = [
            'action'  => 'list_data',
            'app_id'  => $appId,
            'rp'      => 1000,
        ];

        Log::info("Desknet API: fetching app_id={$appId} from {$this->apiUrl}");

        // Use POST with X-Desknets-Auth header (header-based auth)
        $response = Http::timeout(30)
            ->asForm()
            ->withHeaders(['X-Desknets-Auth' => $this->accessKey])
            ->post($this->apiUrl, $params);

        if (!$response->successful()) {
            Log::error("Desknet API failed", [
                'app_id' => $appId,
                'status' => $response->status(),
                'body' => Str::limit($response->body(), 1000),
                'url' => $this->apiUrl,
            ]);

            $msg = "Desknet API error (app_id={$appId}): HTTP {$response->status()}";
            if ($response->status() === 403 && empty(trim($response->body()))) {
                $msg .= " — The AppSuite External Connection API appears to be disabled or IP-restricted on the Desknet server. "
                       . "Ask your Desknet administrator to enable it in: System Admin → AppSuite → External Connection Settings.";
            } else {
                $msg .= " - " . Str::limit($response->body(), 500);
            }
            throw new \RuntimeException($msg);
        }

        $data = $response->json();

        Log::info("Desknet API: response for app_id={$appId}", [
            'status_field' => $data['status'] ?? 'n/a',
            'top_keys' => array_keys($data ?? []),
        ]);

        // Desknet AppSuite returns: { status: 'ok', list: { item: [...records...] } }
        if (isset($data['list']['item']) && is_array($data['list']['item'])) {
            Log::info("Desknet API: found " . count($data['list']['item']) . " records in list.item for app_id={$appId}");
            return $data['list']['item'];
        }

        // Fallback: try other known structures
        if (isset($data['record']) && is_array($data['record'])) {
            return $data['record'];
        }
        if (isset($data['records']) && is_array($data['records'])) {
            return $data['records'];
        }
        if (is_array($data) && isset($data[0])) {
            return $data;
        }

        Log::warning("Desknet API: no records found in response for app_id={$appId}", [
            'top_keys' => array_keys($data ?? []),
        ]);
        return [];
    }

    /**
     * Map designation to role.
     */
    protected function mapDesignationToRole(?string $designation): string
    {
        if (!$designation) {
            return 'staff';
        }

        $designationLower = strtolower($designation);

        // CEO/DGM/GM roles
        if (str_contains($designationLower, 'ceo') ||
            str_contains($designationLower, 'chief executive') ||
            str_contains($designationLower, 'dgm') ||
            str_contains($designationLower, 'deputy general') ||
            str_contains($designationLower, 'general manager')) {
            return 'ceo';
        }

        // Manager/HOD/SPV/Exec roles
        if (str_contains($designationLower, 'manager') ||
            str_contains($designationLower, 'hod') ||
            str_contains($designationLower, 'head of') ||
            str_contains($designationLower, 'supervisor') ||
            str_contains($designationLower, 'spv') ||
            str_contains($designationLower, 'executive') && !str_contains($designationLower, 'senior')) {
            return 'manager_hod';
        }

        // Assistant Manager roles
        if (str_contains($designationLower, 'assistant') ||
            str_contains($designationLower, 'asst')) {
            return 'assistant_manager';
        }

        return 'staff';
    }

    /**
     * Determine the designation category for reporting hierarchy.
     */
    protected function getDesignationCategory(?string $designation): string
    {
        if (!$designation) {
            return 'staff';
        }

        $d = strtolower($designation);

        // General Manager / CEO / DGM - top level
        if (str_contains($d, 'general manager') ||
            str_contains($d, 'ceo') ||
            str_contains($d, 'chief executive') ||
            str_contains($d, 'dgm') ||
            str_contains($d, 'deputy general')) {
            return 'gm';
        }

        // Manager / HOD / SPV / Exec
        if (str_contains($d, 'manager') ||
            str_contains($d, 'hod') ||
            str_contains($d, 'head of') ||
            str_contains($d, 'supervisor') ||
            str_contains($d, 'spv')) {
            return 'manager';
        }

        // Assistant Manager
        if (str_contains($d, 'assistant') || str_contains($d, 'asst')) {
            return 'asst_manager';
        }

        // All other staff (executive, machinist, senior executive, technician, etc.)
        return 'staff';
    }

    /**
     * Auto-assign reports_to based on designation hierarchy and department.
     * - Staff level (executive, machinist, senior exec, technician) → Asst Manager/Manager in same dept
     * - Asst Manager/Manager → General Manager (any dept)
     * - GM/CEO → null (top level)
     */
    protected function autoAssignReportsTo(?string $designation, ?int $departmentId): ?int
    {
        $category = $this->getDesignationCategory($designation);

        if ($category === 'gm') {
            return null; // Top level, no one to report to
        }

        if ($category === 'staff') {
            // Report to Asst Manager or Manager in same department
            $supervisor = User::where('is_active', true)
                ->where('department_id', $departmentId)
                ->where(function ($q) {
                    $q->where('designation', 'like', '%Manager%')
                      ->orWhere('designation', 'like', '%Asst%')
                      ->orWhere('designation', 'like', '%Assistant%');
                })
                ->whereNotNull('designation')
                ->orderByRaw("CASE
                    WHEN LOWER(designation) LIKE '%assistant%' OR LOWER(designation) LIKE '%asst%' THEN 1
                    WHEN LOWER(designation) LIKE '%manager%' THEN 2
                    ELSE 3 END")
                ->first();
            return $supervisor?->id;
        }

        if ($category === 'asst_manager' || $category === 'manager') {
            // Report to General Manager
            $gm = User::where('is_active', true)
                ->where(function ($q) {
                    $q->where('designation', 'like', '%General Manager%')
                      ->orWhere('designation', 'like', '%GM%')
                      ->orWhere('designation', 'like', '%DGM%')
                      ->orWhere('designation', 'like', '%CEO%');
                })
                ->whereNotNull('designation')
                ->first();
            return $gm?->id;
        }

        return null;
    }

    /**
     * Sync staff list from Desknet (app_id: 29).
     * Also extracts unique departments.
     */
    public function syncStaff(?int $triggeredBy = null, string $triggerType = 'manual'): DesknetSyncLog
    {
        $log = DesknetSyncLog::create([
            'sync_type' => 'staff',
            'trigger_type' => $triggerType,
            'triggered_by' => $triggeredBy,
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $records = $this->fetchAppData($this->staffListAppId);

            $created = 0;
            $updated = 0;
            $deactivated = 0;
            $seenDesknetIds = [];
            $seenDepartments = [];

            // Log first record keys to help debug field mapping
            if (!empty($records)) {
                Log::info("Desknet staff: first record keys", ['keys' => array_keys($records[0])]);
            }

            foreach ($records as $record) {
                $staffNo = $this->extractField($record, ['SN', 'sn', 'Staff No', 'staff_no']);
                $name = $this->extractField($record, ['Name', 'name', 'NAME']);
                $department = $this->extractField($record, ['Department', 'department', 'DEPARTMENT']);
                $designation = $this->extractField($record, ['Designation', 'designation', 'DESIGNATION']);
                $status = $this->extractField($record, ['Status', 'status', 'STATUS']);

                if (!$staffNo || !$name) {
                    continue;
                }

                $desknetId = $this->extractRecordId($record) ?? $staffNo;
                $seenDesknetIds[] = $desknetId;
                $isActive = !$status || strtolower($status) === 'active';

                // Sync department
                if ($department && !in_array($department, $seenDepartments)) {
                    Department::updateOrCreate(
                        ['name' => $department],
                        ['is_active' => true, 'last_synced_at' => now()]
                    );
                    $seenDepartments[] = $department;
                }

                $departmentModel = $department ? Department::where('name', $department)->first() : null;

                // Generate email from staff_no if not available
                $email = strtolower($staffNo) . '@tssb.com';

                $existingUser = User::where('desknet_id', $desknetId)
                    ->orWhere('staff_no', $staffNo)
                    ->first();

                if ($existingUser) {
                    // Auto-assign role based on designation (only if not admin)
                    $role = $existingUser->role === 'admin' ? 'admin' : $this->mapDesignationToRole($designation);

                    // Always auto-assign reports_to based on designation hierarchy
                    $reportsTo = $this->autoAssignReportsTo($designation, $departmentModel?->id);

                    $existingUser->update([
                        'desknet_id' => $desknetId,
                        'staff_no' => $staffNo,
                        'name' => $name,
                        'department_id' => $departmentModel?->id,
                        'designation' => $designation,
                        'role' => $role,
                        'reports_to' => $reportsTo,
                        'is_active' => $isActive,
                        'last_synced_at' => now(),
                    ]);
                    $updated++;
                } else {
                    // Auto-assign role based on designation
                    $role = $this->mapDesignationToRole($designation);

                    // Auto-assign reports_to
                    $reportsTo = $this->autoAssignReportsTo($designation, $departmentModel?->id);

                    User::create([
                        'desknet_id' => $desknetId,
                        'staff_no' => $staffNo,
                        'name' => $name,
                        'email' => $email,
                        'password' => Hash::make(strtolower($staffNo)),
                        'role' => $role,
                        'department_id' => $departmentModel?->id,
                        'designation' => $designation,
                        'reports_to' => $reportsTo,
                        'is_active' => $isActive,
                        'last_synced_at' => now(),
                    ]);
                    $created++;
                }
            }

            // Deactivate users not in Desknet anymore (except admin accounts)
            if (!empty($seenDesknetIds)) {
                $deactivated = User::whereNotNull('desknet_id')
                    ->whereNotIn('desknet_id', $seenDesknetIds)
                    ->where('role', '!=', 'admin')
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
            }

            $log->update([
                'status' => 'success',
                'records_created' => $created,
                'records_updated' => $updated,
                'records_deactivated' => $deactivated,
                'completed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Desknet staff sync failed: ' . $e->getMessage());
            $log->update([
                'status' => 'failed',
                'error_message' => Str::limit($e->getMessage(), 1000),
                'completed_at' => now(),
            ]);
        }

        return $log;
    }

    /**
     * Sync project codes from Desknet (app_id: 308).
     */
    public function syncProjectCodes(?int $triggeredBy = null, string $triggerType = 'manual'): DesknetSyncLog
    {
        // Remove execution time limit since we're fetching individual records
        set_time_limit(0);

        $log = DesknetSyncLog::create([
            'sync_type' => 'project_codes',
            'triggered_by' => $triggeredBy,
            'trigger_type' => $triggerType,
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $records = $this->fetchAppData($this->projectCodesAppId);

            $created = 0;
            $updated = 0;
            $deactivated = 0;
            $seenDesknetIds = [];

            // Log first record keys to help debug field mapping
            if (!empty($records)) {
                Log::info("Desknet project codes: first record keys", ['keys' => array_keys($records[0])]);
            }

            foreach ($records as $index => $record) {
                // Log progress every 50 records
                if ($index > 0 && $index % 50 === 0) {
                    Log::info("Desknet project codes: progress", ['processed' => $index, 'total' => count($records)]);
                }

                // Extract fields from record
                $code = $this->extractField($record, ['PROJECT CODE', 'pc']);
                $name = $this->extractField($record, ['PROJECT NAME', 'PROJECT NAME (PN)', 'pn', 'name']);
                $checklistStatus = $this->extractField($record, ['Checklist Status', 'checklist_status', 'status']);

                if (!$code) {
                    continue;
                }

                // Log extracted values for first few records
                static $logCount = 0;
                if ($logCount < 3) {
                    Log::info("Desknet project codes: extracted values", [
                        'code' => $code,
                        'name' => $name,
                        'checklist_status' => $checklistStatus,
                        'record_keys' => array_keys($record),
                    ]);
                    $logCount++;
                }

                $desknetId = $this->extractRecordId($record) ?? $code;
                $seenDesknetIds[] = $desknetId;

                $data = [
                    'desknet_id' => $desknetId,
                    'name' => $name,
                    'is_active' => true,
                    'last_synced_at' => now(),
                ];

                $existing = ProjectCode::where('desknet_id', $desknetId)
                    ->orWhere('code', $code)
                    ->first();

                if ($existing) {
                    $existing->update(array_merge($data, ['code' => $code]));
                    $updated++;
                } else {
                    ProjectCode::create(array_merge($data, ['code' => $code]));
                    $created++;
                }
            }

            // Deactivate project codes not in Desknet anymore
            if (!empty($seenDesknetIds)) {
                $deactivated = ProjectCode::whereNotNull('desknet_id')
                    ->whereNotIn('desknet_id', $seenDesknetIds)
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
            }

            $log->update([
                'status' => 'success',
                'records_created' => $created,
                'records_updated' => $updated,
                'records_deactivated' => $deactivated,
                'completed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Desknet project codes sync failed: ' . $e->getMessage());
            $log->update([
                'status' => 'failed',
                'error_message' => Str::limit($e->getMessage(), 1000),
                'completed_at' => now(),
            ]);
        }

        return $log;
    }

    /**
     * Run full sync: departments (via staff), staff, then project codes.
     */
    public function syncAll(?int $triggeredBy = null, string $triggerType = 'manual'): array
    {
        $staffLog = $this->syncStaff($triggeredBy, $triggerType);
        $projectLog = $this->syncProjectCodes($triggeredBy, $triggerType);

        return [
            'staff' => $staffLog,
            'project_codes' => $projectLog,
        ];
    }

    /**
     * Extract a field value from a Desknet record trying multiple possible key names.
     */
    protected function extractField(array $record, array $possibleKeys): ?string
    {
        foreach ($possibleKeys as $key) {
            if (!array_key_exists($key, $record)) {
                continue;
            }

            $val = $record[$key];

            // Nested structure: {key: {val: "..."}} (Desknet AppSuite standard)
            if (is_array($val) && isset($val['val'])) {
                $extracted = trim((string) $val['val']);
                return $extracted !== '' ? $extracted : null;
            }

            // Nested structure: {key: {value: "..."}}
            if (is_array($val) && isset($val['value'])) {
                $extracted = trim((string) $val['value']);
                return $extracted !== '' ? $extracted : null;
            }

            // Direct scalar value
            if (!is_array($val) && $val !== '' && $val !== null) {
                return trim((string) $val);
            }
        }

        // Try numeric-indexed columns
        if (isset($record['columns']) && is_array($record['columns'])) {
            foreach ($record['columns'] as $col) {
                $colName = $col['name'] ?? $col['title'] ?? '';
                if (in_array($colName, $possibleKeys)) {
                    if (isset($col['val'])) return trim((string) $col['val']);
                    if (isset($col['value'])) return trim((string) $col['value']);
                }
            }
        }

        return null;
    }

    /**
     * Extract the record ID from a Desknet record.
     */
    protected function extractRecordId(array $record): ?string
    {
        // Desknet AppSuite uses data_id with nested val
        if (isset($record['data_id']['val'])) {
            return (string) $record['data_id']['val'];
        }
        return $record['data_id'] ?? $record['id'] ?? $record['record_id'] ?? $record['rid'] ?? null;
    }

    /**
     * Parse a date string in various formats to Y-m-d.
     */
    protected function parseDate(?string $dateStr): ?string
    {
        if (!$dateStr) {
            return null;
        }

        // Try dd/mm/yyyy format (common in Malaysian locale)
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $dateStr, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        // Try Y-m-d
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
            return $dateStr;
        }

        // Try strtotime as fallback
        $ts = strtotime($dateStr);
        return $ts ? date('Y-m-d', $ts) : null;
    }
}
