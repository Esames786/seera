# Screen & Field Specification

## 1. Login

### Fields
- Email Address
- Password
- Remember Me checkbox
- Forgot Password link
- Sign In button

### Notes
- Keep centered card.
- Use ERP logo.
- Optional social login can be removed for enterprise ERP if not required.

---

## 2. Forgot Password

### Fields
- Email Address
- Send Reset Link button
- Back to Login link

---

## 3. Reset Password

### Fields
- New Password
- Confirm Password
- Update Password button

---

## 4. Dashboard + Sidebar

### Dashboard Cards
- Total Staff
- Active Roles
- Pending Approvals
- Inactive Users

### Sidebar Groups
- Dashboard
- Administration
- Accounting
- HR & Payroll
- Projects
- Inventory
- Equipment & Vehicles
- ZATCA
- Reports
- Settings

### Rule
Sidebar must be permission-based.

---

## 5. Users Listing

### Cards
- Total Users
- Active Users
- Mobile App Users
- Locked / Inactive Users

### Filters
- Search by name/email/employee ID
- Department
- Role
- Project/Site
- Status

### Table Columns
- Employee ID
- Name
- Email / Phone
- Department
- Primary Role
- Assigned Project/Site
- Mobile Access
- Last Login
- Status
- Actions

### Actions
- View
- Edit
- Assign Role
- Deactivate/Delete

---

## 6. Add/Edit User

### Profile Identity
- Profile Image
- First Name
- Last Name
- Employee ID
- Email Address
- Phone Number
- Language
- Time Zone

### Employment Information
- Department
- Designation
- Joining Date
- Contract Type
- Iqama Number
- Iqama Expiry Date
- Document Upload

### Access & Security
- Username
- Primary Role
- Additional Roles
- Two Factor Auth
- Mobile App Access
- Account Status
- Temporary Access
- Access Start Date
- Access End Date

### Project / Site / Warehouse Scope
- Assigned Branch
- Assigned Project
- Assigned Site
- Assigned Warehouse

---

## 7. User Details

### Tabs
- Overview
- Permissions
- Projects/Sites
- Activity Logs

### Show
- Profile card
- Role
- Status
- Contact
- Assigned site/project
- Access summary
- Recent activity

---

## 8. Roles Listing

### Cards
- Total Roles
- Active Roles
- Assigned Users
- Approval Workflows

### Filters
- Search Role
- Department
- Status
- Parent Role
- Access Scope

### Table Columns
- Role Name
- Department
- Parent Role
- Access Scope
- Total Users
- Status
- Created Date
- Actions

### Actions
- View
- Edit
- Assign Users
- Workflow
- Delete

---

## 9. Create/Edit Role

### Basic Role Information
- Role Name
- Role Code
- Department
- Parent Role
- Role Level
- Default Dashboard
- Description
- Status

### Access Scope
- Company Access
- Branch Access
- Project Access
- Site Access
- Warehouse Access
- Mobile App Access

### Permission Matrix
Rows:
- Dashboard
- Accounting
- HR
- Payroll
- Attendance
- Projects
- Inventory
- Site Expenses
- Equipment
- Vehicles
- ZATCA Invoicing
- Reports
- Settings

Columns:
- View
- Create
- Edit
- Delete
- Approve
- Export
- Mobile Access

---

## 10. Role Details

### Tabs
- Overview
- Permissions
- Assigned Users
- Approval Workflows
- Activity Logs

### Show
- Role info
- Department
- Parent role
- Role level
- Access scope
- Status
- Assigned users
- Connected workflows

---

## 11. Permission Matrix

### Filters
- Role
- Department
- Module Group
- Search Module

### Table
- Module
- View
- Create
- Edit
- Delete
- Approve
- Export
- Mobile Access

### Buttons
- Save Permissions
- Reset
- Cancel

---

## 12. Role Hierarchy

### Left
- Role tree

### Right
- Selected Role Details

### Fields
- Role Name
- Parent Role
- Department
- Role Level
- Can Approve Child Requests
- Status

### Buttons
- Add Child Role
- Update Hierarchy
- Save Changes

---

## 13. Assign Users to Role

### Filters
- Selected Role
- Department
- Project
- Site

### Dual Lists
- Available Users
- Assigned Users

### Temporary Access
- Temporary Access toggle
- Access Start Date
- Access End Date
- Reason
- Approval Required

---

## 14. Approval Workflow Builder

### Basic Fields
- Workflow Name
- Module
- Trigger Action
- Department
- Project/Site Scope
- Status

### Approval Steps
- Step No
- Approver Role
- Approver User
- Approval Required
- Amount Limit
- SLA Time
- Escalation Role
- Can Reject
- Can Send Back
- Action

### Example Flow
Mechanic → Site Supervisor → Project Manager → Finance Manager → Accounting Auto Posting

---

## 15. Activity Logs

### Filters
- Search activity
- Module
- Action
- Date

### Table Columns
- Date & Time
- User
- Module
- Action
- Old Value
- New Value
- IP Address
- Status