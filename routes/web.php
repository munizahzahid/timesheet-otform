<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\SystemConfigController;
use App\Http\Controllers\Admin\PublicHolidayController;
use App\Http\Controllers\Admin\DesknetSyncController;
use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\ProjectCodeController;
use App\Http\Controllers\Admin\ProjectController;
use App\Http\Controllers\Admin\ProjectPhaseController;
use App\Http\Controllers\Admin\ProjectTaskController;
use App\Http\Controllers\Admin\ProjectTaskCommentController;
use App\Http\Controllers\Admin\ProjectTaskAttachmentController;
use App\Http\Controllers\TimesheetController;
use App\Http\Controllers\ExcelUploadController;
use App\Http\Controllers\AttendanceUploadController;
use App\Http\Controllers\TimesheetApprovalController;
use App\Http\Controllers\OtFormController;
use App\Http\Controllers\OtApprovalController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\ProjectCodeSearchController;
use App\Http\Controllers\AllRecordController;
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
    Route::get('/timesheets/{timesheet}/export-pdf', [TimesheetController::class, 'exportPdf'])->name('timesheets.export-pdf');
    Route::post('/timesheets/{timesheet}/upload-attendance', [AttendanceUploadController::class, 'upload'])->name('timesheets.upload-attendance');
    Route::post('/timesheets/{timesheet}/upload-excel', [ExcelUploadController::class, 'upload'])->name('timesheets.upload-excel');
    Route::post('/timesheets/{timesheet}/delete-excel', [ExcelUploadController::class, 'delete'])->name('timesheets.delete-excel');
    Route::get('/timesheets/{timesheet}/view-pdf', [ExcelUploadController::class, 'view'])->name('timesheets.view-pdf');
    
    // Timesheet approval workflow
    Route::get('/approvals/timesheets', [TimesheetApprovalController::class, 'index'])->name('approvals.timesheets.index');
    Route::get('/approvals/timesheets/approved', [TimesheetApprovalController::class, 'approved'])->name('approvals.timesheets.approved');
    Route::get('/approvals/timesheets/{timesheet}', [TimesheetApprovalController::class, 'show'])->name('approvals.timesheets.show');
    Route::post('/timesheets/{timesheet}/submit', [TimesheetApprovalController::class, 'submit'])->name('timesheets.submit');
    Route::post('/timesheets/{timesheet}/approve-hod', [TimesheetApprovalController::class, 'approveHOD'])->name('timesheets.approve-hod');
    Route::post('/timesheets/{timesheet}/reject-hod', [TimesheetApprovalController::class, 'rejectHOD'])->name('timesheets.reject-hod');
    Route::post('/timesheets/{timesheet}/approve-l1', [TimesheetApprovalController::class, 'approveL1'])->name('timesheets.approve-l1');
    Route::post('/timesheets/{timesheet}/reject-l1', [TimesheetApprovalController::class, 'rejectL1'])->name('timesheets.reject-l1');
    Route::post('/timesheets/{timesheet}/unsubmit', [TimesheetApprovalController::class, 'unsubmit'])->name('timesheets.unsubmit');

    // OT Forms
    Route::get('/ot-forms', [OtFormController::class, 'index'])->name('ot-forms.index');
    Route::post('/ot-forms', [OtFormController::class, 'store'])->name('ot-forms.store');
    Route::get('/ot-forms/{otForm}/edit', [OtFormController::class, 'edit'])->name('ot-forms.edit');
    Route::put('/ot-forms/{otForm}', [OtFormController::class, 'save'])->name('ot-forms.save');
    Route::delete('/ot-forms/{otForm}', [OtFormController::class, 'destroy'])->name('ot-forms.destroy');
    Route::post('/ot-forms/{otForm}/submit-plan', [OtFormController::class, 'submitPlan'])->name('ot-forms.submit-plan');
    Route::post('/ot-forms/{otForm}/unsubmit', [OtFormController::class, 'unsubmit'])->name('ot-forms.unsubmit');
    Route::post('/ot-forms/{otForm}/auto-fill', [OtFormController::class, 'autoFillFromAttendance'])->name('ot-forms.auto-fill');
    Route::post('/ot-forms/{otForm}/add-entry', [OtFormController::class, 'addEntry'])->name('ot-forms.add-entry');
    Route::delete('/ot-forms/{otForm}/entries/{entry}', [OtFormController::class, 'deleteEntry'])->name('ot-forms.delete-entry');
    Route::get('/ot-forms/{otForm}/export-excel', [OtFormController::class, 'exportExcel'])->name('ot-forms.export-excel');
    Route::get('/ot-forms/{otForm}/export-pdf', [OtFormController::class, 'exportPdf'])->name('ot-forms.export-pdf');

    // History
    Route::get('/history', [HistoryController::class, 'index'])->name('history.index');

    // All Records (view-only)
    Route::get('/records/timesheets', [AllRecordController::class, 'timesheets'])->name('records.timesheets');
    Route::get('/records/timesheets/summary', [AllRecordController::class, 'timesheetSummary'])->name('records.timesheets.summary');
    Route::get('/records/timesheets/summary/export-excel', [AllRecordController::class, 'exportSummaryExcel'])->name('records.timesheets.summary.export-excel');
    Route::get('/records/timesheets/summary/export-pdf', [AllRecordController::class, 'exportSummaryPdf'])->name('records.timesheets.summary.export-pdf');
    Route::get('/records/timesheets/{timesheet}', [AllRecordController::class, 'showTimesheet'])->name('records.timesheets.show');
    Route::get('/records/ot-forms', [AllRecordController::class, 'otForms'])->name('records.ot-forms');
    Route::get('/records/ot-forms/{otForm}', [AllRecordController::class, 'showOtForm'])->name('records.ot-forms.show');

    // Project code search API
    Route::get('/api/project-codes/search', [ProjectCodeSearchController::class, 'search'])->name('api.project-codes.search');

    // OT Form Approvals
    Route::get('/approvals/ot-forms', [OtApprovalController::class, 'index'])->name('approvals.ot-forms.index');
    Route::get('/approvals/ot-forms/approved', [OtApprovalController::class, 'approved'])->name('approvals.ot-forms.approved');
    Route::get('/approvals/ot-forms/{otForm}', [OtApprovalController::class, 'show'])->name('approvals.ot-forms.show');
    Route::post('/approvals/ot-forms/{otForm}/approve', [OtApprovalController::class, 'approve'])->name('approvals.ot-forms.approve');
    Route::post('/approvals/ot-forms/{otForm}/reject', [OtApprovalController::class, 'reject'])->name('approvals.ot-forms.reject');
    Route::post('/approvals/ot-forms/{otForm}/hr-forward', [OtApprovalController::class, 'hrForward'])->name('approvals.ot-forms.hr-forward');
    Route::post('/approvals/ot-forms/{otForm}/hr-return', [OtApprovalController::class, 'hrReturn'])->name('approvals.ot-forms.hr-return');
    Route::post('/approvals/ot-forms/{otForm}/hr-edit', [OtApprovalController::class, 'hrEdit'])->name('approvals.ot-forms.hr-edit');
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

    // Audit logs
    Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');

    // Project Management (Admin Only - Draft)
    Route::prefix('project')->name('project.')->group(function () {
        Route::get('/', [ProjectController::class, 'dashboard'])->name('dashboard');
        Route::prefix('projects')->name('projects.')->group(function () {
            Route::get('/', [ProjectController::class, 'index'])->name('index');
            Route::get('/create', [ProjectController::class, 'create'])->name('create');
            Route::post('/', [ProjectController::class, 'store'])->name('store');
            Route::get('/{project}', [ProjectController::class, 'show'])->name('show');
            Route::get('/{project}/edit', [ProjectController::class, 'edit'])->name('edit');
            Route::put('/{project}', [ProjectController::class, 'update'])->name('update');

            // Phases
            Route::prefix('{project}/phases')->name('phases.')->group(function () {
                Route::get('/', [ProjectPhaseController::class, 'index'])->name('index');
                Route::get('/create', [ProjectPhaseController::class, 'create'])->name('create');
                Route::post('/', [ProjectPhaseController::class, 'store'])->name('store');
                Route::get('/{phase}', [ProjectPhaseController::class, 'show'])->name('show');
                Route::get('/{phase}/edit', [ProjectPhaseController::class, 'edit'])->name('edit');
                Route::put('/{phase}', [ProjectPhaseController::class, 'update'])->name('update');
                Route::delete('/{phase}', [ProjectPhaseController::class, 'destroy'])->name('destroy');
            });

            // Tasks
            Route::prefix('{project}/tasks')->name('tasks.')->group(function () {
                Route::get('/', [ProjectTaskController::class, 'index'])->name('index');
                Route::get('/create', [ProjectTaskController::class, 'create'])->name('create');
                Route::post('/', [ProjectTaskController::class, 'store'])->name('store');
                Route::get('/{task}', [ProjectTaskController::class, 'show'])->name('show');
                Route::get('/{task}/edit', [ProjectTaskController::class, 'edit'])->name('edit');
                Route::put('/{task}', [ProjectTaskController::class, 'update'])->name('update');
                Route::delete('/{task}', [ProjectTaskController::class, 'destroy'])->name('destroy');
                Route::post('/{task}/quick-update', [ProjectTaskController::class, 'quickUpdate'])->name('quick-update');

                // Comments
                Route::post('/{task}/comments', [ProjectTaskCommentController::class, 'store'])->name('comments.store');
                Route::delete('/{task}/comments/{comment}', [ProjectTaskCommentController::class, 'destroy'])->name('comments.destroy');

                // Attachments
                Route::get('/{task}/attachments/{attachment}', [ProjectTaskAttachmentController::class, 'show'])->name('attachments.show');
                Route::post('/{task}/attachments', [ProjectTaskAttachmentController::class, 'store'])->name('attachments.store');
                Route::delete('/{task}/attachments/{attachment}', [ProjectTaskAttachmentController::class, 'destroy'])->name('attachments.destroy');
            });
        });
    });
});

require __DIR__.'/auth.php';
