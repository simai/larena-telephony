# Larena Telephony

`larena/telephony` is Larena's single provider-neutral telephony package. Its
public surface defines adapter registration, endpoints, normalized call
sessions and events, guarded commands, health and deterministic compatibility
receipts. A provider can publish an independent Composer adapter that depends
directly only on this package.

The G4.2 slice established the public Adapter SDK, a deterministic fake adapter
and the reusable 14-case conformance kit. G4.3 adds provider-neutral in-memory
endpoint, event-ingress and call-session components plus an in-package Asterisk
AMI 22.x reference adapter. The reference adapter is tested only through an
injectable sanitized fixture transport; this package contains no AMI socket,
PBX/network access, credential resolution, database persistence, media,
recording, CRM vendor code or AI voice runtime.

## Extension rule

Provider-specific payloads and identifiers stay inside adapters. Domain code
uses capabilities and normalized contracts and never branches on provider
names. Adapter registration and conformance never require Larena Pro,
Enterprise, a private package or a commercial entitlement.

`CallSession` owns Larena's logical call identity. Provider call identifiers
enter that DTO only as `sha256:` references; parties, associations and
artifacts remain normalized opaque references. The DTO validates direction,
the planning-frozen state vocabulary, timestamp ordering and optimistic
version without introducing persistence or a state machine runtime.

Third-party package manifest:

```json
{
  "require": {
    "php": "^8.3",
    "larena/telephony": "^1.0"
  }
}
```

The adapter package registers an implementation through its own Laravel
service provider and a versioned `AdapterManifest`. Larena controls neither
the vendor's license nor distribution channel.

## Asterisk AMI fixture boundary

The reference adapter maps the provider-neutral commands `originate`, `hangup`,
`transfer` and `dtmf` to the documented AMI actions `Originate`, `Hangup`,
`Redirect` and `PlayDTMF`. `answer`, `hold` and `resume` are intentionally not
advertised. An action is successful only after an explicitly successful,
matching `ActionID`; rejection is `failed`, while timeout, disconnect and
missing/mismatched correlation remain `uncertain`. An AMI acknowledgement is
not treated as proof that a call reached its desired outcome.

AMI events are normalized inside the adapter. Logical call correlation uses
`Linkedid`, falling back to `Uniqueid`; canonical DTOs retain only hashes and
Larena-owned identities. Duplicate events are ignored before projection,
reordered lifecycle events may reconcile timestamps without regressing state,
and terminal call states never reopen.

## Verification

```bash
composer quality:gate

# G4.3 fixture-only checks
php scripts/run-conformance.php --g4-3-run
php scripts/run-asterisk-ami-tests.php --run
```

Conformance PASS means compatibility with the tested public contract and
fixtures only. It is not security certification, PBX acceptance, carrier
approval or production readiness.
