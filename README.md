# Larena Telephony

`larena/telephony` is Larena's single provider-neutral telephony package. Its
public surface defines adapter registration, endpoints, normalized events,
guarded commands, health and deterministic compatibility receipts. A provider
can publish an independent Composer adapter that depends directly only on this
package.

This G4.2 slice contains the public Adapter SDK, a deterministic fake adapter
and the reusable 14-case conformance kit. It deliberately contains no Asterisk
connection, PBX/network access, database persistence, media, recording, CRM
vendor code or AI voice runtime.

## Extension rule

Provider-specific payloads and identifiers stay inside adapters. Domain code
uses capabilities and normalized contracts and never branches on provider
names. Adapter registration and conformance never require Larena Pro,
Enterprise, a private package or a commercial entitlement.

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

## Verification

```bash
composer quality:gate
```

Conformance PASS means compatibility with the tested public contract and
fixtures only. It is not security certification, PBX acceptance, carrier
approval or production readiness.
