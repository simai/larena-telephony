# G4.3 Telephony Core and Asterisk AMI fixture implementation

Status: `implemented_pending_package_evidence_acceptance`

Code revision: `7bc7852ad4d29bb2c168cb00ec4332af10072619`

Code tree: `e237ca1d69a6c87ac7a8cdf92f6cdd94b0a8d2d4`

Specification launch: `5272bbc18d97e6fa60b96682942ebe5daa466856`

Technology signature:
`sha256:84d90cf011a10a2daef697796285fc0c716ea5fbf5b13f130ad37fef79cff7bf`

## Implemented package slice

- public in-memory endpoint and call-session store contracts;
- deterministic endpoint registry and optimistic call-session store;
- frozen call transition policy and safe late-event reconciliation;
- event ingress that resolves endpoints/adapters, rejects replay before state
  mutation and quarantines unknown or malformed provider events;
- one in-package `larena.asterisk_ami` reference adapter targeting Asterisk 22.x
  LTS AMI semantics;
- injectable AMI transport and context interfaces with no socket or secret
  implementation;
- allowlisted mappings from `originate`, `hangup`, `transfer` and `dtmf` to
  `Originate`, `Hangup`, `Redirect` and `PlayDTMF`;
- normalized mappings for the frozen channel lifecycle event subset;
- Laravel package discovery bindings for the provider-neutral in-memory core.

`answer`, `hold` and `resume` remain unsupported by the Asterisk adapter.
Provider response rejection is `failed`; timeout, disconnect, transport
exception and absent/mismatched correlation are `uncertain`. A matching explicit
AMI success response proves action acknowledgement only, not the resulting call
state.

## Authority and sources

The implementation is bound to the G4.3 launch record and its canonical source
hashes. AMI fixture semantics were frozen from the official Asterisk 22 action
and event documentation plus the AMI v2 specification:

- https://docs.asterisk.org/About-the-Project/Asterisk-Versions/
- https://docs.asterisk.org/Configuration/Interfaces/Asterisk-Manager-Interface-AMI/AMI-v2-Specification/
- https://docs.asterisk.org/Asterisk_22_Documentation/API_Documentation/AMI_Actions/
- https://docs.asterisk.org/Asterisk_22_Documentation/API_Documentation/AMI_Events/

## Explicit exclusions

There is no network transport, live PBX connection, credential resolution,
database or migration, workspace/root integration, ARI/media/recording,
transcription, AI voice, CRM vendor adapter, remote repository, tag, release or
deployment. The maximum claim is `tests_passed_package_scope`.
