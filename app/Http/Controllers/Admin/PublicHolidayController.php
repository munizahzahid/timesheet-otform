<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PublicHoliday;
use Illuminate\Http\Request;

class PublicHolidayController extends Controller
{
    public function index(Request $request)
    {
        $year = $request->input('year', date('Y'));
        $holidays = PublicHoliday::where('year', $year)
            ->orderBy('holiday_date')
            ->get();

        $years = PublicHoliday::selectRaw('DISTINCT year')
            ->orderBy('year', 'desc')
            ->pluck('year');

        if ($years->isEmpty()) {
            $years = collect([date('Y')]);
        }

        return view('admin.holidays.index', compact('holidays', 'year', 'years'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'holiday_date' => 'required|date|unique:public_holidays,holiday_date',
            'name' => 'required|string|max:150',
        ]);

        PublicHoliday::create([
            'holiday_date' => $validated['holiday_date'],
            'name' => $validated['name'],
            'year' => date('Y', strtotime($validated['holiday_date'])),
            'source' => 'company',
            'is_recurring' => false,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.holidays.index', ['year' => date('Y', strtotime($validated['holiday_date']))])
            ->with('success', "Holiday '{$validated['name']}' added.");
    }

    public function update(Request $request, PublicHoliday $holiday)
    {
        $validated = $request->validate([
            'holiday_date' => 'required|date|unique:public_holidays,holiday_date,' . $holiday->id,
            'name' => 'required|string|max:150',
        ]);

        $holiday->update([
            'holiday_date' => $validated['holiday_date'],
            'name' => $validated['name'],
            'year' => date('Y', strtotime($validated['holiday_date'])),
        ]);

        return redirect()->route('admin.holidays.index', ['year' => $holiday->year])
            ->with('success', "Holiday '{$holiday->name}' updated.");
    }

    public function destroy(PublicHoliday $holiday)
    {
        if ($holiday->source === 'gazetted') {
            return redirect()->route('admin.holidays.index', ['year' => $holiday->year])
                ->with('error', 'Gazetted holidays cannot be deleted. You may edit them instead.');
        }

        $name = $holiday->name;
        $year = $holiday->year;
        $holiday->delete();

        return redirect()->route('admin.holidays.index', ['year' => $year])
            ->with('success', "Holiday '{$name}' deleted.");
    }
}
