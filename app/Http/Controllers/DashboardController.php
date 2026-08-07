<?php

namespace App\Http\Controllers;

use App\Models\TrainingAttendance;
use App\Models\TrainingSession;
use App\Services\DashboardHRSummaryService;
use App\Services\DashboardOtAnalyticsService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $summary = (new DashboardHRSummaryService())->getSummary($user);

        $analytics = new DashboardOtAnalyticsService();
        $availableMonths = $analytics->getAvailableMonths();

        $lastMonth = now()->subMonth()->startOfMonth();
        $lastMonthValue = $lastMonth->format('Y-m');
        $default = $availableMonths->firstWhere('value', $lastMonthValue) ?? $availableMonths->first();
        $selectedMonth = $request->input('month');

        if ($selectedMonth && preg_match('/^(\d{4})-(\d{2})$/', $selectedMonth, $matches)) {
            $year = (int) $matches[1];
            $month = (int) $matches[2];
        } elseif ($default) {
            $year = $default->year;
            $month = $default->month;
            $selectedMonth = $default->value;
        } else {
            $year = (int) $lastMonth->format('Y');
            $month = (int) $lastMonth->format('n');
            $selectedMonth = $lastMonthValue;
        }

        // Active training sessions
        $attendedIds = TrainingAttendance::where('user_id', $user->id)
            ->pluck('training_session_id')
            ->toArray();

        $activeTrainingSessions = TrainingSession::withCount('attendances')
            ->where('is_active', true)
            ->orWhereIn('id', $attendedIds)
            ->orderByDesc('training_date')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($session) => $session->setRelation('attended', $session->attendedBy($user)));

        $analyticsData = [
            'otProjectData' => $analytics->getOtHoursByProject($month, $year),
            'otStaffData' => $analytics->getOtHoursByStaff($month, $year),
            'otMonthlyData' => $analytics->getOtHoursByMonth(),
            'availableMonths' => $availableMonths,
            'selectedMonth' => $selectedMonth,
            'selectedYear' => $year,
            'selectedMonthNumber' => $month,
        ];

        return view('dashboard', array_merge(
            ['user' => $user],
            $summary,
            $analyticsData,
            ['activeTrainingSessions' => $activeTrainingSessions]
        ));
    }
}
