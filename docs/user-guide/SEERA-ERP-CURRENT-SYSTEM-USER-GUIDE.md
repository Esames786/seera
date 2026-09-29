# SEERA ERP
# Current System User Guide

| | |
|---|---|
| **Version** | 1.2 |
| **System state** | Feature branch `feature/seera-connected-workspaces-2026-09-23`, 29 September 2026: Project Phase A retained; Employee/Customer/Supplier previous-section navigation and multiple reporting-parent records added. All-required-parent approval processing remains pending. This describes code, not a claim of production deployment. |
| **Prepared** | 29 September 2026 |
| **Status** | Current implemented system only. Planned features are not described as available. |
| **Companion files** | [Screen Index](SCREEN-INDEX.md) · [Workflow Index](WORKFLOW-INDEX.md) |

This guide is written for the people who use Seera every day: the Super Admin, the Finance Manager, the HR Manager, Project Managers, Site In-Charges, purchasing and warehouse staff, trainers and new employees. It uses simple English and fictional example data. Every screen has a permanent Screen ID (for example **FIN-AP-002**) so that training material, support requests and later PDF versions can refer to the same page.

Status labels used in this guide:

| Label | Meaning |
|---|---|
| AVAILABLE | Built and usable today. |
| PARTIAL | Usable, but part of the expected function is missing. The text says what is missing. |
| FOUNDATION ONLY | Data or configuration exists, but the process that uses it is not built. |
| NOT YET OPERATIONAL | A menu item or plan exists; nothing usable exists. |

## Training dataset used in the examples

All examples in this guide use the same fictional data. None of it is real.

| Item | Example value |
|---|---|
| Company | Seera Construction Company |
| Customer | Al Noor Development Co. (code CUS-021) |
| Supplier | Gulf Steel Trading (code SUP-014) |
| Project | Riyadh Commercial Tower (code PRJ-RCT-01) |
| Site / Location | Riyadh Tower - Main Site |
| Warehouse | Riyadh Site Warehouse |
| Employee | Ahmed Hassan (code EMP-0042) |
| Material | Reinforcement Steel 16mm (code ITM-0031) |
| Purchase Request | PR-2026-0010 |
| Purchase Order | PO-2026-0012 — 10,000 kg of Reinforcement Steel 16mm at SAR 3.00 per kg (SAR 30,000 + VAT 4,500 = 34,500) |
| Goods Receipts | GRN-2026-0008 (6,000 kg) and GRN-2026-0009 (4,000 kg) |
| Supplier Bill | GST-INV-1045 (invoices GRN-2026-0008: SAR 18,000 + VAT 2,700 = 20,700) |
| Customer Invoice | INV-2026-0031 |
| VAT rate | 15% |

## Table of contents

1. [About Seera ERP](#1-about-seera-erp)
2. [Login and Navigation](#2-login-and-navigation)
3. [Dashboard](#3-dashboard)
4. [Users, Roles and Permissions](#4-users-roles-and-permissions)
5. [Master Setup](#5-master-setup)
6. [HR & Payroll](#6-hr--payroll)
7. [Customers](#7-customers)
8. [Suppliers](#8-suppliers)
9. [Accounting & Finance](#9-accounting--finance)
10. [Inventory & Purchasing](#10-inventory--purchasing)
11. [Projects](#11-projects)
12. [Marketing](#12-marketing)
13. [Activity Logs / Audit](#13-activity-logs--audit)
14. [Current ZATCA Foundation](#14-current-zatca-foundation)
15. [Reports](#15-reports)
16. [Common Tasks](#16-common-tasks)
17. [End-to-End Workflows](#17-end-to-end-workflows)
18. [Troubleshooting](#18-troubleshooting)
19. [Glossary](#19-glossary)
20. [Current Limitations / Not Yet Operational](#20-current-limitations--not-yet-operational)
21. [Quick Start by Role](#21-quick-start-by-role)

---

## 1. About Seera ERP

| | |
|---|---|
| **Chapter number** | 1 |
| **Chapter name** | About Seera ERP |
| **Purpose** | What Seera is, how this guide is organised, the Connected Workspace Standard and the standard form buttons. |
| **Primary roles** | All users |
| **Screens in this chapter** | — (no screens; reference chapter) |

Seera is the construction ERP of Seera Construction Company. It runs in the web browser. Everyone signs in with their own account, and every account has a role that decides which screens and buttons the person can use.

What Seera covers today:

- **Administration**: users, roles, permission matrix, role hierarchy, approval workflow configuration, activity logs.
- **Master Setup**: company profile, branches, departments, designations, projects, locations (sites), warehouses, expense categories, suppliers, customers.
- **HR & Payroll**: employees with a connected workspace, documents and IQAMA register, shifts, attendance (manual entry), leaves, overtime, salary structures, payroll runs, end of service.
- **Accounting & Finance**: chart of accounts, journal entries, general ledger, accounts payable, accounts receivable, VAT periods, local ZATCA records, cost centers, posting rules, financial reports.
- **Inventory & Purchasing**: items, categories, units, stock on hand, purchase requests, purchase orders, goods receipts, stock issues, transfers, adjustments, stock ledger, inventory reports.
- **Marketing**: leads, visits, visit report, conversion to customer.

What Seera does not cover yet is listed honestly in chapter 20.

### The three ways you work with a record

Seera follows one standard for the main business records (Employee, Supplier, Customer):

```
List  →  View (read-only)  →  Edit / Manage (connected workspace)
```

- **List**: search, filter and choose a record.
- **View**: read everything about the record and its related information. Nothing on a View page changes data.
- **Edit / Manage**: the *connected workspace*. The record's identity stays at the top of the page. Tabs show the profile and the related information (for a supplier: projects, purchase orders, goods receipts, bills, payments, accounting, activity). Each tab saves on its own. There is no "Save All".

Business actions such as **Approve**, **Post**, **Pay**, **Receive**, **Finalize**, **Process** and **Dispatch** are always separate buttons on the document itself. Saving a profile never approves or posts anything.

### The Save buttons

Every form that has been moved to the new standard shows the same buttons:

| Button | What it does |
|---|---|
| Save | Saves and keeps the record open. On a document (bill, invoice, journal) you land on its details page, where Approve or Post is. |
| Save & new | Saves and opens an empty form for the next record. |
| Save & close | Saves and returns to the list, or to the page you came from. |
| Cancel | Leaves without saving. |

Forms that still use the older layout show **Save** or **Update** plus **Cancel** (chapter 20 lists them).

### Unsaved changes protection

If you change something and then try to leave the page, Seera shows a dialog with **Keep editing**, **Discard & leave** and **Save current form**. This works on every form in the system.

---

## 2. Login and Navigation

| | |
|---|---|
| **Chapter number** | 2 |
| **Chapter name** | Login and Navigation |
| **Purpose** | Sign in, change your password and find your way around the menu. |
| **Primary roles** | All users, All new users |
| **Screens in this chapter** | ADM-001, ADM-002, ADM-003, ADM-004 |

### [ADM-001] Login

Web address: `https://seera.tech-brit.co.uk/login`

Purpose:
Sign in to Seera with your email address and password.

Who uses it:
All users.

Navigation:
Open the Seera address in your browser. The login page appears.

Permission required:
None. The account must be active.

What you see:
The Seera logo, the email and password fields, a "remember me" box, a **Sign In** button and a **Forgot password** link.

Fields:

| Field | What to enter | Example | Required? | Notes |
|---|---|---|---|---|
| Email Address | Your work email | ahmed.hassan@example.sa | Yes | Given to you by the administrator |
| Password | Your password | (hidden) | Yes | After five wrong attempts in one minute you must wait |

Buttons:

| Button | What it does |
|---|---|
| Sign In | Checks the details and opens the Dashboard |
| Forgot password | Opens ADM-002 |

Important:
A new account created without a password uses a temporary default password that the administrator gives you. Seera then forces you to set your own password before you can do anything else (ADM-004).

What happens after Sign In:
The Dashboard (ADM-010) opens. If your account is new, the "Set Your Password" page opens first.

Common mistakes:
- Typing the username instead of the email address.
- Trying to sign in with an inactive account. Ask the administrator to activate it.
- Leaving "remember me" ticked on a shared computer.

Related screens:
ADM-002 Forgot Password, ADM-004 Set Your Password, ADM-010 Dashboard.

### [ADM-002] Forgot Password and [ADM-003] Reset Password

Web address: `https://seera.tech-brit.co.uk/forgot-password` (ADM-002) · `https://seera.tech-brit.co.uk/reset-password/{token}` (ADM-003)

Purpose:
Get a link by email to choose a new password.

Who uses it:
All users.

Navigation:
Login → Forgot password.

Permission required:
None.

Fields:

| Field | What to enter | Example | Required? | Notes |
|---|---|---|---|---|
| Email Address | Your work email | ahmed.hassan@example.sa | Yes | The same message is shown whether or not the address exists |
| New Password / Confirm New Password (ADM-003) | A new password, twice | (hidden) | Yes | At least 8 characters |

Buttons:

| Button | What it does |
|---|---|
| Send Reset Link | Sends the email |
| Reset Password | Saves the new password and signs you in |

Important:
Status: **PARTIAL**. The email is only delivered when the server's mail settings have been configured. If you do not receive the email, ask the Super Admin to set your password from the Users screen (USR-004) instead.

Related screens:
ADM-001 Login.

### [ADM-004] Set Your Password (first sign-in)

Web address: `https://seera.tech-brit.co.uk/admin/set-password`

Purpose:
Replace the temporary password with your own the first time you sign in.

Who uses it:
Every new user.

Navigation:
Shown automatically after the first sign-in. You cannot open other pages until you finish it.

Fields:

| Field | What to enter | Example | Required? | Notes |
|---|---|---|---|---|
| Current Password | The temporary password you were given | (hidden) | Yes | |
| New Password | Your own password | (hidden) | Yes | At least 8 characters, different from the current one |
| Confirm New Password | The same password again | (hidden) | Yes | |

Buttons:

| Button | What it does |
|---|---|
| Save Password and Continue | Saves and opens the Dashboard |
| Sign out instead | Signs out without changing anything |

### Navigation basics

- The **left menu** is grouped: Main, Administration, Master Setup, Operations, HR Registers & Approvals, Finance, Inventory, Marketing, Reports, Settings. You only see the groups and items your role allows.
- The **top bar** shows the language switch (English / Arabic, the whole screen turns right-to-left in Arabic) and your name with **Sign out**.
- Numbers next to menu items are queues: pending leaves, unposted journals, follow-ups due, purchase requests waiting, low-stock rows.
- Lists have a **filter bar** (search box and drop-downs) and a **Reset** button. Lists show 10 rows per page.
- **View** opens a read-only page. **Edit** or **Edit / Manage** opens the form or the workspace. **Delete** or **Deactivate** asks for confirmation first.
- Menu items marked "Coming Soon" open an information page only (chapter 20).
- Every screen in this guide shows its **Web address** under the heading. `{id}` stands for the record number you see in the browser's address bar after opening a record from its list (for example `/admin/master/suppliers/14/edit`). The Screen Index lists all addresses in one table.

---

## 3. Dashboard

| | |
|---|---|
| **Chapter number** | 3 |
| **Chapter name** | Dashboard |
| **Purpose** | Read the company figures on the home page. |
| **Primary roles** | All users |
| **Screens in this chapter** | ADM-010 |

### [ADM-010] Dashboard

Web address: `https://seera.tech-brit.co.uk/admin/dashboard`

Purpose:
A quick picture of the company: staff, roles, projects, sites, recent activity.

Who uses it:
All users (the cards depend on your permissions).

Navigation:
Main → Dashboard.

Permission required:
Dashboard — view.

What you see:
Cards for Total Staff, Active Roles, Pending Approvals, Inactive Users, Total Projects, Active Sites and Geo-Fenced Sites; a table of Active Projects (code, project, client, manager, budget, status) and Recent Activity with a link to the Activity Logs.

Important:
"Pending Approvals" counts approval workflow definitions plus users waiting for activation; it is not a queue of documents waiting for approval. Use the Accounting Dashboard (FIN-DASH-001), HR Dashboard (HR-DASH-001) and Inventory Dashboard (INV-DASH-001) for real work queues.

Related screens:
FIN-DASH-001, HR-DASH-001, INV-DASH-001, ADM-020.

---

## 4. Users, Roles and Permissions

| | |
|---|---|
| **Chapter number** | 4 |
| **Chapter name** | Users, Roles and Permissions |
| **Purpose** | Create logins, roles, the permission matrix and access scope. |
| **Primary roles** | Super Admin |
| **Screens in this chapter** | USR-001, USR-002, USR-003, USR-004, ROL-001, ROL-002, ROL-003, ROL-004, ROL-005, ROL-006, ROL-007, ROL-008, ROL-009, ROL-010 |

How access works in Seera:

1. A **Role** is a named set of permissions (for example Accounts Manager). Each permission is a module plus an action: view, create, edit, delete, approve, reject, export, mobile access, post, process, retry, receive, issue, transfer, adjust.
2. A **User** has one primary role. The role also carries an **access scope**: All Company / Company Level (sees everything), Project Level (sees only the project set on the user), Site Level or Warehouse Level.
3. Every screen and button checks the module and action. A user without "Accounts Payable — approve" does not see the Approve button and cannot approve by any other route.

### [USR-001] Users List

Web address: `https://seera.tech-brit.co.uk/admin/users`

Purpose:
Find, open, edit and deactivate user accounts.

Who uses it:
Super Admin.

Navigation:
Administration → Users.

Permission required:
Users — view.

What you see:
Cards (Total Users, Active Users, Mobile App Users, Locked / Inactive), filters (search, department, role, status) and the list: Employee ID, Name, Email / Phone, Department, Primary Role, Assigned Project/Site, Mobile Access, Last Login, Status, Actions (View, Edit, Deactivate).

Related screens:
USR-002, USR-003, USR-004.

### [USR-002] Add User

Web address: `https://seera.tech-brit.co.uk/admin/users/create`

Purpose:
Create a sign-in account, optionally from an existing employee.

Who uses it:
Super Admin.

Navigation:
Administration → Users → + Add New User.

Permission required:
Users — create.

What you see:
Sections: Role & Quick Capabilities, Profile Identity, Employment Information, Access & Security, Project / Site / Warehouse Scope. At the top you can search an existing employee and copy their details.

Fields:

| Field | What to enter | Example | Required? | Notes |
|---|---|---|---|---|
| Primary Role | The role for this account | Site In-Charge | Yes | "+ New" creates a role without leaving the form |
| Full Name | The person's name | Ahmed Hassan | Yes | |
| Employee ID | The employee code | EMP-0042 | No | Filled automatically when you pick an employee |
| Email Address | Work email, used to sign in | ahmed.hassan@example.sa | Yes | Must be unique |
| Phone Number | Mobile number | +966 50 123 4567 | No | |
| Language | English or Arabic | English | No | |
| Username | Short login name | ahmed.hassan | No | |
| Department, Designation | Organisation position | Site Operations / Site In-Charge | No | "+ New" dialogs available |
| Employee Classification, Joining Date, Contract Type, Iqama Number, Iqama Expiry Date | Employment details | Sponsorship / 01-Mar-2024 / Full Time | No | Copied from the employee when linked |
| Account Status | active, inactive, locked, pending | active | Yes | |
| Mobile App Access | Yes / No | Yes | No | Stored on the account; the mobile app itself is not built yet |
| Access Start Date / Access End Date | Temporary access window | 01-Oct-2026 to 31-Dec-2026 | No | Informational on the user; enforced dates are set on ROL-007 |
| Assigned Branch / Project / Site / Warehouse | What the account may see when the role is scoped | Riyadh Commercial Tower | No | Required in practice for Project, Site or Warehouse scoped roles |
| Upload Profile Photo | Picture | photo.jpg | No | |

Buttons:

| Button | What it does |
|---|---|
| Save & stay | Saves and stays on the user's edit page |
| Save User | Saves and returns to the list |
| Cancel | Leaves without saving |

Important:
If you save without a password, the account gets the temporary default password and the user must change it at first sign-in.

What happens after Save:
The user appears in the list. If you linked an employee, the employee's record shows the linked account (HR-EMP-004, tab System Account).

Common mistakes:
- Giving a Project Level role without choosing the Assigned Project: the user will see empty lists.
- Creating a user for an employee who already has an account: link instead, from the employee workspace.
- Forgetting to tell the user their temporary password.

Related screens:
USR-001, USR-004, HR-EMP-004, ROL-002.

### [USR-003] User Details and [USR-004] Edit User

Web address: `https://seera.tech-brit.co.uk/admin/users/{id}` (USR-003) · `https://seera.tech-brit.co.uk/admin/users/{id}/edit` (USR-004)

USR-003 is read-only: an Access Summary (Employee ID, Primary Role, Parent Role, Access Scope, Department, Designation, Branch, Contract Type, Classification, Iqama Number, Mobile App Access, Two Factor Auth, Last Login) and the user's Recent Activity. USR-004 is the same form as USR-002 with the same buttons.

Important:
Saving the Edit User form sets the user's primary role. Extra roles given on Assign Users (ROL-007) are replaced by that primary role when the user form is saved. Reassign them afterwards on ROL-007 if needed.

### [ROL-001] Roles List, [ROL-002] Create Role, [ROL-003] Role Details, [ROL-004] Edit Role

Web address: `https://seera.tech-brit.co.uk/admin/roles` (ROL-001) · `https://seera.tech-brit.co.uk/admin/roles/create` (ROL-002) · `https://seera.tech-brit.co.uk/admin/roles/{id}` (ROL-003) · `https://seera.tech-brit.co.uk/admin/roles/{id}/edit` (ROL-004)

Purpose:
Define what each job may do.

Who uses it:
Super Admin.

Navigation:
Administration → Roles → + Add New Role (or View / Edit / Assign on a row).

Permission required:
Roles — view / create / edit.

What you see (Create / Edit):
Sections Basic Role Information and Access Scope, then the permission grid (one row per module, one column per action, with "All", "Select all visible" and "Clear visible").

Fields:

| Field | What to enter | Example | Required? | Notes |
|---|---|---|---|---|
| Role Name | Job name | Site Accountant | Yes | |
| Role Code | Short code | SITE_ACCOUNTANT | No | Generated when left blank; cannot be changed later |
| Department | Owning department | Accounts | No | "+ New" available |
| Role Type | Type list | Operational | No | |
| Primary reporting parent | Role above this one in the primary hierarchy | Accounts Manager | No | Preserves the existing activity visibility hierarchy; permissions are not inherited |
| Additional reporting parents | Tick one or more additional roles | Purchase Manager and Project Manager | No | Saved reporting links only: no additional data access, permissions or automatic approval authority. Duplicate, self and cyclic links are rejected. |
| Role Level | 1 (highest) to 5 | 3 | Yes | |
| Status | active / inactive | active | Yes | |
| Default Dashboard | Landing page label | Accounting Dashboard | No | Label only |
| Access Scope | All Company, Company Level, Project Level, Site Level, Warehouse Level | Project Level | Yes | Decides what data the role's users see |
| Mobile App Access | Yes / No | No | No | Stored only |
| Can Approve Child Requests? | Yes / No | No | No | Stored only; approvals are not routed by this |
| Start with permissions of | Copy another role's grid | Account Assistant | No | |
| Permission grid | Tick module × action | Accounts Payable: view, create, edit | No | Only the visible rows are changed when you save |

Buttons:

| Button | What it does |
|---|---|
| Save Role / Update Role | Saves the role and its permissions |
| Cancel | Leaves without saving |

Important:
A system role, a role that still has users, or a role with child roles cannot be deleted.
This also protects a role used as an additional reporting parent. Listing, View, Edit and Hierarchy show the saved parents. Removing all additional checkboxes and saving clears only those extra reporting links. The confirmed requirement that **all required parents approve** is not enforced by these links; the approval runtime remains pending.

Related screens:
ROL-005 Permission Matrix, ROL-007 Assign Users.

### [ROL-005] Permission Matrix

Web address: `https://seera.tech-brit.co.uk/admin/roles/permission-matrix`

Purpose:
Edit the permissions of one role across all modules on one page.

Navigation:
Administration → Permission Matrix → choose the role and, optionally, a module group → Apply.

Permission required:
Roles — edit to save.

Buttons: Apply (load the role), Select all visible, Clear visible, Save Permissions, Reset. Only the rows shown on screen are changed by Save; hidden rows keep their current permissions.

### [ROL-006] Role Hierarchy

Web address: `https://seera.tech-brit.co.uk/admin/roles/hierarchy`

Purpose:
See the tree of roles and the selected role's details.

Status: **PARTIAL**. The tree shows the primary hierarchy, with named cross-links for additional reporting parents and all parents in the details card. Permissions are not inherited from a parent role. Additional links do not change access scope or implement all-required-parent approval processing.

Buttons: Add Child Role (opens ROL-002 with the parent preset), Edit Selected Role.

### [ROL-007] Assign Users to Role

Web address: `https://seera.tech-brit.co.uk/admin/roles/assign-users`

Purpose:
Move users into or out of a role, with an optional temporary access window.

Navigation:
Administration → Assign Users → choose the role → Apply Filter.

Permission required:
Roles — edit to save.

What you see:
Two lists (available users, assigned users) with arrow buttons, Add All and Remove All, and a Temporary Access Option section (Temporary Access, Access Start Date, Access End Date, Applies To, Reason).

Important:
Temporary dates are applied to every user you submit in that save, and the role is only active inside the dates. The user's own Edit form resets extra roles to the primary role (see USR-004).

### [ROL-008] Approval Workflows, [ROL-009] Create, [ROL-010] Edit

Web address: `https://seera.tech-brit.co.uk/admin/roles/approval-workflows` (ROL-008) · `https://seera.tech-brit.co.uk/admin/roles/approval-workflows/create` (ROL-009) · `https://seera.tech-brit.co.uk/admin/roles/approval-workflows/{id}/edit` (ROL-010)

Purpose:
Record who should approve what, step by step.

Status: **FOUNDATION ONLY**. The workflow (name, module, trigger action, department, scope, auto posting, notify requester, lock after approval, and steps with approver role, specific user, required, amount limit, SLA hours, escalation role, can reject, can send back) is saved and shown. **Approvals in the system do not follow these steps yet.** Today every Approve button is a single action by any user who has the approve permission for that module.

Buttons: + New Workflow, Preview, Edit, Delete, + Add Step, Save Workflow, Cancel.

---

## 5. Master Setup

| | |
|---|---|
| **Chapter number** | 5 |
| **Chapter name** | Master Setup |
| **Purpose** | Company, organisation, projects, sites, warehouses and expense categories that every document refers to. |
| **Primary roles** | Super Admin, HR Manager, Inventory Manager, Finance Manager |
| **Screens in this chapter** | MST-COM-001, MST-ORG-001, MST-BR-001, MST-BR-002, MST-BR-003, MST-BR-004, MST-DEP-001, MST-DEP-002, MST-DEP-003, MST-DEP-004, MST-DES-001, MST-DES-002, MST-DES-003, MST-DES-004, MST-WH-001, MST-WH-002, MST-WH-003, MST-WH-004, MST-EXP-001, MST-EXP-002, MST-EXP-003, MST-EXP-004 |

Master Setup holds the records every other module refers to. Set these up first, in this order: Company Profile → Organization Structure (branches, departments, designations) → Projects and Locations → Warehouses → Expense Categories → Suppliers and Customers.

Most master forms still use the older button set (**Save** / **Update** and **Cancel**). Suppliers and Customers use the full standard (chapters 7 and 8).

### [MST-COM-001] Company Profile

Web address: `https://seera.tech-brit.co.uk/admin/master/company-profile`

Purpose:
Company identity used on screens and, later, on invoices.

Who uses it:
Super Admin.

Navigation:
Master Setup → Company Profile.

Permission required:
Company Profile — edit to save.

What you see:
Sections Basic Company Information, Saudi Compliance & ZATCA, Address & Fiscal Settings.

Fields:

| Field | What to enter | Example | Required? | Notes |
|---|---|---|---|---|
| Company Name / Arabic Company Name | Legal names | Seera Construction Company | Yes / No | |
| Company Logo | Image file | logo.png | No | |
| Email, Phone, Website | Contact details | info@example.sa | Email and Phone yes | |
| CR Number, VAT Number | Registration numbers | 1010123456 / 300123456700003 | Yes | |
| ZATCA Registration Number, Invoice Mode, Digital Certificate Status | Compliance labels | — | No | Labels only; no live ZATCA connection (chapter 14) |
| Default VAT Rate (%) | Standard rate | 15 | No | |
| Country, City, Default Currency, Fiscal Year Start / End, Full Address | Location and fiscal settings | Saudi Arabia / Riyadh / SAR / 01-Jan / 31-Dec | No | |
| Status | active / inactive | active | No | |

Buttons: Save Company Profile, Cancel.

### [MST-ORG-001] Organization Structure

Web address: `https://seera.tech-brit.co.uk/admin/master/organization`

Purpose:
One hub for Branches, Departments and Designations. Each card shows the recent records, an **+ Add** dialog and an **Open full list** link.

Who uses it:
Super Admin, HR Manager.

Navigation:
Master Setup → Organization Structure.

Permission required:
View on any of Branches, Departments or Designations.

### Branches [MST-BR-001 … 004], Departments [MST-DEP-001 … 004], Designations [MST-DES-001 … 004]

Web address: `https://seera.tech-brit.co.uk/admin/master/branches` (MST-BR-001) · `https://seera.tech-brit.co.uk/admin/master/branches/create` (MST-BR-002) · `https://seera.tech-brit.co.uk/admin/master/branches/{id}` (MST-BR-003) · `https://seera.tech-brit.co.uk/admin/master/branches/{id}/edit` (MST-BR-004) · `https://seera.tech-brit.co.uk/admin/master/departments` (MST-DEP-001) · `https://seera.tech-brit.co.uk/admin/master/departments/create` (MST-DEP-002) · `https://seera.tech-brit.co.uk/admin/master/departments/{id}` (MST-DEP-003) · `https://seera.tech-brit.co.uk/admin/master/departments/{id}/edit` (MST-DEP-004) · `https://seera.tech-brit.co.uk/admin/master/designations` (MST-DES-001) · `https://seera.tech-brit.co.uk/admin/master/designations/create` (MST-DES-002) · `https://seera.tech-brit.co.uk/admin/master/designations/{id}` (MST-DES-003) · `https://seera.tech-brit.co.uk/admin/master/designations/{id}/edit` (MST-DES-004)

Each has a List (search, cards, table with View / Edit / Delete), an Add form, a Details page and an Edit form.

Fields:

| Record | Fields (required marked *) | Example |
|---|---|---|
| Branch | Branch Name *, Branch Code *, City *, Branch Manager, Phone, Email, Address, Status * | Riyadh Head Office, BR-RYD, Riyadh |
| Department | Department Name *, Department Code *, Department Head, Description, Status * | Site Operations, SITE |
| Designation | Designation Name *, Department *, Grade / Level, Default Role, Mobile App Access Default, Status *, Description | Site In-Charge, Site Operations, L3 |

Buttons: Save / Update, Cancel. Details pages show the related projects, warehouses, designations, users or employees.

Important:
Branches, departments and designations can also be created without leaving the User and Employee forms through the "+ New" dialogs.

### Projects [MST-PRJ-001 … 005] and Locations [MST-SITE-001 … 004]

Web address: `https://seera.tech-brit.co.uk/admin/master/projects` (MST-PRJ-001) · `https://seera.tech-brit.co.uk/admin/master/projects/create` (MST-PRJ-002) · `https://seera.tech-brit.co.uk/admin/master/projects/{id}` (MST-PRJ-003) · `https://seera.tech-brit.co.uk/admin/master/projects/{id}/edit` (MST-PRJ-004) · `https://seera.tech-brit.co.uk/admin/master/project-classifications` (MST-PRJ-005) · `https://seera.tech-brit.co.uk/admin/master/sites` (MST-SITE-001) · `https://seera.tech-brit.co.uk/admin/master/sites/create` (MST-SITE-002) · `https://seera.tech-brit.co.uk/admin/master/sites/{id}` (MST-SITE-003) · `https://seera.tech-brit.co.uk/admin/master/sites/{id}/edit` (MST-SITE-004)

Described in chapter 11.

### Warehouses [MST-WH-001 … 004]

Web address: `https://seera.tech-brit.co.uk/admin/master/warehouses` (MST-WH-001) · `https://seera.tech-brit.co.uk/admin/master/warehouses/create` (MST-WH-002) · `https://seera.tech-brit.co.uk/admin/master/warehouses/{id}` (MST-WH-003) · `https://seera.tech-brit.co.uk/admin/master/warehouses/{id}/edit` (MST-WH-004)

Purpose:
Physical stores that hold stock. Stock always belongs to a warehouse.

Who uses it:
Inventory Manager, Super Admin.

Navigation:
Master Setup → Warehouses → + Add Warehouse.

Permission required:
Warehouses — create / view / edit.

Fields:

| Field | What to enter | Example | Required? | Notes |
|---|---|---|---|---|
| Warehouse Name | Store name | Riyadh Site Warehouse | Yes | |
| Warehouse Code | Short code | WH-RCT-01 | Yes | |
| Branch | Owning branch | Riyadh Head Office | Yes | |
| Project (optional) / Site (optional) | Where the store is | Riyadh Commercial Tower / Riyadh Tower - Main Site | No | Project and site users see only their own warehouses' stock |
| Warehouse Incharge | Responsible user | Ahmed Hassan | No | |
| Inventory Valuation Method | Choice | Average | Yes | Stock is valued by weighted average in the current system, whatever is chosen here |
| Status | active / inactive | active | Yes | |
| Location / Address | Free text | Gate 2, Riyadh Tower | No | |

Buttons: Save / Update, Cancel.

### Expense Categories [MST-EXP-001 … 004]

Web address: `https://seera.tech-brit.co.uk/admin/master/expense-categories` (MST-EXP-001) · `https://seera.tech-brit.co.uk/admin/master/expense-categories/create` (MST-EXP-002) · `https://seera.tech-brit.co.uk/admin/master/expense-categories/{id}` (MST-EXP-003) · `https://seera.tech-brit.co.uk/admin/master/expense-categories/{id}/edit` (MST-EXP-004)

Purpose:
Categories used on supplier bill lines (and reserved for the future site expense screen).

Who uses it:
Finance Manager, Super Admin.

Navigation:
Master Setup → Expense Categories → + Add Expense Category.

Permission required:
Expense Categories — create / view / edit.

Fields:

| Field | What to enter | Example | Required? | Notes |
|---|---|---|---|---|
| Category Name / Category Code | Name and code | Fuel, EXP-FUEL | Yes | |
| Linked Chart of Account | Expense account | 5300 - Fuel Expense | Yes | |
| Approval Required, Mobile App Visible, Allowed Payment Type, Invoice Photo Required, Default VAT Treatment | Settings for a future site expense entry | Yes / Yes / Cash / Yes / Standard 15% | No | Stored only today; the site expense screen is NOT YET OPERATIONAL |
| Status, Description | | active | Status yes | |

Buttons: Save / Update, Cancel.

### Payment Terms [SUP-005] and Project Classifications [MST-PRJ-005]

Web address: `https://seera.tech-brit.co.uk/admin/master/payment-terms` (SUP-005) · `https://seera.tech-brit.co.uk/admin/master/project-classifications` (MST-PRJ-005)

Small lists with an inline "Add" section and inline Save / Delete per row. Payment terms (name and days from bill date) decide the due date of supplier bills saved without one. Project classifications label projects.

---

## 6. HR & Payroll

| | |
|---|---|
| **Chapter number** | 6 |
| **Chapter name** | HR & Payroll |
| **Purpose** | Employees, documents, shifts, attendance, leave, overtime, salary structures, payroll runs and end of service. |
| **Primary roles** | HR Manager, Site In-Charge, Finance Manager |
| **Screens in this chapter** | HR-DASH-001, HR-EMP-001, HR-EMP-002, HR-EMP-003, HR-EMP-004, HR-DOC-001, HR-SHF-001, HR-SHF-002, HR-SHF-003, HR-ATT-001, HR-ATT-002, HR-ATT-003, HR-LV-001, HR-LV-002, HR-LV-003, HR-LV-004, HR-OT-001, HR-OT-002, HR-OT-003, HR-SAL-001, HR-SAL-002, HR-SAL-003, HR-SAL-004, HR-PAY-001, HR-PAY-002, HR-PAY-003, HR-PAY-004, HR-EOS-001, HR-EOS-002, HR-EOS-003, HR-EOS-004 |

### [HR-DASH-001] HR Dashboard

Web address: `https://seera.tech-brit.co.uk/admin/hr/dashboard`

Purpose:
Today's HR picture and the queues that need action.

Who uses it:
HR Manager.

Navigation:
Operations → HR Dashboard.

Permission required:
HR — view.

What you see:
Cards (Total Employees, Active Employees, Present Today, Late Today, Absent Today, On Leave Today, Pending Leaves, Pending Overtime, Expiring IQAMAs (60 days), Expiring Documents (60 days), Pending EOSB); tables Today's Attendance Summary, Pending HR Approvals, IQAMA Expiring Soon, Latest Leave Requests; buttons + Manual Attendance, + Add Employee, Open Attendance, Open (per queue), View All.

Important:
The attendance summary is for today only. There is no monthly attendance report yet (chapter 20).

### [HR-EMP-001] Employees List

Web address: `https://seera.tech-brit.co.uk/admin/hr/employees`

Purpose:
Find employees and open View or the Employee Workspace.

Who uses it:
HR Manager.

Navigation:
Operations → Employees.

Permission required:
HR — view.

What you see:
Cards (Total Employees, Active Employees, Mobile App Access, Expiring IQAMAs), filters (search, department, project, classification, document status, status), the table (Code, Name, Department, Designation, Project / Site, Classification, IQAMA Expiry, Basic Salary, Mobile, Status) and per row **View**, **Edit**, **Deactivate**.

Important:
Employees are deactivated, never deleted, so payroll history stays intact.

### [HR-EMP-002] Add Employee

Web address: `https://seera.tech-brit.co.uk/admin/hr/employees/create`

Purpose:
Create an employee record with personal, employment, payroll, document and access details in one form.

Who uses it:
HR Manager.

Navigation:
Operations → Employees → + Add Employee.

Permission required:
HR — create.

What you see:
Five sections shown as tabs: A. Personal Information, B. Employment Information, C. Payroll Information, D. Documents & Attachments, E. Access. A "Show all sections" button shows them on one page.

Fields:

| Field | What to enter | Example | Required? | Notes |
|---|---|---|---|---|
| First Name / Last Name | Names | Ahmed / Hassan | First name yes | |
| Nationality | From the list, "+ New" to add | Egyptian | Yes | |
| Email, Phone, Emergency Contact | Contacts | ahmed.hassan@example.sa | No | |
| Employee Code | Shown automatically (next number); tick "Enter my own code" to type one | EMP-0042 | Yes on edit | The number series depends on the classification |
| Department, Designation, Branch | Position ("+ New" dialogs) | Site Operations / Site In-Charge / Riyadh Head Office | No | |
| Project / Site | Where the person works | Riyadh Commercial Tower / Riyadh Tower - Main Site | No | Decides which project or site data the person's account can see |
| Manager, Joining Date | | Fatimah Al-Zahrani / 01-Mar-2024 | No | |
| Contract Type, Employee Classification | Lists | Full Time / Sponsorship | Yes | Classification changes the code series |
| Contract Start / Contract End | Dates | 01-Mar-2024 / 28-Feb-2026 | No | Start date cannot be in the future |
| Annual Leave Entitlement (days / year) | Number | 21 | No | Used for the leave balance |
| Status | active / inactive | active | Yes | |
| Basic Salary (SAR) | Monthly basic | 6,500 | Yes | |
| Housing, Transport, Food, Fuel, Other Allowance | Monthly amounts | 1,500 / 500 / 300 / 0 / 0 | No | |
| Payment Method, Bank Name, IBAN | How salary is paid | Bank Transfer / Al Rajhi / SA00... | Payment method yes | |
| Documents & Attachments | Rows of Document Type *, profession / class, Number, Issue Date, Expiry Date, File | IQAMA, 2412345678, expiry 15-Jun-2027, PDF | No | PDF or image up to 5 MB; stored privately |
| Link User Account, Mobile App Access | Access section | — / No | No | |

Buttons:

| Button | What it does |
|---|---|
| Save & stay | Saves and stays on this employee (Edit workspace) |
| Save & next | Saves and opens the next section |
| Back / previous section | Returns to the preceding visible section without saving or discarding. Typed entries and selected files remain in this workspace; Back is disabled on the first section. Customer and Supplier Edit related panels offer the same Back control. Leaving the page still checks for unsaved changes. |
| Save & new | Saves and opens an empty employee form |
| Save & close | Saves and returns to the list |
| Cancel | Leaves without saving |

What happens after Save:
The employee is created. When payroll information is complete, the first salary structure is created automatically from it, effective from the joining date. The status message tells you.

Common mistakes:
- Contract start date in the future (refused).
- Uploading a document larger than 5 MB.
- Forgetting the Project / Site for a site employee, so their account sees nothing.

Related screens:
HR-EMP-004 Employee Workspace, HR-SAL-001, USR-002.

### [HR-EMP-003] Employee View (read-only)

Web address: `https://seera.tech-brit.co.uk/admin/hr/employees/{id}`

Purpose:
Read everything about one employee without changing anything.

Navigation:
Employees → View.

Permission required:
HR — view.

What you see:
Employment Information, Personal & Documents, Payroll Information, Documents, Recent Attendance, Leave Data with the annual balance, Overtime, Salary Structures, Payroll History, and cards for Attendance This Month and Pending Leave / Overtime. A "+ New Structure From Profile" link opens the salary structure form when the profile amounts no longer match the active structure.

### [HR-EMP-004] Employee Workspace (Edit)

Web address: `https://seera.tech-brit.co.uk/admin/hr/employees/{id}/edit`

Purpose:
Manage the employee and all related records from one page.

Who uses it:
HR Manager (each tab checks its own permission).

Navigation:
Employees → Edit.

Permission required:
HR — edit to open; tabs need Payroll, Attendance, HR or Users permissions.

What you see:
The five profile sections plus related tabs:

```
Employee
 ├─ A. Personal · B. Employment · C. Payroll · D. Documents · E. Access   (the profile form)
 ├─ Salary Structures     (Payroll)
 ├─ Attendance            (Attendance)
 ├─ Leaves                (HR)
 ├─ Overtime              (Payroll)
 ├─ Shift Assignments     (HR)
 ├─ End of Service        (Payroll)
 ├─ Payroll History       (Payroll, read-only)
 └─ System Account        (Users)
```

Each related tab has its own small form and its own saved-records table with Edit here, Approve, Reject or Delete where allowed. Saving a tab keeps you on the employee and on that tab. A linked user card at the top links to the user account (USR-003 / USR-004) when you may open it.

Important:
- Leave and Overtime saved here are always *pending*; approval is a separate button.
- Salary Structures here create a new effective-dated structure; old structures and past payroll are never overwritten.
- System Account creates or edits the login for this employee; identity fields come from the employee.

### [HR-DOC-001] Employee Documents / IQAMA

Web address: `https://seera.tech-brit.co.uk/admin/hr/documents`

Purpose:
Register of all employee documents with expiry status.

Navigation:
HR Registers & Approvals → Documents / IQAMA.

Permission required:
HR — view.

What you see:
Cards (Total Documents, Valid, Expiring Soon (60 days), Expired), filters (employee, type, status), table (Employee, Type, Number, Issue, Expiry, Validity, File, Status). Files open or download through Seera; they are not public links.

Important:
Documents are added and renewed on the Employee form (section D). This register has no add form of its own.

### [HR-SHF-001 … 003] Shifts

Web address: `https://seera.tech-brit.co.uk/admin/hr/shifts` (HR-SHF-001) · `https://seera.tech-brit.co.uk/admin/hr/shifts/create` (HR-SHF-002) · `https://seera.tech-brit.co.uk/admin/hr/shifts/{id}/edit` (HR-SHF-003)

Fields: Shift Name *, Shift Code *, Status *, Start Time *, End Time *, Break (minutes) *, Grace (minutes) *, Overtime After (minutes) *. Example: Day Shift, DAY, 07:00–16:00, break 60, grace 15, overtime after 540.

Important:
Grace and overtime-after minutes are stored but are not yet used to calculate late minutes or overtime automatically; those are typed on the attendance record.

### [HR-ATT-001 … 003] Attendance

Web address: `https://seera.tech-brit.co.uk/admin/hr/attendance` (HR-ATT-001) · `https://seera.tech-brit.co.uk/admin/hr/attendance/create` (HR-ATT-002) · `https://seera.tech-brit.co.uk/admin/hr/attendance/{id}/edit` (HR-ATT-003)

Purpose:
Record who was present, late or absent.

Status: **PARTIAL** — manual entry only. There is no employee check-in / check-out, no GPS capture and no automatic geofence check yet.

Navigation:
HR Registers & Approvals → Attendance → + Manual Attendance.

Permission required:
Attendance — create / view / edit.

Fields:

| Field | What to enter | Example | Required? | Notes |
|---|---|---|---|---|
| Employee, Date | | Ahmed Hassan, 27-Sep-2026 | Yes | One record per employee per day |
| Shift, Project, Site | Context | Day Shift, Riyadh Commercial Tower, Riyadh Tower - Main Site | No | |
| Check In / Check Out | Times | 07:05 / 16:10 | No | |
| Late (minutes), Overtime (minutes) | Typed by hand | 5 / 0 | Yes | Not calculated automatically |
| Status | present, late, absent, leave, half day | present | Yes | |
| Source | manual / mobile / offline | manual | Yes | Only "manual" is produced by the system today |
| Geo-Fence Status | inside / outside / unknown | inside | Yes | Chosen by the user; not verified by location |
| Remarks | Free text | — | No | |

Buttons: Save / Update, Cancel. The list offers Edit and Delete per row.

### [HR-LV-001 … 004] Leaves

Web address: `https://seera.tech-brit.co.uk/admin/hr/leaves` (HR-LV-001) · `https://seera.tech-brit.co.uk/admin/hr/leaves/create` (HR-LV-002) · `https://seera.tech-brit.co.uk/admin/hr/leaves/{id}` (HR-LV-003) · `https://seera.tech-brit.co.uk/admin/hr/leaves/{id}/edit` (HR-LV-004)

Purpose:
Request, approve or reject leave and keep the balance.

Navigation:
HR Registers & Approvals → Leaves → + Add Leave.

Permission required:
HR — create / view / edit; HR — approve / reject for the decisions.

Fields:

| Field | What to enter | Example | Required? | Notes |
|---|---|---|---|---|
| Employee, Leave Type | | Ahmed Hassan, Annual Leave | Yes | Leave types are managed by HR ("+ New") |
| Status | pending / approved / rejected | pending | Yes | New requests are pending; approve from the details page |
| Start Date, End Date | | 05-Oct-2026 to 09-Oct-2026 | Yes | |
| Total Days | Calculated from the dates, or an agreed exception | 5 | No | |
| Reason, Supporting Document | | Family visit, PDF | No | Document stored privately |
| Rejection Reason | Only when rejecting | — | Yes when rejecting | |

Buttons: Save / Update, Cancel; on details: **Approve**, **Reject Request** (asks for a reason), Edit, Attachment.

What happens after Approve:
Status becomes approved and the days count against the annual entitlement shown on the employee.

### [HR-OT-001 … 003] Overtime

Web address: `https://seera.tech-brit.co.uk/admin/hr/overtime` (HR-OT-001) · `https://seera.tech-brit.co.uk/admin/hr/overtime/create` (HR-OT-002) · `https://seera.tech-brit.co.uk/admin/hr/overtime/{id}/edit` (HR-OT-003)

Fields: Employee *, Date *, Attendance Record (optional link), Hours *, Hourly Rate (SAR) *, Status *, Reason. Amount = hours × rate. **Approve** is a separate button; only approved overtime enters payroll.

### [HR-SAL-001 … 004] Salary Structures

Web address: `https://seera.tech-brit.co.uk/admin/hr/salary-structures` (HR-SAL-001) · `https://seera.tech-brit.co.uk/admin/hr/salary-structures/create` (HR-SAL-002) · `https://seera.tech-brit.co.uk/admin/hr/salary-structures/{id}` (HR-SAL-003) · `https://seera.tech-brit.co.uk/admin/hr/salary-structures/{id}/edit` (HR-SAL-004)

Purpose:
Effective-dated salary definition used by payroll.

Fields: Employee *, Effective From *, Effective To, Basic Salary *, Housing / Transport / Food / Fuel / Other Allowance *, Fixed Deduction *, Status *, and Additional Salary Items (Type allowance/deduction, Name, Amount, Taxable).

Important:
Create a new structure for a change; do not edit history. The employee profile amounts and the active structure are compared and Seera warns when they differ.

### [HR-PAY-001 … 004] Payroll

Web address: `https://seera.tech-brit.co.uk/admin/hr/payroll` (HR-PAY-001) · `https://seera.tech-brit.co.uk/admin/hr/payroll/create` (HR-PAY-002) · `https://seera.tech-brit.co.uk/admin/hr/payroll/{id}` (HR-PAY-003) · `https://seera.tech-brit.co.uk/admin/hr/payroll/{id}/edit` (HR-PAY-004)

Purpose:
Calculate a month's pay for a set of employees.

Status: **PARTIAL** — the run calculates basic, allowances, approved overtime, fixed deductions and net. There is no payslip, no bank or WPS file, and **no accounting posting** when a run is approved.

Navigation:
HR Registers & Approvals → Payroll → + Create Payroll Run.

Permission required:
Payroll — create / view / edit; Payroll — process and approve for the actions.

Fields:

| Field | What to enter | Example | Required? | Notes |
|---|---|---|---|---|
| Payroll Month, Payroll Year | Period | September, 2026 | Yes | |
| Period Start, Period End | Dates | 01-Sep-2026 to 30-Sep-2026 | No | |
| Branch, Project | Limit the run | Riyadh Head Office / Riyadh Commercial Tower | No | |
| Notes | | — | No | |

Buttons: Save (draft), then on the details page **Process Payroll** (builds the employee rows) and **Approve Payroll** (only a processed run), Edit.

Statuses: draft → processed → approved. Present and leave days are counted from attendance but do not change pay in the current system.

### [HR-EOS-001 … 004] End of Service

Web address: `https://seera.tech-brit.co.uk/admin/hr/eosb` (HR-EOS-001) · `https://seera.tech-brit.co.uk/admin/hr/eosb/create` (HR-EOS-002) · `https://seera.tech-brit.co.uk/admin/hr/eosb/{id}` (HR-EOS-003) · `https://seera.tech-brit.co.uk/admin/hr/eosb/{id}/edit` (HR-EOS-004)

Purpose:
Calculate the end-of-service benefit (Saudi Labour Law bands) and approve the settlement.

Fields: Employee *, Termination Date *, Reason For Leaving *, Service Years *, Final Wage (SAR) *, Status, Gratuity Calculation (automatic or manual override), Gratuity Amount, Leave Salary, Other Dues, Deductions, Notes. The details page shows the calculation (first 5 years, after 5 years, entitlement percentage). **Approve** is a separate button. No accounting entry is created.

---

## 7. Customers

| | |
|---|---|
| **Chapter number** | 7 |
| **Chapter name** | Customers |
| **Purpose** | Customer master with contacts, notes, projects, invoices, receipts and ageing in one workspace. |
| **Primary roles** | Marketing Manager, Finance Manager, Any user with Customers — view |
| **Screens in this chapter** | CUS-001, CUS-002, CUS-003, CUS-004 |

Customers follow the Connected Workspace Standard:

```
Customers List  →  View Customer (read-only)  →  Edit / Manage Customer Workspace
```

```
Customer
 ├─ Profile
 ├─ Contacts
 ├─ Notes
 ├─ Projects
 ├─ Invoices
 ├─ Receipts & Balance
 ├─ Ageing
 ├─ Accounting
 ├─ Local ZATCA Records
 └─ Activity
```

### [CUS-001] Customers List

Web address: `https://seera.tech-brit.co.uk/admin/master/customers`

Purpose:
Find customers and open View or the workspace.

Who uses it:
Marketing Manager, Finance Manager, Account Assistant.

Navigation:
Master Setup → Customers.

Permission required:
Customers — view.

What you see:
Cards (Total Customers, Active Customers, Receivable Balance, Linked Projects), filters (search by name, code or VAT number; type; status), the table (Customer Code, Customer Name, Rating, Overdue, Contact Person, Total Projects, Receivable, Status) and per row **View**, **Edit / Manage** (only with Customers — edit) and **Delete** (only with Customers — delete; refused while the customer has projects).

### [CUS-002] Add Customer

Web address: `https://seera.tech-brit.co.uk/admin/master/customers/create`

Purpose:
Create a customer with its first contacts and a note in one go.

Who uses it:
Marketing Manager, Account Assistant.

Navigation:
Master Setup → Customers → + Add Customer.

Permission required:
Customers — create.

What you see:
Customer Information, then Office contacts, Site contacts and Shared notes sections.

Fields:

| Field | What to enter | Example | Required? | Notes |
|---|---|---|---|---|
| Customer Name | Legal name | Al Noor Development Co. | Yes | |
| Customer Code | Code | CUS-021 | No | Generated (CUS-…) when left blank |
| Customer Type | Company, Individual or a type you add | Company | Yes | "+ New" adds a type |
| Accepted Payment Types | Cash, Bank or Both | Both | No | Limits which cash or bank account a receipt may use |
| Rating | Green / Amber / Red | Green | No | |
| VAT Number, CR Number | Registration numbers | 300987654300003 / 1010987654 | No | |
| Opening Receivable (SAR) | Balance brought forward as a note | 0 | No | Master data only; it is not posted to accounting |
| Contact Person, Phone, Email | Main contact | Khalid Al-Otaibi, +966 55 222 3333 | No | |
| Credit Limit (SAR) | Agreed limit | 500,000 | No | Informational |
| Linked Receivable Account | Text label | Accounts Receivable - Customers | No | Invoices post to the receivable control account 1200 |
| Status | active / inactive | active | Yes | |
| Billing Address | Address for invoices | King Fahd Road, Riyadh | No | |
| Office contact / Site contact | Contact name, title, phone, email, address (site contact also picks a site of this customer's projects) | Office: Khalid Al-Otaibi | No | A name is required when any contact detail is entered |
| Add a note with this save | Shared note | Gate opens at 7:00 | No | Needs Customers — create |

Buttons:

| Button | What it does |
|---|---|
| Save | Saves and opens the Customer Workspace |
| Save & new | Saves and opens an empty customer form |
| Save & close | Saves and returns to the list |
| Cancel | Leaves without saving |

What happens after Save:
The customer, its contacts and the note are saved together. If a contact is invalid, nothing is saved.

Common mistakes:
- Choosing a site that belongs to another customer's project for a site contact (refused).
- Typing an opening receivable and expecting it in the ledger; it is a note only.

Related screens:
CUS-003, CUS-004, FIN-AR-002.

### [CUS-003] Customer View (read-only)

Web address: `https://seera.tech-brit.co.uk/admin/master/customers/{id}`

Purpose:
Read the customer and all its related information without any risk of changing data.

Who uses it:
Anyone with Customers — view; each section appears only if you also have that section's permission.

Navigation:
Customers → View.

Permission required:
Customers — view (sections: Projects — view, Accounts Receivable — view, Journal Entries — view, ZATCA Invoicing — view, Activity Logs — view).

What you see:
The persistent header (code, name, status, type, rating, VAT and CR numbers, contact, payment channel and credit limit, receivable account, projects count, and, for finance users, outstanding receivable, open invoices and received to date). A tab strip for jumping to sections. The Customer Information table, then each permitted section with its latest 5 rows and a **View all** link. There are no forms and no save buttons.

### [CUS-004] Customer Workspace (Edit / Manage)

Web address: `https://seera.tech-brit.co.uk/admin/master/customers/{id}/edit`

Purpose:
Manage the customer and everything related to it from one page.

Who uses it:
Marketing Manager, Finance Manager.

Navigation:
Customers → Edit / Manage.

Permission required:
Customers — edit. Each tab: Contacts and Notes (Customers), Projects (Projects — view), Invoices, Receipts & Balance, Ageing (Accounts Receivable — view), Accounting (Journal Entries — view), Local ZATCA Records (ZATCA Invoicing — view), Activity (Activity Logs — view). Tabs you may not view are not shown.

What you see:
The same header, then tabs. **Profile** is the customer form with Save, Save & new, Save & close and Cancel. The other tabs load when you click them.

| Tab | What you can do |
|---|---|
| Contacts | Add contact (Customers — create), Edit here (Customers — edit), Remove (Customers — delete). Site contacts pick a site of this customer's projects. |
| Notes | Add note (Customers — create) with author and date; Remove (Customers — delete). |
| Projects | See this customer's projects (code, name, manager, budget, status) and open them. Only projects in your scope are listed. |
| Invoices | Invoice number, date, project, taxable, VAT, total, received, balance, status, local ZATCA status; View, Edit draft, Record receipt (links to the invoice pages). |
| Receipts & Balance | Approved invoices, Received, Outstanding, Last receipt cards and the receipt list with journal links. Figures use only the invoices you may see. |
| Ageing | Current-state ageing as of today (Current, 1–30, 31–60, 60+ days) of the open invoices you may see, plus the invoice rows with days late. Not a historical as-of report. |
| Accounting | Linked receivable account and the journals created by this customer's invoices and receipts, with drill-through. |
| Local ZATCA Records | UUID, QR, XML and local status per approved invoice. Clearance is not verified live. |
| Activity | Recent activity entries for this customer, paged. |

Buttons on the page: View (read-only), Back to Customers.

Important:
- Approving or reopening an invoice and recording a receipt are done on the invoice (FIN-AR-003, FIN-AR-005), never from the customer profile.
- Saving the Profile tab does not touch contacts, notes, invoices or projects.

What happens after Save (Profile):
Save keeps you in the workspace; Save & close returns to the list or the page you came from.

Common mistakes:
- Looking for the Add Contact form on the View page: it is only in the workspace.
- Expecting the header outstanding figure to include invoices from projects outside your scope: it never does.

Related screens:
CUS-003, FIN-AR-001, FIN-AR-003, FIN-ZAT-001.

---

## 8. Suppliers

| | |
|---|---|
| **Chapter number** | 8 |
| **Chapter name** | Suppliers |
| **Purpose** | Supplier master with projects, orders, receipts, bills and payments in one workspace. |
| **Primary roles** | Purchase Manager, Finance Manager, Any user with Suppliers — view, Super Admin |
| **Screens in this chapter** | SUP-001, SUP-002, SUP-003, SUP-004, SUP-005 |

Suppliers follow the Connected Workspace Standard:

```
Suppliers List  →  View Supplier (read-only)  →  Edit / Manage Supplier Workspace
```

```
Supplier
 ├─ Profile
 ├─ Projects
 ├─ Purchase Orders
 ├─ Goods Receipts
 ├─ Supplier Bills
 ├─ Payments & Balance
 ├─ Accounting
 └─ Activity
```

### [SUP-001] Suppliers List

Web address: `https://seera.tech-brit.co.uk/admin/master/suppliers`

Purpose:
Find suppliers and open View or the workspace.

Who uses it:
Purchase Manager, Finance Manager.

Navigation:
Master Setup → Suppliers.

Permission required:
Suppliers — view.

What you see:
Cards (Total Suppliers, Active Suppliers, Payable Balance from opening balances, Categories), filters (search by name, code, VAT number or city; category; rating; status), the table (Supplier Code, Supplier Name, City, Rating, Phone, Category, Payable Balance, Status) and per row **View**, **Edit / Manage** (Suppliers — edit) and **Deactivate** (Suppliers — delete).

Important:
Suppliers are deactivated, never deleted: bills, payments, orders and receipts must stay for audit and VAT.

### [SUP-002] Add Supplier

Web address: `https://seera.tech-brit.co.uk/admin/master/suppliers/create`

Purpose:
Create a supplier with its commercial, banking and project links.

Who uses it:
Purchase Manager.

Navigation:
Master Setup → Suppliers → + Add Supplier.

Permission required:
Suppliers — create.

What you see:
Sections Supplier Information, Payment & Banking, Projects this Supplier Works For.

Fields:

| Field | What to enter | Example | Required? | Notes |
|---|---|---|---|---|
| Supplier Name | Legal name | Gulf Steel Trading | Yes | |
| Supplier Code | Code | SUP-014 | No | Generated (SUP-…) when blank |
| Supplier Category | From the list ("+ New") | Materials | No | |
| City / Location | | Dammam | No | |
| Supplier Rating | Green / Amber / Red | Green | No | |
| VAT Number, CR Number | | 300112233400003 / 2050112233 | No | |
| Opening Balance (SAR) | Balance brought forward as a note | 0 | No | Not posted to accounting |
| Contact Person, Phone, Email, Address | | Saleh Al-Amri | No | |
| Status | active / inactive | active | Yes | |
| Payment Terms | From the list ("+ New") | 30 Days | No | Bills saved without a due date fall due after these days |
| Accepted Payment Types | Cash, Bank, Both | Bank | No | Limits the accounts offered when paying this supplier |
| Linked Payable Account | Payables control account or a sub-account under it ("+ New") | 2100 - Accounts Payable | No | Bills and payments post to this account |
| Bank Name, Account Holder Name, IBAN | | Al Rajhi Bank / Gulf Steel Trading / SA03… | No | |
| Projects this Supplier Works For | Tick the projects | Riyadh Commercial Tower | No | Managed from the Projects tab once the supplier exists |

Buttons: Save (opens the Supplier Workspace), Save & new, Save & close, Cancel.

Common mistakes:
- Choosing an account outside the payables group as the linked account (refused).
- Setting "Cash" as the accepted payment type for a supplier you pay by bank transfer: the payment form will not offer the bank account.

### [SUP-003] Supplier View (read-only)

Web address: `https://seera.tech-brit.co.uk/admin/master/suppliers/{id}`

Purpose:
Read the supplier and its related records safely.

Navigation:
Suppliers → View.

Permission required:
Suppliers — view; sections need Purchase Orders, Goods Receipts, Accounts Payable, Journal Entries or Activity Logs view.

What you see:
The header (code, name, status, category, city, VAT and CR numbers, contact, payment terms and channel, payable account, projects count, and for finance users the outstanding payable and paid to date), a tab strip, the Supplier Information table, and each permitted section with the latest 5 rows and **View all**. No forms.

### [SUP-004] Supplier Workspace (Edit / Manage)

Web address: `https://seera.tech-brit.co.uk/admin/master/suppliers/{id}/edit`

Purpose:
Manage the supplier and work with its orders, receipts, bills and payments from one page.

Who uses it:
Purchase Manager, Finance Manager.

Navigation:
Suppliers → Edit / Manage.

Permission required:
Suppliers — edit. Tabs: Projects (Suppliers; linking needs Suppliers — edit and Projects — view), Purchase Orders (Purchase Orders — view), Goods Receipts (Goods Receipts — view), Supplier Bills and Payments & Balance (Accounts Payable — view), Accounting (Journal Entries — view), Activity (Activity Logs — view).

| Tab | What you can do |
|---|---|
| Profile | The supplier form with Save, Save & new, Save & close, Cancel. The project checklist is not on this tab; use Projects. |
| Projects | Link a project (drop-down of projects you can see) or Unlink. |
| Purchase Orders | PO number, date, project/site, amount, received / ordered with the outstanding quantity, status; View PO. |
| Goods Receipts | GRN number, PO, project/site, date, value, status, invoiced state (Invoiced / n uninvoiced); View GRN; **Create Supplier Bill** on a posted receipt with uninvoiced quantity (needs Accounts Payable — create). The bill form opens pre-filled and returns to this tab when closed. |
| Supplier Bills | Bill number, date, project/site, total, paid, balance, status, matched GRN lines; View, Edit draft, Record payment (links to the bill pages). |
| Payments & Balance | Approved bills, Paid, Outstanding, Last payment cards and the payment list with journal links. Only bills you may see are counted. |
| Accounting | Linked payable account and the journals created by this supplier's bills and payments. |
| Activity | Recent activity for this supplier, paged. |

Important:
Approve, Pay and Reopen a bill only on the bill itself (FIN-AP-003, FIN-AP-005). Post a goods receipt only on the receipt (INV-GRN-003).

Related screens:
INV-PO-001, INV-GRN-001, FIN-AP-001, WF-002, WF-010.

---

## 9. Accounting & Finance

| | |
|---|---|
| **Chapter number** | 9 |
| **Chapter name** | Accounting & Finance |
| **Purpose** | Chart of accounts, journals, ledger, supplier bills, customer invoices, VAT, cost centers and financial reports. |
| **Primary roles** | Finance Manager, Account Assistant, Finance Manager (company-level users only) |
| **Screens in this chapter** | FIN-DASH-001, FIN-COA-001, FIN-COA-002, FIN-COA-003, FIN-COA-004, FIN-JE-001, FIN-JE-002, FIN-JE-003, FIN-JE-004, FIN-GL-001, FIN-AP-001, FIN-AP-002, FIN-AP-003, FIN-AP-004, FIN-AP-005, FIN-AR-001, FIN-AR-002, FIN-AR-003, FIN-AR-004, FIN-AR-005, FIN-VAT-001, FIN-VAT-002, FIN-CC-001, FIN-CC-002, FIN-CC-003, FIN-CC-004, FIN-PR-001, FIN-PR-002, FIN-PR-003, FIN-PR-004 |

### How accounting works in Seera (read this first)

- Every financial document (supplier bill, customer invoice, payment, receipt, goods receipt, stock issue, stock adjustment) creates its **journal entry automatically** when you approve or post it. You do not type these journals.
- **Save** keeps a document as a *draft*. A draft has no accounting effect and no VAT effect.
- **Approve & Post** (bills, invoices) and **Post** (journals, stock documents) are the moments accounting happens. If anything is wrong (inactive account, unbalanced entry, sealed VAT period), nothing is saved and a message tells you why.
- **Pay** and **Record Receipt** create their own journals when you record them.
- A **finalized VAT period** is sealed: no document with VAT dated inside it can be approved, corrected or recalculated.
- Posted journals are never edited. Corrections are made by **Reopen** (which posts a reversing entry) or by a new manual journal.

Accounting entries created by the normal workflow (all amounts from the training dataset):

| Event | Debit | Credit |
|---|---|---|
| Goods receipt GRN-2026-0008 posted (6,000 kg Reinforcement Steel 16mm at 3.00) | 1400 Inventory Asset 18,000 | 2150 Goods Received Not Invoiced 18,000 |
| Supplier bill GST-INV-1045 approved, matched to that receipt (15% VAT) | 2150 GRNI 18,000 · 1300 Input VAT 2,700 | 2100 Accounts Payable 20,700 |
| Supplier bill approved, direct service line 500 | 5200 Material Expense (or chosen account) 500 · 1300 Input VAT 75 | 2100 Accounts Payable 575 |
| Supplier payment 20,700 by bank | 2100 Accounts Payable 20,700 | 1120 Bank Account 20,700 |
| Customer invoice approved, 2,000 + VAT | 1200 Accounts Receivable 2,300 | 4100 Project Revenue 2,000 · 2210 Output VAT 300 |
| Customer receipt 2,300 by bank | 1120 Bank Account 2,300 | 1200 Accounts Receivable 2,300 |
| Stock issue of 3 units to a project | 5200 Material Expense 300 | 1400 Inventory Asset 300 |

### [FIN-DASH-001] Accounting Dashboard

Web address: `https://seera.tech-brit.co.uk/admin/accounting/dashboard`

Purpose:
The finance picture and the action queue.

Who uses it:
Finance Manager.

Navigation:
Finance → Accounting Dashboard.

Permission required:
Accounting Dashboard — view.

What you see:
Cards Cash in Hand, Bank Balance, Accounts Payable, Accounts Receivable, VAT Payable (all-time output minus input), Unposted Journals, ZATCA Failed Invoices, Monthly Revenue; tables Current month (revenue and expenses), Finance Action Queue (draft bills, draft invoices, unposted journals, failed ZATCA), supplier and customer balances by days overdue, VAT Summary, ZATCA Status Summary, Recent Journal Entries; buttons + Journal Entry, Financial Reports, Open (per queue), Open VAT, Open ZATCA, View All.

### [FIN-COA-001 … 004] Chart of Accounts

Web address: `https://seera.tech-brit.co.uk/admin/accounting/chart-of-accounts` (FIN-COA-001) · `https://seera.tech-brit.co.uk/admin/accounting/chart-of-accounts/create` (FIN-COA-002) · `https://seera.tech-brit.co.uk/admin/accounting/chart-of-accounts/{id}` (FIN-COA-003) · `https://seera.tech-brit.co.uk/admin/accounting/chart-of-accounts/{id}/edit` (FIN-COA-004)

Purpose:
The list of ledger accounts, as a tree.

Who uses it:
Finance Manager.

Navigation:
Finance → Chart of Accounts → + Add Account.

Permission required:
Chart of Accounts — create / view / edit.

Fields (Add / Edit Account):

| Field | What to enter | Example | Required? | Notes |
|---|---|---|---|---|
| Account Code | Number | 5310 | Add: no (next free code under the parent); Edit: yes | Must be unique |
| Account Name | Name | Diesel Expense | Yes | |
| Account Type | asset, liability, equity, revenue, expense | expense | Yes | |
| Parent Account | The account above it | 5300 - Fuel Expense | No | |
| Opening Balance (SAR) | Balance brought forward | 0 | Yes | Shown in the balance sheet, trial balance and ledger for company-level users |
| Normal Balance | debit / credit | debit | Yes | |
| VAT Applicable, Cost Center Required | Flags | No / Yes | No | Stored; not enforced today |
| Status | active / inactive | active | Yes | An inactive account refuses new postings |

Buttons: Cancel, Save, Save & new, Save & close. Delete on the list deactivates an account that has transactions or sub-accounts instead of deleting it.

Standard accounts used by the workflow: 1110 Cash in Hand, 1120 Bank Account, 1200 Accounts Receivable, 1300 Input VAT Receivable, 1400 Inventory Asset, 2100 Accounts Payable, 2150 Goods Received Not Invoiced, 2210 Output VAT, 2300 Salary Payable, 3100 Owner Equity, 4100 Project Revenue, 5200 Material Expense, 5600 Inventory Adjustment Expense.

### [FIN-JE-001 … 004] Journal Entries

Web address: `https://seera.tech-brit.co.uk/admin/accounting/journal-entries` (FIN-JE-001) · `https://seera.tech-brit.co.uk/admin/accounting/journal-entries/create` (FIN-JE-002) · `https://seera.tech-brit.co.uk/admin/accounting/journal-entries/{id}` (FIN-JE-003) · `https://seera.tech-brit.co.uk/admin/accounting/journal-entries/{id}/edit` (FIN-JE-004)

Purpose:
Record a manual, balanced accounting entry and post it to the ledger.

Who uses it:
Finance Manager.

Navigation:
Finance → Journal Entries → + Add Journal Entry.

Permission required:
Journal Entries — create / view / edit; Journal Entries — post to post.

What you see (Add / Edit):
Journal Header and Journal Lines (Account, Description, Debit, Credit, Cost Center, Project, Site, running Totals).

Fields:

| Field | What to enter | Example | Required? | Notes |
|---|---|---|---|---|
| Journal Date | Date of the entry | 27-Sep-2026 | Yes | A VAT account line inside a finalized VAT period is refused |
| Reference Number | Your reference | ADJ-09-2026 | No | |
| Source Module | Manual, or the module it relates to | Manual | Yes | |
| Cost Center | Default cost center | CC-RCT-01 | No | |
| Status | draft / approved / cancelled | draft | Yes | Posting is a separate action |
| Description | What the entry is for | Bank charges September | No | |
| Lines | At least two lines; each line one account with a debit or a credit; optional cost center, project, site | 5200 Material Expense Dr 100 / 1120 Bank Cr 100 | Yes | Debits must equal credits; accounts must be active; a project-scoped user may only use their own projects and sites |

Buttons: Cancel, Save (opens the journal's details), Save & new, Save & close; on details: **Post to Ledger**, **Cancel Entry** (unposted only), Edit.

What happens after Post:
The journal is posted with your name and time, appears in the General Ledger and the reports, and can no longer be edited, deleted or cancelled.

Common mistakes:
- Unequal totals (refused).
- Using an inactive account (refused with the account name).
- Expecting to reverse a posted manual journal with a button: post a new opposite journal instead.

### [FIN-GL-001] General Ledger

Web address: `https://seera.tech-brit.co.uk/admin/accounting/general-ledger`

Purpose:
See every posted line, with an opening and a running balance.

Navigation:
Finance → General Ledger.

Permission required:
General Ledger — view.

What you see:
Filters (account, cost center, project, site, source, from / to, posted only), cards Total Debit, Total Credit, Opening Balance, and the lines (Date, Voucher, Account, Description, Source, Cost Center, Debit, Credit, Balance). The Voucher opens the journal. Opening balance = the account's opening balance (company-level users only) plus all posted movement before the "from" date; the running balance continues across pages.

### [FIN-AP-001] Accounts Payable (Supplier Bills)

Web address: `https://seera.tech-brit.co.uk/admin/accounting/accounts-payable`

Purpose:
Register of supplier bills and the recent payments.

Who uses it:
Finance Manager, Account Assistant.

Navigation:
Finance → Accounts Payable.

Permission required:
Accounts Payable — view.

What you see:
Cards Outstanding Payable, Overdue Bills, Draft Bills, Paid This Month; filters (search, supplier, status, dates); the bills table (Bill Number, Supplier, Bill Date, Due Date, Taxable, VAT, Total, Paid, Balance, Status) with **View**, **Approve** (drafts) and **Pay** (open bills) per row; Recent Supplier Payments.

### [FIN-AP-002] Add Supplier Bill and [FIN-AP-004] Edit Supplier Bill

Web address: `https://seera.tech-brit.co.uk/admin/accounting/accounts-payable/create` (FIN-AP-002) · `https://seera.tech-brit.co.uk/admin/accounting/accounts-payable/{id}/edit` (FIN-AP-004)

Purpose:
Enter a supplier's invoice, either against goods already received or as a direct service bill.

Who uses it:
Account Assistant, Finance Manager.

Navigation:
Finance → Accounts Payable → + Add Supplier Bill. Or from a posted goods receipt (INV-GRN-003) or the supplier workspace: **Create Supplier Bill** (pre-filled).

Permission required:
Accounts Payable — create (edit for FIN-AP-004; drafts only).

What you see:
Bill Information and Bill Lines. Each line has Description, **Received Goods (GRN)**, **Invoiced Qty**, Expense Category, Expense Account, Qty, Unit Price, Cost Center.

Fields:

| Field | What to enter | Example | Required? | Notes |
|---|---|---|---|---|
| Supplier | Select the supplier | Gulf Steel Trading | Yes | Supplier must already exist |
| Bill Number | The supplier's invoice number | GST-INV-1045 | Yes | The same number cannot be entered twice for one supplier |
| Reference Number | PO or delivery reference | PO-2026-0012 | No | |
| Bill Date | Invoice date | 27-Sep-2026 | Yes | Not in the future; decides the VAT period |
| Due Date | Payment due | 27-Oct-2026 | No | Filled from the supplier's payment terms when blank |
| VAT Rate (%) | VAT percentage | 15 | Yes | Applied to every line |
| Project, Site, Cost Center | Dimensions | Riyadh Commercial Tower / Riyadh Tower - Main Site / CC-RCT-01 | No | |
| Notes | | — | No | |
| Line: Description | What was billed | Reinforcement Steel 16mm | Yes (or filled from the receipt) | |
| Line: Received Goods (GRN) | The posted receipt line this bill line invoices | GRN-2026-0008 · Reinforcement Steel 16mm · 6,000 left @ 3.00 | No | Only this supplier's uninvoiced receipt lines are offered. Leave "Direct / service line" for services |
| Line: Invoiced Qty | Quantity of the receipt line invoiced now | 6,000 | Yes when a GRN line is chosen | Cannot exceed what is still uninvoiced |
| Line: Expense Category, Expense Account | Where a direct line is charged | Materials / 5200 - Material Expense | No | For a matched line the account only receives a price difference |
| Line: Qty, Unit Price | Invoice quantity and price | 6,000 × 3.00 | Unit price yes | Defaults from the receipt when a GRN line is chosen |
| Line: Cost Center | | CC-RCT-01 | No | |

Buttons: Cancel, Save (opens the bill details), Save & new, Save & close. When the form was opened from a goods receipt, the supplier workspace or a purchase order's Goods Receipts section, Cancel and Save & close return there.

Important:
Saving a bill never posts accounting. Rows without a description or unit price are ignored.

What happens after Save:
The bill is a draft with status *draft*. It appears in the supplier's workspace and in this register.

Common mistakes:
- Entering a bill for received goods without choosing the GRN line: the goods would be expensed a second time. Always match received goods.
- Asking for more Invoiced Qty than the receipt has left (refused with the remaining quantity).
- Choosing a receipt of another supplier (refused).

Related screens:
FIN-AP-003, INV-GRN-003, SUP-004, WF-010.

### [FIN-AP-003] Supplier Bill Details

Web address: `https://seera.tech-brit.co.uk/admin/accounting/accounts-payable/{id}`

Purpose:
Approve, pay, reopen or read a bill.

Who uses it:
Finance Manager.

Navigation:
Accounts Payable → View.

Permission required:
Accounts Payable — view; approve for Approve & Post and Reopen; process for Record Payment; edit for Edit.

What you see:
The bill is a **light document workspace**. A header stays at the top: Bill Number, Supplier (with a View link for Suppliers — view), status and payment state (Draft, not posted / Awaiting payment / Overdue / Partly paid / Paid in full), bill and due dates, Project / Site, the matched goods receipts and their purchase orders (links need Goods Receipts — view and Purchase Orders — view), **Total**, **Paid**, **Outstanding payment**, and the accounting journal (link needs Journal Entries — view). A section bar links to Bill Info · Lines · GRN Matches · VAT · Accounting Entry · Payments · Balance · Activity; a section is present only when your role may read it.

| Section | What it shows |
|---|---|
| Bill Info | Cards (taxable, VAT, total, balance) and Bill Information |
| Lines | Bill Lines with the Received Goods column (receipt number × quantity, accrued amount, or "Direct / service") |
| GRN Matches | One row per matched receipt line: goods receipt, purchase order, item, matched quantity, accrued amount (what the receipt posted to GRNI), billed amount, variance and the match state — *Provisional (bill still draft)* or *Invoiced (bill approved)* |
| VAT | VAT rate, taxable amount, input VAT, total and whether the VAT ledger row exists yet |
| Accounting Entry | The posted journal lines with a link (Journal Entries — view) |
| Payments | Every payment with account, method, purpose, reference and journal; Record Payment for open bills |
| Balance | Bill total, paid to date, outstanding payment, due date with the overdue days, payment state |
| Activity | The latest entries that name this bill (Activity Logs — view), with View all |

Buttons:

| Button | What it does |
|---|---|
| Back | Returns to where you opened the bill from — the purchase order's Billing section, the goods receipt or the supplier workspace — otherwise to Accounts Payable |
| Approve & Post | Posts the bill's journal and its input VAT row, consumes the matched receipt quantities and sets the bill to *unpaid*. Refused when the VAT period is finalized, an account is inactive, or a matched receipt quantity was invoiced by another bill first |
| Record Payment | Opens FIN-AP-005 (open bills only); afterwards you return to the page you opened the bill from |
| Reopen for Correction | Super Admin only, unpaid bills with no payments: posts a reversing journal, withdraws the VAT row, releases the receipt quantities and returns the bill to *draft* with your reason |
| Edit | Drafts only |

Statuses: draft → unpaid → partially_paid → paid. A second Approve on the same bill is refused; an edit of an approved bill is refused.

### [FIN-AP-005] Record Supplier Payment

Web address: `https://seera.tech-brit.co.uk/admin/accounting/accounts-payable/{id}/payment`

Purpose:
Pay all or part of an open bill.

Navigation:
Supplier Bill Details → Record Payment, or Purchase Order → Billing & GRN Matching → Record Payment.

Permission required:
Accounts Payable — process.

Fields:

| Field | What to enter | Example | Required? | Notes |
|---|---|---|---|---|
| Payment Date | Date paid | 30-Sep-2026 | Yes | |
| Paid From (Cash / Bank Account) | The account money left | 1120 - Bank Account | Yes | Only the channels the supplier accepts are offered |
| Payment Method | Cash, Bank Transfer, Cheque | Bank Transfer | Yes | |
| Purpose | Bill payment, advance, etc. | Bill payment | Yes | |
| Payment Amount (SAR) | Amount | 20,700.00 | Yes | Cannot exceed the outstanding balance |
| Reference Number, Notes | | TRF-88123 | No | |

Buttons: Record Payment, Back (Back to Bill), Cancel. Back and Cancel return to the page you came from: the bill, or the purchase order's Billing section. After Record Payment you return there as well.

What happens after Record Payment:
A journal Dr Accounts Payable / Cr Cash or Bank is posted, the bill's paid and balance amounts update and the status moves to partially_paid or paid. If the same payment is submitted twice (double click, browser retry) the second one is recognised and nothing is added.

### [FIN-AR-001 … 005] Accounts Receivable (Customer Invoices and Receipts)

Web address: `https://seera.tech-brit.co.uk/admin/accounting/accounts-receivable` (FIN-AR-001) · `https://seera.tech-brit.co.uk/admin/accounting/accounts-receivable/create` (FIN-AR-002) · `https://seera.tech-brit.co.uk/admin/accounting/accounts-receivable/{id}` (FIN-AR-003) · `https://seera.tech-brit.co.uk/admin/accounting/accounts-receivable/{id}/edit` (FIN-AR-004) · `https://seera.tech-brit.co.uk/admin/accounting/accounts-receivable/{id}/receipt` (FIN-AR-005)

These screens mirror Accounts Payable.

Add Customer Invoice (FIN-AR-002) fields: Customer *, Invoice Number (generated when blank), Invoice Date * (not in the future), Due Date, VAT Rate (%) *, Project, Cost Center, Notes, and lines with Item / Description *, Qty, Unit Price, Revenue Account, Cost Center. Buttons: Cancel, Save, Save & new, Save & close.

Customer Invoice Details (FIN-AR-003): Invoice Information, ZATCA Record (UUID, QR Code, XML, Digital Signature, Clearance, Retry Count, Open Record), Accounting Entry, Invoice Lines, Receipts. Buttons **Approve & Post** (posts Dr Receivable / Cr Revenue / Cr Output VAT, records the output VAT row and creates the local ZATCA record), **Record Receipt**, **Reopen for Correction** (Super Admin; unpaid, no receipts; refused when the local ZATCA record is cleared), Edit (draft only).

Record Customer Receipt (FIN-AR-005) fields: Receipt Date *, Received Into (Bank / Cash Account) * (only the channels the customer accepts), Payment Method * (must match the account: Cash for the cash account, Bank Transfer or Cheque for the bank), Received Amount (SAR) * (not more than the balance), Reference Number, Notes. Buttons: Record Receipt, Back to Invoice, Cancel. Repeated submissions are recognised and not added twice.

Statuses: draft → unpaid → partially_paid → paid.

### [FIN-VAT-001] VAT Management and [FIN-VAT-002] VAT Period

Web address: `https://seera.tech-brit.co.uk/admin/accounting/vat` (FIN-VAT-001) · `https://seera.tech-brit.co.uk/admin/accounting/vat/{id}` (FIN-VAT-002)

Purpose:
Review the VAT return figures per quarter and finalize the period.

Who uses it:
Finance Manager with a company-level role (project, site and warehouse scoped users cannot open VAT screens).

Navigation:
Finance → VAT Management → View (period).

Permission required:
VAT Management — view; process for Recalculate; approve for Finalize.

What you see:
Cards Output VAT (Sales), Input VAT (Purchases), VAT Payable, VAT Exceptions (rows without a period), Draft Output VAT and Draft Input VAT (unapproved documents, shown separately so the return and the invoice list never look out of step); the VAT Periods table (Period, Start, End, Sales Taxable, Output VAT, Purchase Taxable, Input VAT, VAT Payable, Transactions, Status) with **Recalculate**, **Finalize** and **View**; Recent VAT Transactions.

Buttons:

| Button | What it does |
|---|---|
| Recalculate | Re-adds the period's VAT transactions (draft periods only) |
| Finalize / Finalize Period | Recalculates and seals the period. From then on no VAT-bearing document dated inside it can be approved, reopened or recalculated |
| VAT Report | Opens FIN-REP-006 |

Important:
- Periods are created by the system setup (one per quarter); there is no screen to add a period.
- Finalize cannot be undone from a screen. Check the draft figures first.
- A zero-VAT document does not create a VAT row.

### [FIN-ZAT-001] ZATCA E-Invoicing and [FIN-ZAT-002] ZATCA Record

Web address: `https://seera.tech-brit.co.uk/admin/accounting/zatca` (FIN-ZAT-001) · `https://seera.tech-brit.co.uk/admin/accounting/zatca/{id}` (FIN-ZAT-002)

Status: **FOUNDATION ONLY**. See chapter 14.

### [FIN-CC-001 … 004] Cost Centers

Web address: `https://seera.tech-brit.co.uk/admin/accounting/cost-centers` (FIN-CC-001) · `https://seera.tech-brit.co.uk/admin/accounting/cost-centers/create` (FIN-CC-002) · `https://seera.tech-brit.co.uk/admin/accounting/cost-centers/{id}` (FIN-CC-003) · `https://seera.tech-brit.co.uk/admin/accounting/cost-centers/{id}/edit` (FIN-CC-004)

Purpose:
Codes that tag journal lines, bills and invoices for cost reporting.

Fields: Cost Center Code *, Cost Center Name *, Type * (branch, department, project, site, warehouse), Linked Record, Manager, Status *. Buttons: Cancel, Save, Save & new, Save & close. Details show the posted lines and a ledger link. A cost center with journal lines is deactivated instead of deleted.

Important:
Project cost reporting uses the project set on each line, not the cost center.

### [FIN-PR-001 … 004] Automatic Posting Rules

Web address: `https://seera.tech-brit.co.uk/admin/accounting/posting-rules` (FIN-PR-001) · `https://seera.tech-brit.co.uk/admin/accounting/posting-rules/create` (FIN-PR-002) · `https://seera.tech-brit.co.uk/admin/accounting/posting-rules/{id}` (FIN-PR-003) · `https://seera.tech-brit.co.uk/admin/accounting/posting-rules/{id}/edit` (FIN-PR-004)

Purpose:
One row per automatic journal event (Inventory Purchase, Stock Issued, Stock Adjusted, Bill Approved, Invoice Approved, Payment Recorded, Receipt Recorded, Payroll Approved, Site Expense Approved).

Status: **PARTIAL**. Only the **Auto Post** switch changes behaviour: when it is off, the event creates a *draft* journal that a finance user must post by hand (review mode). The debit and credit accounts, cost center rule and approval flag on the rule are informational; the real accounts come from the documents. "Payroll Approved" and "Site Expense Approved" have no event that fires them yet.

Fields: Source Module *, Trigger Event *, Cost Center Rule *, Debit Account, Credit Account, Auto Post, Approval Required, Status *, Notes. Buttons: Cancel, Save, Save & new, Save & close.

---

## 10. Inventory & Purchasing

| | |
|---|---|
| **Chapter number** | 10 |
| **Chapter name** | Inventory & Purchasing |
| **Purpose** | Items, stock, purchase requests, purchase orders (the procure-to-pay document workspace), goods receipts, issues, transfers, adjustments and inventory reports. |
| **Primary roles** | Inventory Manager, Warehouse Incharge, Purchase Manager, Site In-Charge, Purchase Assistant, Finance Manager |
| **Screens in this chapter** | INV-DASH-001, INV-ITEM-001, INV-ITEM-002, INV-ITEM-003, INV-ITEM-004, INV-CAT-001, INV-CAT-002, INV-CAT-003, INV-UNIT-001, INV-UNIT-002, INV-UNIT-003, INV-STK-001, INV-PR-001, INV-PR-002, INV-PR-003, INV-PR-004, INV-PO-001, INV-PO-002, INV-PO-003, INV-PO-004, INV-GRN-001, INV-GRN-002, INV-GRN-003, INV-GRN-004, INV-ISS-001, INV-ISS-002, INV-ISS-003, INV-ISS-004, INV-TRF-001, INV-TRF-002, INV-TRF-003, INV-TRF-004, INV-ADJ-001, INV-ADJ-002, INV-ADJ-003, INV-ADJ-004, INV-LED-001 |

### How stock works in Seera

- Stock always sits in a **warehouse**. On-hand quantity changes only through documents: goods receipt (in), stock issue (out), transfer (out of one warehouse, into another), adjustment (count correction). Nobody edits the on-hand number directly.
- Stock is valued at **weighted average cost per warehouse**.
- Posting a document moves stock and creates the accounting entry in one step. If accounting is refused, stock does not move either.
- Purchasing runs Purchase Request → Purchase Order → Goods Receipt → Supplier Bill (chapter 9).

### [INV-DASH-001] Inventory Dashboard

Web address: `https://seera.tech-brit.co.uk/admin/inventory/dashboard`

Cards Total Items, Stock Value, Low Stock, Pending PRs, Open POs, Pending GRNs, Open Transfers, Unposted Stock Documents; tables Low Stock Alerts, Warehouse Stock Summary, Recent Stock Movement; buttons + Purchase Request, + Goods Receipt, Full Report, Stock On Hand, Open Stock Ledger. Permission: Inventory Dashboard — view.

### [INV-ITEM-001 … 004] Materials / Items

Web address: `https://seera.tech-brit.co.uk/admin/inventory/items` (INV-ITEM-001) · `https://seera.tech-brit.co.uk/admin/inventory/items/create` (INV-ITEM-002) · `https://seera.tech-brit.co.uk/admin/inventory/items/{id}` (INV-ITEM-003) · `https://seera.tech-brit.co.uk/admin/inventory/items/{id}/edit` (INV-ITEM-004)

Purpose:
The material master.

Navigation:
Inventory → Materials / Items → + Add Item.

Permission required:
Items — create / view / edit.

Fields:

| Field | What to enter | Example | Required? | Notes |
|---|---|---|---|---|
| Item Code, Item Name | | ITM-0031, Reinforcement Steel 16mm | Yes | |
| Category, Unit | Lists | Steel, TON | Unit yes | |
| Valuation Method | average / fifo | average | Yes | Only weighted average is applied today |
| Status, Description | | active | Status yes | |
| Reorder Level, Minimum Stock, Maximum Stock | Quantities | 5 / 2 / 50 | Yes | Reorder level drives the low-stock report; minimum and maximum are stored only |
| Preferred Supplier | | Gulf Steel Trading | No | Display only |
| Linked Inventory Account, Linked Expense Account | Accounts used when posting receipts and issues | 1400 Inventory Asset / 5200 Material Expense | No | Standard accounts are used when blank |
| VAT Applicable | Flag | Yes | No | Stored only; VAT comes from the document rate |

Buttons: Save / Update, Cancel. Delete deactivates an item that has stock or history. Details show Stock By Warehouse (on hand, reserved, available, average cost, value) and Recent Stock Movement.

### [INV-CAT-001 … 003] Item Categories and [INV-UNIT-001 … 003] Units

Web address: `https://seera.tech-brit.co.uk/admin/inventory/categories` (INV-CAT-001) · `https://seera.tech-brit.co.uk/admin/inventory/categories/create` (INV-CAT-002) · `https://seera.tech-brit.co.uk/admin/inventory/categories/{id}/edit` (INV-CAT-003) · `https://seera.tech-brit.co.uk/admin/inventory/units` (INV-UNIT-001) · `https://seera.tech-brit.co.uk/admin/inventory/units/create` (INV-UNIT-002) · `https://seera.tech-brit.co.uk/admin/inventory/units/{id}/edit` (INV-UNIT-003)

Categories: Category Code *, Category Name *, Parent Category, Linked Inventory Account, Linked Expense Account, Status *. Units: Unit Code *, Unit Name *, Allows Decimal, Status *. Buttons: Save / Update, Cancel.

### [INV-STK-001] Stock On Hand and [INV-LED-001] Stock Ledger

Web address: `https://seera.tech-brit.co.uk/admin/inventory/stock` (INV-STK-001) · `https://seera.tech-brit.co.uk/admin/inventory/stock-ledger` (INV-LED-001)

Stock On Hand: cards (Total Stock Value, Total Quantity, Stocked Items, Low Stock Rows), Warehouse Stock Summary, and the rows (Item, Project / Site, On Hand, Reserved, Available, Reorder, Avg Cost, Status) with a low-stock filter. Stock Ledger: every movement (Date, Reference, Movement, Item, Warehouse, In Qty, Out Qty, Balance, Unit Cost, Value, Project / Site) with filters. Permissions: Warehouse Stock — view; Stock Ledger — view.

### [INV-PR-001 … 004] Purchase Requests

Web address: `https://seera.tech-brit.co.uk/admin/inventory/purchase-requests` (INV-PR-001) · `https://seera.tech-brit.co.uk/admin/inventory/purchase-requests/create` (INV-PR-002) · `https://seera.tech-brit.co.uk/admin/inventory/purchase-requests/{id}` (INV-PR-003) · `https://seera.tech-brit.co.uk/admin/inventory/purchase-requests/{id}/edit` (INV-PR-004)

Purpose:
Ask for materials to be bought.

Navigation:
Inventory → Purchase Requests → + Add Purchase Request.

Permission required:
Purchase Requests — create / view / edit; approve / reject for the decision.

Fields: Request Date *, Required Date, Priority *, Project, Site, Deliver To Warehouse, Status *, Reason, and lines Item *, Description, Quantity *, Unit, Est. Unit Cost, Budget Line (free text). Example: PR-2026-0010 for Riyadh Commercial Tower, priority high, 10,000 kg of Reinforcement Steel 16mm, estimated 3.00 per kg. Buttons: Save / Update, Cancel; on details **Approve** (Purchase Requests — approve), **Reject Request** with reason (Purchase Requests — reject), Edit (draft or pending), **Create Purchase Order** (approved requests; Purchase Orders — create).

Purchase Request Details (INV-PR-003) is a **light connected view**. A header stays at the top: PR number, status and ordering state (No purchase order yet / Partly ordered / Fully ordered), requested by and date, Project / Site and Warehouse (links need Projects — view and Warehouses — view), required date and priority, estimated total, the approval (who and when, or the rejection reason) and how much of the requested quantity has been ordered. A section bar links to Request Information · Requested Items · Purchase Orders · Activity.

| Section | What it shows |
|---|---|
| Requested Items | Each line with Quantity, **Ordered so far** (the lines of the purchase orders raised from this request, cancelled orders excluded) and **Still to order** |
| Purchase Orders From This Request | PO number, supplier, total, Received (for example 6,000 of 10,000), status and View PO (Purchase Orders — view) |
| Activity | The latest entries that name this request (Activity Logs — view), with View all |

Statuses: draft → pending → approved / rejected → converted (when a PO is created from it).

### [INV-PO-001 … 004] Purchase Orders

Web address: `https://seera.tech-brit.co.uk/admin/inventory/purchase-orders` (INV-PO-001) · `https://seera.tech-brit.co.uk/admin/inventory/purchase-orders/create` (INV-PO-002) · `https://seera.tech-brit.co.uk/admin/inventory/purchase-orders/{id}` (INV-PO-003) · `https://seera.tech-brit.co.uk/admin/inventory/purchase-orders/{id}/edit` (INV-PO-004)

Purpose:
Order materials from a supplier.

Navigation:
Inventory → Purchase Orders → + Add Purchase Order (or Purchase Request → Create Purchase Order).

Permission required:
Purchase Orders — create / view / edit; approve for Approve Order.

Fields:

| Field | What to enter | Example | Required? | Notes |
|---|---|---|---|---|
| Supplier | | Gulf Steel Trading ("+ New" available) | Yes | |
| PO Date, Expected Delivery | | 20-Sep-2026 / 30-Sep-2026 | PO date yes | |
| Source Purchase Request | The PR it fulfils | PR-2026-0010 | No | |
| Deliver To Warehouse | | Riyadh Site Warehouse | Yes | |
| Default VAT Rate (%) | | 15 | Yes | Lines may override |
| Project, Site, Notes | | Riyadh Commercial Tower | No | |
| Lines | Item *, Description, Qty *, Unit Price, Disc %, VAT %, Line Total | 10,000 kg × Reinforcement Steel 16mm at 3.00 | Yes | + Add Line |
| Attach quotation files | Supplier quotations | quote.pdf | No | Upload / Remove on the details page while the order is open |

Buttons: Save / Update, Cancel; on details **Approve Order** (draft; Purchase Orders — approve), Upload, Remove (quotation), Edit (draft), **Create Goods Receipt**, Back to Purchase Orders.

#### Purchase Order Details (INV-PO-003) — the procure-to-pay document workspace

Web address: `https://seera.tech-brit.co.uk/admin/inventory/purchase-orders/{id}`

The order page is the **connected document workspace** of the whole procure-to-pay chain: everything that happened to the order is read here, while receiving, billing and payment stay explicit actions on their own pages. The page is read-only.

Header (always visible): PO number, status and receiving state, approval (who and when), Supplier with **View** (Suppliers — view) and **Manage** (Suppliers — edit) links, PO date, expected delivery, Project / Site (link needs Projects — view), Deliver to warehouse (link needs Warehouses — view), order total and VAT, **Received x of y · still to receive**, **Invoiced · received but not invoiced**, billing state (Nothing to invoice yet / Received but not invoiced / Partly invoiced / Fully invoiced) with the number of bills and the outstanding payment (Accounts Payable — view), and the number of goods receipts (with how many are still draft).

Section bar: Overview · Order Lines · Source Purchase Request · Supplier & Commercial · Quotations · Goods Receipts · Billing & GRN Matching · Accounting · Activity. A section is present only when your role may read it; the page never shows more than your permissions allow.

| Section | What it shows | Needs |
|---|---|---|
| Overview | Cards (taxable, VAT, total, receiving state) and Order Information | Purchase Orders — view |
| Order Lines | Per line: Ordered, **Received** (posted receipts), **Still to receive**, **Invoiced** (approved bills matched to the receipts), **Received but not invoiced**, unit price, discount, taxable, VAT, total | Purchase Orders — view |
| Source Purchase Request | PR number (link), requested by, required date, priority, request status, requested lines and reason; or "raised directly, without a purchase request" | Purchase Requests — view |
| Supplier & Commercial | Supplier code and name, contact, VAT / CR numbers, payment terms and accepted payment types, rating, supplier status, View Supplier / Manage Supplier; the payable account only with Accounts Payable — view | Suppliers — view |
| Quotations | Supplier quotation files: download, Upload (Purchase Orders — create, while the order is open), Remove (draft only, Purchase Orders — delete) | Purchase Orders — view |
| Goods Receipts | Every receipt against this order: GRN, date, warehouse, received by, accepted quantity, value, stock and status, invoicing state (Not posted yet / Received but not invoiced / n received but not invoiced / Invoiced), View GRN, the receipt's journal (Journal Entries — view), Create Supplier Bill (Accounts Payable — create, posted receipts with uninvoiced quantity) | Goods Receipts — view |
| Billing & GRN Matching | The per-line table Ordered / Received / Still to receive / Invoiced / Received but not invoiced, then the supplier bills matched to this order's receipts: bill, dates, matched GRNs, total, paid, outstanding payment, status, View bill, Edit draft (Accounts Payable — edit), Record Payment (Accounts Payable — process) | Accounts Payable — view |
| Accounting | The journals posted by this order's receipts, the bills matched to them and their payments, with a plain explanation of GRNI; view only | Journal Entries — view |
| Activity | Entries that name the order, its receipts or its bills | Activity Logs — view |

Goods Receipts, Billing, Accounting and Activity show the latest 5 rows with paging and a **View all** link to the full register.

**Create Goods Receipt** appears in the page header and above the Goods Receipts section only when the order is approved or partially received, something is still to receive, and your role holds Goods Receipts — create (or receive). The receipt form opens with the outstanding quantities filled in; **Save & close** brings you back to this order's Goods Receipts section, **Save** stays on the receipt so you can Post Stock, and the receipt page keeps a Back link to the order.

**Create Supplier Bill** from the Goods Receipts section and **Record Payment** / **Edit draft** from the Billing section return to this order's Billing section when you finish or cancel.

How a bill is linked to an order: through its goods receipt matches (F04). A bill entered without a receipt match — a direct or service bill — does not appear on the order; find it under Accounts Payable.

Example: PO-2026-0012 orders 10,000 kg. After GRN-2026-0008 (6,000 kg) is posted the header reads *Partially received · 6000 of 10000 kg · still to receive 4000 kg*. After GST-INV-1045 is approved for those 6,000 kg the billing state reads *Fully invoiced* for the received part, and once GRN-2026-0009 (4,000 kg) is posted it reads *Partly invoiced · received but not invoiced 4000 kg* until the second bill is approved.

Statuses: draft → approved → partially_received → received. Received quantity per line is updated by posted goods receipts; invoiced quantity by approved supplier bills.

### [INV-GRN-001 … 004] Goods Receipt Notes

Web address: `https://seera.tech-brit.co.uk/admin/inventory/goods-receipts` (INV-GRN-001) · `https://seera.tech-brit.co.uk/admin/inventory/goods-receipts/create` (INV-GRN-002) · `https://seera.tech-brit.co.uk/admin/inventory/goods-receipts/{id}` (INV-GRN-003) · `https://seera.tech-brit.co.uk/admin/inventory/goods-receipts/{id}/edit` (INV-GRN-004)

Purpose:
Confirm that goods arrived and put them into stock.

Who uses it:
Warehouse Incharge.

Navigation:
Inventory → Goods Receipt Notes → + Add Goods Receipt, or Purchase Order → Create Goods Receipt (lines pre-filled with the outstanding quantities and the order prices after discount; Save & close returns to the order's Goods Receipts section).

Permission required:
Goods Receipts — create / view / edit; post for Post Stock.

Fields:

| Field | What to enter | Example | Required? | Notes |
|---|---|---|---|---|
| Supplier, Receiving Warehouse | | Gulf Steel Trading / Riyadh Site Warehouse | Yes | |
| Purchase Order | The order being delivered | PO-2026-0012 | No | When set, every line must be on the order, the supplier must match, the order must be open, and the received quantity cannot exceed what is outstanding |
| Received Date | | 26-Sep-2026 | Yes | |
| Delivery Note Number, Supplier Invoice Number | Supplier papers | DN-7741 / GST-INV-1045 | No | Information only; the bill is entered in Accounts Payable |
| VAT Rate (%) | Expected VAT | 15 | Yes | Shown for information; VAT is recorded on the supplier bill, not on the receipt |
| Lines | Item *, Ordered, Received *, Accepted, Unit Cost | 10,000 / 6,000 / 6,000 / 3.00 | Yes | Accepted is what enters stock; the rest is recorded as rejected |

Buttons: Cancel, **Save** (stays on the receipt, where Post Stock is), **Save & close** (returns to where you came from, otherwise the list); on details **Post Stock** (Goods Receipts — post), Edit (draft; Goods Receipts — edit), **Create Supplier Bill** (posted, uninvoiced quantity; Accounts Payable — create), **Back** / **Back to Purchase Order**.

Goods Receipt Details (INV-GRN-003) is a **light document workspace**. A header stays at the top: GRN number, status, received date and by whom, the source purchase order (link needs Purchase Orders — view), supplier (View needs Suppliers — view), warehouse (link needs Warehouses — view), Project / Site, value, Stock (Posted to stock / Not posted yet), Accounting (Posted with the journal link for Journal Entries — view / Not posted yet) and the invoicing state (Not posted yet / Received but not invoiced / Partly invoiced / Invoiced, with the invoiced and received-but-not-invoiced quantities). A section bar links to Receipt Information · Received Lines · Bill Matches · Accounting Entry · Activity.

| Section | What it shows |
|---|---|
| Received Lines | Ordered, Received, Accepted, Rejected, **Invoiced**, **Received but not invoiced**, unit cost, total cost |
| Bill Matches | Each supplier bill line matched to this receipt: bill, date, bill status, item, matched quantity, accrued amount, match state (Provisional while the bill is draft, Invoiced once approved), View bill (Accounts Payable — view) |
| Accounting Entry | The posted journal lines with a link (Journal Entries — view) |
| Activity | The latest entries that name this receipt (Activity Logs — view), with View all |

What happens after Post Stock:
Stock and the stock ledger increase by the accepted quantity at the unit cost; the purchase order's received quantity and status update; the accounting entry **Dr Inventory Asset / Cr Goods Received Not Invoiced** is posted. No supplier payable and no VAT are recorded at this stage. The receipt becomes read-only.

Common mistakes:
- Receiving more than the order's outstanding quantity (refused, with the remaining figure).
- Posting a receipt into the wrong warehouse; it cannot be edited afterwards. Use a transfer to move the stock.
- Expecting the supplier's balance to change at receipt time: it changes when the bill is approved.

### [INV-ISS-001 … 004] Stock Issues

Web address: `https://seera.tech-brit.co.uk/admin/inventory/stock-issues` (INV-ISS-001) · `https://seera.tech-brit.co.uk/admin/inventory/stock-issues/create` (INV-ISS-002) · `https://seera.tech-brit.co.uk/admin/inventory/stock-issues/{id}` (INV-ISS-003) · `https://seera.tech-brit.co.uk/admin/inventory/stock-issues/{id}/edit` (INV-ISS-004)

Purpose:
Take materials out of a warehouse for a project or site.

Fields: Issue From Warehouse *, Issue Date *, Issue To Project, Issue To Site, Purpose, lines Item *, Quantity *. Buttons: Save / Update, Cancel; on details **Post Issue**, Edit (draft).

What happens after Post Issue:
Stock decreases at the current average cost; the entry Dr Material Expense (or the item's expense account) / Cr Inventory Asset is posted with the project and site; the project cost report and the material consumption report include it. If any line has insufficient stock the whole issue is refused.

### [INV-TRF-001 … 004] Stock Transfers

Web address: `https://seera.tech-brit.co.uk/admin/inventory/stock-transfers` (INV-TRF-001) · `https://seera.tech-brit.co.uk/admin/inventory/stock-transfers/create` (INV-TRF-002) · `https://seera.tech-brit.co.uk/admin/inventory/stock-transfers/{id}` (INV-TRF-003) · `https://seera.tech-brit.co.uk/admin/inventory/stock-transfers/{id}/edit` (INV-TRF-004)

Fields: Transfer Date *, From Warehouse *, To Warehouse *, Notes, lines Item *, Quantity *. Buttons: Save / Update, Cancel; **Dispatch Transfer** (stock leaves the source), **Receive Transfer** (stock enters the destination at the dispatched cost), Edit (draft), Open Stock Ledger.

Statuses: draft → dispatched → received. No accounting entry is created (stock moves inside the company).

Important:
Receive needs the Stock Transfers — receive right. The standard roles delivered with the system do not hold it yet, so a Super Admin receives transfers until the roles are updated.

### [INV-ADJ-001 … 004] Stock Adjustments

Web address: `https://seera.tech-brit.co.uk/admin/inventory/stock-adjustments` (INV-ADJ-001) · `https://seera.tech-brit.co.uk/admin/inventory/stock-adjustments/create` (INV-ADJ-002) · `https://seera.tech-brit.co.uk/admin/inventory/stock-adjustments/{id}` (INV-ADJ-003) · `https://seera.tech-brit.co.uk/admin/inventory/stock-adjustments/{id}/edit` (INV-ADJ-004)

Fields: Warehouse *, Item *, Adjustment Date *, Counted Quantity *, Reason. Buttons: Save / Update, Cancel; **Approve** then **Post Adjustment**, Edit (draft).

What happens after Post Adjustment:
The on-hand quantity becomes the counted quantity (re-read at posting time); a loss posts Dr Inventory Adjustment Expense / Cr Inventory Asset, a gain the opposite.

### [INV-REP-001 … 005] Inventory Reports

Web address: `https://seera.tech-brit.co.uk/admin/inventory/reports` (INV-REP-001) · `https://seera.tech-brit.co.uk/admin/inventory/reports/stock-valuation` (INV-REP-002) · `https://seera.tech-brit.co.uk/admin/inventory/reports/low-stock` (INV-REP-003) · `https://seera.tech-brit.co.uk/admin/inventory/reports/project-consumption` (INV-REP-004) · `https://seera.tech-brit.co.uk/admin/inventory/reports/movement` (INV-REP-005)

Stock Valuation, Low Stock, Project Material Consumption and Stock Movement, each with filters and an **Export PDF** button that uses the browser's print function. There is no CSV export for inventory reports yet. Permission: Inventory Reports — view.

---

## 11. Projects

| | |
|---|---|
| **Chapter number** | 11 |
| **Chapter name** | Projects |
| **Purpose** | Project Connected Workspace Phase A: existing operations, independent child flows and shared cost-report figures. |
| **Primary roles** | Project Manager, Super Admin |
| **Screens in this chapter** | MST-PRJ-001, MST-PRJ-002, MST-PRJ-003, MST-PRJ-004, MST-PRJ-005, MST-SITE-001, MST-SITE-002, MST-SITE-003, MST-SITE-004 |

Project Connected Workspace **Phase A is available**: open one Project to understand its existing operational records. Site Expenses, BOQ/budget lines, labour-to-GL, equipment cost and progress tracking are still not operational. The separate Projects & Site Expenses menu remains a placeholder.

### [MST-PRJ-001 … 004] Projects

Web address: `https://seera.tech-brit.co.uk/admin/master/projects` (MST-PRJ-001) · `https://seera.tech-brit.co.uk/admin/master/projects/create` (MST-PRJ-002) · `https://seera.tech-brit.co.uk/admin/master/projects/{id}` (MST-PRJ-003) · `https://seera.tech-brit.co.uk/admin/master/projects/{id}/edit` (MST-PRJ-004)

Navigation: Master Setup → Projects → **View** (read-only) → **Edit / Manage** (profile editing). View and every related GET perform no business write. Each related record remains independent; no Save All. Global registers and approvals are retained.

The identity header stays outside the section switcher: Project code/name, customer, manager, classification, branch, status, dates and master budget. Visible-site, assigned-staff and warehouse counts use your access scope. Open POs, Material Used, Amount Still to Receive and posted cost/revenue appear only with their corresponding permissions.

#### Project profile fields and saves

| Field | Example | Validation / meaning |
|---|---|---|
| Name, Code | Riyadh Commercial Tower / PRJ-RCT-01 | Required; code unique |
| Customer | Al Noor Development Co. | Existing customer; inline creation if authorized |
| Classification, Branch, Project Manager | Commercial / Riyadh Head Office / project manager user | Existing master links; authorized inline creation retained |
| Status | active | active, planning, on hold, completed, inactive |
| Start / End | 01-Feb-2026 / 31-Dec-2027 | End cannot precede start; optional in current server validation |
| Master budget | SAR 12,500,000 | Non-negative single master amount, not BOQ/budget lines |
| Location, Description | Riyadh / tower construction | Optional |

Save stays on Edit / Manage. Save & Close returns to the safe origin, otherwise the Projects list. Save & New opens a fresh Project form where creation is permitted. Cancel leaves without saving. Related business actions are never triggered by profile Save.

#### Project sections, sources and permissions

Every panel additionally requires Projects — view. Hidden panels reject direct URL access too. Each list loads on demand, ten records at a time, with Previous/Next and **View all (paged)**. No full transaction history is loaded on the initial View. Without JavaScript the section links open standalone paged read-only screens.

| Section | What it shows / where to go next | Additional permission |
|---|---|---|
| Overview | Location, description and shared identity/summary header | Projects — view; edit for profile form |
| Customer | Actual linked customer, VAT/CR/contact, authorized View / Edit Manage. Project approved invoice and outstanding totals only with AR view | Customers — view; edit for Manage |
| Sites / Locations | Only this Project's visible sites, supervisor/address/status, geofence radius and stored attendance flags. Add Location / Edit Site use this fixed Project | Sites — view; create/edit separately |
| Project Team / Staff | Current employees, department/designation/site/manager/status/mobile access, authorized Employee links | HR — view; edit for Manage |
| Warehouses | Visible project warehouses, site/incharge/valuation/status; stocked item counts and positive stock value only with stock permission | Warehouses — view; Warehouse Stock — view for figures |
| Stock On Hand | Positive stock balances per warehouse/item/unit, on-hand quantity, existing average cost and stored total value | Warehouse Stock — view |
| Suppliers | Pivot-linked suppliers plus suppliers of visible project POs/bills when those document permissions exist. Non-cancelled project PO count/value excludes other projects | Suppliers — view; Purchase Orders — view for purchase figures; Accounts Payable — view for bill-derived membership |
| Purchase Requests | Number/date/required date/status and visible linked project order count. Open existing PR View for requested/ordered quantities | Purchase Requests — view; Purchase Orders — view for linked count |
| Project Purchases | PO number, supplier, date, total and current status/receiving state; billing state from visible posted receipt lines. View opens existing P2P workspace | Purchase Orders — view; Goods Receipts — view for billing state |
| Goods Receipts | GRNs belonging through a visible PO of this Project, supplier/warehouse/date, stock and invoicing states | Goods Receipts — view |
| Material Used | Posted Stock Issue ledger rows: issue/date/site/warehouse/item/unit/quantity/value. A warehouse receipt is NOT consumption | Stock Issues — view; Inventory Reports — view for report drill-through |
| Customer Invoices | Project invoices, taxable/VAT/total/received/outstanding/status, local ZATCA state. Receipt action needs process permission and an unpaid/partially-paid invoice | Accounts Receivable — view/process |
| Customer Receipts | Receipts whose invoice is visible and belongs to this Project, amount/date/method/reference | Accounts Receivable — view |
| Financial / Cost Summary | Same calculation service as Project Cost Report, all dates; filtered report drill-through | Financial Reports — view |
| Activity | New Project and context-Site saves with an exact immutable Project entity token, further restricted by Activity Logs visibility | Activity Logs — view |

Stock values reuse stored Warehouse Stock `total_value`; they are not recomputed from a new valuation formula. Quantities are shown per item/unit rather than summing incompatible units. Material Used sums scoped issue-ledger values for visible posted Stock Issues; it does not add GRNs or count the same material cost again in GL.

Financial semantics: posted expense-account lines tagged with the Project contribute debit minus credit; revenue-account lines contribute credit minus debit. Reversals net off. Margin is posted revenue minus posted cost. Budget used is posted cost divided by master budget (zero when no budget). Supplier Billed and Customer Invoiced preserve the report's **non-draft** semantics, including cancelled documents; these differ from approved-only AR totals and are not cash figures. Cost-centre linkage alone does not attribute a line to a Project. Scope-limited cost compared with the whole Project master budget is not a site-specific budget comparison. No payroll/labour, Site Expense or equipment integration is implied.

#### Return to the Project

- Project → Sites → Add Location opens `/admin/master/projects/{project}/sites/create`; editing uses `/admin/master/projects/{project}/sites/{site}/edit`. These reuse MST-SITE-002/004, not new screens. The URL Project is authoritative; a forged Project or foreign Site is rejected. Save & Close returns to Project → Sites. A site/warehouse-scoped user cannot create a new site outside their existing scope.
- Project → Project Purchases → View opens the existing PO View with a safe return origin. **Back to origin** returns to Project → Project Purchases.
- Project → Customer Invoices → View similarly provides **Back to origin**. Posting/approval/receipt entry still happen only through existing authorized screens.
- Safe return destinations must be relative internal admin paths; external URLs, misleading prefixes and path traversal are refused.

Current limitations: one current Project per employee, no assignment-history engine; no direct stock or journal editing; old free-text/name-only activity omitted because it has no trustworthy entity key; no construction-progress estimate; no full BOQ/budget, Site Expenses, labour/equipment costing, mobile geofence runtime, offline sync or multi-step approval runtime. A Project with sites or warehouses still cannot be deleted.

### [MST-SITE-001 … 004] Locations (Sites)

Web address: `https://seera.tech-brit.co.uk/admin/master/sites` (MST-SITE-001) · `https://seera.tech-brit.co.uk/admin/master/sites/create` (MST-SITE-002) · `https://seera.tech-brit.co.uk/admin/master/sites/{id}` (MST-SITE-003) · `https://seera.tech-brit.co.uk/admin/master/sites/{id}/edit` (MST-SITE-004)

Purpose:
A physical site of a project, with its geo-fence for future attendance checks.

Navigation:
Master Setup → Locations → + Add Location (or Project Details → + Add Location).

Permission required:
Sites — create / view / edit.

Fields:

| Field | What to enter | Example | Required? | Notes |
|---|---|---|---|---|
| Site Name, Site Code | | Riyadh Tower - Main Site, SITE-RCT-01 | Yes | |
| Project | Owning project | Riyadh Commercial Tower | Yes | Fixed when opened from the project |
| Site Supervisor | A user | Ahmed Hassan | Yes | |
| Address, Status | | Olaya, Riyadh / active | Status yes | |
| Latitude, Longitude | Pick on the map or type | 24.7136 / 46.6753 | Yes | Map with a pin and radius circle |
| Geo-Fence Radius (meters) | | 300 | Yes | 10 to 100,000 |
| Geo-Fence Enabled, Attendance Allowed Inside Boundary, Offline Attendance Allowed | Flags | Yes / Yes / No | No | Stored for the future attendance check; not enforced today |

Buttons: Save / Update, Cancel.

When opened from Project → Sites, the form instead offers Save / Save & Close / Save & New (if authorized) / Cancel, keeps the Project fixed, and returns to that Project's Sites section on close. The map and stored geofence flags are unchanged; live attendance enforcement is not implemented.

### Where project figures are today

- Material cost: Project → Material Used, stock issues to the project (chapter 10) and the Project Material Consumption report.
- Posted cost, revenue, supplier billed, customer invoiced, margin and budget used: the Project Cost Report (FIN-REP-007).
- Supplier and customer links: the Supplier and Customer workspaces.
- Labour cost, equipment cost and site expenses: NOT YET OPERATIONAL.

---

## 12. Marketing

| | |
|---|---|
| **Chapter number** | 12 |
| **Chapter name** | Marketing |
| **Purpose** | Leads, visits and conversion to customers. |
| **Primary roles** | Marketing Manager |
| **Screens in this chapter** | MKT-001, MKT-002, MKT-003, MKT-004, MKT-005 |

### [MKT-001] Leads & Visits, [MKT-002] New Lead, [MKT-004] Edit Lead

Web address: `https://seera.tech-brit.co.uk/admin/marketing/leads` (MKT-001) · `https://seera.tech-brit.co.uk/admin/marketing/leads/create` (MKT-002) · `https://seera.tech-brit.co.uk/admin/marketing/leads/{id}/edit` (MKT-004)

Purpose:
Track prospects and the visits made to them.

Who uses it:
Marketing Manager.

Navigation:
Marketing → Leads & Visits → + New Lead.

Permission required:
Marketing — create / view / edit.

Fields:

| Field | What to enter | Example | Required? | Notes |
|---|---|---|---|---|
| Company / Client | Prospect name | Al Noor Development Co. | Yes | |
| Contact Person, Contact Title, Phone, Email | | Khalid Al-Otaibi, Procurement Manager | No | |
| City, Location / Address | | Riyadh | No | |
| Source, Requirement, Estimated Value (SAR) | | Referral / Tower fit-out / 2,000,000 | No | |
| Assigned To | Sales person (a user) | Noura Al-Harbi | No | |
| Status | new, contacted, quotation requested, won, lost … | contacted | No | |
| Next Follow-up, Notes | | 05-Oct-2026 | No | The Leads menu shows the number of follow-ups due |

Buttons: Save / Update, Cancel, Back to Leads.

### [MKT-003] Lead (details, visits, convert)

Web address: `https://seera.tech-brit.co.uk/admin/marketing/leads/{id}`

What you see:
Lead Information, the visits list, and a **Record Visit** form (Visit Date *, Visit Time, Location, Person Met *, Their Title, Outcome *, Next Follow-up, Next Action, Remarks) with **Save Visit**. **Convert to Customer** creates the customer record from the lead once and links it; a converted lead shows the customer.

### [MKT-005] Visit Report

Web address: `https://seera.tech-brit.co.uk/admin/marketing/report`

Filters by period, sales person and outcome; cards Clients Visited, Follow-ups Scheduled, Deals Won; rows Date / Time, Client, Location, Person Met, Visited By, Outcome, Next Follow-up, Remarks; **Export Excel (CSV)** needs Marketing — export.

---

## 13. Activity Logs / Audit

| | |
|---|---|
| **Chapter number** | 13 |
| **Chapter name** | Activity Logs / Audit |
| **Purpose** | Who did what and when. |
| **Primary roles** | Super Admin |
| **Screens in this chapter** | ADM-020 |

### [ADM-020] Activity Logs

Web address: `https://seera.tech-brit.co.uk/admin/activity-logs`

Purpose:
Who did what, when. Every create, update, approve, post, pay, receive, reopen and deactivate writes a line.

Who uses it:
Super Admin; managers see the entries of the users below them in the role hierarchy.

Navigation:
Administration → Activity Logs.

Permission required:
Activity Logs — view.

What you see:
Filters (search, user, module, dates) and the table Date & Time, User, Module, Action, Old Value, New Value, IP Address, Status.

Important:
The Old Value and New Value columns are filled only for a few administrative entries; for business documents the log records the action and the document number, not the field-level change. Activity for one supplier or customer is also shown inside its workspace (Activity tab).

---

## 14. Current ZATCA Foundation

| | |
|---|---|
| **Chapter number** | 14 |
| **Chapter name** | Current ZATCA Foundation |
| **Purpose** | What the local ZATCA records are and are not. |
| **Primary roles** | Finance Manager |
| **Screens in this chapter** | FIN-ZAT-001, FIN-ZAT-002 |

Status: **FOUNDATION ONLY**. Read this chapter before promising anything about e-invoicing to a customer or an auditor.

What Seera does today when a customer invoice is approved:

| Item | What exists | Status |
|---|---|---|
| Local ZATCA record | One record per approved invoice, linked to the invoice | FOUNDATION ONLY |
| UUID | A unique identifier generated locally | FOUNDATION ONLY |
| QR code | A locally built payload (customer, invoice number, date, total, VAT). It is not the ZATCA TLV format and carries no signature | FOUNDATION ONLY |
| XML | A file reference is stored; the XML file itself is not generated | FOUNDATION ONLY |
| Digital signature | Shown as "signed"; no certificate or real signing exists | FOUNDATION ONLY |
| Tamper-proof hash | A local hash of the record; it is not chained to the previous invoice | FOUNDATION ONLY |
| Clearance status | Local statuses draft, generated, pending, cleared, failed, cancelled. The system never sets "cleared" itself | FOUNDATION ONLY |
| Retry | Changes a failed record back to pending and counts the retry. No message is sent anywhere | FOUNDATION ONLY |
| Live Phase-2 clearance, ZATCA responses, production certificates | Do not exist | NOT YET OPERATIONAL |

### [FIN-ZAT-001] ZATCA E-Invoicing (local records)

Web address: `https://seera.tech-brit.co.uk/admin/accounting/zatca`

Cards Cleared, Pending Clearance, Failed, Draft (local statuses); filters; table Invoice, UUID, Customer, Issue Date, QR, XML, Signature, Clearance, ZATCA Response, Retries; **View** and **Retry** (failed records; ZATCA Invoicing — retry). Permission: ZATCA Invoicing — view.

### [FIN-ZAT-002] ZATCA Record

Web address: `https://seera.tech-brit.co.uk/admin/accounting/zatca/{id}`

The record's fields, the QR payload and the local hash, with **Retry Clearance** and **Open Invoice**. Reopening an invoice cancels its local record.

In the Customer workspace the tab is called **Local ZATCA Records** and every status is marked "local, not verified live".

---

## 15. Reports

| | |
|---|---|
| **Chapter number** | 15 |
| **Chapter name** | Reports |
| **Purpose** | Where every report lives and how to export it. |
| **Primary roles** | Finance Manager, Finance Manager (company-level users only), Project Manager, Inventory Manager |
| **Screens in this chapter** | FIN-REP-001, FIN-REP-002, FIN-REP-003, FIN-REP-004, FIN-REP-005, FIN-REP-006, FIN-REP-007, INV-REP-001, INV-REP-002, INV-REP-003, INV-REP-004, INV-REP-005 |

All accounting reports have a period filter (presets or from / to), a Reset button, **Export Excel (CSV)** (needs Financial Reports — export) and **Export PDF** (browser print). Project, site and warehouse scoped users see only their own lines, and opening balances are shown to company-level users only.

| Screen ID | Report | What it shows | Notes |
|---|---|---|---|
| FIN-REP-002 | Balance Sheet | Assets, liabilities and equity as of the "to" date, plus the result to date | Assets = liabilities + equity + result |
| FIN-REP-003 | Profit & Loss | Revenue and expenses inside the period only | Opening balances are never included |
| FIN-REP-004 | Trial Balance | Debit and credit balance per account as of the "to" date; totals must match | |
| FIN-REP-005 | Cash Flow | Opening cash and bank, cash in, cash out, closing | Direct cash and bank movement only; no operating / investing / financing split |
| FIN-REP-006 | VAT Report | Sales taxable, output VAT, purchase taxable, input VAT and VAT payable per period | Company-level users only |
| FIN-REP-007 | Project Cost Report | Per project: budget, posted cost, budget used %, posted revenue, supplier billed, customer invoiced, margin | Net of reversals |
| INV-REP-002 | Stock Valuation | Quantity, average cost and value per item and warehouse | Print only |
| INV-REP-003 | Low Stock | Items at or below the reorder level, with shortfall | Print only; + Purchase Request shortcut |
| INV-REP-004 | Project Material Consumption | Quantity and value issued per project | Print only |
| INV-REP-005 | Stock Movement | Total in, total out, net movement per item | Print only |
| MKT-005 | Visit Report | Marketing visits by period, sales person and outcome | CSV export |

Not available yet: HR reports (attendance register, payroll register), project dashboard and project reports as a menu (use FIN-REP-007 and INV-REP-004).

---

## 16. Common Tasks

| | |
|---|---|
| **Chapter number** | 16 |
| **Chapter name** | Common Tasks |
| **Purpose** | Step-by-step recipes for the most frequent jobs. |
| **Primary roles** | All users |
| **Screens in this chapter** | — (no screens; reference chapter) |

### Create a supplier

1. Sign in.
2. From the left menu, open Master Setup.
3. Select Suppliers.
4. Click + Add Supplier.
5. Enter the Supplier Name (Gulf Steel Trading).
6. Select Payment Terms (30 Days) and Accepted Payment Types (Bank).
7. Select the Linked Payable Account (2100 - Accounts Payable).
8. Tick the projects the supplier works for.
9. Click Save & close.

Expected result: the supplier appears in the Suppliers list.
Next common action: create a Purchase Order, or open the supplier's workspace with Edit / Manage.

### Enter and approve a supplier bill for goods you received

1. Open Inventory → Goods Receipt Notes and click View on the posted receipt (GRN-2026-0008), or open the purchase order (PO-2026-0012) and its Goods Receipts section.
2. Click Create Supplier Bill. The bill form opens with the receipt lines filled in (Cancel and Save & close return to the receipt or the order you started from).
3. Enter the supplier's Bill Number (GST-INV-1045) and check the Bill Date and VAT Rate.
4. Adjust Invoiced Qty or Unit Price only if the supplier's invoice differs.
5. Click Save. The bill details page opens.
6. Click Approve & Post.

Expected result: status *unpaid*, an accounting entry Dr GRNI / Dr Input VAT / Cr Accounts Payable, one VAT row, and the receipt shows the quantity as invoiced.
Next common action: Record Payment when the bill is paid.

### Record a customer receipt

1. Open Finance → Accounts Receivable.
2. Click Receipt on the invoice row (or View → Record Receipt).
3. Enter Receipt Date, choose the bank or cash account, the Payment Method and the amount.
4. Click Record Receipt.

Expected result: the invoice balance reduces; status becomes partially_paid or paid; a journal Dr Bank / Cr Accounts Receivable is posted.

### Add an employee and give them a login

See WF-001.

### Find everything about one customer

1. Open Master Setup → Customers.
2. Click View for a read-only page, or Edit / Manage to work in the workspace.
3. Use the tabs: Contacts, Notes, Projects, Invoices, Receipts & Balance, Ageing, Accounting, Local ZATCA Records, Activity.

### Post a manual journal

See WF-004.

### Close a VAT quarter

See WF-005.

### Switch language

Use the language switch in the top bar. The screen turns to Arabic (right-to-left) or back to English immediately; your data is unchanged.

---

## 17. End-to-End Workflows

| | |
|---|---|
| **Chapter number** | 17 |
| **Chapter name** | End-to-End Workflows |
| **Purpose** | The documented business flows WF-001 to WF-015. |
| **Primary roles** | Super Admin, HR Manager, Finance Manager, Account Assistant, Purchase Manager, Warehouse Incharge, Site In-Charge, Marketing Manager |
| **Workflows in this chapter** | WF-001, WF-002, WF-003, WF-004, WF-005, WF-006, WF-007, WF-008, WF-009, WF-010, WF-011, WF-012, WF-013, WF-014, WF-015 |

Each workflow lists purpose, roles, prerequisites, navigation, steps, example input, the system result, statuses before and after, the audit trail, common errors and the related Screen IDs. The Workflow Index is in [WORKFLOW-INDEX.md](WORKFLOW-INDEX.md).

### WF-001 Create Employee and User Access

Purpose: register a new employee and give them a Seera login.
Roles involved: HR Manager (employee), Super Admin (login).
Prerequisites: department, designation, branch, project and site exist (they can also be added with "+ New" inside the form).
Navigation: Operations → Employees → + Add Employee; then Employee Workspace → System Account tab (or Administration → Users → + Add New User).
Steps:
1. Fill A. Personal (Ahmed Hassan, Egyptian), B. Employment (Site Operations, Site In-Charge, Riyadh Commercial Tower, Riyadh Tower - Main Site, joined 01-Mar-2024, Full Time, Sponsorship), C. Payroll (basic 6,500, housing 1,500, transport 500, Bank Transfer), D. Documents (IQAMA 2412345678, expiry 15-Jun-2027, PDF).
2. Click Save & stay. The Employee Workspace opens.
3. Open the System Account tab. Choose the role (Site In-Charge), status active, leave the password blank, click Save.
4. Give the employee the temporary password. They set their own password at first sign-in (ADM-004).
Expected system result: employee EMP-0042 with an automatic salary structure; a user linked to the employee; the user sees only Riyadh Commercial Tower data because the role is project-scoped.
Statuses: employee active; user active with "must change password".
Audit trail: "Created employee", "Created user" (Users module) entries with your name.
Common errors: contract start in the future; a user already linked to the employee; missing project on a project-scoped role.
Related screens: HR-EMP-002, HR-EMP-004, USR-002, USR-003.

### WF-002 Supplier → Purchase Order → Goods Receipt → Supplier Bill → Payment

Purpose: buy materials, receive them (in one or several deliveries), record the supplier's invoices and pay them, with correct accounting at every step, and read the whole chain from the purchase order.
Roles involved: Site In-Charge (request), Purchase Manager (order), Warehouse Incharge (receipts), Account Assistant (bills), Finance Manager (approve, pay).
Prerequisites: supplier SUP-014 Gulf Steel Trading with payment terms and payable account; item ITM-0031 Reinforcement Steel 16mm; warehouse Riyadh Site Warehouse (project Riyadh Commercial Tower); an open VAT period.
Navigation: Inventory → Purchase Requests → Approve → Create Purchase Order; Purchase Order (INV-PO-003) → Approve Order → Create Goods Receipt; Goods Receipt → Post Stock; Purchase Order → Goods Receipts → Create Supplier Bill; Bill → Approve & Post; Purchase Order → Billing & GRN Matching → Record Payment.
Steps and example input:
1. Purchase request PR-2026-0010 (Site In-Charge): Riyadh Commercial Tower, priority high, 10,000 kg of Reinforcement Steel 16mm, estimated 3.00 per kg. Save. The Purchase Manager approves it (WF-011).
2. Purchase order PO-2026-0012 from the request: supplier Gulf Steel Trading, deliver to Riyadh Site Warehouse, project Riyadh Commercial Tower, line 10,000 kg × Reinforcement Steel 16mm at 3.00, VAT 15% (SAR 30,000 + 4,500 = 34,500). Save, then **Approve Order**. The order page now shows *Nothing received yet · still to receive 10000 kg*.
3. First delivery — goods receipt GRN-2026-0008 from the order (Warehouse Incharge, Purchase Order → Create Goods Receipt): received 6,000, accepted 6,000, unit cost 3.00. **Save** (the receipt opens), then **Post Stock**. Back on the order: *Partially received · 6000 of 10000 kg · still to receive 4000 kg*; the Goods Receipts section lists GRN-2026-0008 as *Received but not invoiced*.
4. Supplier bill GST-INV-1045 (Account Assistant, Purchase Order → Goods Receipts → Create Supplier Bill): line matched to GRN-2026-0008, invoiced qty 6,000 at 3.00 (SAR 18,000 + VAT 2,700 = 20,700). Save. The Finance Manager clicks **Approve & Post**. The order's Billing section now shows Ordered 10000 · Received 6000 · Still to receive 4000 · Invoiced 6000 · Received but not invoiced 0, and the bill GST-INV-1045 with outstanding payment SAR 20,700.
5. Second delivery — goods receipt GRN-2026-0009: received 4,000, accepted 4,000, unit cost 3.00. Save & close returns to the order; open the receipt and Post Stock. The order becomes *Fully received*, billing state *Partly invoiced · received but not invoiced 4000 kg*.
6. Second bill GST-INV-1071 for GRN-2026-0009: 4,000 at 3.00 (SAR 12,000 + 1,800 = 13,800). Save, Approve & Post. The order reads *Fully invoiced*.
7. Payment of GST-INV-1045: 20,700.00 from 1120 Bank Account, Bank Transfer, purpose Bill payment (Purchase Order → Billing → Record Payment). Record Payment returns to the order's Billing section, where the bill now shows Paid 20,700 and Outstanding payment 0. Pay GST-INV-1071 the same way (13,800.00).
Expected accounting result:
- Step 3: Dr 1400 Inventory Asset 18,000 / Cr 2150 Goods Received Not Invoiced 18,000. Stock 6,000 kg at 3.00.
- Step 4: Dr 2150 GRNI 18,000 / Dr 1300 Input VAT 2,700 / Cr 2100 Accounts Payable 20,700. One input VAT row of 2,700.
- Step 5: Dr 1400 Inventory Asset 12,000 / Cr 2150 GRNI 12,000. Stock 10,000 kg at 3.00.
- Step 6: Dr 2150 GRNI 12,000 / Dr 1300 Input VAT 1,800 / Cr 2100 Accounts Payable 13,800. GRNI for the order is back to 0.
- Step 7: Dr 2100 Accounts Payable 20,700 / Cr 1120 Bank 20,700, then 13,800 / 13,800.
Where to read it: the order's Accounting section lists all six journals; each goods receipt shows its own entry and its Bill Matches; each bill shows its GRN Matches, VAT, payments and balance.
Statuses: request draft → pending → approved → converted; PO draft → approved → partially_received → received; GRN draft → posted (twice); bills draft → unpaid → paid.
Audit trail: Created purchase request, Approved purchase request, Created purchase order, Approved purchase order, Created goods receipt, Posted goods receipt, Created supplier bill, Approved supplier bill, Recorded supplier payment — all visible in the order's Activity section.
Common errors: receiving more than the order's outstanding quantity (refused with the remaining figure); a bill for received goods entered as a direct line (goods expensed twice, and the bill does not appear on the order); a bill dated in a finalized VAT period; a payment larger than the balance; a cash account chosen for a bank-only supplier.
Related screens: SUP-002, INV-PR-002, INV-PR-003, INV-PO-002, INV-PO-003, INV-GRN-002, INV-GRN-003, FIN-AP-002, FIN-AP-003, FIN-AP-005, SUP-004.

### WF-003 Customer → Invoice → Receipt

Purpose: invoice a customer and record the money received.
Roles involved: Account Assistant (invoice), Finance Manager (approve, receipt).
Prerequisites: customer CUS-021 with accepted payment types; project; open VAT period.
Navigation: Finance → Accounts Receivable → + Add Customer Invoice; Invoice → Approve & Post; Invoice → Record Receipt.
Steps and example input:
1. Invoice INV-2026-0031: customer Al Noor Development Co., project Riyadh Commercial Tower, date 27-Sep-2026, VAT 15%, line "Progress claim 3", 1 × 2,000.00. Save.
2. Approve & Post.
3. Record Receipt: 2,300.00 into 1120 Bank Account, Bank Transfer.
Expected result: step 2 posts Dr 1200 Accounts Receivable 2,300 / Cr 4100 Project Revenue 2,000 / Cr 2210 Output VAT 300, one output VAT row and a local ZATCA record (pending, not verified live); step 3 posts Dr 1120 Bank 2,300 / Cr 1200 Accounts Receivable 2,300.
Statuses: draft → unpaid → paid.
Audit trail: Created customer invoice, Approved customer invoice, Recorded customer receipt.
Common errors: bank receipt for a cash-only customer; receipt larger than the balance; invoice dated in a finalized period.
Related screens: CUS-002, FIN-AR-002, FIN-AR-003, FIN-AR-005, CUS-004.

### WF-004 Manual Journal → General Ledger

Purpose: record an entry that no document creates (bank charges, accruals).
Roles involved: Finance Manager.
Prerequisites: active accounts; open VAT period if a VAT account is used.
Navigation: Finance → Journal Entries → + Add Journal Entry; Journal → Post to Ledger; Finance → General Ledger.
Steps: date 27-Sep-2026, source Manual, description "Bank charges September", lines 5200 Material Expense Dr 100.00 and 1120 Bank Account Cr 100.00. Save. Post to Ledger. Open the General Ledger filtered on 1120.
Expected result: the journal is posted, the ledger shows the line with the running balance, the P&L for September shows the 100.
Statuses: draft → posted.
Audit trail: Created journal entry, Posted journal entry.
Common errors: unbalanced lines; inactive account; posting twice (refused); trying to edit after posting (refused).
Related screens: FIN-JE-002, FIN-JE-003, FIN-GL-001.

### WF-005 VAT Period Review and Finalize

Purpose: check the quarter's VAT figures and seal the period before filing.
Roles involved: Finance Manager (company-level role).
Prerequisites: all bills and invoices of the quarter approved; draft documents reviewed.
Navigation: Finance → VAT Management → View (period) → Recalculate → Finalize Period; Reports → Accounting Reports → VAT Report.
Steps: open the quarter; compare the Draft Output VAT and Draft Input VAT cards with the unapproved documents; approve or delete the drafts; click Recalculate; check the VAT Report; click Finalize Period.
Expected result: the period status becomes finalized; its totals are frozen; any later VAT-bearing document dated inside it is refused with the period name.
Statuses: draft → finalized.
Audit trail: Recalculated VAT period, Finalized VAT period.
Common errors: finalizing while drafts are still open; trying to reopen a bill dated in the sealed period (refused; use a new document in the open period).
Related screens: FIN-VAT-001, FIN-VAT-002, FIN-REP-006.

### WF-006 Inventory Transfer between Warehouses

Purpose: move stock from one warehouse to another.
Roles involved: Warehouse Incharge (dispatch), a user with Stock Transfers — receive (receive; today the Super Admin).
Navigation: Inventory → Stock Transfers → + Add Stock Transfer; Transfer → Dispatch Transfer; Transfer → Receive Transfer.
Steps: from Riyadh Site Warehouse to Jeddah Site Warehouse, 2 × Reinforcement Steel 16mm. Save. Dispatch. Receive.
Expected result: stock leaves the source at its average cost, then enters the destination at that cost; the ledger shows transfer_out and transfer_in rows; no accounting entry.
Statuses: draft → dispatched → received.
Audit trail: Created stock transfer, Dispatched stock transfer, Received stock transfer.
Common errors: insufficient stock at dispatch; receiving without the receive right.
Related screens: INV-TRF-002, INV-TRF-003, INV-LED-001.

### WF-007 Stock Issue to a Project

Purpose: charge materials to a project.
Roles involved: Warehouse Incharge.
Navigation: Inventory → Stock Issues → + Add Stock Issue; Issue → Post Issue.
Steps: from Riyadh Site Warehouse, issue to Riyadh Commercial Tower / Riyadh Tower - Main Site, 3 × Reinforcement Steel 16mm. Save. Post Issue.
Expected result: stock 10 → 7; Dr 5200 Material Expense 300 / Cr 1400 Inventory Asset 300 tagged with the project and site; the Project Cost Report and Project Material Consumption show 300.
Statuses: draft → posted.
Audit trail: Created stock issue, Posted stock issue.
Common errors: quantity above stock (whole issue refused); issue posted from the wrong warehouse.
Related screens: INV-ISS-002, INV-ISS-003, FIN-REP-007, INV-REP-004.

### WF-008 Payroll Run

Purpose: calculate a month's salaries.
Status: PARTIAL.
Roles involved: HR Manager (create, process), Finance Manager (approve).
Prerequisites: active salary structures; approved overtime for the month.
Navigation: HR Registers & Approvals → Payroll → + Create Payroll Run; Run → Process Payroll; Run → Approve Payroll.
Steps: September 2026, period 01-Sep to 30-Sep, all branches. Save. Process Payroll. Review the employee rows (basic, allowances, overtime, deductions, net). Approve Payroll.
Expected result: the run holds one row per employee with the net amount; the employee's Payroll History tab shows it.
Statuses: draft → processed → approved.
Audit trail: Created payroll run, Processed payroll run, Approved payroll run.
Not done by the system: payslips, bank / WPS file, accounting posting, GOSI or unpaid-leave deductions.
Related screens: HR-PAY-002, HR-PAY-003, HR-SAL-001, HR-EMP-004.

### WF-009 Leave Request lifecycle

Purpose: request, approve or reject leave.
Roles involved: HR Manager.
Navigation: HR Registers & Approvals → Leaves → + Add Leave (or Employee Workspace → Leaves tab); Leave → Approve / Reject Request.
Steps: Ahmed Hassan, Annual Leave, 05-Oct-2026 to 09-Oct-2026 (5 days), reason "Family visit". Save (pending). Approve.
Expected result: approved leave; the employee's annual balance shows 5 used.
Statuses: pending → approved or rejected (with reason).
Audit trail: Created leave request, Approved leave request / Rejected leave request.
Common errors: end date before start date; approving without the approve right.
Related screens: HR-LV-002, HR-LV-003, HR-EMP-004.

### WF-010 Supplier F04 GRNI accounting flow

Purpose: understand the accounting behind receiving goods and matching the supplier's invoice, so that a purchase is never counted twice.
Roles involved: Warehouse Incharge, Finance Manager.
The flow in user language:
1. **Goods receipt confirms goods were received.** Posting it puts the goods into stock and records that the company owes for them, but not yet to a specific invoice: Dr Inventory / Cr Goods Received Not Invoiced (GRNI). No VAT, no supplier balance yet.
2. **The supplier bill matched to the receipt** turns that into a real payable: Dr GRNI / Dr Input VAT / Cr Accounts Payable. The supplier's balance and the VAT return change now, once.
3. **Payment** clears the payable: Dr Accounts Payable / Cr Cash or Bank.
4. A **direct or service bill** with no receipt posts Dr Expense / Dr Input VAT / Cr Accounts Payable.
Rules the system enforces: a receipt line can be invoiced only up to its accepted quantity, across one or many bills; two bills cannot invoice the same quantity; a price difference between the invoice and the receipt goes to the line's expense account, never into inventory; reopening a bill gives the quantity back to the receipt.
You never type these journals yourself. Do not edit posted journals; correct with Reopen or a new document.
How it looks on screen (PO-2026-0012, 10,000 kg at 3.00): after GRN-2026-0008 (6,000 kg) is posted, the order's Billing & GRN Matching section shows Ordered 10000 · Received 6000 · Still to receive 4000 · Invoiced 0 · **Received but not invoiced 6000** — that last figure is the GRNI accrual of SAR 18,000 on account 2150. The receipt's header reads *Received but not invoiced*. When GST-INV-1045 is approved for those 6,000 kg, Invoiced becomes 6000, Received but not invoiced 0, the receipt reads *Invoiced*, the bill's GRN Matches section shows the match as *Invoiced (bill approved)* and the order's Accounting section lists both journals. Received but not invoiced across all orders is what account 2150 holds.
Related screens: INV-PO-003, INV-GRN-003, FIN-AP-002, FIN-AP-003, FIN-AP-005, SUP-004.

### WF-011 Purchase Request → Purchase Order

Purpose: let a site ask for materials and let purchasing turn the request into an order.
Roles involved: Site In-Charge (request), Purchase Manager (approve, order).
Navigation: Inventory → Purchase Requests → + Add Purchase Request; Request → Approve; Request → Create Purchase Order.
Steps: request PR-2026-0010 for Riyadh Commercial Tower, priority high, 10,000 kg × Reinforcement Steel 16mm, estimated 3.00 per kg. Save. Approve. Create Purchase Order (lines copied), choose Gulf Steel Trading, Save, Approve Order.
What you see afterwards: the request page (INV-PR-003) reads *Fully ordered*, its Requested Items show Ordered so far 10000 / Still to order 0 and its Purchase Orders section lists PO-2026-0012 with the received quantity as deliveries are posted; the order page (INV-PO-003) shows the request under Source Purchase Request with a link back.
Statuses: request draft → pending → approved → converted; order draft → approved.
Audit trail: Created purchase request, Approved purchase request, Created purchase order (the request's Activity section and the order's Activity section both show them).
Related screens: INV-PR-002, INV-PR-003, INV-PO-002, INV-PO-003.

### WF-012 Correct an approved bill or invoice (Reopen)

Purpose: fix a wrong approved document without editing posted accounting.
Roles involved: Super Admin.
Prerequisites: the bill or invoice is unpaid with no payments or receipts; its VAT period is still open; for an invoice, the local ZATCA record is not cleared.
Navigation: Bill or Invoice Details → Reopen for Correction → enter the reason.
Expected result: a reversing journal is posted (the original stays), the VAT row is withdrawn, matched receipt quantities are released, the document returns to draft with the reason in its notes. Correct it and Approve & Post again.
Audit trail: Reopened supplier bill / Reopened customer invoice with the reason.
Common errors: reopening a paid bill (refused: reverse the payment first, which is not yet possible in a screen); reopening inside a finalized period (refused).
Related screens: FIN-AP-003, FIN-AR-003.

### WF-013 Stock Adjustment after a physical count

Purpose: correct the on-hand quantity after counting.
Roles involved: Warehouse Incharge (create), Inventory Manager (approve, post).
Navigation: Inventory → Stock Adjustments → + Add Stock Adjustment; Adjustment → Approve → Post Adjustment.
Steps: Riyadh Site Warehouse, Reinforcement Steel 16mm, counted 6 (system 7), reason "Damaged bar scrapped". Save. Approve. Post Adjustment.
Expected result: on hand 7 → 6; Dr 5600 Inventory Adjustment Expense 100 / Cr 1400 Inventory Asset 100.
Statuses: draft → approved → posted.
Related screens: INV-ADJ-002, INV-ADJ-003.

### WF-014 Marketing Lead → Visit → Customer

Purpose: follow a prospect until it becomes a customer.
Roles involved: Marketing Manager.
Navigation: Marketing → Leads & Visits → + New Lead; Lead → Record Visit; Lead → Convert to Customer.
Steps: lead "Al Noor Development Co.", assigned to Noura Al-Harbi, next follow-up 05-Oct-2026. Record a visit (person met Khalid Al-Otaibi, outcome "Quotation requested"). When won, click Convert to Customer.
Expected result: the customer record is created once and linked to the lead; the Customer Workspace opens for contacts and notes.
Related screens: MKT-002, MKT-003, CUS-004.

### WF-015 End of Service settlement

Purpose: calculate and approve an employee's end-of-service benefit.
Roles involved: HR Manager.
Navigation: HR Registers & Approvals → End of Service → + Add EOSB Record; Record → Approve.
Steps: Ahmed Hassan, termination 30-Sep-2026, reason resignation, service years calculated, final wage 8,500. Save (the gratuity is calculated by the labour-law bands; manual override possible). Review the calculation table. Approve.
Expected result: an approved settlement figure. No accounting entry is created; Finance records the payment outside the system for now.
Statuses: draft → approved.
Related screens: HR-EOS-002, HR-EOS-003.

### WF-016 Project operational context

1. Create or open **Al Noor Development Co.** in Customers. Create **Riyadh Commercial Tower** in Projects (MST-PRJ-002), choosing that customer, manager, dates and master budget.
2. Open Project View (MST-PRJ-003), then Sites / Locations → Add Location. Save **Riyadh Tower - Main Site** and use Save & Close to return to the same Project → Sites. Geofence settings are stored, not a live attendance check.
3. In Warehouses (MST-WH-002), assign **Riyadh Site Warehouse** to the Project and Site. On the employee's Employment section (HR-EMP-004), assign the same current Project/Site. Return to Project → Warehouses or Project Team to read the resulting links. No new assignment engine exists.
4. Link **Gulf Steel Trading** to the Project from Supplier Manage. Create a Purchase Request for **Reinforcement Steel 16mm**, then **PO-2026-0012** through the existing Inventory workflow. Project → Purchase Requests / Project Purchases shows only this project's authorized records. Open the PO for lines, partial receiving and F04 billing context (WF-002/WF-010/WF-011).
5. Receive **GRN-2026-0008** into Riyadh Site Warehouse and explicitly Post Stock on its existing screen. Project → Goods Receipts shows stock/invoicing state; receipt is not material consumption.
6. Create and post a Stock Issue to the Project/Site when materials are consumed (WF-007). Project → Material Used shows the posted issue ledger value and per-item/unit quantity. The accounting posting, when present, appears in Project → Financial / Cost Summary through the existing report logic; do not add Material Used to posted cost again.
7. Create **INV-2026-0031** for this Project in Accounts Receivable. Approval and Record Receipt stay explicit authorized actions (WF-003). Project → Customer Invoices / Customer Receipts shows the resulting records and outstanding amounts. Back to origin on Invoice View returns to Project context.
8. Review Project → Financial / Cost Summary, then Open Project Cost Report for the same Project. This is current operational context, not a full project management engine.

Permissions are independent at every step. A project/site-scoped operator sees only rows permitted by the existing global scopes; the same scope feeds panel totals. Read-only pages do not post, approve, receive stock or process payroll.

Pending Phase B: Site Expenses, budget lines/BOQ, labour/payroll-to-GL, equipment cost, expanded budget-vs-actual and a future operational site dashboard. No live ZATCA clearance or F08 multi-step approval runtime is introduced.

---

## 18. Troubleshooting

| | |
|---|---|
| **Chapter number** | 18 |
| **Chapter name** | Troubleshooting |
| **Purpose** | What a message means and what to do. |
| **Primary roles** | All users |
| **Screens in this chapter** | — (no screens; reference chapter) |

| What you see | Why | What to do |
|---|---|---|
| "You do not have permission to perform this action." | Your role lacks the module and action | Ask the Super Admin to grant it (ROL-005) |
| A list is empty although records exist | Your role is project, site or warehouse scoped and your account has no project, site or warehouse | Set the Assigned Project / Site / Warehouse on your user (USR-004) |
| "…is dated …, inside VAT period Q3 2026 which is already finalized. Nothing was recorded." | The document's VAT belongs to a sealed period | Use a date in the open period or a new document |
| "Nothing was recorded: account 5310 Diesel Expense is inactive." | An account on the document is inactive | Reactivate the account or choose another |
| "Total debit must equal total credit" | Journal lines are unbalanced | Correct the amounts |
| "This bill was approved while you were editing it; your changes were not saved." | Someone approved the document after you opened the form | Reopen the document if a correction is still needed |
| "This payment … was already recorded …; nothing was added." | The same payment was submitted twice | Nothing to do; the first payment stands |
| "… has only 6 uninvoiced; this bill asks for 10." | The receipt quantity is already invoiced | Reduce the invoiced quantity or check the earlier bill |
| "Purchase order … has only 4 outstanding" | Receiving more than ordered | Reduce the received quantity or raise the order |
| "This customer does not accept the selected payment channel." | Cash / bank rule on the customer or supplier | Choose the allowed account, or change the rule on the profile |
| Unsaved changes dialog appears when leaving | You changed a form | Keep editing, Discard & leave, or Save current form |
| The reset-password email never arrives | Mail is not configured on the server | Ask the Super Admin to set a temporary password |
| A "Coming Soon" page opens | The menu item is a placeholder | See chapter 20 |

---

## 19. Glossary

| | |
|---|---|
| **Chapter number** | 19 |
| **Chapter name** | Glossary |
| **Purpose** | Terms used in this guide. |
| **Primary roles** | All users |
| **Screens in this chapter** | — (no screens; reference chapter) |

| Term | Meaning |
|---|---|
| Access scope | The part of the company a role may see: All Company, Company Level, Project Level, Site Level, Warehouse Level |
| Approve & Post | The button that makes a bill or invoice final and creates its accounting entry |
| Connected workspace | The Edit / Manage page of a record where its profile and related records are managed in tabs |
| Document workspace | The read-only View page of a purchase order, request, goods receipt or bill that shows the document with everything related to it (receipts, bills, journals, activity), each section by permission |
| Draft | A saved document with no accounting or VAT effect yet |
| GRN | Goods Receipt Note: the document that confirms goods arrived |
| GRNI | Goods Received Not Invoiced, account 2150: the value of received goods whose supplier bill is not yet approved |
| Input VAT / Output VAT | VAT paid on purchases (1300) / VAT charged on sales (2210) |
| Journal entry | A balanced accounting entry; automatic ones come from documents, manual ones from FIN-JE-002 |
| Local ZATCA record | Seera's own record of an approved invoice (UUID, QR payload, status); not a live ZATCA clearance |
| Matched line | A supplier bill line linked to a goods receipt line |
| Outstanding payment | The part of an approved bill not yet paid (bill total minus paid) |
| Posted | Written to the general ledger; cannot be edited |
| Received but not invoiced | Accepted receipt quantity not yet covered by an approved supplier bill; its value is the GRNI accrual |
| Reopen | Return an unpaid approved document to draft with a reversing entry |
| Save & close / Save & new / Save | The standard form buttons (chapter 1) |
| Still to receive | Ordered quantity minus the quantity received by posted goods receipts |
| VAT period | A quarter whose VAT figures are collected and, once finalized, sealed |
| Weighted average cost | The stock valuation method: total value ÷ total quantity per warehouse |

---

## 20. Current Limitations / Not Yet Operational

| | |
|---|---|
| **Chapter number** | 20 |
| **Chapter name** | Current Limitations / Not Yet Operational |
| **Purpose** | What is not usable yet. |
| **Primary roles** | Super Admin, trainers |
| **Screens in this chapter** | — (no screens; reference chapter) |

Areas that exist as menus, settings or plans but are not usable business functions today. Do not train users on them as working features.

| Area | Status | What exists |
|---|---|---|
| Site Expenses (daily site purchases with photo, approval, posting) | NOT YET OPERATIONAL | Expense Categories master, a posting rule row and a seeded approval workflow only |
| Projects & Site Expenses menu, budget lines/BOQ, milestones, site expenses | NOT YET OPERATIONAL | Project Connected Workspace Phase A under Master Setup: existing operational context, one master budget, shared Project Cost Report |
| Equipment & Vehicles | NOT YET OPERATIONAL | Menu placeholder and permission names only |
| Mobile app, check-in / check-out, GPS geofence attendance | NOT YET OPERATIONAL | Mobile access flags on users and roles; site coordinates and radius; manual attendance with a typed geo-fence status |
| Offline entry and sync | NOT YET OPERATIONAL | Nothing |
| Live ZATCA Phase-2 clearance, real QR, XML, signing | NOT YET OPERATIONAL (local records: FOUNDATION ONLY) | Chapter 14 |
| Approval workflow execution (multi-step, all-required) | FOUNDATION ONLY | Builder and seeded workflows; every Approve is a single action |
| Payroll → accounting posting, payslips, bank / WPS file, GOSI | NOT YET OPERATIONAL | Payroll run calculation and approval |
| HR Reports menu (attendance register, payroll register) | NOT YET OPERATIONAL | HR Dashboard for today's figures |
| Project Reports menu | Placeholder | Project Cost Report and Project Material Consumption exist |
| System Settings menu | NOT YET OPERATIONAL | Company Profile |
| Credit notes, payment / receipt reversal | NOT YET OPERATIONAL | Reopen for unpaid documents only |
| VAT period creation screen, zero-rated category | NOT YET OPERATIONAL | Periods created at setup; 0% documents create no VAT row |
| Year-end close / retained earnings | NOT YET OPERATIONAL | Balance sheet result is cumulative |
| Inventory report CSV export | NOT YET OPERATIONAL | Print only |
| Stock Transfers — receive right on standard roles | PARTIAL | Super Admin receives transfers |
| Password reset by email | PARTIAL | Needs mail configuration on the server |
| Purchase order ↔ supplier bill link without a goods receipt match | PARTIAL | A bill is linked to an order only through its goods receipt matches; a direct or service bill is not shown on the order |
| Forms still on the older Save / Cancel layout | PARTIAL | Master Setup (except Suppliers and Customers), HR registers, Inventory (except Goods Receipts), Marketing, Roles, Users (Save & stay only) |

---

## 21. Quick Start by Role

| | |
|---|---|
| **Chapter number** | 21 |
| **Chapter name** | Quick Start by Role |
| **Purpose** | The screens each role uses daily. |
| **Primary roles** | All users |
| **Screens in this chapter** | — (no screens; reference chapter) |

### Super Admin

Daily: Dashboard (ADM-010), Users (USR-001), Roles and Permission Matrix (ROL-001, ROL-005), Activity Logs (ADM-020). Setup: Company Profile (MST-COM-001), Organization Structure (MST-ORG-001), Projects and Locations (MST-PRJ-001, MST-SITE-001), Warehouses (MST-WH-001). Corrections: Reopen on bills and invoices (WF-012), receiving stock transfers (WF-006).

### Finance Manager

1. Accounting Dashboard (FIN-DASH-001): check the action queue.
2. Accounts Payable (FIN-AP-001): approve draft bills, record payments (WF-002).
3. Accounts Receivable (FIN-AR-001): approve invoices, record receipts (WF-003).
4. Journal Entries (FIN-JE-001): post manual journals (WF-004).
5. Supplier and Customer workspaces (SUP-004, CUS-004) for balances, ageing and history.
6. Month end: General Ledger (FIN-GL-001), Trial Balance, Profit & Loss, Balance Sheet (chapter 15); quarter end: VAT (WF-005).

### HR Manager

1. HR Dashboard (HR-DASH-001): today's attendance, pending leaves and overtime, expiring IQAMAs.
2. Employees (HR-EMP-001) and the Employee Workspace (HR-EMP-004) for everything about one person (WF-001).
3. Attendance (HR-ATT-001), Leaves (WF-009), Overtime (HR-OT-001).
4. Salary Structures (HR-SAL-001) and the monthly Payroll Run (WF-008).
5. End of Service (WF-015).

### Project Manager

1. Projects (MST-PRJ-001): your projects, sites, staff, suppliers and warehouses.
2. Locations (MST-SITE-001) with geo-fence data.
3. Purchase Requests (INV-PR-001): approve site requests (WF-011).
4. Project Cost Report (FIN-REP-007) and Project Material Consumption (INV-REP-004).
5. Customer workspace (CUS-004) for the client's invoices and contacts.
Not available yet: project dashboard, budget lines, site expenses, equipment.

### Site In-Charge / Site Supervisor

Current usable screens: Purchase Requests (INV-PR-002) to ask for materials; Manual Attendance (HR-ATT-002) for the site's staff; Stock On Hand (INV-STK-001) for the site store; Locations (MST-SITE-003) to read the site details.
Not available yet: mobile check-in, site expense entry, equipment.

### Warehouse Incharge

Goods Receipt Notes (WF-002 steps 2), Stock Issues (WF-007), Stock Transfers (WF-006), Stock Adjustments (WF-013), Stock On Hand and Stock Ledger.

### Purchase Manager / Assistant

Suppliers (SUP-001, SUP-004), Purchase Requests (INV-PR-003), Purchase Orders (INV-PO-002; INV-PO-003 is the document workspace that shows receipts, billing state and accounting for one order), Goods Receipts for viewing.

### Marketing Manager

Leads & Visits (WF-014), Visit Report (MKT-005), Customers (CUS-004).

---

End of guide. Screen IDs are indexed in [SCREEN-INDEX.md](SCREEN-INDEX.md); workflows in [WORKFLOW-INDEX.md](WORKFLOW-INDEX.md).
