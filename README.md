# TELEMEDICINE System

TELEMEDICINE is a role-based web application built for online healthcare management. It allows patients to book appointments, consult doctors through browser-based video calls, upload medical reports, receive digital prescriptions, track medicines, and monitor health vitals. Doctors can manage appointments, review patient data, issue prescriptions, and start consultations, while admins can verify doctor credentials before they appear to patients.

This project is designed to run locally with XAMPP using PHP and MySQL/MariaDB.

## Project Overview

The system includes three main user roles:

- `Patient`
- `Doctor`
- `Admin`

Main workflows supported by the project:

- Patient and doctor registration/login
- Admin verification of doctor accounts and credentials
- Appointment booking with doctor availability and time-slot rules
- Real-time style consultation flow using browser media devices
- Patient report upload and controlled report sharing
- Medicine checklist and prescription generation
- Health vitals logging and simple dashboard analytics
- Chat and notification features
- Built-in symptom assistant / emergency guidance chatbot

## Features

### Patient Module

- Register and log in as a patient
- Update profile and upload profile picture
- Book appointments with verified doctors
- View upcoming appointments
- Accept incoming consultation calls from doctors
- Start or join video consultations
- Upload medical reports in `PDF`, `JPG`, `JPEG`, and `PNG`
- Share reports with doctors or monitors after approval
- Log health vitals such as blood pressure, glucose, SpO2, and heart rate
- View medicine checklist and prescription reminders
- Download prescription files
- Use patient-to-patient communication and monitoring features

### Doctor Module

- Register and log in as a doctor
- Add specialization, license number, affiliations, and bio
- Upload professional credentials for admin review
- Become visible to patients only after admin verification
- Control appointment availability status
- View appointments and patients
- Start or receive consultation calls
- Review shared patient reports and vitals
- Issue one or multiple digital prescriptions
- Generate prescription PDF files automatically

### Admin Module

- Log in with admin account
- View doctors and patients
- Review uploaded doctor credentials
- Approve or reject doctor verification
- Control which doctors become visible on booking screens

### Extra Features

- Smart health assistant chatbot with symptom guidance
- Emergency quick-help numbers in the chatbot screen
- Notification system for appointments, calls, and requests
- Dark-mode aware UI in multiple pages
- Charts for patient vitals history

## Tools and Technologies Used

### Backend

- `PHP`
  - Used to build the full server-side application logic
  - Handles authentication, sessions, form processing, file uploads, notifications, appointments, prescriptions, and database operations

- `MySQL / MariaDB`
  - Used as the relational database
  - Stores patients, doctors, admins, appointments, reports, calls, messages, vitals, prescriptions, and notifications

- `MySQLi`
  - Used inside PHP to connect to the database and run queries/prepared statements

### Frontend

- `HTML5`
  - Used to structure all pages and forms

- `CSS3`
  - Used for custom UI styling, responsive layouts, cards, modals, dark mode, and dashboards

- `JavaScript`
  - Used for interactive UI behavior including video call controls, dynamic medicine forms, file downloads, theme handling, chatbot interaction, and page transitions

- `Google Fonts`
  - Used for interface typography such as `Clash Display`, `DM Sans`, and `Instrument Serif`

- `Chart.js`
  - Used in dashboards to visualize patient vitals history

### Browser APIs Used

- `MediaDevices / getUserMedia`
  - Used to access camera and microphone for video consultation

- `Fetch API`
  - Used for asynchronous requests like downloading files and page interactions

- `File System Access API`
  - Used in the prescription download flow with `showSaveFilePicker()` when the browser supports it

### Development Environment

- `XAMPP`
  - Used to run Apache and MySQL locally
  - The project is intended to be placed inside `htdocs`

- `phpMyAdmin`
  - Used to import the database and inspect tables locally

- `VS Code / any code editor`
  - Can be used to edit the project files

## How These Tools Are Used in This Project

### 1. PHP + MySQL

PHP pages connect to the `telemedicine_db` database through `config/db.php`. The application uses session-based login and role checks to decide whether a user is a patient, doctor, or admin. Data such as appointments, uploaded reports, calls, patient vitals, and prescriptions are saved in MySQL tables.

### 2. XAMPP

XAMPP provides the local Apache server and MySQL/MariaDB database needed to run the project. Place the folder inside:

```text
C:\xampp\htdocs\telemedicine_system
```

Then start `Apache` and `MySQL` from the XAMPP control panel.

### 3. Chart.js

Chart.js is used in the patient dashboard to display recent vitals trends visually, making it easier to monitor health changes over time.

### 4. Browser Media Features

The video consultation part uses browser device access so doctor and patient can join a consultation page and use the system camera and microphone directly from the browser.

### 5. Custom PDF Generation

The project contains a custom PHP-based PDF generator in `includes/prescription_pdf.php`. It creates prescription PDF files without relying on a heavy external PDF package.

### 6. File Upload System

Patients can upload reports and users can upload profile pictures and doctor credentials. PHP handles validation, directory creation, and saving file paths into the database.

## Folder Structure

```text
telemedicine_system/
|-- admin/                    # Admin dashboard and verification pages
|-- config/                   # Database connection
|-- css/                      # Shared styles
|-- doctor/                   # Doctor-side pages
|-- images/                   # Static assets and profile images
|-- includes/                 # Shared helper/core files
|-- js/                       # Shared JavaScript files
|-- patient/                  # Patient-side pages
|-- uploads/                  # Uploaded reports and prescriptions
|-- index.php                 # Landing page
|-- login.php                 # Login page
|-- register.php              # Registration page
|-- video_call.php            # Consultation screen
`-- telemedicine_db.sql       # Database export
```

## Database

Database name:

```text
telemedicine_db
```

Important tables include:

- `patients`
- `doctors`
- `admins`
- `appointments`
- `reports`
- `video_calls`
- `messages`
- `checklists`
- `checklist_items`
- `medicine_intakes`
- `patient_vitals`
- `doctor_credentials`
- `user_notifications`
- `monitor_requests`
- `report_share_requests`

Some tables or columns are also created automatically by helper files when the application runs for the first time.

## Installation and Setup

### 1. Copy Project to XAMPP

Place the project in:

```text
C:\xampp\htdocs\telemedicine_system
```

### 2. Start Services

Open XAMPP Control Panel and start:

- `Apache`
- `MySQL`

### 3. Create the Database

1. Open `http://localhost/phpmyadmin`
2. Create a database named `telemedicine_db`
3. Import the file `telemedicine_db.sql`

### 4. Check Database Connection

Open `config/db.php` and confirm the local settings:

```php
$conn = new mysqli("localhost", "root", "", "telemedicine_db");
```

### 5. Run the Project

Open this URL in your browser:

```text
http://localhost/telemedicine_system/
```

## Default Admin Account

The project can create a default admin account automatically if no admin exists.

- Email: `admin@telemedicine.local`
- Password: `admin123`

You can log in through the admin login flow and verify doctor accounts from the admin dashboard.

## Main Pages

- `index.php` - landing page
- `register.php` - registration for patient or doctor
- `login.php` - login page
- `patient/dashboard.php` - patient dashboard
- `patient/book_appointment.php` - appointment booking
- `patient/upload_report.php` - report upload and sharing
- `doctor/dashboard.php` - doctor dashboard
- `doctor/add_prescription.php` - issue digital prescriptions
- `admin/dashboard.php` - admin verification dashboard
- `video_call.php` - live consultation page
- `chatbot.php` - smart health assistant

## Security and Data Handling

- Passwords are stored using `password_hash()`
- Sessions are used for authentication
- Multiple areas use prepared statements for safer database access
- File uploads are restricted by type in key upload flows

## Future Improvements

- Stronger input validation across all forms
- Better real-time call signaling
- Email or SMS notifications
- More advanced analytics for vitals and medicine adherence
- Stronger role-based access control and audit logging
- Deployment-ready environment configuration

## Author Notes

This project demonstrates a full-stack PHP telemedicine platform with patient care, doctor workflows, admin verification, file management, dashboards, and digital consultation features in one local XAMPP-based application.
