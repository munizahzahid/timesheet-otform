<?php

namespace App\Http\Controllers;

use App\Models\Timesheet;
use App\Services\ExcelParsingService;
use App\Services\PdfParsingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AttendanceUploadController extends Controller
{
    protected PdfParsingService $pdfParser;
    protected ExcelParsingService $excelParser;

    public function __construct(PdfParsingService $pdfParser, ExcelParsingService $excelParser)
    {
        $this->pdfParser = $pdfParser;
        $this->excelParser = $excelParser;
    }

    /**
     * Process the uploaded attendance file (PDF primary, Excel fallback).
     */
    public function upload(Request $request, Timesheet $timesheet)
    {
        Log::info('Attendance upload request received', [
            'timesheet_id' => $timesheet->id,
            'month' => $timesheet->month,
            'year' => $timesheet->year,
            'has_file' => $request->hasFile('attendance_file'),
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
            'attendance_file' => 'required|file|mimes:pdf,xlsx,xls,csv|max:5120', // max 5MB
        ]);

        try {
            $file = $request->file('attendance_file');
            $originalName = $file->getClientOriginalName();
            $filePath = $file->getRealPath();
            $extension = strtolower($file->getClientOriginalExtension());

            Log::info('Attendance upload processing', [
                'timesheet_id' => $timesheet->id,
                'file_name' => $originalName,
                'file_size' => $file->getSize(),
                'file_type' => $extension,
            ]);

            // Route to appropriate parser
            if ($extension === 'pdf') {
                $result = $this->pdfParser->parseAndApply($filePath, $timesheet);
            } else {
                $result = $this->excelParser->parseAndApply($filePath, $timesheet);
            }

            Log::info('Attendance upload result', [
                'timesheet_id' => $timesheet->id,
                'file_type' => $extension,
                'processed' => $result['processed'],
                'warnings_count' => count($result['warnings'] ?? []),
            ]);

            $fileType = $extension === 'pdf' ? 'PDF' : 'Excel';
            $message = "{$fileType} processed successfully: {$result['processed']} days updated.";
            if ($result['employee_name']) {
                $message .= " Employee: {$result['employee_name']}";
            }
            if ($result['employee_code']) {
                $message .= " (Code: {$result['employee_code']})";
            }

            if ($result['processed'] === 0) {
                $message = "{$fileType} file '{$originalName}' was processed but 0 days matched this timesheet "
                         . "(period: " . \DateTime::createFromFormat('!m', $timesheet->month)->format('F') . " {$timesheet->year}). "
                         . "Please ensure the file matches the timesheet month.";

                return redirect()->route('timesheets.edit', $timesheet)
                    ->with('upload_error', $message)
                    ->with('upload_warnings', $result['warnings'] ?? []);
            }

            return redirect()->route('timesheets.edit', $timesheet)
                ->with('upload_success', $message)
                ->with('upload_warnings', $result['warnings'] ?? []);

        } catch (\Throwable $e) {
            Log::error('Attendance upload failed: ' . $e->getMessage(), [
                'timesheet_id' => $timesheet->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('timesheets.edit', $timesheet)
                ->with('upload_error', 'Failed to process file: ' . $e->getMessage());
        }
    }
}
