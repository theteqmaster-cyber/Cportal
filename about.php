<?php
/**
 * File: about.php
 * Description: The about page of Tswayi High School, detailing background history, values, and qualified faculty details.
 * Importance: Displays credentials, mission statements, and faculty list to construct trust with parents, guests, and students.
 */

session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>About Us - cportal</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
  <script>
    if (localStorage.getItem('theme') === 'light') {
      document.documentElement.classList.add('light-mode');
    }
  </script>
</head>
<body>

  <!-- Navigation Header -->
  <header>
    <div class="header-container">
      <div style="display: flex; align-items: center; gap: 15px;">
        <div style="display: flex; align-items: center; gap: 10px; cursor: pointer;" onclick="window.location.href='index.php'">
          <div style="width: 40px; height: 40px; background-color: var(--primary); border-radius: 50%; display: flex; justify-content: center; align-items: center; font-weight: bold; font-size: 20px; box-shadow: 0 0 10px var(--primary-glow); color: #fff;">C</div>
          <div style="margin-left: 10px;">
            <h2 style="font-size: 18px; line-height: 1.1;">cportal</h2>
            <span style="font-size: 12px; color: var(--text-secondary);">Tswayi High School</span>
          </div>
        </div>
        <button class="theme-toggle-btn" onclick="toggleTheme()" aria-label="Toggle Theme">
          <svg class="sun-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
          <svg class="moon-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
        </button>
      </div>
      <div style="display: flex; align-items: center; gap: 20px;">
        <button class="menu-btn" onclick="toggleMenu()" aria-label="Toggle Menu">
          <span></span>
          <span></span>
          <span></span>
        </button>
      </div>
    </div>
  </header>

  <!-- Side Navigation Drawer -->
  <nav class="nav-menu" id="side-nav-menu">
    <a href="index.php">🏠 Home</a>
    <a href="about.php" style="color: var(--text-primary); background-color: rgba(255, 255, 255, 0.05);">📖 About Us</a>
    <a href="register.php">📝 Enrollment</a>
    <a href="events.php">📅 School Events</a>
    <a href="helpdesk.php">🛠 IT Help Desk</a>
    <hr/>
    <?php if (isset($_SESSION['user'])): ?>
      <a href="dashboard.php" style="color: var(--success);">💻 Dashboard</a>
      <a href="logout.php" style="color: var(--error);">🚪 Logout</a>
    <?php else: ?>
      <a href="login.php" style="color: var(--primary-hover);">🔑 Sign In</a>
    <?php endif; ?>
  </nav>

  <!-- Main Content Container -->
  <main>
    <!-- Page Title -->
    <div>
      <h1 style="font-size: 36px; background: linear-gradient(to right, var(--gradient-text-start), var(--gradient-text-end)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">About Our School</h1>
      <p style="color: var(--text-secondary); margin-top: 5px; font-size: 15px;">Discover our background, our mission, and our highly qualified faculty.</p>
    </div>

    <!-- History / General Information -->
    <section class="glass-panel" style="display: flex; flex-direction: column; gap: 20px;">
      <h2>Our History & Philosophy</h2>
      <p style="color: var(--text-secondary); line-height: 1.7; font-size: 15px;">
        Tswayi High School was established with a singular vision: to offer quality secondary education to students in our private community, keeping up with global academic and technological standards. We believe that no school, teacher, or child should be left behind in the digital age. By implementing cportal, we combine rigorous testing parameters and database management with AI tools to elevate student academic performance and parent-teacher communication.
      </p>
      <div class="grid-2" style="margin-top: 15px;">
        <div style="padding: 20px; background: var(--bg-input); border: 1px solid var(--border-color); border-radius: var(--radius-md);">
          <h3 style="color: var(--accent); margin-bottom: 8px;">🔬 Academic Rigor</h3>
          <p style="color: var(--text-secondary); font-size: 14px; line-height: 1.6;">Our curriculum follows standard international guidelines, emphasizing critical logical reasoning, experimental sciences, and advanced math modeling.</p>
        </div>
        <div style="padding: 20px; background: var(--bg-input); border: 1px solid var(--border-color); border-radius: var(--radius-md);">
          <h3 style="color: var(--primary-hover); margin-bottom: 8px;">💡 AI & IT Training</h3>
          <p style="color: var(--text-secondary); font-size: 14px; line-height: 1.6;">Students receive specialized training in computer science, and interact daily with localized AI study companions to answer study queries and clarify grades.</p>
        </div>
      </div>
    </section>

    <!-- Subjects Offered -->
    <section class="glass-panel" style="display: flex; flex-direction: column; gap: 20px;">
      <h2>Core Curriculum Modules</h2>
      <div class="grid-2">
        <div style="padding: 20px; background: var(--bg-input); border: 1px solid var(--border-color); border-radius: var(--radius-md);">
          <h4>📐 Mathematics</h4>
          <p style="color: var(--text-secondary); font-size: 13.5px; margin-top: 5px; line-height: 1.5;">Covers algebraic equations, calculus basics, probability theory, geometry, and real-world statistical analysis.</p>
        </div>
        <div style="padding: 20px; background: var(--bg-input); border: 1px solid var(--border-color); border-radius: var(--radius-md);">
          <h4>💻 Computer Science</h4>
          <p style="color: var(--text-secondary); font-size: 13.5px; margin-top: 5px; line-height: 1.5;">Algorithms, basic programming (JavaScript, PHP, Python), database management with SQL, and web app structure.</p>
        </div>
        <div style="padding: 20px; background: var(--bg-input); border: 1px solid var(--border-color); border-radius: var(--radius-md);">
          <h4>🧬 Biology</h4>
          <p style="color: var(--text-secondary); font-size: 13.5px; margin-top: 5px; line-height: 1.5;">Cell structures, evolutionary biology, genetics, plant/animal physiology, and environmental ecology systems.</p>
        </div>
        <div style="padding: 20px; background: var(--bg-input); border: 1px solid var(--border-color); border-radius: var(--radius-md);">
          <h4>📚 English Language & Literature</h4>
          <p style="color: var(--text-secondary); font-size: 13.5px; margin-top: 5px; line-height: 1.5;">Advanced composition writing, logical speech articulation, grammatical rules, and literary text reviews.</p>
        </div>
      </div>
    </section>

    <!-- Faculty Members -->
    <section class="glass-panel" style="display: flex; flex-direction: column; gap: 20px;">
      <h2>Meet Our Faculty</h2>
      <div class="grid-3">
        <div style="padding: 20px; background: var(--bg-input); border: 1px solid var(--border-color); border-radius: var(--radius-md); display: flex; align-items: center; gap: 15px;">
          <div style="width: 50px; height: 50px; background-color: var(--primary); border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 20px; font-weight: bold;">MN</div>
          <div>
            <h4 style="font-size: 16px;">Mr. Mphathisi Ndlovu</h4>
            <p style="color: var(--text-secondary); font-size: 13px; margin-top: 2px;">Faculty of Mathematics</p>
          </div>
        </div>
        <div style="padding: 20px; background: var(--bg-input); border: 1px solid var(--border-color); border-radius: var(--radius-md); display: flex; align-items: center; gap: 15px;">
          <div style="width: 50px; height: 50px; background-color: var(--accent); border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 20px; font-weight: bold;">MD</div>
          <div>
            <h4 style="font-size: 16px;">Mrs. Melissa Dube</h4>
            <p style="color: var(--text-secondary); font-size: 13px; margin-top: 2px;">Faculty of English</p>
          </div>
        </div>
        <div style="padding: 20px; background: var(--bg-input); border: 1px solid var(--border-color); border-radius: var(--radius-md); display: flex; align-items: center; gap: 15px;">
          <div style="width: 50px; height: 50px; background-color: var(--success); border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 20px; font-weight: bold;">EK</div>
          <div>
            <h4 style="font-size: 16px;">Mr. Ezra Kufazvineyi</h4>
            <p style="color: var(--text-secondary); font-size: 13px; margin-top: 2px;">Faculty of Science</p>
          </div>
        </div>
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
        <span style="font-size: 10px; color: var(--text-secondary);">Powered by IT Help Desk</span>
      </div>
      <button onclick="toggleAiChat()" style="background: none; border: none; color: var(--text-secondary); cursor: pointer; font-size: 18px;">✕</button>
    </div>
    
    <div class="ai-body" id="ai-messages-container">
      <div class="ai-msg ai-msg-assistant">
        Hello! I can explain Tswayi High School's curriculum and history. What would you like to know?
      </div>
    </div>

    <form class="ai-input-form" onsubmit="sendAiMessage(event)">
      <input type="text" id="ai-chat-input" placeholder="Ask AI about our school..." required autocomplete="off">
      <button type="submit" class="ai-send-btn">➔</button>
    </form>
  </div>

  <script src="script.js"></script>
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
<!-- Future Improvements: Make the faculty section dynamic by loading teachers' bios, subjects, and profiles directly from the database. -->
</html>
