# G4.2 Public Telephony Adapter SDK

Status: `implemented_pending_cross-repository_acceptance`

The exact tested implementation revision is
`a087b3e1b23be4cec5c8bcde9408628851f32300`. It implements the public
provider-neutral SDK in the single Apache-2.0 package `larena/telephony` and is
bound to Larena Specs launch revision
`9f95be6f1bf1d8d97de330321ccf9a41ce89ff24`.

Implemented surface:

- versioned provider, adapter, endpoint, logical call session, event, command,
  health and receipt value contracts;
- a normalized `CallSession` that owns the Larena call identity, accepts only a
  hashed provider reference, validates direction/state/timestamps/version and
  keeps parties, associations and artifacts as bounded references;
- registration that rejects duplicate identities, incompatible contract
  versions, unknown capabilities and capability/handler mismatches;
- Access-before-dispatch and idempotent command control through public ports;
- deterministic event deduplication and chronological reconciliation helper;
- recursive secret/provider diagnostic redaction;
- framework registration with no network, database or boot side effects;
- deterministic fake adapter;
- separately named `acme/larena-telephony-demo-adapter` Composer fixture whose
  only direct Larena dependency is `larena/telephony`;
- one reusable 14-case conformance suite and deterministic compatibility
  receipt format.

The implementation contains no Asterisk adapter, PBX connection, persistence,
migration, CRM vendor integration, remote connector, media, recording,
transcription or AI voice runtime. No remote repository was created and no
branch was pushed.

Conformance PASS is a contract-compatibility claim only. It is not independent
product acceptance, security certification, application runtime evidence or
production eligibility.
