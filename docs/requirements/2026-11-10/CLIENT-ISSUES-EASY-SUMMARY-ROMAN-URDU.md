# SEERA: client ne kya kaha, hum ne kya kiya?

Date: 10 November 2026

Ye document sirf **Downloads/seera_today ke is feedback batch** ka hai: **10 voice notes, 19 images aur aapki production wali seera/laravel.log**. Purane tamam sprints ka naya sign-off is document se imply nahi hota.

## Seedha jawab: kya sab ho gaya?

**Tamam 29 media files review hui hain aur 13 actionable points ki list bani hai. Koi point chupaya ya list se nikala nahi gaya. Lekin tamam points ko 100% live complete kehna abhi sahi nahi hoga.**

- **10 points:** software ka kaam complete aur local QA pass.
- **1 point:** IBAN handling improve/test ho gayi; client ke exact bank number ki confirmation pending.
- **2 points:** purane users/data ki actual live cleanup pending. Sirf inventory tool ready hai.
- **Production:** is feedback branch ko hum ne live deploy nahi kiya. GitHub par push hone ka matlab website update hona nahi hai.

Application changes commit: `2da7886b3e93fdf59a15198f8507c45d64e6ff91`.

Branch: `feature/seera-client-feedback-fixes-2026-11-10`. Iske baad wale commits documentation/evidence ke hain.

## Har point: pehle kya tha, ab kya hai?

| # | Client ka issue / kehna | Hum ne kya kiya | Status |
| --- | --- | --- | --- |
| R10-01 | "Bank account/IBAN sahi dala hai, phir bhi invalid aa raha hai." | Bank se copy ki hui extra spaces, invisible spacing aur Arabic/Persian digits handle kiye. Error ab format aur check-digit problem alag samjhata hai. Galat number ko zabardasti accept nahi karwaya. | **PARTIAL:** exact client IBAN ko bank document se milana baqi hai. |
| R10-02 | "Leave approve ho gayi, lekin 21 din poore dikh rahe hain." | Aapki confirmed policy rakhi: sirf approved Annual Leave usi calendar year ke annual balance se deduct hogi. Year dekhne/change karne ka option aur clear explanation diya. | **DONE:** Annual-only rule ke mutabiq. |
| R10-03 | "Employee ki profile picture lagane ka option nahi mil raha." | Employee ke Personal section mein photo choose, preview, save, replace aur remove ka kaam diya. | **DONE** |
| R10-04 | "Add User mein plus/photo button par click se kuch nahi hota." | Dummy button ki jagah working photo upload diya. Create aur Edit / Manage dono par kaam karta hai; saved image user profile, topbar aur sidebar mein dikhti hai. | **DONE** |
| R10-05 | "Same role ke do logon ka access alag hona chahiye, khas taur par new/probation employee ka." | User Manage mein individual Inherit / Allow / Deny permissions di. Sirf Super Admin changes karega, reason dega aur record banega. Ek bande ka change uske colleague ko affect nahi karta. | **DONE:** aapke approved recommended rule ke mutabiq. |
| R10-06 | "Mera aur Super Admin ka login rakho, baqi purane demo users hatao." | Read-only inventory command banayi jo exact account IDs, roles aur employee links dikhati hai. Kisi live user ko delete/deactivate nahi kiya. | **PENDING:** live list review ke baad approved demo accounts deactivate karne hain. |
| R10-07 | "Purane projects/sites/doosri entries confuse kar rahi hain; saaf starting data chahiye." | Inventory mein master records aur aise Cost Centers ki list bhi aati hai jinka linked record missing hai. Purani aur nayi real entries apne aap guess karke nahi hatayi. | **PENDING:** live inventory aur item-wise keep/remove list baqi hai. |
| R10-08 | "Apne login se apni leave request kahan apply karun?" | Topbar mein My Leave diya. Linked employee apni request de sakta hai aur apni history/status dekh sakta hai. Doosre employee ka record nahi khulta; request Pending rehti hai. | **DONE** |
| R10-09 | "Employee ki tarah Customer/Supplier ka agla code bhi khud aaye; main baar baar number na likhun." | Next-code preview, automatic numbering aur optional manual-code choice di. Save & New ke baad next preview milta hai. Purane saved codes ko change nahi kiya. | **DONE** |
| R10-10 | "Supplier ke papers/documents attach karne ka option nahi hai." | Supplier Create/Manage mein documents upload, type/reference/dates aur saved files ke View/Download options diye. | **DONE** |
| R10-11 | "Customer ke documents bhi usi customer ke saath attach hon." | Customer Create/Manage mein wahi private document facility di. Customer View par files dekhi/download hoti hain; upload/edit nahi hota. | **DONE** |
| R10-12 | "Equipment save karke View mein ja kar documents dalne parte hain; pehle page par saath hon." | Equipment ki initial entry aur Edit form mein documents saath attach ho sakte hain. Saved files bhi usi form mein nazar aati hain. | **DONE** |
| R10-13 | Images mein Equipment par 500 Server Error aa raha tha. | Log se blank initial meter ka error mila. Blank/omitted reading ab 0 save hoti hai; entered reading preserve hoti hai. Negative reading validation error deti hai. | **DONE:** branch par fix/test hua; live deployment baqi hai. |

## Leave wali baat example se

Client ki image mein leave **21-29 August 2025**, **Urgent / Personal Leave**, 9 din thi. Lekin upar dikhne wala balance **2026 Annual Leave** ka tha.

Aapne confirm kiya: **sirf Annual Leave deduct honi hai**. Isliye us 2025 Personal Leave ko 2026 Annual balance se minus karna aapki policy badal deta. Hum ne aisa nahi kiya.

Ab year aur type ka matlab clear dikhaya hai. Misal ke taur par 2026 mein 21 annual din hain aur 3 approved Annual Leave din hain to remaining 18 aayega. Pending din alag dikhte hain. Automatic carry-forward add nahi kiya.

## Individual permissions example se

Ali aur Bilal dono Accounts Manager hain. Unke role ka normal access same hai.

- Ali ke liye **Deny** lagaya to woh action Ali nahi kar sakega, chahe role usko allow karta ho.
- Bilal ka access apne aap change nahi hoga.
- **Allow** se us bande ko extra permission milti hai.
- **Inherit** ka matlab wapas role wala normal rule.

Ye options **Users -> us bande ka Edit / Manage -> Individual permissions** mein hain. Shared Permission Matrix ab bhi poore role ko change karti hai; wahan individual settings dhoondhne ka link diya hai.

Project/site ki boundary phir bhi apply hoti hai. Allow laga kar koi Super Admin nahi banta. Probation khatam hone par exceptions apne aap expire nahi hoti; administrator ko update karni hongi. Existing temporary-role dates apni jagah apply hoti hain.

## Kya aapki baat ka matlab change kiya?

**Aapke confirmed business rules ko ulta nahi kiya. Lekin "koi behavior bilkul change nahi hua" kehna bhi sahi nahi hoga, kyunke bugs fix karne ke liye behavior change hota hai. Ye choices aur extra protections khul kar record kar raha hoon:**

| Baat / choice | Kya kiya aur kyun |
| --- | --- |
| Employee aur User ki photo | Dono par upload diya, lekin ek ki photo doosre par automatically copy nahi hoti. Ye implementation choice hai, client ka explicit photo-sharing rule nahi tha. Agar linked photo sync chahiye to woh alag follow-up hai. |
| Apni leave apply karna | My Leave se apni request di ja sakti hai, poore HR ki files/salaries ka access nahi milta. Leave ko auto-approve nahi kiya. |
| Equipment ke meter ka blank hona | Blank ko existing database default ke mutabiq 0 kiya. Client ki entered positive reading replace nahi hoti. |
| Customer ke saved documents | Jis customer ke documents saved hain usko direct hard-delete karne se roka. Inactive karne ka message diya taake documents/history na tootay. Ye additional protection hai. |
| Super Admin security | Review mein purane user/role paths ka bypass mila. Ordinary Users/Roles permissions se Super Admin banne ya uska account/role change karne ka raasta band kiya. Ye extra security fix hai, normal role defaults ka data rewrite nahi. |
| File limits | Photo 4 MB; documents 10 MB per file, ek save mein 10 files. PDF/JPG/PNG/WebP allowed. Ye technical limits hain; har category ka document lazmi nahi banaya. |
| Bohat lambi file names/references/date ranges | Database limit ki wajah se naya 500 na aaye, isliye handling/validation add ki. Bohat lambi saved filename label truncate ho sakti hai; file ke andar ka content nahi badalta. |
| Customer/Supplier codes | Blank code par backend numbering pehle bhi thi. Client ki complaint ke liye visible preview aur deliberate manual/automatic choice improve ki; poora numbering system zero se replace nahi kiya. |
| Ek screen par documents | Profile aur documents ek entry form mein diye. Payment, approval, fuel, maintenance ya accounting ko save button ke saath automatic execute nahi karwaya. |
| Purane users/data | Aapke latest jawab "pehle inventory, phir deactivate" ke mutabiq deletion rok kar inventory ready ki. Ye point pending hai, complete keh kar nahi chhupaya. |

VAT rate/rounding, salary calculation, financial posting aur historical approved/posted records ke business rules is batch mein replace nahi kiye. Production database par koi update/reset nahi chalaya.

## Ab sach mein kya baqi hai?

1. **Live deploy:** abhi website par purana code ho sakta hai; is branch ka deployment hum ne nahi kiya. Is update mein 2 migrations aur naya build.zip chahiye; seeder ya full reset nahi.
2. **Live inventory:** approved release ke baad operator read-only report nikalega. Usmein exact client/Super Admin accounts aur keep/remove entries review hongi.
3. **Approved deactivation/cleanup:** inventory ke baad. Kisi ko abhi delete/deactivate nahi kiya; nayi real entries ko demo samajh kar nahi hatana.
4. **Exact IBAN:** agar bank se dobara copy karne par bhi issue aaye, exact value ko privately bank document se compare karna hai. Bank ownership ya client ke exact number ko abhi verify nahi kiya.
5. **Client walkthrough:** client updated screens par apna workflow approve kare. Local tests aur browser checks client sign-off ka replacement nahi.

Real MySQL par simultaneous Customer/Supplier creation ka separate concurrency probe aur physical phone testing is run mein nahi ki. Browser mobile widths par checks hue hain.

## Testing ka simple status

| Check | Recorded result |
| --- | --- |
| Full Laravel tests | 894 passed, 0 failed; 10,638 assertions |
| JavaScript tests | 12 passed, 0 failed |
| English/Arabic browser checks | 363 passed, 0 failed |
| Screen widths | 320, 390, 768 aur 1440 px; tested pages par page-level horizontal overflow nahi mila |
| Production deployment / live cleanup | Is work mein nahi kiya |

Ye existing saved QA evidence ke results hain. Is simple document ke liye poori suite dobara nahi chalayi; application code change nahi hua.

## Detail chahiye to

- [Har audio/image ki alag explanation aur transcript passes](SOURCE-REVIEW.md)
- [Original 13-point requirements review](CLIENT-FEEDBACK-REVIEW.md)
- [Technical fixes, changed files aur QA evidence](../../release-readiness/SEERA-CLIENT-FEEDBACK-FIXES-2026-11-10.md)
- [New screens ka user guide](../../user-guide/CLIENT-ENTRY-UPDATES-2026-11-10.md)
- [Deployment preflight](../../deployment-client-feedback-2026-11-10.md)
