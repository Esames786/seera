# Codex Prompt — Full System Test, Seera Construction ERP

Copy everything below the line into Codex.

---

You are testing a Laravel 12 construction ERP called Seera. Do a full, adversarial
review and functional test of the whole system. Report findings; do not refactor
architecture or start new feature phases.

## Environment

- Laravel 12, PHP 8.3, MySQL for dev, SQLite in-memory for tests
- PHP is not on PATH. Use: `d:/laragon2/bin/php/php-8.3.16-Win32-vs16-x64/php.exe`
- Node is not on PATH. Use: `d:/laragon2/bin/nodejs/node-v20.20.1-win-x64`
- Admin login: `admin@example.com` / `password` (Omar Mukhtar, General Manager)

Setup:
```
php artisan migrate:fresh --seed --force
php artisan test
npm run build
```

## What is built (phases 1-6)

1. Access control: users, roles, permission matrix, role hierarchy, approval workflows, activity logs
2. Master setup: company, branches, departments, designations, projects, sites with geo-fence, warehouses, suppliers, customers, expense categories
3. HR & payroll: employees, documents, shifts, attendance, leaves, overtime, salary structures, payroll runs, end-of-service
4. Accounting: chart of accounts, journal entries, general ledger, AP, AR, VAT, ZATCA foundation, financial reports, cost centers, automatic posting rules
5. Projects + site expenses: NOT IMPLEMENTED, reference package only
6. Inventory: items, categories, units, stock on hand, purchase requests, purchase orders, goods receipts, stock issues, transfers, adjustments, stock ledger, inventory reports

## Priority 1 — business rules that must hold

Verify each by exercising the UI or writing a test. These are the rules the
system is supposed to enforce, so try hard to break them.

Accounting
- A journal entry whose debits do not equal its credits must be rejected on save AND on post
- A posted journal entry cannot be edited or deleted
- Supplier bill approval posts: debit expense + debit input VAT, credit accounts payable
- Supplier payment posts: debit accounts payable, credit cash or bank
- Customer invoice approval posts: debit accounts receivable, credit revenue + credit output VAT
- Customer receipt posts: debit cash or bank, credit accounts receivable
- A payment or receipt larger than the outstanding balance must be rejected
- Trial balance total debit must equal total credit
- VAT payable must equal output VAT minus input VAT

Inventory
- Goods receipt posting increases stock by the ACCEPTED quantity, not the received quantity
- Stock issue decreases stock at the warehouse average cost
- Issuing more than the available quantity must be rejected and must leave stock untouched
- Transfer dispatch removes from source; stock must NOT appear at the destination until receive
- A stock adjustment must not change stock until it is approved AND posted
- Every movement writes a stock ledger entry; the ledger must replay to the current balance
- Posted GRN, issue and adjustment documents must be read-only

HR
- Payroll: net = basic + allowances + approved overtime - deductions
- Attendance is unique per employee per day
- End-of-service gratuity: half a month's salary per year for the first five years,
  a full month per year after that, on the final basic salary
- Resignation scales that gratuity by length of service; termination, contract
  completion and force majeure pay 100%
- Check the resignation tiers in `app/Services/Hr/GratuityCalculator.php` against
  Saudi Labour Law articles 84, 85 and 87 and report any mismatch

## Priority 2 — recent client changes, verify these specifically

- Employee form sections: A Personal, B Employment, C Documents, D Payroll, E Document Attachments, F Access
- "Employee Classification" (Sponsorship / Freelancer) is required and saves
- Section C is named "Documents", NOT "Saudi Documents", and holds IQAMA, passport,
  insurance and driving license numbers with expiry dates
- Payroll section has housing, transport, food, fuel and other allowance fields
- Documents are attached from the employee form; the Employee Documents screen is a
  read-only register with no add option
- Selecting a department filters the Designation dropdown to that department only.
  Same for project filtering the Site dropdown. Check on both the employee form and
  the user form. Confirm an already-saved value that does not match is cleared rather
  than silently submitted
- The Departments list must NOT contain "Equipment"
- Org hierarchy matches: Omar Mukhtar GM; Nabeel Mukhtar Project Manager; Zubair Ahmed
  Accounts Manager; Zulfiqar Purchase Manager; Waleed HR Manager; Abdullah Mukhtar
  Marketing Manager; Zafar Ali Site In-Charge; Abdullah Shahmeer Account Assistant;
  Ayaz Purchase Assistant; Kamran Mechanic; Shaban and Rizwan Operators

## Priority 3 — cross-cutting checks

- Visit every route in `php artisan route:list`. Report any 500, missing view or
  undefined variable. There are around 290 routes
- Sidebar: eight collapsible groups, active group opens automatically, collapse state
  survives a reload, no dead links
- Permission matrix: 15 action columns. Saving must not silently revoke actions that
  are not rendered. This was a real bug once; confirm it cannot come back
- Every destructive action either soft-deactivates or is blocked when history exists
- N+1 queries on index screens. Install a query counter and report the worst offenders
- Validation: submit each create form empty and confirm the errors are useful
- Money and quantity rounding: look for float drift in payroll, VAT and stock valuation

## Priority 4 — known gaps, confirm and do not "fix"

- Phase 5 (projects + site expenses) is not implemented
- ZATCA is foundation only: UUID, QR payload, XML path, hash are generated locally.
  There is no live clearance API and there must not be one yet
- The system is English only. Arabic and RTL are not implemented
- `public/build.zip` is a tracked build artifact, probably stale
- Sidebar badges run four COUNT queries per page render, uncached

## Deliverable

A single report with:
1. Critical bugs: data loss, wrong money, broken business rule. Include exact
   reproduction steps and the file and line
2. Functional bugs: broken screens, bad validation, wrong totals
3. UX and consistency issues
4. Performance: slow queries, N+1
5. Security: mass assignment, missing authorization, unescaped output, file upload handling
6. Test coverage gaps: which rules above are NOT covered by a test

Rank everything by severity. For each finding give a concrete failing case, not a
general observation. If you claim a rule is broken, prove it with steps or a test.
