# New client audio and missing live controls — 11 November 2026

Status: source review and release-gap diagnosis. No production write, deployment, migration, deletion, deactivation or reset was performed. This review is on the separate `docs/seera-client-audio-followup-2026-11-11` branch; the QA/deployment candidate branch is unchanged.

## Direct answer about the supplied screenshots

The client is right that the controls are absent in the screens they are using. The Employee's **Personal** section is already selected, but no profile-photo uploader is shown. The Customer Create page shows the old editable field with the `Auto: CUS-001` placeholder, rather than the new next-code preview/manual-choice controls. Asking the client to find another tab or refresh repeatedly does not supply missing server-rendered controls.

The latest owner terminal evidence placed the serving checkout at `9cc5ea0` and prepared `063c42f` in the independent private directory `/home/techbrit/seera-november.mAuOQH/candidate`. Those preparation commands did not activate the new code or migrate the original database. The source at `9cc5ea0` exactly contains the old controls visible in the screenshots. A new live HEAD must still be checked if another operator has deployed since that evidence.

| Control | Last reported live source at 9cc5ea0 | Prepared source at f22e391 | Evidence boundary |
| --- | --- | --- | --- |
| Employee photo | Personal form proceeds straight to First Name; no photo component | Personal form includes `x-admin.profile-photo` and its chooser/preview | The client's screenshot matches the old form. Prepared-branch tests/screenshots are not a live upload demonstration. |
| Customer code | Editable `code` input, blank value, static `Auto: CUS-001` placeholder | `x-admin.master-code` gives a readonly next-code preview; manual entry is a deliberate checkbox choice | Backend blank-code numbering existed before; the missing piece on the supplied screen is the explicit preview/readonly UX. |
| Supplier code | Old input with `Auto: SUP-001` placeholder | Same shared next-code component as Customer | Do not assume Customer/Supplier actual saved numbering was wholly absent. |
| Customer/Supplier documents | New party-document facility not present in this old release | Documents on initial Create/Manage and saved-file View/Download | The earlier server inventory showed no Supplier document table or personal-permission table and incomplete feedback migrations. |

Actual source references: `resources/views/admin/hr/employees/_form.blade.php`, `resources/views/admin/master/customers/_form.blade.php`, `resources/views/admin/master/suppliers/_form.blade.php`, and `resources/views/components/admin/master-code.blade.php`. They were compared using Git, not assumed from screenshots alone.

If the serving HEAD later proves to be the new release but still renders this old markup, inspect the actual document root/checkout and compiled-view/OPcache state. Do not present browser cache clearing as a fix for an old serving checkout. Do not copy only the new form onto the old database: the complete release needs its reviewed schema and storage recovery.

## Sources and method

The newly added files were found in **`C:/Users/Dell/Downloads/seera_today`**, not the older `Downloads/seera` directory. There are two new 11 November voice notes, totaling **92.033 seconds**. Earlier 10 November media were not renamed or overwritten.

| ID | Original filename | Duration | SHA-256 |
| --- | --- | --- | --- |
| N11-A01 | WhatsApp Audio 2026-11-11 at 1.59.43 PM.ogg | 47.666 s | `17efcd5b2c19f65ed29b766d528cbbab8ea038aa0d042fdfd78ce3a85a3c55ba` |
| N11-A02 | WhatsApp Audio 2026-11-11 at 2.02.52 PM.ogg | 44.367 s | `7dd2e71a6ef74545505d88df6e04918fbdc98bc84b72f027914f2aa57e3d2440` |

Offline extraction used medium-model Urdu transcription and small-model English translation, followed by medium Urdu and English checks of the 6–14-second admin passage (six passes in total). Raw machine output is private under `storage/app/private/requirements-review/seera_today_2026_11_11/`. These are reviewed meanings, not certified verbatim transcripts. The small English pass hallucinates repetitive numbers in A01 and reverses part of the user-deletion sentence; those passages are rejected as evidence. The medium model mishears the loanword “user” as “video” in its initial A02 pass; the context and clipped reread establish login accounts, not video files. The targeted Urdu and English passes both identify the client saying that their retained login is an admin user. The spoken email and final few words are not reliable enough for an exact identifier or a new functional requirement.

## N11-A01 — repeated unchanged live screens

| Approximate time | Reviewed meaning in Roman Urdu | Requirement / assessment |
| --- | --- | --- |
| 00:00–00:13 | “Raat screenshots bheje thay. Ctrl+Shift+R se kai dafa refresh aur dobara login kiya; points ab bhi waise hi hain.” | Re-report of unchanged live behavior. Do not ask them to repeat refresh as the resolution. |
| 00:13–00:20 | “Customer aur Supplier ke documents attach karne ka option abhi available nahi.” | R10-10/R10-11 remain unavailable on the reported live release; prepared code is separate. |
| 00:20–00:30 | “New Customer/Supplier ka code Employee ki tarah khud aaye aur automatic field manually type/edit na karni pare.” | R10-09: default automatic, visible readonly preview. Existing prepared code follows that default. The optional manual checkbox remains an implementation choice, not a separately confirmed client demand. |
| 00:30–00:41 | “Purane user IDs hatane ko kaha tha, nayi IDs main banaunga; purane users ab bhi hain.” | R10-06 actual cleanup remains unperformed. Documentation/tool readiness does not satisfy this request. |
| 00:41–00:48 | “Screenshots dobara bhej raha hoon; login karke check karein, mere paas update nahi aa rahi.” | Acceptance must use the installed release and the client's actual login/role, not only local synthetic screenshots. |

## N11-A02 — desired clean starting dataset

| Approximate time | Reviewed meaning in Roman Urdu | Required handling |
| --- | --- | --- |
| 00:00–00:12 | “Sirf mera naya/apna user rakhein, baqi users hata dein; yeh admin ka user hai.” The clipped reread resolves this as the client describing their retained login as an admin account, rather than clearly asking to retain the separate old Admin User too. | This updates the client's earlier keep preference. Verify the exact current User ID/email and actual active Super Admin capability; “admin” in speech is not proof of its effective role. Do not infer the User ID from Employee `/3/edit`, list order, spoken email or displayed code. Owner instruction remains inventory first, then approved deactivation; do not lock out the last usable admin or silently grant a privileged role. |
| 00:12–00:22 | Client mentions recently entered master data and prefers retaining it where possible; says re-entry is possible if it has to be removed. | Conditional willingness to re-enter is not a precise approved delete list or a reason to discard all new data. Inventory Employee/Customer/Supplier, uploaded documents and linked records first. |
| 00:22–00:33 | “Mujhe zero se clean starting data chahiye. Entry karte waqt purana data saath aa jata hai.” | Separate old DB rows, shared required setup, missing references and browser autocomplete. Do not erase permissions/chart/company setup to remove misleading suggestions. |
| 00:33–00:44 | Wants the previously mentioned few corrections done together, then to work through the system from beginning to end and report problems. | A corrected-release walkthrough is requested. The uncertain word transcribed as “activations” is not evidence of four or five new modules/licenses/integrations. |

## Actionable backlog and current status

| ID | Action | Current status |
| --- | --- | --- |
| N11-01 | Resolve serving-release mismatch; verify photo and code controls on the actual accepted release/client login | OPEN LIVE RELEASE GAP. Prepared code exists; no production activation is evidenced. |
| N11-02 | Deliver Customer/Supplier document controls end-to-end | PREPARED IN CODE; LIVE ROLLOUT PENDING. Do not mark accepted until saved documents are checked on the correct deployed release. |
| N11-03 | Verify auto codes default to generated/readonly on Create and keep correct Save & New sequencing | PREPARED IN CODE; live screenshot still shows the old placeholder. No renumbering of saved records. |
| N11-04 | Read-only current login/link inventory, exact retained identities, approved deactivation | PENDING ACTUAL INVENTORY AND ID REVIEW. No live user was removed by this work. |
| N11-05 | Itemized clean-start master-data review, preserving necessary setup and real entries/files | PENDING KEEP/REVIEW LIST. No full reset or master deletion performed. |
| N11-06 | Client walkthrough after the complete release/storage preparation | PENDING ACTUAL ROLLOUT AND ACCEPTANCE. New captures and local tests do not substitute for it. |

These are predominantly repeated, still-unavailable feedback points rather than six unrelated new software features. The new preference is to retain the client's own admin login rather than all old accounts, and to start clean. Exact retained IDs, privileges, dependencies and a destructive execution list still require the owner review; no cleanup was executed by this audio review.

## Next concrete operator evidence

The prepared candidate already includes the read-only `seera:entry-audit` command. Save its JSON outside the web root, using the confirmed original source database, and review users/roles/actual Employee links, duplicated displayed codes and master dependencies. The report includes private names/emails and must not be committed or published in the client guide.

The current known database condition is 101 MyISAM and two InnoDB tables, including incomplete November DDL. Follow the separate data-preserving backup/restore/rehearsal procedure before activating the new feature code. Do not execute `migrate:fresh`, the go-live reset, blind `migrate --force`, or bulk user deletion to make the screenshots resemble the guide.

The [November screenshot guide](../../user-guide/CLIENT-FEEDBACK-RESOLUTIONS-2026-11-10.md#screenshots-where-each-change-is) shows fictional examples of the prepared changes. It must not be described to this client as proof that their currently visible portal has already changed.
