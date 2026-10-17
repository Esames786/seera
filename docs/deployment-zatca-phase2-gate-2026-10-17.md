# Deployment addendum — ZATCA Phase 2 official-validation gate, 17 October 2026

Feature branch `feature/seera-connected-workspaces-2026-09-23`. Extends [deployment-zatca-phase2-2026-10-17.md](deployment-zatca-phase2-2026-10-17.md); evidence in [zatca-sandbox-validation-evidence-2026-10-17.md](zatca-sandbox-validation-evidence-2026-10-17.md).

**Code-only release.** No migration, no seeder, no permission change, no configuration change, and the asset bundle is unchanged (`public/build.zip` 197,777 bytes, SHA-256 `436847840B7FC77904572B45ED3388752BA76CBC0A12290EFBE821A31BD18B7C`, same as the sprint-1 release). It is **not** deployed and must not be deployed or merged without the owner's release decision; production ZATCA stays disabled.

| Change | Why | Effect on existing data |
| --- | --- | --- |
| SignedProperties digest construction (`ZatcaSignatureService::signedPropertiesForDigest`) | The sandbox reporting endpoint refused the sprint-1 digest (`signed-properties-hashing`). | Documents stamped with the old construction would be refused by ZATCA on reporting; none exist outside tests because no environment was onboarded. Records in `signed` state from before this change should be regenerated (Regenerate action) before submission. |
| Certificate serial as decimal and issuer with reversed multi-valued attributes (`ZatcaCrypto::serialDecimal`, `issuerName`) | Production CA serials are 19 bytes; the schema refuses `0x…`. | Same as above. |
| QR tag 3 without trailing `Z` (`ZatcaStampService`) | Platform warning `invoiceTimeStamp_QRCODE_INVALID`. | Same as above. |
| Compliance sample buyer VAT differs from the seller VAT (`ZatcaComplianceSamples`) | `BR-CUSTOM-VALIDATION-01`. | Only affects the onboarding compliance checks. |

Deploy steps when released: standard code pull (`git pull --ff-only`), `optimize:clear`, `view:cache`; no `migrate`, no bundle unzip required (the zip is unchanged). Verify with the ZATCA record page: Generate & validate XML on a test invoice, then, only in sandbox or simulation with a test CSID, Submit to ZATCA.
