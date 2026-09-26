# SEERA ERP — Screen Index (Current System)

Master index of every screen in the current system. Screen IDs are permanent; use them when you refer to a page in training, support tickets or the [User Guide](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md). Workflows are indexed in [WORKFLOW-INDEX.md](WORKFLOW-INDEX.md).

Version 1.0 · Prepared 27 September 2026 · System state: current feature branch (Connected Workspace Standard, Accounting UX Batch 1, Supplier and Customer workspaces, F04 GRNI model) · Status: current implemented system only.

How to read the columns:

- **Navigation Path** is the left-menu path. "→" means click the next item.
- **Permission** columns name the module and action checked by the system. "—" means the action does not exist on that screen.
- **Status**: AVAILABLE, PARTIAL, FOUNDATION ONLY, NOT YET OPERATIONAL.
- **Guide** links to the chapter in the User Guide.

## Sign-in and shell

| Screen ID | Screen Name | Sidebar Module | Navigation Path | Primary User Role | Create | View | Edit | Approve / Process | Status | Guide |
|---|---|---|---|---|---|---|---|---|---|---|
| ADM-001 | Login | — | Sign-in page | All users | — | — | — | — | AVAILABLE | [2](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#2-login-and-navigation) |
| ADM-002 | Forgot Password | — | Login → Forgot password | All users | — | — | — | — | PARTIAL (needs mail setup on the server) | [2](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#2-login-and-navigation) |
| ADM-003 | Reset Password | — | Link from the reset email | All users | — | — | — | — | PARTIAL (needs mail setup on the server) | [2](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#2-login-and-navigation) |
| ADM-004 | Set Your Password (first sign-in) | — | Shown automatically after first sign-in | All new users | — | — | — | — | AVAILABLE | [2](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#2-login-and-navigation) |
| ADM-010 | Dashboard | Main | Dashboard | All users | — | Dashboard — view | — | — | AVAILABLE | [3](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#3-dashboard) |
| ADM-020 | Activity Logs | Administration | Administration → Activity Logs | Super Admin | — | Activity Logs — view | — | — | AVAILABLE | [13](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#13-activity-logs--audit) |

## Users, roles and permissions

| Screen ID | Screen Name | Sidebar Module | Navigation Path | Primary User Role | Create | View | Edit | Approve / Process | Status | Guide |
|---|---|---|---|---|---|---|---|---|---|---|
| USR-001 | Users List | Administration | Administration → Users | Super Admin | Users — create | Users — view | Users — edit | — | AVAILABLE | [4](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#4-users-roles-and-permissions) |
| USR-002 | Add User | Administration | Users → + Add New User | Super Admin | Users — create | — | — | — | AVAILABLE | [4](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#4-users-roles-and-permissions) |
| USR-003 | User Details | Administration | Users → View | Super Admin | — | Users — view | — | — | AVAILABLE | [4](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#4-users-roles-and-permissions) |
| USR-004 | Edit User | Administration | Users → Edit | Super Admin | — | — | Users — edit | — | AVAILABLE | [4](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#4-users-roles-and-permissions) |
| ROL-001 | Roles List | Administration | Administration → Roles | Super Admin | Roles — create | Roles — view | Roles — edit | — | AVAILABLE | [4](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#4-users-roles-and-permissions) |
| ROL-002 | Create Role | Administration | Roles → + Add New Role | Super Admin | Roles — create | — | — | — | AVAILABLE | [4](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#4-users-roles-and-permissions) |
| ROL-003 | Role Details | Administration | Roles → View | Super Admin | — | Roles — view | — | — | AVAILABLE | [4](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#4-users-roles-and-permissions) |
| ROL-004 | Edit Role | Administration | Roles → Edit | Super Admin | — | — | Roles — edit | — | AVAILABLE | [4](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#4-users-roles-and-permissions) |
| ROL-005 | Permission Matrix | Administration | Administration → Permission Matrix | Super Admin | — | Roles — view | Roles — edit | — | AVAILABLE | [4](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#4-users-roles-and-permissions) |
| ROL-006 | Role Hierarchy | Administration | Administration → Role Hierarchy | Super Admin | — | Roles — view | — | — | PARTIAL (display only; no permission inheritance) | [4](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#4-users-roles-and-permissions) |
| ROL-007 | Assign Users to Role | Administration | Administration → Assign Users | Super Admin | — | Roles — view | Roles — edit (Save Changes) | — | AVAILABLE | [4](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#4-users-roles-and-permissions) |
| ROL-008 | Approval Workflows | Administration | Administration → Approval Workflows | Super Admin | Roles — create | Roles — view | Roles — edit | — | FOUNDATION ONLY (configuration is saved; it is not executed by approvals yet) | [4](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#4-users-roles-and-permissions) |
| ROL-009 | Create Approval Workflow | Administration | Approval Workflows → + New Workflow | Super Admin | Roles — create | — | — | — | FOUNDATION ONLY | [4](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#4-users-roles-and-permissions) |
| ROL-010 | Edit Approval Workflow | Administration | Approval Workflows → Edit | Super Admin | — | — | Roles — edit | — | FOUNDATION ONLY | [4](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#4-users-roles-and-permissions) |

## Master Setup

| Screen ID | Screen Name | Sidebar Module | Navigation Path | Primary User Role | Create | View | Edit | Approve / Process | Status | Guide |
|---|---|---|---|---|---|---|---|---|---|---|
| MST-COM-001 | Company Profile | Master Setup | Master Setup → Company Profile | Super Admin | — | Company Profile — view | Company Profile — edit | — | AVAILABLE (ZATCA fields are labels only) | [5](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#5-master-setup) |
| MST-ORG-001 | Organization Structure | Master Setup | Master Setup → Organization Structure | Super Admin | — | Branches / Departments / Designations — view (any) | — | — | AVAILABLE | [5](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#5-master-setup) |
| MST-BR-001 | Branches List | Master Setup | Organization Structure → Branches → Open full list | Super Admin | Branches — create | Branches — view | Branches — edit | — | AVAILABLE | [5](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#5-master-setup) |
| MST-BR-002 | Add Branch | Master Setup | Branches → + Add Branch | Super Admin | Branches — create | — | — | — | AVAILABLE | [5](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#5-master-setup) |
| MST-BR-003 | Branch Details | Master Setup | Branches → View | Super Admin | — | Branches — view | — | — | AVAILABLE | [5](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#5-master-setup) |
| MST-BR-004 | Edit Branch | Master Setup | Branches → Edit | Super Admin | — | — | Branches — edit | — | AVAILABLE | [5](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#5-master-setup) |
| MST-DEP-001 | Departments List | Master Setup | Organization Structure → Departments → Open full list | Super Admin | Departments — create | Departments — view | Departments — edit | — | AVAILABLE | [5](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#5-master-setup) |
| MST-DEP-002 | Add Department | Master Setup | Departments → + Add Department | Super Admin | Departments — create | — | — | — | AVAILABLE | [5](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#5-master-setup) |
| MST-DEP-003 | Department Details | Master Setup | Departments → View | Super Admin | — | Departments — view | — | — | AVAILABLE | [5](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#5-master-setup) |
| MST-DEP-004 | Edit Department | Master Setup | Departments → Edit | Super Admin | — | — | Departments — edit | — | AVAILABLE | [5](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#5-master-setup) |
| MST-DES-001 | Designations List | Master Setup | Organization Structure → Designations → Open full list | Super Admin, HR Manager | Designations — create | Designations — view | Designations — edit | — | AVAILABLE | [5](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#5-master-setup) |
| MST-DES-002 | Add Designation | Master Setup | Designations → + Add Designation | Super Admin, HR Manager | Designations — create | — | — | — | AVAILABLE | [5](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#5-master-setup) |
| MST-DES-003 | Designation Details | Master Setup | Designations → View | Super Admin, HR Manager | — | Designations — view | — | — | AVAILABLE | [5](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#5-master-setup) |
| MST-DES-004 | Edit Designation | Master Setup | Designations → Edit | Super Admin, HR Manager | — | — | Designations — edit | — | AVAILABLE | [5](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#5-master-setup) |
| MST-PRJ-001 | Projects List | Master Setup | Master Setup → Projects | Project Manager, Super Admin | Projects — create | Projects — view | Projects — edit | — | AVAILABLE | [11](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#11-projects) |
| MST-PRJ-002 | Create Project | Master Setup | Projects → + Create Project | Project Manager, Super Admin | Projects — create | — | — | — | AVAILABLE | [11](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#11-projects) |
| MST-PRJ-003 | Project Details | Master Setup | Projects → View | Project Manager, Super Admin | — | Projects — view | — | — | AVAILABLE | [11](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#11-projects) |
| MST-PRJ-004 | Edit Project | Master Setup | Projects → Edit | Project Manager, Super Admin | — | — | Projects — edit | — | AVAILABLE | [11](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#11-projects) |
| MST-PRJ-005 | Project Classifications | Master Setup | Projects → Classifications | Super Admin | Projects — create | Projects — view | Projects — edit | — | AVAILABLE | [11](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#11-projects) |
| MST-SITE-001 | Locations List | Master Setup | Master Setup → Locations | Project Manager, Super Admin | Sites — create | Sites — view | Sites — edit | — | AVAILABLE | [11](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#11-projects) |
| MST-SITE-002 | Add Location | Master Setup | Locations → + Add Location | Project Manager, Super Admin | Sites — create | — | — | — | AVAILABLE | [11](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#11-projects) |
| MST-SITE-003 | Location Details | Master Setup | Locations → View | Project Manager, Super Admin | — | Sites — view | — | — | AVAILABLE | [11](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#11-projects) |
| MST-SITE-004 | Edit Location | Master Setup | Locations → Edit | Project Manager, Super Admin | — | — | Sites — edit | — | AVAILABLE | [11](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#11-projects) |
| MST-WH-001 | Warehouses List | Master Setup | Master Setup → Warehouses | Inventory Manager, Super Admin | Warehouses — create | Warehouses — view | Warehouses — edit | — | AVAILABLE | [5](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#5-master-setup) |
| MST-WH-002 | Add Warehouse | Master Setup | Warehouses → + Add Warehouse | Inventory Manager, Super Admin | Warehouses — create | — | — | — | AVAILABLE | [5](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#5-master-setup) |
| MST-WH-003 | Warehouse Details | Master Setup | Warehouses → View | Inventory Manager, Super Admin | — | Warehouses — view | — | — | AVAILABLE | [5](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#5-master-setup) |
| MST-WH-004 | Edit Warehouse | Master Setup | Warehouses → Edit | Inventory Manager, Super Admin | — | — | Warehouses — edit | — | AVAILABLE | [5](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#5-master-setup) |
| MST-EXP-001 | Expense Categories List | Master Setup | Master Setup → Expense Categories | Finance Manager, Super Admin | Expense Categories — create | Expense Categories — view | Expense Categories — edit | — | AVAILABLE (used on supplier bill lines; the Site Expense entry screen does not exist yet) | [5](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#5-master-setup) |
| MST-EXP-002 | Add Expense Category | Master Setup | Expense Categories → + Add Expense Category | Finance Manager, Super Admin | Expense Categories — create | — | — | — | AVAILABLE | [5](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#5-master-setup) |
| MST-EXP-003 | Expense Category Details | Master Setup | Expense Categories → View | Finance Manager, Super Admin | — | Expense Categories — view | — | — | AVAILABLE | [5](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#5-master-setup) |
| MST-EXP-004 | Edit Expense Category | Master Setup | Expense Categories → Edit | Finance Manager, Super Admin | — | — | Expense Categories — edit | — | AVAILABLE | [5](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#5-master-setup) |

## Suppliers

| Screen ID | Screen Name | Sidebar Module | Navigation Path | Primary User Role | Create | View | Edit | Approve / Process | Status | Guide |
|---|---|---|---|---|---|---|---|---|---|---|
| SUP-001 | Suppliers List | Master Setup | Master Setup → Suppliers | Purchase Manager, Finance Manager | Suppliers — create | Suppliers — view | Suppliers — edit (Edit / Manage) | Suppliers — delete (Deactivate) | AVAILABLE | [8](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#8-suppliers) |
| SUP-002 | Add Supplier | Master Setup | Suppliers → + Add Supplier | Purchase Manager | Suppliers — create | — | — | — | AVAILABLE | [8](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#8-suppliers) |
| SUP-003 | Supplier View (read-only) | Master Setup | Suppliers → View | Any user with Suppliers — view | — | Suppliers — view (each section has its own view permission) | — | — | AVAILABLE | [8](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#8-suppliers) |
| SUP-004 | Supplier Workspace (Edit / Manage) | Master Setup | Suppliers → Edit / Manage | Purchase Manager, Finance Manager | Suppliers — create (Save & New) | Suppliers — view | Suppliers — edit | Panel actions use their own module permissions | AVAILABLE | [8](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#8-suppliers) |
| SUP-005 | Payment Terms | Master Setup | Suppliers → Add Supplier → Payment Terms "+ New", or Master Setup → Payment Terms page | Purchase Manager, Super Admin | Suppliers — create | Suppliers — view | Suppliers — edit | — | AVAILABLE | [8](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#8-suppliers) |

## Customers

| Screen ID | Screen Name | Sidebar Module | Navigation Path | Primary User Role | Create | View | Edit | Approve / Process | Status | Guide |
|---|---|---|---|---|---|---|---|---|---|---|
| CUS-001 | Customers List | Master Setup | Master Setup → Customers | Marketing Manager, Finance Manager | Customers — create | Customers — view | Customers — edit (Edit / Manage) | Customers — delete | AVAILABLE | [7](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#7-customers) |
| CUS-002 | Add Customer | Master Setup | Customers → + Add Customer | Marketing Manager | Customers — create | — | — | — | AVAILABLE | [7](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#7-customers) |
| CUS-003 | Customer View (read-only) | Master Setup | Customers → View | Any user with Customers — view | — | Customers — view (each section has its own view permission) | — | — | AVAILABLE | [7](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#7-customers) |
| CUS-004 | Customer Workspace (Edit / Manage) | Master Setup | Customers → Edit / Manage | Marketing Manager, Finance Manager | Customers — create (contacts, notes, Save & New) | Customers — view | Customers — edit | Panel actions use their own module permissions | AVAILABLE | [7](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#7-customers) |

## HR & Payroll

| Screen ID | Screen Name | Sidebar Module | Navigation Path | Primary User Role | Create | View | Edit | Approve / Process | Status | Guide |
|---|---|---|---|---|---|---|---|---|---|---|
| HR-DASH-001 | HR Dashboard | Operations | Operations → HR Dashboard | HR Manager | — | HR — view | — | — | AVAILABLE | [6](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#6-hr--payroll) |
| HR-EMP-001 | Employees List | Operations | Operations → Employees | HR Manager | HR — create | HR — view | HR — edit | HR — delete (Deactivate) | AVAILABLE | [6](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#6-hr--payroll) |
| HR-EMP-002 | Add Employee | Operations | Employees → + Add Employee | HR Manager | HR — create | — | — | — | AVAILABLE | [6](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#6-hr--payroll) |
| HR-EMP-003 | Employee View (read-only) | Operations | Employees → View | HR Manager | — | HR — view | — | — | AVAILABLE | [6](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#6-hr--payroll) |
| HR-EMP-004 | Employee Workspace (Edit) | Operations | Employees → Edit | HR Manager | HR — create | HR — view | HR — edit (panels use Payroll, Attendance, HR, Users permissions) | Leave / Overtime / EOSB approve in panels | AVAILABLE | [6](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#6-hr--payroll) |
| HR-DOC-001 | Employee Documents / IQAMA | HR Registers & Approvals | HR Registers → Documents / IQAMA | HR Manager | — (documents are added from the Employee form) | HR — view | — | — | AVAILABLE | [6](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#6-hr--payroll) |
| HR-SHF-001 | Shifts List | HR Registers & Approvals | HR Registers → Shifts | HR Manager | HR — create | HR — view | HR — edit | — | AVAILABLE | [6](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#6-hr--payroll) |
| HR-SHF-002 | Add Shift | HR Registers & Approvals | Shifts → + Add Shift | HR Manager | HR — create | — | — | — | AVAILABLE | [6](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#6-hr--payroll) |
| HR-SHF-003 | Edit Shift | HR Registers & Approvals | Shifts → Edit | HR Manager | — | — | HR — edit | — | AVAILABLE | [6](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#6-hr--payroll) |
| HR-ATT-001 | Attendance List | HR Registers & Approvals | HR Registers → Attendance | HR Manager, Site In-Charge | Attendance — create | Attendance — view | Attendance — edit | — | PARTIAL (manual entry only; no check-in / geofence check) | [6](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#6-hr--payroll) |
| HR-ATT-002 | Manual Attendance (Add) | HR Registers & Approvals | Attendance → + Manual Attendance | HR Manager, Site In-Charge | Attendance — create | — | — | — | PARTIAL | [6](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#6-hr--payroll) |
| HR-ATT-003 | Edit Attendance | HR Registers & Approvals | Attendance → Edit | HR Manager | — | — | Attendance — edit | — | PARTIAL | [6](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#6-hr--payroll) |
| HR-LV-001 | Leaves List | HR Registers & Approvals | HR Registers → Leaves | HR Manager | HR — create | HR — view | HR — edit | HR — approve / reject | AVAILABLE | [6](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#6-hr--payroll) |
| HR-LV-002 | Add Leave Request | HR Registers & Approvals | Leaves → + Add Leave | HR Manager | HR — create | — | — | — | AVAILABLE | [6](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#6-hr--payroll) |
| HR-LV-003 | Leave Details | HR Registers & Approvals | Leaves → View | HR Manager | — | HR — view | — | HR — approve / reject | AVAILABLE | [6](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#6-hr--payroll) |
| HR-LV-004 | Edit Leave | HR Registers & Approvals | Leaves → Edit | HR Manager | — | — | HR — edit | — | AVAILABLE | [6](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#6-hr--payroll) |
| HR-OT-001 | Overtime List | HR Registers & Approvals | HR Registers → Overtime | HR Manager | Payroll — create | Payroll — view | Payroll — edit | Payroll — approve | AVAILABLE | [6](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#6-hr--payroll) |
| HR-OT-002 | Add Overtime | HR Registers & Approvals | Overtime → + Add Overtime | HR Manager | Payroll — create | — | — | — | AVAILABLE | [6](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#6-hr--payroll) |
| HR-OT-003 | Edit Overtime | HR Registers & Approvals | Overtime → Edit | HR Manager | — | — | Payroll — edit | — | AVAILABLE | [6](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#6-hr--payroll) |
| HR-SAL-001 | Salary Structures List | HR Registers & Approvals | HR Registers → Salary Structures | HR Manager, Finance Manager | Payroll — create | Payroll — view | Payroll — edit | — | AVAILABLE | [6](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#6-hr--payroll) |
| HR-SAL-002 | Add Salary Structure | HR Registers & Approvals | Salary Structures → + Add Salary Structure | HR Manager | Payroll — create | — | — | — | AVAILABLE | [6](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#6-hr--payroll) |
| HR-SAL-003 | Salary Structure Details | HR Registers & Approvals | Salary Structures → View | HR Manager | — | Payroll — view | — | — | AVAILABLE | [6](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#6-hr--payroll) |
| HR-SAL-004 | Edit Salary Structure | HR Registers & Approvals | Salary Structures → Edit | HR Manager | — | — | Payroll — edit | — | AVAILABLE | [6](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#6-hr--payroll) |
| HR-PAY-001 | Payroll Runs List | HR Registers & Approvals | HR Registers → Payroll | HR Manager, Finance Manager | Payroll — create | Payroll — view | Payroll — edit | Payroll — process, Payroll — approve | PARTIAL (no payslip, no bank file, no accounting posting) | [6](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#6-hr--payroll) |
| HR-PAY-002 | Create Payroll Run | HR Registers & Approvals | Payroll → + Create Payroll Run | HR Manager | Payroll — create | — | — | — | PARTIAL | [6](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#6-hr--payroll) |
| HR-PAY-003 | Payroll Run Details | HR Registers & Approvals | Payroll → View | HR Manager, Finance Manager | — | Payroll — view | — | Payroll — process, Payroll — approve | PARTIAL | [6](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#6-hr--payroll) |
| HR-PAY-004 | Edit Payroll Run | HR Registers & Approvals | Payroll → Edit | HR Manager | — | — | Payroll — edit | — | PARTIAL | [6](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#6-hr--payroll) |
| HR-EOS-001 | End of Service List | HR Registers & Approvals | HR Registers → End of Service | HR Manager | Payroll — create | Payroll — view | Payroll — edit | Payroll — approve | AVAILABLE (calculation only; no accounting posting) | [6](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#6-hr--payroll) |
| HR-EOS-002 | Add EOSB Record | HR Registers & Approvals | End of Service → + Add EOSB Record | HR Manager | Payroll — create | — | — | — | AVAILABLE | [6](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#6-hr--payroll) |
| HR-EOS-003 | EOSB Details | HR Registers & Approvals | End of Service → View | HR Manager | — | Payroll — view | — | Payroll — approve | AVAILABLE | [6](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#6-hr--payroll) |
| HR-EOS-004 | Edit EOSB Record | HR Registers & Approvals | End of Service → Edit | HR Manager | — | — | Payroll — edit | — | AVAILABLE | [6](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#6-hr--payroll) |

## Accounting & Finance

| Screen ID | Screen Name | Sidebar Module | Navigation Path | Primary User Role | Create | View | Edit | Approve / Process | Status | Guide |
|---|---|---|---|---|---|---|---|---|---|---|
| FIN-DASH-001 | Accounting Dashboard | Finance | Finance → Accounting Dashboard | Finance Manager | — | Accounting Dashboard — view | — | — | AVAILABLE | [9](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#9-accounting--finance) |
| FIN-COA-001 | Chart of Accounts | Finance | Finance → Chart of Accounts | Finance Manager | Chart of Accounts — create | Chart of Accounts — view | Chart of Accounts — edit | — | AVAILABLE | [9](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#9-accounting--finance) |
| FIN-COA-002 | Add Account | Finance | Chart of Accounts → + Add Account | Finance Manager | Chart of Accounts — create | — | — | — | AVAILABLE | [9](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#9-accounting--finance) |
| FIN-COA-003 | Account Details | Finance | Chart of Accounts → View | Finance Manager | — | Chart of Accounts — view | — | — | AVAILABLE | [9](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#9-accounting--finance) |
| FIN-COA-004 | Edit Account | Finance | Chart of Accounts → Edit | Finance Manager | — | — | Chart of Accounts — edit | — | AVAILABLE | [9](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#9-accounting--finance) |
| FIN-JE-001 | Journal Entries List | Finance | Finance → Journal Entries | Finance Manager | Journal Entries — create | Journal Entries — view | Journal Entries — edit | Journal Entries — post | AVAILABLE | [9](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#9-accounting--finance) |
| FIN-JE-002 | Add Journal Entry | Finance | Journal Entries → + Add Journal Entry | Finance Manager | Journal Entries — create | — | — | — | AVAILABLE | [9](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#9-accounting--finance) |
| FIN-JE-003 | Journal Entry Details | Finance | Journal Entries → View | Finance Manager | — | Journal Entries — view | Journal Entries — edit (Cancel Entry) | Journal Entries — post | AVAILABLE | [9](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#9-accounting--finance) |
| FIN-JE-004 | Edit Journal Entry | Finance | Journal Entries → Edit | Finance Manager | — | — | Journal Entries — edit | — | AVAILABLE | [9](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#9-accounting--finance) |
| FIN-GL-001 | General Ledger | Finance | Finance → General Ledger | Finance Manager | — | General Ledger — view | — | — | AVAILABLE | [9](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#9-accounting--finance) |
| FIN-AP-001 | Accounts Payable (Supplier Bills) | Finance | Finance → Accounts Payable | Finance Manager, Account Assistant | Accounts Payable — create | Accounts Payable — view | Accounts Payable — edit | Accounts Payable — approve, process (Pay) | AVAILABLE | [9](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#9-accounting--finance) |
| FIN-AP-002 | Add Supplier Bill | Finance | Accounts Payable → + Add Supplier Bill | Account Assistant | Accounts Payable — create | — | — | — | AVAILABLE | [9](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#9-accounting--finance) |
| FIN-AP-003 | Supplier Bill Details | Finance | Accounts Payable → View | Finance Manager | — | Accounts Payable — view | Accounts Payable — edit | Accounts Payable — approve (Approve & Post, Reopen), process (Record Payment) | AVAILABLE | [9](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#9-accounting--finance) |
| FIN-AP-004 | Edit Supplier Bill | Finance | Accounts Payable → Edit (draft only) | Account Assistant | — | — | Accounts Payable — edit | — | AVAILABLE | [9](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#9-accounting--finance) |
| FIN-AP-005 | Record Supplier Payment | Finance | Supplier Bill Details → Record Payment | Finance Manager | — | — | — | Accounts Payable — process | AVAILABLE | [9](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#9-accounting--finance) |
| FIN-AR-001 | Accounts Receivable (Customer Invoices) | Finance | Finance → Accounts Receivable | Finance Manager, Account Assistant | Accounts Receivable — create | Accounts Receivable — view | Accounts Receivable — edit | Accounts Receivable — approve, process (Receipt) | AVAILABLE | [9](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#9-accounting--finance) |
| FIN-AR-002 | Add Customer Invoice | Finance | Accounts Receivable → + Add Customer Invoice | Account Assistant | Accounts Receivable — create | — | — | — | AVAILABLE | [9](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#9-accounting--finance) |
| FIN-AR-003 | Customer Invoice Details | Finance | Accounts Receivable → View | Finance Manager | — | Accounts Receivable — view | Accounts Receivable — edit | Accounts Receivable — approve (Approve & Post, Reopen), process (Record Receipt) | AVAILABLE | [9](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#9-accounting--finance) |
| FIN-AR-004 | Edit Customer Invoice | Finance | Accounts Receivable → Edit (draft only) | Account Assistant | — | — | Accounts Receivable — edit | — | AVAILABLE | [9](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#9-accounting--finance) |
| FIN-AR-005 | Record Customer Receipt | Finance | Customer Invoice Details → Record Receipt | Finance Manager | — | — | — | Accounts Receivable — process | AVAILABLE | [9](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#9-accounting--finance) |
| FIN-VAT-001 | VAT Management | Finance | Finance → VAT Management | Finance Manager (company-level users only) | — | VAT Management — view | — | VAT Management — process (Recalculate), approve (Finalize) | AVAILABLE (periods are created by setup, not by a screen) | [9](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#9-accounting--finance) |
| FIN-VAT-002 | VAT Period Details | Finance | VAT Management → View | Finance Manager | — | VAT Management — view | — | VAT Management — process, approve | AVAILABLE | [9](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#9-accounting--finance) |
| FIN-ZAT-001 | ZATCA E-Invoicing (local records) | Finance | Finance → ZATCA E-Invoicing | Finance Manager | — | ZATCA Invoicing — view | — | ZATCA Invoicing — retry | FOUNDATION ONLY (local record; no live clearance) | [14](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#14-current-zatca-foundation) |
| FIN-ZAT-002 | ZATCA Record Details | Finance | ZATCA E-Invoicing → View | Finance Manager | — | ZATCA Invoicing — view | — | ZATCA Invoicing — retry | FOUNDATION ONLY | [14](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#14-current-zatca-foundation) |
| FIN-CC-001 | Cost Centers List | Finance | Finance → Cost Centers | Finance Manager | Cost Centers — create | Cost Centers — view | Cost Centers — edit | — | AVAILABLE | [9](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#9-accounting--finance) |
| FIN-CC-002 | Add Cost Center | Finance | Cost Centers → + Add Cost Center | Finance Manager | Cost Centers — create | — | — | — | AVAILABLE | [9](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#9-accounting--finance) |
| FIN-CC-003 | Cost Center Details | Finance | Cost Centers → View | Finance Manager | — | Cost Centers — view | — | — | AVAILABLE | [9](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#9-accounting--finance) |
| FIN-CC-004 | Edit Cost Center | Finance | Cost Centers → Edit | Finance Manager | — | — | Cost Centers — edit | — | AVAILABLE | [9](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#9-accounting--finance) |
| FIN-PR-001 | Automatic Posting Rules | Finance | Finance → Automatic Posting Rules | Finance Manager | Auto Posting Rules — create | Auto Posting Rules — view | Auto Posting Rules — edit | — | PARTIAL (only the "Auto Post" switch changes behaviour; accounts on the rule are informational) | [9](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#9-accounting--finance) |
| FIN-PR-002 | Add Posting Rule | Finance | Automatic Posting Rules → + Add Posting Rule | Finance Manager | Auto Posting Rules — create | — | — | — | PARTIAL | [9](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#9-accounting--finance) |
| FIN-PR-003 | Posting Rule Details | Finance | Automatic Posting Rules → View | Finance Manager | — | Auto Posting Rules — view | — | — | PARTIAL | [9](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#9-accounting--finance) |
| FIN-PR-004 | Edit Posting Rule | Finance | Automatic Posting Rules → Edit | Finance Manager | — | — | Auto Posting Rules — edit | — | PARTIAL | [9](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#9-accounting--finance) |
| FIN-REP-001 | Financial Reports (index) | Reports | Reports → Accounting Reports | Finance Manager | — | Financial Reports — view | — | Financial Reports — export (CSV) | AVAILABLE | [15](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#15-reports) |
| FIN-REP-002 | Balance Sheet | Reports | Accounting Reports → Balance Sheet | Finance Manager | — | Financial Reports — view | — | Financial Reports — export | AVAILABLE | [15](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#15-reports) |
| FIN-REP-003 | Profit & Loss | Reports | Accounting Reports → Profit & Loss | Finance Manager | — | Financial Reports — view | — | Financial Reports — export | AVAILABLE | [15](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#15-reports) |
| FIN-REP-004 | Trial Balance | Reports | Accounting Reports → Trial Balance | Finance Manager | — | Financial Reports — view | — | Financial Reports — export | AVAILABLE | [15](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#15-reports) |
| FIN-REP-005 | Cash Flow | Reports | Accounting Reports → Cash Flow | Finance Manager | — | Financial Reports — view | — | Financial Reports — export | AVAILABLE (direct cash and bank movement only) | [15](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#15-reports) |
| FIN-REP-006 | VAT Report | Reports | Accounting Reports → VAT Report | Finance Manager (company-level users only) | — | Financial Reports — view | — | Financial Reports — export | AVAILABLE | [15](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#15-reports) |
| FIN-REP-007 | Project Cost Report | Reports | Accounting Reports → Project Cost Report | Finance Manager, Project Manager | — | Financial Reports — view | — | Financial Reports — export | AVAILABLE | [15](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#15-reports) |

## Inventory & Purchasing

| Screen ID | Screen Name | Sidebar Module | Navigation Path | Primary User Role | Create | View | Edit | Approve / Process | Status | Guide |
|---|---|---|---|---|---|---|---|---|---|---|
| INV-DASH-001 | Inventory Dashboard | Inventory | Inventory → Inventory Dashboard | Inventory Manager | — | Inventory Dashboard — view | — | — | AVAILABLE | [10](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#10-inventory--purchasing) |
| INV-ITEM-001 | Materials / Items List | Inventory | Inventory → Materials / Items | Inventory Manager | Items — create | Items — view | Items — edit | — | AVAILABLE | [10](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#10-inventory--purchasing) |
| INV-ITEM-002 | Add Item | Inventory | Materials / Items → + Add Item | Inventory Manager | Items — create | — | — | — | AVAILABLE | [10](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#10-inventory--purchasing) |
| INV-ITEM-003 | Item Details | Inventory | Materials / Items → View | Inventory Manager | — | Items — view | — | — | AVAILABLE | [10](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#10-inventory--purchasing) |
| INV-ITEM-004 | Edit Item | Inventory | Materials / Items → Edit | Inventory Manager | — | — | Items — edit | — | AVAILABLE | [10](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#10-inventory--purchasing) |
| INV-CAT-001 | Item Categories | Inventory | Inventory → Item Categories | Inventory Manager | Item Categories — create | Item Categories — view | Item Categories — edit | — | AVAILABLE | [10](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#10-inventory--purchasing) |
| INV-CAT-002 | Add Item Category | Inventory | Item Categories → + Add Category | Inventory Manager | Item Categories — create | — | — | — | AVAILABLE | [10](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#10-inventory--purchasing) |
| INV-CAT-003 | Edit Item Category | Inventory | Item Categories → Edit | Inventory Manager | — | — | Item Categories — edit | — | AVAILABLE | [10](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#10-inventory--purchasing) |
| INV-UNIT-001 | Units of Measure | Inventory | Inventory → Units | Inventory Manager | Units — create | Units — view | Units — edit | — | AVAILABLE | [10](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#10-inventory--purchasing) |
| INV-UNIT-002 | Add Unit | Inventory | Units → + Add Unit | Inventory Manager | Units — create | — | — | — | AVAILABLE | [10](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#10-inventory--purchasing) |
| INV-UNIT-003 | Edit Unit | Inventory | Units → Edit | Inventory Manager | — | — | Units — edit | — | AVAILABLE | [10](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#10-inventory--purchasing) |
| INV-STK-001 | Stock On Hand | Inventory | Inventory → Stock On Hand | Inventory Manager, Warehouse Incharge | — | Warehouse Stock — view | — | — | AVAILABLE | [10](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#10-inventory--purchasing) |
| INV-PR-001 | Purchase Requests List | Inventory | Inventory → Purchase Requests | Purchase Manager, Site In-Charge | Purchase Requests — create | Purchase Requests — view | Purchase Requests — edit | Purchase Requests — approve / reject | AVAILABLE | [10](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#10-inventory--purchasing) |
| INV-PR-002 | Add Purchase Request | Inventory | Purchase Requests → + Add Purchase Request | Site In-Charge, Purchase Assistant | Purchase Requests — create | — | — | — | AVAILABLE | [10](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#10-inventory--purchasing) |
| INV-PR-003 | Purchase Request Details | Inventory | Purchase Requests → View | Purchase Manager | — | Purchase Requests — view | — | Purchase Requests — approve / reject | AVAILABLE | [10](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#10-inventory--purchasing) |
| INV-PR-004 | Edit Purchase Request | Inventory | Purchase Requests → Edit | Purchase Assistant | — | — | Purchase Requests — edit | — | AVAILABLE | [10](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#10-inventory--purchasing) |
| INV-PO-001 | Purchase Orders List | Inventory | Inventory → Purchase Orders | Purchase Manager | Purchase Orders — create | Purchase Orders — view | Purchase Orders — edit | Purchase Orders — approve | AVAILABLE | [10](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#10-inventory--purchasing) |
| INV-PO-002 | Add Purchase Order | Inventory | Purchase Orders → + Add Purchase Order | Purchase Assistant | Purchase Orders — create | — | — | — | AVAILABLE | [10](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#10-inventory--purchasing) |
| INV-PO-003 | Purchase Order Details | Inventory | Purchase Orders → View | Purchase Manager | — | Purchase Orders — view | Purchase Orders — edit (quotation files) | Purchase Orders — approve | AVAILABLE | [10](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#10-inventory--purchasing) |
| INV-PO-004 | Edit Purchase Order | Inventory | Purchase Orders → Edit (draft only) | Purchase Assistant | — | — | Purchase Orders — edit | — | AVAILABLE | [10](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#10-inventory--purchasing) |
| INV-GRN-001 | Goods Receipt Notes List | Inventory | Inventory → Goods Receipt Notes | Warehouse Incharge | Goods Receipts — create | Goods Receipts — view | Goods Receipts — edit | Goods Receipts — post (Post Stock) | AVAILABLE | [10](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#10-inventory--purchasing) |
| INV-GRN-002 | Add Goods Receipt | Inventory | Goods Receipt Notes → + Add Goods Receipt, or Purchase Order → Create Goods Receipt | Warehouse Incharge | Goods Receipts — create | — | — | — | AVAILABLE | [10](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#10-inventory--purchasing) |
| INV-GRN-003 | Goods Receipt Details | Inventory | Goods Receipt Notes → View | Warehouse Incharge, Finance Manager | — | Goods Receipts — view | Goods Receipts — edit (draft) | Goods Receipts — post; Accounts Payable — create (Create Supplier Bill) | AVAILABLE | [10](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#10-inventory--purchasing) |
| INV-GRN-004 | Edit Goods Receipt | Inventory | Goods Receipt Notes → Edit (draft only) | Warehouse Incharge | — | — | Goods Receipts — edit | — | AVAILABLE | [10](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#10-inventory--purchasing) |
| INV-ISS-001 | Stock Issues List | Inventory | Inventory → Stock Issues | Warehouse Incharge | Stock Issues — create | Stock Issues — view | Stock Issues — edit | Stock Issues — post | AVAILABLE | [10](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#10-inventory--purchasing) |
| INV-ISS-002 | Add Stock Issue | Inventory | Stock Issues → + Add Stock Issue | Warehouse Incharge | Stock Issues — create | — | — | — | AVAILABLE | [10](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#10-inventory--purchasing) |
| INV-ISS-003 | Stock Issue Details | Inventory | Stock Issues → View | Warehouse Incharge | — | Stock Issues — view | Stock Issues — edit (draft) | Stock Issues — post | AVAILABLE | [10](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#10-inventory--purchasing) |
| INV-ISS-004 | Edit Stock Issue | Inventory | Stock Issues → Edit (draft only) | Warehouse Incharge | — | — | Stock Issues — edit | — | AVAILABLE | [10](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#10-inventory--purchasing) |
| INV-TRF-001 | Stock Transfers List | Inventory | Inventory → Stock Transfers | Warehouse Incharge | Stock Transfers — create | Stock Transfers — view | Stock Transfers — edit | Stock Transfers — transfer (Dispatch), receive (Receive) | AVAILABLE (Receive needs the "receive" right, which the standard roles do not have yet) | [10](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#10-inventory--purchasing) |
| INV-TRF-002 | Add Stock Transfer | Inventory | Stock Transfers → + Add Stock Transfer | Warehouse Incharge | Stock Transfers — create | — | — | — | AVAILABLE | [10](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#10-inventory--purchasing) |
| INV-TRF-003 | Stock Transfer Details | Inventory | Stock Transfers → View | Warehouse Incharge | — | Stock Transfers — view | Stock Transfers — edit (draft) | Stock Transfers — transfer, receive | AVAILABLE | [10](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#10-inventory--purchasing) |
| INV-TRF-004 | Edit Stock Transfer | Inventory | Stock Transfers → Edit (draft only) | Warehouse Incharge | — | — | Stock Transfers — edit | — | AVAILABLE | [10](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#10-inventory--purchasing) |
| INV-ADJ-001 | Stock Adjustments List | Inventory | Inventory → Stock Adjustments | Inventory Manager | Stock Adjustments — create | Stock Adjustments — view | Stock Adjustments — edit | Stock Adjustments — approve, post | AVAILABLE | [10](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#10-inventory--purchasing) |
| INV-ADJ-002 | Add Stock Adjustment | Inventory | Stock Adjustments → + Add Stock Adjustment | Warehouse Incharge | Stock Adjustments — create | — | — | — | AVAILABLE | [10](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#10-inventory--purchasing) |
| INV-ADJ-003 | Stock Adjustment Details | Inventory | Stock Adjustments → View | Inventory Manager | — | Stock Adjustments — view | Stock Adjustments — edit (draft) | Stock Adjustments — approve, post | AVAILABLE | [10](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#10-inventory--purchasing) |
| INV-ADJ-004 | Edit Stock Adjustment | Inventory | Stock Adjustments → Edit (draft only) | Warehouse Incharge | — | — | Stock Adjustments — edit | — | AVAILABLE | [10](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#10-inventory--purchasing) |
| INV-LED-001 | Stock Ledger | Inventory | Inventory → Stock Ledger | Inventory Manager | — | Stock Ledger — view | — | — | AVAILABLE | [10](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#10-inventory--purchasing) |
| INV-REP-001 | Inventory Reports (index) | Inventory / Reports | Inventory → Inventory Reports | Inventory Manager | — | Inventory Reports — view | — | — | AVAILABLE (print only; no CSV yet) | [15](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#15-reports) |
| INV-REP-002 | Stock Valuation Report | Inventory / Reports | Inventory Reports → Stock Valuation | Inventory Manager | — | Inventory Reports — view | — | — | AVAILABLE | [15](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#15-reports) |
| INV-REP-003 | Low Stock Report | Inventory / Reports | Inventory Reports → Low Stock | Inventory Manager | — | Inventory Reports — view | — | — | AVAILABLE | [15](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#15-reports) |
| INV-REP-004 | Project Material Consumption | Inventory / Reports | Inventory Reports → Project Consumption | Inventory Manager, Project Manager | — | Inventory Reports — view | — | — | AVAILABLE | [15](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#15-reports) |
| INV-REP-005 | Stock Movement Report | Inventory / Reports | Inventory Reports → Movement | Inventory Manager | — | Inventory Reports — view | — | — | AVAILABLE | [15](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#15-reports) |

## Marketing

| Screen ID | Screen Name | Sidebar Module | Navigation Path | Primary User Role | Create | View | Edit | Approve / Process | Status | Guide |
|---|---|---|---|---|---|---|---|---|---|---|
| MKT-001 | Leads & Visits List | Marketing | Marketing → Leads & Visits | Marketing Manager | Marketing — create | Marketing — view | Marketing — edit | — | AVAILABLE | [12](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#12-marketing) |
| MKT-002 | New Lead | Marketing | Leads & Visits → + New Lead | Marketing Manager | Marketing — create | — | — | — | AVAILABLE | [12](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#12-marketing) |
| MKT-003 | Lead (details, visits, convert) | Marketing | Leads & Visits → View | Marketing Manager | Marketing — create (Record Visit) | Marketing — view | Marketing — edit (Convert to Customer) | — | AVAILABLE | [12](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#12-marketing) |
| MKT-004 | Edit Lead | Marketing | Leads & Visits → Edit | Marketing Manager | — | — | Marketing — edit | — | AVAILABLE | [12](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#12-marketing) |
| MKT-005 | Visit Report | Marketing | Marketing → Visit Report | Marketing Manager | — | Marketing — view | — | Marketing — export (CSV) | AVAILABLE | [12](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#12-marketing) |

## Menu items that are placeholders

| Screen ID | Screen Name | Sidebar Module | Navigation Path | Status | What exists instead |
|---|---|---|---|---|---|
| CS-001 | Projects & Site Expenses | Operations | Operations → Projects & Site Expenses | NOT YET OPERATIONAL | Projects master (MST-PRJ-001) and the Project Cost Report (FIN-REP-007) exist; there is no site expense entry screen |
| CS-002 | Equipment & Vehicles | Operations | Operations → Equipment & Vehicles | NOT YET OPERATIONAL | Nothing operational; design reference only |
| CS-003 | HR Reports | Reports | Reports → HR Reports | NOT YET OPERATIONAL | HR Dashboard (HR-DASH-001) shows today's figures |
| CS-004 | Project Reports | Reports | Reports → Project Reports | NOT YET OPERATIONAL as a menu; the reports exist | Use Project Cost Report (FIN-REP-007) and Project Material Consumption (INV-REP-004) |
| CS-005 | System Settings | Settings | Settings → System Settings | NOT YET OPERATIONAL | Company Profile (MST-COM-001) holds company settings |

Totals: 132 screens indexed (127 operational or partial screens plus 5 placeholders).
