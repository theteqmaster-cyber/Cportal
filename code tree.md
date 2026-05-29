# Cportal Workspace Directory Tree

A brief overview of the files inside the Cportal school portal workspace, their purposes, and their importance.

```text
Cportal/
├── about.php          - About Page (renders school history, values, and faculty details)
├── ai.php             - AI Chatbot Gateway (handles Groq API requests with local fallbacks)
├── dashboard.php      - Primary Internal Hub (role-based controls for students, parents, teachers, bursars, admins, and helpdesk)
├── db.php             - Database Connection (initiates connection using safe PDO settings)
├── index.php          - Public Homepage (general information, enrollment application, public events calendar, AI chat)
├── init_db.php        - Database Seeding (constructs schema tables and populates default sample records)
├── login.php          - Login Gatekeeper (authenticates credentials and creates user sessions)
├── logout.php         - Session Termination (destroys active user sessions securely)
├── register.php       - Onboarding Application (collects parent/student details to trigger enrollment process)
├── script.js          - Client-side Interactions (dynamic behaviors like drawer panel triggers and light/dark theme toggle)
└── style.css          - Premium CSS Stylesheet (contains design tokens, layout variables, typography, and dark/light mode switches)
```

---

## File Explanations

### `index.php`
- **What it does:** Displays school info, events calendar, and serves as landing page.
- **Why it's important:** Entry point for public visitors and guests.

### `about.php`
- **What it does:** Details school background, values, and lists teachers/subjects.
- **Why it's important:** Onboards trust by proving credentials and faculty capabilities.

### `dashboard.php`
- **What it does:** Adapts interface tools to fit user roles (e.g. grading for teachers, locks for helpdesk, billing info for bursars).
- **Why it's important:** The primary internal operations portal for the school.

### `login.php`
- **What it does:** Secures access via user credentials check.
- **Why it's important:** Essential entry security for internal school operations.

### `register.php`
- **What it does:** Onboards parent accounts and registers pending students.
- **Why it's important:** The portal onboarding gate for new applicants.

### `logout.php`
- **What it does:** Securely terminates active sessions.
- **Why it's important:** Prevents session hijacking and unauthorized reuse of logged-in devices.

### `ai.php`
- **What it does:** Powers the cportal AI chatbot using Groq.
- **Why it's important:** Provides on-demand analytics and helpful responses matching each user's role.

### `db.php`
- **What it does:** Provides PDO-based database connectivity.
- **Why it's important:** Foundational utility allowing script-to-database communications.

### `init_db.php`
- **What it does:** Installs tables and inserts starting records.
- **Why it's important:** Handles installation migrations and populates starting administrative data.

### `script.js`
- **What it does:** Manages dynamic client behaviors (drawer sidebar, theme switches).
- **Why it's important:** Vital for UI interactivity and theme toggles.

### `style.css`
- **What it does:** Implements layout, styles, variables, transitions, and dark/light modes.
- **Why it's important:** Centralized stylesheet managing the entire visual theme and layout structure.
