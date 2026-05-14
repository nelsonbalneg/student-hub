<?php

use App\Http\Controllers\Auth\SsoAuthenticatedSessionController;
use App\Http\Controllers\CurriculumController;
use App\Http\Controllers\DashboardController;
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
use App\Http\Controllers\FileVault\DossierDocumentController;
use App\Http\Controllers\FileVault\StudentDossierController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

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
    Route::get('curriculum', CurriculumController::class)
        ->middleware('can:curriculum.view')
        ->name('curriculum.index');
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

    // File Vault Dossier Module
    Route::prefix('student-services/file-vault')
        ->name('file-vault.')
        ->group(function () {
            Route::get('/dossiers', [StudentDossierController::class, 'index'])
                ->middleware('can:dossiers.view')
                ->name('dossiers.index');
            Route::post('/dossiers', [StudentDossierController::class, 'store'])
                ->middleware('can:dossiers.create')
                ->name('dossiers.store');
            Route::get('/dossiers/{studentDossier}', [StudentDossierController::class, 'show'])
                ->middleware('can:dossiers.view')
                ->name('dossiers.show');
            Route::patch('/dossiers/{studentDossier}', [StudentDossierController::class, 'update'])
                ->middleware('can:dossiers.update')
                ->name('dossiers.update');
            Route::patch('/dossiers/{studentDossier}/status', [StudentDossierController::class, 'updateStatus'])
                ->middleware('can:dossiers.transition')
                ->name('dossiers.status');
            Route::post('/dossiers/{studentDossier}/assign', [StudentDossierController::class, 'assign'])
                ->middleware('can:dossiers.assign')
                ->name('dossiers.assign');
            Route::post('/dossiers/{studentDossier}/archive', [StudentDossierController::class, 'archive'])
                ->middleware('can:dossiers.archive')
                ->name('dossiers.archive');
            Route::post('/dossiers/{studentDossier}/approve', [StudentDossierController::class, 'approve'])
                ->middleware('can:dossiers.approve')
                ->name('dossiers.approve');
            Route::get('/dossiers/{studentDossier}/audit-logs', [StudentDossierController::class, 'auditLogs'])
                ->middleware('can:dossiers.audit.view')
                ->name('dossiers.audit-logs');

            Route::post('/dossiers/{studentDossier}/documents', [DossierDocumentController::class, 'store'])
                ->middleware('can:dossiers.documents.upload')
                ->name('dossiers.documents.store');
            Route::patch('/documents/{document}/verify', [DossierDocumentController::class, 'verify'])
                ->middleware('can:dossiers.documents.verify')
                ->name('documents.verify');
            Route::post('/documents/{document}/retry-scan', [DossierDocumentController::class, 'retryScan'])
                ->middleware('can:dossiers.documents.verify')
                ->name('documents.retry-scan');
            Route::delete('/documents/{document}', [DossierDocumentController::class, 'destroy'])
                ->middleware('can:dossiers.documents.upload')
                ->name('documents.destroy');
            Route::get('/documents/{document}/download', [DossierDocumentController::class, 'download'])
                ->middleware('can:dossiers.download')
                ->name('documents.download');
        });
});

require __DIR__.'/settings.php';
