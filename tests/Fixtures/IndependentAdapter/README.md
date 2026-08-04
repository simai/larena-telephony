# Independent adapter fixture

This directory is a separately named Composer package with its own compatible
license. Its only direct Larena dependency is the public `larena/telephony`
package. It executes the same 14-case suite as the in-package fake adapter.

The fixture is deterministic and performs no network or PBX access. Its PASS
receipt proves public contract compatibility only.
