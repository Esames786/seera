# Finance decision — VAT rounding for ZATCA e-invoices (prepared 24 October 2026, decision pending)

**Who decides:** the owner / Finance. **What Seera does until then:** keeps the existing calculation and blocks, before any ZATCA call, every document whose figures would fail ZATCA's document-level check (Option A behaviour, already in place). Nothing in this note changes posted invoices, VAT rows or journals.

## 1. The rule and the difference

| Source | Rule |
| --- | --- |
| Seera today | Each invoice line: taxable = round(qty × price, 2); VAT = round(taxable × rate ÷ 100, 2). Invoice VAT = **sum of the line VAT amounts**. Journals, VAT rows, receipts, notes and the ZATCA XML all carry these figures. |
| ZATCA (XML Implementation Standard v1.2 §10 and rule BR-S-09; Data Dictionary 2023-05-19) | Amounts are two decimals, rounded half-up. The VAT **category amount** (BT-117) must equal the category taxable amount (BT-116) × rate, **rounded once at document level**, within 0.01 tolerance of the sum of line VAT amounts is *not* the test: the category total is. Line VAT (KSA-11) is informational and must itself be the rounded line value. |

The two agree whenever the per-line rounding residues do not accumulate past half a halala. They disagree on specific combinations only.

## 2. Worked examples (15 % VAT, SAR)

| Invoice | Lines | Seera VAT (sum of lines) | ZATCA document-level VAT | Difference | Outcome today |
| --- | --- | --- | --- | --- | --- |
| E1 | 10 × 100.00; 5 × 200.00 | 150.00 + 300.00 = 450.00 | 2,000.00 × 15 % = 300.00 … (taxable 2,000.00 → 300.00) | 0.00 | passes (this was CLEARED on the sandbox on 17 and 22 Oct) |
| E2 | 1 × 1.03; 1 × 1.03 | 0.15 + 0.15 = **0.30** (0.1545 rounds to 0.15 each) | 2.06 × 15 % = 0.309 → **0.31** | −0.01 | **blocked locally** (BR-S-09 message on the record; observed on the sandbox run as the local refusal) |
| E3 | 3 × 33.33 | 99.99 × 15 % = 14.9985 → 15.00 (one line) | 99.99 × 15 % → 15.00 | 0.00 | passes |
| E4 | 7 lines × 0.03 | 7 × round(0.0045) = 7 × 0.00 = **0.00** | 0.21 × 15 % = 0.0315 → **0.03** | −0.03 | blocked locally |
| E5 | 2 × 500.00; 3 × 250.50 | 150.00 + 112.73 (112.725 → 112.73) = **262.73** | 1,751.50 × 15 % = 262.725 → **262.73** | 0.00 | passes (print-view sample invoice) |
| E6 | 12.5 × 199.99 | 2,499.875 → taxable 2,499.88; VAT 374.98 | taxable 2,499.88 × 15 % = 374.982 → 374.98 | 0.00 | passes |

Frequency in practice: construction progress claims have few lines with round prices, so mismatches are rare; they appear with many small-value lines or prices ending in odd halalas.

## 3. Option A — keep the existing calculation, block mismatching documents at ZATCA (current state)

| Aspect | Effect |
| --- | --- |
| Arithmetic | Unchanged everywhere. |
| GL / VAT reconciliation | Unchanged: journal VAT = VAT row = invoice VAT = XML VAT for every document that passes. |
| Posted invoices | Untouched. |
| Credit / debit notes | Same per-line calculation at the invoice rate; the same local block applies to a note whose lines accumulate residue. |
| Historical data | Untouched. |
| Operations | When the local validator refuses a document, Finance edits the draft before approval (adjust a price by 0.01 or split a line) so the per-line sum equals the document-level figure; the record shows both figures and the rule id. A posted invoice that fails cannot be sent; it must be corrected by a credit note and re-invoiced. |
| Risk | A posted standard invoice that cannot be cleared is a business incident (clearance is required before the invoice is valid for the buyer). Mitigation: validate before approval (the "Generate & validate XML" preview exists on every record; the form could run it on save in a later sprint). |

## 4. Option B — prospectively adopt document-level rounding for new invoices (after Finance authorisation)

| Aspect | Effect |
| --- | --- |
| Arithmetic | At approval (not at draft save), Seera computes the VAT per category on the category taxable total (BR-S-09) and allocates the rounding difference (at most a few halalas) to one line, by convention the last line, so that line VAT still sums to the header. Line taxable amounts never change. |
| GL / VAT reconciliation | Still exact: journal VAT = VAT row = invoice VAT = XML VAT, now equal to the document-level figure. |
| Posted invoices | Never recalculated; the rule applies from a cut-over date to documents approved after it. |
| Credit / debit notes | Notes use the same rule from the cut-over; a note against an old invoice is valid on its own (its own category total), so no retroactive effect. |
| Historical data | Untouched. Reports mixing old and new documents stay internally consistent because every document reconciles to its own posted figures. |
| Work | PostingService / approval path, invoice and note totals at approval, tests for the allocation rule and for the AR / VAT report invariants, user-guide wording. Estimated one focused sprint; no migration. |
| Risk | The displayed line VAT of one line may differ by 0.01 from qty × price × rate; customers rarely notice but the print view should footnote it. Needs Finance sign-off on the allocation line choice. |

## 5. Option C (rejected): send document-level VAT in the XML while the ledger keeps per-line VAT

Breaks the rule that the XML carries the posted invoice's own figures; the VAT return and the cleared documents would disagree by halalas across the period. Not offered.

## 6. Recommendation and decision record

Recommendation: **A now, B as a scheduled change** if Finance wants the local refusals to disappear. Either way, historical posted invoices are never mutated.

| Decision | Chosen by | Date | Notes |
| --- | --- | --- | --- |
| ☐ Option A (keep, block) | | | default until a choice is recorded here |
| ☐ Option B (prospective document-level rounding from cut-over date ______) | | | requires the implementation sprint; cut-over date to be set by Finance |
