# R1 legacy AP evidence guard

The AP residual projection flags posted legacy payment references, explicit legacy allocations and orphan allocations. It does not infer an amount reduction or rewrite history. The existing reservation entry point refuses a flagged bill with `ap_reconciliation_required`; an unposted reference without an allocation does not block.

Focused AP regressions: 13 tests / 151 assertions. Disposable MariaDB service verification confirmed the same blocking and no-write behavior. Final default suite: 756 tests / 15,693 assertions. No race, production activation, or human UAT claimed.