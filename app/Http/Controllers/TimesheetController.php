<?php

namespace App\Http\Controllers;

use App\Models\ProjectCode;
use App\Models\Timesheet;
use App\Models\TimesheetAdminHour;
use App\Models\TimesheetDayMetadata;
use App\Models\TimesheetProjectHour;
use App\Models\TimesheetProjectRow;
use App\Services\TimesheetCalculationService;
use App\Services\TimesheetExcelExport;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use DateTime;
use Illuminate\Support\Facades\DB;

class TimesheetController extends Controller
{
    protected TimesheetCalculationService $calcService;

    public function __construct(TimesheetCalculationService $calcService)
    {
        $this->calcService = $calcService;
    }

    public function index(Request $request)
    {
        $timesheets = Timesheet::where('user_id', $request->user()->id)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->paginate(12);

        return view('timesheets.index', compact('timesheets'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year'  => 'required|integer|min:2020|max:2099',
        ]);

        $exists = Timesheet::where('user_id', $request->user()->id)
            ->where('month', $validated['month'])
            ->where('year', $validated['year'])
            ->exists();

        if ($exists) {
            return redirect()->route('timesheets.index')
                ->with('error', 'A timesheet for this month already exists.');
        }

        $timesheet = DB::transaction(function () use ($request, $validated) {
            $timesheet = Timesheet::create([
                'user_id' => $request->user()->id,
                'month'   => $validated['month'],
                'year'    => $validated['year'],
                'status'  => 'draft',
            ]);

            // Generate day metadata
            $days = $this->calcService->generateDayMetadata($validated['month'], $validated['year']);
            foreach ($days as $day) {
                TimesheetDayMetadata::create([
                    'timesheet_id'   => $timesheet->id,
                    'entry_date'     => $day['date'],
                    'day_of_week'    => $day['day_of_week'],
                    'day_type'       => $day['day_type'],
                    'available_hours' => $day['available_hours'],
                ]);
            }

            return $timesheet;
        });

        return redirect()->route('timesheets.edit', $timesheet);
    }

    public function edit(Request $request, Timesheet $timesheet)
    {
        if ($timesheet->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            abort(403);
        }

        $timesheet->load([
            'user.department',
            'dayMetadata',
            'adminHours',
            'projectRows.projectCode',
            'projectRows.hours',
            'approvalLogs.user',
        ]);

        $days = $this->calcService->generateDayMetadata($timesheet->month, $timesheet->year);
        $daysInMonth = count($days);

        // Merge DB metadata (e.g. MC/leave from Excel upload) into generated days
        foreach ($timesheet->dayMetadata as $meta) {
            $d = (int) $meta->entry_date->day;
            if (isset($days[$d])) {
                $days[$d]['day_type'] = $meta->day_type;
                $days[$d]['available_hours'] = (float) $meta->available_hours;
                $days[$d]['time_in'] = $meta->time_in;
                $days[$d]['time_out'] = $meta->time_out;
                $days[$d]['late_hours'] = (float) $meta->late_hours;
                $days[$d]['ot_eligible_hours'] = (float) $meta->ot_eligible_hours;
                $days[$d]['attendance_hours'] = (float) $meta->attendance_hours;
            }
        }

        // Build admin hours lookup: admin_type => day => hours
        $adminData = [];
        foreach (TimesheetCalculationService::ADMIN_TYPES as $type => $label) {
            $adminData[$type] = [];
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $adminData[$type][$d] = 0;
            }
        }
        foreach ($timesheet->adminHours as $ah) {
            $day = (int) $ah->entry_date->day;
            if (isset($adminData[$ah->admin_type])) {
                $adminData[$ah->admin_type][$day] = (float) $ah->hours;
            }
        }

        // Build project rows data
        $projectRowsData = [];
        foreach ($timesheet->projectRows->sortBy('row_order') as $row) {
            $hoursData = [];
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $hoursData[$d] = [
                    'normal_nc' => 0, 'normal_cobq' => 0,
                    'ot_nc' => 0, 'ot_cobq' => 0,
                ];
            }
            foreach ($row->hours as $h) {
                $day = (int) $h->entry_date->day;
                $hoursData[$day] = [
                    'normal_nc'   => (float) $h->normal_nc_hours,
                    'normal_cobq' => (float) $h->normal_cobq_hours,
                    'ot_nc'       => (float) $h->ot_nc_hours,
                    'ot_cobq'     => (float) $h->ot_cobq_hours,
                ];
            }
            $projectRowsData[] = [
                'id'             => $row->id,
                'project_code_id' => $row->project_code_id,
                'project_name'   => $row->project_name,
                'row_order'      => $row->row_order,
                'hours'          => $hoursData,
            ];
        }

        $projectCodes = ProjectCode::where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        $adminTypes = TimesheetCalculationService::ADMIN_TYPES;

        // Build approval stamps for timesheet
        $approvalStamps = $this->buildTimesheetApprovalStamps($timesheet);

        return view('timesheets.edit', compact(
            'timesheet', 'days', 'daysInMonth', 'adminData',
            'projectRowsData', 'projectCodes', 'adminTypes', 'approvalStamps'
        ));
    }

    /**
     * AJAX save endpoint — receives full matrix JSON.
     */
    public function save(Request $request, Timesheet $timesheet)
    {
        if ($timesheet->user_id !== $request->user()->id) {
            abort(403);
        }

        if (!in_array($timesheet->status, ['draft', 'rejected_hod', 'rejected_l1'])) {
            return response()->json(['error' => 'Timesheet cannot be edited in its current status.'], 422);
        }

        DB::transaction(function () use ($request, $timesheet) {
            // Save admin hours
            $adminHours = $request->input('admin_hours', []);
            foreach ($adminHours as $type => $dayValues) {
                foreach ($dayValues as $day => $hours) {
                    $hours = (float) $hours;
                    $entryDate = sprintf('%04d-%02d-%02d', $timesheet->year, $timesheet->month, $day);

                    if ($hours > 0) {
                        TimesheetAdminHour::updateOrCreate(
                            [
                                'timesheet_id' => $timesheet->id,
                                'admin_type'   => $type,
                                'entry_date'   => $entryDate,
                            ],
                            ['hours' => $hours]
                        );
                    } else {
                        TimesheetAdminHour::where('timesheet_id', $timesheet->id)
                            ->where('admin_type', $type)
                            ->where('entry_date', $entryDate)
                            ->delete();
                    }
                }
            }

            // Save project rows
            $projectRows = $request->input('project_rows', []);
            $existingRowIds = [];

            foreach ($projectRows as $idx => $rowData) {
                $rowId = $rowData['id'] ?? null;

                if ($rowId && $rowId !== 'new') {
                    $row = TimesheetProjectRow::where('id', $rowId)
                        ->where('timesheet_id', $timesheet->id)
                        ->first();
                    if ($row) {
                        $row->update([
                            'project_code_id' => $rowData['project_code_id'] ?: null,
                            'project_name'    => $rowData['project_name'] ?? '',
                            'row_order'       => $idx + 1,
                        ]);
                    }
                } else {
                    $row = TimesheetProjectRow::create([
                        'timesheet_id'    => $timesheet->id,
                        'project_code_id' => $rowData['project_code_id'] ?: null,
                        'project_name'    => $rowData['project_name'] ?? '',
                        'row_order'       => $idx + 1,
                    ]);
                }

                $existingRowIds[] = $row->id;

                // Save hours for this row
                $hours = $rowData['hours'] ?? [];
                foreach ($hours as $day => $vals) {
                    $entryDate = sprintf('%04d-%02d-%02d', $timesheet->year, $timesheet->month, $day);
                    $hasValues = ((float)($vals['normal_nc'] ?? 0)) > 0
                        || ((float)($vals['normal_cobq'] ?? 0)) > 0
                        || ((float)($vals['ot_nc'] ?? 0)) > 0
                        || ((float)($vals['ot_cobq'] ?? 0)) > 0;

                    if ($hasValues) {
                        TimesheetProjectHour::updateOrCreate(
                            [
                                'project_row_id' => $row->id,
                                'entry_date'     => $entryDate,
                            ],
                            [
                                'normal_nc_hours'   => (float)($vals['normal_nc'] ?? 0),
                                'normal_cobq_hours' => (float)($vals['normal_cobq'] ?? 0),
                                'ot_nc_hours'       => (float)($vals['ot_nc'] ?? 0),
                                'ot_cobq_hours'     => (float)($vals['ot_cobq'] ?? 0),
                            ]
                        );
                    } else {
                        TimesheetProjectHour::where('project_row_id', $row->id)
                            ->where('entry_date', $entryDate)
                            ->delete();
                    }
                }
            }

            // Remove deleted project rows
            TimesheetProjectRow::where('timesheet_id', $timesheet->id)
                ->whereNotIn('id', $existingRowIds)
                ->each(function ($row) {
                    $row->hours()->delete();
                    $row->delete();
                });
        });

        return response()->json(['success' => true, 'message' => 'Saved.']);
    }

    /**
     * Print-friendly timesheet view (landscape A4).
     */
    public function print(Request $request, Timesheet $timesheet)
    {
        if ($timesheet->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            abort(403);
        }

        $timesheet->load([
            'user.department',
            'dayMetadata',
            'adminHours',
            'projectRows.projectCode',
            'projectRows.hours',
        ]);

        $days = $this->calcService->generateDayMetadata($timesheet->month, $timesheet->year);
        $daysInMonth = count($days);

        // Merge DB metadata
        foreach ($timesheet->dayMetadata as $meta) {
            $d = (int) $meta->entry_date->day;
            if (isset($days[$d])) {
                $days[$d]['day_type'] = $meta->day_type;
                $days[$d]['available_hours'] = (float) $meta->available_hours;
            }
        }

        // Build admin hours lookup
        $adminData = [];
        foreach (TimesheetCalculationService::ADMIN_TYPES as $type => $label) {
            $adminData[$type] = [];
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $adminData[$type][$d] = 0;
            }
        }
        foreach ($timesheet->adminHours as $ah) {
            $day = (int) $ah->entry_date->day;
            if (isset($adminData[$ah->admin_type])) {
                $adminData[$ah->admin_type][$day] = (float) $ah->hours;
            }
        }

        // Build project rows data
        $projectRowsData = [];
        foreach ($timesheet->projectRows->sortBy('row_order') as $row) {
            $hoursData = [];
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $hoursData[$d] = [
                    'normal_nc' => 0, 'normal_cobq' => 0,
                    'ot_nc' => 0, 'ot_cobq' => 0,
                ];
            }
            foreach ($row->hours as $h) {
                $day = (int) $h->entry_date->day;
                $hoursData[$day] = [
                    'normal_nc'   => (float) $h->normal_nc_hours,
                    'normal_cobq' => (float) $h->normal_cobq_hours,
                    'ot_nc'       => (float) $h->ot_nc_hours,
                    'ot_cobq'     => (float) $h->ot_cobq_hours,
                ];
            }
            $projectRowsData[] = [
                'id'             => $row->id,
                'project_code_id' => $row->project_code_id,
                'project_name'   => $row->project_name,
                'project_code'   => $row->projectCode ? $row->projectCode->code : '',
                'row_order'      => $row->row_order,
                'hours'          => $hoursData,
            ];
        }

        // Pad to 5 project slots if fewer
        while (count($projectRowsData) < 5) {
            $hoursData = [];
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $hoursData[$d] = ['normal_nc' => 0, 'normal_cobq' => 0, 'ot_nc' => 0, 'ot_cobq' => 0];
            }
            $projectRowsData[] = [
                'id' => null, 'project_code_id' => null, 'project_name' => '',
                'project_code' => '', 'row_order' => count($projectRowsData) + 1,
                'hours' => $hoursData,
            ];
        }

        $adminTypes = TimesheetCalculationService::ADMIN_TYPES;

        return view('timesheets.print', compact(
            'timesheet', 'days', 'daysInMonth', 'adminData',
            'projectRowsData', 'adminTypes'
        ));
    }

    /**
     * Export timesheet as PDF matching physical form layout.
     */
    public function exportPdf(Request $request, Timesheet $timesheet)
    {
        $user = $request->user();

        // Allow: timesheet owner, admin, or users who can approve this timesheet
        $canDownload = $timesheet->user_id === $user->id
            || $user->isAdmin()
            || $user->canApproveTimesheetHOD()
            || $user->canApproveTimesheetL1();

        if (!$canDownload) {
            abort(403);
        }

        $month = DateTime::createFromFormat('!m', $timesheet->month)->format('F');
        $name = preg_replace('/\s+/', '_', $timesheet->user->name ?? 'Staff');
        $filename = "Timesheet_{$name}_{$month}_{$timesheet->year}.pdf";

        $pdf = Pdf::loadView('timesheets.pdf.export', compact('timesheet'))
            ->setPaper('a4', 'landscape')
            ->setOption('margin-top', 3)
            ->setOption('margin-bottom', 3)
            ->setOption('margin-left', 3)
            ->setOption('margin-right', 3);

        return $pdf->download($filename);
    }

    public function destroy(Request $request, Timesheet $timesheet)
    {
        $user = $request->user();

        // Owner can delete only draft timesheets
        if ($timesheet->user_id === $user->id) {
            if ($timesheet->status !== 'draft') {
                return redirect()->route('timesheets.index')
                    ->with('error', 'Only draft timesheets can be deleted by owner.');
            }
        } else {
            // Non-owners must be admin
            if (!$user->isAdmin()) {
                abort(403);
            }
            // Admin must provide reason for deleting non-draft
            if ($timesheet->status !== 'draft') {
                $request->validate([
                    'delete_reason' => 'required|string|max:500',
                ]);
            }
        }

        DB::transaction(function () use ($timesheet, $request, $user) {
            foreach ($timesheet->projectRows as $row) {
                $row->hours()->delete();
            }
            $timesheet->projectRows()->delete();
            $timesheet->adminHours()->delete();
            $timesheet->dayMetadata()->delete();
            $timesheet->approvalLogs()->delete();
            $timesheet->delete();

            // Log deletion if it's an approved/submitted form
            if ($timesheet->status !== 'draft') {
                // TODO: Add audit log entry here when audit feature is implemented
                // For now, we could use the existing approval log system or create a simple log
            }
        });

        // If admin was viewing a user's history, redirect back to that history
        if ($request->has('user_id') && $timesheet->user_id != $user->id) {
            return redirect()->route('history.index', ['user_id' => $timesheet->user_id])
                ->with('success', 'Timesheet deleted.');
        }

        return redirect()->route('timesheets.index')
            ->with('success', 'Timesheet deleted.');
    }

    /**
     * Build stamp data array for the approval-stamps component on timesheets.
     * Flow: Staff → HOD/EXEC/SPV (L1) → Asst. Mngr/Mngr (L2).
     */
    private function buildTimesheetApprovalStamps(Timesheet $timesheet): array
    {
        $approvalLogs = $timesheet->approvalLogs->sortBy('id');
        $stamps = [];

        // Stamp 1: Prepared By (Staff)
        $staffSubmitted = $timesheet->staff_signature || !in_array($timesheet->status, ['draft']);
        $stamps[] = [
            'label'  => 'Prepared By',
            'code'   => 'PRPD',
            'status' => $staffSubmitted && $timesheet->status !== 'draft' ? 'approved' : 'empty',
            'date'   => $timesheet->staff_signed_at ? $timesheet->staff_signed_at->format('m/d') : '',
            'name'   => $timesheet->staff_signature ?? ($timesheet->user->name ?? ''),
            'role'   => 'Staff',
        ];

        // Stamp 2: Checked By (HOD / EXEC / SPV — L1)
        $l1Status = 'empty';
        $l1Date = '';
        $l1Name = '';
        $l1Role = 'HOD';

        if ($timesheet->l1_signature) {
            $l1Status = 'approved';
            $l1Date = $timesheet->l1_signed_at ? $timesheet->l1_signed_at->format('m/d') : '';
            $l1Name = $timesheet->l1_signature;
            $l1Log = $approvalLogs->where('level', 1)->where('action', 'approved')->first();
            $l1Role = $l1Log && $l1Log->user ? ($l1Log->user->designation ?? 'HOD') : 'HOD';
        } elseif (in_array($timesheet->status, ['pending_hod', 'pending_l1'])) {
            $l1Status = 'pending';
        } elseif (in_array($timesheet->status, ['pending_l2', 'pending_l3', 'approved'])) {
            $l1Status = 'approved';
            $l1Log = $approvalLogs->where('level', 1)->where('action', 'approved')->first();
            if ($l1Log && $l1Log->user) {
                $l1Name = $l1Log->user->name;
                $l1Role = $l1Log->user->designation ?? 'HOD';
            }
        } elseif ($timesheet->status === 'rejected_l1') {
            $l1Status = 'rejected';
            $rejectLog = $approvalLogs->where('level', 1)->where('action', 'rejected')->sortByDesc('id')->first();
            if ($rejectLog && $rejectLog->user) {
                $l1Name = $rejectLog->user->name;
                $l1Role = $rejectLog->user->designation ?? 'HOD';
            }
        }

        $stamps[] = [
            'label'  => 'Checked By',
            'code'   => 'CHKD',
            'status' => $l1Status,
            'date'   => $l1Date,
            'name'   => $l1Name,
            'role'   => $l1Role,
        ];

        // Stamp 3: Verified By (Asst. Manager / Manager — L2)
        $l2Status = 'empty';
        $l2Date = '';
        $l2Name = '';
        $l2Role = 'Asst. Manager';

        if ($timesheet->l2_signature) {
            $l2Status = 'approved';
            $l2Date = $timesheet->l2_signed_at ? $timesheet->l2_signed_at->format('m/d') : '';
            $l2Name = $timesheet->l2_signature;
            $l2Log = $approvalLogs->where('level', 2)->where('action', 'approved')->first();
            $l2Role = $l2Log && $l2Log->user ? ($l2Log->user->designation ?? 'Manager') : 'Manager';
        } elseif ($timesheet->status === 'pending_l2') {
            $l2Status = 'pending';
        } elseif (in_array($timesheet->status, ['pending_l3', 'approved'])) {
            $l2Status = 'approved';
            $l2Log = $approvalLogs->where('level', 2)->where('action', 'approved')->first();
            if ($l2Log && $l2Log->user) {
                $l2Name = $l2Log->user->name;
                $l2Role = $l2Log->user->designation ?? 'Manager';
            }
        } elseif ($timesheet->status === 'rejected_l2') {
            $l2Status = 'rejected';
            $rejectLog = $approvalLogs->where('level', 2)->where('action', 'rejected')->sortByDesc('id')->first();
            if ($rejectLog && $rejectLog->user) {
                $l2Name = $rejectLog->user->name;
                $l2Role = $rejectLog->user->designation ?? 'Manager';
            }
        }

        $stamps[] = [
            'label'  => 'Verified By',
            'code'   => 'VRFD',
            'status' => $l2Status,
            'date'   => $l2Date,
            'name'   => $l2Name,
            'role'   => $l2Role,
        ];

        return $stamps;
    }

    public function previewExcel(Request $request, Timesheet $timesheet, TimesheetExcelExport $exporter)
    {
        $user = $request->user();

        // Allow: timesheet owner, admin, or users who can approve this timesheet
        $canPreview = $timesheet->user_id === $user->id
            || $user->isAdmin()
            || $user->canApproveTimesheetHOD()
            || $user->canApproveTimesheetL1();

        if (!$canPreview) {
            abort(403);
        }

        $spreadsheet = $exporter->generate($timesheet);
        $writer = new Xlsx($spreadsheet);

        $fileName = 'excel_preview_' . $timesheet->id . '_' . time() . '.xlsx';
        $tempDir = storage_path('app/public/temp');

        // Ensure temp directory exists
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $tempPath = $tempDir . '/' . $fileName;
        $writer->save($tempPath);

        // Determine backUrl based on user role
        $backUrl = $timesheet->user_id === $user->id
            ? route('timesheets.edit', $timesheet)
            : route('approvals.timesheets.show', $timesheet);

        return view('excel-preview', [
            'title' => 'Timesheet Excel Preview',
            'downloadUrl' => route('timesheets.export-excel', $timesheet),
            'backUrl' => $backUrl,
            'filePath' => $tempPath,
        ]);
    }

    public function exportExcel(Request $request, Timesheet $timesheet, TimesheetExcelExport $exporter)
    {
        $user = $request->user();

        // Allow: timesheet owner, admin, or users who can approve this timesheet
        $canDownload = $timesheet->user_id === $user->id
            || $user->isAdmin()
            || $user->canApproveTimesheetHOD()
            || $user->canApproveTimesheetL1();

        if (!$canDownload) {
            abort(403);
        }

        $spreadsheet = $exporter->generate($timesheet);
        $writer = new Xlsx($spreadsheet);

        $month = DateTime::createFromFormat('!m', $timesheet->month)->format('F');
        $name = preg_replace('/\s+/', '_', $timesheet->user->name ?? 'Staff');
        $filename = "Timesheet_{$name}_{$month}_{$timesheet->year}.xlsx";

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }

}
