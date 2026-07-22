<?php

namespace App\Http\Controllers;

use App\Models\ApprovalLog;
use App\Models\Notification;
use App\Models\OtForm;
use App\Models\OtFormEntry;
use App\Models\Project;
use App\Models\PublicHoliday;
use App\Models\User;
use App\Services\OtEmailNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OtApprovalController extends Controller
{
    protected OtEmailNotificationService $emailService;

    public function __construct(OtEmailNotificationService $emailService)
    {
        $this->emailService = $emailService;
    }

    /**
     * List OT forms pending the current user's approval/review.
     */
    public function index()
    {
        $user = Auth::user();
        $pendingStatuses = $this->getPendingStatusesForUser($user);

        if (empty($pendingStatuses)) {
            $otForms = OtForm::where('id', 0)->paginate(20);
            return view('approvals.ot-forms.index', compact('otForms'));
        }

        $query = OtForm::with('user')
            ->whereIn('status', $pendingStatuses);

        // Filter by designated approver if not admin/hr
        if (!in_array($user->role, ['admin', 'hr'])) {
            $query->where(function ($q) use ($user, $pendingStatuses) {
                if (in_array('pending_manager', $pendingStatuses)) {
                    $q->whereHas('user', function ($uq) use ($user) {
                        $uq->where('ot_approver_id', $user->id);
                    });
                }
                if (in_array('pending_gm', $pendingStatuses)) {
                    $q->orWhereHas('user', function ($uq) use ($user) {
                        $uq->where('ot_final_approver_id', $user->id);
                    });
                }
                // HR review is role-based
                if (in_array('pending_hr', $pendingStatuses)) {
                    $q->orWhere('status', 'pending_hr');
                }
            });
        }

        $otForms = $query->orderByDesc('updated_at')->paginate(20);

        return view('approvals.ot-forms.index', compact('otForms'));
    }

    /**
     * List approved OT forms for the current user.
     */
    public function approved()
    {
        $user = Auth::user();

        // Show OT forms this user has approved or reviewed/forwarded as HR
        $approvedIds = ApprovalLog::where('approvable_type', 'ot_form')
            ->where('approver_id', $user->id)
            ->whereIn('action', ['approved', 'hr_forwarded'])
            ->pluck('approvable_id')
            ->unique()
            ->values();

        $query = OtForm::with('user')->whereIn('id', $approvedIds);

        $otForms = $query->orderByDesc('updated_at')->paginate(20);

        // Load this user's approval/review dates for the paginated forms
        $formIds = $otForms->pluck('id');
        $approvalLogs = ApprovalLog::where('approvable_type', 'ot_form')
            ->whereIn('approvable_id', $formIds)
            ->where('approver_id', $user->id)
            ->whereIn('action', ['approved', 'hr_forwarded'])
            ->orderBy('id')
            ->get()
            ->groupBy('approvable_id');

        $approvedAt = [];
        foreach ($approvalLogs as $formId => $logs) {
            $approvedAt[$formId] = $logs->last()->acted_at;
        }

        return view('approvals.ot-forms.approved', compact('otForms', 'approvedAt'));
    }

    /**
     * Show a single OT form for review.
     */
    public function show(OtForm $otForm)
    {
        $otForm->load('entries.projectCode', 'user.department');

        $projectCodes = Project::where('is_active', true)
            ->orderBy('project_code')
            ->get(['id', 'project_code', 'project_name']);

        // Load public holidays for this month (for UI highlighting)
        $publicHolidays = PublicHoliday::whereYear('holiday_date', $otForm->year)
            ->whereMonth('holiday_date', $otForm->month)
            ->pluck('holiday_date')
            ->map(fn($d) => $d->format('Y-m-d'))
            ->flip()
            ->all();

        // Build approval stamps
        $approvalLogs = $otForm->approvalLogs()->get();
        $approvalStamps = $this->buildApprovalStamps($otForm, $approvalLogs);

        $hasHrCorrections = $otForm->entries->contains(fn ($e) => !empty($e->hr_corrections));

        return view('approvals.ot-forms.show', compact('otForm', 'approvalStamps', 'projectCodes', 'publicHolidays', 'hasHrCorrections'));
    }

    /**
     * Approve OT form (Manager/HOD or CEO).
     */
    public function approve(Request $request, OtForm $otForm)
    {
        $user = Auth::user();

        // Check if user is authorized to approve based on designated approvers
        $canApprove = $this->canUserApproveByDesignation($otForm, $user);
        if (!$canApprove) {
            return response()->json(['error' => 'You are not authorized to approve this OT form.'], 403);
        }

        $nextStatus = $this->getNextStatus($otForm->status);
        if (!$nextStatus) {
            return response()->json(['error' => 'This OT form is not pending your approval.'], 403);
        }

        $request->validate(['signature' => 'required|string']);

        $level = match ($otForm->status) {
            'pending_manager' => 2,  // Manager/HOD level
            'pending_gm' => 1,       // GM/CEO level
            default => 1,
        };

        $otForm->update(['status' => $nextStatus]);

        try {
            ApprovalLog::create([
                'approvable_type' => 'ot_form',
                'approvable_id' => $otForm->id,
                'approver_id' => $user->id,
                'phase' => 'ot_pre',
                'level' => $level,
                'action' => 'approved',
                'acted_at' => now(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to create approval log: ' . $e->getMessage());
        }

        // Send notifications based on new status
        if ($nextStatus === 'pending_hr') {
            $monthName = \DateTime::createFromFormat('!m', $otForm->month)->format('F');
            $this->notifyHRUsers($otForm, 'OT Form Pending HR Review', "{$otForm->user->name}'s OT Form ({$otForm->form_type_label}) for {$monthName} {$otForm->year} is pending your review.");

            // Send email notifications to HR users
            $hrUsers = User::where('role', 'hr')->where('is_active', true)->get();
            foreach ($hrUsers as $hrUser) {
                $this->emailService->sendSubmissionNotification($otForm, $hrUser);
            }
        }

        if ($nextStatus === 'approved') {
            $this->emailService->sendApprovalNotification($otForm);
        }

        $message = match ($nextStatus) {
            'pending_hr' => 'OT form approved by Manager/HOD, forwarded to HR for review.',
            'approved' => 'OT form approved by CEO.',
            default => 'OT form approved.',
        };

        return response()->json([
            'success' => true,
            'status' => $otForm->status,
            'message' => $message,
        ]);
    }

    /**
     * HR forwards OT form to CEO.
     */
    public function hrForward(Request $request, OtForm $otForm)
    {
        $user = Auth::user();

        if (!$user->canReviewOTForm()) {
            return response()->json(['error' => 'You are not authorized to review this OT form.'], 403);
        }

        if ($otForm->status !== 'pending_hr') {
            return response()->json(['error' => 'This OT form is not pending HR review.'], 400);
        }

        $otForm->update(['status' => 'pending_gm']);

        ApprovalLog::create([
            'approvable_type' => 'ot_form',
            'approvable_id' => $otForm->id,
            'approver_id' => $user->id,
            'phase' => 'ot_pre',
            'level' => 0,
            'action' => 'hr_forwarded',
            'acted_at' => now(),
        ]);

        // Notify CEO / final approver
        $this->notifyCEOUsers($otForm, 'OT Form Pending CEO Approval', "{$otForm->user->name}'s OT Form has been forwarded by HR for your approval.");

        // Send email notification to CEO / final approver
        $ceoUser = null;
        if ($otForm->user->ot_final_approver_id) {
            $ceoUser = User::find($otForm->user->ot_final_approver_id);
        }
        if (!$ceoUser) {
            $ceoUser = User::where('role', 'ceo')->where('is_active', true)->first();
        }
        if ($ceoUser) {
            $this->emailService->sendSubmissionNotification($otForm, $ceoUser);
        }

        // Notify staff that the form has been reviewed and forwarded to CEO
        $editMessage = $otForm->hr_remarks
            ? "Your OT Form has been reviewed and edited by HR before forwarding to CEO. Corrections:\n{$otForm->hr_remarks}"
            : "Your OT Form has been reviewed by HR and forwarded to CEO for final approval.";

        Notification::create([
            'user_id' => $otForm->user_id,
            'title' => 'OT Form Forwarded to CEO',
            'message' => $editMessage,
            'link' => route('ot-forms.edit', $otForm),
        ]);

        return response()->json([
            'success' => true,
            'status' => $otForm->status,
            'message' => 'OT form forwarded to CEO for approval.',
        ]);
    }

    /**
     * HR returns OT form for correction.
     */
    public function hrReturn(Request $request, OtForm $otForm)
    {
        $user = Auth::user();

        if (!$user->canReviewOTForm()) {
            return response()->json(['error' => 'You are not authorized to review this OT form.'], 403);
        }

        if ($otForm->status !== 'pending_hr') {
            return response()->json(['error' => 'This OT form is not pending HR review.'], 400);
        }

        $request->validate(['remarks' => 'required|string']);

        $otForm->update(['status' => 'returned_hr']);

        ApprovalLog::create([
            'approvable_type' => 'ot_form',
            'approvable_id' => $otForm->id,
            'approver_id' => $user->id,
            'phase' => 'ot_pre',
            'level' => 0,
            'action' => 'hr_returned',
            'remarks' => $request->remarks,
            'acted_at' => now(),
        ]);

        // Notify employee
        Notification::create([
            'user_id' => $otForm->user_id,
            'title' => 'OT Form Returned for Correction',
            'message' => "Your OT Form has been returned by HR for correction. Reason: {$request->remarks}",
            'link' => route('ot-forms.edit', $otForm),
        ]);

        // Send email notification to employee
        $this->emailService->sendHrReturnNotification($otForm, $request->remarks);

        return response()->json([
            'success' => true,
            'status' => $otForm->status,
            'message' => 'OT form returned to employee for correction.',
        ]);
    }

    /**
     * HR edits and saves corrections to an OT form.
     */
    public function hrEdit(Request $request, OtForm $otForm)
    {
        $user = Auth::user();

        if (!$user->canReviewOTForm()) {
            return response()->json(['error' => 'You are not authorized to review this OT form.'], 403);
        }

        if (!in_array($otForm->status, ['pending_hr', 'pending_gm', 'approved'])) {
            return response()->json(['error' => 'This OT form cannot be edited by HR in its current status.'], 400);
        }

        $request->validate([
            'entries' => 'required|array',
            'entries.*.id' => 'required|integer|exists:ot_form_entries,id',
        ]);

        $entries = $request->input('entries', []);
        $summaryLines = [];
        $now = now();

        DB::transaction(function () use ($otForm, $entries, $user, $now, &$summaryLines) {
            foreach ($entries as $entryData) {
                $entryId = $entryData['id'] ?? null;
                if (!$entryId) continue;

                $entry = OtFormEntry::where('id', $entryId)
                    ->where('ot_form_id', $otForm->id)
                    ->first();
                if (!$entry) continue;

                // Original values: from hr_corrections if already edited, else current values
                $original = $entry->hr_corrections ?? [
                    'planned_start_time' => $entry->getOriginal('planned_start_time') ?? $entry->planned_start_time,
                    'planned_end_time' => $entry->getOriginal('planned_end_time') ?? $entry->planned_end_time,
                    'actual_start_time' => $entry->getOriginal('actual_start_time') ?? $entry->actual_start_time,
                    'actual_end_time' => $entry->getOriginal('actual_end_time') ?? $entry->actual_end_time,
                    'project_code_id' => $entry->project_code_id,
                    'project_category' => $entry->project_category,
                    'manual_project_code_name' => $entry->manual_project_code_name,
                    'project_name' => $entry->project_name,
                ];

                // Build new values from request
                $newValues = [
                    'planned_start_time' => $entryData['planned_start_time'] ?? null,
                    'planned_end_time' => $entryData['planned_end_time'] ?? null,
                    'actual_start_time' => $entryData['actual_start_time'] ?? null,
                    'actual_end_time' => $entryData['actual_end_time'] ?? null,
                    'project_code_id' => !empty($entryData['project_code_id']) ? $entryData['project_code_id'] : null,
                    'project_category' => $entryData['project_category'] ?? null,
                    'manual_project_code_name' => $entryData['manual_project_code_name'] ?? null,
                    'project_name' => $entryData['project_name'] ?? null,
                ];

                // Normalize time strings to H:i for comparison
                $normalizeTime = function ($value) {
                    if (!$value) return null;
                    return \substr($value, 0, 5);
                };

                $compareValue = function ($field, $old, $new) use ($normalizeTime) {
                    if (in_array($field, ['planned_start_time', 'planned_end_time', 'actual_start_time', 'actual_end_time'])) {
                        return $normalizeTime($old) === $normalizeTime($new);
                    }
                    return $old == $new;
                };

                // Calculate hours from times
                $newPlannedTotal = $this->calcHoursFromStrings($newValues['planned_start_time'], $newValues['planned_end_time']);
                $newActualTotal = $this->calcHoursFromStrings($newValues['actual_start_time'], $newValues['actual_end_time']);

                // Recalculate OT category breakdown based on day type
                // Match PDF/Excel split logic:
                //   rest day: ot2 = min(8.0, hours), ot3 = max(0, hours - 8.0), ot5 = 1
                //   normal day: ot1 = hours
                //   public holiday: ot4 = hours
                $isPH = $entry->is_public_holiday ?? false;
                $isRest = $entry->entry_date && in_array($entry->entry_date->dayOfWeek, [0, 6]);
                if ($isPH) {
                    $newValues['ot_type'] = 'public_holiday';
                    $newValues['ot_normal_day_hours'] = 0;
                    $newValues['ot_rest_day_hours'] = 0;
                    $newValues['ot_rest_day_excess_hours'] = 0;
                    $newValues['ot_rest_day_count'] = 0;
                    $newValues['ot_ph_hours'] = $newActualTotal;
                } elseif ($isRest) {
                    $newValues['ot_type'] = 'rest_day';
                    $newValues['ot_normal_day_hours'] = 0;
                    if ($otForm->isExecutive()) {
                        $newValues['ot_rest_day_hours'] = $newActualTotal;
                        $newValues['ot_rest_day_excess_hours'] = 0;
                    } else {
                        $newValues['ot_rest_day_hours'] = min($newActualTotal, 8.0);
                        $newValues['ot_rest_day_excess_hours'] = max(0, $newActualTotal - 8.0);
                    }
                    $newValues['ot_rest_day_count'] = 1;
                    $newValues['ot_ph_hours'] = 0;
                } else {
                    $newValues['ot_type'] = 'normal_day';
                    $newValues['ot_normal_day_hours'] = $newActualTotal;
                    $newValues['ot_rest_day_hours'] = 0;
                    $newValues['ot_rest_day_excess_hours'] = 0;
                    $newValues['ot_rest_day_count'] = 0;
                    $newValues['ot_ph_hours'] = 0;
                }

                // Detect changes and record summary
                $dateLabel = $entry->entry_date->format('j/n');
                $changedFields = [];
                $fieldLabels = [
                    'planned_start_time' => 'Plan Start',
                    'planned_end_time' => 'Plan End',
                    'actual_start_time' => 'Actual Start',
                    'actual_end_time' => 'Actual End',
                    'project_code_id' => 'Project Code',
                    'project_category' => 'Project Category',
                    'manual_project_code_name' => 'Project Name',
                    'project_name' => 'Project Name',
                ];

                $displayValue = function ($field, $value) use ($normalizeTime) {
                    if (in_array($field, ['planned_start_time', 'planned_end_time', 'actual_start_time', 'actual_end_time'])) {
                        return $normalizeTime($value) ?? '-';
                    }
                    if ($field === 'project_code_id') {
                        return $value ? Project::find($value)?->project_code : '-';
                    }
                    return $value ?? '-';
                };

                foreach ($newValues as $field => $newValue) {
                    if (!isset($fieldLabels[$field])) continue;
                    $oldValue = $original[$field] ?? null;
                    if (!$compareValue($field, $oldValue, $newValue)) {
                        $changedFields[] = "- {$fieldLabels[$field]}: {$displayValue($field, $oldValue)} → {$displayValue($field, $newValue)}";
                    }
                }

                if (!empty($changedFields)) {
                    $summaryLines[] = $dateLabel . ":\n" . implode("\n", $changedFields);
                }

                // Update current values, preserving original snapshot only on first HR edit
                $updateData = [
                    'planned_start_time' => $newValues['planned_start_time'] ?: null,
                    'planned_end_time' => $newValues['planned_end_time'] ?: null,
                    'actual_start_time' => $newValues['actual_start_time'] ?: null,
                    'actual_end_time' => $newValues['actual_end_time'] ?: null,
                    'planned_total_hours' => $newPlannedTotal,
                    'actual_total_hours' => $newActualTotal,
                    'project_code_id' => $newValues['project_code_id'],
                    'project_category' => $newValues['project_category'] ?: null,
                    'manual_project_code_name' => $newValues['manual_project_code_name'] ?: null,
                    'project_name' => $newValues['project_name'] ?: null,
                    'ot_type' => $newValues['ot_type'] ?? $entry->ot_type,
                    'ot_normal_day_hours' => $newValues['ot_normal_day_hours'] ?? 0,
                    'ot_rest_day_hours' => $newValues['ot_rest_day_hours'] ?? 0,
                    'ot_rest_day_excess_hours' => $newValues['ot_rest_day_excess_hours'] ?? 0,
                    'ot_rest_day_count' => $newValues['ot_rest_day_count'] ?? 0,
                    'ot_ph_hours' => $newValues['ot_ph_hours'] ?? 0,
                ];

                if (empty($entry->hr_corrections)) {
                    $updateData['hr_corrections'] = $original;
                }

                $entry->update($updateData);
            }

            // Recalculate total OT hours from all entries
            $totalOtHours = $otForm->entries()->sum('actual_total_hours');

            // Update OT form HR metadata
            $otForm->update([
                'hr_remarks' => !empty($summaryLines) ? implode("\n", $summaryLines) : null,
                'hr_edited_at' => $now,
                'hr_edited_by' => $user->id,
                'total_ot_hours' => $totalOtHours,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'OT form corrections saved.',
            'hr_remarks' => $otForm->fresh()->hr_remarks,
        ]);
    }

    private function calcHoursFromStrings(?string $start, ?string $end): float
    {
        if (!$start || !$end) return 0;
        $s = \Carbon\Carbon::parse($start);
        $e = \Carbon\Carbon::parse($end);
        if ($e->lte($s)) $e->addDay();
        return max(0, round($e->diffInMinutes($s, true) / 60, 2));
    }

    /**
     * Reject OT form.
     */
    public function reject(Request $request, OtForm $otForm)
    {
        $user = Auth::user();

        if (!in_array($otForm->status, ['pending_manager', 'pending_gm'])) {
            return response()->json(['error' => 'This OT form is not pending approval.'], 403);
        }

        $canApprove = $this->canUserApproveByDesignation($otForm, $user);
        if (!$canApprove) {
            return response()->json(['error' => 'You are not authorized to reject this OT form.'], 403);
        }

        $request->validate(['remarks' => 'required|string']);

        $level = $otForm->status === 'pending_gm' ? 1 : 2;

        $otForm->update(['status' => 'rejected']);

        ApprovalLog::create([
            'approvable_type' => 'ot_form',
            'approvable_id' => $otForm->id,
            'approver_id' => $user->id,
            'phase' => 'ot_pre',
            'level' => $level,
            'action' => 'rejected',
            'remarks' => $request->remarks,
            'acted_at' => now(),
        ]);

        // Send email notification to employee
        $this->emailService->sendRejectionNotification($otForm, $user, $request->remarks);

        return response()->json([
            'success' => true,
            'status' => $otForm->status,
            'message' => 'OT form rejected.',
        ]);
    }

    /**
     * Determine if user is the designated OT approver for this form.
     */
    private function canUserApproveByDesignation(OtForm $otForm, $user): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        $formUser = $otForm->user;

        // Level 1 (pending_manager): user must be the designated OT approver
        if ($otForm->status === 'pending_manager') {
            return $formUser->ot_approver_id && Auth::id() === $formUser->ot_approver_id;
        }

        // Level 3 (pending_gm): user must be the designated final approver
        if ($otForm->status === 'pending_gm') {
            return $formUser->ot_final_approver_id && Auth::id() === $formUser->ot_final_approver_id;
        }

        return false;
    }

    /**
     * Get next status based on current status.
     */
    private function getNextStatus(string $currentStatus): ?string
    {
        return match ($currentStatus) {
            'pending_manager' => 'pending_hr',
            'pending_gm' => 'approved',
            default => null,
        };
    }

    /**
     * Get the statuses this user can act on as a designated OT approver.
     */
    private function getPendingStatusesForUser($user): array
    {
        if ($user->role === 'admin') {
            return ['pending_manager', 'pending_hr', 'pending_gm'];
        }

        $statuses = [];

        // HR can review pending_hr forms
        if ($user->canReviewOTForm()) {
            $statuses[] = 'pending_hr';
        }

        // Check if user is a designated approver for any staff
        $isOtApprover = User::where('ot_approver_id', $user->id)->exists();
        $isOtFinalApprover = User::where('ot_final_approver_id', $user->id)->exists();

        if ($isOtApprover) {
            $statuses[] = 'pending_manager';
        }

        if ($isOtFinalApprover) {
            $statuses[] = 'pending_gm';
        }

        return $statuses;
    }

    /**
     * Build stamp data array for approval-stamps component.
     * OT Forms: "Claimed by" (staff) + "Approved by" (HOD/Manager).
     */
    private function buildApprovalStamps(OtForm $otForm, $approvalLogs): array
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

    /**
     * Notify CEO / final approver users about an OT form event.
     */
    private function notifyCEOUsers(OtForm $otForm, string $title, string $message): void
    {
        $formUser = $otForm->user;

        // Try designated final approver first
        if ($formUser->ot_final_approver_id) {
            Notification::create([
                'user_id' => $formUser->ot_final_approver_id,
                'title' => $title,
                'message' => $message,
                'link' => route('approvals.ot-forms.show', $otForm),
            ]);
            return;
        }

        // Fallback: notify all CEO-role users
        $ceoUsers = User::where('role', 'ceo')->where('is_active', true)->get();
        foreach ($ceoUsers as $ceoUser) {
            Notification::create([
                'user_id' => $ceoUser->id,
                'title' => $title,
                'message' => $message,
                'link' => route('approvals.ot-forms.show', $otForm),
            ]);
        }
    }
}
