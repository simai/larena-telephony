# Changelog

## Unreleased

- Added provider-neutral in-memory endpoint, event-ingress and call-session
  components with explicit transition and late-event reconciliation rules.
- Added the in-package Asterisk AMI 22.x reference adapter over an injectable
  transport contract, without a network implementation or credential access.
- Mapped `originate`, `hangup`, `transfer` and `dtmf` to an allowlisted AMI
  action subset; `answer`, `hold` and `resume` remain unsupported.
- Added the Asterisk adapter to the unchanged public 14-case conformance suite
  and added positive, negative, metamorphic and failure-injection fixture tests.

The maximum claim for this work remains `tests_passed_package_scope`. It does
not establish PBX connectivity, workspace/root runtime acceptance, release or
production eligibility.
