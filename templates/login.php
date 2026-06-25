<!DOCTYPE html>
<html lang="ru" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VPN Panel - Вход</title>
    <style>
        :root {
            --bg: #f7fafc;
            --card-bg: #ffffff;
            --text: #2d3748;
            --text-secondary: #4a5568;
            --border: #e2e8f0;
            --primary: #667eea;
            --primary-hover: #5a67d8;
            --error-bg: #fed7d7;
            --error-text: #c53030;
            --input-bg: #ffffff;
            --shadow: rgba(0,0,0,0.1);
        }
        
        [data-theme="dark"] {
            --bg: #1a202c;
            --card-bg: #2d3748;
            --text: #e2e8f0;
            --text-secondary: #a0aec0;
            --border: #4a5568;
            --primary: #7f9cf5;
            --primary-hover: #667eea;
            --error-bg: #742a2a;
            --error-text: #feb2b2;
            --input-bg: #1a202c;
            --shadow: rgba(0,0,0,0.4);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, var(--primary) 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.3s;
        }
        
        .theme-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 50px;
            padding: 10px 20px;
            cursor: pointer;
            font-size: 18px;
            box-shadow: 0 2px 8px var(--shadow);
            transition: all 0.3s;
            z-index: 1000;
        }
        
        .theme-toggle:hover {
            transform: scale(1.05);
        }
        
        .login-box {
            background: var(--card-bg);
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 20px 60px var(--shadow);
            width: 100%;
            max-width: 400px;
            transition: all 0.3s;
        }
        
        h1 {
            text-align: center;
            margin-bottom: 30px;
            color: var(--text);
            transition: color 0.3s;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 14px;
            transition: color 0.3s;
        }
        
        input {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 16px;
            background: var(--input-bg);
            color: var(--text);
            transition: all 0.3s;
        }
        
        input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }
        
        button {
            width: 100%;
            padding: 14px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        button:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
        }
        
        .error {
            background: var(--error-bg);
            color: var(--error-text);
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
        }
        
        .info {
            text-align: center;
            color: var(--text-secondary);
            font-size: 13px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <button class="theme-toggle" onclick="toggleTheme()" title="Переключить тему">
        <span id="themeIcon">🌙</span>
    </button>
    
    <div class="login-box">
        <h1>🔐 VPN Master Panel</h1>
        <?php if (isset($error) && $error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" required autofocus placeholder="admin@vpn.local" 
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Пароль</label>
                <input type="password" name="password" required placeholder="••••••••">
            </div>
            <button type="submit">Войти</button>
        </form>
        <div class="info">По умолчанию: admin@vpn.local / admin123</div>
    </div>
    
    <script>
        // Загружаем сохранённую тему
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);
        updateThemeIcon();
        
        function toggleTheme() {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeIcon();
        }
        
        function updateThemeIcon() {
            const theme = document.documentElement.getAttribute('data-theme');
            document.getElementById('themeIcon').textContent = theme === 'dark' ? '☀️' : '🌙';
        }
    </script>
</body>
</html>
