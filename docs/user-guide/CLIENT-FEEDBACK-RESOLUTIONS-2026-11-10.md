# Client feedback: issues, fixes and screens

Updated: 10 November 2026. Written in easy English for the client walkthrough.

This table covers the latest feedback batch: 10 voice notes, 19 images and the supplied production error log. **The changes are implemented and locally tested on the feedback branch. They have not been deployed by this work.** Check the installed release before asking the client to test them.

There are **13 points: 10 implemented, 1 partial and 2 pending**. “Implemented” means the software change is ready for review; it does not mean the client has accepted it or the live website has changed.

## Reported issue versus fix

On a phone, swipe the table sideways to read every column. Menu names below use the English interface. Choose Arabic from the application language selector for the corresponding Arabic screens.

| Point | What the client reported | What we changed | Where to find it | How the client can check | Status |
| --- | --- | --- | --- | --- | --- |
| R10-01 | “My IBAN is correct, but the system says it is invalid.” | Copied spaces, invisible spacing and Arabic/Persian digits are handled. Format errors and check-digit errors have clearer messages. Incorrect check digits are still refused. | Employees → Open/Edit → Salary & payment → IBAN. | Copy the complete IBAN from the bank document and save. If it is still refused, compare the exact value privately with that document. | **PARTIAL:** software improved; the client's exact bank value still needs confirmation. |
| R10-02 | “Leave is approved, but the balance still shows 21 days.” | The balance clearly identifies its calendar year. Only approved **Annual Leave** reduces that year's annual balance. Pending days are shown separately. | Employees → View → leave balance and year links; Open/Edit → Leaves. Also My Leave → year selector. | With 21 days available and 3 approved Annual Leave days in the same year, remaining leave is 18. Personal Leave does not reduce it. | **IMPLEMENTED — awaiting deployment and client check.** |
| R10-03 | “I cannot add an employee profile picture.” | Added photo selection, preview, save, replacement and removal. | Employees → Open/Edit → Personal → photo. | Choose a photo, save, reopen and check it. To remove it, select Remove saved photo and save. | **IMPLEMENTED — awaiting deployment and client check.** |
| R10-04 | “The photo/plus button on Add User does nothing.” | The chooser now uploads a photo. The saved user photo appears in the profile, topbar and sidebar. | Users → Add User; or Users → Edit / Manage → Profile. | Choose a photo, save and reopen the user. Employee and User account photos are separate. | **IMPLEMENTED — awaiting deployment and client check.** |
| R10-05 | “Two people with the same role need different access, especially a new employee.” | Super Admin can set **Inherit, Allow or Deny** for one user, with a required reason and an audit record. Deny takes priority over role grants. | Users → that person → Edit / Manage → expand Individual permissions below Profile. | Give one user a Deny exception. Check that this action is blocked for that person while another person with the same role keeps their normal access. | **IMPLEMENTED — awaiting deployment and client check.** |
| R10-06 | “Keep my login and Super Admin; remove the old demo logins.” | Prepared a read-only inventory of user IDs, roles and employee links. No live account has been removed or deactivated. | Owner/operator inventory report; review retained accounts against Users. This is not a new cleanup screen. | Owner first confirms the exact accounts to keep. Review the inventory, then separately approve demo-account deactivation. | **PENDING — live inventory, review and deactivation.** |
| R10-07 | “Old projects, sites and other entries make new entry confusing.” | Prepared a read-only inventory of master records and cost centres with missing linked records. No live master record has been deleted. | Owner/operator inventory report; Projects, Sites and the relevant master lists. | Mark each item Keep or Review. Protect newly entered real records before approving any cleanup. | **PENDING — live inventory and an approved cleanup list.** |
| R10-08 | “Where can I request leave using my own login?” | Added **My Leave** for a user linked to one eligible HR employee. It shows their own requests, status and annual balance. New requests remain Pending. | Topbar → My Leave. If missing, HR checks the linked Employee in User Manage. | Sign in as the linked employee, choose a leave type and dates, and submit. Check that only that employee's records are shown. Approval remains a separate HR action. | **IMPLEMENTED — awaiting deployment and client check.** |
| R10-09 | “Customer and Supplier codes should appear automatically, like Employee codes.” | Added the next-code preview, automatic numbering and an optional manual-code choice. Save & New shows the next preview. Saved old codes are unchanged. | Customers → Add Customer → Code; Suppliers → Add Supplier → Code. | Leave Enter my own code instead unchecked. Save the record, then use Save & New for another. The final saved code may advance if another user saves first. | **IMPLEMENTED — awaiting deployment and client check.** |
| R10-10 | “There is no place to attach Supplier papers.” | Added private document uploads, type, reference and dates, plus View and Download for saved files. | Suppliers → Add Supplier or Edit / Manage → Documents. Supplier View shows saved files without upload controls. | Add a document row and select a file. Save the supplier, reopen and use View or Download. | **IMPLEMENTED — awaiting deployment and client check.** |
| R10-11 | “Customer papers should be attached to the Customer.” | Added the same private document facility for Customers. View stays read-only. | Customers → Add Customer or Edit / Manage → Documents; View → saved documents. | Select a file and save the customer. Confirm that it can be viewed and downloaded from that customer's record. | **IMPLEMENTED — awaiting deployment and client check.** |
| R10-12 | “For Equipment documents, I must save first and move to View. Keep them on the entry screen.” | Equipment documents can now be added with initial asset entry or in Edit. Saved documents are listed on the form. | Equipment & Vehicles → Add Equipment or Edit → Documents. | Enter the asset details, select a document and save once. Reopen the asset and check its saved document. | **IMPLEMENTED — awaiting deployment and client check.** |
| R10-13 | Equipment showed a 500 Server Error. | The log identified a save failure when the initial meter was blank. Blank or omitted meter now saves as 0. Entered readings are kept; negative readings receive a validation message. | Equipment & Vehicles → Add Equipment → initial/current meter reading. | Save a valid training asset with a blank initial meter and check that it saves as 0. A positive entered reading must remain unchanged. | **IMPLEMENTED — the logged save defect is fixed; live check is pending.** |

## Rules confirmed with the owner

| Topic | Agreed behaviour |
| --- | --- |
| Annual balance | Deduct only approved Annual Leave for the selected calendar year. Do not deduct Urgent/Personal Leave. No automatic carry-forward was introduced. |
| Individual access | Inherit role access by default. Super Admin alone manages personal Allow/Deny exceptions and supplies a reason. Deny takes priority. |
| Record access | Personal Allow does not widen project, site or warehouse scope, revive an expired role assignment, or make someone Super Admin. |
| Old users and data | Inventory first. Review exact IDs and retained accounts before approving deactivation or cleanup. |

The supplied leave example was **9 days of Urgent / Personal Leave in August 2025**, while the balance shown was **Annual Leave for 2026**. Under the owner's confirmed rule, that leave must not reduce the 2026 annual balance.

## Other changes the client should know

| Detail | What it means for the user |
| --- | --- |
| Employee and User photos | Both can be uploaded, but one does not automatically copy to the other. |
| Upload limits | Photos: JPG, PNG or WebP, up to 4 MB. Documents: PDF, JPG, PNG or WebP, up to 10 MB each and 10 files per save. Select files again after a validation error. |
| Saving documents | Save the parent Customer, Supplier or Equipment form to store its selected files. Uploading a paper does not approve, post or pay a transaction. |
| Customer removal | A customer with saved documents is protected from direct deletion. Use Inactive when appropriate to retain the record and files. |
| Permissions and probation | Personal exceptions remain until Super Admin changes them. No automatic probation-end schedule was added. The shared Permission Matrix still changes the role for everyone. |
| Super Admin protection | Ordinary User/Role permissions cannot be used to assign the Super Admin role or modify its protected account or role. |
| Accounting history | This feedback batch does not change VAT rounding, salary formulas or historical posted financial transactions. |

## What is still required

1. Deploy the approved candidate using the operator deployment guide. This client guide is not a deployment instruction.
2. Confirm the exact disputed IBAN privately against the bank document.
3. Run the live read-only inventory and approve the accounts and master records to keep. Cleanup has not happened.
4. Walk through the implemented rows with the client on the installed release and record acceptance or a remaining issue against each point ID.

## Related user instructions

- [November entry instructions](CLIENT-ENTRY-UPDATES-2026-11-10.md): step-by-step use of the changed controls.
- [Full user guide](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md): existing workflows and screen reference.
- [Screen Index](SCREEN-INDEX.md): permanent screen IDs and access details.
