<?php

namespace App\Http\Controllers;

use App\Models\ExcelUpload;
use App\Models\Timesheet;
use App\Services\ExcelParsingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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

            // Save file to storage
            $storedPath = $file->store('uploads/attendance', 'local');

            // Save file record
            ExcelUpload::create([
                'user_id' => $request->user()->id,
                'timesheet_id' => $timesheet->id,
                'file_name' => $originalName,
                'file_path' => $storedPath,
                'month' => $timesheet->month,
                'year' => $timesheet->year,
                'rows_parsed' => $result['processed'] ?? 0,
                'rows_failed' => 0,
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

    /**
     * Delete the uploaded file for a timesheet.
     */
    public function delete(Request $request, Timesheet $timesheet)
    {
        // Authorize: only owner or admin
        if ($timesheet->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            abort(403);
        }

        // Only editable timesheets
        if (!in_array($timesheet->status, ['draft', 'rejected_l1', 'rejected_l2'])) {
            return redirect()->route('timesheets.edit', $timesheet)
                ->with('upload_error', 'Timesheet cannot be edited in its current status.');
        }

        $upload = ExcelUpload::where('timesheet_id', $timesheet->id)->first();
        if ($upload) {
            // Delete file from storage
            if (Storage::exists($upload->file_path)) {
                Storage::delete($upload->file_path);
            }
            $upload->delete();
        }

        return redirect()->route('timesheets.edit', $timesheet)
            ->with('upload_success', 'File deleted successfully. You can now upload a new file.');
    }

    /**
     * View the uploaded file for a timesheet.
     */
    public function view(Request $request, Timesheet $timesheet)
    {
        // Authorize: only owner or admin
        if ($timesheet->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            abort(403);
        }

        $upload = ExcelUpload::where('timesheet_id', $timesheet->id)->first();
        if (!$upload) {
            abort(404, 'No file uploaded for this timesheet.');
        }

        if (!Storage::exists($upload->file_path)) {
            abort(404, 'File not found in storage.');
        }

        $file = Storage::get($upload->file_path);
        $mimeType = Storage::mimeType($upload->file_path);

        return response($file, 200, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . $upload->file_name . '"',
        ]);
    }
}
