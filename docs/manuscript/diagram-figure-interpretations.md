# FIGURE INTERPRETATIONS

Written in the same voice and past tense as your existing figure paragraphs. Paste each one under its figure.

---

## Figure 2. System Architecture

Figure 2 showed the architecture of the system. All users entered through a single login interface, which directed them to the features allowed by their assigned role. The Admin reached user and access management and system administration and audit. The SK Federation reached youth profiling, program and activity management, budget proposal and monitoring, information and communication, and the dashboard with reports and documents. The SK Barangay Official reached the same features together with inventory and equipment request, since the barangay council held the property and prepared the budget proposal. The KK Member reached inventory and equipment request and information and communication only. Every feature wrote to and read from a single centralized database, and the database returned to the login interface, showing that access was controlled at one point rather than at each feature.

---

## Figure 3. Conceptual Framework

Figure 3 presented the conceptual framework of the system using the input-process-output model. The input consisted of the records the users supplied, namely login credentials, registration details, youth profiles, program and activity details, evaluation questions, QR code scans, inventory items and stock quantities, equipment requests, the budget allocation entered by the federation, the budget proposals and supporting documents prepared by the barangay officials, the expenses charged against approved budgets, announcements and documents, and the accounts and roles maintained by the Admin. The process covered what the system did with these records, from verifying the user and the role, through profiling, scheduling, QR code generation, attendance recording, evaluation, e-certificate issuance, inventory and equipment request handling, budget proposal and approval, and budget monitoring, up to content posting, feedback handling, report generation, and the dashboard. The output listed what the users received in return, including verified accounts, scheduled activities with printable QR codes, recorded attendance, completed evaluations with results the members could review, issued e-certificates, updated inventory, processed equipment requests, approved budget proposals with the remaining allocation, program budgets showing the allocated, utilised, and balance amounts with a complete or incomplete status, published announcements, an organized document repository, and printable reports and dashboards.

---

## Figure 4. Use Case Diagram

Figure 4 showed the use case diagram of the system. It described the four users and what each one was allowed to do. The Admin managed user accounts and roles, barangay records, and system settings and logs. The SK Barangay Official verified the registration of KK members from the same barangay, maintained their profiles, managed programs and activities, generated QR codes, set up evaluations, maintained the barangay inventory and processed equipment requests, and prepared the budget proposal together with its supporting documents. The SK Federation monitored youth profiles, programs and activities, and attendance and evaluation across the barangays, maintained the federation repository, and approved the budget proposals submitted to it. Both SK users posted announcements, uploaded documents, replied to feedback, and generated reports. The KK Member registered an account, viewed announcements and the calendar, maintained a personal profile, scanned the activity QR code, viewed the attendance history and the results of the evaluations submitted, requested equipment, and sent comments and suggestions. The diagram also showed that scanning the QR code included recording the attendance, submitting the evaluation, and receiving the e-certificate, that preparing a budget proposal included uploading its documents, that managing the inventory included viewing the stock status, and that submitting comments could be extended by sending them anonymously.

---

## Figure 5. Activity Diagram – Administrator

Figure 5 showed the activity diagram of the Administrator. The process began with the login, and the system checked whether it succeeded. A failed login returned the Administrator to the login screen, while a successful one identified the user level and permission and opened the homepage. From the homepage the Administrator could manage user accounts, assign user roles, and designate the SK chairman of each barangay; manage the SK barangays and the system settings; or view the system logs. The three paths met at the end of the process, showing that the role of the Administrator was limited to maintaining the system and its users rather than encoding barangay records.

---

## Figure 6. Activity Diagram – SK Federation

Figure 6 showed the activity diagram of the SK Federation. After a successful login the system identified the user level and permission and opened the homepage. From there the federation could view the youth profiles, monitor the programs and activities, monitor the attendance, view the evaluation results, and view the accomplishment reports of all barangays; manage the federation repository by posting announcements, uploading documents, and replying to feedback; or work on the budget. In the budget path the federation entered the budget allocation for the period and reviewed the budget proposal submitted by a barangay. If the proposal was approved, the system deducted the approved amount from the allocation and the federation monitored the utilisation and the remaining balance before generating the reports. If it was not approved, the proposal returned to the review step so it could be acted upon again after revision. All three paths converged at the end of the process.

---

## Figure 7. Activity Diagram – SK Barangay Official

Figure 7 showed the activity diagram of the SK Barangay Official. Once the login succeeded and the homepage opened, the official could follow four paths. The first covered the members, where the official verified the registrations received from the barangay, activated the accounts that were approved, maintained the KK profiles, created the programs and activities, generated the QR code, and set up the evaluations. The second covered the inventory, where the official maintained the items and their stock status, processed the equipment requests of the KK members, and, upon approval, released the item and deducted the quantity before monitoring the attendance and the certificates. The third covered the budget, where the official prepared the proposal, uploaded the supporting documents to the repository, and submitted the proposal to the federation; an approved proposal allowed the official to record the expenses of each program and activity and to mark the budget as complete or incomplete with a note on the remaining amount, while a proposal that was not approved returned for revision and resubmission. The fourth covered the posting of announcements and documents, the replies to feedback, the accomplishment reports, and the generation of reports. The four paths met at the end of the process.

---

## Figure 8. Activity Diagram – KK Member

Figure 8 showed the activity diagram of the KK Member. The process began by checking whether the member already had an account. A member without one registered and waited for the SK Barangay Official to verify the account before logging in. After a successful login the system identified the user level and permission and opened the homepage, from which the member could follow three paths. The first covered viewing the announcements and the calendar, the accomplishment reports, the personal profile, the attendance history, and the results of the evaluations the member had submitted. The second covered scanning the QR code of an activity; if the code was valid and the activity was open, the system recorded the attendance, opened the evaluation form, and issued the e-certificate once the evaluation was submitted, while an invalid code returned the member to the scanning step. The third covered requesting equipment from the barangay inventory and submitting comments and suggestions, which the member could choose to send anonymously so that the identity was hidden. The three paths met at the end of the process.

---

## Figure 9. Sequence Diagram – Administrator

Figure 9 showed the sequence of exchanges between the Administrator, the system, and the database. The Administrator submitted the login credentials, which the system verified against the database before displaying the dashboard. The succeeding requests followed the same pattern, where the Administrator issued a request, the system wrote to or read from the database, and the result returned to the screen. These covered the creation and updating of user accounts, the assignment of roles and barangay assignments, the maintenance of the barangay records and the system settings, and the querying of the audit logs, before the session ended at logout.

---

## Figure 10. Sequence Diagram – SK Federation

Figure 10 showed the sequence of exchanges for the SK Federation. After the credentials were verified and the dashboard was displayed, the federation requested the youth profiles, the programs and activities, and the attendance and evaluation summaries, which the system fetched from the database and returned as consolidated records. In the budget exchange the federation first saved the budget allocation for the period, then opened the pending proposals together with their attached documents. An approved proposal caused the system to update its status and deduct the approved amount from the allocation, while a returned proposal caused the system to save the remarks and set it back for revision. The federation then queried the allocated, utilised, and balance amounts before managing the repository, replying to feedback, and generating the reports.

---

## Figure 11. Sequence Diagram – SK Barangay Official

Figure 11 showed the sequence of exchanges for the SK Barangay Official. After the login was verified, the official fetched the pending registrations of the barangay and updated their status, verified-by, and date-verified fields, then maintained the KK records. The system saved the program, the activity, and the generated QR token together, and returned the QR code for printing, after which the evaluation form was linked to the activity. For the inventory, the system returned the stock status and the pending equipment requests; an approved request caused the system to deduct the issued quantity, update the stock status, and save the transaction, while a declined one saved the remarks. In the budget exchange the system saved the proposal, linked the uploaded document to it, and set its status to pending. An approved proposal allowed the official to record the expenses, which the system saved while recomputing the utilised and balance amounts, and to save the completion status with the note on the remaining amount. A returned proposal was revised and set back to pending. The official then posted content, replied to feedback, and generated reports before logging out.

---

## Figure 12. Sequence Diagram – KK Member

Figure 12 showed the sequence of exchanges for the KK Member. A member without an account registered first, and the system saved the registration with a pending status until the SK Barangay Official verified it. After the credentials and the account status were verified, the homepage was displayed and the member requested the announcements, the calendar, and the personal profile. When the member scanned the QR code of an activity, the system validated the token and the status of the activity; if both were valid it saved the attendance, opened the evaluation form, saved the responses, and generated the e-certificate, while an invalid code was rejected. The member then requested the results of the evaluations submitted and the attendance history. For equipment, the system returned the available items, saved the request with a pending status, and later notified the member of the approval or the decline. Comments and suggestions were saved either with or without the identity of the member depending on the choice made.

---

## Figure 13. Entity Relationship Diagram

Figure 13 showed the database design of the system. The users table held the accounts and was linked to the roles and the barangays, and to the user who verified each registration. The youth profiles, the programs, the activities, the attendance, the evaluation forms with their questions, responses, and answers, and the certificates were linked in sequence, so that an activity carried its own QR token, its own attendance records, and its own evaluation form, and a certificate was traced back to the response, the member, and the activity that produced it. The documents, the announcements, the feedback, the accomplishments, and the reports were each linked to the user or the program they belonged to. The inventory items were held per barangay and were linked to the inventory transactions and to the equipment requests filed by the members. The budget was represented by four tables, where the allocation entered by the federation carried the remaining amount, the proposal carried its status, its proposed and approved amounts, the remarks, and the link to the document uploaded as its attachment, the program budget carried the allocated and utilised amounts together with the completion status and the note on the balance, and the expenses were recorded against the program budget and the activity they were charged to.
