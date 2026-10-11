# GOSI readiness audit — 11 October 2026

Audit only. **GOSI remains NOT YET OPERATIONAL.** Nothing in this document is implemented; no contribution
rate is stored or applied anywhere in Seera. This note lists what the system already holds, what is missing,
the policy decisions the owner / client must confirm, and the configurable architecture recommended for a
future, separately approved sprint. Rates and caps are deliberately **not** quoted here: they must come from
the current GOSI regulations / the client's registration, not from memory.

## 1. What exists today

| Need | Existing field / behaviour | Where | Usable for GOSI? |
| --- | --- | --- | --- |
| Nationality | `employees.nationality` (free text, lookup list `nationality` incl. "Saudi") | HR-EMP-002/004 | Partly: a text value; "Saudi" is not a guaranteed flag |
| Saudi / non-Saudi distinction | none; `employee_classification` (Sponsorship / …) is a **sponsorship** concept, not a GOSI category | `employees.employee_classification` | No |
| Basic salary | `employees.basic_salary` and the effective-dated `salary_structures.basic_salary` | HR-SAL-001 | Yes (history by effective date) |
| Housing allowance | `employees.housing_allowance`, `salary_structures.housing_allowance` | HR-SAL-001 | Yes |
| Other allowances | transport, food, fuel, other on both records | HR-SAL-001 | Available, but their GOSI treatment is a policy decision |
| Contributory wage | not stored; would be derived (basic + housing in the common rule, to be confirmed) | — | No |
| Contribution category (annuities, occupational hazards, SANED) | none | — | No |
| Employee / employer rates | none | — | No |
| Wage caps / floors | none | — | No |
| Effective dates of rates | none | — | No |
| GOSI registration number (employer, employee) | none | — | No |
| Date of birth / age (some branches depend on age) | none on `employees` | — | No |
| Payroll deduction line for GOSI | `payroll_run_items.total_deductions` is one aggregated figure; the Payroll posting rule credits one **Deduction Liability Account** | WF-021 | Partly: a GOSI employee share could be posted there, but it is not separated from other deductions |
| Employer contribution expense | no separate expense / liability mapping | FIN-PR-004 | No |
| Payslip | shows Deductions as one figure | HR-PAY-005 | Would need a GOSI line |

## 2. What is missing

1. A reliable **GOSI category** per employee (Saudi national / GCC national / non-Saudi, or "exempt"), independent of free-text nationality and of sponsorship classification.
2. **Employee and employer rate tables** with effective dates and the components each covers (annuities, occupational hazards, unemployment insurance), configurable by Finance, never hard-coded.
3. **Contributory wage definition** (which salary components count) and **minimum / maximum wage caps**, configurable with effective dates.
4. **Employer and employee registration numbers** if a GOSI file or reconciliation is ever required.
5. A **separate GOSI deduction** on the payroll item (employee share) and a **separate employer contribution** (expense + liability) so the payroll journal can post: Dr salary expense (gross), Dr GOSI employer expense, Cr payroll payable (net), Cr GOSI payable (employee share + employer share), Cr other deductions.
6. **Date of birth** on the employee record if any rule depends on age.
7. A **monthly GOSI statement / reconciliation** screen or export (optional, later).

## 3. Policy decisions required before any implementation

- Which components form the contributory wage for this client (basic + housing is common, but the client's registration and the regulation in force decide).
- The exact employee and employer percentages per category and per component, with their effective dates, from the current GOSI regulation and the client's registration — to be entered by Finance in a configuration screen, not typed into code.
- Treatment of the employer share: separate expense account and liability account on the Payroll posting rule.
- Whether GOSI is deducted from a partial month (joiners / leavers) and how proration applies — note that Seera has **no attendance-based proration** today.
- Whether non-Saudi employees carry occupational-hazard contributions only, and how GCC nationals are treated.
- Rounding rule (per employee, per run) and the currency precision.

## 4. Recommended architecture (future sprint, after policy confirmation)

1. **Master data**: `gosi_rate_schedules` (category, component, employee_rate, employer_rate, wage_floor, wage_cap, effective_from, effective_to, status), maintained on a Finance screen with history; `employees.gosi_category` (enum), `employees.gosi_number`, optional `employees.date_of_birth`.
2. **Calculation** inside Process Payroll (not at approval): per item store `gosi_wage`, `gosi_employee`, `gosi_employer`, and keep `total_deductions` = other deductions + `gosi_employee` so net stays `gross − total_deductions`. Rates resolved by the schedule effective on the period end; a missing schedule for a category blocks processing with a readable message (same pattern as the posting-rule checks).
3. **Accounting**: extend the Payroll posting rule with `gosi_expense_account_id` (employer share) and `gosi_liability_account_id`; the Phase 1 line builder adds Dr employer expense (employee's project / cost center) and Cr GOSI payable (employee + employer shares). Deductions other than GOSI keep the existing Deduction Liability Account.
4. **Settlement**: paying GOSI is a separate liability settlement (Dr GOSI payable, Cr bank), reusing the Phase 2 settlement pattern with its own idempotency key and reversal.
5. **Payslip / exports**: GOSI employee share as its own line; a monthly GOSI contribution list export using stored item values (generic CSV first; an official format only from a verified specification).
6. **Tests**: rate resolution by date, category coverage, caps, rounding, journal balance with the extra lines, settlement idempotency, payslip line, and regression on Payroll → GL and salary settlement.

## 5. Status truth for documentation

- GOSI: **NOT YET OPERATIONAL** (no fields beyond nationality and salary components, no rates, no calculation, no posting).
- Official Saudi WPS / Mudad file: **NOT YET OPERATIONAL** (Phase 2 ships a generic bank transfer CSV only).
