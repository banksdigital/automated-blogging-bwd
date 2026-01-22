<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken) ?>">
    <title>Login - BWD Blog Platform</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #0A0A0A;
            --bg-secondary: #111111;
            --bg-tertiary: #1A1A1A;
            --border-default: #252525;
            --border-hover: #333333;
            --text-primary: #FFFFFF;
            --text-secondary: #888888;
            --text-muted: #555555;
            --status-error: #EF4444;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            width: 100%;
            max-width: 400px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .logo-mark {
            width: 60px;
            height: 60px;
            background: var(--text-primary);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: var(--bg-primary);
            font-size: 18px;
            margin-bottom: 24px;
        }
        
        .login-title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .login-subtitle {
            color: var(--text-secondary);
            font-size: 14px;
        }
        
        .login-form {
            background: var(--bg-secondary);
            border: 1px solid var(--border-default);
            padding: 32px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-secondary);
            margin-bottom: 8px;
        }
        
        .form-input {
            width: 100%;
            padding: 12px 16px;
            background: var(--bg-tertiary);
            border: 1px solid var(--border-default);
            color: var(--text-primary);
            font-family: inherit;
            font-size: 14px;
            transition: border-color 0.15s ease;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--text-primary);
        }
        
        .form-input::placeholder {
            color: var(--text-muted);
        }
        
        .btn {
            width: 100%;
            padding: 14px 20px;
            background: var(--text-primary);
            color: var(--bg-primary);
            border: none;
            font-family: inherit;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.15s ease;
        }
        
        .btn:hover {
            opacity: 0.9;
        }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .error-message {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid var(--status-error);
            color: var(--status-error);
            padding: 12px 16px;
            margin-bottom: 20px;
            font-size: 13px;
            display: none;
        }
        
        .error-message.visible {
            display: block;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="logo-mark">BWD</div>
            <h1 class="login-title">Blog Platform</h1>
            <p class="login-subtitle">Sign in to manage your content</p>
        </div>
        
        <form class="login-form" id="login-form">
            <div class="error-message" id="error-message"></div>
            
            <div class="form-group">
                <label class="form-label" for="email">Email</label>
                <input type="email" id="email" name="email" class="form-input" placeholder="you@example.com" required autofocus>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input type="password" id="password" name="password" class="form-input" placeholder="••••••••" required>
            </div>
            
            <button type="submit" class="btn" id="submit-btn">Sign In</button>
        </form>
    </div>
    
    <script>
        const form = document.getElementById('login-form');
        const errorEl = document.getElementById('error-message');
        const submitBtn = document.getElementById('submit-btn');
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            errorEl.classList.remove('visible');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Signing in...';
            
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            
            try {
                const response = await fetch('/api/auth/login', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify({ email, password })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    window.location.href = '/';
                } else {
                    throw new Error(data.error?.message || 'Login failed');
                }
            } catch (error) {
                errorEl.textContent = error.message;
                errorEl.classList.add('visible');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Sign In';
            }
        });
    </script>
</body>
</html>
