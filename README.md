# Role-Based Learning Management & Seminar Coordination System

A full-stack, secure management portal built to handle academic workflows and event logistics through precise, role-based access control. The system features a centralized data architecture where permissions are dynamically enforced across four distinct user roles, ensuring secure session handling and data integrity.

## 👥 Granular User Roles & Permissions
* **Admin:** Manages core system data, registers Faculty and Focal Persons, manages the database of available event locations, and holds sole authority to approve or disapprove seminar requests.
* **Focal Person (Department Coordinator):** Acts as the primary event planner. Can upload department news and notifications, request seminar bookings for specific locations, and download the official approval/destination letters once authorized by the Admin.
* **Faculty:** Serves as an authorized viewer with clean dashboard access to review system updates, announcements, and academic timelines.
* **Student:** Maintains a dedicated, self-managed profile dashboard with capabilities to dynamically update personal information and track academic records.

## 🚀 Key Technical Features
* **Dynamic Multi-Role Authentication:** Secure PHP session-based login architecture that routes users dynamically to their tailored interfaces based on database privileges.
* **Logistics & Workflow Automation:** A multi-step request/approval pipeline for scheduling seminars, tracking venue availability, and generating downloadable authorization documents.
* **Full-Stack CRUD Operations:** Complete data management system for handling user profiles, location entries, and department-wide announcements.

## 🛠️ Tech Stack
* **Backend:** PHP (Server-side business logic, request handling, and session management)
* **Frontend:** HTML5, CSS3, JavaScript (ES6) for an interactive, responsive user interface
* **Database:** MySQL (Relational schema handling user roles, location mappings, and application states)
* **Development Environment:** VS Code
