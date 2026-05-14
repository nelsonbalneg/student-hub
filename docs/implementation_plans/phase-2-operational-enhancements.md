# Phase 2 Implementation Plan: Operational Enhancements (Aligned to Phase 0 Requirements Pack)

## 1. Phase Objective
Extend MVP operations with request/release management, KPI dashboards, saved productivity views, and SLA alerting while strengthening privacy governance and disclosure accountability.

## 2. Recommended Duration
- 1 to 2 sprints

## 3. Scope
### In Scope
- Internal request and release queue workflows.
- Operational dashboards and report exports.
- Saved filters/views and user productivity features.
- SLA alerting and digest notifications.
- Privacy workflow extensions for data subject and disclosure operations.

### Out of Scope
- OCR automation.
- Full retention/disposal engine automation.

## 4. Workstreams and Tasks

### Workstream A: Request and Release Tracking
1. Add `dossier_requests` entity and lifecycle states:
   - `Submitted`
   - `Under Review`
   - `Ready for Release`
   - `Released`
   - `Rejected`
2. Build create/update/request endpoints with requester metadata.
3. Link request lifecycle to dossier lifecycle and release actions.
4. Capture release confirmation (timestamp, actor, channel, notes).
5. Add legal-basis and legitimate-interest capture where disclosure occurs.

Deliverables:
- Request queue UI and detail page.
- Traceable request-to-release chain with disclosure metadata.

### Workstream B: Dashboard and Reporting
1. Implement KPI endpoints:
   - queue by status
   - overdue by assignee
   - average cycle time
   - first-pass completeness rate
   - disclosure activity counts
2. Add date-range filters and role-aware visibility.
3. Add CSV export for operations and compliance reports.
4. Add trend panels for missing documents and return-to-incomplete rate.
5. Validate KPI formulas with ARO supervisor and compliance owner.

Deliverables:
- Operations + compliance dashboard.
- Exportable KPI reports with approved formulas.

### Workstream C: Saved Views and Productivity
1. Implement saved filters and default view preferences.
2. Add quick presets (My Queue, Overdue, For Approval, Incomplete).
3. Persist column preferences.
4. Add safe bulk actions (assign, priority update) with policy checks.

Deliverables:
- Saved views manager.
- Personalization persistence model.

### Workstream D: SLA Alerts and Notifications
1. Define SLA thresholds by priority and transaction type.
2. Implement overdue evaluator job and escalation thresholds.
3. Send digest notifications to assignees/supervisor.
4. Add urgent escalation flow for threshold breach.
5. Surface alerts in queue and dossier screens.

Deliverables:
- Scheduled SLA jobs and notifications.
- In-app alert indicators.

### Workstream E: Privacy and Compliance Expansion
1. Implement data subject request tracking hooks:
   - access
   - correction/rectification
   - erasure/blocking with retention exception handling
2. Add compliance review dashboard widget (pending privacy tasks).
3. Extend audit catalog to include data subject rights processing actions.
4. Add policy review reminder cadence for audit/event scope updates.

Deliverables:
- Privacy operations workflow hooks.
- Compliance oversight views.

## 5. Non-Functional Targets
- Dashboard load under 2.5 seconds for default range.
- Queue query latency under 1.5 seconds for common filters.
- Notification job reliability with retry and dead-letter controls.
- No untracked disclosure events in sampled audits.

## 6. Testing and Validation

### Feature Tests
- Request creation/update/release authorization paths.
- Saved views CRUD and user ownership isolation.
- SLA calculation and escalation logic.
- Data subject workflow permission and validation paths.

### Integration Tests
- KPI aggregation correctness and export consistency.
- Notification job dispatch, retries, and failures.
- Disclosure/audit log linking integrity.

### UAT Focus
- End-to-end request-to-release workflow.
- Dashboard metric trust validation.
- Data subject request handling workflow walkthrough.

## 7. Dependencies
- Stable Phase 1 schema and event logs.
- Approved KPI formulas and SLA thresholds.
- Notification channel readiness.
- Compliance owner availability for validation.

## 8. Risks and Mitigation
- Risk: KPI interpretation mismatch.
  - Mitigation: publish approved KPI formula sheet.
- Risk: notification fatigue.
  - Mitigation: digest grouping and severity thresholds.
- Risk: disclosure metadata incompleteness.
  - Mitigation: required fields + validation + QA sampling.
- Risk: privacy workflow ambiguity.
  - Mitigation: DPO-reviewed SOP and acceptance scripts.

## 9. Exit Gate
- Request/release lifecycle is fully auditable.
- KPI dashboards and exports are validated and signed off.
- Saved views adopted by pilot users.
- SLA alerts operate within accepted false-positive limits.
- Data subject workflow hooks and disclosure capture are operational.
- Phase 3 optimization backlog approved.
