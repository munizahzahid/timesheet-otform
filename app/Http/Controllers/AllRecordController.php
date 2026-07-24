<?php

namespace App\Http\Controllers;

use App\Models\ApprovalLog;
use App\Models\OtForm;
use App\Models\Timesheet;
use App\Models\TimesheetApprovalLog;
use App\Models\User;
use App\Services\TimesheetCalculationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AllRecordController extends Controller
{
    public function timesheets(Request $request)
    {
        if (!Auth::user()->canViewAllRecords()) {
            abort(403);
        }

        $month = $request->input('month');
        $year = $request->input('year');

        $query = Timesheet::with('user')
            ->where('status', 'approved')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->orderByDesc('updated_at');

        if ($month) {
            $query->where('month', $month);
        }
        if ($year) {
            $query->where('year', $year);
        }

        $timesheets = $query->paginate(20)->withQueryString();

        $approvedAt = $this->getTimesheetApprovedDates($timesheets);

        return view('records.timesheets', compact('timesheets', 'approvedAt', 'month', 'year'));
    }

    public function timesheetSummary(Request $request)
    {
        if (!Auth::user()->canViewAllRecords()) {
            abort(403);
        }

        $month = $request->input('month', (int) date('n'));
        $year = $request->input('year', (int) date('Y'));
        $category = $request->input('category', 'all');

        // Build the list of staff (all or by category)
        $staffQuery = User::where('is_active', true)->orderBy('name');
        if ($category !== 'all') {
            $staffQuery->where('category', $category);
        }
        $staff = $staffQuery->get();

        // Load approved timesheets for the selected month/year across all staff
        // so the project code list is the same for every category. Hours/summary
        // are still scoped to the selected staff list inside buildSummaryData.
        $timesheets = Timesheet::with(['user', 'adminHours', 'projectRows.projectCode', 'projectRows.hours', 'dayMetadata'])
            ->where('status', 'approved')
            ->where('month', $month)
            ->where('year', $year)
            ->get();

        $data = $this->buildSummaryData($staff, $timesheets);

        return view('records.timesheet-summary', array_merge(
            $data,
            compact('staff', 'month', 'year', 'category')
        ));
    }

    public function exportSummaryExcel(Request $request, \App\Services\TimesheetSummaryExcelExport $exporter)
    {
        if (!Auth::user()->canViewAllRecords()) {
            abort(403);
        }

        $month = $request->input('month', (int) date('n'));
        $year = $request->input('year', (int) date('Y'));
        $category = $request->input('category', 'all');

        $staffQuery = User::where('is_active', true)->orderBy('name');
        if ($category !== 'all') {
            $staffQuery->where('category', $category);
        }
        $staff = $staffQuery->get();

        $timesheets = Timesheet::with(['user', 'adminHours', 'projectRows.projectCode', 'projectRows.hours', 'dayMetadata'])
            ->where('status', 'approved')
            ->where('month', $month)
            ->where('year', $year)
            ->get();

        $data = $this->buildSummaryData($staff, $timesheets);

        $spreadsheet = $exporter->generate($month, $year, $category, $staff, $data['adminTypes'], $data['adminHours'], $data['projects'], $data['summary']);
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        $monthName = \DateTime::createFromFormat('!m', $month)->format('F');
        $filename = "Timesheet_Summary_{$category}_{$monthName}_{$year}.xlsx";

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    public function exportSummaryPdf(Request $request)
    {
        if (!Auth::user()->canViewAllRecords()) {
            abort(403);
        }

        $month = $request->input('month', (int) date('n'));
        $year = $request->input('year', (int) date('Y'));
        $category = $request->input('category', 'all');

        $staffQuery = User::where('is_active', true)->orderBy('name');
        if ($category !== 'all') {
            $staffQuery->where('category', $category);
        }
        $staff = $staffQuery->get();

        $timesheets = Timesheet::with(['user', 'adminHours', 'projectRows.projectCode', 'projectRows.hours', 'dayMetadata'])
            ->where('status', 'approved')
            ->where('month', $month)
            ->where('year', $year)
            ->get();

        $data = $this->buildSummaryData($staff, $timesheets);

        $monthName = \DateTime::createFromFormat('!m', $month)->format('F');
        $filename = "Timesheet_Summary_{$category}_{$monthName}_{$year}.pdf";

        $pdf = \Pdf::loadView('records.timesheet-summary-pdf', array_merge(
            $data,
            ['month' => $month, 'year' => $year, 'category' => $category, 'staff' => $staff]
        ))
            ->setPaper('a4', 'landscape')
            ->setOption(['dpi' => 150, 'defaultFont' => 'Arial']);

        return $pdf->download($filename);
    }

    private function buildSummaryData($staff, $timesheets): array
    {
        $adminTypes = TimesheetCalculationService::ADMIN_TYPES;

        $adminHours = [];
        foreach ($adminTypes as $type => $label) {
            $adminHours[$type] = array_fill_keys($staff->pluck('id')->toArray(), 0);
        }

        $projects = [];
        $projectKey = fn($row) => $row->projectCode
            ? $row->projectCode->code . '|' . $row->projectCode->name
            : ($row->project_category ?? 'N/A') . '|' . ($row->manual_project_code_name ?? '');

        foreach ($timesheets as $timesheet) {
            foreach ($timesheet->adminHours as $ah) {
                if (!isset($adminHours[$ah->admin_type][$timesheet->user_id])) {
                    continue;
                }
                $adminHours[$ah->admin_type][$timesheet->user_id] += (float) $ah->hours;
            }

            foreach ($timesheet->projectRows as $row) {
                $key = $projectKey($row);
                if (!isset($projects[$key])) {
                    $projects[$key] = [
                        'code' => $row->projectCode ? $row->projectCode->code : ($row->project_category ?? 'N/A'),
                        'name' => $row->projectCode ? $row->projectCode->name : ($row->manual_project_code_name ?? ''),
                        'hours' => array_fill_keys($staff->pluck('id')->toArray(), [
                            'normal_nc' => 0, 'normal_cobq' => 0,
                            'ot_nc' => 0, 'ot_cobq' => 0,
                        ]),
                    ];
                }
                if (!isset($projects[$key]['hours'][$timesheet->user_id])) {
                    continue;
                }
                foreach ($row->hours as $h) {
                    $projects[$key]['hours'][$timesheet->user_id]['normal_nc'] += (float) $h->normal_nc_hours;
                    $projects[$key]['hours'][$timesheet->user_id]['normal_cobq'] += (float) $h->normal_cobq_hours;
                    $projects[$key]['hours'][$timesheet->user_id]['ot_nc'] += (float) $h->ot_nc_hours;
                    $projects[$key]['hours'][$timesheet->user_id]['ot_cobq'] += (float) $h->ot_cobq_hours;
                }
            }
        }

        uksort($projects, function ($a, $b) use ($projects) {
            return strcmp($projects[$a]['code'] . $projects[$a]['name'], $projects[$b]['code'] . $projects[$b]['name']);
        });

        $summary = [];
        foreach ($staff as $user) {
            $summary[$user->id] = [
                'total_external_project' => 0,
                'total_working_hours' => 0,
                'hours_available' => 0,
                'overtime' => 0,
            ];
        }

        foreach ($timesheets as $timesheet) {
            if (!isset($summary[$timesheet->user_id])) {
                continue;
            }

            $external = 0;
            foreach ($timesheet->projectRows as $row) {
                foreach ($row->hours as $h) {
                    $external += (float) $h->normal_nc_hours
                        + (float) $h->normal_cobq_hours
                        + (float) $h->ot_nc_hours
                        + (float) $h->ot_cobq_hours;
                }
            }

            $adminTotal = 0;
            foreach ($timesheet->adminHours as $ah) {
                $adminTotal += (float) $ah->hours;
            }

            $available = $timesheet->dayMetadata->sum('available_hours');
            $working = $adminTotal + $external;

            $summary[$timesheet->user_id]['total_external_project'] += $external;
            $summary[$timesheet->user_id]['total_working_hours'] += $working;
            $summary[$timesheet->user_id]['hours_available'] += $available;
            $summary[$timesheet->user_id]['overtime'] += $working - $available;
        }

        return compact('adminTypes', 'adminHours', 'projects', 'summary');
    }

    public function showTimesheet(Timesheet $timesheet)
    {
        if (!Auth::user()->canViewAllRecords()) {
            abort(403);
        }

        if ($timesheet->status !== 'approved') {
            abort(404);
        }

        $timesheet->load([
            'user.department',
            'user.timesheetHodApprover',
            'user.timesheetApprover',
            'dayMetadata',
            'adminHours',
            'projectRows.projectCode',
            'projectRows.hours',
            'approvalLogs.user',
        ]);

        $calcService = new TimesheetCalculationService();
        $days = $calcService->generateDayMetadata($timesheet->month, $timesheet->year);
        $daysInMonth = count($days);

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

        $adminTypes = TimesheetCalculationService::ADMIN_TYPES;
        $adminHours = [];
        foreach ($adminTypes as $type => $label) {
            $adminHours[$type] = [];
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $adminHours[$type][$d] = 0;
            }
        }
        foreach ($timesheet->adminHours as $ah) {
            $day = (int) $ah->entry_date->day;
            if (isset($adminHours[$ah->admin_type])) {
                $adminHours[$ah->admin_type][$day] = (float) $ah->hours;
            }
        }

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
                if (isset($hoursData[$day])) {
                    $hoursData[$day] = [
                        'normal_nc' => (float) $h->normal_nc_hours,
                        'normal_cobq' => (float) $h->normal_cobq_hours,
                        'ot_nc' => (float) $h->ot_nc_hours,
                        'ot_cobq' => (float) $h->ot_cobq_hours,
                    ];
                }
            }

            $displayCode = $row->projectCode ? $row->projectCode->code : '';
            $displayName = $row->projectCode ? $row->projectCode->name : '';
            if ($row->project_category) {
                $displayCode = $row->project_category;
                $displayName = $row->manual_project_code_name ?? '';
            }

            $projectRowsData[] = [
                'id' => $row->id,
                'project_code_id' => $row->project_code_id,
                'project_code' => $displayCode,
                'project_name' => $displayName,
                'hours' => $hoursData,
            ];
        }

        $flatProjectRows = [];
        $subRowTypes = [
            ['field' => 'normal_nc', 'label' => 'Normal NC'],
            ['field' => 'normal_cobq', 'label' => 'Normal COBQ'],
            ['field' => 'ot_nc', 'label' => 'OT NC'],
            ['field' => 'ot_cobq', 'label' => 'OT COBQ'],
        ];
        foreach ($projectRowsData as $pIdx => $project) {
            foreach ($subRowTypes as $sIdx => $subRow) {
                $flatProjectRows[] = [
                    'pIdx' => $pIdx,
                    'sIdx' => $sIdx,
                    'key' => "{$pIdx}-{$sIdx}",
                    'field' => $subRow['field'],
                    'label' => $subRow['label'],
                    'project_code' => $project['project_code'],
                    'project_name' => $project['project_name'],
                ];
            }
        }

        $approvalLogs = $timesheet->approvalLogs ? $timesheet->approvalLogs->sortBy('id') : collect();
        $approvalStamps = $this->buildTimesheetApprovalStamps($timesheet, $approvalLogs);

        // Get approved OT form total hours for this user/month
        $approvedOtForm = \App\Models\OtForm::where('user_id', $timesheet->user_id)
            ->where('month', $timesheet->month)
            ->where('year', $timesheet->year)
            ->whereIn('status', ['pending_gm', 'approved'])
            ->first();
        $otApprovedByHr = $approvedOtForm ? floor($approvedOtForm->total_ot_hours * 4) / 4 : null;

        return response()->view('records.timesheet-show', compact(
            'timesheet', 'approvalStamps', 'daysInMonth', 'days', 'adminTypes', 'adminHours', 'projectRowsData', 'flatProjectRows', 'otApprovedByHr'
        ));
    }

    public function otForms(Request $request)
    {
        if (!Auth::user()->canViewAllRecords()) {
            abort(403);
        }

        $month = $request->input('month');
        $year = $request->input('year');

        $query = OtForm::with('user')
            ->where('status', 'approved')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->orderByDesc('updated_at');

        if ($month) {
            $query->where('month', $month);
        }
        if ($year) {
            $query->where('year', $year);
        }

        $otForms = $query->paginate(20)->withQueryString();

        $approvedAt = $this->getOtFormApprovedDates($otForms);

        return view('records.ot-forms', compact('otForms', 'approvedAt', 'month', 'year'));
    }

    public function showOtForm(OtForm $otForm)
    {
        if (!Auth::user()->canViewAllRecords()) {
            abort(403);
        }

        if ($otForm->status !== 'approved') {
            abort(404);
        }

        $otForm->load(['entries' => function ($q) {
            $q->orderBy('entry_date')->orderBy('id');
        }, 'entries.projectCode', 'user.department', 'hrEditor']);

        $approvalLogs = $otForm->approvalLogs()->get();
        $approvalStamps = $this->buildOtApprovalStamps($otForm, $approvalLogs);

        $staffApproverName = $otForm->user->short_name ?? $otForm->user->name ?? '';
        $managerLog = $approvalLogs->where('level', 2)->where('action', 'approved')->first();
        $gmLog = $approvalLogs->where('level', 1)->where('action', 'approved')->first();

        $managerApproverName = '';
        $managerApproverDesignation = '';
        $managerApprovedDate = '';
        if ($managerLog && $managerLog->approver) {
            $managerApproverName = $managerLog->approver->short_name ?? $managerLog->approver->name;
            $managerApproverDesignation = $managerLog->approver->designation ?? 'Manager';
            $managerApprovedDate = $managerLog->acted_at ? $managerLog->acted_at->format('d/m/Y') : '';
        } elseif ($otForm->user->ot_approver_id) {
            $mgrUser = \App\Models\User::find($otForm->user->ot_approver_id);
            $managerApproverName = $mgrUser->short_name ?? $mgrUser->name ?? '';
            $managerApproverDesignation = $mgrUser->designation ?? 'Manager';
        } elseif ($otForm->isExecutive() && $otForm->user->ot_exec_approver_id) {
            $mgrUser = \App\Models\User::find($otForm->user->ot_exec_approver_id);
            $managerApproverName = $mgrUser->short_name ?? $mgrUser->name ?? '';
            $managerApproverDesignation = $mgrUser->designation ?? 'Manager';
        } elseif (!$otForm->isExecutive() && $otForm->user->ot_non_exec_approver_id) {
            $mgrUser = \App\Models\User::find($otForm->user->ot_non_exec_approver_id);
            $managerApproverName = $mgrUser->short_name ?? $mgrUser->name ?? '';
            $managerApproverDesignation = $mgrUser->designation ?? 'Manager';
        }

        $gmApproverName = '';
        $gmApproverDesignation = '';
        $gmApprovedDate = '';
        $gmIsApproved = false;
        if ($gmLog && $gmLog->approver) {
            $gmApproverName = $gmLog->approver->short_name ?? $gmLog->approver->name;
            $gmApproverDesignation = $gmLog->approver->designation ?? 'CEO';
            $gmApprovedDate = $gmLog->acted_at ? $gmLog->acted_at->format('d/m/Y') : '';
            $gmIsApproved = true;
        } elseif ($otForm->user->ot_final_approver_id) {
            $gmUser = \App\Models\User::find($otForm->user->ot_final_approver_id);
            $gmApproverName = $gmUser->short_name ?? $gmUser->name ?? '';
            $gmApproverDesignation = $gmUser->designation ?? 'CEO';
            $gmIsApproved = true;
        } elseif ($otForm->isExecutive() && $otForm->user->ot_exec_final_approver_id) {
            $gmUser = \App\Models\User::find($otForm->user->ot_exec_final_approver_id);
            $gmApproverName = $gmUser->short_name ?? $gmUser->name ?? '';
            $gmApproverDesignation = $gmUser->designation ?? 'CEO';
            $gmIsApproved = true;
        } elseif (!$otForm->isExecutive() && $otForm->user->ot_non_exec_final_approver_id) {
            $gmUser = \App\Models\User::find($otForm->user->ot_non_exec_final_approver_id);
            $gmApproverName = $gmUser->short_name ?? $gmUser->name ?? '';
            $gmApproverDesignation = $gmUser->designation ?? 'CEO';
            $gmIsApproved = true;
        }

        $projectCodes = \App\Models\Project::where('is_active', true)
            ->orderBy('project_code')
            ->get(['id', 'project_code', 'project_name']);

        return view('records.ot-form-show', compact(
            'otForm', 'approvalStamps', 'projectCodes', 'staffApproverName',
            'managerApproverName', 'managerApproverDesignation', 'managerApprovedDate',
            'gmApproverName', 'gmApproverDesignation', 'gmApprovedDate', 'gmIsApproved'
        ));
    }

    public function otSummary(Request $request)
    {
        if (!Auth::user()->canViewAllRecords()) {
            abort(403);
        }

        $month = $request->input('month', (int) date('n'));
        $year = $request->input('year', (int) date('Y'));
        $category = $request->input('category', 'all');

        $staffQuery = User::where('is_active', true)->orderBy('name');
        if ($category !== 'all') {
            $staffQuery->where('category', $category);
        }
        $staff = $staffQuery->get();

        $otForms = OtForm::with(['user', 'entries.projectCode'])
            ->where('status', 'approved')
            ->where('month', $month)
            ->where('year', $year)
            ->get();

        $data = $this->buildOtSummaryData($staff, $otForms);

        return view('records.ot-summary', array_merge(
            $data,
            compact('staff', 'month', 'year', 'category')
        ));
    }

    public function exportOtSummaryExcel(Request $request, \App\Services\OtSummaryExcelExport $exporter)
    {
        if (!Auth::user()->canViewAllRecords()) {
            abort(403);
        }

        $month = $request->input('month', (int) date('n'));
        $year = $request->input('year', (int) date('Y'));
        $category = $request->input('category', 'all');

        $staffQuery = User::where('is_active', true)->orderBy('name');
        if ($category !== 'all') {
            $staffQuery->where('category', $category);
        }
        $staff = $staffQuery->get();

        $otForms = OtForm::with(['user', 'entries.projectCode'])
            ->where('status', 'approved')
            ->where('month', $month)
            ->where('year', $year)
            ->get();

        $data = $this->buildOtSummaryData($staff, $otForms);

        $spreadsheet = $exporter->generate($month, $year, $category, $staff, $data['projects'], $data['totals']);
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        $monthName = \DateTime::createFromFormat('!m', $month)->format('F');
        $filename = "OT_Summary_{$category}_{$monthName}_{$year}.xlsx";

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    public function exportOtSummaryPdf(Request $request)
    {
        if (!Auth::user()->canViewAllRecords()) {
            abort(403);
        }

        $month = $request->input('month', (int) date('n'));
        $year = $request->input('year', (int) date('Y'));
        $category = $request->input('category', 'all');

        $staffQuery = User::where('is_active', true)->orderBy('name');
        if ($category !== 'all') {
            $staffQuery->where('category', $category);
        }
        $staff = $staffQuery->get();

        $otForms = OtForm::with(['user', 'entries.projectCode'])
            ->where('status', 'approved')
            ->where('month', $month)
            ->where('year', $year)
            ->get();

        $data = $this->buildOtSummaryData($staff, $otForms);

        $monthName = \DateTime::createFromFormat('!m', $month)->format('F');
        $filename = "OT_Summary_{$category}_{$monthName}_{$year}.pdf";

        $pdf = \Pdf::loadView('records.ot-summary-pdf', array_merge(
            $data,
            ['month' => $month, 'year' => $year, 'category' => $category, 'staff' => $staff]
        ))
            ->setPaper('a4', 'landscape')
            ->setOption(['dpi' => 150, 'defaultFont' => 'Arial']);

        return $pdf->download($filename);
    }

    private function buildOtSummaryData($staff, $otForms): array
    {
        $projects = [];
        $projectKey = fn($entry) => $entry->projectCode
            ? $entry->projectCode->project_code . '|' . $entry->projectCode->project_name
            : ($entry->project_category ?? 'N/A') . '|' . ($entry->manual_project_code_name ?? $entry->project_name ?? '');

        foreach ($otForms as $otForm) {
            foreach ($otForm->entries as $entry) {
                $key = $projectKey($entry);
                if (!isset($projects[$key])) {
                    $projects[$key] = [
                        'code' => $entry->projectCode ? $entry->projectCode->project_code : ($entry->project_category ?? 'N/A'),
                        'name' => $entry->projectCode ? $entry->projectCode->project_name : ($entry->manual_project_code_name ?? $entry->project_name ?? ''),
                        'hours' => array_fill_keys($staff->pluck('id')->toArray(), 0),
                    ];
                }
                if (!isset($projects[$key]['hours'][$otForm->user_id])) {
                    continue;
                }
                $floored = floor((float) $entry->actual_total_hours * 4) / 4;
                $projects[$key]['hours'][$otForm->user_id] += $floored;
            }
        }

        uksort($projects, function ($a, $b) use ($projects) {
            return strcmp($projects[$a]['code'] . $projects[$a]['name'], $projects[$b]['code'] . $projects[$b]['name']);
        });

        $totals = array_fill_keys($staff->pluck('id')->toArray(), 0);
        // Use the approved OT form's stored total_ot_hours as the source of truth
        // for each staff's total, so the summary matches the approved OT form.
        foreach ($otForms as $otForm) {
            if (isset($totals[$otForm->user_id])) {
                $totals[$otForm->user_id] += $otForm->total_ot_hours ?? 0;
            }
        }

        return compact('projects', 'totals');
    }

    private function getTimesheetApprovedDates($timesheets)
    {
        $ids = $timesheets->pluck('id');
        $logs = TimesheetApprovalLog::whereIn('timesheet_id', $ids)
            ->where('action', 'approved')
            ->orderBy('id')
            ->get()
            ->groupBy('timesheet_id');

        $approvedAt = [];
        foreach ($logs as $timesheetId => $logGroup) {
            $approvedAt[$timesheetId] = $logGroup->last()->created_at;
        }
        return $approvedAt;
    }

    private function getOtFormApprovedDates($otForms)
    {
        $ids = $otForms->pluck('id');
        $logs = ApprovalLog::where('approvable_type', 'ot_form')
            ->whereIn('approvable_id', $ids)
            ->where('action', 'approved')
            ->orderBy('id')
            ->get()
            ->groupBy('approvable_id');

        $approvedAt = [];
        foreach ($logs as $formId => $logGroup) {
            $approvedAt[$formId] = $logGroup->last()->acted_at;
        }
        return $approvedAt;
    }

    private function buildTimesheetApprovalStamps($timesheet, $approvalLogs)
    {
        $stamps = [];
        $submitted = !in_array($timesheet->status, ['draft']);

        $stamps[] = [
            'label' => 'Submitted',
            'code' => 'CLMD',
            'status' => $submitted ? 'approved' : 'empty',
            'date' => $timesheet->submitted_at ? $timesheet->submitted_at->format('m/d') : '',
            'name' => $timesheet->user->short_name ?? $timesheet->user->name ?? '',
            'role' => $timesheet->user->designation ?? 'Staff',
        ];

        $hodLog = $approvalLogs->where('level', 'hod')->where('action', 'approved')->first();
        $l1Log = $approvalLogs->where('level', 'l1')->where('action', 'approved')->first();

        $hodStatus = 'empty';
        if ($hodLog) {
            $hodStatus = 'approved';
        } elseif (in_array($timesheet->status, ['pending_hod'])) {
            $hodStatus = 'pending';
        } elseif (in_array($timesheet->status, ['pending_l1', 'approved', 'rejected_l1'])) {
            $hodStatus = 'approved';
        }

        $hodUser = $hodLog ? $hodLog->user : null;
        if (!$hodUser && $hodStatus === 'approved') {
            $hodUser = $timesheet->user->timesheetHodApprover;
        }
        $stamps[] = [
            'label' => 'TS1 Approver',
            'code' => 'APRV',
            'status' => $hodStatus,
            'date' => $hodLog && $hodLog->created_at ? $hodLog->created_at->format('m/d') : '',
            'name' => $hodUser ? ($hodUser->short_name ?? $hodUser->name) : '',
            'role' => $hodUser ? ($hodUser->designation ?? 'TS1 Approver') : 'TS1 Approver',
        ];

        $l1Status = 'empty';
        if ($l1Log) {
            $l1Status = 'approved';
        } elseif (in_array($timesheet->status, ['pending_l1'])) {
            $l1Status = 'pending';
        } elseif ($timesheet->status === 'approved') {
            $l1Status = 'approved';
        }

        $l1User = $l1Log ? $l1Log->user : null;
        if (!$l1User && $l1Status === 'approved') {
            $l1User = $timesheet->user->timesheetApprover;
        }
        $stamps[] = [
            'label' => 'Level 2 Approver',
            'code' => 'APRV',
            'status' => $l1Status,
            'date' => $l1Log && $l1Log->created_at ? $l1Log->created_at->format('m/d') : '',
            'name' => $l1User ? ($l1User->short_name ?? $l1User->name) : '',
            'role' => $l1User ? ($l1User->designation ?? 'Level 2 Approver') : 'Level 2 Approver',
        ];

        return $stamps;
    }

    private function buildOtApprovalStamps($otForm, $approvalLogs)
    {
        $stamps = [];
        $submitted = $otForm->plan_submitted_at || !in_array($otForm->status, ['draft']);

        $stamps[] = [
            'label' => $otForm->isNonExecutive() ? 'Disediakan Oleh' : 'Claimed by',
            'code' => 'CLMD',
            'status' => $submitted && $otForm->status !== 'draft' ? 'approved' : 'empty',
            'date' => $otForm->plan_submitted_at ? $otForm->plan_submitted_at->format('m/d') : '',
            'name' => $otForm->user->short_name ?? $otForm->user->name ?? '',
            'role' => $otForm->user->designation ?? 'Staff',
        ];

        $managerLog = $approvalLogs->where('level', 2)->where('action', 'approved')->first();
        $gmLog = $approvalLogs->where('level', 1)->where('action', 'approved')->first();

        $managerStatus = 'empty';
        if ($managerLog) {
            $managerStatus = 'approved';
        } elseif (in_array($otForm->status, ['pending_manager'])) {
            $managerStatus = 'pending';
        } elseif (in_array($otForm->status, ['pending_hr', 'pending_gm', 'approved', 'returned_hr'])) {
            $managerStatus = 'approved';
        }

        $managerUser = $managerLog ? $managerLog->approver : null;
        if (!$managerUser && $managerStatus === 'approved') {
            $managerUser = $otForm->user->ot_approver;
            if (!$managerUser && $otForm->isExecutive()) {
                $managerUser = $otForm->user->ot_exec_approver;
            }
            if (!$managerUser && !$otForm->isExecutive()) {
                $managerUser = $otForm->user->ot_non_exec_approver;
            }
        }

        $stamps[] = [
            'label' => $otForm->isNonExecutive() ? 'Disokong Oleh' : 'Approved by',
            'code' => 'APRV',
            'status' => $managerStatus,
            'date' => $managerLog && $managerLog->acted_at ? $managerLog->acted_at->format('m/d') : '',
            'name' => $managerUser ? ($managerUser->short_name ?? $managerUser->name) : '',
            'role' => $managerUser ? ($managerUser->designation ?? 'Manager') : 'Manager',
        ];

        $gmStatus = 'empty';
        if ($gmLog) {
            $gmStatus = 'approved';
        } elseif (in_array($otForm->status, ['pending_gm'])) {
            $gmStatus = 'pending';
        } elseif ($otForm->status === 'approved') {
            $gmStatus = 'approved';
        }

        $gmUser = $gmLog ? $gmLog->approver : null;
        if (!$gmUser && $gmStatus === 'approved') {
            $gmUser = $otForm->user->ot_final_approver;
            if (!$gmUser && $otForm->isExecutive()) {
                $gmUser = $otForm->user->ot_exec_final_approver;
            }
            if (!$gmUser && !$otForm->isExecutive()) {
                $gmUser = $otForm->user->ot_non_exec_final_approver;
            }
        }

        $stamps[] = [
            'label' => $otForm->isNonExecutive() ? 'Diluluskan Oleh' : 'Approved by',
            'code' => 'APRV',
            'status' => $gmStatus,
            'date' => $gmLog && $gmLog->acted_at ? $gmLog->acted_at->format('m/d') : '',
            'name' => $gmUser ? ($gmUser->short_name ?? $gmUser->name) : '',
            'role' => $gmUser ? ($gmUser->designation ?? 'CEO') : 'CEO',
        ];

        return $stamps;
    }
}
