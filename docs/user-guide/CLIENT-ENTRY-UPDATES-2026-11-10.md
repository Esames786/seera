# Employee and master entry — November update

Use these instructions for the November entry improvements. Check that the relevant option is available on your portal before following its steps. Pending items are listed in the client feedback table.

For the client review, open [Client feedback: issues, fixes and screens](CLIENT-FEEDBACK-RESOLUTIONS-2026-11-10.md). It lists all 13 reported points, their fixes, the exact screen to visit and the remaining work in easy English.

## Quick screen guide

| Task | Open this screen | What to do |
| --- | --- | --- |
| Add an Employee photo | Employees → Open/Edit → Personal | Choose a photo, check the preview and save. |
| Add a User photo | Users → Add User or Edit / Manage → Profile | Choose the user's photo and save the form. |
| Check annual leave | Employees → View → year links; or My Leave → year selector | Select the correct year. Only approved Annual Leave reduces its annual balance. |
| Request your own leave | Topbar → My Leave | Select the type and dates, enter a reason and submit. HR approval is separate. |
| Change one person's access | Users → Edit / Manage → Individual permissions | Super Admin selects Inherit, Allow or Deny, enters a reason and saves. |
| Use an automatic party code | Customers / Suppliers → Add | Leave the manual-code option unchecked and save. |
| Attach party papers | Customer / Supplier → Add or Edit / Manage → Documents | Add a document row, select a file and save the parent form. |
| Attach Equipment papers | Equipment & Vehicles → Add or Edit → Documents | Select documents while entering the asset, then save. |
| Correct an IBAN entry | Employees → Open/Edit → Salary & payment | Copy the full value from the bank document. If refused, compare the exact value privately. |

Keep using the existing Previous/Back and Save actions in each workspace. A document row is stored when its parent form is saved. An individual-permission change has its own Save individual permissions action.

## Photos / الصور الشخصية

**Employee:** Employees → Open/Edit → Personal. Choose a JPG, PNG or WebP photo, check the preview, then use the normal Save action. Replace it by choosing another image, or select Remove saved photo and save. Photos are private and limited to 4 MB. Initials remain when there is no photo.

**User account:** Users → Add User, or an existing User → Edit / Manage → Profile. The photo chooser now works and the saved photo appears in the user's identity/topbar/sidebar. User-account photos and HR Employee photos are managed separately. Uploading a photo does not link an employee, assign a role or copy payroll/bank data.

**العربية:** اختر الصورة من البيانات الشخصية للموظف أو من ملف المستخدم، راجع المعاينة ثم احفظ. الصور خاصة وبحد أقصى 4 ميغابايت. لإزالتها اختر إزالة الصورة المحفوظة ثم احفظ. تُدار صورة الموظف وصورة المستخدم بشكل منفصل.

## Customer and Supplier codes / رموز العملاء والموردين

On Add Customer/Add Supplier, the readonly field shows the next expected code. Leave **Enter my own code instead** unchecked for automatic numbering. Save & New opens another blank form with an updated preview. If another operator saves first, your final code can advance; the saved code is authoritative.

For a deliberate custom code, check the manual-code option and enter it. Switching back to automatic clears that manual value. Editing an existing master does not renumber it automatically.

**العربية:** تظهر معاينة الرمز التالي عند الإضافة. اترك خيار إدخال رمز خاص غير محدد للترقيم التلقائي. يفتح الحفظ وإضافة جديد نموذجاً بمعاينة محدثة. إذا حفظ مستخدم آخر أولاً فقد يتغير الرمز النهائي. لا تتغير رموز السجلات السابقة تلقائياً.

## Private documents / المستندات الخاصة

Customer/Supplier Create and Edit / Manage now contain Documents. Equipment Create/Edit also accepts documents with the asset details, so an operator can complete initial entry without saving first and moving to View for uploads.

Choose the type and file, optionally enter reference/issued/expiry dates, and use Add another document for more rows. Save the parent form to upload them. The saved list provides View and Download. Read-only Customer/Supplier View allows reading/downloads without edit/upload controls.

Documents accept PDF/JPG/PNG/WebP, up to 10 files per save and 10 MB per file. Equipment document references allow 80 characters; party references allow 100. An unused blank row does not create a document. After validation errors, select files again before resubmitting. Uploading a paper never approves an invoice, assigns equipment, posts a journal or pays a supplier. Confirm required company papers with the owner; the system does not invent mandatory document categories.

**العربية:** أضف المستندات من قسم المستندات في النموذج نفسه ثم احفظ. يظهر لكل مستند محفوظ خيارا العرض والتنزيل. يقبل النظام PDF والصور المذكورة، حتى 10 ملفات في الحفظ الواحد و10 ميغابايت لكل ملف. أعد اختيار الملفات بعد أخطاء التحقق. لا يؤدي رفع المستند إلى اعتماد أو دفع أو ترحيل محاسبي.

## My Leave / إجازاتي

An active user linked to exactly one active HR employee can open My Leave from the topbar. If the link is missing/ambiguous, HR must correct it in User Manage → linked Employee; the system does not guess from a name or employee-code text.

Choose a leave type and dates, enter a reason and optionally attach supporting paperwork. Apply for leave submits **Pending**, with days calculated from the dates. Your own list shows its status. You cannot select another employee or approve your own request from this page. HR approval remains separate; My Leave does not grant HR-wide access.

The balance is **Annual Leave for the selected calendar year**. The owner confirmed that Urgent/Personal leave does not deduct from Annual Leave. A 2025 Personal leave therefore does not reduce the 2026 Annual balance. Pending Annual days are shown separately from approved usage. Employee View has year navigation links; Employee Manage's Leave panel explains the current-year balance. No automatic carry-forward has been introduced.

**العربية:** افتح إجازاتي من الشريط العلوي بعد ربط الحساب بموظف واحد. اختر النوع والتواريخ ثم قدّم الطلب؛ يُحفظ بانتظار الموافقة ويُحسب عدد الأيام من التواريخ. يظهر رصيد الإجازة السنوية للسنة المحددة فقط. لا تُخصم الإجازة الشخصية أو الطارئة من الرصيد السنوي، ولا تُمنح صلاحيات الموارد البشرية العامة بهذه الصفحة.

## Individual permissions / الصلاحيات الفردية

**Super Admin:** Find the employee/login in Users, open Edit / Manage and expand Individual permissions beneath the Profile form. Expand a module to review its actions, role default and effective access:

- **Inherit from roles:** use existing role defaults.
- **Allow:** add this permission for this person.
- **Deny:** refuse this permission even when one or more roles grant it.

Enter a reason and Save individual permissions. Other people sharing the role are unchanged. Changes are audited. If another administrator changed the exceptions, reload before saving instead of overwriting that change. To remove an exception, select Inherit and save with a reason.

Exceptions do not widen project/site/warehouse scope or turn someone into Super Admin. Inactive/expired role assignments cannot be revived by Allow. Super Admin role assignment/profile changes remain protected, so an ordinary Users/Role grant cannot bypass this restriction. Exceptions persist until an administrator changes them; no automatic probation-expiry schedule is implied. Administrative permission changes can remove your own access—review self-edits carefully.

The shared Permission Matrix still changes a **role for everyone**. Its Individual permissions link points to Users for person-specific changes. My Leave is a separate own-record self-service workflow; it does not expose the HR register. Password changes and language switching likewise remain personal account actions.

**العربية:** يدير المشرف العام الاستثناءات من المستخدمين ← التعديل / الإدارة ← الصلاحيات الفردية. الوراثة تستخدم صلاحيات الأدوار، والسماح يضيف صلاحية للشخص، والمنع يتجاوز جميع منح الأدوار. يلزم سبب وتُسجل التغييرات. لا تتغير صلاحيات زملائه بنفس الدور أو حدود المشاريع والمواقع. لإلغاء الاستثناء اختر الوراثة واحفظ. لا يوجد انتهاء تلقائي لفترة التجربة ما لم يُضبط لاحقاً بسياسة معتمدة.

## IBAN / رقم الحساب المصرفي الدولي

Copy the full IBAN from the bank. Spaces and common Arabic/Persian digit presentation are normalized, but check digits must still be correct. A checksum error is not bank ownership verification: compare the exact entered value with the bank document. Never bypass validation or publish the full IBAN in support messages/screenshots.

**العربية:** انسخ رقم IBAN كاملاً من البنك. تُنسق المسافات والأرقام العربية أو الفارسية، لكن يجب أن تكون أرقام التحقق صحيحة. هذا تحقق من التنسيق وليس تأكيداً لملكية الحساب البنكي. قارن الإدخال بوثيقة البنك عند ظهور خطأ ولا تنشر الرقم كاملاً.

## Starting data cleanup

Review the old account and master-entry lists with the owner. Confirm what to keep before approving any deactivation or cleanup. Cleanup is still pending; newly entered real records must be preserved.

## Customer overdue reminders / تذكيرات تأخر العملاء

After the owner activates the daily schedule, an authorized Finance or Super Admin user receives one in-app reminder per overdue invoice each day. Reminders begin one day after its due date. Open **Notifications → Open invoice** to review it. Settled invoices and records outside your permissions/project scope are excluded. This does not send email or SMS.

**العربية:** بعد تفعيل الجدول اليومي، تصل إلى مستخدم المالية أو المدير الأعلى المخوّل رسالة داخل النظام مرة واحدة يومياً لكل فاتورة متأخرة، ابتداءً من اليوم التالي للاستحقاق. افتح الإشعارات ثم الفاتورة. تُستبعد الفواتير المسددة والسجلات خارج نطاق صلاحياتك. لا يُرسل بريد إلكتروني أو رسائل نصية.

## Supplier advances / الدفعات المقدمة للمورد

Open **Suppliers → Edit / Manage → Supplier advances**. Finance must first map its dedicated advance asset account. Posting requires both Accounts Payable approval and payment permissions.

1. Choose **Record a recoverable payment**. Select Advance, Salary paid on behalf, or Repair paid on behalf. Enter date, amount, cash/bank account, project/site, supporting reference and reason. Confirm the agreed right to recover the money. **Post recoverable payment** records the cash outflow and an advance balance; it does not pay an unrelated bill or create VAT.
2. Choose **Apply to bill** only when explicitly clearing an eligible posted bill for the same supplier/project/site. The bill balance reduces, but no additional cash leaves the bank.
3. Choose **Record supplier refund** when the supplier actually returns money. Select the receiving cash/bank account and amount.
4. For a correction, enter a reason and **Reverse** the affected application/refund. Reverse the original payment only after its active applications/refunds have been reversed. History stays visible.

Example: pay 1,000 in advance, apply 400 to a bill and receive 100 back. The remaining recoverable is 500. Old cash payments remain separate. A new taxable back-charge needs its proper tax-document treatment; do not use a recoverable payment to bypass VAT.

**العربية:** افتح الموردين ← تعديل / إدارة ← الدفعات المقدمة للمورد. بعد ربط حساب الأصل المستقل بواسطة المالية، سجّل الدفعة ومرجع الاتفاق وسبب الاسترداد. يمكن تطبيقها صراحةً على فاتورة مرحّلة للمورد والمشروع والموقع أنفسهم، أو تسجيل أموال أعادها المورد. مثال: دفعة ١٠٠٠، وتطبيق ٤٠٠، واسترداد ١٠٠؛ الرصيد المتاح ٥٠٠. لا تُنشأ ضريبة تلقائياً ولا تتغير المدفوعات السابقة. يتطلب العكس سبباً وتبقى السجلات محفوظة.
