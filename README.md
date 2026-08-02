# ProjectPulse

**ProjectPulse** (Portal for Unified Learner Monitoring, School Records, and Engagement) is a web-based **QR Code-enabled Integrated Learner Information and School Management System** designed to streamline school operations by centralizing learner information into a single, secure, and role-based platform.

The system automates attendance monitoring, academic record management, health profiling, guidance services, and parent engagement while providing real-time reports that support evidence-based decision-making.

---

## Features

### Administrator Portal
- Dashboard with real-time statistics
- QR Code attendance monitoring
- Learner Management
- Teacher Management
- User Account Management
- Section Management
- Subject Management
- School Year Management
- Attendance Reports
- Academic Reports
- Health Reports
- Guidance Reports
- System Activity Logs
- Backup and Restore Database

---

### Teacher Portal
- Dashboard
- Class Adviser Management
- Import Grades
- Export Grade Templates
- Encode Grades
- View Learner Profiles
- View Attendance Records
- Create Parent Accounts
- Link Parents to Learners
- View Academic History
- Generate Class Reports

---

### Health Coordinator Portal
- Dashboard
- Learner Health Profiles
- Height and Weight Recording
- Automatic BMI Computation
- Nutritional Status Monitoring
- Health History
- Deworming Records
- School-Based Feeding Program (SBFP) Monitoring
- Medical Intervention Records
- Health Reports

---

### Guidance Counselor Portal
- Dashboard
- Learner Guidance Profiles
- Individual Counseling Records
- Group Counseling Records
- Referral Management
- Behavioral Monitoring
- Intervention Plans
- Parent Conference Records
- Follow-up Monitoring
- Career Guidance Records
- Guidance Reports
- Case Management

---

### Parent Portal
- Dashboard
- View Learner Profile
- Attendance Monitoring
- Academic Performance
- Grade History
- Health Records (Authorized Information)
- Guidance Appointment Notifications
- School Announcements
- Contact Adviser

---

## Core Modules

### QR Code Attendance System
- QR Code generation
- QR Code scanning
- AM/PM attendance
- Late detection
- Attendance logs
- Attendance history

### Learner Information Management
- Personal Information
- Enrollment Records
- Academic Records
- Attendance Records
- Health Records
- Guidance Records

### Reporting Module
- Daily Attendance Reports
- Monthly Attendance Reports
- Learner Attendance Reports
- Class Performance Reports
- Health Reports
- Guidance Reports
- Export to PDF and Excel

---

## System Highlights

- Integrated Learner Profile
- QR Code Attendance Monitoring
- Role-Based Access Control (RBAC)
- Centralized Database
- Real-Time Dashboard
- Automated Report Generation
- Parent Engagement Portal
- Health Monitoring
- Guidance Case Management
- Secure User Authentication
- Responsive Web Interface

---

## Technology Stack

### Frontend
- HTML5
- CSS3
- Bootstrap 5
- JavaScript
- AJAX

### Backend
- PHP 8
- Laravel Framework *(or Native PHP)*

### Database
- MySQL / MariaDB

### Libraries and Tools
- QR Code Generator
- QR Code Scanner
- Chart.js
- DataTables
- SweetAlert2
- Python OpenCV and `face-recognition` (facial attendance)

### Facial Attendance Setup

The face-recognition station is integrated with the ProjectPulse database and
learner records. It does not use the separate `attendance_db` from the source
repository. Learner face registrations are stored as
`assets/images/learners/<12-digit-LRN>.jpg` and attendance is written to the
existing `attendance_records` and `attendance_scan_logs` tables.

1. Install Python 3.11 (recommended by the source attendance project) and the
   scanner dependencies with the same Python executable Apache will use:
   `C:\\Path\\To\\Python311\\python.exe -m pip install -r ai_scanner/requirements.txt`
2. If Apache cannot find Python, set `PROJECTPULSE_FACE_PYTHON` (or the legacy
   `AI_ATTENDANCE_PYTHON`) to the full path of that Python executable and
   restart Apache.
3. In the administrator portal, open **Face Enrollment**, capture a clear
   photo for each learner, then select **Train Face Recognition Model** to
   rebuild the local encoding cache.
4. Open **Face Recognition Station** using an Attendance or Administrator
   account and allow browser camera access.

The recognition model is rebuilt by the training action after enrollment.

For responsive scanning, ProjectPulse starts a localhost recognition worker on
the first facial-attendance scan. The worker keeps the OpenCV detector and
trained model in memory, so subsequent frames do not start Python or reload
the model. It listens only on `127.0.0.1:8765`; set
`PROJECTPULSE_FACE_SERVICE_URL` only when using a different local port.

---

## User Roles

- Administrator
- Teacher
- Health Coordinator
- Guidance Counselor
- Parent

---

## Security Features

- Role-Based Access Control (RBAC)
- Password Hashing
- User Authentication
- Secure Session Management
- Activity Logs
- Input Validation
- Database Backup and Recovery

---

## Objectives

ProjectPulse aims to:

- Centralize learner information into a unified digital platform.
- Automate attendance monitoring using QR Code technology.
- Improve administrative efficiency.
- Support evidence-based decision-making.
- Strengthen collaboration among administrators, teachers, parents, health coordinators, and guidance counselors.
- Enhance learner monitoring through integrated academic, attendance, health, and guidance records.
- Promote parent engagement through secure access to learner information.

---

## Future Enhancements

- SMS Notifications
- Email Notifications
- Mobile Application (Android and iOS)
- Push Notifications
- Learning Analytics Dashboard
- Early Warning and Intervention System
- AI-Assisted Student Support
- Appointment Scheduling
- Electronic Document Management
- School Clinic Inventory Management
- Multi-School Support
- Cloud Deployment

---

## License

This project is developed as an educational innovation and research initiative.

**© 2026 ProjectPulse. All Rights Reserved.**
