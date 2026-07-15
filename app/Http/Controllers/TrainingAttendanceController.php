<?php

namespace App\Http\Controllers;

use App\Models\TrainingAttendance;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TrainingAttendanceController extends Controller
{
    private function isAdminOrHr(): bool
    {
        return in_array(Auth::user()->role, ['admin', 'hr']);
    }

    public function index()
    {
        $user = Auth::user();
        $isAdminOrHr = $this->isAdminOrHr();

        if ($isAdminOrHr) {
            $sessions = TrainingSession::withCount('attendances')
                ->orderByDesc('training_date')
                ->orderByDesc('created_at')
                ->get()
                ->map(fn ($session) => $session->setRelation('attended', $session->attendedBy($user)));
        } else {
            $attendedIds = TrainingAttendance::where('user_id', $user->id)
                ->pluck('training_session_id')
                ->toArray();

            $sessions = TrainingSession::where('is_active', true)
                ->orWhereIn('id', $attendedIds)
                ->orderByDesc('training_date')
                ->orderByDesc('created_at')
                ->get()
                ->map(fn ($session) => $session->setRelation('attended', $session->attendedBy($user)));
        }

        return view('training-attendance.index', [
            'sessions' => $sessions,
            'isAdminOrHr' => $isAdminOrHr,
        ]);
    }

    public function create()
    {
        if (! $this->isAdminOrHr()) {
            abort(403);
        }

        return view('training-attendance.create');
    }

    public function store(Request $request)
    {
        if (! $this->isAdminOrHr()) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'venue' => 'required|string|max:255',
            'training_date' => 'required|date',
            'time_in' => 'required|date_format:H:i',
            'time_out' => 'required|date_format:H:i|after:time_in',
        ]);

        $validated['created_by'] = Auth::id();
        $validated['is_active'] = false;

        TrainingSession::create($validated);

        return redirect()->route('training-attendance.index')
            ->with('success', 'Training session created successfully.');
    }

    public function edit(TrainingSession $trainingSession)
    {
        if (! $this->isAdminOrHr()) {
            abort(403);
        }

        return view('training-attendance.edit', [
            'session' => $trainingSession,
        ]);
    }

    public function update(Request $request, TrainingSession $trainingSession)
    {
        if (! $this->isAdminOrHr()) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'venue' => 'required|string|max:255',
            'training_date' => 'required|date',
            'time_in' => 'required|date_format:H:i',
            'time_out' => 'required|date_format:H:i|after:time_in',
        ]);

        $trainingSession->update($validated);

        return redirect()->route('training-attendance.index')
            ->with('success', 'Training session updated successfully.');
    }

    public function destroy(TrainingSession $trainingSession)
    {
        if (! $this->isAdminOrHr()) {
            abort(403);
        }

        $trainingSession->delete();

        return redirect()->route('training-attendance.index')
            ->with('success', 'Training session deleted successfully.');
    }

    public function activate(TrainingSession $trainingSession)
    {
        if (! $this->isAdminOrHr()) {
            abort(403);
        }

        $trainingSession->update(['is_active' => true]);

        return redirect()->route('training-attendance.index')
            ->with('success', 'Training session activated.');
    }

    public function deactivate(TrainingSession $trainingSession)
    {
        if (! $this->isAdminOrHr()) {
            abort(403);
        }

        $trainingSession->update(['is_active' => false]);

        return redirect()->route('training-attendance.index')
            ->with('success', 'Training session deactivated.');
    }

    public function attend(Request $request, TrainingSession $trainingSession)
    {
        $user = Auth::user();

        if (! $trainingSession->is_active) {
            return redirect()->route('training-attendance.index')
                ->with('error', 'This training session is not active.');
        }

        if ($trainingSession->attendedBy($user)) {
            return redirect()->route('training-attendance.index')
                ->with('error', 'You have already marked attendance for this training.');
        }

        $validated = $request->validate([
            'signature' => 'required|string|max:255',
        ]);

        TrainingAttendance::create([
            'training_session_id' => $trainingSession->id,
            'user_id' => $user->id,
            'staff_no' => $user->staff_no,
            'signature' => $validated['signature'],
            'attended_at' => now(),
        ]);

        return redirect()->route('training-attendance.index')
            ->with('success', 'Attendance marked successfully.');
    }

    public function report(TrainingSession $trainingSession)
    {
        if (! $this->isAdminOrHr()) {
            abort(403);
        }

        $attendances = TrainingAttendance::with('user')
            ->where('training_session_id', $trainingSession->id)
            ->orderBy('attended_at')
            ->get();

        $attendedUserIds = $attendances->pluck('user_id')->toArray();
        $users = User::whereNotIn('id', $attendedUserIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'staff_no']);

        return view('training-attendance.report', [
            'session' => $trainingSession,
            'attendances' => $attendances,
            'isAdminOrHr' => $this->isAdminOrHr(),
            'availableUsers' => $users,
        ]);
    }

    public function exportPdf(TrainingSession $trainingSession)
    {
        if (! $this->isAdminOrHr()) {
            abort(403);
        }

        $attendances = TrainingAttendance::with('user')
            ->where('training_session_id', $trainingSession->id)
            ->orderBy('attended_at')
            ->get();

        $pdf = \Pdf::loadView('training-attendance.pdf.export', [
            'session' => $trainingSession,
            'attendances' => $attendances,
        ])->setPaper('a4', 'portrait');

        $filename = 'Training_Attendance_' . preg_replace('/[^A-Za-z0-9]/', '_', $trainingSession->name) . '.pdf';

        return $pdf->stream($filename);
    }

    public function destroyAttendee(TrainingAttendance $attendance)
    {
        if (! $this->isAdminOrHr()) {
            abort(403);
        }

        $session = $attendance->trainingSession;
        $attendance->delete();

        return redirect()->route('training-attendance.report', $session)
            ->with('success', 'Attendee removed successfully.');
    }

    public function addAttendee(Request $request, TrainingSession $trainingSession)
    {
        if (! $this->isAdminOrHr()) {
            abort(403);
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'signature' => 'required|string|max:255',
        ]);

        if (TrainingAttendance::where('training_session_id', $trainingSession->id)
            ->where('user_id', $validated['user_id'])
            ->exists()) {
            return redirect()->route('training-attendance.report', $trainingSession)
                ->with('error', 'This user has already attended this session.');
        }

        $user = User::findOrFail($validated['user_id']);

        TrainingAttendance::create([
            'training_session_id' => $trainingSession->id,
            'user_id' => $user->id,
            'staff_no' => $user->staff_no,
            'signature' => $validated['signature'],
            'attended_at' => now(),
        ]);

        return redirect()->route('training-attendance.report', $trainingSession)
            ->with('success', 'Attendee added successfully.');
    }
}
