# Instructions for Figma Designer

## Goal
Convert this HTML prototype into a clean Figma design for the Construction ERP Admin Portal.

## Design Style
- Use a professional ERP dashboard style.
- Keep dark left sidebar.
- Use blue as primary action color.
- Use white cards on light gray background.
- Keep tables clean and readable.
- Use status badges for Active, Inactive, Pending, Locked.
- Use tabs for detail pages.
- Use modal designs for delete/deactivate confirmations.

## Recommended Screen Width
- Desktop admin: 1440px width.
- Mobile app screens will be designed later, not in this phase.

## Must-Have Screens in This Phase
1. Login
2. Forgot Password
3. Reset Password
4. Dashboard + Sidebar
5. Users Listing
6. Add/Edit User
7. User Details
8. Roles Listing
9. Create/Edit Role
10. Role Details
11. Permission Matrix
12. Role Hierarchy
13. Assign Users to Role
14. Approval Workflow Builder
15. Activity Logs

## Corrections to Current Figma
- Rename "Delete Roles" to "Delete Role".
- Rename "Delete Users" to "Delete User".
- Rename "Assigning Roles" to "Assign Users to Role".
- Fix duplicate numbering.
- Add Approve and Export permissions.
- Add Access Scope fields.
- Add Department and Parent Role to Role Management.
- Add Employee ID, Iqama, Assigned Project/Site, and Mobile App Access to User Management.
- Add Activity Logs screen.
- Add Approval Workflow Builder screen.
- Add Role Hierarchy screen.

## Sample Roles
- Super Admin
- Finance Manager
- HR Manager
- Project Manager
- Site Supervisor
- Mechanic
- Inventory Manager
- Accountant

## Sample Approval Flow
Mechanic → Site Supervisor → Project Manager → Finance Manager

## Designer Notes
Do not make this a simple user table. It is an ERP access module. The design must clearly show:
- Who the user is.
- What role they have.
- What permissions they have.
- Where those permissions apply.
- Who approves their requests.