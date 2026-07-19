# ProjectPulse

ProjectPulse is a PHP and MySQL project for a school portal that currently supports:

- learner records
- learner health coordination with BMI, deworming, and feeding program tracking
- historical grades by school year and grade level
- attendance with time in, time out, and daily or monthly viewing
- teacher accounts with section assignment and parent-linking tools
- teacher import templates for parent accounts and subject grades
- flexible grade records for quarterly subjects and senior high semester subjects
- parent access to linked children with month-based attendance review
- parent access to linked children grade history across recorded grade levels
- session-based login for attendance, admin, health coordinator, teacher, and parent portals
- LRN lookup that shows learner name, grade, and section

## Current stack

- PHP for server-side rendering
- MySQL for data storage
- plain CSS for the initial interface
- XAMPP-friendly folder layout

## Current attendance flow

1. Open `http://localhost/ProjectPulse` or `http://<your-lan-ip>/ProjectPulse`
2. Login with username `attendance_admin` and password `attendance123`
3. You will be redirected to the attendance page
4. Search or scan an LRN to view learner name, grade level, section, school year, and today's attendance status
5. Use the `Time In` or `Time Out` buttons to save attendance for the current day

## Database setup

1. Open `phpMyAdmin` or MySQL from XAMPP
2. Import [database/schema.sql](C:\xampp\htdocs\ProjectPulse\database\schema.sql)
3. Visit `http://localhost/ProjectPulse` or `http://<your-lan-ip>/ProjectPulse`

## Default database credentials

- Host: `127.0.0.1`
- Port: `3306`
- Database: `project_pulse`
- Username: `root`
- Password: empty by default in many XAMPP setups

## Demo LRNs

- `123456789012`
- `987654321098`

## Demo parent login

- Username: `demo_parent`
- Password: `parent123`

## Demo teacher login

- Username: `teacher_mabini`
- Password: `teacher123`

## Demo health coordinator login

- Username: `health_coordinator`
- Password: `health123`

## Teacher imports

- Parent template: `download_parent_template.php?format=csv` or `format=xls`
- Grade template: `download_grade_template.php?format=csv` or `format=xls`
- Grade imports include `school_year` and `grade_level`, regular quarter 1-4 grades, and senior high semester subjects where unused quarters can be blank or `#`.

## Notes

- Historical grade lookup is supported through `learner_enrollments` and `grade_records`
- Parent access is supported through `parents` and `parent_learner_links`
- Attendance summaries can be grouped by day or month using `attendance_records` and `attendance_legends`
