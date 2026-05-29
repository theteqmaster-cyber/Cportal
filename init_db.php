<?php
/**
 * File: init_db.php
 * Description: Sets up the database schema, creates all required tables (users, classes, students, teachers, grades, events, audit logs, help tickets), and seeds default portal users and sample records.
 * Importance: Necessary for initial database installation and configuration setup.
 */

require_once 'db.php';

try {
    // 1. Users Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('admin', 'teacher', 'student', 'bursar', 'helpdesk', 'parent') NOT NULL,
            status ENUM('active', 'locked') NOT NULL DEFAULT 'active'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 2. Classes Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS classes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) UNIQUE NOT NULL,
            teacher_id INT NULL,
            FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 3. Students Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS students (
            id INT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            class_id INT NULL,
            fees_paid TINYINT(1) NOT NULL DEFAULT 0,
            parent_id INT NULL,
            enrollment_status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'approved',
            FOREIGN KEY (id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL,
            FOREIGN KEY (parent_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 4. Teachers Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS teachers (
            id INT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            subjects TEXT NULL, -- JSON array of subjects
            FOREIGN KEY (id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 5. Grades Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS grades (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            subject VARCHAR(100) NOT NULL,
            marks INT CHECK (marks >= 0 AND marks <= 100),
            term VARCHAR(50) NOT NULL,
            teacher_id INT NULL,
            FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 6. Events Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(150) NOT NULL,
            description TEXT NULL,
            event_date DATE NOT NULL,
            created_by INT NULL,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 7. Audit Logs Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS audit_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            action VARCHAR(255) NOT NULL,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            ip_address VARCHAR(45) NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 8. Help Tickets Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS help_tickets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            message TEXT NOT NULL,
            guest_contact VARCHAR(100) NULL,
            status ENUM('open', 'resolved', 'escalated') NOT NULL DEFAULT 'open',
            response TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    echo "Tables created successfully.\n";

    // Seed default users if users table is empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    if ($stmt->fetchColumn() == 0) {
        echo "Seeding initial data...\n";

        // Passwords
        $helpdeskHash = password_hash('helpdesk123', PASSWORD_BCRYPT);
        $adminHash    = password_hash('admin123', PASSWORD_BCRYPT);
        $bursarHash   = password_hash('bursar123', PASSWORD_BCRYPT);
        $teacherHash  = password_hash('teacher123', PASSWORD_BCRYPT);
        $teacher2Hash = password_hash('teacher223', PASSWORD_BCRYPT);
        $studentHash  = password_hash('student123', PASSWORD_BCRYPT);
        $parentHash   = password_hash('parent123', PASSWORD_BCRYPT);

        // IT Helpdesk User
        $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)")
            ->execute(['helpdesk', $helpdeskHash, 'helpdesk']);

        // Admin User
        $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)")
            ->execute(['admin', $adminHash, 'admin']);
        $adminId = $pdo->lastInsertId();

        // Bursar User
        $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)")
            ->execute(['bursar', $bursarHash, 'bursar']);

        // Teacher 1: Mphathisi Ndlovu
        $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)")
            ->execute(['mndlovu', $teacherHash, 'teacher']);
        $teacher1Id = $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO teachers (id, name, subjects) VALUES (?, ?, ?)")
            ->execute([$teacher1Id, 'Mphathisi Ndlovu', json_encode(['Mathematics', 'Computer Science'])]);

        // Teacher 2: Melissa Dube
        $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)")
            ->execute(['mdube', $teacher2Hash, 'teacher']);
        $teacher2Id = $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO teachers (id, name, subjects) VALUES (?, ?, ?)")
            ->execute([$teacher2Id, 'Melissa Dube', json_encode(['English Language', 'Biology'])]);

        // Classes
        $pdo->prepare("INSERT INTO classes (name, teacher_id) VALUES (?, ?)")
            ->execute(['Form 3A', $teacher1Id]);
        $class3AId = $pdo->lastInsertId();

        $pdo->prepare("INSERT INTO classes (name, teacher_id) VALUES (?, ?)")
            ->execute(['Form 3B', $teacher2Id]);
        $class3BId = $pdo->lastInsertId();

        // Parent User
        $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)")
            ->execute(['parent_jncube', $parentHash, 'parent']);
        $parentId = $pdo->lastInsertId();

        // Student 1: Daphne Ncube (Paid, Form 3A, Parent link)
        $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)")
            ->execute(['dncube', $studentHash, 'student']);
        $student1Id = $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO students (id, name, class_id, fees_paid, parent_id, enrollment_status) VALUES (?, ?, ?, 1, ?, 'approved')")
            ->execute([$student1Id, 'Daphne Ncube', $class3AId, $parentId]);

        // Student 2: John Smith (Unpaid, Form 3B, No parent)
        $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)")
            ->execute(['jsmith', $studentHash, 'student']);
        $student2Id = $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO students (id, name, class_id, fees_paid, parent_id, enrollment_status) VALUES (?, ?, ?, 0, NULL, 'approved')")
            ->execute([$student2Id, 'John Smith', $class3BId]);

        // Grades for Daphne (Paid)
        $pdo->prepare("INSERT INTO grades (student_id, subject, marks, term, teacher_id) VALUES (?, 'Mathematics', 85, 'Term 1', ?)")
            ->execute([$student1Id, $teacher1Id]);
        $pdo->prepare("INSERT INTO grades (student_id, subject, marks, term, teacher_id) VALUES (?, 'Computer Science', 92, 'Term 1', ?)")
            ->execute([$student1Id, $teacher1Id]);
        $pdo->prepare("INSERT INTO grades (student_id, subject, marks, term, teacher_id) VALUES (?, 'English Language', 78, 'Term 1', ?)")
            ->execute([$student1Id, $teacher2Id]);

        // Grades for John (Unpaid)
        $pdo->prepare("INSERT INTO grades (student_id, subject, marks, term, teacher_id) VALUES (?, 'English Language', 65, 'Term 1', ?)")
            ->execute([$student2Id, $teacher2Id]);
        $pdo->prepare("INSERT INTO grades (student_id, subject, marks, term, teacher_id) VALUES (?, 'Biology', 70, 'Term 1', ?)")
            ->execute([$student2Id, $teacher2Id]);

        // School Events
        $pdo->prepare("INSERT INTO events (title, description, event_date, created_by) VALUES (?, ?, ?, ?)")
            ->execute(['Welcome Term 2', 'Orientation day and assembly for all students and parents.', '2026-06-02', $adminId]);
        $pdo->prepare("INSERT INTO events (title, description, event_date, created_by) VALUES (?, ?, ?, ?)")
            ->execute(['Inter-School Athletics Competition', 'Tswayi High hosting regional athletic meets. Come and cheer for our team!', '2026-06-18', $adminId]);

        echo "Seeding completed successfully.\n";
    } else {
        echo "Database already contains users. Seeding skipped.\n";
    }

} catch (PDOException $e) {
    die("Database initialization failed: " . $e->getMessage() . "\n");
}

// Future Improvements: Implement modular migration support and add indexes to foreign keys to optimize query performance.
