🟢 RISHTA SYSTEM – FINAL USER SIDE FLOW
________________________________________
1️⃣ Landing Page (Home Page)
Structure & Sections:
•	Header: Logo + Navigation → Home, About, How it Works, Contact, Login/Register 
•	Hero Section: Banner/slider/video + tagline “Find Your Perfect Match” + CTA buttons (Register Now / Search Profiles) 
•	Additional Sections: 
1.	How it Works → step-by-step with icons 
2.	Featured Profiles → free visitor preview (basic info: name, age, city, photo) 
3.	Success Stories → testimonials/videos 
4.	Membership / Benefits → Free vs Premium plan comparison 
5.	FAQs / Help → accordion style 
•	Footer: Contact info, social links, privacy policy, Terms & Conditions, newsletter signup 
Logic:
•	Free visitor → can preview 3–5 profiles 
•	CTA → encourages registration to unlock more features 
________________________________________
2️⃣ Registration (No OTP)
Full Name
Letters only (A–Z, a–z), no numbers or special characters
Minimum 2 words (First + Last name)
Maximum 50 characters
Auto-capitalize first letter of each word
Validation message example: “Please enter your full name using letters only.”
Email
Must follow standard email format (e.g., name@example.com)
Unique across system (duplicate emails not allowed)
Optional: Block disposable email domains to ensure authenticity
Validation message: “Please enter a valid email address.”
Phone Number
Optional field
Digits only (0–9), exactly 11 digits
No letters or symbols allowed
Used for contact or optional notifications
Validation message: “Phone number must contain exactly 11 digits.”
Gender
Dropdown selection: Male / Female / Prefer not to say
Stored for matching algorithm and profile filtering
Validation: Cannot be empty
Tooltip: “Select your gender for personalized match suggestions.”
Date of Birth
Date picker input
Minimum age: 18 years
Age automatically calculated and stored for profile & match algorithm
Validation message: “You must be at least 18 years old to register.”
Password
Minimum 8 characters
Must include uppercase & lowercase letters
Optional: Include numbers or special characters for stronger security
Password strength indicator (weak/medium/strong)
Validation message: “Password must be at least 8 characters with uppercase and lowercase letters.”
Confirm Password
Must match the password field exactly
Validation message: “Passwords do not match. Please try again.”
Terms & Conditions
Mandatory checkbox
User cannot register without accepting terms
Optional tooltip: “By registering, you agree to our Terms & Conditions and Privacy Policy.”
Additional UX / Security Enhancements
Real-time validation with inline error messages for each field
Prevent copy-paste in password fields to encourage strong password creation
CAPTCHA / Bot prevention for spam registrations
Auto-focus on the first empty field after validation error
Flow:
•	Account auto-verified 
•	Redirect to Login Page 
Enhancements:
•	CAPTCHA / Bot protection 
•	Prevent duplicate registration 
________________________________________
3️⃣ Login
Fields:
•	Email / Phone + Password 
•	Forgot Password → reset via email 
•	Optional: Login via Google/Facebook 
Security Logic:
•	5 failed attempts → lock account 15 min 
•	Passwords encrypted in DB 
Flow:
•	Valid → redirect to Dashboard 
•	Invalid → error message 
________________________________________
4️⃣ Dashboard
Sidebar Options:
•	Dashboard, My Profile, Partner Preferences, Matches, Search Profiles, Interests, Messages/Chat, Saved/Shortlisted Profiles, Profile Visitors, Subscription, Notifications, Settings, Help/Support, Logout 
Widgets:
•	Profile Completion % 
•	Total Matches 
•	Interests Sent / Received 
•	Messages (Unread count) 
•	Profile Views 
•	Subscription Status 
•	Recent Activity 
•	Suggested Matches Slider 
•	Daily Login Streak 
Free User Logic:
•	Limit 5–7 profile views/day 
•	Sensitive info hidden 
•	Chat disabled until verification or mutual interest 
________________________________________
5️⃣ Search & Matching Logic (Improved)
Filters: Age, City, Education, Religion, Profession, Income
Sort: Recently Active, Verified, Premium Member
Improvements:
•	Shortlist Feature: Save profiles before sending interest 
•	Compatibility Score: % match based on education, location, preferences 
•	Avoid No Results: Suggest slightly broader matches if exact filters fail 
Integration:
•	Shortlisted profiles visible in separate section 
•	Compatibility score guides interest requests 
________________________________________
6️⃣ Profile Privacy & Photo Security
Logic & Features:
•	Hidden info: email, phone, address for free users 
•	Photo Watermarking: auto watermark logo 
•	Photo Privacy Settings: Users choose visibility → All / Matched / Premium 
•	Blur Effect: Free users see blurred photos 
•	Integration: 
o	Encourages upgrade 
o	Protects user photos 
________________________________________
7️⃣ Interest Requests & Chat
Logic:
•	Send Interest → Pending 
•	Receiver Accepts → Accepted → unlock chat & hidden info 
•	Receiver Rejects → Rejected → info remains hidden 
Chat Enhancements:
•	Access only after mutual interest or verification 
•	Message Expiry: Archive after 30 days of inactivity 
•	Report in Chat: Each message has “Report” → flagged for admin 
Integration:
•	Chat unlock logic synced with verification & mutual interest 
•	Reported messages sent to Admin Dashboard 
________________________________________
8️⃣ Subscription / Premium Upgrade
Logic:
•	Free → limited views, chat locked, hidden info 
•	Premium → unlimited views, full info, chat unlocked 
•	Admin-added plans → visible to users 
Integration:
•	Upgrade triggers notifications & dashboard widget update 
________________________________________
9️⃣ Notifications & Activity
•	Matches suggested 
•	New interest received 
•	New messages 
•	Profile visitors 
•	Subscription reminders 
________________________________________
10️⃣ Logout & Session
•	Clears session → redirects to login 
•	Optional: last login/logout notification


🟢 ADMIN SIDE – IN-DEPTH EXPLANATION
________________________________________
1️⃣ Admin Login
Purpose: Secure entry for admin to manage the entire system.
Fields & Flow:
•	Email / Username 
•	Password 
•	Forgot Password → email reset link 
Logic:
•	No OTP → simplifies login for admin 
•	Password encryption → security 
•	Failed login attempts limit → 5 wrong attempts → lock for 15 minutes 
•	Optional: log last login device & IP 
Integration:
•	Admin dashboard only accessible after successful login 
•	Controls user-side data (profiles, chat, subscriptions, reports) 
________________________________________
2️⃣ Dashboard & Widgets
Purpose: Quick overview of system health & user engagement.
Widgets & Logic:
1.	Total Users: Free vs Premium → track growth 
2.	Daily Active Users: Count of logged-in users today 
3.	New Registrations: Users registered today/week/month 
4.	Pending Verification Requests: Profiles waiting for admin approval 
5.	Total Matches / Interests: Number of interest requests sent & accepted 
6.	Profile Completion Stats: Average % completion → identify inactive users 
7.	Revenue / Subscription Stats: Income from premium subscriptions 
8.	Alerts: Fake profiles, abusive messages, bulk reports 
Integration:
•	Widgets update real-time 
•	Admin sees trends → can take corrective actions 
________________________________________
3️⃣ User Management
Purpose: Manage, monitor, and maintain all user accounts.
Features & Logic:
•	Search & Filter: By name, email, subscription type, verification status, last login 
•	Edit Profile: Admin can edit user details (bio, photos, preferences) 
•	Suspend / Delete Accounts: 
o	Suspend → temporary restriction (user cannot log in) 
o	Delete → permanent removal 
o	Immediate effect on User Dashboard 
•	Reset Password: Admin-triggered password reset → email notification 
Improvements:
•	Bulk Actions: Select multiple accounts → delete or suspend in one action 
•	Fake Profile Detection: System alerts admin if multiple accounts created from same IP 
Integration:
•	Suspended/deleted users cannot access their dashboard 
•	Bulk actions save admin time, especially for spam/fake accounts 
________________________________________
4️⃣ Profile Verification
Purpose: Ensure authenticity & security of profiles.
Logic:
•	Users upload required verification documents (email, phone, optional ID photo) 
•	Admin reviews pending requests: 
o	Approve: Chat unlocks + hidden info visible to approved users 
o	Reject: User notified → can re-upload 
•	Verification is required for: Chat access, sensitive info visibility, and premium benefits 
Integration:
•	Directly affects User Side chat and info visibility 
•	Promotes trust among users 
________________________________________
5️⃣ Matches & Interests Monitoring
Purpose: Maintain quality of matches & prevent misuse.
Logic:
•	Track all interest requests sent & received 
•	Monitor acceptance/rejection rate 
•	Detect spam or excessive interest requests 
•	Admin can block/report users sending abusive content 
Integration:
•	Helps maintain safe & active user environment 
•	Admin interventions immediately affect user interactions 
________________________________________
6️⃣ Messages / Chat Oversight
Purpose: Ensure safe messaging environment.
Logic:
•	Access flagged messages → messages with reports or abusive content 
•	Admin can delete, warn, or suspend sender 
•	Bad Words Filter: 
o	Profile bio & chat automatically scanned 
o	Abusive words replaced with **** or blocked 
o	Admin receives reports for repeated offenses 
Integration:
•	Chat on user side → only unlocked & verified users can communicate 
•	Abuse prevention enhances user safety 
________________________________________
7️⃣ Subscription / Payments
Purpose: Manage monetization & user access levels.
Features & Logic:
•	Add / Edit Plans: Free, Premium, Custom → define price, duration, and features 
•	Upgrade/downgrade users manually → reflected immediately on User Dashboard 
•	Payment history & revenue tracking 
•	Plan visibility → users see updated options on their dashboard 
Integration:
•	Free users → limited profile views, chat locked, hidden info 
•	Premium users → unlimited access, chat unlocked, all info visible 
________________________________________
8️⃣ Reports & Analytics
Purpose: Data-driven decisions & FYP documentation.
Reports Available:
•	User list (active, free/premium, verified/unverified) 
•	Matches & interest statistics 
•	Messages / flagged content 
•	Revenue reports 
•	Profile completion rates 
Downloadable Formats: PDF, CSV, XLSX → usable for presentations or analysis
Integration:
•	Reflects real-time user activity 
•	Helps admin monitor trends & user engagement 
________________________________________
9️⃣ General Settings
Purpose: Configure site-wide settings affecting user experience.
Features:
•	Site info → logo, name, contact info, social links 
•	Free user limits → profile views/day, basic info displayed 
•	Verification rules → required fields, chat unlock rules 
•	Security defaults → auto logout, password/session rules 
•	Email templates → for registration, verification, notifications 
Integration:
•	Settings changes directly impact User Side functionality 
________________________________________
10️⃣ Notifications / Announcements
Purpose: Communicate system-wide messages to users.
Logic:
•	Broadcast notifications → all users or filtered group (free/premium, location, age) 
•	Examples: “Upgrade to Premium for unlimited profiles”, “System maintenance notice” 
•	Appears as dashboard alert or popup on User Side 
________________________________________
11️⃣ Help / Support
Purpose: Admin manages user support tickets & FAQs.
Logic:
•	Track incoming user queries / complaints 
•	Respond and resolve tickets 
•	Update FAQ → visible on User Side Help section 
________________________________________
12️⃣ Logout & Session Management
Logic:
•	Clear session → redirect to login page 
•	Optional: log last login/logout activity 
•	Ensures admin security 