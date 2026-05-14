<?php

use App\Http\Controllers\Auth\SsoAuthenticatedSessionController;
use App\Http\Controllers\CurriculumController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\GradesController;
use App\Http\Controllers\InternetAccountController;
use App\Http\Controllers\StudentProfileController;
use App\Http\Controllers\StudentRecordsController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AnnouncementCategoryController;
use App\Http\Controllers\EvaluationController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\ClearanceUpdateController;
use App\Http\Controllers\StudentSemesterClearanceController;
use App\Http\Controllers\ClearanceAccountabilityController;
use App\Http\Controllers\Admin\ReferenceLookupController;
use App\Http\Controllers\SiteSettings\SiteCampusController;
use App\Http\Controllers\SiteSettings\SiteAcademicTermController;
use App\Http\Controllers\SiteSettings\SiteGradeViewingController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return redirect()->route('auth.sso.redirect');
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('auth/sso/redirect', [SsoAuthenticatedSessionController::class, 'redirect'])
        ->name('auth.sso.redirect');

    Route::get('auth/sso/callback', [SsoAuthenticatedSessionController::class, 'callback'])
        ->name('auth.sso.callback');

    Route::get('auth/callback', [SsoAuthenticatedSessionController::class, 'callback'])
        ->name('auth.callback');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)
        ->middleware('can:dashboard.view')
        ->name('dashboard');
    Route::get('grades', GradesController::class)
        ->middleware('can:grades.view')
        ->name('grades.index');
    Route::post('grades/evaluation/submit', [GradesController::class, 'submitEvaluation'])
        ->middleware('can:grades.view')
        ->name('grades.evaluation.submit');
    Route::get('curriculum', CurriculumController::class)
        ->middleware('can:curriculum.view')
        ->name('curriculum.index');
    Route::get('student-academic-registration', fn() => Inertia::render('Enrollment/StudentAcademicRegistration'))
        ->name('enrollment.student-academic-registration');
    Route::get('student-academic-registration/status', [EnrollmentController::class, 'status'])
        ->name('enrollment.student-academic-registration.status');
    Route::get('student-academic-registration/confirm', fn() => redirect()->route('enrollment.student-academic-registration'));
    Route::post('student-academic-registration/confirm', [EnrollmentController::class, 'submitConfirmation'])
        ->name('enrollment.student-academic-registration.confirm');
    Route::get('student-profile', StudentProfileController::class)
        ->middleware('can:student-profile.view')
        ->name('student-profile.index');
    Route::get('internet-accounts', [InternetAccountController::class, 'index'])
        ->name('internet-accounts.index');
    Route::post('internet-accounts', [InternetAccountController::class, 'store'])
        ->middleware('can:internet-accounts.create')
        ->name('internet-accounts.store');
    Route::patch('internet-accounts/{internetAccount}/approve', [InternetAccountController::class, 'approve'])
        ->middleware('can:internet-accounts.approve')
        ->name('internet-accounts.approve');
    Route::patch('internet-accounts/{internetAccount}/cancel', [InternetAccountController::class, 'cancel'])
        ->middleware('can:internet-accounts.cancel')
        ->name('internet-accounts.cancel');
    Route::delete('internet-accounts/{internetAccount}', [InternetAccountController::class, 'destroy'])
        ->middleware('can:internet-accounts.delete')
        ->name('internet-accounts.destroy');

    Route::prefix('student/evaluation')
        ->name('student.evaluation.')
        ->controller(EvaluationController::class)
        ->group(function () {
            Route::get('/', 'studentIndex')
                ->middleware('can:evaluation.view')
                ->name('index');
            Route::post('/intent', 'submitIntent')
                ->middleware('can:evaluation.submit-intent')
                ->name('intent.store');
            Route::patch('/requests/{evaluationRequest}/cancel', 'cancelIntent')
                ->name('requests.cancel');
        });

    Route::prefix('admin/evaluations')
        ->name('admin.evaluations.')
        ->controller(EvaluationController::class)
        ->middleware('can:evaluation.manage-requests')
        ->group(function () {
            Route::get('/', 'adminIndex')->name('index');
            Route::post('/periods', 'storePeriod')
                ->middleware('can:evaluation.create-period')
                ->name('periods.store');
            Route::patch('/periods/{evaluationPeriod}', 'updatePeriod')
                ->middleware('can:evaluation.edit-period')
                ->name('periods.update');
            Route::patch('/periods/{evaluationPeriod}/status', 'updatePeriodStatus')
                ->middleware('can:evaluation.edit-period')
                ->name('periods.status');
            Route::delete('/periods/{evaluationPeriod}', 'destroyPeriod')
                ->middleware('can:evaluation.delete-period')
                ->name('periods.destroy');
            Route::get('/requests/{evaluationRequest}', 'showRequest')->name('requests.show');
            Route::patch('/requests/{evaluationRequest}/status', 'updateRequestStatus')
                ->name('requests.status');
            Route::post('/requests/{evaluationRequest}/feedback', 'addFeedback')
                ->middleware('can:evaluation.feedback')
                ->name('requests.feedback');
        });

    // Student Records CRUD
    Route::post('/achievements', [StudentRecordsController::class, 'storeAchievement'])
        ->middleware('can:achievements.create')
        ->name('achievements.store');
    Route::patch('/achievements/{achievement}', [StudentRecordsController::class, 'updateAchievement'])
        ->middleware('can:achievements.update')
        ->name('achievements.update');
    Route::delete('/achievements/{achievement}', [StudentRecordsController::class, 'deleteAchievement'])
        ->middleware('can:achievements.delete')
        ->name('achievements.delete');

    Route::post('/trainings', [StudentRecordsController::class, 'storeTraining'])
        ->middleware('can:trainings.create')
        ->name('trainings.store');
    Route::patch('/trainings/{training}', [StudentRecordsController::class, 'updateTraining'])
        ->middleware('can:trainings.update')
        ->name('trainings.update');
    Route::delete('/trainings/{training}', [StudentRecordsController::class, 'deleteTraining'])
        ->middleware('can:trainings.delete')
        ->name('trainings.delete');

    Route::prefix('user-management')
        ->name('user-management.')
        ->controller(UserManagementController::class)
        ->group(function () {
            Route::get('/', 'index')
                ->middleware('can:users.view')
                ->name('index');
            Route::get('/roles', 'rolesPermissions')
                ->name('roles.index');

            Route::post('/users', 'storeUser')
                ->middleware('can:users.create')
                ->name('users.store');
            Route::patch('/users/{user}', 'updateUser')
                ->middleware('can:users.update')
                ->name('users.update');
            Route::patch('/users/{user}/office', 'assignOffice')
                ->middleware('can:users.update')
                ->name('users.tag-office');
            Route::patch('/users/{user}/roles', 'assignRoles')
                ->middleware('can:users.assign-role')
                ->name('users.roles.update');
            Route::patch('/users/{user}/toggle', 'toggleUser')
                ->middleware('can:users.update')
                ->name('users.toggle');
            Route::delete('/users/{user}', 'destroyUser')
                ->middleware('can:users.delete')
                ->name('users.destroy');

            Route::post('/roles', 'storeRole')
                ->middleware('can:roles.create')
                ->name('roles.store');
            Route::patch('/roles/{role}', 'updateRole')
                ->middleware('can:roles.update')
                ->name('roles.update');
            Route::patch('/roles/{role}/permissions', 'syncRolePermissions')
                ->middleware('can:roles.update')
                ->name('roles.permissions.update');
            Route::delete('/roles/{role}', 'destroyRole')
                ->middleware('can:roles.delete')
                ->name('roles.destroy');

            Route::post('/permissions', 'storePermission')
                ->middleware('can:permissions.create')
                ->name('permissions.store');
            Route::patch('/permissions/{permission}', 'updatePermission')
                ->middleware('can:permissions.update')
                ->name('permissions.update');
            Route::delete('/permissions/{permission}', 'destroyPermission')
                ->middleware('can:permissions.delete')
                ->name('permissions.destroy');
        });

    // Reference Lookups
    Route::prefix('admin/reference-lookups')
        ->name('admin.reference-lookups.')
        ->controller(ReferenceLookupController::class)
        ->middleware('role:Super Admin') // Or specific permission
        ->group(function () {
            Route::get('/', 'index')->name('index');
            
            // Offices
            Route::post('/offices', 'storeOffice')->name('offices.store');
            Route::patch('/offices/{office}', 'updateOffice')->name('offices.update');
            Route::delete('/offices/{office}', 'destroyOffice')->name('offices.destroy');
            
            // Clearance Types
            Route::post('/types', 'storeType')->name('types.store');
            Route::patch('/types/{type}', 'updateType')->name('types.update');
            Route::delete('/types/{type}', 'destroyType')->name('types.destroy');
            
            // Semesters
            Route::post('/semesters', 'storeSemester')->name('semesters.store');
            Route::patch('/semesters/{semester}', 'updateSemester')->name('semesters.update');
            Route::delete('/semesters/{semester}', 'destroySemester')->name('semesters.destroy');
        });

    // Announcements Module
    Route::prefix('announcements')
        ->name('announcements.')
        ->group(function () {
            Route::get('/', [AnnouncementController::class, 'index'])
                ->middleware('can:viewAny,App\Models\Announcement')
                ->name('index');
            Route::get('/create', [AnnouncementController::class, 'create'])
                ->middleware('can:create,App\Models\Announcement')
                ->name('create');
            Route::post('/', [AnnouncementController::class, 'store'])
                ->middleware('can:create,App\Models\Announcement')
                ->name('store');

            // Categories
            Route::prefix('categories')
                ->name('categories.')
                ->controller(AnnouncementCategoryController::class)
                ->group(function () {
                    Route::get('/', 'index')
                        ->middleware('can:announcement.manage-categories')
                        ->name('index');
                    Route::post('/', 'store')
                        ->middleware('can:announcement.manage-categories')
                        ->name('store');
                    Route::patch('/{category}', 'update')
                        ->middleware('can:announcement.manage-categories')
                        ->name('update');
                    Route::delete('/{category}', 'destroy')
                        ->middleware('can:announcement.manage-categories')
                        ->name('destroy');
                });

            Route::get('/{announcement}', [AnnouncementController::class, 'show'])
                ->middleware('can:view,announcement')
                ->name('show');
            Route::get('/{announcement}/edit', [AnnouncementController::class, 'edit'])
                ->middleware('can:update,announcement')
                ->name('edit');
            Route::patch('/{announcement}', [AnnouncementController::class, 'update'])
                ->middleware('can:update,announcement')
                ->name('update');
            Route::delete('/{announcement}', [AnnouncementController::class, 'destroy'])
                ->middleware('can:delete,announcement')
                ->name('destroy');
            Route::patch('/{announcement}/publish', [AnnouncementController::class, 'publish'])
                ->middleware('can:publish,announcement')
                ->name('publish');
            Route::patch('/{announcement}/archive', [AnnouncementController::class, 'archive'])
                ->middleware('can:archive,announcement')
                ->name('archive');
        });

    // Clearance Module
    Route::prefix('student-services/clearance')
        ->name('clearance.')
        ->group(function () {
            // Admin Routes
            Route::middleware('can:clearance-update.view')->group(function () {
                Route::get('/updates', [ClearanceUpdateController::class, 'index'])->name('updates.index');
                Route::post('/updates', [ClearanceUpdateController::class, 'store'])->name('updates.store');
                Route::get('/updates/{update}', [ClearanceUpdateController::class, 'show'])->name('updates.show');
                Route::patch('/updates/{update}', [ClearanceUpdateController::class, 'update'])->name('updates.update');
                Route::post('/updates/{update}/publish', [ClearanceUpdateController::class, 'publish'])->name('updates.publish');
                Route::post('/updates/{update}/close', [ClearanceUpdateController::class, 'close'])->name('updates.close');
                Route::post('/updates/{update}/sync-offices', [ClearanceUpdateController::class, 'syncOffices'])->name('updates.sync-offices');
                Route::post('/updates/{update}/toggle-office', [ClearanceUpdateController::class, 'toggleOffice'])->name('updates.toggle-office');
                Route::delete('/updates/{update}/offices/{office}', [ClearanceUpdateController::class, 'removeOffice'])->name('updates.remove-office');
                Route::delete('/updates/{update}/applications/{application}', [ClearanceUpdateController::class, 'deleteApplication'])->name('updates.delete-application');
                Route::delete('/updates/{update}', [ClearanceUpdateController::class, 'destroy'])->name('updates.destroy');
                Route::patch('/updates/{update}/extend', [ClearanceUpdateController::class, 'extend'])->name('updates.extend');

                // Accountabilities
                Route::get('/accountabilities-center', [ClearanceAccountabilityController::class, 'center'])->name('accountabilities-center');
                Route::get('/updates/{update}/accountabilities', [ClearanceAccountabilityController::class, 'index'])->name('accountabilities.index');
                Route::get('/accountabilities/students', [ClearanceAccountabilityController::class, 'students'])->name('accountabilities.students.search');
                Route::post('/updates/{update}/accountabilities', [ClearanceAccountabilityController::class, 'store'])->name('accountabilities.store');
                Route::post('/updates/{update}/accountabilities/upload-preview', [ClearanceAccountabilityController::class, 'uploadPreview'])->name('accountabilities.upload-preview');
                Route::post('/updates/{update}/accountabilities/upload-save', [ClearanceAccountabilityController::class, 'uploadSave'])->name('accountabilities.upload-save');
                Route::post('/accountabilities/{accountability}/resolve', [ClearanceAccountabilityController::class, 'resolve'])->name('accountabilities.resolve');
                Route::post('/accountabilities/{accountability}/waive', [ClearanceAccountabilityController::class, 'waive'])->name('accountabilities.waive');
                Route::post('/accountabilities/{accountability}/reset', [ClearanceAccountabilityController::class, 'reset'])->name('accountabilities.reset');
                Route::patch('/accountabilities/{accountability}', [ClearanceAccountabilityController::class, 'update'])->name('accountabilities.update');
                Route::delete('/accountabilities/{accountability}', [ClearanceAccountabilityController::class, 'destroy'])->name('accountabilities.destroy');
            });

            // Student Routes
            Route::get('/my-clearance', [StudentSemesterClearanceController::class, 'myClearance'])->name('my-clearance');
            Route::get('/my-clearance/{clearance}', [StudentSemesterClearanceController::class, 'show'])->name('show');
            Route::post('/updates/{update}/apply', [StudentSemesterClearanceController::class, 'apply'])->name('apply');
        });

    // FAQ Module
    Route::prefix('faqs')->name('faqs.')->group(function () {
        Route::get('/', [App\Http\Controllers\Faq\FaqPublicController::class, 'index'])->name('index');
        Route::get('/view/{faq}', [App\Http\Controllers\Faq\FaqPublicController::class, 'show'])->name('show');
        Route::post('/{faq}/feedback', [App\Http\Controllers\Faq\FaqFeedbackController::class, 'store'])->name('feedback');

        Route::prefix('manage')->name('manage.')->group(function () {
            Route::resource('categories', App\Http\Controllers\Faq\FaqCategoryController::class);
            Route::get('/analytics', [App\Http\Controllers\Faq\FaqAnalyticsController::class, 'index'])->name('analytics');
            Route::resource('faqs', App\Http\Controllers\Faq\FaqController::class);
        });
    });

    // Site Settings
    Route::prefix('admin/site-settings')
        ->name('site-settings.')
        ->group(function () {
            Route::resource('campuses', SiteCampusController::class);
            Route::prefix('campuses/{campus}')
                ->name('campuses.')
                ->group(function () {
                    Route::post('terms', [SiteAcademicTermController::class, 'store'])->name('terms.store');
                    Route::patch('terms/{term}', [SiteAcademicTermController::class, 'update'])->name('terms.update');
                    Route::patch('terms/{term}/activate', [SiteAcademicTermController::class, 'activate'])->name('terms.activate');
                    Route::delete('terms/{term}', [SiteAcademicTermController::class, 'destroy'])->name('terms.destroy');
                });

            // Placeholder routes for new tabs
            Route::get('evaluation', fn() => Inertia::render('SiteSettings/Placeholder', ['title' => 'Evaluation']))->name('evaluation');
            Route::get('ccd-cares', fn() => Inertia::render('SiteSettings/Placeholder', ['title' => 'CCD Cares']))->name('ccd-cares');
            
            Route::get('grade-viewing', [SiteGradeViewingController::class, 'index'])->name('grade-viewing.index');
            Route::post('grade-viewing', [SiteGradeViewingController::class, 'store'])->name('grade-viewing.store');
            Route::patch('grade-viewing/{rule}', [SiteGradeViewingController::class, 'update'])->name('grade-viewing.update');
            Route::delete('grade-viewing/{rule}', [SiteGradeViewingController::class, 'destroy'])->name('grade-viewing.destroy');
            Route::patch('grade-viewing/{rule}/toggle', [SiteGradeViewingController::class, 'toggle'])->name('grade-viewing.toggle');
            
            Route::get('sar', fn() => Inertia::render('SiteSettings/Placeholder', ['title' => 'SAR']))->name('sar');
        });
});

require __DIR__.'/settings.php';
