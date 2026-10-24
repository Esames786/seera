# ZATCA Phase 2 — official SDK and FATOORA sandbox validation evidence (17 October 2026)

Companion to [zatca-phase2-source-baseline-2026-10-17.md](zatca-phase2-source-baseline-2026-10-17.md) (decisions D-01 … D-26) and [deployment-zatca-phase2-2026-10-17.md](deployment-zatca-phase2-2026-10-17.md). Branch `feature/seera-connected-workspaces-2026-09-23`, gate start `b75ca46`. Nothing in this document is a ZATCA approval: the Developer Portal manual (#5 §2.3.8) states that sandbox results are "not considered as acknowledged, approved or accepted by ZATCA". Nothing here was deployed, merged, or run against production or with production credentials.

## 1. Status vocabulary used everywhere

| Word | Meaning |
| --- | --- |
| SOURCE IMPLEMENTED | Code and automated tests exist in the repository. |
| SDK VERIFIED | The official Compliance and Enablement Toolbox was run on the artefact. **None in this gate** (section 3). |
| SANDBOX VERIFIED | The ZATCA Developer Portal sandbox platform accepted the artefact (HTTP 200 with validation status PASS, or 202 with warnings listed). |
| PRODUCTION ONBOARDED | A production CSID was obtained with the company's own OTP. **None.** |

## 2. Environment, identity and hygiene

| Item | Value |
| --- | --- |
| Environment | ZATCA Developer Portal sandbox (non-production). Base `https://gw-fatoora.zatca.gov.sa/e-invoicing/developer-portal`, confirmed from the portal's published application configuration (`https://sandbox.zatca.gov.sa/env-config.js`). Simulation and production were **not** called. |
| Date | 17 October 2026 (UTC timestamps 05:30–08:40). |
| Seller identity | ZATCA's published sandbox dummy VAT `399999999900003`, legal name "Seera Sandbox Probe Co", unit common name `Seera-Sandbox-Probe-1`, serial `1-Seera ERP|2-1.0|3-probe-…`, location `RRRD2929`, industry Construction, document types `1100`. The sandbox OTP is the fixed value the Developer Portal publishes for its sandbox (#5 §2.3.9), used once per request. |
| Key material | A throw-away secp256k1 key pair generated for the gate by `ZatcaCrypto::generateKeyPair`; never the company's key. The key, CSR, sandbox CSIDs and secrets were held only in a local scratch file outside the repository for the duration of the gate and deleted afterwards. No token, secret, OTP, key or certificate appears in this document, in the repository, in tests or in the report. |
| Code | Seera services only (`ZatcaCrypto`, `ZatcaInvoiceBuilder`, `ZatcaXmlValidator`, `ZatcaSignatureService`, `ZatcaStampService`, `ZatcaOnboardingService`, `ZatcaSubmissionService`, `ZatcaClient`) driven by scratch scripts on an in-memory SQLite database; no home-grown validator replaced the official one. |
| Buyer identity for business documents | "Sandbox Buyer LLC", VAT `399999999800003`, CRN, national address in Riyadh (dummy data). |

## 3. Official SDK: NOT RUN (blocked by authentication)

| Step | Result |
| --- | --- |
| Official material refresh (Phase A) | The nine documents of the baseline were re-checked; no newer version was published. Two sources added: the SDK User Manual v.01 (23 pages, linked from the ZATCA "Download SDK" page) and the Developer Portal application configuration. |
| SDK download | Every official link — the ZATCA website "Download SDK" page and the four SDK links in the Developer Portal configuration (Java, .NET, simulation, beta) — redirects to a Microsoft 365 sign-in on `sadzit.sharepoint.com` (HTTP 200 HTML "Sign in to your account", also with `download=1`). The manual (#10 §3.1.3.2) confirms the download requires a registered, logged-in Developer Portal user who accepts the terms. No third-party mirror was used. |
| Java runtime | Temurin OpenJDK 11.0.25+9 portable zip obtained and verified (`openjdk version "11.0.25" 2024-10-15`); within the manual's JDK ≥ 11 < 15 window. Kept outside the repository. |
| Reproducible setup | Documented in the baseline ("Reproducible SDK setup"): runtime, download path (owner account), input exports, the manual's CLI commands (`fatoorah validatexml -f`, `validatexml -f -p`, `validateqr -qr`, `generatehash -f`, `generate -f -x`), what to record. |
| Consequence | Phase C (seven representative XMLs through the SDK) was **not run**. The same documents were put through the sandbox platform instead, which applies the ZATCA rule set and returns rule ids (sections 4–6). Rule ids below are the platform's. |

## 4. Onboarding on the sandbox (Phase F)

| # | Call | Result |
| --- | --- | --- |
| F-1 | `POST /compliance` with Seera's CSR (template `TSTZATCA-Code-Signing`, subject C=SA / OU / O / CN, SAN dirName SN / UID / title / registeredAddress / businessCategory) | HTTP 200, `dispositionMessage` ISSUED, `requestID` 1234567890123 (sandbox constant). Certificate: issuer `CN=eInvoicing`, subject = CSR subject, serial 13 digits, valid to 2031-10-16. **D-16 verified for the sandbox template.** |
| F-2 | `POST /compliance/invoices` × 6 (standard 388 / 383 / 381, simplified 388 / 383 / 381), first run | All six **rejected**: `BR-CUSTOM-VALIDATION-01` "Seller VAT … must not match the buyer VAT" — the sprint-1 sample buyer used the same dummy VAT as the sandbox seller. Simplified documents also warned `invoiceTimeStamp_QRCODE_INVALID` (KSA-25). Both fixed (section 7). |
| F-3 | `POST /compliance/invoices` × 6, after the fixes | standard 388 / 383 / 381 **CLEARED**, simplified 388 / 383 / 381 **REPORTED**; validation status PASS, 0 warnings, 0 errors; info `XSD_ZATCA_VALID`. The ECDSA signature value in these documents comes from Seera's own throw-away key and the certificate issued for it. |
| F-4 | `POST /production/csids` with the compliance request id | HTTP 200, ISSUED, token type PCSID; unit status `production`, certificate valid to 2029-01-09. The certificate is the sandbox's **shared static** production certificate (`CN=TST-886431145-399999999900003`, `O=Maximum Speed Tech Supply LTD`, issuer `CN=PRZEINVOICESCA4-CA, DC=extgazt, DC=gov, DC=local`, 19-byte serial) and does not contain Seera's public key: see D-26. |

## 5. Business documents on the sandbox (Phases G, H)

| # | Document | Endpoint | Result |
| --- | --- | --- | --- |
| G-1 | Standard tax invoice, two lines (2 × 500.00; 3 × 250.50), Arabic and English descriptions, 15 % VAT, buyer with VAT + CRN + national address, supply date | `invoices/clearance/single`, `Clearance-Status: 1`, PCSID | First attempt (before D-24): HTTP 400 `XSD_ZATCA_INVALID` cvc-datatype-valid.1.2.1 `'0x1100…3803' is not a valid value for 'integer'` (certificate serial). After the fix: **HTTP 200 CLEARED**, 0 warnings; cleared XML stored (13,984 bytes) with ZATCA's QR (8 tags). Record status **Cleared by ZATCA**. |
| G-2 | Standard tax invoice with an Arabic-only buyer legal name | clearance | **HTTP 200 CLEARED**. |
| H-1 | Simplified tax invoice, one line 100.00, 15 % VAT | `invoices/reporting/single`, PCSID | Before D-24: XSD error as above plus `signed-properties-hashing`. After D-24: `signed-properties-hashing` only. After the SignedProperties fix (section 7): **HTTP 200 REPORTED**, 0 warnings. Record status **Reported to ZATCA**. |

Only actual platform responses set these states; the services were not modified to assume success.

## 6. Negative cases (Phase I) and their Seera category

| Case | How it was produced | Platform / Seera result | Seera category |
| --- | --- | --- | --- |
| Invalid buyer (no street / city / VAT / id on a standard invoice) | Customer without address | Refused locally before any call: `readiness/buyer_street_missing` | Validation error (readiness) |
| VAT rounding mismatch (two lines of 1.03) | Per-line VAT 0.30 vs document-level 0.31 | Refused locally: `local_validation/validation_failed` (BR-S-09) | Validation error (local) |
| Seller VAT differs from the CSID VAT | Seller VAT edited to `399999999700003` | HTTP 400 `certificate-permissions` + `vatRegistrationNumber_QRCODE_INVALID` | Rejected (validation error) |
| Invalid signature value | `ds:SignatureValue` corrupted, hash intact | HTTP 400 `INVOICE_SIGNATURE_VALUE_QRCODE_INVALID` (QR tag 7 vs document) | Rejected |
| Wrong PIH | KSA-13 replaced by the first-document hash, `invoiceHash` recomputed over the altered document | HTTP 400 `invoiceHash_QRCODE_INVALID` (QR tag 6 vs recomputed hash). The platform did not name the PIH itself. | Rejected |
| Wrong `invoiceHash` field | Hash of another string sent in the request body | HTTP 400 `invoiceHash_QRCODE_INVALID` | Rejected |
| Tampered stored artefact | Signed XML on disk edited after stamping | Refused locally before any call: `state/artifact_tampered` | Validation error (integrity) |
| Invalid credential | Wrong token / secret | HTTP 401 | Authentication error |
| Malformed XML | `<Invoice><broken></Invoice>` | HTTP 400 `XSD_ZATCA_INVALID` | Rejected |
| Standard document on the reporting endpoint | Signed standard invoice to `reporting/single` | HTTP 400 `XML-INVOICE-ERROR` (the documented 303 was not observed on the sandbox) | Rejected (wrong endpoint by code) |
| Duplicate submission | The same signed document (same UUID, hash, ICV) sent twice | **Accepted twice** (HTTP 200 CLEARED both times): the sandbox does not detect duplicates. | — (see Phase J) |
| Timeout / outcome unknown | Cannot be induced on the sandbox; covered by `ZatcaPhase2Test` with a faked connection exception | Record → "Submission outcome unknown: verification required"; retry resends the identical document | Outcome unknown → Retryable |
| Rate limit (429) / server error (5xx) | Not observed on the sandbox; covered by faked responses in `ZatcaPhase2Test` | Retryable | Retryable |

## 7. Decisions resolved with official evidence (Phases D, E)

| Decision | Current implementation before the gate | Official evidence | Change |
| --- | --- | --- | --- |
| D-07 canonicalisation / digest | `DOMDocument::C14N()` after removing UBLExtensions, the QR reference and `cac:Signature`; SHA-256; base64 | The hash Seera sends and embeds was accepted by all three endpoints; a wrong hash is refused with `invoiceHash_QRCODE_INVALID`, so the platform recomputes it with the same transforms | None. SDK `generatehash` not run. |
| D-10 signature input / SignedProperties digest | ECDSA over the raw digest bytes; certificate digest base64(hex(SHA-256)); SignedProperties digest = binary SHA-256 of the block as embedded | Reporting refused the embedded-block digest with `signed-properties-hashing`; of twelve constructions only one is REPORTED: SHA-256 **as hex, then base64**, over the SignedProperties block serialised standalone (`xmlns:xades` on the element and `xmlns:ds` on each ds child), while the document keeps the undecorated block. The compliance endpoint does not apply the rule, which hid it in sprint 1. | `ZatcaSignatureService::signedPropertiesForDigest`; regression test `test_signed_properties_digest_uses_the_standalone_serialisation_and_hex_then_base64`. Old construction not preserved. |
| D-16 CSR / certificate | Template `TSTZATCA-Code-Signing`, SAN dirName fields | `/compliance` ISSUED for Seera's CSR; certificate subject equals the CSR subject | None for the sandbox; `PREZATCA` / `ZATCA` templates remain unverified (simulation / production OTP). |
| D-11 QR tag 3 | `IssueDate` + `T` + `IssueTime` + `Z` | Warning `invoiceTimeStamp_QRCODE_INVALID`; without `Z` (or with `Z` in both places) PASS | `ZatcaStampService` builds tag 3 from the XML values without `Z`; assertion updated. |
| D-23 sample buyer VAT | Fixed `399999999900003` | `BR-CUSTOM-VALIDATION-01` when equal to the seller VAT | `ZatcaComplianceSamples::SAMPLE_BUYER_VATS`; test `test_compliance_sample_buyer_vat_never_equals_the_seller_vat`. |
| D-24 certificate serial / issuer | `openssl_x509_parse()` `serialNumber` used as is; issuer RFC 2253 without reversing multi-valued attributes | `XSD_ZATCA_INVALID` for `0x…` serials; `certificate-issuer-name` for other issuer forms | `ZatcaCrypto::serialDecimal`, `ZatcaCrypto::issuerName`; test `test_certificate_serial_and_issuer_follow_the_production_ca_format`. |

Cross-check against platform output (Phase E): invoice hash, signature value and public key are checked by the platform through the QR rules (`invoiceHash_QRCODE_INVALID`, `INVOICE_SIGNATURE_VALUE_QRCODE_INVALID`, `vatRegistrationNumber_QRCODE_INVALID` were each triggered on purpose and absent on the accepted documents); the SignedProperties digest by `signed-properties-hashing`; PIH and ICV were accepted on a chain of six stamped documents per unit. The sandbox cannot verify the ECDSA value against its shared production certificate, so the only key-bound proof is the compliance CSID run (F-3). Comparing against SDK output (`generate -x`, `generatehash`) remains open until the owner downloads the SDK.

## 8. Concurrency re-run (Phase N)

Crypto, hash and onboarding code changed, so `tests/manual/zatca-concurrency.php` was re-run on a disposable MySQL 8.0.30 server (port 33479, datadir `seera-zatca-lock-test-…`, deleted afterwards): all four scenarios PASS (two invoices at once → ICV 1 and 2 with a linked PIH; the same invoice twice → one identity; submit vs retry → one identity, attempts 1; a failed request keeps the chain and the retry reuses the identity).

## 9. VAT rounding — Finance decision brief (Phase K; no policy chosen)

Official rule: ZATCA computes the VAT category amount on the category taxable total, rounded to two decimals (BR-S-09; XML standard §10 "two decimals, half-up"). Seera's posted invoice VAT is the sum of per-line VAT rounded per line. The two differ only when the per-line rounding residues accumulate (observed: two lines of 1.03 → 0.30 vs 0.31). The sandbox refused nothing on this point because the local validator stops such an invoice before any call; the G-1 / H-1 documents had no residue.

| Option | What changes | Effect on posted history | Effect on ZATCA | Effort / risk |
| --- | --- | --- | --- | --- |
| A. Keep per-line VAT; block mismatching invoices at ZATCA | Nothing in posting. Finance corrects the invoice before approval (adjust a line price by 0.01) when the local check refuses it. | None. | Only invoices whose per-line sum equals the document-level amount can be cleared / reported. | Lowest effort; the operational cost is an occasional pre-approval correction and a clear message on the record (already in place). |
| B. Document-level VAT at approval for new invoices | `PostingService` / invoice approval computes VAT per category on the taxable total (BR-S-09) and allocates the rounding difference to one line (last line) so lines still sum to the header. | None for invoices already posted; applies from a cut-over date. | Every new invoice satisfies BR-S-09 by construction. | Medium: touches invoice approval, VAT rows and the journal; needs AR / VAT report tests and Finance sign-off on the allocation rule. |
| C. Both amounts kept: XML carries document-level VAT, the ledger keeps per-line VAT | The XML would show a VAT total that differs from the posted invoice by up to a few halalas. | None. | Accepted by ZATCA, but the e-invoice and the accounting record disagree. | Rejected on principle: the guide's rule "the XML always carries the posted invoice's own figures" would be broken, and the VAT return would not match the cleared documents. |

Recommendation for Finance to decide: A now (no code change), B as a scheduled change if Finance wants mismatches to stop appearing. Historical posted invoices are never mutated under any option.

## 10. Credit / debit notes — dependency brief (Phase L)

| Question | Official evidence | Assessment |
| --- | --- | --- |
| Can a cleared / reported invoice be cancelled or edited? | Implementation Resolution (#7) Article 3: e-invoices and notes are generated in XML or PDF/A-3 with embedded XML, carry the cryptographic stamp and are transmitted; Annex 2 prohibited functions include deleting or modifying an issued e-invoice; Article 4 and #1 §5.2 / BR-KSA-17 / BR-KSA-56: corrections are electronic credit / debit notes that reference the original invoice and state the reason. | **Production blocker.** Once Seera clears or reports a document in production, the only lawful correction is a note; Seera cannot issue one as a business document (no AR note module, no VAT / GL / AR effect). Seera's current protection is "do not submit a doubtful invoice" (readiness and local validation) and the lock on stamped invoices. Going live without notes means every post-clearance correction must be done outside Seera. |
| Does the sandbox accept notes? | The six onboarding samples (388 / 383 / 381 for both types) were CLEARED / REPORTED. | The XML shape for notes (BillingReference, InstructionNote reason) is sandbox-verified as sample documents only; it is not a business feature. |
| What must the note module include? | #1 §5.2, BR-KSA-17 (reason), BR-KSA-56 (billing reference). | AR credit / debit note document with lines, VAT, GL posting and AR balance effect, workspace actions, the ZATCA record wiring (type code 381 / 383, same chain), permissions, tests. Not built in this gate, per instruction. |

Classification: **PRODUCTION BLOCKER** (must exist before production ZATCA is enabled), not post-go-live.

## 11. Arabic and the human-readable invoice (Phase M)

| Requirement | Official evidence | Seera today |
| --- | --- | --- |
| Human-readable fields in Arabic | #4 FAQ: "All the data fields that are visible on human readable form of the invoice must be in Arabic … Invoice can be bilingual". | No printable or PDF customer invoice exists; the Company Profile has an Arabic legal name and the readiness check warns when it is missing. The cleared XML carries the seller Arabic name when present. |
| Sharing the invoice with the buyer | #7 Article 3(A)(2): every e-invoice and note "must be generated in XML format or PDF/A-3 format (with embedded XML)"; Article 3(B)(3): a printed copy of the simplified invoice must be presented to the customer, or by agreement its electronic form "or any other human readable format". | Seera provides the cleared XML download (standard) and the stamped XML (simplified); no PDF/A-3 and no printed layout. |
| Arabic numerals | #1 §9: decimals in Arabic numerals (1234567890) in the XML. | Satisfied by the XML builder. |

Assessment: a bilingual human-readable layout (printable, later PDF/A-3 with the embedded XML) is required before production for standard invoices shared with buyers; not built in this gate because the instruction was to build it only if clearly required **and** scoped by the owner. It is listed as a pre-production item in the readiness matrix, not as a sprint deliverable.

## 12. Production readiness matrix

| Area | Status | Evidence / gap |
| --- | --- | --- |
| XML BUILDER | SOURCE IMPLEMENTED · SANDBOX VERIFIED | Standard and simplified 388, notes 381 / 383 (samples), Arabic text, multi-line: PASS on the sandbox. Discounts / allowances are not modelled in Seera invoices (not tested). |
| OFFICIAL SDK | NOT RUN | Download login-gated; JDK 11 runtime ready; steps documented. |
| CRYPTO / SIGNATURE | SOURCE IMPLEMENTED · SANDBOX VERIFIED (compliance CSID with Seera's own key; SignedProperties digest and certificate serial / issuer corrected) | ECDSA value not verifiable on the sandbox production certificate (shared static cert). |
| QR | SOURCE IMPLEMENTED · SANDBOX VERIFIED (timestamp corrected) | Tags 1–9 accepted; cleared standard documents carry ZATCA's QR. |
| HASH CHAIN (ICV / PIH) | SOURCE IMPLEMENTED · SANDBOX VERIFIED · MySQL concurrency probe PASS | Chains of six documents accepted per unit. |
| COMPLIANCE CSID | SANDBOX VERIFIED | Issued for Seera's CSR; six compliance documents accepted. |
| PRODUCTION CSID | SANDBOX VERIFIED (sandbox only) · **PRODUCTION NOT STARTED** | Simulation and production need the company's OTP and the owner's FATOORA account. |
| STANDARD CLEARANCE SANDBOX | SANDBOX VERIFIED | HTTP 200 CLEARED, cleared XML stored. |
| SIMPLIFIED REPORTING SANDBOX | SANDBOX VERIFIED | HTTP 200 REPORTED. |
| DUPLICATE / OUTCOME UNKNOWN | SOURCE IMPLEMENTED (identity guard, outcome-unknown state, identical retry) | Sandbox has no duplicate detection and ZATCA publishes no status-query API; manual portal verification stays the procedure; simulation behaviour unverified. |
| VAT ROUNDING POLICY | DECISION PENDING (Finance) | Options A / B / C above; local validator blocks mismatches meanwhile. |
| CREDIT / DEBIT NOTES | PRODUCTION BLOCKER · NOT BUILT | Section 10. |
| ARABIC HUMAN INVOICE | PRE-PRODUCTION ITEM · NOT BUILT | Section 11. |
| PRODUCTION ZATCA | NOT OPERATIONAL | `ZATCA_PRODUCTION_ENABLED=false`; no production credentials exist in Seera. |

## 13. Unresolved warnings and open points

- None on the accepted documents (0 warnings on F-3, G-1, G-2, H-1). The `certificate-issuer-name` warning appears only when a document signed with the compliance certificate is sent with production credentials (expected mismatch; not a Seera flow).
- The platform's `XML-INVOICE-ERROR` for a standard document on the reporting endpoint is classified as Rejected by error code; the documented 303 handling stays in the client for simulation / production.
- SDK-side comparison of `generatehash` and `generate -x` output against Seera's values is open until the SDK can be downloaded with the owner's account.
- Simulation (`PREZATCA-Code-Signing`, company VAT) has not been exercised; it is the next environment before any production step.

## 14. Addendum, 22 October 2026 — customer credit / debit notes on the sandbox

Same environment, hygiene and status vocabulary as sections 1–2; a new throw-away identity was created for the run (compliance CSID issued for a fresh Seera CSR, six compliance documents PASS, sandbox production CSID issued) and deleted afterwards. Documents were built by Seera's business layer: a posted customer invoice, then a credit note and a debit note created and posted through `CustomerNoteService` (journal + VAT correction + balance application), then submitted through `ZatcaSubmissionService` as 381 / 383 documents on the same EGS chain.

| # | Document | Type / transaction code | Endpoint | Result | ICV / PIH |
| --- | --- | --- | --- | --- | --- |
| N-1 | Standard tax invoice, two lines (10 × 100.00; 5 × 200.00), Arabic text | 388 / 0100000 | clearance | HTTP 200 **CLEARED**, 0 warnings | 1 / first-document hash |
| N-2 | Standard credit note against N-1: 2 × 100.00 of line 1, reason "Return of goods" (BillingReference = N-1, InstructionNote = reason) | 381 / 0100000 | clearance | HTTP 200 **CLEARED**, 0 warnings | 2 / hash of N-1 |
| N-3 | Standard debit note against N-1: new charge 3 × 150.00, reason "Agreed additional charge" | 383 / 0100000 | clearance | HTTP 200 **CLEARED**, 0 warnings | 3 / hash of N-2 |
| N-4 | Simplified tax invoice, same lines | 388 / 0200000 | reporting | HTTP 200 **REPORTED**, 0 warnings | 4 / hash of N-3 |
| N-5 | Simplified credit note against N-4 | 381 / 0200000 | reporting | HTTP 200 **REPORTED**, 0 warnings | 5 / hash of N-4 |
| N-6 | Simplified debit note against N-4 | 383 / 0200000 | reporting | HTTP 200 **REPORTED**, 0 warnings | 6 / hash of N-5 |

Observations: the note inherits the original document's class (standard → clearance, simplified → reporting), as XML Implementation Standard §5.2 requires; the sandbox accepted the billing reference as the original invoice number alone (no `cbc:IssueDate` inside `InvoiceDocumentReference`), positive note amounts with the type code carrying the meaning, the free-text reason (KSA-10) and the original supply date as `ActualDeliveryDate`. No source change was needed for the notes beyond the business layer; the stamp, hash, QR and SignedProperties constructions corrected in section 7 held for 381 / 383. Business effect recorded during the run: credit note 230.00 applied to the invoice (balance 2,300.00 → 2,070.00), debit note 517.50 added (→ 2,587.50), journals JV-…-0001 … 0004 balanced.

Statuses after this run: ZATCA NOTE XML — SOURCE IMPLEMENTED · SANDBOX VERIFIED; STANDARD CREDIT / DEBIT NOTE SANDBOX — VERIFIED; SIMPLIFIED CREDIT / DEBIT NOTE SANDBOX — VERIFIED; official SDK — still NOT RUN; production — NOT ONBOARDED.

## 15. Addendum, 24 October 2026 — final readiness gate

- Official sources re-checked: unchanged (2023-05-19 vF set; SDK manual v.01; SDK download still behind the Developer Portal / SharePoint sign-in). **Official SDK: NOT RUN — portal access required.**
- Company simulation (FATOORA simulation with the client's VAT and OTP): **NOT RUN — owner inputs required**; the throw-away sandbox identity of 17 / 22 October is not company verification. No simulation document is marked CLEARED or REPORTED.
- New controls tested (`ZatcaProductionControlsTest`): production disabled by default and independent of APP_ENV; environment mismatch refused before any call; expired certificate blocks readiness; no secret in logs, submission rows or screens; out-of-order stamping keeps a monotonic, linked chain.
- Printable documents (`CustomerInvoicePrintTest`, headless Edge renders at 1280 px, 400 px and A4 PDF, two pages for a five-line invoice): bilingual layout, issued snapshots, stored QR drawn with the vendored MIT `qrcode-generator` library; QR scanning with a phone remains an owner UAT step.
- Status words after this gate: SOURCE IMPLEMENTED — yes; OFFICIAL SDK VERIFIED — no; SANDBOX VERIFIED — yes (invoices and notes); COMPANY SIMULATION VERIFIED — no; PRODUCTION ONBOARDED — no; PRODUCTION OPERATIONAL — no. Matrix and activation steps: [zatca-production-readiness-2026-10-24.md](zatca-production-readiness-2026-10-24.md).
