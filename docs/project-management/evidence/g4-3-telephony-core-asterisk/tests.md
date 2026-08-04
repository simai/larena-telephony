# G4.3 package test record

Tested revision: `7bc7852ad4d29bb2c168cb00ec4332af10072619`

Tested tree: `e237ca1d69a6c87ac7a8cdf92f6cdd94b0a8d2d4`

PHP: `/Applications/ServBay/package/php/8.4/8.4.20/bin/php` (`8.4.20`)

Composer lock:
`sha256:fc713eb371240905e78682f951897b68f54fe5d2e503d8f732157c1399fe84cc`

## Executed gates

| Gate | Result |
| --- | --- |
| `composer quality:gate` with the pinned PHP/Composer binaries | PASS |
| `php scripts/run-conformance.php --g4-3-check` | PASS after receipt write |
| unchanged public suite against `larena.fake` | 14/14 PASS |
| unchanged public suite against `acme.demo` | 14/14 PASS |
| unchanged public suite against `larena.asterisk_ami` | 14/14 PASS |
| `php scripts/run-asterisk-ami-tests.php --check` | 63 checks PASS after receipt write |
| PHP syntax scan | PASS |
| PHPStan level 8 | PASS |
| launch-path and forbidden-runtime scope checker | PASS |

The 63-case core receipt includes positive, negative, integration, metamorphic
and failure-injection assertions. It covers duplicate/reordered events, safe
late timestamp reconciliation, immutable terminal state, exact AMI action
allowlists, invalid DTMF/channel rejection, response rejection, timeout,
disconnect, missing/mismatched correlation, transport exception, payload
normalization and deterministic replay.

## Evidence limitations

All provider fixtures are synthetic and sanitized. Tests opened no network
connection, resolved no credential, made no real call and used no persistent
store. These results cannot establish PBX interoperability, aggregate workspace
composition, root application behavior, independent acceptance or production
eligibility.
