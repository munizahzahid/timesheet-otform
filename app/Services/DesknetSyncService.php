<?php

namespace App\Services;

use App\Models\Department;
use App\Models\DesknetSyncLog;
use App\Models\Project;
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
        $this->projectCodesAppId = (int) (SystemConfig::getValue('desknet_project_codes_app_id') ?: config('services.desknet.project_codes_app_id', 12));
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

    /**
     * Fetch app details from Desknet AppSuite.
     */
    protected function fetchAppDetail(int $appId): ?array
    {
        $params = [
            'action' => 'get_app',
            'app_id' => $appId,
        ];

        $response = Http::timeout(30)
            ->asForm()
            ->withHeaders(['X-Desknets-Auth' => $this->accessKey])
            ->post($this->apiUrl, $params);

        if (!$response->successful()) {
            Log::error('Desknet app detail fetch failed', [
                'app_id' => $appId,
                'status' => $response->status(),
                'body' => Str::limit($response->body(), 500),
            ]);
            return null;
        }

        return $response->json();
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

        // Manager/HOD/SPV roles
        if (str_contains($designationLower, 'manager') ||
            str_contains($designationLower, 'hod') ||
            str_contains($designationLower, 'head of') ||
            str_contains($designationLower, 'supervisor') ||
            str_contains($designationLower, 'spv')) {
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

                $desknetId = $this->extractRecordId($record);
                $seenDesknetIds[] = $desknetId ?? $staffNo;
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

                $existingUser = User::where('desknet_id', $desknetId)
                    ->orWhere('staff_no', $staffNo)
                    ->first();

                if ($existingUser) {
                    // Preserve role and other manually set fields; only update basic info from Desknet
                    $updateData = [
                        'desknet_id' => $desknetId,
                        'staff_no' => $staffNo,
                        'name' => $name,
                        'department_id' => $departmentModel?->id,
                        'designation' => $designation,
                        'is_active' => $isActive,
                        'last_synced_at' => now(),
                    ];

                    $existingUser->update($updateData);
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
     * Sync project codes from Desknet (app_id: 12).
     * Also creates/updates project header records in the projects table.
     */
    public function syncProjectCodes(?int $triggeredBy = null, string $triggerType = 'manual'): DesknetSyncLog
    {
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

            $projectCreated = 0;
            $projectUpdated = 0;
            $projectDeactivated = 0;
            $seenDesknetIds = [];

            if (!empty($records)) {
                Log::info("Desknet project codes: first record keys", ['keys' => array_keys($records[0])]);
            }

            foreach ($records as $index => $record) {
                if ($index > 0 && $index % 50 === 0) {
                    Log::info("Desknet project codes: progress", ['processed' => $index, 'total' => count($records)]);
                }

                $desknetId = $this->extractRecordId($record) ?? null;
                $code = $this->extractField($record, ['PROJECT CODE', 'pc']);

                if (!$code) {
                    continue;
                }

                if ($desknetId) {
                    $seenDesknetIds[] = $desknetId;
                }

                // Fetch full detail to get all fields
                $detail = null;
                if ($desknetId) {
                    $detail = $this->fetchRecordDetail($this->projectCodesAppId, $desknetId);
                }

                // Fall back to list data if detail fails
                $source = $detail ?: $record;
                $projectData = $this->buildProjectData($source, $desknetId, $code);

                // Primary: sync pm_projects table for all years
                $projectExisting = Project::where('desknet_id', $desknetId)
                    ->orWhere('project_code', $code)
                    ->first();

                if ($projectExisting) {
                    $projectExisting->update($projectData);
                    $projectUpdated++;
                } else {
                    Project::create($projectData);
                    $projectCreated++;
                }
            }

            // Deactivate projects not in Desknet anymore
            if (!empty($seenDesknetIds)) {
                $projectDeactivated = Project::whereNotNull('desknet_id')
                    ->whereNotIn('desknet_id', $seenDesknetIds)
                    ->where('is_active', true)
                    ->update(['is_active' => false, 'status' => 'inactive']);
            }

            $log->update([
                'status' => 'success',
                'records_created' => $projectCreated,
                'records_updated' => $projectUpdated,
                'records_deactivated' => $projectDeactivated,
                'completed_at' => now(),
                'metadata' => [
                    'projects_created' => $projectCreated,
                    'projects_updated' => $projectUpdated,
                    'projects_deactivated' => $projectDeactivated,
                ],
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
     * Build pm_projects data from a Desknet record.
     */
    protected function buildProjectData(array $record, ?string $desknetId, string $code): array
    {
        $poCustomer = $this->extractAttachmentUrls($this->extractRawField($record, 'attachment_po_cust'));
        $otherAttachments = $this->extractAttachmentUrls($this->extractRawField($record, 'other_attach'));

        return [
            'desknet_id' => $desknetId,
            'project_code' => $code,
            'project_name' => $this->extractField($record, ['PROJECT NAME', 'PROJECT NAME (PN)', 'pn', 'name']) ?? $code,
            'status' => 'active',
            'start_date_plan' => $this->parseDate($this->extractField($record, ['start_date'])),
            'end_date_plan' => $this->parseDate($this->extractField($record, ['delivery_date'])),
            'project_manager' => $this->extractField($record, ['pm']),
            'project_manager_staff_id' => $this->extractField($record, ['staff_id_pm']),
            'project_manager_department' => $this->extractField($record, ['dept_pm']),
            'deskman_1' => $this->extractField($record, ['pdm_1']),
            'deskman_1_staff_id' => $this->extractField($record, ['staff_id_dm_1']),
            'deskman_1_department' => $this->extractField($record, ['dept_dm_1']),
            'deskman_2' => $this->extractField($record, ['pdm_2']),
            'deskman_2_staff_id' => $this->extractField($record, ['staff_id_dm_2']),
            'deskman_2_department' => $this->extractField($record, ['dept_dm_2']),
            'po_no' => $this->extractField($record, ['po']),
            'client' => $this->extractField($record, ['client']),
            'attn' => $this->extractField($record, ['attn']),
            'full_address' => $this->extractField($record, ['full_address']),
            'tin' => $this->extractField($record, ['tin']),
            'identification_no' => $this->extractField($record, ['identification_no']),
            'contact_no' => $this->extractField($record, ['contact_no']),
            'email' => $this->extractField($record, ['email']),
            'exemption_cert_no' => $this->extractField($record, ['exemp_cert_no']),
            'term_1' => $this->extractField($record, ['term_1']),
            'term_2' => $this->extractField($record, ['term_2']),
            'term_3' => $this->extractField($record, ['term_3']),
            'term_4' => $this->extractField($record, ['term_4']),
            'term_5' => $this->extractField($record, ['term_5']),
            'project_value' => $this->parseDecimal($this->extractField($record, ['proj_value'])),
            'purchasing_budget_100' => $this->parseDecimal($this->extractField($record, ['pur_budget_100'])),
            'purchasing_budget_95' => $this->parseDecimal($this->extractField($record, ['pur_budget_95'])),
            'year' => $this->parseInt($this->extractField($record, ['year'])),
            'attachment_po_customer' => $poCustomer,
            'other_attachments' => $otherAttachments,
            'project_schedule_status' => $this->extractField($record, ['proj_schedule_status']),
            'date_time_added' => $this->parseDateTime($this->extractField($record, ['date_time_added'])),
            'added_by' => $this->extractField($record, ['added_by']),
            'date_time_updated' => $this->parseDateTime($this->extractField($record, ['date_time_updated'])),
            'updated_by' => $this->extractField($record, ['updated_by']),
            'last_synced_at' => now(),
        ];
    }

    /**
     * Build a Desknet payload from a local Project.
     */
    public function buildDesknetProjectPayload(Project $project): array
    {
        $payload = [
            '{{pc}}' => $project->project_code,
            '{{pn}}' => $project->project_name,
            '{{start_date}}' => $project->start_date_plan?->format('Y-m-d'),
            '{{delivery_date}}' => $project->end_date_plan?->format('Y-m-d'),
            '{{pm}}' => $project->project_manager,
            '{{staff_id_pm}}' => $project->project_manager_staff_id,
            '{{dept_pm}}' => $project->project_manager_department,
            '{{pdm_1}}' => $project->deskman_1,
            '{{staff_id_dm_1}}' => $project->deskman_1_staff_id,
            '{{dept_dm_1}}' => $project->deskman_1_department,
            '{{pdm_2}}' => $project->deskman_2,
            '{{staff_id_dm_2}}' => $project->deskman_2_staff_id,
            '{{dept_dm_2}}' => $project->deskman_2_department,
            '{{po}}' => $project->po_no,
            '{{client}}' => $project->client,
            '{{attn}}' => $project->attn,
            '{{full_address}}' => $project->full_address,
            '{{tin}}' => $project->tin,
            '{{identification_no}}' => $project->identification_no,
            '{{contact_no}}' => $project->contact_no,
            '{{email}}' => $project->email,
            '{{exemp_cert_no}}' => $project->exemption_cert_no,
            '{{term_1}}' => $project->term_1,
            '{{term_2}}' => $project->term_2,
            '{{term_3}}' => $project->term_3,
            '{{term_4}}' => $project->term_4,
            '{{term_5}}' => $project->term_5,
            '{{proj_value}}' => $project->project_value,
            '{{pur_budget_100}}' => $project->purchasing_budget_100,
            '{{pur_budget_95}}' => $project->purchasing_budget_95,
            '{{year}}' => $project->year,
            '{{proj_schedule_status}}' => $project->project_schedule_status,
        ];

        // Note: date_time_added, added_by, date_time_updated, updated_by are
        // Desknet-managed system fields and cannot be written via the external API.

        // Remove null/empty values so they don't overwrite existing Desknet data unexpectedly
        return array_filter($payload, function ($value) {
            return $value !== null && $value !== '';
        });
    }

    /**
     * Push a local Project to Desknet (create or update).
     * Only called when the user explicitly clicks Save / Push to Desknet.
     */
    public function pushProjectToDesknet(Project $project, ?int $triggeredBy = null): array
    {
        if (empty($this->apiUrl) || empty($this->accessKey)) {
            throw new \RuntimeException('Desknet API URL or Access Key is not configured.');
        }

        $payload = $this->buildDesknetProjectPayload($project);

        // Desknet uses insert_data for create and update_data for update
        $action = $project->desknet_id ? 'update_data' : 'insert_data';

        $params = [
            'action' => $action,
            'app_id' => $this->projectCodesAppId,
        ];

        if ($project->desknet_id) {
            $params['data_id'] = $project->desknet_id;

            // Fetch current record to get revision number for optimistic locking
            $existingRecord = $this->fetchRecordDetail($this->projectCodesAppId, $project->desknet_id);
            if ($existingRecord) {
                $revision = $this->extractRawField($existingRecord, 'revision');
                if ($revision) {
                    $payload['{{revision}}'] = (string) $revision;
                }

                Log::info("Desknet push project existing record", [
                    'project_id' => $project->id,
                    'desknet_id' => $project->desknet_id,
                    'revision' => $revision,
                    'pm_raw' => $this->extractRawField($existingRecord, 'pm'),
                    'staff_id_pm_raw' => $this->extractRawField($existingRecord, 'staff_id_pm'),
                    'dept_pm_raw' => $this->extractRawField($existingRecord, 'dept_pm'),
                ]);
            }
        }

        $params = array_merge($params, $payload);

        Log::info("Desknet push project", [
            'project_id' => $project->id,
            'desknet_id' => $project->desknet_id,
            'action' => $action,
            'field_count' => count($payload),
            'payload_keys' => array_keys($payload),
        ]);

        $response = Http::timeout(30)
            ->asForm()
            ->withHeaders(['X-Desknets-Auth' => $this->accessKey])
            ->post($this->apiUrl, $params);

        if (!$response->successful()) {
            Log::error("Desknet push project failed", [
                'project_id' => $project->id,
                'status' => $response->status(),
                'body' => Str::limit($response->body(), 1000),
            ]);
            throw new \RuntimeException("Desknet push failed: HTTP {$response->status()} — " . Str::limit($response->body(), 500));
        }

        $data = $response->json();
        $bodyStatus = $data['status'] ?? null;

        if ($bodyStatus !== null && $bodyStatus !== 'ok' && $bodyStatus !== 'OK' && $bodyStatus !== 'success') {
            Log::error("Desknet push project returned non-ok status", [
                'project_id' => $project->id,
                'status' => $bodyStatus,
                'body' => Str::limit($response->body(), 1000),
            ]);
            throw new \RuntimeException("Desknet push failed: status='{$bodyStatus}' — " . Str::limit($response->body(), 500));
        }

        // If creating, capture the new Desknet record id
        if (!$project->desknet_id) {
            $newId = $data['id'] ?? $data['ID'] ?? $data['data_id'] ?? $data['record']['id'] ?? null;
            if ($newId) {
                $project->update(['desknet_id' => (string) $newId]);
            }
        }

        $log = DesknetSyncLog::create([
            'sync_type' => 'project_codes',
            'trigger_type' => 'manual',
            'triggered_by' => $triggeredBy,
            'status' => 'success',
            'records_updated' => $action === 'update_data' ? 1 : 0,
            'records_created' => $action === 'insert_data' ? 1 : 0,
            'started_at' => now(),
            'completed_at' => now(),
            'metadata' => [
                'project_id' => $project->id,
                'desknet_id' => $project->desknet_id,
                'direction' => 'push',
                'action' => $action,
                'response' => $data,
            ],
        ]);

        return [
            'success' => true,
            'desknet_id' => $project->desknet_id,
            'response' => $data,
            'log' => $log,
        ];
    }

    /**
     * Extract the raw field value (including nested arrays) from a Desknet record.
     */
    protected function extractRawField(array $record, string $key): mixed
    {
        if (!array_key_exists($key, $record)) {
            return null;
        }

        $val = $record[$key];

        if (is_array($val) && isset($val['val'])) {
            return $val['val'];
        }

        if (is_array($val) && isset($val['value'])) {
            return $val['value'];
        }

        return $val;
    }

    /**
     * Extract attachment URLs from a Desknet attachment field value.
     */
    protected function extractAttachmentUrls(mixed $fieldValue): ?array
    {
        if (!is_array($fieldValue)) {
            return null;
        }

        if (isset($fieldValue['attach']['item']) && is_array($fieldValue['attach']['item'])) {
            $items = $fieldValue['attach']['item'];
            if (isset($items['id'])) {
                $items = [$items];
            }

            return array_map(function ($item) {
                return [
                    'id' => $item['id'] ?? null,
                    'name' => $item['attachdisp'] ?? null,
                    'url' => $item['url'] ?? null,
                    'mimetype' => $item['mimetype'] ?? null,
                    'size' => $item['size'] ?? null,
                ];
            }, $items);
        }

        return null;
    }

    /**
     * Parse a string value to an integer.
     */
    protected function parseInt(?string $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (int) preg_replace('/[^0-9]/', '', $value);
    }

    /**
     * Parse a string value to a decimal.
     */
    protected function parseDecimal(?string $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (float) str_replace(',', '', $value);
    }

    /**
     * Parse a string value to a datetime.
     */
    protected function parseDateTime(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return \Carbon\Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Fetch and log a single Desknet record detail for field mapping.
     */
    public function diagnoseRecordDetail(int $appId, string $dataId): ?array
    {
        $detail = $this->fetchRecordDetail($appId, $dataId);

        if ($detail === null) {
            return null;
        }

        Log::info("Desknet diagnose detail app_id={$appId} data_id={$dataId}", [
            'keys' => array_keys($detail),
            'detail' => $detail,
        ]);

        return $detail;
    }

    /**
     * Diagnose a Desknet app by fetching a few records and logging the raw structure.
     */
    public function diagnoseApp(int $appId, int $limit = 3, ?int $viewId = null): array
    {
        if (empty($this->apiUrl) || empty($this->accessKey)) {
            throw new \RuntimeException('Desknet API URL or Access Key is not configured.');
        }

        $params = [
            'action'  => 'list_data',
            'app_id'  => $appId,
            'rp'      => $limit,
        ];

        if ($viewId) {
            $params['view_id'] = $viewId;
        }

        Log::info("Desknet diagnose: fetching app_id={$appId}" . ($viewId ? " view_id={$viewId}" : ''));

        $response = Http::timeout(30)
            ->asForm()
            ->withHeaders(['X-Desknets-Auth' => $this->accessKey])
            ->post($this->apiUrl, $params);

        if (!$response->successful()) {
            throw new \RuntimeException("Desknet API error (app_id={$appId}): HTTP {$response->status()} — " . Str::limit($response->body(), 500));
        }

        $data = $response->json();
        $records = [];

        if (isset($data['list']['item']) && is_array($data['list']['item'])) {
            $records = $data['list']['item'];
        } elseif (isset($data['record']) && is_array($data['record'])) {
            $records = $data['record'];
        } elseif (isset($data['records']) && is_array($data['records'])) {
            $records = $data['records'];
        } elseif (is_array($data) && isset($data[0])) {
            $records = $data;
        }

        $diagnostics = [];
        foreach ($records as $index => $record) {
            // Flatten first level keys and sample values
            $sample = [];
            foreach ($record as $key => $value) {
                if (is_array($value)) {
                    $sample[$key] = $value;
                } else {
                    $sample[$key] = $value;
                }
            }
            $diagnostics[] = [
                'index' => $index,
                'record_keys' => array_keys($record),
                'sample' => $sample,
            ];

            Log::info("Desknet diagnose app_id={$appId} record #{$index}", [
                'keys' => array_keys($record),
                'sample' => $sample,
            ]);
        }

        return [
            'app_id' => $appId,
            'view_id' => $viewId,
            'record_count' => count($records),
            'records' => $diagnostics,
        ];
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
        // Desknet AppSuite returns data_id as either a scalar or nested under val/value.
        // The staff list app uses the localized field name "Data ID".
        $dataId = $this->extractRawField($record, 'data_id')
            ?? $this->extractRawField($record, 'Data ID')
            ?? $this->extractRawField($record, 'id')
            ?? $this->extractRawField($record, 'record_id')
            ?? $this->extractRawField($record, 'rid')
            ?? null;

        return $dataId !== null ? (string) $dataId : null;
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
