<?php

namespace App\Http\Controllers;

use App\Services\DashboardHRSummaryService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $summary = (new DashboardHRSummaryService())->getSummary($user);

        return view('dashboard', array_merge(
            ['user' => $user],
            $summary
        ));
    }
}
