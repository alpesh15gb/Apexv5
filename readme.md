You are a senior Laravel architect and HRMS domain expert.

I want to build a full-featured Employee Attendance Tracking System using:

Tech Stack:
- Backend: PHP 8.2, Laravel 10+
- Database:
   - Main App DB: MySQL or PostgreSQL
   - Punch Logs Source DB: Microsoft SQL Server Express (Etimetracklite1)
- Hosting: Docker (Nginx + PHP-FPM + Supervisor + Queue Worker)

Punch Log Source:
- MSSQL Database: Etimetracklite1
- Table contains raw punch logs from biometric devices
- All timestamps are already in IST format (no timezone conversion needed)

Core Requirements:

1. Data Sync & Processing
- Read punch logs automatically from MSSQL
- Cron job / queue worker to pull new logs every X minutes
- Store normalized logs in local DB
- Avoid duplicate punches
- Map punches to employees using device employee codes

2. Attendance Engine
- Shift master:
   - Fixed shifts
   - Night shifts (cross date)
   - Grace period, late rules, early out rules
- Daily attendance calculation:
   - First In, Last Out
   - Total working hours
   - Late, Early, Absent, Half-day, Present
- Weekly & Monthly aggregation
- Handle missing punches

3. Masters & Structure
- Companies
- Branches
- Locations
- Departments
- Shifts
- Employees linked to:
   Company -> Branch -> Location -> Department -> Shift

4. Reports Module
- Daily attendance report
- Weekly summary
- Monthly attendance register
- Late coming report
- Absent report
- Overtime report
- Filters by:
   Company, Branch, Location, Department, Shift, Employee, Date range

5. Export & Documents
- Export all reports to:
   - Excel (.xlsx)
   - PDF (company branded)
- Attendance register format similar to payroll standards

6. UI / UX
- Clean modern admin dashboard
- Sidebar navigation
- Charts:
   - Present vs Absent
   - Late trends
- Responsive layout
- Use Tailwind + Alpine OR Vue if needed

7. Authentication & Roles
- Super Admin
- Company Admin
- HR Manager
- Read-only Viewer
- Role-based data access (company level isolation)

8. APIs & Structure
- Service-based architecture:
   - PunchImportService
   - AttendanceCalculationService
   - ReportService
- Repository pattern for DB access
- Proper validation and form requests

9. Docker Setup
- docker-compose with:
   - nginx
   - php-fpm
   - mysql/postgres
   - supervisor for queue
- Environment-based config

10. Code Quality
- Migrations
- Seeders for masters
- Policy based authorization
- Jobs & queues for heavy tasks
- Logging of sync status and errors

Deliverables:
- Full DB schema design
- Laravel migrations
- Models and relationships
- Services for punch import and attendance calculation
- Report queries optimized for large datasets
- Controllers and routes
- UI layout with reusable components
- Docker configuration

Start by:
1. Designing the database schema (ER structure)
2. Showing migrations
3. Explaining punch import flow
4. Then attendance calculation logic
5. Then reports

Proceed step-by-step and ask only when absolutely required.
