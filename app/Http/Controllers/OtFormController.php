<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\OtForm;
use App\Models\OtFormEntry;
use App\Models\ProjectCode;
use App\Models\ApprovalLog;
use App\Models\User;
use App\Services\OtAutoFillService;
use App\Services\OtEmailNotificationService;
use App\Services\OtFormExcelExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class OtFormController extends Controller
{
    protected OtEmailNotificationService $emailService;

    public function __construct(OtEmailNotificationService $emailService)
    {
        $this->emailService = $emailService;
    }

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

        $otForm->load(['entries' => function ($q) {
            $q->orderBy('entry_date')->orderBy('id');
        }, 'entries.projectCode', 'user.department', 'hrEditor']);
        $projectCodes = ProjectCode::where('is_active', true)
            ->orderBy('code')
            ->get();

        // Build approval stamps data
        $approvalLogs = $otForm->approvalLogs()->get();
        $approvalStamps = $this->buildOtApprovalStamps($otForm, $approvalLogs);

        // Determine if the OT form can be unsubmitted
        $hasApproval = $approvalLogs->where('action', 'approved')->isNotEmpty();
        $canUnsubmit = $otForm->user_id === Auth::id()
            && $otForm->status === 'pending_manager'
            && !$hasApproval;

        // Approver names for mini stamps in table columns
        // Level 2 = Manager/HOD, Level 1 = GM/CEO (as set in OtApprovalController)
        $staffApproverName = $otForm->user->short_name ?? $otForm->user->name ?? '';
        $managerLog = $approvalLogs->where('level', 2)->where('action', 'approved')->first();
        $gmLog = $approvalLogs->where('level', 1)->where('action', 'approved')->first();

        // Fallback to designated approvers if no approval logs exist
        $managerApproverName = '';
        $managerApproverDesignation = '';
        $managerApprovedDate = '';
        if ($managerLog && $managerLog->approver) {
            $managerApproverName = $managerLog->approver->short_name ?? $managerLog->approver->name;
            $managerApproverDesignation = $managerLog->approver->designation ?? 'Manager';
            $managerApprovedDate = $managerLog->acted_at ? $managerLog->acted_at->format('d/m/Y') : '';
        } elseif ($otForm->user->ot_approver_id) {
            $mgrUser = User::find($otForm->user->ot_approver_id);
            $managerApproverName = $mgrUser->short_name ?? $mgrUser->name ?? '';
            $managerApproverDesignation = $mgrUser->designation ?? 'Manager';
        } elseif ($otForm->isExecutive() && $otForm->user->ot_exec_approver_id) {
            $mgrUser = User::find($otForm->user->ot_exec_approver_id);
            $managerApproverName = $mgrUser->short_name ?? $mgrUser->name ?? '';
            $managerApproverDesignation = $mgrUser->designation ?? 'Manager';
        } elseif (!$otForm->isExecutive() && $otForm->user->ot_non_exec_approver_id) {
            $mgrUser = User::find($otForm->user->ot_non_exec_approver_id);
            $managerApproverName = $mgrUser->short_name ?? $mgrUser->name ?? '';
            $managerApproverDesignation = $mgrUser->designation ?? 'Manager';
        }

        $gmApproverName = '';
        $gmApproverDesignation = '';
        $gmApprovedDate = '';
        if ($gmLog && $gmLog->approver) {
            $gmApproverName = $gmLog->approver->short_name ?? $gmLog->approver->name;
            $gmApproverDesignation = $gmLog->approver->designation ?? 'CEO';
            $gmApprovedDate = $gmLog->acted_at ? $gmLog->acted_at->format('d/m/Y') : '';
        } elseif ($otForm->user->ot_final_approver_id) {
            $gmUser = User::find($otForm->user->ot_final_approver_id);
            $gmApproverName = $gmUser->short_name ?? $gmUser->name ?? '';
            $gmApproverDesignation = $gmUser->designation ?? 'CEO';
        } elseif ($otForm->isExecutive() && $otForm->user->ot_exec_final_approver_id) {
            $gmUser = User::find($otForm->user->ot_exec_final_approver_id);
            $gmApproverName = $gmUser->short_name ?? $gmUser->name ?? '';
            $gmApproverDesignation = $gmUser->designation ?? 'CEO';
        } elseif (!$otForm->isExecutive() && $otForm->user->ot_non_exec_final_approver_id) {
            $gmUser = User::find($otForm->user->ot_non_exec_final_approver_id);
            $gmApproverName = $gmUser->short_name ?? $gmUser->name ?? '';
            $gmApproverDesignation = $gmUser->designation ?? 'CEO';
        }

        return view('ot-forms.edit', compact(
            'otForm', 'projectCodes', 'approvalStamps',
            'staffApproverName', 'managerApproverName', 'gmApproverName',
            'managerApproverDesignation', 'gmApproverDesignation',
            'managerApprovedDate', 'gmApprovedDate', 'canUnsubmit'
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

    public function destroy(Request $request, OtForm $otForm)
    {
        $user = Auth::user();

        // Owner can delete only draft OT forms
        if ($otForm->user_id === $user->id) {
            if ($otForm->status !== 'draft') {
                return redirect()->route('ot-forms.index')
                    ->with('error', 'Only draft OT forms can be deleted by owner.');
            }
        } else {
            // Non-owners must be admin
            if (!$user->isAdmin()) {
                abort(403);
            }
            // Admin must provide reason for deleting non-draft
            if ($otForm->status !== 'draft') {
                $request->validate([
                    'delete_reason' => 'required|string|max:500',
                ]);
            }
        }

        DB::transaction(function () use ($otForm) {
            $otForm->entries()->delete();
            ApprovalLog::where('approvable_type', 'ot_form')
                ->where('approvable_id', $otForm->id)
                ->delete();
            $otForm->delete();

            // TODO: Add audit log entry here when audit feature is implemented
        });

        $formLabel = $otForm->form_type === 'executive' ? 'Executive' : 'Non-Executive';
        $period = \DateTime::createFromFormat('!m', $otForm->month)->format('F') . ' ' . $otForm->year;
        $formName = "{$formLabel} OT Form - {$period}";

        // If viewing another user's form (admin context), redirect to that user's history
        if ($otForm->user_id !== Auth::id()) {
            return redirect()->route('history.index', ['user_id' => $otForm->user_id])
                ->with('success', "Successfully deleted '{$formName}'");
        }

        return redirect()->route('ot-forms.index')
            ->with('success', "Successfully deleted '{$formName}'");
    }

    public function exportExcel(OtForm $otForm, OtFormExcelExport $exporter)
    {
        $user = Auth::user();

        // Allow: OT form owner, admin, or users with All Records permission for approved forms
        $canDownload = $otForm->user_id === $user->id || $user->isAdmin() || ($otForm->status === 'approved' && $user->canViewAllRecords());

        if (!$canDownload) {
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

    public function exportPdf(OtForm $otForm)
    {
        $user = Auth::user();

        // Allow: OT form owner, admin, or users with All Records permission for approved forms
        $canDownload = $otForm->user_id === $user->id || $user->isAdmin() || ($otForm->status === 'approved' && $user->canViewAllRecords());

        if (!$canDownload) {
            abort(403);
        }

        $otForm->load('entries.projectCode', 'user.department');

        $type   = $otForm->isExecutive() ? 'OCF' : 'BKLM';
        $month  = \DateTime::createFromFormat('!m', $otForm->month)->format('F');
        $name   = preg_replace('/\s+/', '_', $otForm->user->name ?? 'Staff');
        $filename = "{$type}_{$name}_{$month}_{$otForm->year}.pdf";

        $pdf = Pdf::loadView('ot-forms.pdf.export', compact('otForm'))
            ->setPaper('a4', 'landscape')
            ->setOption(['dpi' => 150, 'defaultFont' => 'Arial']);

        return $pdf->download($filename);
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

            // Calculate OT breakdown by day type
            $isPublicHoliday = !empty($data['is_public_holiday']);
            $dayOfWeek = $entry->entry_date->dayOfWeek; // 0=Sun, 6=Sat
            $isWeekend = in_array($dayOfWeek, [0, 6]);

            $otNormalDay = 0;
            $otRestDay = 0;
            $otRestDayExcess = 0;
            $otRestDayCount = 0;
            $otPhHours = 0;

            if ($actualTotal > 0) {
                if ($isPublicHoliday) {
                    $otPhHours = $actualTotal;
                } elseif ($isWeekend) {
                    $otRestDay = $actualTotal;
                    $otRestDayCount = 1;
                } else {
                    $otNormalDay = $actualTotal;
                }
            }

            $entry->update([
                'project_code_id' => !empty($data['project_code_id']) ? $data['project_code_id'] : null,
                'project_category' => $data['project_category'] ?? null,
                'manual_project_code_name' => $data['manual_project_code_name'] ?? null,
                'project_name' => $data['project_name'] ?? null,
                'planned_start_time' => $plannedStart ?: null,
                'planned_end_time' => $plannedEnd ?: null,
                'planned_total_hours' => $plannedTotal,
                'actual_start_time' => $actualStart ?: null,
                'actual_end_time' => $actualEnd ?: null,
                'actual_total_hours' => $actualTotal,
                'meal_break' => !empty($data['meal_break']),
                'is_shift' => !empty($data['is_shift']),
                'is_public_holiday' => $isPublicHoliday,
                'ot_normal_day_hours' => $otNormalDay,
                'ot_rest_day_hours' => $otRestDay,
                'ot_rest_day_excess_hours' => $otRestDayExcess,
                'ot_rest_day_count' => $otRestDayCount,
                'ot_ph_hours' => $otPhHours,
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
        return max(0, round($diff / 60, 2));
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

    /**
     * Add a new entry row for a given date on the OT form.
     */
    public function addEntry(Request $request, OtForm $otForm)
    {
        if ($otForm->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if (!$otForm->isEditable()) {
            return response()->json(['error' => 'OT form cannot be edited in its current status.'], 400);
        }

        $request->validate([
            'entry_date' => 'required|date',
        ]);

        $entryDate = Carbon::parse($request->entry_date);

        // Validate date is within the form's month/year
        if ($entryDate->month !== $otForm->month || $entryDate->year !== $otForm->year) {
            return response()->json(['error' => 'Date must be within the form\'s month/year.'], 400);
        }

        $entry = OtFormEntry::create([
            'ot_form_id' => $otForm->id,
            'entry_date' => $entryDate->toDateString(),
        ]);

        return response()->json([
            'success' => true,
            'entry_id' => $entry->id,
            'entry_date' => $entryDate->toDateString(),
        ]);
    }

    /**
     * Delete an entry row from the OT form (must keep at least 1 per date).
     */
    public function deleteEntry(OtForm $otForm, OtFormEntry $entry)
    {
        if ($otForm->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if (!$otForm->isEditable()) {
            return response()->json(['error' => 'OT form cannot be edited in its current status.'], 400);
        }

        if ($entry->ot_form_id !== $otForm->id) {
            return response()->json(['error' => 'Entry does not belong to this form.'], 400);
        }

        // Must keep at least 1 entry per date
        $countForDate = OtFormEntry::where('ot_form_id', $otForm->id)
            ->where('entry_date', $entry->entry_date)
            ->count();

        if ($countForDate <= 1) {
            return response()->json(['error' => 'Cannot delete the last entry for this date.'], 400);
        }

        $entry->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Staff can unsubmit their OT form if no approval has been made yet.
     */
    public function unsubmit(Request $request, OtForm $otForm)
    {
        if ($otForm->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($otForm->status !== 'pending_manager') {
            return response()->json(['error' => 'OT form cannot be unsubmitted in its current status.'], 400);
        }

        $hasApproval = $otForm->approvalLogs()
            ->where('action', 'approved')
            ->exists();

        if ($hasApproval) {
            return response()->json(['error' => 'Cannot unsubmit after approval.'], 400);
        }

        $otForm->update(['status' => 'draft']);

        return response()->json([
            'success' => true,
            'status' => $otForm->status,
            'message' => 'OT form unsubmitted. You can now edit it.',
        ]);
    }

    public function submitPlan(Request $request, OtForm $otForm)
    {
        if ($otForm->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if (!in_array($otForm->status, ['draft', 'rejected', 'returned_hr'])) {
            return response()->json(['error' => 'OT form cannot be submitted in its current status.'], 400);
        }

        // Validate at least 1 entry with planned times and a project selection
        $filledEntries = $otForm->entries()
            ->whereNotNull('planned_start_time')
            ->whereNotNull('planned_end_time')
            ->where(function ($q) {
                $q->whereNotNull('project_code_id')
                  ->orWhereNotNull('project_category');
            })
            ->count();

        if ($filledEntries === 0) {
            return response()->json(['error' => 'At least one entry with planned times is required.'], 400);
        }

        // If returned by HR, resubmit goes directly back to HR review
        $isResubmitFromHR = $otForm->status === 'returned_hr';
        $newStatus = $isResubmitFromHR ? 'pending_hr' : 'pending_manager';
        $message = $isResubmitFromHR
            ? 'Resubmitted for HR review.'
            : 'Submitted for Manager/Asst Manager approval.';

        $otForm->update([
            'status' => $newStatus,
            'plan_submitted_at' => now(),
        ]);

        // Notify HR users when resubmitting
        if ($isResubmitFromHR) {
            $this->notifyHRUsers($otForm, 'OT Form Resubmitted', "{$otForm->user->name}'s OT Form has been resubmitted after correction.");

            // Send email notifications to HR users
            $hrUsers = User::where('role', 'hr')->where('is_active', true)->get();
            foreach ($hrUsers as $hrUser) {
                $this->emailService->sendSubmissionNotification($otForm, $hrUser);
            }
        }

        // Notify designated L1 approver on first submission
        if (!$isResubmitFromHR && $otForm->user->ot_approver_id) {
            Notification::create([
                'user_id' => $otForm->user->ot_approver_id,
                'title' => 'OT Form Pending Approval',
                'message' => "An OT Form from {$otForm->user->name} is pending your approval.",
                'link' => route('approvals.ot-forms.show', $otForm),
            ]);

            // Send email notification to L1 approver
            $l1Approver = User::find($otForm->user->ot_approver_id);
            if ($l1Approver) {
                $this->emailService->sendSubmissionNotification($otForm, $l1Approver);
            }
        }

        return response()->json([
            'success' => true,
            'status' => $otForm->status,
            'message' => $message,
        ]);
    }

    /**
     * Build stamp data array for approval-stamps component.
     * OT Forms: "Claimed by" (staff) + "Approved by" (HOD/Manager).
     */
    private function buildOtApprovalStamps(OtForm $otForm, $approvalLogs): array
    {
        $stamps = [];
        $submitted = $otForm->plan_submitted_at || !in_array($otForm->status, ['draft']);

        // Stamp 1: Staff who submitted
        $stamps[] = [
            'label'  => $otForm->isNonExecutive() ? 'Disediakan Oleh' : 'Claimed by',
            'code'   => 'CLMD',
            'status' => $submitted && $otForm->status !== 'draft' ? 'approved' : 'empty',
            'date'   => $otForm->plan_submitted_at ? $otForm->plan_submitted_at->format('m/d') : '',
            'name'   => $otForm->user->short_name ?? $otForm->user->name ?? '',
            'role'   => $otForm->user->designation ?? 'Staff',
        ];

        // Find approval logs
        $managerLog = $approvalLogs->where('level', 2)->where('action', 'approved')->first();
        $gmLog = $approvalLogs->where('level', 1)->where('action', 'approved')->first();

        // Manager/HOD stamp
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
            } elseif (!$managerUser) {
                $managerUser = $otForm->user->ot_non_exec_approver;
            }
        }
        $stamps[] = [
            'label'  => $otForm->isNonExecutive() ? 'Disokong Oleh' : 'Approved by',
            'code'   => 'APRV',
            'status' => $managerStatus,
            'date'   => $managerLog && $managerLog->acted_at ? $managerLog->acted_at->format('m/d') : '',
            'name'   => $managerUser ? ($managerUser->short_name ?? $managerUser->name) : '',
            'role'   => $managerUser ? ($managerUser->designation ?? 'MGR / HOD') : 'MGR / HOD',
        ];

        // Add 3rd stamp for CEO final approval
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
            } elseif (!$gmUser) {
                $gmUser = $otForm->user->ot_non_exec_final_approver;
            }
        }
        $stamps[] = [
            'label'  => $otForm->isNonExecutive() ? 'Diluluskan Oleh' : 'Approved by',
            'code'   => 'APRV',
            'status' => $gmStatus,
            'date'   => $gmLog && $gmLog->acted_at ? $gmLog->acted_at->format('m/d') : '',
            'name'   => $gmUser ? ($gmUser->short_name ?? $gmUser->name) : '',
            'role'   => $gmUser ? ($gmUser->designation ?? 'CEO') : 'CEO',
        ];

        return $stamps;
    }

    /**
     * Notify all HR users about an OT form event.
     */
    private function notifyHRUsers(OtForm $otForm, string $title, string $message): void
    {
        $hrUsers = User::where('role', 'hr')->where('is_active', true)->get();
        foreach ($hrUsers as $hrUser) {
            Notification::create([
                'user_id' => $hrUser->id,
                'title' => $title,
                'message' => $message,
                'link' => route('approvals.ot-forms.show', $otForm),
            ]);
        }
    }
}
