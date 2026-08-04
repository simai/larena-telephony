# G4.2 Test Evidence

Tested code revision: `a087b3e1b23be4cec5c8bcde9408628851f32300`

Specs launch revision: `9f95be6f1bf1d8d97de330321ccf9a41ce89ff24`

Runtime: PHP `8.4.20`, Composer `2.7.1`, package platform PHP `8.3.31`.

All commands ran locally without PBX, network calls from package code,
database, credentials, customer data or application installation:

| Gate | Command | Result |
| --- | --- | --- |
| metadata | `php scripts/validate-larena-package.php` | PASS |
| syntax | `php scripts/lint.php` | PASS, 48 PHP files |
| static analysis | `php scripts/analyse.php` | PASS, PHPStan level 8 |
| public tests | `php tests/run.php` | PASS, 18 aggregate assertions |
| conformance freshness | `php scripts/run-conformance.php --check` | PASS |
| scope | `php tools/larena-scope-check.php` | PASS, 70 repository paths |

Conformance results:

- `larena.fake`: 14/14, `conformant`;
- `acme.demo`: 14/14, `conformant`;
- repeated suite execution: byte-identical receipts;
- duplicate and reordered events: one identity per idempotency key and stable
  chronological ordering;
- repeated commands: one adapter dispatch;
- denied and unsupported commands: zero adapter dispatches;
- uncertain provider result: never success;
- raw endpoint secret and private Telephony dependency: rejected;
- raw provider call identifier and reversed call timestamps: rejected;
- equivalent provider identities: equal non-identity behavior signature;
- reordered Composer requirements: equal dependency-boundary fingerprint.

The package-wide quality gate is activated for the evidence commit. These
tests prove neither application installation nor Asterisk/PBX/runtime behavior.
