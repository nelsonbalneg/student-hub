# ARO Student Dossier Management Module Plan

## 1. Goal and Scope

### Goal
Enable ARO staff to centrally manage, track, and audit each student dossier in the file vault from intake to archival.

### Business Outcomes
- Faster retrieval and processing of student records.
- Clear dossier status visibility for ARO staff and authorized offices.
- Reduced lost or duplicate documents.
- Full audit trail for compliance and accountability.

### In Scope
- Dossier creation, indexing, and lifecycle tracking.
- Document upload and metadata management.
- Workflow statuses, assignments, and routing.
- Search, filters, and reporting dashboards.
- Role-based access and audit logging.

### Out of Scope (Phase 1)
- OCR extraction and AI document classification.
- External records exchange with third-party institutions.
- Public self-service upload portal for non-authenticated users.

## 2. Users and Roles

### Primary Users
- ARO Staff: Daily dossier operations.
- ARO Supervisor: Oversight, approval, workload balancing.
- Records Officer (Read/Request): Authorized cross-office viewing/requesting.

### Recommended Role Permissions
Follow your existing permission naming pattern (`module.action`).

- `dossiers.view`
- `dossiers.create`
- `dossiers.update`
- `dossiers.delete`
- `dossiers.submit-review`
- `dossiers.approve`
- `dossiers.archive`
- `dossiers.release`
- `dossiers.download`
- `dossiers.audit.view`
- `dossiers.manage-tags`

## 3. Core Data Model (Recommended)

### Main Entities
- `student_dossiers`
- `dossier_documents`
- `dossier_status_histories`
- `dossier_assignments`
- `dossier_requests`
- `dossier_notes`
- `dossier_tags` and `dossier_taggables`

### Key Fields

`student_dossiers`
- `id`
- `student_id` (FK to student reference)
- `dossier_number` (unique, human-readable)
- `status` (enum)
- `priority` (low, normal, high, urgent)
- `current_owner_id` (ARO user)
- `intake_date`
- `completion_due_at`
- `archived_at`
- `created_by`, `updated_by`
- timestamps

`dossier_documents`
- `id`
- `student_dossier_id`
- `document_type` (TOR, enrollment form, affidavit, etc.)
- `document_code` (optional standard code)
- `version`
- `is_required`
- `is_verified`
- `file_path` or media reference
- `mime_type`, `file_size`
- `checksum` (duplicate detection)
- `uploaded_by`
- timestamps

`dossier_status_histories`
- `id`
- `student_dossier_id`
- `from_status`
- `to_status`
- `remarks`
- `changed_by`
- `changed_at`

## 4. Recommended Dossier Lifecycle

Use explicit states to avoid ambiguity.

1. `Draft` - Dossier created, initial metadata being encoded.
2. `For Intake Review` - Ready for validation by assigned staff.
3. `Incomplete` - Missing required documents.
4. `Active` - Complete and currently being processed.
5. `For Supervisor Approval` - Pending sign-off for completion/release.
6. `Released` - Official copy or completion action released.
7. `Archived` - Closed and retained per policy.
8. `On Hold` - Blocked due to issue or pending clarification.

## 5. Feature Recommendations

### A. Dossier Registry and Indexing (Must Have)
- Create dossier from student lookup.
- Auto-generate dossier number (year + sequence).
- Encode metadata: program, year level, semester, record type, priority.
- Add tags for fast grouping (graduation, transfer, pending clearance).

### B. Document Vault Management (Must Have)
- Upload multiple files per dossier.
- Required document checklist template by transaction type.
- Document versioning and replacement history.
- File validation (type, max size, duplicate checksum warning).

### C. Workflow and Tasking (Must Have)
- Assign dossier to ARO staff.
- Move status with required remarks on critical transitions.
- SLA due dates and overdue flags.
- Internal notes and handoff comments.

### D. Search and Retrieval (Must Have)
- Global search by student number, name, dossier number.
- Advanced filters: status, priority, owner, date range, program.
- Saved views for frequent queue patterns.

### E. Audit, Security, and Compliance (Must Have)
- Audit trail for create/update/delete/status/download actions.
- Permission-guarded access by role.
- Download watermarking log reference (optional in Phase 1.5).
- Soft delete with restore window for accidental deletion.

### F. Request and Release Tracking (Should Have)
- Internal request queue from authorized offices.
- Track requester, reason, due date, and release channel.
- Release confirmation with timestamp and releasing staff.

### G. Dashboard and Reporting (Should Have)
- Queue counts by status and assignee.
- Overdue dossiers and aging buckets.
- Daily completed dossiers and average processing time.
- Missing document trends by transaction type.

### H. Future Enhancements (Nice to Have)
- OCR and metadata auto-fill.
- Auto-classification of document type.
- Email/SMS notifications for release completion.
- Retention policy automation and disposal scheduling.

## 6. Recommended Workflows

### Workflow 1: New Dossier Intake
1. ARO staff searches student and clicks Create Dossier.
2. System generates dossier number and sets status `Draft`.
3. Staff selects transaction type and required checklist template loads.
4. Staff uploads initial files and fills metadata.
5. Staff submits to `For Intake Review`.
6. Reviewer validates and moves to `Active` or `Incomplete` with remarks.

### Workflow 2: Missing Document Resolution
1. Dossier in `Incomplete` shows missing checklist items.
2. Staff adds requested files as new versions/documents.
3. Staff resubmits to `For Intake Review`.
4. Reviewer verifies and moves dossier to `Active`.

### Workflow 3: Supervisor Approval and Release
1. Staff marks processing done and submits to `For Supervisor Approval`.
2. Supervisor approves, rejects, or sets `On Hold` with reason.
3. On approval, dossier status becomes `Released`.
4. System logs release details and updates request ticket if linked.

### Workflow 4: Archival
1. Released dossiers older than policy threshold are listed in Archive Queue.
2. Staff performs archive action (manual in Phase 1).
3. Dossier becomes `Archived`; files remain read-only.
4. Any restore action requires elevated permission and is fully logged.

## 7. UI Pages (Inertia + Vue)

Recommended route prefix: `student-services/file-vault` to align with existing module patterns.

- `GET /student-services/file-vault/dossiers` - Dossier list and filters
- `GET /student-services/file-vault/dossiers/{dossier}` - Dossier detail
- `POST /student-services/file-vault/dossiers` - Create
- `PATCH /student-services/file-vault/dossiers/{dossier}` - Update metadata
- `PATCH /student-services/file-vault/dossiers/{dossier}/status` - Transition state
- `POST /student-services/file-vault/dossiers/{dossier}/documents` - Upload document
- `DELETE /student-services/file-vault/documents/{document}` - Remove document (soft)
- `POST /student-services/file-vault/dossiers/{dossier}/assign` - Assign owner
- `POST /student-services/file-vault/dossiers/{dossier}/archive` - Archive

## 8. Notifications and Escalations

- Notify assignee on new assignment.
- Notify supervisor when dossier enters `For Supervisor Approval`.
- Daily digest for overdue dossiers.
- Escalation rule: urgent dossiers overdue by 24 hours trigger supervisor alert.

## 9. Reporting KPIs

- Dossiers created per day/week/month.
- Average cycle time (Draft to Released).
- First-pass completeness rate (% without `Incomplete` return).
- Overdue backlog count by assignee.
- Release turnaround SLA compliance rate.

## 10. Implementation Plan (Phased)

### Phase 0: Discovery and Validation (3-5 days)
- Validate fields, statuses, and checklist templates with ARO team.
- Confirm retention and access policy requirements.
- Finalize role-permission matrix.

### Phase 1: MVP Build (2-3 sprints)
- Database schema and models.
- Dossier CRUD, status workflow, and assignment.
- Document upload and checklist validation.
- Basic search and filter.
- Audit logs and permission checks.

### Phase 2: Operational Enhancements (1-2 sprints)
- Request and release tracking.
- Dashboard analytics and export.
- Saved views and SLA alerts.

### Phase 3: Optimization (future)
- OCR, auto-tagging, retention automation, advanced integrations.

## 11. Testing Strategy (Pest)

### Feature Tests
- Create dossier with valid/invalid payload.
- Status transition rules and authorization checks.
- Required document checklist enforcement.
- Archive and restore permission coverage.

### Integration Tests
- File upload storage and metadata persistence.
- Assignment notification dispatch.
- Audit trail entries for sensitive actions.

### UI Tests
- Filter/search accuracy.
- Workflow buttons state per role and status.
- Overdue indicators and dashboard counters.

## 12. Risks and Mitigation

- Risk: inconsistent metadata encoding.
  - Mitigation: enforce required fields and controlled dropdown values.
- Risk: unauthorized access to sensitive files.
  - Mitigation: strict permission guards and signed file access URLs.
- Risk: backlog due to unclear ownership.
  - Mitigation: mandatory assignee and SLA tracking.
- Risk: resistance to workflow changes.
  - Mitigation: pilot with ARO champions and short training sessions.

## 13. Suggested Acceptance Criteria for MVP

- ARO staff can create and update a dossier linked to a student.
- Required checklist validation blocks completion when missing files exist.
- Dossier status transitions follow defined lifecycle and are auditable.
- Authorized users can search dossiers by key identifiers and status.
- Supervisor can approve, hold, or archive with reason logging.

## 14. Recommended Next Decisions

1. Confirm the final dossier status list and allowed transitions.
2. Approve required checklist templates per transaction type.
3. Approve permission matrix per role before implementation.
4. Decide storage strategy (local disk, S3, or hybrid).
5. Decide retention period for archived dossiers.
