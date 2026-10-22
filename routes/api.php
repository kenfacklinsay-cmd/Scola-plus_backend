<?php

use App\Http\Controllers\Api\AssessmentController;
use App\Http\Controllers\Api\AssignmentController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Api\Auth\PasswordResetController;
use App\Http\Controllers\Api\DashboardOverviewController;
use App\Http\Controllers\Api\FinanceOverviewController;
use App\Http\Controllers\Api\GradebookController;
use App\Http\Controllers\Api\ParentController;
use App\Http\Controllers\Api\ParentFinanceController;
use App\Http\Controllers\Api\ParentNotificationController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PaymentReceiptPdfController;
use App\Http\Controllers\Api\PaymentReceiptPrintController;
use App\Http\Controllers\Api\PeriodController;
use App\Http\Controllers\Api\SchoolClassController;
use App\Http\Controllers\Api\SchoolYearController;
use App\Http\Controllers\Api\StudentBulletinPdfController;
use App\Http\Controllers\Api\StudentBulletinPrintController;
use App\Http\Controllers\Api\Students\StudentController;
use App\Http\Controllers\Api\Students\StudentPhotoController;
use App\Http\Controllers\Api\SubjectController;
use App\Http\Controllers\Api\TeacherController;
use App\Http\Controllers\Api\TimetableController;
use App\Http\Controllers\Api\TuitionFeeController;
use App\Http\Controllers\Api\UserAccountController;
use App\Http\Resources\Api\UserResource;
use App\Models\SchoolClass;
use Illuminate\Support\Facades\Route;

// Vérifier que l'API backend est disponible pour le frontend séparé.
Route::get('/health', function () {
    return response()->json([
        'service' => 'ScolaPlus API',
        'status' => 'ok',
    ]);
})->name('api.health');

// Connexion et informations du compte pour l'application cliente.
Route::post('/login', [AuthenticatedSessionController::class, 'store'])
    ->middleware('throttle:login')
    ->name('api.login');

Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])
    ->middleware('throttle:password-reset')
    ->name('api.password.email');
Route::post('/reset-password', [PasswordResetController::class, 'reset'])
    ->middleware('throttle:password-reset')
    ->name('api.password.update');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn () => new UserResource(request()->user()))
        ->name('api.user');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('api.logout');

    Route::put('/account/password', [UserAccountController::class, 'updateOwnPassword'])
        ->name('api.account.password.update');
});

Route::middleware(['auth:sanctum', 'role:DIRECTEUR,SECRETARIAT'])
    ->prefix('students')
    ->name('api.students.')
    ->group(function () {
        Route::get('/', [StudentController::class, 'index'])->name('index');
        Route::post('/', [StudentController::class, 'store'])->name('store');
        Route::get('/{student}', [StudentController::class, 'show'])->name('show');
        Route::patch('/{student}', [StudentController::class, 'update'])->name('update');
        Route::patch('/{student}/enrollments/{enrollment}', [StudentController::class, 'transferEnrollment'])
            ->name('enrollments.transfer');
    });

Route::middleware(['auth:sanctum', 'role:DIRECTEUR,SECRETARIAT,COMPTABILITE'])
    ->get('/school-years', [SchoolYearController::class, 'index'])
    ->name('api.school-years.index');

Route::middleware(['auth:sanctum', 'role:DIRECTEUR,SECRETARIAT'])->group(function () {
    Route::get('/accounts', [UserAccountController::class, 'index'])->name('api.accounts.index');
    Route::patch('/accounts/{account}/status', [UserAccountController::class, 'updateStatus'])
        ->name('api.accounts.status.update');
    Route::post('/accounts/{account}/temporary-password', [UserAccountController::class, 'resetAccountPassword'])
        ->name('api.accounts.password.reset');
    Route::get('/classes', [SchoolClassController::class, 'index'])->name('api.classes.index');
    Route::get('/teachers', [TeacherController::class, 'index'])->name('api.teachers.index');
    Route::get('/parents', [ParentController::class, 'index'])->name('api.parents.index');
    Route::post('/parents/{parent}/students/{student}', [UserAccountController::class, 'linkStudent'])
        ->name('api.parents.students.link');
    Route::post('/periods', [PeriodController::class, 'store'])->name('api.periods.store');
    Route::get('/subjects', [SubjectController::class, 'index'])->name('api.subjects.index');
    Route::post('/subjects', [SubjectController::class, 'store'])->name('api.subjects.store');
    Route::patch('/subjects/{subject}', [SubjectController::class, 'update'])->name('api.subjects.update');
    Route::get('/assignments', [AssignmentController::class, 'index'])->name('api.assignments.index');
    Route::get('/schedules', [TimetableController::class, 'index'])->name('api.schedules.index');
    Route::post('/schedules', [TimetableController::class, 'store'])->name('api.schedules.store');
    Route::put('/schedules/{schedule}', [TimetableController::class, 'update'])->name('api.schedules.update');
    Route::delete('/schedules/{schedule}', [TimetableController::class, 'destroy'])->name('api.schedules.destroy');
    Route::post('/assignments', [AssignmentController::class, 'store'])->name('api.assignments.store');
    Route::patch('/assignments/{assignment}', [AssignmentController::class, 'update'])
        ->name('api.assignments.update');
    Route::post('/classes', [SchoolClassController::class, 'store'])
        ->can('manage-school-structure', SchoolClass::class)
        ->name('api.classes.store');
    Route::patch('/classes/{schoolClass}', [SchoolClassController::class, 'update'])
        ->can('manage-school-structure', 'schoolClass')
        ->name('api.classes.update');

    Route::post('/school-years', [SchoolYearController::class, 'store'])->name('api.school-years.store');
});

Route::middleware(['auth:sanctum', 'role:DIRECTEUR,SECRETARIAT'])->group(function () {
    Route::post('/staff-accounts', [UserAccountController::class, 'createStaff'])
        ->name('api.staff-accounts.store');
    Route::post('/teachers', [UserAccountController::class, 'createTeacher'])
        ->name('api.teachers.store');
    Route::post('/parents/{parent}/account', [UserAccountController::class, 'createParent'])
        ->name('api.parents.account.store');
});

Route::middleware(['auth:sanctum', 'role:DIRECTEUR,SECRETARIAT,PARENT'])->group(function () {
    Route::get('/students/{student}/periods/{period}/bulletin/pdf', StudentBulletinPdfController::class)
        ->name('api.students.periods.bulletin.pdf');
    Route::get('/students/{student}/periods/{period}/bulletin/print', StudentBulletinPrintController::class)
        ->name('api.students.periods.bulletin.print');
});

Route::middleware(['auth:sanctum', 'role:DIRECTEUR,SECRETARIAT,ENSEIGNANT'])->group(function () {
    Route::get('/periods', [PeriodController::class, 'index'])->name('api.periods.index');
});

Route::middleware(['auth:sanctum', 'role:ENSEIGNANT'])->group(function () {
    Route::get('/teacher/assignments', [AssignmentController::class, 'mine'])->name('api.teacher.assignments');
    Route::get('/teacher/students', [StudentController::class, 'teacherStudents'])->name('api.teacher.students');
    Route::get('/teacher/schedules', [TimetableController::class, 'mine'])->name('api.teacher.schedules');
    Route::get('/teacher/attendance', [AttendanceController::class, 'teacherRoster'])
        ->name('api.teacher.attendance.show');
    Route::put('/teacher/attendance', [AttendanceController::class, 'saveTeacherRoster'])
        ->name('api.teacher.attendance.update');
    Route::get('/assessments', [AssessmentController::class, 'index'])->name('api.assessments.index');
    Route::post('/assessments', [AssessmentController::class, 'store'])->name('api.assessments.store');
    Route::get('/assessments/{assessment}/grades', [GradebookController::class, 'showAssessmentGrades'])
        ->name('api.assessments.grades.index');
    Route::put('/assessments/{assessment}/grades', [GradebookController::class, 'saveAssessmentGrades'])
        ->name('api.assessments.grades.update');
});

Route::middleware(['auth:sanctum', 'role:DIRECTEUR'])->group(function () {
    Route::get('/dashboard/overview', DashboardOverviewController::class)
        ->name('api.dashboard.overview');

    Route::get('/classes/{schoolClass}/periods/{period}/grade-summary', [GradebookController::class, 'classSummary'])
        ->name('api.classes.periods.grade-summary');
});

// Comptabilité : lecture et encaissement ; seuls les comptes DIRECTEUR gèrent le tarif.
Route::middleware(['auth:sanctum', 'role:DIRECTEUR,COMPTABILITE'])->group(function () {
    Route::get('/finance/overview', FinanceOverviewController::class)->name('api.finance.overview');
    Route::get('/tuition-fees', [TuitionFeeController::class, 'index'])->name('api.tuition-fees.index');
    Route::get('/tuition-fees/{tuitionFee}/students', [TuitionFeeController::class, 'students'])
        ->name('api.tuition-fees.students.index');
    Route::get('/tuition-fees/{tuitionFee}/students/{student}/balance', [TuitionFeeController::class, 'studentBalance'])
        ->name('api.tuition-fees.students.balance');
    Route::get('/payments', [PaymentController::class, 'index'])->name('api.payments.index');
    Route::post('/payments', [PaymentController::class, 'store'])->name('api.payments.store');
    Route::get('/payments/{payment}/receipt', [PaymentController::class, 'receipt'])
        ->name('api.payments.receipt');
    Route::get('/payments/{payment}/receipt/print', PaymentReceiptPrintController::class)
        ->name('api.payments.receipt.print');
    Route::get('/payments/{payment}/receipt/pdf', PaymentReceiptPdfController::class)
        ->name('api.payments.receipt.pdf');
});

Route::middleware(['auth:sanctum', 'role:DIRECTEUR,SECRETARIAT'])
    ->get('/attendance/absences', [AttendanceController::class, 'absences'])
    ->name('api.attendance.absences.index');

Route::middleware(['auth:sanctum', 'role:PARENT'])->group(function () {
    Route::get('/parent/children', [ParentFinanceController::class, 'children'])
        ->name('api.parent.children.index');
    Route::get('/parent/children/{student}/tuition', [ParentFinanceController::class, 'childBalance'])
        ->name('api.parent.children.tuition');
    Route::get('/parent/children/{student}/periods/{period}', [ParentFinanceController::class, 'childPeriod'])
        ->name('api.parent.children.periods.show');
    Route::get('/parent/notifications', [ParentNotificationController::class, 'index'])
        ->name('api.parent.notifications.index');
    Route::patch('/parent/notifications/{notification}/read', [ParentNotificationController::class, 'markRead'])
        ->name('api.parent.notifications.read');
    Route::get('/parent/payments/{payment}/receipt/pdf', PaymentReceiptPdfController::class)
        ->name('api.parent.payments.receipt.pdf');
    Route::get('/parent/payments/{payment}/receipt/print', PaymentReceiptPrintController::class)
        ->name('api.parent.payments.receipt.print');
});

Route::middleware(['auth:sanctum', 'role:DIRECTEUR'])->group(function () {
    Route::post('/tuition-fees', [TuitionFeeController::class, 'store'])->name('api.tuition-fees.store');
    Route::put('/tuition-fees/{tuitionFee}/installments', [TuitionFeeController::class, 'saveInstallments'])
        ->name('api.tuition-fees.installments.update');
    Route::post('/tuition-fees/{tuitionFee}/discounts', [TuitionFeeController::class, 'addDiscount'])
        ->name('api.tuition-fees.discounts.store');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/students/{student}/photo', [StudentPhotoController::class, 'show'])
        ->can('viewPhoto', 'student')
        ->name('api.students.photo.show');

    Route::middleware('role:DIRECTEUR,SECRETARIAT')->group(function () {
        Route::put('/students/{student}/photo', [StudentPhotoController::class, 'store'])
            ->middleware('throttle:student-photo')
            ->can('updatePhoto', 'student')
            ->name('api.students.photo.store');

        Route::delete('/students/{student}/photo', [StudentPhotoController::class, 'destroy'])
            ->can('updatePhoto', 'student')
            ->name('api.students.photo.destroy');
    });
});
