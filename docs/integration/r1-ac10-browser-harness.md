# R1 AC10: technical browser harness

Run only against an authorized disposable installation. Enable the bank
workspace and ERP panel on that installation. Supply an account that can see
both the bank request and AP reconciliation queue, and the UUID of a bank
request explicitly prepared for the test. The harness selects that request;
it does not create a payment, mutate the workflow, or upload a file.

Install the existing Playwright tooling locally (for example,
`npm install --no-save --package-lock=false playwright@1.56.1`). Set:

| Environment variable | Meaning |
| --- | --- |
| `R1_STAGING_URL` | Application base URL, including its mount path if applicable |
| `R1_UAT_USER`, `R1_UAT_PASS` | Authorized disposable login |
| `R1_BANK_REQUEST_UUID` | UUID of the authorized, visible test request |
| `R1_BROWSER_EVIDENCE_DIR` | Optional dedicated output directory; defaults to `docs/integration` |
| `R1_BROWSER_EXECUTABLE` | Optional Chromium-based executable path; otherwise install Playwright Chromium |

Run `node scripts/r1-browser-acceptance.mjs`. Exit 0 means every required
check passed and the final Markdown report was persisted. Missing settings,
partial runs, wrong print identity, cleanup errors and report write failures
return nonzero. Use a separate evidence directory for each run; screenshots
can contain fixture data and must not be committed.

Run `node --test scripts/r1-browser-acceptance.test.mjs` with
`R1_BROWSER_EXECUTABLE` pointing to a local Chromium-based browser to execute
the four-case matrix against a loopback HTTP fixture. Without an explicit
browser, that test command runs only the configuration case. The fixture
matrix checks CLI behavior; it is not evidence of Finance UAT, workflow
mutations, storage acceptance, or release sign-off.