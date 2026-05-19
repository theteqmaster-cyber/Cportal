# cportal Setup & Management Steps

This guide outlines how to configure the Groq API Key, manage the PHP development server, and use the default seeded credentials.

---

## 1. Setting up the Groq API Key

The portal uses a `.env` configuration file to pull the Groq API Key. The key is used by `ai.php` to supply contextual AI chat responses for students, parents, teachers, and administrators.

1. Open the `.env` file in the root directory.
2. Set the `GROQ_API_KEY` property with your API key:
   ```env
   GROQ_API_KEY=your_groq_api_key_here
   ```
3. Save the file.
   *Note: If the key is left empty or not found, the AI chat widget will automatically switch to a local Mock AI offline responder to prevent crashes.*

---

## 2. Managing the PHP Development Server

Since we prioritized academic standards, the portal runs on standard HTML, CSS, JavaScript, and PHP. You can launch and manage the site locally using the PHP built-in web server.

### Starting the Server
Open your terminal in the `Cportal` root directory and run:
```bash
php -S localhost:5000
```
Then, open your browser and navigate to:
```
http://localhost:5000/index.php
```

### Stopping the Server
1. If the server is running in your current terminal pane, simply press:
   `Ctrl + C`
2. If the server is running in the background and you want to terminate it:
   ```bash
   kill $(lsof -t -i:5000)
   ```

---

## 3. Seeded Database Credentials

The MySQL database `project_db` was initialized and pre-seeded with sample users for all 6 core roles. Use the credentials below to log in:

| Role | Username | Password | Notes / Context |
| :--- | :--- | :--- | :--- |
| **IT Helpdesk** | `helpdesk` | `helpdesk123` | Can lock/unlock accounts, reset passwords, check transaction logs, and review support tickets. |
| **Admin** | `admin` | `admin123` | Can hire teachers, create classes, approve parent enrollment forms, and post school events. |
| **Bursar** | `bursar` | `bursar123` | Can toggle student fees status to allow/block student progress report access. |
| **Teacher** | `mndlovu` | `teacher123` | *Mphathisi Ndlovu* (Math/CS). Can enter student grades for class Form 3A. |
| **Teacher** | `mdube` | `teacher223` | *Melissa Dube* (English/Biology). Can enter student grades for class Form 3B. |
| **Student (Paid)** | `dncube` | `student123` | *Daphne Ncube* (Form 3A). Fees paid. Can view grades and get AI homework help. |
| **Student (Unpaid)**| `jsmith` | `student123` | *John Smith* (Form 3B). Fees outstanding. Dashboard restricted behind payment wall. |
| **Parent** | `parent_jncube`| `parent123` | Parent of *Daphne Ncube*. Can view approval status and student credentials. |
