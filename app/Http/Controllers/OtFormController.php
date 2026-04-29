<?php

namespace App\Http\Controllers;

use App\Models\OtForm;
use App\Models\OtFormEntry;
use App\Models\ProjectCode;
use App\Services\OtAutoFillService;
use App\Services\OtFormExcelExport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class OtFormController extends Controller
{
    public function index()
    {
        $otForms = OtForm::where('user_id', Auth::id())
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('ot-forms.index', compact('otForms'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2020|max:2099',
            'form_type' => 'required|in:executive,non_executive',
            'company_name' => 'required|string|max:150',
            'section_line' => 'nullable|string|max:150',
        ]);

        $otForm = OtForm::create([
            'user_id' => Auth::id(),
            'month' => $request->month,
            'year' => $request->year,
            'form_type' => $request->form_type,
            'company_name' => $request->company_name,
            'section_line' => $request->section_line,
            'status' => 'draft',
        ]);

        // Pre-create 31 empty rows (one per day) for both executive and non-executive
        $daysInMonth = Carbon::create($request->year, $request->month)->daysInMonth;
        $entries = [];
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $entries[] = [
                'ot_form_id' => $otForm->id,
                'entry_date' => Carbon::create($request->year, $request->month, $day)->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        OtFormEntry::insert($entries);

        return redirect()->route('ot-forms.edit', $otForm)
            ->with('success', 'OT form created successfully.');
    }

    public function edit(OtForm $otForm)
    {
        if ($otForm->user_id !== Auth::id()) {
            abort(403);
        }

        $otForm->load('entries.projectCode', 'user.department');
        $projectCodes = ProjectCode::where('is_active', true)
            ->orderBy('code')
            ->get();

        // Build approval stamps data
        $approvalLogs = $otForm->approvalLogs();
        $approvalStamps = $this->buildOtApprovalStamps($otForm, $approvalLogs);

        // Approver names for mini stamps in table columns
        // Level 2 = Manager/HOD, Level 1 = GM/CEO (as set in OtApprovalController)
        $staffApproverName = $otForm->user->name ?? '';
        $managerLog = $approvalLogs->where('level', 2)->where('action', 'approved')->first();
        $gmLog = $approvalLogs->where('level', 1)->where('action', 'approved')->first();

        // Fallback to designated approvers if no approval logs exist
        if ($managerLog && $managerLog->approver) {
            $managerApproverName = $managerLog->approver->name;
        } elseif ($otForm->isExecutive() && $otForm->user->ot_exec_approver_id) {
            $managerApproverName = User::find($otForm->user->ot_exec_approver_id)->name ?? '';
        } elseif (!$otForm->isExecutive() && $otForm->user->ot_non_exec_approver_id) {
            $managerApproverName = User::find($otForm->user->ot_non_exec_approver_id)->name ?? '';
        } else {
            $managerApproverName = '';
        }

        if ($gmLog && $gmLog->approver) {
            $gmApproverName = $gmLog->approver->name;
        } elseif ($otForm->isExecutive() && $otForm->user->ot_exec_final_approver_id) {
            $gmApproverName = User::find($otForm->user->ot_exec_final_approver_id)->name ?? '';
        } elseif (!$otForm->isExecutive() && $otForm->user->ot_non_exec_final_approver_id) {
            $gmApproverName = User::find($otForm->user->ot_non_exec_final_approver_id)->name ?? '';
        } else {
            $gmApproverName = '';
        }

        return view('ot-forms.edit', compact(
            'otForm', 'projectCodes', 'approvalStamps',
            'staffApproverName', 'managerApproverName', 'gmApproverName'
        ));
    }

    public function save(Request $request, OtForm $otForm)
    {
        if ($otForm->user_id !== Auth::id()) {
            abort(403);
        }

        if (!$otForm->isEditable()) {
            return redirect()->route('ot-forms.edit', $otForm)
                ->with('error', 'This OT form cannot be edited in its current status.');
        }

        // Save section/line if changed
        $otForm->update([
            'section_line' => $request->input('section_line'),
        ]);

        $this->saveEntries($request, $otForm);

        return redirect()->route('ot-forms.edit', $otForm)
            ->with('success', 'OT form saved successfully.');
    }

    public function destroy(OtForm $otForm)
    {
        if ($otForm->user_id !== Auth::id()) {
            abort(403);
        }

        if ($otForm->status !== 'draft') {
            return redirect()->route('ot-forms.index')
                ->with('error', 'Only draft OT forms can be deleted.');
        }

        $otForm->delete();
        return redirect()->route('ot-forms.index')
            ->with('success', 'OT form deleted.');
    }

    public function previewExcel(OtForm $otForm, OtFormExcelExport $exporter)
    {
        if ($otForm->user_id !== Auth::id()) {
            abort(403);
        }

        $otForm->load('entries.projectCode', 'user.department');

        $spreadsheet = $exporter->generate($otForm);
        $writer = new Xlsx($spreadsheet);

        $tempDir = storage_path('app/temp');
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        $tempPath = $tempDir . '/excel_preview_ot_' . $otForm->id . '_' . time() . '.xlsx';
        $writer->save($tempPath);

        return view('excel-preview', [
            'title' => 'OT Form Excel Preview',
            'downloadUrl' => route('ot-forms.export-excel', $otForm),
            'backUrl' => route('ot-forms.edit', $otForm),
            'filePath' => $tempPath,
        ]);
    }

    public function exportExcel(OtForm $otForm, OtFormExcelExport $exporter)
    {
        if ($otForm->user_id !== Auth::id()) {
            abort(403);
        }

        $otForm->load('entries.projectCode', 'user.department');

        $spreadsheet = $exporter->generate($otForm);
        $writer = new Xlsx($spreadsheet);

        $type   = $otForm->isExecutive() ? 'OCF' : 'BKLM';
        $month  = \DateTime::createFromFormat('!m', $otForm->month)->format('F');
        $name   = preg_replace('/\s+/', '_', $otForm->user->name ?? 'Staff');
        $filename = "{$type}_{$name}_{$month}_{$otForm->year}.xlsx";

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    private function saveEntries(Request $request, OtForm $otForm): void
    {
        $entries = $request->input('entries', []);

        foreach ($entries as $entryId => $data) {
            $entry = OtFormEntry::where('id', $entryId)
                ->where('ot_form_id', $otForm->id)
                ->first();

            if (!$entry) continue;

            $plannedStart = $data['planned_start_time'] ?? null;
            $plannedEnd = $data['planned_end_time'] ?? null;
            $plannedTotal = 0;
            if ($plannedStart && $plannedEnd) {
                $plannedTotal = $this->calcHours($plannedStart, $plannedEnd);
            }

            $actualStart = $data['actual_start_time'] ?? null;
            $actualEnd = $data['actual_end_time'] ?? null;
            $actualTotal = 0;
            if ($actualStart && $actualEnd) {
                $actualTotal = $this->calcHours($actualStart, $actualEnd);
            }

            $entry->update([
                'project_code_id' => !empty($data['project_code_id']) ? $data['project_code_id'] : null,
                'project_name' => $data['project_name'] ?? null,
                'planned_start_time' => $plannedStart ?: null,
                'planned_end_time' => $plannedEnd ?: null,
                'planned_total_hours' => $plannedTotal,
                'actual_start_time' => $actualStart ?: null,
                'actual_end_time' => $actualEnd ?: null,
                'actual_total_hours' => $actualTotal,
                'meal_break' => !empty($data['meal_break']),
                'is_shift' => !empty($data['is_shift']),
                'is_public_holiday' => !empty($data['is_public_holiday']),
                'ot_normal_day_hours' => floatval($data['ot_normal_day_hours'] ?? 0),
                'ot_rest_day_hours' => floatval($data['ot_rest_day_hours'] ?? 0),
                'ot_rest_day_excess_hours' => floatval($data['ot_rest_day_excess_hours'] ?? 0),
                'ot_rest_day_count' => intval($data['ot_rest_day_count'] ?? 0),
                'ot_ph_hours' => floatval($data['ot_ph_hours'] ?? 0),
                'jenis_ot_normal' => !empty($data['jenis_ot_normal']),
                'jenis_ot_training' => !empty($data['jenis_ot_training']),
                'jenis_ot_kaizen' => !empty($data['jenis_ot_kaizen']),
                'jenis_ot_5s' => !empty($data['jenis_ot_5s']),
            ]);
        }
    }

    private function calcHours(string $start, string $end): float
    {
        $s = Carbon::parse($start);
        $e = Carbon::parse($end);
        // Handle overnight
        if ($e->lte($s)) {
            $e->addDay();
        }
        $diff = $e->diffInMinutes($s, true);
        return round(abs($diff) / 60, 2);
    }

    /**
     * Auto-fill OT form actual times from attendance records (parsed from PDF).
     */
    public function autoFillFromAttendance(OtForm $otForm, OtAutoFillService $autoFillService)
    {
        if ($otForm->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if (!$otForm->isEditable()) {
            return response()->json(['error' => 'OT form cannot be edited in its current status.'], 400);
        }

        $result = $autoFillService->autoFill($otForm);

        return response()->json([
            'success' => true,
            'filled' => $result['filled'],
            'skipped' => $result['skipped'],
            'warnings' => $result['warnings'] ?? [],
            'message' => $result['message'],
        ]);
    }

    public function submitPlan(Request $request, OtForm $otForm)
    {
        if ($otForm->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if (!in_array($otForm->status, ['draft', 'rejected'])) {
            return response()->json(['error' => 'OT form cannot be submitted in its current status.'], 400);
        }

        // Validate at least 1 entry with planned times
        $filledEntries = $otForm->entries()
            ->whereNotNull('planned_start_time')
            ->whereNotNull('planned_end_time')
            ->whereNotNull('project_code_id')
            ->count();

        if ($filledEntries === 0) {
            return response()->json(['error' => 'At least one entry with planned times is required.'], 400);
        }

        $otForm->update([
            'status' => 'pending_manager',
            'plan_submitted_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'status' => $otForm->status,
            'message' => 'Submitted for Manager/Asst Manager approval.',
        ]);
    }

    /**
     * Build stamp data array for approval-stamps component.
     * OT Forms: "Claimed by" (staff) + "Approved by" (HOD/Manager).
     */
    private function buildOtApprovalStamps(OtForm $otForm, $approvalLogs): array
    {
        $latestApproval = $approvalLogs->where('action', 'approved')->sortByDesc('level')->first();
        $latestReject = $approvalLogs->where('action', 'rejected')->sortByDesc('level')->first();
        $hodLog = $latestApproval ?? $latestReject;

        $stamps = [];

        // Stamp 1: Claimed by (Staff who submitted)
        $submitted = $otForm->plan_submitted_at || !in_array($otForm->status, ['draft']);
        $stamps[] = [
            'label'  => 'Claimed by',
            'code'   => 'CLMD',
            'status' => $submitted && $otForm->status !== 'draft' ? 'approved' : 'empty',
            'date'   => $otForm->plan_submitted_at ? $otForm->plan_submitted_at->format('m/d') : '',
            'name'   => $otForm->user->name ?? '',
            'role'   => 'Staff',
        ];

        // Stamp 2: Approved by (HOD / Manager who approved)
        $hodStatus = 'empty';
        if ($hodLog && $hodLog->action === 'approved') {
            $hodStatus = 'approved';
        } elseif ($hodLog && $hodLog->action === 'rejected') {
            $hodStatus = 'rejected';
        } elseif (in_array($otForm->status, ['pending_manager', 'pending_gm'])) {
            $hodStatus = 'pending';
        } elseif ($otForm->status === 'approved') {
            $hodStatus = 'approved';
        }

        $hodUser = $hodLog ? $hodLog->approver : null;
        $stamps[] = [
            'label'  => 'Approved by',
            'code'   => 'APRV',
            'status' => $hodStatus,
            'date'   => $hodLog && $hodLog->acted_at ? $hodLog->acted_at->format('m/d') : '',
            'name'   => $hodUser ? $hodUser->name : '',
            'role'   => $hodUser ? ($hodUser->designation ?? 'HOD') : 'HOD',
        ];

        return $stamps;
    }
}
