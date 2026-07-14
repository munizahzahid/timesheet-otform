<?php

namespace App\Http\Controllers;

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

        $default = $availableMonths->first();
        $selectedMonth = $request->input('month');

        if ($selectedMonth && preg_match('/^(\d{4})-(\d{2})$/', $selectedMonth, $matches)) {
            $year = (int) $matches[1];
            $month = (int) $matches[2];
        } elseif ($default) {
            $year = $default->year;
            $month = $default->month;
            $selectedMonth = $default->value;
        } else {
            $year = (int) now()->format('Y');
            $month = (int) now()->format('n');
            $selectedMonth = now()->format('Y-m');
        }

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
            $analyticsData
        ));
    }
}
