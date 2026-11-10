# SEERA — source-by-source review, 10 November 2026

Private review index. No application/production change. See [CLIENT-FEEDBACK-REVIEW.md](CLIENT-FEEDBACK-REVIEW.md) for the consolidated requirements and proposed work order.

Coverage: **10 audio files + 19 images = 29 original files**; total container duration **245.065 seconds**. All original hashes were recorded before interpretation. No videos occur in this folder.

Order: lexicographic filename order within each media kind; suffix `(1)` does not establish within-second speaking order. Cross-links are content-based. Timecodes belong to ASR and may overrun slightly or shift; they are not certified human alignment.

Method: offline faster-whisper CPU int8; medium Urdu transcription and small English translation; explicit language Urdu, beam 5, temperature 0, no previous-text conditioning. Baseline uses VAD. Additional A09/A10 passes disable VAD / isolate unclear passages. Machine output is retained as evidence, not guaranteed verbatim. Reviewed meanings below resolve clear translation errors; unresolved terms are explicitly marked.

The original directory and private JSON evidence are local-only. Full bank values, contacts and raw SQL are not repeated. Do not publish original screenshots or the raw log.

## Content sequence

| Topic sequence | Audio | Images | Requirements |
| --- | --- | --- | --- |
| 1. IBAN rejected | A01 | I01, I02 | R10-01 |
| 2. Approved leave not reducing balance | A02 | I03, I05 | R10-02 |
| 3. Employee photo missing | A03 | I05, I15 | R10-03 |
| 4. Add User photo control unresponsive | A04 | I04 | R10-04 |
| 5. Person-specific permission matrix | A05 | I06 | R10-05 |
| 6. Keep two protected logins; remove old demo users | A06 | I07, I08 | R10-06 |
| 7. Clean starting dataset / original hierarchy | A07 | I07, I08, I09, I10 | R10-06, R10-07 |
| 8. Own leave request unavailable | A08 | I09, I06 | R10-08 |
| 9. Automatic party codes and party documents | A09 | I11, I12, I13, I14, I15 | R10-09, R10-10, R10-11 |
| 10. Equipment documents at initial entry | A10 | I16, I17 | R10-12 |
| Additional later failure evidence | — | I18, I19 | R10-13 |

## Audio files

### A01 — IBAN rejected

- Original: [WhatsApp Audio 2026-11-10 at 1.53.03 PM.ogg](<C:/Users/Dell/Downloads/seera_today/WhatsApp Audio 2026-11-10 at 1.53.03 PM.ogg>)
- SHA-256: `a17afe2e7911a5be33a798e950c0e82fccd47fa25342aad7342d40dab697c622`
- Size: 35,640 bytes; container duration: 13.847s.
- Maps to: R10-01; related images: I01, I02.

**Reviewed meaning:** Client says the bank-confirmed account is entered correctly but validation rejects it. Investigate the exact submitted value; a bank check is not established by ASR.

**Accuracy / unresolved detail:** Both passes support the complaint. Exact bank number is intentionally not transcribed into this document.

#### Machine pass: A01-medium-transcribe
Private evidence: `A01-medium-transcribe.json`.

- [0.00–4.00s] تابوش بھئی یہ ایررل اس میں دیتا ہے ایکاؤنڈ کا
- [4.00–7.00s] ایکاؤنڈ میں نے بلکل ٹھیک ڈالا ہے اپنے بینڈ سے چیک کر کے
- [7.00–10.00s] لیکن یہ ایررل دے دیتا ہے کہ یہ ایکاؤنڈ نمبر غلط ہے
- [10.00–12.00s] تو اس کو دوڑا دیکھو یہ کیا مسئلہ ہے بیکینڈ پر
- [12.00–14.00s] پر پوبلم آ رہی ہے کیا مسئلہ ہے

#### Machine pass: A01-small-translate
Private evidence: `A01-small-translate.json`.

- [0.00–1.00s] Assalam-o-Aliakum
- [1.00–4.00s] So this error is given in this account
- [4.00–6.00s] I have put the account completely right
- [6.00–8.00s] by checking it from my bank
- [8.00–10.00s] but this error is given that this account number is wrong
- [10.00–13.00s] so see what is the problem at the back end
- [13.00–14.00s] what is the problem

### A02 — Approved leave not reducing balance

- Original: [WhatsApp Audio 2026-11-10 at 1.53.04 PM.ogg](<C:/Users/Dell/Downloads/seera_today/WhatsApp Audio 2026-11-10 at 1.53.04 PM.ogg>)
- SHA-256: `5987475f0c1390b28debe0444be68f631255aed463aff8e5048de1aa94e6774b`
- Size: 35,752 bytes; container duration: 13.947s.
- Maps to: R10-02; related images: I03, I05.

**Reviewed meaning:** Client expects approved leave to be subtracted from the displayed 21-day balance.

**Accuracy / unresolved detail:** Small translation mangles leave/approval wording. Urdu and screenshot establish the subject. Screenshot compares 2026 annual balance to 2025 urgent/personal leave; policy remains unresolved.

#### Machine pass: A02-medium-transcribe
Private evidence: `A02-medium-transcribe.json`.

- [0.00–2.58s] یہ تابش بھائی میں نے لیڈز ڈالی ہے
- [2.58–4.80s] لیکن یہ کٹی نہیں ہے اس میں سے
- [4.80–6.76s] 21 پوری آ رہی ہے
- [6.76–9.72s] 21 میں سے کارٹ کے باقی دن چاہیے تھے نا اس کو
- [9.72–11.58s] اب روز بھی ہوگئی ہے ساڑا کچھ ہوگئے
- [11.58–14.10s] لیکن اس نے پیلنس نہیں معنیس کیا اس میں سے

#### Machine pass: A02-small-translate
Private evidence: `A02-small-translate.json`.

- [0.00–4.80s] I have put the leaves in the pot but it is not bitter at all.
- [4.80–6.80s] It is coming out of 21.
- [6.80–9.80s] The rest of the leaves should have been cut out of 21.
- [9.80–11.80s] It has been proved that something is wrong.
- [11.80–13.80s] But it did not minus the balance.

### A03 — Employee photo missing

- Original: [WhatsApp Audio 2026-11-10 at 1.53.05 PM.ogg](<C:/Users/Dell/Downloads/seera_today/WhatsApp Audio 2026-11-10 at 1.53.05 PM.ogg>)
- SHA-256: `5284188800677ded16b2ba04725a3efad08caa9e7f64248533cc421598d48573`
- Size: 20,234 bytes; container duration: 7.846s.
- Maps to: R10-03; related images: I05, I15.

**Reviewed meaning:** Client cannot find an employee profile-photo upload option.

**Accuracy / unresolved detail:** Both passes agree. Document attachments are a separate feature.

#### Machine pass: A03-medium-transcribe
Private evidence: `A03-medium-transcribe.json`.

- [0.00–3.40s] تابیش ویس میں پچر لگانے کا اوپشن نہیں مجھے کہیں بھی ملا
- [3.40–7.80s] کہ ایمپلائی کے پچر جو ایڈ کرنی ہے پروفائل پچر وہ اس کا اوپشن نہیں نظر آ رہا ہے

#### Machine pass: A03-small-translate
Private evidence: `A03-small-translate.json`.

- [0.00–8.00s] I didn't have the option to put a picture in it, I just wanted to add the profile picture of the employee

### A04 — Add User photo control unresponsive

- Original: [WhatsApp Audio 2026-11-10 at 1.53.06 PM.ogg](<C:/Users/Dell/Downloads/seera_today/WhatsApp Audio 2026-11-10 at 1.53.06 PM.ogg>)
- SHA-256: `f16448e15ee51eb215f5331ba7a40192eac574cfbb2fa82543754c0ee9b12eeb`
- Size: 16,167 bytes; container duration: 6.266s.
- Maps to: R10-04; related images: I04.

**Reviewed meaning:** Add User displays a photo option/plus, but clicking does not let the client add a picture.

**Accuracy / unresolved detail:** Small translation misphrases the negative; Urdu clearly reports a nonworking plus/control, not a request to remove the control.

#### Machine pass: A04-medium-transcribe
Private evidence: `A04-medium-transcribe.json`.

- [0.00–3.92s] یہ ایڈ یوزر میں یہ اوپشن آ رہا ہے لیکن یہاں پر یہ پچھل کو ایڈ نہیں کرنے دی رہا
- [3.92–6.32s] پلس کے سائن پر کلک کریں تو کچھ نہیں ہو رہا

#### Machine pass: A04-small-translate
Private evidence: `A04-small-translate.json`.

- [0.00–6.00s] This option is available in the add user, but you don't have to add it here, if you click on the plus sign, nothing will happen.

### A05 — Person-specific permission matrix

- Original: [WhatsApp Audio 2026-11-10 at 1.53.07 PM.ogg](<C:/Users/Dell/Downloads/seera_today/WhatsApp Audio 2026-11-10 at 1.53.07 PM.ogg>)
- SHA-256: `53331db8b2b2e62b0e024b23d4399e99a9873fd74483a54c21df2ace06c2ccf8`
- Size: 82,771 bytes; container duration: 32.106s.
- Maps to: R10-05; related images: I06.

**Reviewed meaning:** Access should differ between people sharing a role: Accounts Manager, Site Supervisor, fresh employee or someone on probation. Client wants employee-wise permission configuration.

**Accuracy / unresolved detail:** English pass is repetitive and incomplete; Urdu supports the interpretation. Precise grant/deny precedence and automatic probation expiry are not specified.

#### Machine pass: A05-medium-transcribe
Private evidence: `A05-medium-transcribe.json`.

- [0.00–4.56s] یہ جو پرمیشن میکرس ہے نا یہ رول یا موڈل کے ساتھ سی نہیں ہونی چاہیے
- [4.56–7.16s] مطلب ساتھ اس میں ایمپلائی بھی آنا چاہیے
- [7.16–10.20s] پھر اگر یہ ایک اکاؤنٹس مینجر ہے اس کو ہوسکتا ہم نے دینی ہو
- [10.20–12.52s] کچھ چیزوں کی ساتھ دوسرے کو نہ دینی ہو
- [12.52–15.36s] یا ایک سائٹ سپوروائزر ہے اس کو کچھ چیزوں کی ساتھ دینی ہو
- [15.36–19.20s] دوسرے کو نہ دینی ہو کوئی نیا فریشی آتا ہے تو اس کی لیمٹیڈ اس کا ساتھ نہیں ہو
- [19.20–22.40s] کوئی پروبیشن پر ہے تو اس کی لیمیٹیڈ ایسا سکتی ہے
- [22.40–26.40s] ایسا تو ایک ایموڈیول کو ایسا سکتی ہے تو سب کے لیے اجازت ہو جائیں گے نا
- [26.40–29.40s] اس دیزنیشن سے لیڈ بندے کے لیے
- [29.40–32.40s] تو یہاں پر ایموڈیو کے ساپ سے ہونے چاہیے

#### Machine pass: A05-small-translate
Private evidence: `A05-small-translate.json`.

- [0.00–7.00s] The permission matrix is a role or a module, it should be employed.
- [7.00–12.00s] If there is an account manager, he or she has to give it to someone else.
- [12.00–16.00s] If there is a site supervisor, he or she has to give it to someone else.
- [16.00–19.00s] If there is a new fresh, he or she has to give it to someone else.
- [19.00–22.00s] If there is a probation, he or she has to give it to someone else.
- [22.00–26.00s] If they give it to someone else, he or she has to give it to someone else.
- [26.00–32.00s] I'm going to show you what I'm going to show you.

### A06 — Keep two protected logins; remove old demo users

- Original: [WhatsApp Audio 2026-11-10 at 1.53.08 PM (1).ogg](<C:/Users/Dell/Downloads/seera_today/WhatsApp Audio 2026-11-10 at 1.53.08 PM (1).ogg>)
- SHA-256: `434df0f0297df12e0d34d3f2d5b37aae1c5f72ff80e0afa4451335e866f7e582`
- Size: 19,813 bytes; container duration: 8.107s.
- Maps to: R10-06; related images: I07, I08.

**Reviewed meaning:** Keep the client's login and the Super Admin login; remove other old demo logins so the client can recreate real users.

**Accuracy / unresolved detail:** Small translation says files; Urdu says users. Do not interpret this as permission to delete physical files. Protected account IDs are not specified.

#### Machine pass: A06-medium-transcribe
Private evidence: `A06-medium-transcribe.json`.

- [0.08–5.28s] یہ صرف میرا یوزر چھوڑ کے اور سوپر ایڈمن کا یوزر چھوڑ کے باقی ساڑھے جو ہے نا آپ ڈلیٹ کرنے
- [6.08–8.08s] میں خود دوبارہ سے نئے جنریٹ کروں گا

#### Machine pass: A06-small-translate
Private evidence: `A06-small-translate.json`.

- [0.08–5.58s] This is just leaving my user and super admin user and deleting the rest of the files.
- [5.58–8.08s] I will generate a new one myself.

### A07 — Clean starting dataset / original hierarchy

- Original: [WhatsApp Audio 2026-11-10 at 1.53.08 PM.ogg](<C:/Users/Dell/Downloads/seera_today/WhatsApp Audio 2026-11-10 at 1.53.08 PM.ogg>)
- SHA-256: `5d67648603eb2e03584e0fbf66fbea602c4f19a85b503f7e3fac6b3721dbf657`
- Size: 150,536 bytes; container duration: 58.687s.
- Maps to: R10-06, R10-07; related images: I07, I08, I09, I10.

**Reviewed meaning:** Client wants old users/sites/projects/entry remnants cleared to avoid confusion while entering real employees, users, roles and permissions in the correct sequence.

**Accuracy / unresolved detail:** Several nouns are imperfectly recognized, but sites/projects and old users are supported across passes. Screenshot of a Cost Center is context, not an approved record-by-record delete list.

#### Machine pass: A07-medium-transcribe
Private evidence: `A07-medium-transcribe.json`.

- [0.00–5.04s] میں نے آپ کو بولا بھی تھا اس میں کہ مجھے ہی زیرو زیرو آپ نے کرکے دینا چاہتے ہیں
- [5.04–7.44s] یہ بھی ہی ہی ہی ہی شوہ رہے ہیں
- [7.44–11.20s] اور بھی بڑی چیز جگہوں پر ایمپلائی جب میں ایڈ کر رہا تھا تو کافی سارے چیزیں ہیں
- [11.20–14.40s] اپنے سائٹس اور پروڈیٹس جو پہلے آئیے ہیں
- [14.40–16.40s] میں نے بھی 0 سے دوبارہ سٹارٹ کرنا
- [16.40–19.00s] اور بلکل صحیح طریقے سے کرنا ہے
- [19.00–21.00s] جو انٹریز پوری مکمل کرکے
- [21.00–23.00s] جیسا بھی میں انپلائی آئیے ہیں
- [23.00–25.00s] انپلائی سے میں آئی ہوں
- [25.00–27.00s] پھر رول میں گیا ہوں پھر مشن میں گیا ہوں
- [27.00–29.00s] یعنی پورا ایک جس طریقے کار ہے
- [29.00–30.80s] ہرارکی ہے اس کے مطابق چلنا
- [30.80–32.80s] پھر میں اس میں مسئلہ آئے گا
- [32.80–34.80s] پرانی چیزیں ہوں گی وہ کنشوز کریں
- [34.80–40.00s] اس کو دیکھیں اس کو بیک اینڈ سے اس کو بیپل جیرو کر دیں اس میں کوئی چیز پڑھانی نہ ہو
- [40.00–47.30s] اب میں نے صرف ایک نئیہ یوزر بنائیا وہ اپنا بس مجھے وہی شو ہونا چاہیے جو پڑھانے یوزر آپ لوگ نے بنائے وہ سائیڈ ڈلیٹ کر دیں وہ غلط ہے
- [47.30–54.20s] جو تو نئے بنانے نا جو جس کے ہیں اوریجنل طریقے سے ایمیلز کے ساتھ ان کے رولز کے ساتھ ان کے ہر چیز کے ساتھ
- [54.20–58.98s] لیکن اس کو بھی کہا تھا کہ اس کو کلیر کروائے

#### Machine pass: A07-small-translate
Private evidence: `A07-small-translate.json`.

- [0.00–5.00s] I told you in the last message that you have to give me 00.
- [5.00–8.00s] All the users are showing it.
- [8.00–15.00s] When I was adding the employee, there were a lot of things like sites and projects that I had added earlier.
- [15.00–17.00s] I have to start again from zero.
- [17.00–19.00s] I have to do it in the right way.
- [19.00–23.00s] I have added the employee after completing the entire case.
- [23.00–26.00s] I have come to the user, I have gone to the role, then to the permission.
- [26.00–31.00s] I mean, the entire way of doing it depends on the hierarchy.
- [31.00–32.00s] Then I will have a problem in this.
- [32.00–35.00s] The old things will get confused.
- [35.00–39.00s] Kindly look at it, from the back end, zero it completely.
- [39.00–40.00s] There is nothing old in it.
- [40.00–43.00s] Now I have just made a new user.
- [43.00–44.00s] I should be the same.
- [44.00–47.00s] The old user that you have made, delete it.
- [47.00–48.00s] That is wrong.
- [48.00–49.00s] What you don't want to make is right.
- [49.00–51.00s] The original way of doing it is with emails,
- [51.00–55.00s] with their roles, with everything, okay?
- [55.00–59.00s] So the last time I told you to clear it.

### A08 — Own leave request unavailable

- Original: [WhatsApp Audio 2026-11-10 at 1.53.10 PM.ogg](<C:/Users/Dell/Downloads/seera_today/WhatsApp Audio 2026-11-10 at 1.53.10 PM.ogg>)
- SHA-256: `89854aa9a5f68e5fac61d3869b7fb39d796bea56f699b6a4d51cc8977219a00a`
- Size: 62,356 bytes; container duration: 24.727s.
- Maps to: R10-08; related images: I09, I06.

**Reviewed meaning:** Using the newly created personal login, the client sees limited options despite believing permissions were granted and cannot find how to apply for their own leave.

**Accuracy / unresolved detail:** Actual effective permissions are not proved by a voice note. No request to grant all HR-wide data to ordinary staff should be inferred.

#### Machine pass: A08-medium-transcribe
Private evidence: `A08-medium-transcribe.json`.

- [0.00–5.20s] میں نے اپلوئے اگر لیو رکس کرنی ہے تو میرے پاس تو اپشن نہیں آرہا میں کیسے اپلائی کروں گا
- [5.20–9.72s] یہ میں نے اپنا لوگین کیا جب میں نے اپنا لوگین بنائیا اس میں میں نے اپنا پوری اپرویلس دیں ہے فلال
- [9.72–15.16s] سپر ایڈمین والی ساتھ اپرویلس میرے پاس بھی ہیں لیکن مجھے پھر بھی اکسا چیزیں شو نہیں ہوری
- [15.16–22.36s] لیمیٹر ڈس ایسے ہیں میرے پاس اور اس کے بعد میں نے اپنی لیو رکس ڈالنی ہے تو میں اپنی لیو رکس کسے ڈالوں گا
- [22.36–24.36s] میرے پاس تو اپشن نہیں آرہا

#### Machine pass: A08-small-translate
Private evidence: `A08-small-translate.json`.

- [0.00–10.00s] If I want to request leave, then I don't have the option. How will I apply? I have made my own login, so I have given you all the approvals.
- [10.00–17.00s] I have all the approval of Super Admin, but I don't usually show it. I have limited access.
- [17.00–24.00s] After that, I don't have the option to add my leave request.

### A09 — Automatic party codes and party documents

- Original: [WhatsApp Audio 2026-11-10 at 1.53.19 PM.ogg](<C:/Users/Dell/Downloads/seera_today/WhatsApp Audio 2026-11-10 at 1.53.19 PM.ogg>)
- SHA-256: `a2f30f17179807858b24cbab707938be4a3b209e803fe91489619ea662d67e7c`
- Size: 147,963 bytes; container duration: 57.026s.
- Maps to: R10-09, R10-10, R10-11; related images: I11, I12, I13, I14, I15.

**Reviewed meaning:** Employee codes automatically advance (SP-002 example). Customer and Supplier should likewise allocate/advance their own codes. Both masters also need document attachment options.

**Accuracy / unresolved detail:** Document examples near 47-54 seconds are less clear: CR and national-address papers are supported by a targeted pass; PO/application wording needs confirmation. Do not invent a mandatory legal-document list.

#### Machine pass: A09-medium-no-vad-transcribe
Private evidence: `A09-medium-no-vad-transcribe.json`.

- [36.00–39.00s] جو ہے نمبرنگ ہوگی میں خود سے نہیں دوں گا
- [39.00–42.00s] اور دوسرا جو ہے نا سپلائر میں کوئی ڈاکومیٹ ڈیج کرنے کا
- [42.00–43.00s] اپشن ہے نا
- [43.00–44.00s] کلائنٹ میں ہے
- [44.00–47.00s] ڈاکومیٹس کا تو یہ آپ کو بتایا تھا کہ اس کو ساتھ ڈیج کرنے
- [47.00–49.00s] ہم نے ان کی جو سی آر وغیر ہوتا ہے نیشنلڈرس ویڈ
- [49.00–51.00s] وغیرہ جو بھی اس کو کلائنٹ سے لیٹری
- [51.00–53.00s] جو سپلائر سے لیٹرین جو اس پر ڈاکومیٹس ہے پیو
- [53.00–54.00s] اپنیشن ہے
- [54.00–57.00s] ہم نے اٹچ کرنا چاہتے ہیں تو اس کا کوئی اوپشن نہیں ہے

#### Machine pass: A09-medium-transcribe
Private evidence: `A09-medium-transcribe.json`.

- [0.30–2.30s] سلام علیکم
- [2.30–3.30s] تابش بھائی
- [3.30–4.30s] یہ دیکھیں
- [4.30–5.30s] کوڈ کا آپ کو کہا تھا جیسے بھی
- [5.30–6.30s] لاز اکرین جو میں نے شیئر کیا
- [6.30–8.30s] ایمپلوئیہ میں اس نے میں نے ایک ایمپلوئیہ
- [8.30–9.30s] ایٹ کیا تو اس نے اٹومیٹک
- [9.30–11.30s] کوڈ دے دیا اس کو یعنی
- [11.30–13.30s] SP 02 کر دیا
- [13.30–15.30s] لیکن سپلائر میں اور کسٹمر میں وہ
- [15.30–16.30s] نہیں کر رہا وہاں پر دوبارہ سے
- [16.30–17.30s] دینا پا رہا ہے
- [17.30–18.30s] تو میں نے کہا تھا یہ اٹو جنڈیٹ
- [18.30–20.30s] ہونا چاہیے کوڈ نا
- [20.30–21.30s] کہ پہلے ایک ایک میں نے
- [21.30–51.30s] میں نے ایک سپلائر بھی ایڈ کیا ایک کلائنٹ بھی ایڈ کیا ٹھیک ہے دونوں ایڈ کی ہیں تو اس کے بعد جو ہے وہ جب دوبارہ اگلی ایڈ کرنے لگاؤ تو وہ پھر میرے سے ہی کہہ رہا ہے کہ آپ کو ایڈ کرو میں نے نہیں ایڈ کرنا اس نے خود اسے ٹو کر دینا 3 4 5 اٹو پہ جو ہے وہ اس کی جو ہے نمبرنگ ہوگی میں خود سے نہیں دوں گا اور دوسرا جو ہے نہ سپلائر میں کوئی ڈوکومیٹ ڈیج کرنے کا افشن ہے نہ کلائنٹ میں ڈوکومیٹس کا تو یہ آپ کو بتایا تھا کہ
- [51.30–54.14s] پلائیر سلیٹرن جو اس پر لکومیٹ ہے پی اوہ اپڈیشن ہے
- [54.14–57.24s] ہمیں اٹچ کرنی ہے تو اس کا کوئی اپشن اویلی بل نہیں ہے

#### Machine pass: A09-small-translate
Private evidence: `A09-small-translate.json`.

- [0.30–20.30s] Assalam-o-Aliakum, Tavish bhai, as I have shared the last screen of the code, I have given the automatic code, SP02, but it is not working in the supplier and customer, so I said that the code should be auto-generated.
- [20.30–44.30s] I have added a supplier and a client, both have been added, so after that, when I start adding the next one again, he is telling me to decide the code, I have not decided, he will do it himself, 3, 4, 5, 8, 2, I will not give the numbering on his own, and the second is there is an option to attach a document in the supplier in the client,
- [44.30–57.30s] We have to attach the documents with it, we have to attach the CER, National Address, any client or supplier that has documents, PO, application, we have to attach it, so there is no option available for it.

### A10 — Equipment documents at initial entry

- Original: [WhatsApp Audio 2026-11-10 at 1.53.21 PM.ogg](<C:/Users/Dell/Downloads/seera_today/WhatsApp Audio 2026-11-10 at 1.53.21 PM.ogg>)
- SHA-256: `5ef0bee110a56680f539c2646abd84d28bced08755ce49b4a31d28f2788a0bd4`
- Size: 57,953 bytes; container duration: 22.506s.
- Maps to: R10-12; related images: I16, I17.

**Reviewed meaning:** Equipment currently requires saving then going to View to upload papers. Client wants details and attachments together on the first page, like Employee, and an obvious indication of what is complete.

**Accuracy / unresolved detail:** Medium Urdu passes omit an opening segment retained by the independent English pass. Read all retained passes together with the Equipment screenshots; timestamps across passes are approximate, not a word-perfect alignment.

#### Machine pass: A10-medium-no-vad-transcribe
Private evidence: `A10-medium-no-vad-transcribe.json`.

- [0.00–3.00s] یہ ایک پیچھ پر ساڑا کچھ ہو جائی اس کا بھی اسی طرح سے ہونا چاہیئے
- [3.00–6.00s] یہ تو ایڈ کریں گے تو ایڈ ہو جائے گا اس پر نہ ڈیٹیل دے کوئی تو پھر
- [6.00–9.00s] کیسے چیک ہوگا کس کی ڈیٹیل ہے کس کی نہیں ہے
- [9.00–12.00s] تو یہ پہلے پیچھ پر ساڑ چیزیں ساتھ ہونی چاہیئے نا

#### Machine pass: A10-medium-opening-transcribe
Private evidence: `A10-medium-opening-transcribe.json`.

- [0.00–10.70s] لیکن جب آپ کی پنٹ کو سیو کر کے ویو میں جاتے ہیں تو وہاں جا کے آپ اپلوڈ کریں گے لیکن یہ نہیں ہے یہ جیسے آپ کو بتایا تھا میں ایک پیچھ پر سارا کچھ

#### Machine pass: A10-medium-opening-translate
Private evidence: `A10-medium-opening-translate.json`.

- [0.00–7.00s] This option is available for the equipment, but when you save the equipment and go to the view, you will go there and upload it, but this is not the case.
- [7.00–13.00s] As I told you in the employee, everything should be done on the same page, this should also be done in the same way.
- [13.00–20.00s] If you add it, it will be added, if you do not see the details, then how will it be checked, who has the details and who does not have it.
- [20.00–23.00s] So all these things should be together on the first page.

#### Machine pass: A10-medium-transcribe
Private evidence: `A10-medium-transcribe.json`.

- [0.00–3.00s] یہ ایک پیچھ پر ساڑا کچھ ہو جائی اس کا بھی اسی طرح سے ہونا چاہیئے
- [3.00–6.00s] یہ تو ایڈ کریں گے تو ایڈ ہو جائے گا اس پر نہ ڈیٹیل دے کوئی تو پھر
- [6.00–9.00s] کیسے چیک ہوگا کس کی ڈیٹیل ہے کس کی نہیں ہے
- [9.00–12.00s] تو یہ پہلے پیچھ پر ساڑ چیزیں ساتھ ہونی چاہیئے نا

#### Machine pass: A10-small-translate
Private evidence: `A10-small-translate.json`.

- [0.00–6.00s] This is an option available for equipment, but when you go to the view after saving the equipment, then you will upload it there.
- [6.00–7.00s] But this is not it.
- [7.00–11.00s] As you can see in the employee, everything should be on the same page.
- [11.00–13.00s] This should also be the same way.
- [13.00–15.00s] If you add this, then it will be added.
- [15.00–18.00s] If there is no detail in it, then how will it be checked?
- [18.00–20.00s] Whose detail is there, whose is not.
- [20.00–23.00s] So this is the first page, everything should be together.

## Images — all originals visually reviewed

### I01 — WhatsApp Image 2026-11-10 at 1.53.02 PM.jpeg

- [Open original](<C:/Users/Dell/Downloads/seera_today/WhatsApp Image 2026-11-10 at 1.53.02 PM.jpeg>)
- SHA-256: `b39f08dd858e7dafbbc2947bf773991e65f0b7b84bc58178447faedd7c7f18ff`; size 9,782 bytes; dimensions 641×67.
- Related requirement(s): R10-01.
- **Observed:** IBAN error message explicitly asks for valid syntax/check digits. No module header is visible.

### I02 — WhatsApp Image 2026-11-10 at 1.53.03 PM.jpeg

- [Open original](<C:/Users/Dell/Downloads/seera_today/WhatsApp Image 2026-11-10 at 1.53.03 PM.jpeg>)
- SHA-256: `765383daef3c31a7ef81cff436ef297ec11b9549d745d5270d5df312a94c52f2`; size 4,051 bytes; dimensions 559×59.
- Related requirement(s): R10-01.
- **Observed:** IBAN input crop. Full sensitive value withheld here; not a bank verification document.

### I03 — WhatsApp Image 2026-11-10 at 1.53.04 PM.jpeg

- [Open original](<C:/Users/Dell/Downloads/seera_today/WhatsApp Image 2026-11-10 at 1.53.04 PM.jpeg>)
- SHA-256: `a0542cc060694035af10f2bb7d207964fe46b17735e4fef090db3c6c137a547d`; size 39,588 bytes; dimensions 1600×216.
- Related requirement(s): R10-02.
- **Observed:** 2026 annual entitlement 21, used/pending zero, remaining 21. Approved Urgent / Personal Leave is 21-29 August 2025, nine days.

### I04 — WhatsApp Image 2026-11-10 at 1.53.05 PM (1).jpeg

- [Open original](<C:/Users/Dell/Downloads/seera_today/WhatsApp Image 2026-11-10 at 1.53.05 PM (1).jpeg>)
- SHA-256: `9163347dd075cbc3698aa6184c9648669ebf86371ca025fa66a2c1afddd7c86f`; size 84,371 bytes; dimensions 1600×497.
- Related requirement(s): R10-04.
- **Observed:** Add User form: linked Employee selected, profile card has a plus and Upload Profile Photo button; role and employee autofill context.

### I05 — WhatsApp Image 2026-11-10 at 1.53.05 PM.jpeg

- [Open original](<C:/Users/Dell/Downloads/seera_today/WhatsApp Image 2026-11-10 at 1.53.05 PM.jpeg>)
- SHA-256: `b78c2d2aee7292804e259cf9e9295461aaa8528254569dd4cd2f4ebb67c69395`; size 25,347 bytes; dimensions 1004×146.
- Related requirement(s): R10-02, R10-03.
- **Observed:** Employee header uses initials avatar; displays 2026 remaining 21/21 and other identity/employment context.

### I06 — WhatsApp Image 2026-11-10 at 1.53.06 PM.jpeg

- [Open original](<C:/Users/Dell/Downloads/seera_today/WhatsApp Image 2026-11-10 at 1.53.06 PM.jpeg>)
- SHA-256: `129c52c69b2cce4b14388c78f23e9703247a1fb96d8a1cd214369e42aee56324`; size 140,505 bytes; dimensions 1600×680.
- Related requirement(s): R10-05, R10-08.
- **Observed:** Role-based Permission Matrix, Accounts Manager, Accounts group filter. Checked visible rows do not prove hidden HR grants.

### I07 — WhatsApp Image 2026-11-10 at 1.53.07 PM.jpeg

- [Open original](<C:/Users/Dell/Downloads/seera_today/WhatsApp Image 2026-11-10 at 1.53.07 PM.jpeg>)
- SHA-256: `3f92a5b44917e76be7a0c348f27468582ba178f37685f92978c6373f6a139ad8`; size 117,161 bytes; dimensions 1600×495.
- Related requirement(s): R10-06, R10-07.
- **Observed:** Users register contains multiple old accounts; only first page is shown. Names/email/phones intentionally not copied.

### I08 — WhatsApp Image 2026-11-10 at 1.53.08 PM.jpeg

- [Open original](<C:/Users/Dell/Downloads/seera_today/WhatsApp Image 2026-11-10 at 1.53.08 PM.jpeg>)
- SHA-256: `c362f0058477c1eb08eb4d4a9e6bb34474c00435aee6fcf14cb2675a07ce29bb`; size 13,379 bytes; dimensions 1600×47.
- Related requirement(s): R10-06.
- **Observed:** A single newly added user row with SP-prefixed employee code. Exact protected user ID is not visible.

### I09 — WhatsApp Image 2026-11-10 at 1.53.09 PM (1).jpeg

- [Open original](<C:/Users/Dell/Downloads/seera_today/WhatsApp Image 2026-11-10 at 1.53.09 PM (1).jpeg>)
- SHA-256: `9a926b470c05cea872ccb838412dfc75740b9a81a43003be41bfdd97a29fa1c2`; size 7,199 bytes; dimensions 180×191.
- Related requirement(s): R10-07, R10-08.
- **Observed:** Sidebar crop: Overtime, Salary Structures, Payroll and End of Service. Menu visibility alone does not prove leave access.

### I10 — WhatsApp Image 2026-11-10 at 1.53.09 PM.jpeg

- [Open original](<C:/Users/Dell/Downloads/seera_today/WhatsApp Image 2026-11-10 at 1.53.09 PM.jpeg>)
- SHA-256: `21445e7f450b02246767d80bf7745128d3c0d0a305f5461e2dc233fbb9eeb84b`; size 64,595 bytes; dimensions 1600×474.
- Related requirement(s): R10-07.
- **Observed:** Cost Centers register contains one project-type center. This does not prove its current foreign-key validity or authorize deleting it.

### I11 — WhatsApp Image 2026-11-10 at 1.53.10 PM.jpeg

- [Open original](<C:/Users/Dell/Downloads/seera_today/WhatsApp Image 2026-11-10 at 1.53.10 PM.jpeg>)
- SHA-256: `82653ddf3b3412bb1e84311eb1e9d81ea4c9add9766e9494f121d08c591ef285`; size 124,050 bytes; dimensions 1600×770.
- Related requirement(s): R10-09, R10-10.
- **Observed:** Filled Add Supplier, manually visible SUP-001, payment/bank fields and no document section in this screenshot.

### I12 — WhatsApp Image 2026-11-10 at 1.53.11 PM.jpeg

- [Open original](<C:/Users/Dell/Downloads/seera_today/WhatsApp Image 2026-11-10 at 1.53.11 PM.jpeg>)
- SHA-256: `1d978204cfc9e63e8b1ebae7621a58176bbcb9aadeb9f4305d556672d52168f7`; size 124,156 bytes; dimensions 1600×871.
- Related requirement(s): R10-09, R10-11.
- **Observed:** Add Customer profile/address/contact fields; code placeholder Auto: CUS-001. No document section visible.

### I13 — WhatsApp Image 2026-11-10 at 1.53.12 PM.jpeg

- [Open original](<C:/Users/Dell/Downloads/seera_today/WhatsApp Image 2026-11-10 at 1.53.12 PM.jpeg>)
- SHA-256: `4bf250cf49a90606c1523748f65d5dece6a881238ea399a0c01a30e32384cec0`; size 67,688 bytes; dimensions 1600×439.
- Related requirement(s): R10-09, R10-11.
- **Observed:** Customer register contains one record CUS-001; two Add Customer controls. It does not show the next saved number.

### I14 — WhatsApp Image 2026-11-10 at 1.53.14 PM.jpeg

- [Open original](<C:/Users/Dell/Downloads/seera_today/WhatsApp Image 2026-11-10 at 1.53.14 PM.jpeg>)
- SHA-256: `20d43ffe5fbadd7542f41ec9343735d7ac7e33802557d82d80b492363b6f379d`; size 105,939 bytes; dimensions 1600×704.
- Related requirement(s): R10-09.
- **Observed:** Focused Customer Code input with browser-like suggestions from prior entries. Suggestions are not proof these values are database records.

### I15 — WhatsApp Image 2026-11-10 at 1.53.17 PM.jpeg

- [Open original](<C:/Users/Dell/Downloads/seera_today/WhatsApp Image 2026-11-10 at 1.53.17 PM.jpeg>)
- SHA-256: `bc7e7043d282d785dded0b35fb0d7293390f9e9bcfa3fe4a87b95123b10b5824`; size 142,250 bytes; dimensions 1600×880.
- Related requirement(s): R10-03, R10-09.
- **Observed:** Add Employee displays next SP-002 preview, optional manual-code checkbox and Employee tabs/fields.

### I16 — WhatsApp Image 2026-11-10 at 1.53.19 PM.jpeg

- [Open original](<C:/Users/Dell/Downloads/seera_today/WhatsApp Image 2026-11-10 at 1.53.19 PM.jpeg>)
- SHA-256: `37ec04ae710b3da9e6d16884bccf310dd1c40428cabf9ef8a30430e631329ddd`; size 142,654 bytes; dimensions 1600×878.
- Related requirement(s): R10-12.
- **Observed:** Add Equipment / Vehicle form with profile, vehicle fields, initial meter and tracking-readiness fields. Does not show initial document upload.

### I17 — WhatsApp Image 2026-11-10 at 1.53.20 PM.jpeg

- [Open original](<C:/Users/Dell/Downloads/seera_today/WhatsApp Image 2026-11-10 at 1.53.20 PM.jpeg>)
- SHA-256: `824d33904556e636b28da0e16bdbdc135277292a0d5c9c5871da4f7f7af36ec7`; size 46,094 bytes; dimensions 1600×231.
- Related requirement(s): R10-12.
- **Observed:** Saved Equipment Documents panel already has two rows with private filename links, Download actions and a new upload row.

### I18 — WhatsApp Image 2026-11-10 at 1.53.21 PM.jpeg

- [Open original](<C:/Users/Dell/Downloads/seera_today/WhatsApp Image 2026-11-10 at 1.53.21 PM.jpeg>)
- SHA-256: `89718d82941fdaa606cec0468c222268e80e950090868c9b933f1b06ee58f231`; size 17,595 bytes; dimensions 1600×560.
- Related requirement(s): R10-13.
- **Observed:** HTTP 500 Server Error, URL /admin/equipment. Screenshot cannot identify request method or exception.

### I19 — WhatsApp Image 2026-11-10 at 3.15.11 PM.jpeg

- [Open original](<C:/Users/Dell/Downloads/seera_today/WhatsApp Image 2026-11-10 at 3.15.11 PM.jpeg>)
- SHA-256: `abb8b0e37101858ad6b743421399ef3db9cec0317abdd01ed58b70abe50975c7`; size 24,789 bytes; dimensions 1600×831.
- Related requirement(s): R10-13.
- **Observed:** Later HTTP 500 Server Error at /admin/equipment. Same visible symptom as I18, but distinct file/hash.

## Log source

`D:/laragon2/www/seera/laravel.log` — supplied production copy, 440,684 bytes, SHA-256 `d0e5c0c44137e547fbacf6a1774db560c34ac05b530f0595363e17cc8715eeb4`.

20 top-level errors reviewed; three recent Equipment insert failures and 17 older setup/deployment entries. See the consolidated review for the grouped assessment. Raw SQL is deliberately kept only in the supplied ignored log/private extraction.

## Source inspection references

| Area | Read-only source reference |
| --- | --- |
| IBAN | `app/Support/Iban.php`; `app/Http/Controllers/Admin/Hr/EmployeeController.php:371` |
| Leave balance | `app/Models/Employee.php:127` |
| Employee photo/header | `app/Models/Employee.php:13`; `resources/views/admin/hr/employees/show.blade.php:26`; Employee `_form.blade.php` |
| User photo button | `resources/views/admin/users/_form.blade.php:3` and `:14` |
| Role permissions | `app/Models/User.php:140`; `app/Http/Controllers/Admin/PermissionMatrixController.php`; `app/Support/PermissionGroups.php` |
| Menu/access | `app/Support/SidebarMenu.php:199`; `app/Http/Middleware/EnsureUserHasPermission.php:39` and `:44` |
| Party numbering | `app/Http/Controllers/Admin/Master/CustomerController.php:223`; `SupplierController.php:182`; `app/Support/CodeGenerator.php` |
| Customer notes | `app/Models/CustomerNote.php`; `resources/views/admin/master/customers/_workspace-panel.blade.php:79` |
| Equipment creation/validation | `app/Http/Controllers/Admin/Equipment/EquipmentAssetController.php:79` and `:188`; `resources/views/admin/equipment/_form.blade.php:32` |
| Equipment schema | `database/migrations/2026_10_12_000001_create_equipment_module.php:41` |
| Equipment documents | `resources/views/admin/equipment/show.blade.php:283`; `app/Http/Controllers/Admin/Equipment/EquipmentOperationsController.php:169` |
| Earlier reset scope | `app/Console/Commands/GoLiveReset.php`; `docs/go-live-reset-2026-11-04.md` |

Inspection confirms code paths, not fresh production behavior. No functional/browser test, live DB inspection or deployment was run.
