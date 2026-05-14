# Phase 1 Implementation Plan: MVP Build (Aligned to Phase 0 Requirements Pack)

## 1. Phase Objective
Deliver a production-usable MVP for ARO dossier management with secure CRUD, controlled workflow transitions, document vault operations, assignment queues, and audit/disclosure accountability compliant with Phase 0 requirements.

## 2. Recommended Duration
- 2 to 3 sprints
- Assumption: 2-week sprint cadence

## 3. Entry Gates (Must Be Closed Before Sprint 1)
1. Legal regime priority finalized:
   - Philippines DPA-first, FERPA-compatible
   - FERPA-first, DPA-compatible
   - dual-policy by campus/jurisdiction
2. Storage target finalized:
   - Local private disk
   - S3 private bucket
   - Hybrid by environment
3. Retention trigger and retention period per transaction type approved.
4. Malware scanning provider decision finalized (or approved deferred hook).
5. ARO supervisor approved lifecycle transition matrix.
6. DPO/compliance approved privacy requirements and data subject rights hooks.

## 4. MVP Scope
### Must Deliver
- Dossier CRUD and metadata management based on Phase 0 data dictionary.
- Controlled lifecycle transitions using the approved state machine.
- Document upload/version/verification and required checklist enforcement.
- Assignment and ownership workflow.
- Basic search and filters.
- Permission-gated access and policy enforcement.
- Audit + disclosure access logs for sensitive actions.

### Deferred to Phase 2+
- Office request queue workflow (full requester operations).
- Advanced dashboards/saved views.
- OCR and retention/disposal automation.

## 5. Detailed Execution Plan by Sprint

### Sprint 1: Foundation and Compliance Baseline
1. Implement migrations and indexes:
   - `student_dossiers`
   - `dossier_documents`
   - `dossier_status_histories`
   - `dossier_assignments`
   - `dossier_notes`
   - `dossier_access_logs`
2. Add key indexes:
   - `dossier_number` unique
   - composite `status/priority/current_owner_id`
   - search indexes for student fields and created dates
   - checksum index for duplicate detection
3. Implement Eloquent models/relations and policies.
4. Seed and map permissions from Phase 0 matrix:
   - `dossiers.view`, `dossiers.create`, `dossiers.update`, `dossiers.assign`, `dossiers.transition`, `dossiers.submit-review`, `dossiers.approve`, `dossiers.release`, `dossiers.archive`, `dossiers.download`, `dossiers.documents.upload`, `dossiers.documents.verify`, `dossiers.audit.view`
5. Build CRUD endpoints and FormRequest validators.
6. Build initial Inertia/Vue pages:
   - Dossier List
   - Dossier Create
   - Dossier Detail shell
7. Produce operational compliance artifacts:
   - ROPA-aligned processing inventory draft
   - purpose limitation mapping by field/form

Exit Criteria:
- Dossier CRUD works with permission and policy checks.
- Schema matches Phase 0 data dictionary minimum.
- Compliance baseline artifacts drafted and reviewed.

### Sprint 2: Workflow and Secure Document Vault
1. Implement transition engine with allowed transitions only:
   - Draft -> For Intake Review
   - For Intake Review -> Active|Incomplete
   - Incomplete -> For Intake Review
   - Active -> For Supervisor Approval|On Hold
   - On Hold -> Active
   - For Supervisor Approval -> Released|On Hold
   - Released -> Archived
2. Enforce transition constraints:
   - required remarks for `Incomplete` and `On Hold`
   - checklist complete before `For Supervisor Approval`
   - supervisor permission + release remarks for `Released`
   - archive permission + retention condition check for `Archived`
3. Implement secure document upload pipeline:
   - extension/MIME allowlist
   - file signature check for supported types
   - generated storage filenames
   - private storage
   - size/rate limits
   - malware scanning hook (queued)
4. Implement document versioning, verification fields, and replacement behavior.
5. Add status timeline and checklist panels in dossier detail.
6. Implement signed download endpoint and download log entry creation.

Exit Criteria:
- Workflow engine blocks invalid transitions.
- Upload controls match security requirements.
- Sensitive actions produce audit/access log entries.

### Sprint 3: Assignment, Search, Audit Hardening, UAT
1. Implement assign/reassign endpoint and assignment history logging.
2. Add queue views and filters:
   - student number/name
   - dossier number
   - status
   - priority
   - owner
   - date range
3. Implement canonical audit event catalog at app layer:
   - create/update/status/assign/upload/verify/download/archive/disclose
4. Implement `dossier_access_logs` fields for disclosure/accountability:
   - action
   - actor_id
   - recipient_or_requestor
   - legal_basis
   - legitimate_interest
   - occurred_at
5. Add archive action with retention gate checks.
6. Execute full UAT script from Phase 0.
7. Run stabilization and defect closure.

Exit Criteria:
- End-to-end MVP workflow passes UAT.
- Access/disclosure logging is verifiable and complete.
- No critical authz or privacy defects remain open.

## 6. Backend Technical Plan

### Controllers
- `DossierController`
- `DossierWorkflowController`
- `DossierDocumentController`
- `DossierAssignmentController`
- `DossierAuditController` (read-only access to logs)

### Domain Services
- `DossierNumberGeneratorService`
- `DossierTransitionService`
- `DossierChecklistValidatorService`
- `DossierAuditLoggerService`
- `DossierAccessLoggerService`
- `DossierRetentionGateService`

### Validation and Policy Requirements
- FormRequest validation for all write actions.
- Policy checks on all dossier/document endpoints.
- No direct status mutations outside transition service.

## 7. Frontend Technical Plan (Inertia + Vue)

### Pages
- Dossier Index (pagination + filters)
- Dossier Detail tabs: Overview, Documents, History, Notes, Audit
- Create/Edit dialog pages

### Components
- Status badge and transition action panel
- Required checklist completion component
- Secure upload widget with error handling
- Assignment control panel
- Audit/access timeline viewer (permission-gated)

## 8. Data Integrity, Privacy, and Security Controls
- Soft deletes for dossiers/documents where applicable.
- Transaction-level locking for transition consistency.
- Immutable audit/access timestamps and actor IDs.
- Signed temporary URLs for file downloads.
- Least-privilege role mapping enforced.
- Data minimization and purpose limitation reflected in forms.
- Data subject rights handling hook points documented (access/correction/erasure requests).

## 9. QA and Testing Plan

### Feature Tests (Pest)
- CRUD happy/negative paths.
- Authorization and policy checks per permission.
- Lifecycle transition rules and constraints.
- Checklist enforcement before approval/release.
- Archive permission and retention gate checks.

### Integration Tests
- Upload validation + storage behavior.
- Checksum duplicate logic and version incrementing.
- Signed download behavior.
- Audit/access log persistence and field completeness.

### UAT Script (Required)
1. Create dossier for valid student with generated dossier number.
2. Upload partial docs and verify approval transition is blocked.
3. Complete checklist and move to `For Supervisor Approval`.
4. Supervisor releases with remarks.
5. Archive eligible released dossier.
6. Verify audit and access/disclosure logs.
7. Verify unauthorized role cannot download/release/archive.

## 10. Operational Readiness
- Permissions setup guide.
- Failed upload and scanning exception runbook.
- Privacy incident escalation contacts documented.
- Monitoring checklist for queue backlog, upload failures, and authz denials.

## 11. Phase 1 Exit Gate
- All Phase 0 MVP acceptance tests pass.
- Lifecycle and transition constraints are enforced in code and tests.
- Security controls meet upload/storage/auth requirements.
- Audit + disclosure/access logs are complete and reviewable.
- DPO/compliance and ARO supervisor sign-off recorded.
- Phase 2 backlog prioritized and approved.
