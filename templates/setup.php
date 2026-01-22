<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken) ?>">
    <title>Setup - BWD Blog Platform</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #0A0A0A;
            --bg-secondary: #111111;
            --bg-tertiary: #1A1A1A;
            --border-default: #252525;
            --text-primary: #FFFFFF;
            --text-secondary: #888888;
            --text-muted: #555555;
            --status-error: #EF4444;
            --status-success: #22C55E;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
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
        
        .setup-container {
            width: 100%;
            max-width: 480px;
        }
        
        .setup-header {
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
        
        .setup-title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .setup-subtitle {
            color: var(--text-secondary);
            font-size: 14px;
            line-height: 1.5;
        }
        
        .setup-form {
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
        
        .form-hint {
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 6px;
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
        
        .btn:hover { opacity: 0.9; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        
        .error-message {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid var(--status-error);
            color: var(--status-error);
            padding: 12px 16px;
            margin-bottom: 20px;
            font-size: 13px;
            display: none;
        }
        
        .error-message.visible { display: block; }
        
        .setup-steps {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-bottom: 32px;
        }
        
        .step {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 600;
            background: var(--bg-tertiary);
            color: var(--text-muted);
        }
        
        .step.active {
            background: var(--text-primary);
            color: var(--bg-primary);
        }
        
        .step.complete {
            background: var(--status-success);
            color: white;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="setup-header">
            <div class="logo-mark">BWD</div>
            <h1 class="setup-title">Welcome to BWD Blog Platform</h1>
            <p class="setup-subtitle">Let's set up your admin account to get started.</p>
        </div>
        
        <div class="setup-steps">
            <div class="step active">1</div>
            <div class="step">2</div>
            <div class="step">3</div>
        </div>
        
        <form class="setup-form" id="setup-form">
            <div class="error-message" id="error-message"></div>
            
            <div class="form-group">
                <label class="form-label" for="name">Your Name</label>
                <input type="text" id="name" name="name" class="form-input" placeholder="John Smith" required autofocus>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="email">Email Address</label>
                <input type="email" id="email" name="email" class="form-input" placeholder="you@blackwhitedenim.com" required>
                <p class="form-hint">This will be used for login and notifications</p>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input type="password" id="password" name="password" class="form-input" placeholder="••••••••" required minlength="8">
                <p class="form-hint">Minimum 8 characters</p>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-input" placeholder="••••••••" required>
            </div>
            
            <button type="submit" class="btn" id="submit-btn">Create Account & Continue</button>
        </form>
    </div>
    
    <script>
        const form = document.getElementById('setup-form');
        const errorEl = document.getElementById('error-message');
        const submitBtn = document.getElementById('submit-btn');
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            errorEl.classList.remove('visible');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Creating account...';
            
            const formData = {
                name: document.getElementById('name').value,
                email: document.getElementById('email').value,
                password: document.getElementById('password').value,
                confirm_password: document.getElementById('confirm_password').value
            };
            
            try {
                const response = await fetch('/api/setup/create', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify(formData)
                });
                
                const data = await response.json();
                
                if (data.success) {
                    window.location.href = '/';
                } else {
                    throw new Error(data.error?.message || 'Setup failed');
                }
            } catch (error) {
                errorEl.textContent = error.message;
                errorEl.classList.add('visible');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Create Account & Continue';
            }
        });
    </script>
</body>
</html>
