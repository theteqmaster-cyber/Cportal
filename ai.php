<?php
/**
 * File: ai.php
 * Description: Implements a server-side AI chatbot integration for the portal using the Groq API (fallback to Mock responses if API key is not configured).
 * Importance: Empowers students, parents, and teachers with immediate AI-assisted data analysis and query resolutions based on their portal role.
 */

session_start();
require_once 'db.php';

header('Content-Type: application/json');

// Read JSON input
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
$message = isset($data['message']) ? trim($data['message']) : '';

if (empty($message)) {
    echo json_encode(['reply' => 'No message provided.']);
    exit;
}

// 1. Compile System Prompt Context based on session user
$user = isset($_SESSION['user']) ? $_SESSION['user'] : null;
$contextPrompt = "";

if (!$user) {
    // Guest context
    $contextPrompt = "You are the cportal AI Assistant for Tswayi High School. The user is a Guest (unlogged-in). Explain that Tswayi High School offers Mathematics, Computer Science, Biology, and English. Guests can browse general details, view school events, or register for enrollment. Parents can apply online using the 'Apply for Enrollment' form on the public page. Keep your answers warm, friendly, and direct. Do not give any technical database details.";
} else {
    $role = $user['role'];
    $userId = $user['id'];
    
    if ($role === 'student') {
        // Fetch student details
        $stmt = $pdo->prepare("SELECT s.name, s.fees_paid, c.name as class_name FROM students s LEFT JOIN classes c ON s.class_id = c.id WHERE s.id = ?");
        $stmt->execute([$userId]);
        $student = $stmt->fetch();
        
        if ($student) {
            if ($student['fees_paid'] == 0) {
                $contextPrompt = "You are the cportal AI Assistant for Tswayi High School. The current user is a STUDENT named '" . $student['name'] . "' in Class '" . ($student['class_name'] ?? 'Unassigned') . "'. FEES STATUS: UNPAID. Under school rules, unpaid students are blocked from viewing subjects or grades. If they ask about grades, subjects, or progress reports, explain politely that access is restricted until fees are cleared by the Bursar. Direct them to make a payment in USD or ZiG at the Bursar's office. Do NOT expose any grade details.";
            } else {
                // Fetch grades
                $gStmt = $pdo->prepare("SELECT g.subject, g.marks, g.term, t.name as teacher_name FROM grades g LEFT JOIN teachers t ON g.teacher_id = t.id WHERE g.student_id = ?");
                $gStmt->execute([$userId]);
                $grades = $gStmt->fetchAll();
                $gradesSummary = "";
                foreach ($grades as $g) {
                    $gradesSummary .= "- " . $g['subject'] . ": " . $g['marks'] . "% (" . $g['term'] . ", taught by " . ($g['teacher_name'] ?? 'N/A') . ")\n";
                }
                $contextPrompt = "You are the cportal AI Assistant for Tswayi High School. The current user is a STUDENT named '" . $student['name'] . "' in Class '" . ($student['class_name'] ?? 'Unassigned') . "'. FEES STATUS: PAID. Here are their grades:\n" . ($gradesSummary ?: "No grades recorded yet.") . "\nHelp them analyze their marks, calculate averages, suggest study paths, or answer any school questions.";
            }
        }
    } else if ($role === 'parent') {
        $stmt = $pdo->prepare("SELECT s.name, s.enrollment_status, s.fees_paid, c.name as class_name FROM students s LEFT JOIN classes c ON s.class_id = c.id WHERE s.parent_id = ?");
        $stmt->execute([$userId]);
        $children = $stmt->fetchAll();
        $childrenSummary = "";
        foreach ($children as $c) {
            $childrenSummary .= "- Child: " . $c['name'] . ", Status: " . $c['enrollment_status'] . ", Class: " . ($c['class_name'] ?? 'Unassigned') . ", Fees: " . ($c['fees_paid'] ? 'Paid' : 'Unpaid') . "\n";
        }
        $contextPrompt = "You are the cportal AI Assistant. The current user is a Parent. Here are their children linked to their account:\n" . $childrenSummary . "\nHelp them track enrollment approvals, view credentials for approved accounts, or explain billing and fee policies.";
    } else if ($role === 'teacher') {
        $tStmt = $pdo->prepare("SELECT name, subjects FROM teachers WHERE id = ?");
        $tStmt->execute([$userId]);
        $teacher = $tStmt->fetch();
        $cStmt = $pdo->prepare("SELECT name FROM classes WHERE teacher_id = ?");
        $cStmt->execute([$userId]);
        $classes = $cStmt->fetchAll(PDO::FETCH_COLUMN);
        
        $contextPrompt = "You are the cportal AI Assistant. The user is a teacher named '" . ($teacher['name'] ?? '') . "'. They teach: " . ($teacher['subjects'] ?? '[]') . ". They are class teacher for classes: " . implode(', ', $classes) . ". Help them compile class averages, draft report comments, or answer operational questions.";
    } else if ($role === 'bursar') {
        $contextPrompt = "You are the cportal AI Assistant. The user is the school bursar. Help them understand tuition structures, currency rates (USD/ZiG), or generate payment notices.";
    } else if ($role === 'admin') {
        $contextPrompt = "You are the cportal AI Assistant. The user is the school administrator. Help them draft announcements, plan events, or organize teacher allocations.";
    } else if ($role === 'helpdesk') {
        $contextPrompt = "You are the cportal AI Assistant. The user is the IT Helpdesk coordinator. They can lock/unlock accounts, reset passwords, check transaction logs, and review support tickets. Explain the database schema if they ask: users, classes, students, teachers, grades, events, audit_logs, help_tickets. Provide exact SQL queries to assist them.";
    }
}

// Append response length and greeting constraints to ensure responses are concise, natural, and friendly
$contextPrompt .= " Keep your response concise, helpful, and direct (ideally 2 to 3 sentences). Avoid long paragraphs or essay-like responses. If the user greets you (e.g., 'hi' or 'hello'), greet them back naturally (e.g., 'Hello!' or 'Hi there!'). However, do NOT repeat the formal 'Welcome to Tswayi High School' greeting on subsequent responses.";

// 2. Read GROQ_API_KEY from environment or file
$groqKey = null;
if (file_exists('.env')) {
    $lines = file('.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, 'GROQ_API_KEY=') === 0) {
            $parts = explode('=', $line, 2);
            $groqKey = trim($parts[1]);
        }
    }
}
if (empty($groqKey) && getenv('GROQ_API_KEY')) {
    $groqKey = trim(getenv('GROQ_API_KEY'));
}

if (!empty($groqKey)) {
    $postData = [
        'model' => 'llama-3.1-8b-instant',
        'messages' => [
            ['role' => 'system', 'content' => $contextPrompt],
            ['role' => 'user', 'content' => $message]
        ],
        'temperature' => 0.5,
        'max_tokens' => 500
    ];
    
    if (function_exists('curl_init')) {
        $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($postData),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $groqKey
            ],
            CURLOPT_TIMEOUT => 15
        ]);
        
        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        
        if ($err) {
            echo json_encode(['reply' => "[AI Fallback Mode] Connection issue to Groq AI cloud ($err). Please review options or contact Helpdesk."]);
        } else {
            $resData = json_decode($response, true);
            $reply = $resData['choices'][0]['message']['content'] ?? 'I could not process your query at this moment.';
            echo json_encode(['reply' => $reply]);
        }
    } else {
        // Fallback to stream context if php-curl is not installed
        $options = [
            'http' => [
                'header'  => "Content-Type: application/json\r\n" .
                             "Authorization: Bearer " . $groqKey . "\r\n",
                'method'  => 'POST',
                'content' => json_encode($postData),
                'timeout' => 15,
                'ignore_errors' => true
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ];
        $context  = stream_context_create($options);
        $response = @file_get_contents('https://api.groq.com/openai/v1/chat/completions', false, $context);
        
        if ($response === false) {
            $lastError = error_get_last();
            $errorDetail = $lastError ? $lastError['message'] : 'SSL/Connection stream failed';
            echo json_encode(['reply' => "[AI Fallback Mode] Connection issue to Groq AI cloud: " . $errorDetail]);
        } else {
            $resData = json_decode($response, true);
            if (isset($resData['error']['message'])) {
                echo json_encode(['reply' => "[Groq API Error] " . $resData['error']['message']]);
            } else {
                $reply = $resData['choices'][0]['message']['content'] ?? 'I could not process your query at this moment.';
                echo json_encode(['reply' => $reply]);
            }
        }
    }
} else {
    // Return Mock response
    $mockReply = "";
    if (!$user) {
        $mockReply = "Welcome to Tswayi High School! We offer Mathematics, Computer Science, Biology, and English. (Local Mock AI Mode: GROQ_API_KEY is not configured).";
    } else if ($user['role'] === 'student') {
        $mockReply = "Hello student. (Local Mock AI Mode). If your fees are paid, you can view your grades in your dashboard. Otherwise, make a payment in USD/ZiG at the Bursar.";
    } else {
        $mockReply = "Hello " . $user['username'] . " (Role: " . $user['role'] . "). (Local Mock AI Mode). Once configured, the AI will dynamically assist you with operations matching your role.";
    }
    echo json_encode(['reply' => $mockReply]);
}

// Future Improvements: Implement chat session history persistence to allow users to resume conversations.
