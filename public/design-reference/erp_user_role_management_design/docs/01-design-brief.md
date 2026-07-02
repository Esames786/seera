# Construction ERP - User & Role Management Design Brief

## Purpose
This package defines the first design phase of the Construction ERP Admin Web Portal.

The current Figma design already has Login, Users, Roles, Delete Modals, and Assign Roles. This package upgrades those screens into an ERP-level access management module.

## Main Access Control Concept

Access should be designed in 5 layers:

1. **User**
   - Actual person who uses the system.
   - Example: Omar Sir, Nabeel Sir, Kamran.

2. **Role**
   - Job/access position.
   - Example: Super Admin, Finance Manager, HR Manager, Project Manager, Site Supervisor, Mechanic.

3. **Permission**
   - What a role can do in each module.
   - Example: View, Create, Edit, Delete, Approve, Export.

4. **Access Scope**
   - Where the permission applies.
   - Example: All Company, Selected Branch, Assigned Project, Assigned Site, Assigned Warehouse.

5. **Approval Workflow**
   - How a request moves for approval.
   - Example: Mechanic → Site Supervisor → Project Manager → Finance Manager.

## Screens Covered

### Authentication
- Login
- Forgot Password
- Reset Password

### Dashboard / Navigation
- Main Dashboard
- Role-based sidebar

### User Management
- Users Listing
- Add/Edit User
- User Details
- Deactivate/Delete User modal

### Role Management
- Roles Listing
- Create/Edit Role
- Role Details
- Permission Matrix
- Role Hierarchy
- Assign Users to Role
- Approval Workflow Builder
- Activity Logs

## Sidebar Design

Sidebar should be module-based:

- Dashboard
- Administration
  - Users
  - Roles
  - Permission Matrix
  - Role Hierarchy
  - Approval Workflows
  - Activity Logs
- Accounting
- HR & Payroll
- Projects
- Inventory
- Equipment & Vehicles
- ZATCA
- Reports
- Settings

Sidebar should be permission-based. A user should only see allowed modules.

## Important Correction Against Current Figma

The current Figma is generic. Correct it as follows:

- Add department to roles.
- Add parent role to roles.
- Add access scope to roles/users.
- Add approve permission.
- Add export permission.
- Add mobile app access.
- Add project/site/warehouse access.
- Add role hierarchy screen.
- Add approval workflow builder.
- Add audit/activity logs.
- Add temporary access option during role assignment.