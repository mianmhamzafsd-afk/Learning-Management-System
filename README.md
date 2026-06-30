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

## 🔑 Quick Start & Test Accounts
The included database schema comes pre-populated with multiple imaginary test accounts for each role to demonstrate the system's workflows. 

To test the system, import the `.sql` file into your local MySQL environment, browse the users table, and log into **any** account using the global password below:

* **Global Test Password (for all accounts):** `12345`

### Dynamic Roles Available to Test:
* 👤 **Admin Panel:** Log in with any Admin account to manage faculty, focal persons, and approve event locations.
* 👔 **Focal Person Dashboard:** Log in with a coordinator account to post department news, request seminar venues, and download approved letters.
* 🧑‍🏫 **Faculty Portal:** Log in with any instructor account to view active system announcements and dashboards.
* 👨‍🎓 **Student Space:** Log in with any student account to update profiles and view academic tracking.

*(Note: While the password used for rapid local testing is uniform, all credentials are safely stored within the database as secure cryptographic hashes.)*
