# Phase 3 Screen-wise Field Specification

## HR Dashboard
Cards: Total Employees, Active Employees, Present Today, Late Today, On Leave, Pending Leaves, Pending Overtime, Current Month Payroll, Expiring IQAMAs, Pending EOSB.

## Employees
Listing columns: Employee Code, Name, Department, Designation, Project/Site, IQAMA Expiry, Basic Salary, Mobile Access, Status, Actions.
Filters: Search, Department, Designation, Branch, Project, Site, Status.

## Add/Edit Employee
Personal: First Name, Last Name, Email, Phone, Emergency Contact, Nationality.
Employment: Employee Code, Department, Designation, Branch, Project, Site, Manager, Joining Date, Contract Type, Contract Start/End, Status.
Saudi Documents: IQAMA Number, IQAMA Expiry, Passport Number, Passport Expiry, Upload.
Payroll: Basic Salary, Payment Method, Bank Name, IBAN.
Access: Link User Account, Mobile App Access.

## Employee Documents
Types: IQAMA, Passport, Contract, Medical Insurance, Driving License, Other.
Fields: Employee, Document Type, Number, Issue Date, Expiry Date, File, Status, Notes.

## Shifts
Fields: Name, Code, Start Time, End Time, Break Minutes, Grace Minutes, Overtime After Minutes, Status.

## Attendance
Fields: Employee, Project, Site, Shift, Date, Check In, Check Out, Status, Source, Geo-Fence, Remarks.

## Leaves
Types: Annual, Sick, Emergency, Unpaid.
Fields: Employee, Leave Type, Start Date, End Date, Total Days, Reason, Status, Approved By, Approved At, Rejection Reason.

## Overtime
Fields: Employee, Attendance Record, Date, Hours, Rate, Amount, Reason, Status, Approved By.

## Salary Structures
Fields: Employee, Basic Salary, Housing, Transport, Food, Other Allowance, Fixed Deduction, Effective From/To, Status.
Additional items: Type, Name, Amount, Taxable.

## Payroll
Fields: Payroll Month, Year, Period Start/End, Branch, Project, Notes.
Basic rule: net = basic + allowances + approved overtime - deductions.

## EOSB
Fields: Employee, Termination Date, Service Years, Last Basic Salary, EOSB Amount, Leave Salary, Other Dues, Deductions, Final Amount, Reason, Status.
Note: Saudi-compliant EOSB calculation rules come later.
