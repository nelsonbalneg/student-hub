# Phase 0 Requirements Pack: Student Dossier Management (File Vault)

## Document Purpose
This document completes Phase 0 by combining:
1. Research-backed best practices for student dossier management.
2. Implementable requirements artifacts for Phase 1 kickoff.

Related planning baseline:
- `docs/aro-dossier-module-plan.md`
- `docs/implementation_plans/phase-0-discovery-and-validation.md`

## 1. Best-Practice Research Summary (What to Adopt)

### A. Records Governance and Lifecycle
Best practice:
- Treat dossiers as formal records with defined lifecycle, metadata, controls, and retention/disposition rules.

Why:
- ISO 15489 emphasizes records controls, metadata, responsibilities, monitoring, and processes for creation/capture/management across formats.

What to implement in this module:
- Formal dossier lifecycle states and transition controls.
- Required metadata model and ownership fields.
- Records schedule fields (retention trigger, retention class, archive/disposal status).

### B. Student Privacy and Disclosure Accountability
Best practice:
- Enforce strict access controls and disclosure recordkeeping for personally identifiable student records.

Why:
- FERPA requires recordkeeping for many requests/disclosures and limits redisclosure.

What to implement in this module:
- Access by role + legitimate educational interest.
- Disclosure/request log with requester, legal basis, recipient, purpose, and timestamp.
- Redisclosure constraints and audit-ready history.

### C. Data Privacy-by-Design (Philippines DPA Alignment)
Best practice:
- Build privacy-by-design/default, maintain processing records, and enforce organizational/physical/technical safeguards.

Why:
- NPC IRR (RA 10173) requires organizational, physical, and technical security measures and records of processing activities.

What to implement in this module:
- Data minimization and purpose limitation at form level.
- Records of processing activities (ROPA-aligned fields and process docs).
- Defined DPO/compliance accountability path.
- Data subject rights workflow hooks (access/correction/erasure handling with legal exceptions).

### D. Secure File Management
Best practice:
- Harden upload pipeline with defense-in-depth validation and storage controls.

Why:
- OWASP file upload guidance recommends extension/content checks, permission controls, storage isolation, and size limits.

What to implement in this module:
- Allowlist MIME/extensions, server-side signature checks, safe filename strategy.
- Private storage, signed download URLs, strict authz checks.
- Upload limits, malware scan hook (if available), and checksum for duplicate detection.

### E. Audit and Security Controls
Best practice:
- Define audit events explicitly and review/update logging policy over time.

Why:
- NIST SP 800-53 (AU/AC families) highlights event logging scope, access enforcement, and periodic updates.

What to implement in this module:
- Canonical audit event catalog.
- Tamper-evident audit entries (append-only semantics at app layer).
- Periodic audit policy review cadence.

## 2. Phase 0 Requirements Artifacts

## 2.1 Approved Scope Statement (MVP)
In scope for Phase 1:
- Dossier CRUD with controlled statuses.
- Document vault upload/version/checklist validation.
- Assignment workflow and queue filtering.
- Permission-based access.
- Audit logs for sensitive actions.

Out of scope for Phase 1:
- OCR/classification automation.
- External institution integrations.
- Full retention disposal automation.

## 2.2 Stakeholder and Accountability Matrix
- Product Owner: Scope, acceptance, rollout decisions.
- ARO Supervisor: Workflow, status transitions, approvals.
- ARO Staff Representatives: Intake and checklist validation.
- DPO/Compliance: Privacy/legal controls and retention rules.
- Engineering Lead: Architecture and implementation feasibility.
- QA Lead: Test coverage and UAT gate ownership.

## 2.3 Lifecycle and Transition Rules (MVP)

Statuses:
- Draft
- For Intake Review
- Incomplete
- Active
- For Supervisor Approval
- Released
- Archived
- On Hold

Allowed transitions:
- Draft -> For Intake Review
- For Intake Review -> Active
- For Intake Review -> Incomplete
- Incomplete -> For Intake Review
- Active -> For Supervisor Approval
- Active -> On Hold
- On Hold -> Active
- For Supervisor Approval -> Released
- For Supervisor Approval -> On Hold
- Released -> Archived

Transition constraints:
- `For Supervisor Approval` requires 100% required checklist compliance.
- `Released` requires supervisor permission and release remarks.
- `Archived` requires archive permission and retention condition check.
- Any transition to `Incomplete` or `On Hold` requires remarks.

## 2.4 Required Data Dictionary (MVP Minimum)

`student_dossiers`
- id (UUID/bigint)
- student_id (indexed FK)
- dossier_number (unique indexed)
- transaction_type (indexed)
- status (indexed)
- priority (indexed)
- current_owner_id (indexed)
- intake_date
- completion_due_at
- release_at (nullable)
- archived_at (nullable)
- created_by, updated_by
- timestamps, soft deletes

`dossier_documents`
- id
- student_dossier_id (indexed FK)
- document_type (indexed)
- version (int)
- is_required (bool)
- is_verified (bool)
- file_path
- original_filename
- mime_type
- file_size
- checksum (indexed)
- uploaded_by
- verified_by (nullable)
- verified_at (nullable)
- timestamps, soft deletes

`dossier_status_histories`
- id
- student_dossier_id (indexed FK)
- from_status
- to_status
- remarks
- changed_by
- changed_at

`dossier_access_logs`
- id
- student_dossier_id
- action (view/download/disclose/request)
- actor_id
- recipient_or_requestor
- legal_basis
- legitimate_interest
- metadata_json
- occurred_at

## 2.5 Permissions Matrix (MVP)
- dossiers.view
- dossiers.create
- dossiers.update
- dossiers.assign
- dossiers.transition
- dossiers.submit-review
- dossiers.approve
- dossiers.release
- dossiers.archive
- dossiers.download
- dossiers.documents.upload
- dossiers.documents.verify
- dossiers.audit.view

Role mapping (initial):
- ARO Staff: view, create, update, assign, transition, submit-review, documents.upload, download
- ARO Supervisor: all ARO Staff + approve, release, archive
- Compliance Auditor (optional): view, audit.view
- Other Office Requester: limited view/request through request module only (Phase 2)

## 2.6 Privacy and Compliance Requirements (Must Pass Before Dev Sign-off)
- Personal data processing inventory documented (ROPA-aligned).
- Purpose limitation per form/field documented.
- Access model documented with least-privilege roles.
- Data subject request handling flow documented:
  - access request
  - correction/rectification
  - erasure/blocking (subject to legal retention exceptions)
- Incident and breach escalation contacts documented.
- Disclosure recordkeeping fields enforced at app level.

## 2.7 Security Requirements (MVP)
Upload and file handling:
- Allowlist extensions and MIME types.
- Verify file signature for supported types.
- Rename stored files to generated identifiers.
- Store files in non-public/private disk.
- Signed temporary links for downloads.
- Max upload size and rate limits.
- Antivirus scanning hook (queue-based, if scanner available).

Authorization and auditing:
- Policy checks on all dossier/document endpoints.
- Audit events (create/update/status/assign/upload/download/archive/disclose).
- Immutable timestamps and actor IDs on audit records.

## 2.8 API Contract Draft (Phase 1)
Route prefix: `/student-services/file-vault`

- GET `/dossiers`
- POST `/dossiers`
- GET `/dossiers/{dossier}`
- PATCH `/dossiers/{dossier}`
- PATCH `/dossiers/{dossier}/status`
- POST `/dossiers/{dossier}/assign`
- POST `/dossiers/{dossier}/documents`
- PATCH `/documents/{document}/verify`
- DELETE `/documents/{document}` (soft delete)
- POST `/dossiers/{dossier}/archive`
- GET `/dossiers/{dossier}/audit-logs`

Contract requirements:
- All write endpoints require FormRequest validation.
- All endpoints protected with permission/policy middleware.
- All status transitions executed via transition service, not direct model mutation.

## 2.9 Backlog for Phase 1 (Ready Stories)

Epic A: Dossier Foundation
- A1 Migration + model creation.
- A2 Dossier numbering service.
- A3 Dossier create/edit endpoints + validation.

Epic B: Workflow Controls
- B1 Transition engine + rules.
- B2 Status history timeline persistence.
- B3 Remarks enforcement.

Epic C: Document Vault
- C1 Secure upload pipeline.
- C2 Document versioning and verification.
- C3 Required checklist validator.

Epic D: Security and Audit
- D1 Permission seeding and policy wiring.
- D2 Audit event logging service.
- D3 Signed download endpoint.

Epic E: UI Delivery
- E1 Dossier index with filters.
- E2 Dossier detail (overview, docs, history).
- E3 Transition and assignment actions.

## 2.10 Definition of Ready (DoR)
A story is ready when:
- Acceptance criteria are testable.
- API payload and validation rules are defined.
- Permission behavior is defined.
- UX state/empty/error scenarios are specified.
- Dependencies are identified.

## 2.11 Definition of Done (DoD)
A story is done when:
- Code merged with tests passing.
- Policy and validation coverage included.
- Audit events implemented for sensitive actions.
- QA checklist passed including negative/unauthorized paths.
- Documentation updated.

## 2.12 UAT Acceptance Tests (Phase 0 Output for Phase 1 Use)
1. Create dossier for valid student and verify generated dossier number.
2. Upload partial documents and ensure transition to approval is blocked.
3. Complete required checklist and move to `For Supervisor Approval`.
4. Approve and release dossier with reason logging.
5. Archive eligible released dossier.
6. Verify audit trail entries for all above actions.
7. Verify unauthorized role cannot download/release/archive.

## 3. Open Decisions (Must Be Closed Before Sprint 1)
1. Legal regime priority for production policy text:
   - Philippines DPA-first, FERPA-compatible
   - FERPA-first, DPA-compatible
   - dual-policy by campus/jurisdiction
2. File storage target:
   - Local private disk
   - S3 private bucket
   - hybrid by environment
3. Retention trigger and retention period per transaction type.
4. Malware scanning provider and asynchronous scan behavior.

## 4. Phase 0 Completion Status
Completed in this document:
- Best-practice research synthesis
- Lifecycle and transition requirements
- Data dictionary baseline
- Permission matrix
- Security/privacy requirements
- API contract draft
- Backlog and quality gates

Pending organizational approvals:
- Final legal and retention policy sign-off
- DPO validation of data subject rights handling workflow
- ARO supervisor sign-off on transition matrix

## 5. Source References (Research)
- ISO 15489 records management overview: https://committee.iso.org/sites/tc46sc11/home/projects/published/iso-15489-records-management.html
- FERPA regulations index and sections (34 CFR Part 99): https://studentprivacy.ed.gov/ferpa
- FERPA legal basics: https://studentprivacy.ed.gov/legal-basics
- Philippines Data Privacy Act overview: https://privacy.gov.ph/the-data-privacy-act-and-its-irr/
- Data Privacy Act (RA 10173 text): https://privacy.gov.ph/data-privacy-act/
- DPA IRR (organizational/physical/technical security and processing records): https://privacy.gov.ph/implementing-rules-regulations-data-privacy-act-2012/
- NPC Data Subject Rights: https://privacy.gov.ph/data-subject-rights
- OWASP File Upload Cheat Sheet: https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html
- NIST SP 800-53 Rev. 5 (audit and access control references): https://nvlpubs.nist.gov/nistpubs/SpecialPublications/NIST.SP.800-53r5.pdf
- NARA records scheduling guidance context: https://www.archives.gov/records-mgmt/scheduling/knowing
