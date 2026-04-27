<?php

namespace App\Http\Controllers;

use App\Models\Timesheet;
use App\Services\ExcelParsingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ExcelUploadController extends Controller
{
    protected ExcelParsingService $parser;

    public function __construct(ExcelParsingService $parser)
    {
        $this->parser = $parser;
    }

    /**
     * Process the uploaded Excel attendance file.
     */
    public function upload(Request $request, Timesheet $timesheet)
    {
        Log::info('Excel upload request received', [
            'timesheet_id' => $timesheet->id,
            'month' => $timesheet->month,
            'year' => $timesheet->year,
            'has_file' => $request->hasFile('excel_file'),
            'user_id' => $request->user()?->id,
        ]);

        // Authorize: only owner or admin
        if ($timesheet->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            abort(403);
        }

        // Only editable timesheets
        if (!in_array($timesheet->status, ['draft', 'rejected_l1', 'rejected_l2'])) {
            return redirect()->route('timesheets.edit', $timesheet)
                ->with('upload_error', 'Timesheet cannot be edited in its current status.');
        }

        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls,csv|max:5120', // max 5MB
        ]);

        try {
            $file = $request->file('excel_file');
            $originalName = $file->getClientOriginalName();
            $filePath = $file->getRealPath();

            Log::info('Excel upload processing', [
                'timesheet_id' => $timesheet->id,
                'file_name' => $originalName,
                'file_size' => $file->getSize(),
                'file_path' => $filePath,
            ]);

            $result = $this->parser->parseAndApply($filePath, $timesheet);

            Log::info('Excel upload result', [
                'timesheet_id' => $timesheet->id,
                'processed' => $result['processed'],
                'warnings_count' => count($result['warnings'] ?? []),
            ]);

            $message = "Excel processed successfully: {$result['processed']} days updated.";
            if ($result['employee_name']) {
                $message .= " Employee: {$result['employee_name']}";
            }
            if ($result['employee_code']) {
                $message .= " (Code: {$result['employee_code']})";
            }

            if ($result['processed'] === 0) {
                $message = "Excel file '{$originalName}' was processed but 0 days matched this timesheet "
                         . "(period: " . \DateTime::createFromFormat('!m', $timesheet->month)->format('F') . " {$timesheet->year}). "
                         . "Please ensure the Excel file matches the timesheet month.";

                return redirect()->route('timesheets.edit', $timesheet)
                    ->with('upload_error', $message)
                    ->with('upload_warnings', $result['warnings'] ?? []);
            }

            return redirect()->route('timesheets.edit', $timesheet)
                ->with('upload_success', $message)
                ->with('upload_warnings', $result['warnings'] ?? []);

        } catch (\Throwable $e) {
            Log::error('Excel upload failed: ' . $e->getMessage(), [
                'timesheet_id' => $timesheet->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('timesheets.edit', $timesheet)
                ->with('upload_error', 'Failed to process Excel file: ' . $e->getMessage());
        }
    }
}
