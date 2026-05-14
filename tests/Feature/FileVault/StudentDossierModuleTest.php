<?php

use App\Models\DossierAccessLog;
use App\Models\DossierDocument;
use App\Models\StudentDossier;
use App\Models\User;
use App\Jobs\ScanDossierDocumentForMalware;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function createDossierStaff(array $permissions = []): User
{
    $user = User::factory()->create();

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user->givePermissionTo($permissions);

    return $user;
}

test('authorized staff can create a student dossier with initial status history', function () {
    $staff = createDossierStaff(['dossiers.create']);
    $student = User::factory()->create(['student_no' => '2026-0001']);

    $response = $this
        ->actingAs($staff)
        ->postJson(route('file-vault.dossiers.store'), [
            'student_id' => $student->id,
            'transaction_type' => 'graduation-clearance',
            'priority' => 'normal',
        ]);

    $response->assertCreated()
        ->assertJsonPath('dossier.status', StudentDossier::STATUS_DRAFT);

    $dossierId = $response->json('dossier.id');

    $this->assertDatabaseHas('dossier_status_histories', [
        'student_dossier_id' => $dossierId,
        'to_status' => StudentDossier::STATUS_DRAFT,
    ]);
});

test('cannot transition to supervisor approval when required documents are missing', function () {
    $staff = createDossierStaff(['dossiers.transition']);

    $dossier = StudentDossier::query()->create([
        'student_id' => User::factory()->create()->id,
        'dossier_number' => 'DOS-2026-000001',
        'transaction_type' => 'graduation-clearance',
        'status' => StudentDossier::STATUS_ACTIVE,
        'priority' => StudentDossier::PRIORITY_NORMAL,
        'created_by' => $staff->id,
    ]);

    $response = $this
        ->actingAs($staff)
        ->patchJson(route('file-vault.dossiers.status', $dossier), [
            'status' => StudentDossier::STATUS_FOR_SUPERVISOR_APPROVAL,
            'remarks' => 'Ready for review',
        ]);

    $response->assertUnprocessable();
    expect($response->json('message'))->toContain('required documents');
});

test('uploading and verifying required document allows transition to supervisor approval', function () {
    $staff = createDossierStaff([
        'dossiers.transition',
        'dossiers.submit-review',
        'dossiers.documents.upload',
        'dossiers.documents.verify',
    ]);

    $student = User::factory()->create();

    $dossier = StudentDossier::query()->create([
        'student_id' => $student->id,
        'dossier_number' => 'DOS-2026-000002',
        'transaction_type' => 'graduation-clearance',
        'status' => StudentDossier::STATUS_ACTIVE,
        'priority' => StudentDossier::PRIORITY_NORMAL,
        'created_by' => $staff->id,
    ]);

    foreach (['transcript', 'clearance-form', 'diploma-request'] as $docType) {
        $uploadResponse = $this->actingAs($staff)->postJson(route('file-vault.dossiers.documents.store', $dossier), [
            'document_type' => $docType,
            'is_required' => true,
            'file' => UploadedFile::fake()->create($docType.'.pdf', 100, 'application/pdf'),
        ]);

        $uploadResponse->assertCreated();

        $documentId = $uploadResponse->json('document.id');

        $verifyResponse = $this->actingAs($staff)->patchJson(route('file-vault.documents.verify', $documentId), [
            'is_verified' => true,
        ]);

        $verifyResponse->assertOk();
    }

    $transitionResponse = $this->actingAs($staff)->patchJson(route('file-vault.dossiers.status', $dossier), [
        'status' => StudentDossier::STATUS_FOR_SUPERVISOR_APPROVAL,
        'remarks' => 'Checklist complete.',
    ]);

    $transitionResponse->assertOk();

    $this->assertDatabaseHas('student_dossiers', [
        'id' => $dossier->id,
        'status' => StudentDossier::STATUS_FOR_SUPERVISOR_APPROVAL,
    ]);

    expect(DossierAccessLog::query()->where('student_dossier_id', $dossier->id)->count())->toBeGreaterThan(0);
});

test('transition to supervisor approval requires submit-review permission', function () {
    $staff = createDossierStaff([
        'dossiers.transition',
        'dossiers.documents.upload',
        'dossiers.documents.verify',
    ]);

    $student = User::factory()->create();

    $dossier = StudentDossier::query()->create([
        'student_id' => $student->id,
        'dossier_number' => 'DOS-2026-000003',
        'transaction_type' => 'graduation-clearance',
        'status' => StudentDossier::STATUS_ACTIVE,
        'priority' => StudentDossier::PRIORITY_NORMAL,
        'created_by' => $staff->id,
    ]);

    foreach (['transcript', 'clearance-form', 'diploma-request'] as $docType) {
        $uploadResponse = $this->actingAs($staff)->postJson(route('file-vault.dossiers.documents.store', $dossier), [
            'document_type' => $docType,
            'is_required' => true,
            'file' => UploadedFile::fake()->create($docType.'.pdf', 100, 'application/pdf'),
        ]);
        $uploadResponse->assertCreated();
        $documentId = $uploadResponse->json('document.id');
        $this->actingAs($staff)->patchJson(route('file-vault.documents.verify', $documentId), [
            'is_verified' => true,
        ])->assertOk();
    }

    $response = $this->actingAs($staff)->patchJson(route('file-vault.dossiers.status', $dossier), [
        'status' => StudentDossier::STATUS_FOR_SUPERVISOR_APPROVAL,
        'remarks' => 'Ready for review.',
    ]);

    $response->assertForbidden();
});

test('release requires prior approval', function () {
    $staff = createDossierStaff([
        'dossiers.transition',
        'dossiers.approve',
        'dossiers.release',
        'dossiers.documents.upload',
        'dossiers.documents.verify',
    ]);

    $dossier = StudentDossier::query()->create([
        'student_id' => User::factory()->create()->id,
        'dossier_number' => 'DOS-2026-000005',
        'transaction_type' => 'graduation-clearance',
        'status' => StudentDossier::STATUS_FOR_SUPERVISOR_APPROVAL,
        'priority' => StudentDossier::PRIORITY_NORMAL,
        'current_owner_id' => $staff->id,
        'created_by' => $staff->id,
    ]);

    foreach (['transcript', 'clearance-form', 'diploma-request'] as $docType) {
        $uploadResponse = $this->actingAs($staff)->postJson(route('file-vault.dossiers.documents.store', $dossier), [
            'document_type' => $docType,
            'is_required' => true,
            'file' => UploadedFile::fake()->create($docType.'.pdf', 100, 'application/pdf'),
        ]);
        $uploadResponse->assertCreated();
        $documentId = $uploadResponse->json('document.id');
        $this->actingAs($staff)->patchJson(route('file-vault.documents.verify', $documentId), [
            'is_verified' => true,
        ])->assertOk();
    }

    $response = $this->actingAs($staff)->patchJson(route('file-vault.dossiers.status', $dossier), [
        'status' => StudentDossier::STATUS_RELEASED,
        'remarks' => 'Releasing approved dossier.',
        'recipient_or_requestor' => 'Registrar Office',
        'legal_basis' => 'Student request',
        'legitimate_interest' => 'Credential release',
    ]);

    $response->assertUnprocessable();
    expect($response->json('message'))->toContain('approved before release');
});

test('release requires remarks and disclosure metadata', function () {
    $staff = createDossierStaff([
        'dossiers.transition',
        'dossiers.approve',
        'dossiers.release',
    ]);

    $dossier = StudentDossier::query()->create([
        'student_id' => User::factory()->create()->id,
        'dossier_number' => 'DOS-2026-000004',
        'transaction_type' => 'graduation-clearance',
        'status' => StudentDossier::STATUS_FOR_SUPERVISOR_APPROVAL,
        'priority' => StudentDossier::PRIORITY_NORMAL,
        'current_owner_id' => $staff->id,
        'created_by' => $staff->id,
    ]);

    $response = $this->actingAs($staff)->patchJson(route('file-vault.dossiers.status', $dossier), [
        'status' => StudentDossier::STATUS_RELEASED,
        'remarks' => '',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['remarks', 'recipient_or_requestor', 'legal_basis', 'legitimate_interest']);
});

test('queue view filter my_queue returns only assigned dossiers', function () {
    $staff = createDossierStaff(['dossiers.view']);
    $other = User::factory()->create();
    $student = User::factory()->create();

    StudentDossier::query()->create([
        'student_id' => $student->id,
        'dossier_number' => 'DOS-2026-100001',
        'transaction_type' => 'graduation-clearance',
        'status' => StudentDossier::STATUS_ACTIVE,
        'priority' => StudentDossier::PRIORITY_NORMAL,
        'current_owner_id' => $staff->id,
        'created_by' => $staff->id,
    ]);

    StudentDossier::query()->create([
        'student_id' => $student->id,
        'dossier_number' => 'DOS-2026-100002',
        'transaction_type' => 'graduation-clearance',
        'status' => StudentDossier::STATUS_ACTIVE,
        'priority' => StudentDossier::PRIORITY_NORMAL,
        'current_owner_id' => $other->id,
        'created_by' => $staff->id,
    ]);

    $response = $this->actingAs($staff)->getJson(route('file-vault.dossiers.index', ['view' => 'my_queue']));
    $response->assertOk();
    expect($response->json('dossiers.data'))->toHaveCount(1);
});

test('queue view filter unassigned returns only dossiers without owner', function () {
    $staff = createDossierStaff(['dossiers.view']);
    $student = User::factory()->create();

    StudentDossier::query()->create([
        'student_id' => $student->id,
        'dossier_number' => 'DOS-2026-100003',
        'transaction_type' => 'graduation-clearance',
        'status' => StudentDossier::STATUS_ACTIVE,
        'priority' => StudentDossier::PRIORITY_NORMAL,
        'current_owner_id' => null,
        'created_by' => $staff->id,
    ]);

    StudentDossier::query()->create([
        'student_id' => $student->id,
        'dossier_number' => 'DOS-2026-100004',
        'transaction_type' => 'graduation-clearance',
        'status' => StudentDossier::STATUS_ACTIVE,
        'priority' => StudentDossier::PRIORITY_NORMAL,
        'current_owner_id' => $staff->id,
        'created_by' => $staff->id,
    ]);

    $response = $this->actingAs($staff)->getJson(route('file-vault.dossiers.index', ['view' => 'unassigned']));
    $response->assertOk();
    expect($response->json('dossiers.data'))->toHaveCount(1);
});

test('queue view filter overdue returns overdue open dossiers only', function () {
    $staff = createDossierStaff(['dossiers.view']);
    $student = User::factory()->create();

    StudentDossier::query()->create([
        'student_id' => $student->id,
        'dossier_number' => 'DOS-2026-100005',
        'transaction_type' => 'graduation-clearance',
        'status' => StudentDossier::STATUS_ACTIVE,
        'priority' => StudentDossier::PRIORITY_NORMAL,
        'current_owner_id' => $staff->id,
        'completion_due_at' => now()->subDay(),
        'created_by' => $staff->id,
    ]);

    StudentDossier::query()->create([
        'student_id' => $student->id,
        'dossier_number' => 'DOS-2026-100006',
        'transaction_type' => 'graduation-clearance',
        'status' => StudentDossier::STATUS_RELEASED,
        'priority' => StudentDossier::PRIORITY_NORMAL,
        'current_owner_id' => $staff->id,
        'completion_due_at' => now()->subDay(),
        'created_by' => $staff->id,
    ]);

    $response = $this->actingAs($staff)->getJson(route('file-vault.dossiers.index', ['view' => 'overdue']));
    $response->assertOk();
    expect($response->json('dossiers.data'))->toHaveCount(1);
});

test('audit logs filter by action returns matching events only', function () {
    $staff = createDossierStaff(['dossiers.view', 'dossiers.audit.view']);
    $student = User::factory()->create();

    $dossier = StudentDossier::query()->create([
        'student_id' => $student->id,
        'dossier_number' => 'DOS-2026-100007',
        'transaction_type' => 'graduation-clearance',
        'status' => StudentDossier::STATUS_ACTIVE,
        'priority' => StudentDossier::PRIORITY_NORMAL,
        'created_by' => $staff->id,
    ]);

    DossierAccessLog::query()->create([
        'student_dossier_id' => $dossier->id,
        'action' => 'assign',
        'actor_id' => $staff->id,
        'occurred_at' => now(),
    ]);

    DossierAccessLog::query()->create([
        'student_dossier_id' => $dossier->id,
        'action' => 'view',
        'actor_id' => $staff->id,
        'occurred_at' => now(),
    ]);

    $response = $this->actingAs($staff)->getJson(route('file-vault.dossiers.audit-logs', $dossier).'?action=assign');
    $response->assertOk();
    expect($response->json('logs.data'))->toHaveCount(1);
    expect($response->json('logs.data.0.action'))->toBe('assign');
});

test('audit logs can be filtered by actor and date range', function () {
    $staff = createDossierStaff(['dossiers.view', 'dossiers.audit.view']);
    $otherActor = User::factory()->create();
    $student = User::factory()->create();

    $dossier = StudentDossier::query()->create([
        'student_id' => $student->id,
        'dossier_number' => 'DOS-2026-100008',
        'transaction_type' => 'graduation-clearance',
        'status' => StudentDossier::STATUS_ACTIVE,
        'priority' => StudentDossier::PRIORITY_NORMAL,
        'created_by' => $staff->id,
    ]);

    DossierAccessLog::query()->create([
        'student_dossier_id' => $dossier->id,
        'action' => 'view',
        'actor_id' => $staff->id,
        'occurred_at' => now()->subDays(3),
    ]);

    DossierAccessLog::query()->create([
        'student_dossier_id' => $dossier->id,
        'action' => 'assign',
        'actor_id' => $staff->id,
        'occurred_at' => now()->subDay(),
    ]);

    DossierAccessLog::query()->create([
        'student_dossier_id' => $dossier->id,
        'action' => 'update',
        'actor_id' => $otherActor->id,
        'occurred_at' => now()->subDay(),
    ]);

    $response = $this->actingAs($staff)->getJson(route('file-vault.dossiers.audit-logs', $dossier).'?actor_id='.$staff->id.'&date_from='.now()->subDays(2)->toDateString().'&date_to='.now()->toDateString());
    $response->assertOk();
    expect($response->json('logs.data'))->toHaveCount(1);
    expect($response->json('logs.data.0.action'))->toBe('assign');
    expect($response->json('logs.data.0.actor_id'))->toBe($staff->id);
});

test('document cannot be verified while malware scan is not clean', function () {
    $staff = createDossierStaff(['dossiers.documents.verify']);

    $dossier = StudentDossier::query()->create([
        'student_id' => User::factory()->create()->id,
        'dossier_number' => 'DOS-2026-100010',
        'transaction_type' => 'graduation-clearance',
        'status' => StudentDossier::STATUS_ACTIVE,
        'priority' => StudentDossier::PRIORITY_NORMAL,
        'created_by' => $staff->id,
    ]);

    $document = DossierDocument::query()->create([
        'student_dossier_id' => $dossier->id,
        'document_type' => 'transcript',
        'version' => 1,
        'is_required' => true,
        'is_verified' => false,
        'file_path' => 'dossiers/fake.pdf',
        'original_filename' => 'fake.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1000,
        'checksum' => str_repeat('a', 64),
        'scan_status' => 'pending',
        'uploaded_by' => $staff->id,
    ]);

    $response = $this->actingAs($staff)->patchJson(route('file-vault.documents.verify', $document), [
        'is_verified' => true,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['is_verified']);
});

test('retry scan requeues document scan and resets scan metadata', function () {
    Queue::fake();
    $staff = createDossierStaff(['dossiers.documents.verify']);

    $dossier = StudentDossier::query()->create([
        'student_id' => User::factory()->create()->id,
        'dossier_number' => 'DOS-2026-100011',
        'transaction_type' => 'graduation-clearance',
        'status' => StudentDossier::STATUS_ACTIVE,
        'priority' => StudentDossier::PRIORITY_NORMAL,
        'created_by' => $staff->id,
    ]);

    $document = DossierDocument::query()->create([
        'student_dossier_id' => $dossier->id,
        'document_type' => 'transcript',
        'version' => 1,
        'is_required' => true,
        'is_verified' => false,
        'file_path' => 'dossiers/fake.pdf',
        'original_filename' => 'fake.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1000,
        'checksum' => str_repeat('b', 64),
        'scan_status' => 'failed',
        'scan_message' => 'Scanner timeout',
        'scanned_at' => now()->subMinute(),
        'uploaded_by' => $staff->id,
    ]);

    $response = $this->actingAs($staff)->postJson(route('file-vault.documents.retry-scan', $document));

    $response->assertOk()
        ->assertJsonPath('document.scan_status', 'pending');

    Queue::assertPushed(ScanDossierDocumentForMalware::class, fn (ScanDossierDocumentForMalware $job) => $job->documentId === $document->id);

    $this->assertDatabaseHas('dossier_documents', [
        'id' => $document->id,
        'scan_status' => 'pending',
        'scan_message' => 'Scan queued for retry.',
    ]);
});
