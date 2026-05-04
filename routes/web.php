<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\SystemConfigController;
use App\Http\Controllers\Admin\PublicHolidayController;
use App\Http\Controllers\Admin\DesknetSyncController;
use App\Http\Controllers\Admin\ProjectCodeController;
use App\Http\Controllers\TimesheetController;
use App\Http\Controllers\ExcelUploadController;
use App\Http\Controllers\AttendanceUploadController;
use App\Http\Controllers\TimesheetApprovalController;
use App\Http\Controllers\OtFormController;
use App\Http\Controllers\OtApprovalController;
use App\Http\Controllers\HistoryController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Timesheets
    Route::get('/timesheets', [TimesheetController::class, 'index'])->name('timesheets.index');
    Route::post('/timesheets', [TimesheetController::class, 'store'])->name('timesheets.store');
    Route::get('/timesheets/{timesheet}/edit', [TimesheetController::class, 'edit'])->name('timesheets.edit');
    Route::put('/timesheets/{timesheet}', [TimesheetController::class, 'save'])->name('timesheets.save');
    Route::delete('/timesheets/{timesheet}', [TimesheetController::class, 'destroy'])->name('timesheets.destroy');
    Route::get('/timesheets/{timesheet}/print', [TimesheetController::class, 'print'])->name('timesheets.print');
    Route::get('/timesheets/{timesheet}/preview-excel', [TimesheetController::class, 'previewExcel'])->name('timesheets.preview-excel');
    Route::get('/timesheets/{timesheet}/export-excel', [TimesheetController::class, 'exportExcel'])->name('timesheets.export-excel');
    Route::post('/timesheets/{timesheet}/upload-attendance', [AttendanceUploadController::class, 'upload'])->name('timesheets.upload-attendance');
    Route::post('/timesheets/{timesheet}/upload-excel', [ExcelUploadController::class, 'upload'])->name('timesheets.upload-excel');
    
    // Timesheet approval workflow
    Route::get('/approvals/timesheets', [TimesheetApprovalController::class, 'index'])->name('approvals.timesheets.index');
    Route::get('/approvals/timesheets/{timesheet}', [TimesheetApprovalController::class, 'show'])->name('approvals.timesheets.show');
    Route::post('/timesheets/{timesheet}/submit', [TimesheetApprovalController::class, 'submit'])->name('timesheets.submit');
    Route::post('/timesheets/{timesheet}/approve-hod', [TimesheetApprovalController::class, 'approveHOD'])->name('timesheets.approve-hod');
    Route::post('/timesheets/{timesheet}/reject-hod', [TimesheetApprovalController::class, 'rejectHOD'])->name('timesheets.reject-hod');
    Route::post('/timesheets/{timesheet}/skip-hod', [TimesheetApprovalController::class, 'skipHOD'])->name('timesheets.skip-hod');
    Route::post('/timesheets/{timesheet}/approve-l1', [TimesheetApprovalController::class, 'approveL1'])->name('timesheets.approve-l1');
    Route::post('/timesheets/{timesheet}/reject-l1', [TimesheetApprovalController::class, 'rejectL1'])->name('timesheets.reject-l1');

    // OT Forms
    Route::get('/ot-forms', [OtFormController::class, 'index'])->name('ot-forms.index');
    Route::post('/ot-forms', [OtFormController::class, 'store'])->name('ot-forms.store');
    Route::get('/ot-forms/{otForm}/edit', [OtFormController::class, 'edit'])->name('ot-forms.edit');
    Route::put('/ot-forms/{otForm}', [OtFormController::class, 'save'])->name('ot-forms.save');
    Route::delete('/ot-forms/{otForm}', [OtFormController::class, 'destroy'])->name('ot-forms.destroy');
    Route::post('/ot-forms/{otForm}/submit-plan', [OtFormController::class, 'submitPlan'])->name('ot-forms.submit-plan');
    Route::post('/ot-forms/{otForm}/auto-fill', [OtFormController::class, 'autoFillFromAttendance'])->name('ot-forms.auto-fill');
    Route::get('/ot-forms/{otForm}/export-excel', [OtFormController::class, 'exportExcel'])->name('ot-forms.export-excel');

    // History
    Route::get('/history', [HistoryController::class, 'index'])->name('history.index');

    // OT Form Approvals
    Route::get('/approvals/ot-forms', [OtApprovalController::class, 'index'])->name('approvals.ot-forms.index');
    Route::get('/approvals/ot-forms/{otForm}', [OtApprovalController::class, 'show'])->name('approvals.ot-forms.show');
    Route::post('/approvals/ot-forms/{otForm}/approve', [OtApprovalController::class, 'approve'])->name('approvals.ot-forms.approve');
    Route::post('/approvals/ot-forms/{otForm}/reject', [OtApprovalController::class, 'reject'])->name('approvals.ot-forms.reject');
});

// Admin routes
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    // User management
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');

    // System config
    Route::get('/settings', [SystemConfigController::class, 'index'])->name('settings.index');
    Route::put('/settings', [SystemConfigController::class, 'update'])->name('settings.update');

    // Public holidays
    Route::get('/holidays', [PublicHolidayController::class, 'index'])->name('holidays.index');
    Route::post('/holidays', [PublicHolidayController::class, 'store'])->name('holidays.store');
    Route::put('/holidays/{holiday}', [PublicHolidayController::class, 'update'])->name('holidays.update');
    Route::delete('/holidays/{holiday}', [PublicHolidayController::class, 'destroy'])->name('holidays.destroy');

    // Desknet sync
    Route::get('/desknet-sync', [DesknetSyncController::class, 'index'])->name('desknet-sync.index');
    Route::post('/desknet-sync/run', [DesknetSyncController::class, 'run'])->name('desknet-sync.run');
    Route::post('/desknet-sync/test', [DesknetSyncController::class, 'test'])->name('desknet-sync.test');

    // Project codes (read-only)
    Route::get('/project-codes', [ProjectCodeController::class, 'index'])->name('project-codes.index');
});

require __DIR__.'/auth.php';
