<?php
session_start();
require_once 'db.php';

// Fetch events from DB
$events = [];
try {
    $stmt = $pdo->query("SELECT * FROM events ORDER BY event_date ASC");
    $events = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Failed to fetch events: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cportal - Tswayi High School</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
</head>
<body>

  <!-- Navigation Header -->
  <header>
    <div class="header-container">
      <div style="display: flex; align-items: center; gap: 10px;" onclick="window.location.href='index.php'" style="cursor: pointer;">
        <div style="width: 40px; height: 40px; background-color: var(--primary); border-radius: 50%; display: flex; justify-content: center; align-items: center; font-weight: bold; font-size: 20px; box-shadow: 0 0 10px var(--primary-glow)">C</div>
        <div style="margin-left: 10px;">
          <h2 style="font-size: 18px; line-height: 1.1;">cportal</h2>
          <span style="font-size: 12px; color: var(--text-secondary);">Tswayi High School</span>
        </div>
      </div>
      <nav style="display: flex; gap: 15px; align-items: center;">
        <?php if (isset($_SESSION['user'])): ?>
          <span style="font-size: 14px; color: var(--text-secondary);">
            Logged in as <strong style="color: var(--text-primary);"><?= htmlspecialchars($_SESSION['user']['username']) ?></strong>
          </span>
          <a href="dashboard.php" class="btn btn-outline" style="padding: 6px 14px;">Dashboard</a>
          <a href="logout.php" class="btn btn-danger" style="padding: 6px 14px;">Logout</a>
        <?php else: ?>
          <a href="login.php" class="btn btn-outline">Sign In</a>
          <a href="register.php" class="btn btn-primary">Apply for Enrollment</a>
        <?php endif; ?>
      </nav>
    </div>
  </header>

  <!-- Main Content -->
  <main>
    <!-- Hero Banner -->
    <section class="glass-panel" style="padding: 60px 40px; display: flex; flex-direction: column; gap: 20px; align-items: center; text-align: center; background: radial-gradient(ellipse at center, rgba(79, 70, 229, 0.15) 0%, rgba(11, 15, 25, 0) 70%); border-radius: var(--radius-lg);">
      <h1 style="font-size: 42px; font-weight: 800; line-height: 1.2; background: linear-gradient(to right, #f8fafc, #6366f1); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
        Technology for Quality Education
      </h1>
      <p style="max-width: 600px; font-size: 18px; color: var(--text-secondary); line-height: 1.6;">
        Welcome to Tswayi High School’s cportal system. We combine academic standards with accessible, AI-powered technology to keep our students and community connected.
      </p>
      <div style="display: flex; gap: 15px; margin-top: 10px;">
        <a href="register.php" class="btn btn-primary" style="padding: 12px 30px; font-size: 15px; border-radius: var(--radius-md);">Apply Online Now</a>
        <button onclick="toggleAiChat()" class="btn btn-outline" style="padding: 12px 30px; font-size: 15px; border-radius: var(--radius-md);">Ask School AI</button>
      </div>
    </section>

    <!-- School Mission & Vision Cards -->
    <section class="grid-3">
      <div class="glass-panel" style="display: flex; flex-direction: column; gap: 15px;">
        <div style="width: 40px; height: 40px; background-color: rgba(79, 70, 229, 0.1); color: var(--primary); display: flex; justify-content: center; align-items: center; border-radius: 8px; font-weight: bold; font-size: 20px;">👁</div>
        <h3>Our Vision</h3>
        <p style="color: var(--text-secondary); font-size: 15px; line-height: 1.6;">
          To be a premier institution of quality instruction that matches global academic and technological standards, ensuring no child is left behind due to technical barriers.
        </p>
      </div>
      <div class="glass-panel" style="display: flex; flex-direction: column; gap: 15px;">
        <div style="width: 40px; height: 40px; background-color: rgba(217, 119, 6, 0.1); color: var(--accent); display: flex; justify-content: center; align-items: center; border-radius: 8px; font-weight: bold; font-size: 20px;">🚀</div>
        <h3>Our Mission</h3>
        <p style="color: var(--text-secondary); font-size: 15px; line-height: 1.6;">
          Providing standardized, affordable private secondary education with high-level learning outcomes by equipping learners with essential scientific, mathematical, and IT tools.
        </p>
      </div>
      <div class="glass-panel" style="display: flex; flex-direction: column; gap: 15px;">
        <div style="width: 40px; height: 40px; background-color: rgba(16, 185, 129, 0.1); color: var(--success); display: flex; justify-content: center; align-items: center; border-radius: 8px; font-weight: bold; font-size: 20px;">📚</div>
        <h3>Academic Subjects</h3>
        <p style="color: var(--text-secondary); font-size: 15px; line-height: 1.6;">
          Our curriculum covers core secondary modules including **Mathematics**, **Computer Science**, **Biology**, and **English Language**, paired with interactive AI tutoring.
        </p>
      </div>
    </section>

    <!-- School Calendar Section -->
    <section class="glass-panel" style="display: flex; flex-direction: column; gap: 20px;">
      <div>
        <h2>School Calendar & Events</h2>
        <p style="color: var(--text-secondary); font-size: 14px; margin-top: 5px;">Keep track of academic starts and sport operations.</p>
      </div>
      <div style="display: flex; flex-direction: column; gap: 15px;">
        <?php if (empty($events)): ?>
          <p style="color: var(--text-muted);">No upcoming events scheduled.</p>
        <?php else: ?>
          <?php foreach ($events as $evt): ?>
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 15px; background: var(--bg-input); border: 1px solid var(--border-color); border-radius: var(--radius-md);">
              <div>
                <h4 style="color: var(--text-primary);"><?= htmlspecialchars($evt['title']) ?></h4>
                <p style="color: var(--text-secondary); font-size: 14px; margin-top: 4px;"><?= htmlspecialchars($evt['description']) ?></p>
              </div>
              <span style="font-size: 13px; font-weight: 600; color: var(--accent); background: var(--accent-glow); padding: 5px 12px; border-radius: 15px; border: 1px solid rgba(217, 119, 6, 0.25);">
                <?= htmlspecialchars($evt['event_date']) ?>
              </span>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </section>
  </main>

  <!-- Footer -->
  <footer>
    <div class="footer-container">
      © 2026 Tswayi High School • Built by NUST Computer Science (Mphathisi Ndlovu, Melissa Dube, Daphne Ncube)
    </div>
  </footer>

  <!-- Floating AI Assistant Widget -->
  <button class="ai-bubble" onclick="toggleAiChat()">💬</button>

  <div class="ai-panel" id="ai-chat-panel" style="display: none;">
    <div class="ai-header">
      <div>
        <h3 style="font-size: 15px; display: flex; align-items: center; gap: 6px;">
          <span style="width: 8px; height: 8px; background-color: var(--success); border-radius: 50%; display: inline-block;"></span>
          cportal AI Assistant
        </h3>
        <span style="font-size: 10px; color: var(--text-secondary);">Powered by Groq Cloud APIs</span>
      </div>
      <button onclick="toggleAiChat()" style="background: none; border: none; color: var(--text-secondary); cursor: pointer; font-size: 18px;">✕</button>
    </div>
    
    <div class="ai-body" id="ai-messages-container">
      <div class="ai-msg ai-msg-assistant">
        Hello! I am the cportal AI. How can I help you today at Tswayi High School?
      </div>
    </div>

    <form class="ai-input-form" onsubmit="sendAiMessage(event)">
      <input type="text" id="ai-chat-input" placeholder="Ask AI for clarification..." required autocomplete="off">
      <button type="submit" class="ai-send-btn">➔</button>
    </form>
  </div>

  <script>
    function toggleAiChat() {
      const panel = document.getElementById('ai-chat-panel');
      panel.style.display = panel.style.display === 'none' ? 'flex' : 'none';
      scrollToBottom();
    }

    function scrollToBottom() {
      const container = document.getElementById('ai-messages-container');
      container.scrollTop = container.scrollHeight;
    }

    function sendAiMessage(e) {
      e.preventDefault();
      const input = document.getElementById('ai-chat-input');
      const text = input.value.trim();
      if (!text) return;

      input.value = '';
      appendMessage('user', text);

      // Append typing indicator
      const container = document.getElementById('ai-messages-container');
      const typingDiv = document.createElement('div');
      typingDiv.className = 'ai-msg ai-msg-assistant typing-indicator';
      typingDiv.innerText = 'Thinking...';
      container.appendChild(typingDiv);
      scrollToBottom();

      fetch('ai.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message: text })
      })
      .then(res => res.json())
      .then(data => {
        container.removeChild(typingDiv);
        appendMessage('assistant', data.reply);
      })
      .catch(err => {
        container.removeChild(typingDiv);
        appendMessage('assistant', 'Sorry, I encountered an error communicating with the server.');
      });
    }

    function appendMessage(sender, text) {
      const container = document.getElementById('ai-messages-container');
      const msgDiv = document.createElement('div');
      msgDiv.className = `ai-msg ai-msg-${sender}`;
      msgDiv.innerText = text;
      container.appendChild(msgDiv);
      scrollToBottom();
    }
  </script>

</body>
</html>
