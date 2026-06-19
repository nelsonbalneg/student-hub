<?php

use App\Http\Controllers\Admin\ReferenceLookupController;
use App\Http\Controllers\Admin\RegistrarController;
use App\Http\Controllers\Admin\Reporting\PftResultController;
use App\Http\Controllers\AnnouncementCategoryController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\SsoAuthenticatedSessionController;
use App\Http\Controllers\CarbonFootprintController;
use App\Http\Controllers\ClassScheduleController;
use App\Http\Controllers\ClearanceAccountabilityController;
use App\Http\Controllers\ClearanceUpdateController;
use App\Http\Controllers\CurriculumController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\EvaluationController;
use App\Http\Controllers\Faq\FaqAnalyticsController;
use App\Http\Controllers\Faq\FaqCategoryController;
use App\Http\Controllers\Faq\FaqController;
use App\Http\Controllers\Faq\FaqFeedbackController;
use App\Http\Controllers\Faq\FaqPublicController;
use App\Http\Controllers\GradesController;
use App\Http\Controllers\InternetAccountController;
use App\Http\Controllers\LegalDocumentController;
use App\Http\Controllers\LegalPublicController;
use App\Http\Controllers\MyCarbonFootprintController;
use App\Http\Controllers\ReportingOverviewController;
use App\Http\Controllers\SiteSettings\EvaluationTemplateController;
use App\Http\Controllers\SiteSettings\PhysicalFitnessConfigurationController;
use App\Http\Controllers\SiteSettings\SiteAcademicTermController;
use App\Http\Controllers\SiteSettings\SiteBrandingController;
use App\Http\Controllers\SiteSettings\SiteCampusController;
use App\Http\Controllers\SiteSettings\SiteGradeViewingController;
use App\Http\Controllers\SiteSettings\SiteStudentProfileController;
use App\Http\Controllers\Society\SocietyAccreditationController;
use App\Http\Controllers\Society\SocietyAnnouncementController;
use App\Http\Controllers\Society\SocietyController;
use App\Http\Controllers\Society\SocietyDashboardController;
use App\Http\Controllers\Society\SocietyEventController;
use App\Http\Controllers\Society\SocietyMembershipController;
use App\Http\Controllers\Society\SocietyReportController;
use App\Http\Controllers\StudentPftResultController;
use App\Http\Controllers\StudentProfileController;
use App\Http\Controllers\StudentRecordsController;
use App\Http\Controllers\StudentSemesterClearanceController;
use App\Http\Controllers\SystemLogController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return redirect()->route('auth.sso.redirect');
})->name('home');

Route::get('legal/{type}', [LegalPublicController::class, 'show'])
    ->whereIn('type', ['terms', 'cookie_policy', 'privacy_policy'])
    ->name('legal.public.show');

Route::middleware('guest')->group(function () {
    Route::get('auth/sso/redirect', [SsoAuthenticatedSessionController::class, 'redirect'])
        ->name('auth.sso.redirect');

    Route::get('auth/sso/callback', [SsoAuthenticatedSessionController::class, 'callback'])
        ->name('auth.sso.callback');

    Route::get('auth/callback', [SsoAuthenticatedSessionController::class, 'callback'])
        ->name('auth.callback');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('legal/accept-terms', [LegalPublicController::class, 'acceptTerms'])
        ->name('legal.accept-terms');
});

Route::middleware(['auth', 'verified', 'terms.accepted'])->group(function () {
    Route::get('dashboard', DashboardController::class)
        ->middleware('can:dashboard.view')
        ->name('dashboard');

    Route::prefix('system/logs')
        ->name('system.logs.')
        ->middleware('role_or_permission:Super Admin|System Administrator|system.logs.view')
        ->group(function () {
            Route::get('/', [SystemLogController::class, 'index'])->name('index');
            Route::get('/download', [SystemLogController::class, 'download'])
                ->middleware('role_or_permission:Super Admin|System Administrator|system.logs.download')
                ->name('download');
            Route::get('/export', [SystemLogController::class, 'export'])
                ->middleware('role_or_permission:Super Admin|System Administrator|system.logs.export|system.logs.download')
                ->name('export');
            Route::delete('/clear', [SystemLogController::class, 'clear'])
                ->middleware('role_or_permission:Super Admin|system.logs.clear')
                ->name('clear');
        });

    Route::get('grades', GradesController::class)
        ->middleware('can:grades.view')
        ->name('grades.index');
    Route::post('grades/evaluation/submit', [GradesController::class, 'submitEvaluation'])
        ->middleware('can:grades.view')
        ->name('grades.evaluation.submit');
    Route::get('curriculum', CurriculumController::class)
        ->middleware('can:curriculum.view')
        ->name('curriculum.index');
    Route::get('academic/class-schedule', ClassScheduleController::class)
        ->middleware('role_or_permission:Student|Super Admin|view class schedule')
        ->name('academic.class-schedule.index');
    Route::get('academic/cor/download', [ClassScheduleController::class, 'downloadCOR'])
        ->middleware('role_or_permission:Student|Super Admin|download cor')
        ->name('academic.cor.download');
    Route::get('student-academic-registration', fn () => Inertia::render('Enrollment/StudentAcademicRegistration'))
        ->name('enrollment.student-academic-registration');
    Route::get('student-academic-registration/confirm', fn () => redirect()->route('enrollment.student-academic-registration'));
    Route::post('student-academic-registration/confirm', [EnrollmentController::class, 'submitConfirmation'])
        ->name('enrollment.student-academic-registration.confirm');
    Route::get('student-academic-registration/status', [EnrollmentController::class, 'status'])
        ->name('enrollment.student-academic-registration.status');
    Route::get('student-profile', StudentProfileController::class)
        ->middleware('can:student-profile.view')
        ->name('student-profile.index');
    Route::patch('student-profile', [StudentProfileController::class, 'update'])
        ->middleware('can:student-profile.view')
        ->name('student-profile.update');
    Route::get('student-profile/physical-fitness/analytics', [StudentPftResultController::class, 'analytics'])
        ->middleware(['can:student-profile.view', 'can:pft.view'])
        ->name('student-profile.physical-fitness.analytics');
    Route::post('student-profile/physical-fitness/{testType}', [StudentPftResultController::class, 'store'])
        ->middleware('can:pft.submit')
        ->name('student-profile.physical-fitness.store');
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
    Route::patch('internet-accounts/{internetAccount}', [InternetAccountController::class, 'update'])
        ->middleware('can:internet-accounts.manage')
        ->name('internet-accounts.update');
    Route::delete('internet-accounts/{internetAccount}', [InternetAccountController::class, 'destroy'])
        ->middleware('can:internet-accounts.delete')
        ->name('internet-accounts.destroy');

    Route::get('my-carbon-footprint', [MyCarbonFootprintController::class, 'index'])
        ->middleware('can:reporting.carbon_footprint.user_view')
        ->name('reporting.my-carbon-footprint');

    Route::prefix('admin/reporting')
        ->name('reporting.')
        ->group(function () {
            Route::get('/', fn () => redirect()->route('reporting.overview.index'))
                ->middleware('can:reporting.view')
                ->name('index');
            Route::get('/overview', [ReportingOverviewController::class, 'index'])
                ->middleware('can:reporting.overview.view')
                ->name('overview.index');
            Route::get('/audit-logs', [AuditLogController::class, 'index'])
                ->middleware('can:reporting.audit_logs.view')
                ->name('audit-logs.index');
            Route::get('/carbon-footprint', [CarbonFootprintController::class, 'index'])
                ->middleware('can:reporting.carbon_footprint.view')
                ->name('carbon-footprint.index');
        });

    Route::prefix('admin/reporting/pft-result')
        ->name('admin.reporting.pft-result.')
        ->controller(PftResultController::class)
        ->middleware('can:reporting.pft_result.view')
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/data', 'data')->name('data');
            Route::get('/analytics', 'analyticsPage')->name('analytics');
            Route::get('/analytics/data', 'analyticsData')->name('analytics-data');
            Route::get('/analytics/drilldown', 'analyticsDrilldown')->name('analytics-drilldown');
            Route::get('/analytics/export/drilldown-excel', 'exportDrilldownExcel')
                ->middleware('can:reporting.export')
                ->name('export-drilldown-excel');
            Route::prefix('filter')->name('filter.')->group(function () {
                Route::get('/campuses', 'filterCampuses')->name('campuses');
                Route::get('/terms', 'filterTerms')->name('terms');
                Route::get('/colleges', 'filterColleges')->name('colleges');
                Route::get('/sections', 'filterSections')->name('sections');
                Route::get('/pft-test-types', 'filterPftTestTypes')->name('pft-test-types');
            });
            Route::get('/export/excel', 'exportExcel')
                ->middleware('can:reporting.export')
                ->name('export-excel');
            Route::get('/export/pdf', 'exportPdf')
                ->middleware('can:reporting.export')
                ->name('export-pdf');
            Route::get('/export/analytics-pdf', 'exportAnalyticsPdf')
                ->middleware('can:reporting.export')
                ->name('export-analytics-pdf');
        });

    Route::prefix('admin/registrar')
        ->name('admin.registrar.')
        ->controller(RegistrarController::class)
        ->middleware('can:registrar.view')
        ->group(function () {
            Route::get('student-profile', 'studentProfile')
                ->middleware('can:registrar.student-profile.view')
                ->name('student-profile.index');
            Route::get('report-of-grades', 'reportOfGrades')
                ->middleware('can:registrar.report-of-grades.view')
                ->name('report-of-grades.index');
            Route::get('report-of-grades/curriculum/print', 'printCurriculum')
                ->middleware('can:registrar.report-of-grades.view')
                ->name('report-of-grades.curriculum.print');
            Route::post('report-of-grades/search', 'searchReportOfGrades')
                ->middleware('can:registrar.report-of-grades.view')
                ->name('report-of-grades.search');
            Route::get('tag-graduating-student', 'tagGraduatingStudent')
                ->middleware('can:registrar.tag-graduating-student.view')
                ->name('tag-graduating-student.index');
        });

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

    Route::prefix('settings/legal')
        ->name('legal.')
        ->controller(LegalDocumentController::class)
        ->group(function () {
            Route::get('/', 'index')
                ->middleware('can:legal.view')
                ->name('index');
            Route::get('/create', 'create')
                ->middleware('can:legal.create')
                ->name('create');
            Route::post('/', 'store')
                ->middleware('can:legal.create')
                ->name('store');
            Route::get('/{legalDocument}', 'show')
                ->middleware('can:legal.view')
                ->name('show');
            Route::get('/{legalDocument}/edit', 'edit')
                ->middleware('can:legal.edit')
                ->name('edit');
            Route::put('/{legalDocument}', 'update')
                ->middleware('can:legal.edit')
                ->name('update');
            Route::delete('/{legalDocument}', 'destroy')
                ->middleware('can:legal.delete')
                ->name('destroy');
            Route::patch('/{legalDocument}/activate', 'activate')
                ->middleware('can:legal.activate')
                ->name('activate');
            Route::patch('/{legalDocument}/deactivate', 'deactivate')
                ->middleware('can:legal.activate')
                ->name('deactivate');
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
        Route::get('/', [FaqPublicController::class, 'index'])->name('index');
        Route::get('/view/{faq}', [FaqPublicController::class, 'show'])->name('show');
        Route::post('/{faq}/feedback', [FaqFeedbackController::class, 'store'])->name('feedback');

        Route::prefix('manage')->name('manage.')->group(function () {
            Route::resource('categories', FaqCategoryController::class);
            Route::get('/analytics', [FaqAnalyticsController::class, 'index'])->name('analytics');
            Route::resource('faqs', FaqController::class);
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

            Route::prefix('evaluation')
                ->name('evaluation.')
                ->controller(EvaluationTemplateController::class)
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('templates', 'storeTemplate')->name('templates.store');
                    Route::post('templates/{template}/clone', 'cloneTemplate')->name('templates.clone');
                    Route::patch('templates/{template}', 'updateTemplate')->name('templates.update');
                    Route::delete('templates/{template}', 'destroyTemplate')->name('templates.destroy');
                    Route::post('categories', 'storeCategory')->name('categories.store');
                    Route::patch('categories/reorder', 'reorderCategories')->name('categories.reorder');
                    Route::patch('categories/{category}', 'updateCategory')->name('categories.update');
                    Route::delete('categories/{category}', 'destroyCategory')->name('categories.destroy');
                    Route::post('statements', 'storeStatement')->name('statements.store');
                    Route::patch('statements/reorder', 'reorderStatements')->name('statements.reorder');
                    Route::patch('statements/{statement}', 'updateStatement')->name('statements.update');
                    Route::delete('statements/{statement}', 'destroyStatement')->name('statements.destroy');
                    Route::post('scales', 'storeScale')->name('scales.store');
                    Route::patch('scales/reorder', 'reorderScales')->name('scales.reorder');
                    Route::patch('scales/{scale}', 'updateScale')->name('scales.update');
                    Route::delete('scales/{scale}', 'destroyScale')->name('scales.destroy');
                    Route::post('choices', 'storeChoice')->name('choices.store');
                    Route::patch('choices/reorder', 'reorderChoices')->name('choices.reorder');
                    Route::patch('choices/{choice}', 'updateChoice')->name('choices.update');
                    Route::delete('choices/{choice}', 'destroyChoice')->name('choices.destroy');
                });

            // Placeholder routes for new tabs
            Route::get('ccd-cares', fn () => Inertia::render('SiteSettings/Placeholder', ['title' => 'CCD Cares']))->name('ccd-cares');

            Route::get('grade-viewing', [SiteGradeViewingController::class, 'index'])->name('grade-viewing.index');
            Route::post('grade-viewing', [SiteGradeViewingController::class, 'store'])->name('grade-viewing.store');
            Route::patch('grade-viewing/{rule}', [SiteGradeViewingController::class, 'update'])->name('grade-viewing.update');
            Route::delete('grade-viewing/{rule}', [SiteGradeViewingController::class, 'destroy'])->name('grade-viewing.destroy');
            Route::patch('grade-viewing/{rule}/toggle', [SiteGradeViewingController::class, 'toggle'])->name('grade-viewing.toggle');

            Route::get('student-profile', [SiteStudentProfileController::class, 'index'])->name('student-profile.index');
            Route::get('student-profile/students/search', [SiteStudentProfileController::class, 'searchStudents'])
                ->name('student-profile.students.search');
            Route::post('student-profile/awards', [SiteStudentProfileController::class, 'storeAward'])->name('student-profile.awards.store');
            Route::patch('student-profile/awards/{achievement}', [SiteStudentProfileController::class, 'updateAward'])->name('student-profile.awards.update');
            Route::delete('student-profile/awards/{achievement}', [SiteStudentProfileController::class, 'destroyAward'])->name('student-profile.awards.destroy');
            Route::post('student-profile/trainings', [SiteStudentProfileController::class, 'storeTraining'])->name('student-profile.trainings.store');
            Route::patch('student-profile/trainings/{training}', [SiteStudentProfileController::class, 'updateTraining'])->name('student-profile.trainings.update');
            Route::delete('student-profile/trainings/{training}', [SiteStudentProfileController::class, 'destroyTraining'])->name('student-profile.trainings.destroy');
            Route::patch('student-profile/physical-fitness-permission', [SiteStudentProfileController::class, 'updatePhysicalFitnessPermission'])
                ->middleware('can:pft.permission.manage')
                ->name('student-profile.physical-fitness-permission.update');

            Route::prefix('physical-fitness/configuration')
                ->name('physical-fitness.configuration.')
                ->controller(PhysicalFitnessConfigurationController::class)
                ->middleware('can:pft.configuration.view')
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('components', 'storeComponent')->middleware('can:pft.configuration.create')->name('components.store');
                    Route::patch('components/{component}', 'updateComponent')->middleware('can:pft.configuration.update')->name('components.update');
                    Route::delete('components/{component}', 'destroyComponent')->middleware('can:pft.configuration.delete')->name('components.destroy');
                    Route::post('categories', 'storeCategory')->middleware('can:pft.configuration.create')->name('categories.store');
                    Route::patch('categories/{category}', 'updateCategory')->middleware('can:pft.configuration.update')->name('categories.update');
                    Route::delete('categories/{category}', 'destroyCategory')->middleware('can:pft.configuration.delete')->name('categories.destroy');
                    Route::post('test-types', 'storeTestType')->middleware('can:pft.configuration.create')->name('test-types.store');
                    Route::patch('test-types/{testType}', 'updateTestType')->middleware('can:pft.configuration.update')->name('test-types.update');
                    Route::delete('test-types/{testType}', 'destroyTestType')->middleware('can:pft.configuration.delete')->name('test-types.destroy');
                    Route::post('fields', 'storeConfiguration')->middleware('can:pft.configuration.create')->name('fields.store');
                    Route::patch('fields/{configuration}', 'updateConfiguration')->middleware('can:pft.configuration.update')->name('fields.update');
                    Route::delete('fields/{configuration}', 'destroyConfiguration')->middleware('can:pft.configuration.delete')->name('fields.destroy');
                    Route::post('interpretation-rules', 'storeInterpretationRule')->middleware('can:pft.configuration.create')->name('interpretation-rules.store');
                    Route::patch('interpretation-rules/{rule}', 'updateInterpretationRule')->middleware('can:pft.configuration.update')->name('interpretation-rules.update');
                    Route::delete('interpretation-rules/{rule}', 'destroyInterpretationRule')->middleware('can:pft.configuration.delete')->name('interpretation-rules.destroy');
                });

            Route::get('sar', fn () => Inertia::render('SiteSettings/Placeholder', ['title' => 'SAR']))->name('sar');

            Route::get('site-settings', [SiteBrandingController::class, 'index'])->name('branding.index');
            Route::post('site-settings', [SiteBrandingController::class, 'update'])->name('branding.update');
        });

    // Society Module
    Route::prefix('societies')
        ->name('societies.')
        ->middleware('can:society.view')
        ->group(function () {
            // Student/Public Routes
            Route::get('/', [SocietyController::class, 'index'])->name('index');
            Route::get('/registration', [SocietyController::class, 'registration'])->name('registration');
            Route::post('/registration', [SocietyController::class, 'store'])
                ->middleware('can:society.create')
                ->name('store');
            Route::delete('/{society}', [SocietyController::class, 'destroy'])
                ->middleware('can:society.delete')
                ->name('destroy');
            Route::get('/my-society', [SocietyController::class, 'mySociety'])
                ->middleware('can:society.view')
                ->name('my-society');
            Route::get('/my-societies', [SocietyMembershipController::class, 'mySocieties'])->name('my-societies');
            Route::get('/events', [SocietyEventController::class, 'publicIndex'])->name('events.index');
            Route::get('/announcements', [SocietyAnnouncementController::class, 'publicIndex'])->name('announcements.index');
            Route::get('/students/search', [SocietyController::class, 'searchStudents'])
                ->middleware('can:society.manage_officers')
                ->name('students.search');

            Route::get('/{society}', [SocietyController::class, 'show'])->name('show');
            Route::post('/{society}/join', [SocietyMembershipController::class, 'join'])->name('join');

            // Society Management (Officers/Advisers)
            Route::prefix('manage/{society}')
                ->name('manage.')
                ->scopeBindings()
                ->group(function () {
                    Route::get('/dashboard', [SocietyDashboardController::class, 'societyIndex'])->name('dashboard');
                    Route::get('/profile', [SocietyController::class, 'manageProfile'])->name('profile');
                    Route::patch('/profile', [SocietyController::class, 'update'])
                        ->middleware('can:society.update')
                        ->name('profile.update');
                    Route::patch('/publish', [SocietyController::class, 'publish'])
                        ->middleware('can:society.update')
                        ->name('publish');
                    Route::get('/accreditation', [SocietyAccreditationController::class, 'index'])->name('accreditation.index');
                    Route::post('/accreditation', [SocietyAccreditationController::class, 'store'])
                        ->middleware('can:society.apply_accreditation')
                        ->name('accreditation.store');
                    Route::post('/accreditation/{accreditationRequest}/submit', [SocietyAccreditationController::class, 'submit'])
                        ->middleware('can:society.apply_accreditation')
                        ->name('accreditation.submit');
                    Route::post('/accreditation/{accreditationRequest}/requirements', [SocietyAccreditationController::class, 'uploadRequirement'])
                        ->middleware('can:society.submit_requirements')
                        ->name('accreditation.requirements.store');
                    Route::delete('/accreditation/{accreditationRequest}/requirements/{submission}', [SocietyAccreditationController::class, 'destroyRequirement'])
                        ->middleware('can:society.submit_requirements')
                        ->name('accreditation.requirements.destroy');
                    Route::get('/officers', [SocietyController::class, 'manageOfficers'])->name('officers.index');
                    Route::post('/officers', [SocietyController::class, 'storeOfficer'])
                        ->middleware('can:society.manage_officers')
                        ->name('officers.store');
                    Route::patch('/officers/{officer}', [SocietyController::class, 'updateOfficer'])
                        ->middleware('can:society.manage_officers')
                        ->name('officers.update');
                    Route::delete('/officers/{officer}', [SocietyController::class, 'destroyOfficer'])
                        ->middleware('can:society.manage_officers')
                        ->name('officers.destroy');
                    Route::get('/advisers', [SocietyController::class, 'manageAdvisers'])->name('advisers.index');
                    Route::post('/advisers', [SocietyController::class, 'storeAdviser'])
                        ->middleware('can:society.manage_advisers')
                        ->name('advisers.store');
                    Route::patch('/advisers/{adviser}', [SocietyController::class, 'updateAdviser'])
                        ->middleware('can:society.manage_advisers')
                        ->name('advisers.update');
                    Route::delete('/advisers/{adviser}', [SocietyController::class, 'destroyAdviser'])
                        ->middleware('can:society.manage_advisers')
                        ->name('advisers.destroy');
                    Route::get('/members', [SocietyMembershipController::class, 'index'])->name('members.index');
                    Route::get('/members-roster', [SocietyController::class, 'manageMembers'])->name('members.roster');
                    Route::post('/members-roster', [SocietyController::class, 'storeMember'])
                        ->middleware('can:society.manage_members')
                        ->name('members.store');
                    Route::get('/bylaws', [SocietyController::class, 'manageBylaws'])->name('bylaws.index');
                    Route::get('/announcements', [SocietyAnnouncementController::class, 'index'])->name('announcements.index');
                    Route::get('/events', [SocietyEventController::class, 'index'])->name('events.index');
                    Route::get('/attendance', [SocietyEventController::class, 'attendanceIndex'])->name('attendance.index');
                });
        });

    Route::prefix('admin/societies')
        ->name('admin.societies.')
        ->middleware('role:Super Admin|OSA Admin')
        ->group(function () {
            Route::get('/dashboard', [SocietyDashboardController::class, 'index'])->name('dashboard');
            Route::get('/applications', [SocietyAccreditationController::class, 'adminIndex'])->name('applications.index');
            Route::get('/pending-review', [SocietyAccreditationController::class, 'pendingReview'])->name('pending-review.index');
            Route::get('/returned', [SocietyAccreditationController::class, 'returned'])->name('returned.index');
            Route::get('/approved', [SocietyAccreditationController::class, 'approved'])->name('approved.index');
            Route::get('/rejected', [SocietyAccreditationController::class, 'rejected'])->name('rejected.index');
            Route::get('/requirements', [SocietyAccreditationController::class, 'manageRequirements'])
                ->middleware('can:society.manage_requirements')
                ->name('requirements.index');
            Route::post('/requirements', [SocietyAccreditationController::class, 'storeRequirement'])
                ->middleware('can:society.manage_requirements')
                ->name('requirements.store');
            Route::get('/applications/{accreditationRequest}/review', [SocietyAccreditationController::class, 'reviewPage'])->name('applications.review');
            Route::patch('/applications/{accreditationRequest}/review', [SocietyAccreditationController::class, 'review'])->name('applications.review.update');
            Route::patch('/applications/{accreditationRequest}/requirements/{submission}', [SocietyAccreditationController::class, 'reviewRequirement'])->name('applications.requirements.review');
            Route::get('/applications/{accreditationRequest}/print/{type?}', [SocietyAccreditationController::class, 'print'])->name('applications.print');
            Route::get('/accredited', [SocietyController::class, 'adminIndex'])->name('accredited.index');
            Route::get('/reports', [SocietyReportController::class, 'index'])->name('reports.index');

            Route::prefix('{society}')->group(function () {
                Route::get('/profile', [SocietyController::class, 'adminShow'])->name('profile');
                Route::get('/officers', [SocietyController::class, 'adminOfficers'])->name('officers');
                Route::get('/members', [SocietyMembershipController::class, 'adminIndex'])->name('members');
                Route::get('/bylaws', [SocietyController::class, 'adminBylaws'])->name('bylaws');
                Route::get('/announcements', [SocietyAnnouncementController::class, 'adminIndex'])->name('announcements');
                Route::get('/events', [SocietyEventController::class, 'adminIndex'])->name('events');
                Route::get('/attendance', [SocietyEventController::class, 'adminAttendance'])->name('attendance');
                Route::patch('/reopen', [SocietyController::class, 'reopen'])->name('reopen');
            });
        });
});

require __DIR__.'/settings.php';
