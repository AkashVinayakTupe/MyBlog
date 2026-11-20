<?php
// config.php
// All settings and keys live ONLY in this file.
// Never share this file. Never upload it to GitHub.

// ---------- Site ----------
define('APP_NAME', 'My Blog');
define('SITE_URL', 'http://localhost/myblog'); // no slash at the end
define('APP_DEBUG', true);                     // true on your PC. Set false on live hosting.

// ---------- Database ----------
define('DB_HOST', 'localhost');
define('DB_NAME', 'myblog');
define('DB_USER', 'root');   // XAMPP/Laragon default
define('DB_PASS', '');       // XAMPP/Laragon default is empty. Use a strong one on live hosting.

// ---------- Gemini ----------

// pest here key
define('GEMINI_MODEL', 'gemini-3.5-flash');   // Change ONLY here. Verify with the test page.
define('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta');
define('GEMINI_TIMEOUT', 120);                // seconds for one call
define('GEMINI_MAX_RETRIES', 3);              // retries for 429 and 5xx errors

// ---------- Logging ----------
define('LOG_FILE', __DIR__ . '/logs/app.log');

// ---------- Time ----------
date_default_timezone_set('Asia/Kolkata');