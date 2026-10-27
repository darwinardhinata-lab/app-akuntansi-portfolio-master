# Menu implementation and acceptance status

The 151 IDs are source inventory, not delivered functions. `menu-map.csv` uses:

- `heading`: source heading/anchor, no executable process.
- `unmapped`: no ERP implementation mapping established.
- `mapped-not-implemented`: target route proposed, implementation not established.
- `reuse-candidate`: existing named route identified, functional parity not established.
- `implemented-pending-acceptance`: end-to-end implementation identified; acceptance incomplete.
- `verified`: acceptance complete with route, implementation path, permission contract and relevant test evidence, all populated.

The generator intentionally emits no `verified` or `implemented-pending-acceptance` rows merely from route existence. A matching label, a legacy test, or passing reference v33 tests is insufficient evidence. Duplicate navigable destinations are alias candidates, not automatic redirects. No status authorizes module activation.