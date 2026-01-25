<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken) ?>">
    <title>BWD Blog Platform</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/main.css">
    <style>
        /* Claude Panel Styles */
        .claude-panel {
            position: fixed;
            right: 0;
            top: var(--header-height);
            bottom: 0;
            width: 420px;
            background: var(--bg-secondary);
            border-left: 1px solid var(--border-default);
            display: flex;
            flex-direction: column;
            transform: translateX(100%);
            transition: transform 0.3s ease;
            z-index: 100;
        }
        
        .claude-panel.open {
            transform: translateX(0);
        }
        
        .claude-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-default);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .claude-title {
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .claude-indicator {
            width: 8px;
            height: 8px;
            background: var(--status-published);
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        .claude-close {
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            font-size: 20px;
        }
        
        .claude-messages {
            flex: 1;
            overflow-y: auto;
            padding: 24px;
        }
        
        .claude-message {
            margin-bottom: 20px;
        }
        
        .claude-message-user {
            background: var(--bg-tertiary);
            padding: 12px 16px;
            margin-left: 40px;
        }
        
        .claude-message-assistant {
            padding: 12px 0;
        }
        
        .claude-message-assistant p {
            margin-bottom: 12px;
            line-height: 1.6;
        }
        
        .claude-input-area {
            padding: 16px 24px;
            border-top: 1px solid var(--border-default);
        }
        
        .claude-quick-actions {
            display: flex;
            gap: 8px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }
        
        .claude-quick-btn {
            padding: 6px 12px;
            background: var(--bg-tertiary);
            border: 1px solid var(--border-default);
            color: var(--text-secondary);
            font-size: 11px;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        
        .claude-quick-btn:hover {
            color: var(--text-primary);
            border-color: var(--border-hover);
        }
        
        .claude-input-wrapper {
            display: flex;
            gap: 8px;
        }
        
        #claude-input {
            flex: 1;
            background: var(--bg-tertiary);
            border: 1px solid var(--border-default);
            padding: 12px 16px;
            color: var(--text-primary);
            font-family: var(--font-main);
            font-size: 13px;
            resize: none;
        }
        
        #claude-input:focus {
            outline: none;
            border-color: var(--text-primary);
        }
        
        .claude-send {
            padding: 12px 16px;
            background: var(--text-primary);
            border: none;
            color: var(--bg-primary);
            cursor: pointer;
            font-weight: 500;
        }
        
        .claude-float-btn {
            position: fixed;
            bottom: 24px;
            right: 24px;
            width: 56px;
            height: 56px;
            background: var(--text-primary);
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.5);
            transition: all 0.2s ease;
            z-index: 50;
        }
        
        .claude-float-btn:hover {
            transform: scale(1.05);
        }
        
        .claude-float-btn svg {
            stroke: var(--bg-primary);
            width: 24px;
            height: 24px;
        }
    </style>
</head>
<body>
    <div class="app">
        <!-- Header -->
        <header class="header">
            <div class="logo">
                <div class="logo-mark">BWD</div>
                <div class="logo-text">Blog Platform <span id="current-view-title">/ Dashboard</span></div>
            </div>
            <div class="header-actions">
                <div class="sync-status" id="sync-status">
                    <span class="sync-dot"></span>
                    <span>Ready</span>
                </div>
                <div class="user-menu">
                    <div style="width: 24px; height: 24px; background: linear-gradient(135deg, #333 0%, #666 100%); border-radius: 50%;"></div>
                    <span><?= htmlspecialchars($user['name'] ?? 'Admin') ?></span>
                </div>
            </div>
        </header>

        <!-- Sidebar -->
        <nav class="sidebar">
            <div class="nav-section">
                <div class="nav-section-title">Content</div>
                <button class="nav-item active" data-navigate="/">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7"/>
                        <rect x="14" y="3" width="7" height="7"/>
                        <rect x="14" y="14" width="7" height="7"/>
                        <rect x="3" y="14" width="7" height="7"/>
                    </svg>
                    Dashboard
                </button>
                <button class="nav-item" data-navigate="/posts">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                        <line x1="16" y1="13" x2="8" y2="13"/>
                        <line x1="16" y1="17" x2="8" y2="17"/>
                    </svg>
                    Posts
                    <span class="nav-badge" id="posts-count">0</span>
                </button>
                <button class="nav-item" data-navigate="/roadmap">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                        <line x1="16" y1="2" x2="16" y2="6"/>
                        <line x1="8" y1="2" x2="8" y2="6"/>
                        <line x1="3" y1="10" x2="21" y2="10"/>
                    </svg>
                    Roadmap
                </button>
                <button class="nav-item" data-navigate="/brainstorm">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                    </svg>
                    Brainstorm
                    <span class="nav-badge" id="ideas-count">0</span>
                </button>
                <button class="nav-item" data-navigate="/products">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                    </svg>
                    Products
                </button>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">AI Tools</div>
                <button class="nav-item" onclick="Claude.toggle()">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M8 14s1.5 2 4 2 4-2 4-2"/>
                        <line x1="9" y1="9" x2="9.01" y2="9"/>
                        <line x1="15" y1="9" x2="15.01" y2="9"/>
                    </svg>
                    Claude Assistant
                </button>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">Settings</div>
                <button class="nav-item" data-navigate="/settings">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="3"/>
                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                    </svg>
                    Configuration
                </button>
                <button class="nav-item" data-navigate="/settings/brand-voice">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 20h9"/>
                        <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>
                    </svg>
                    Brand Voice
                </button>
                <button class="nav-item" data-navigate="/settings/sync">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="23 4 23 10 17 10"/>
                        <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
                    </svg>
                    Sync Data
                </button>
            </div>
            
            <!-- Logout at bottom -->
            <div style="margin-top: auto; padding-top: 16px; border-top: 1px solid var(--border-default);">
                <button class="nav-item" onclick="App.logout()">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                        <polyline points="16 17 21 12 16 7"/>
                        <line x1="21" y1="12" x2="9" y2="12"/>
                    </svg>
                    Logout
                </button>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="main" id="main-content">
            <div class="loading">
                <div class="spinner"></div>
            </div>
        </main>
    </div>

    <!-- Claude AI Panel -->
    <div class="claude-panel" id="claude-panel">
        <div class="claude-header">
            <span class="claude-title">
                <span class="claude-indicator"></span>
                Claude Assistant
            </span>
            <button class="claude-close" onclick="Claude.close()">×</button>
        </div>
        <div class="claude-messages" id="claude-messages">
            <div style="color: var(--text-secondary); text-align: center; padding: 40px 20px;">
                <p style="margin-bottom: 12px;">👋 Hi! I'm here to help with your blog content.</p>
                <p style="font-size: 12px;">Try asking me to brainstorm ideas, improve your writing, or suggest products.</p>
            </div>
        </div>
        <div class="claude-input-area">
            <div class="claude-quick-actions">
                <button class="claude-quick-btn" onclick="Claude.send('Give me 5 blog ideas for this month')">💡 Blog Ideas</button>
                <button class="claude-quick-btn" onclick="Claude.send('What content should I create for Valentine\'s Day?')">💝 Valentine's</button>
                <button class="claude-quick-btn" onclick="Claude.send('Suggest trending topics for fashion blogs')">📈 Trending</button>
            </div>
            <div class="claude-input-wrapper">
                <textarea id="claude-input" placeholder="Ask Claude anything..." rows="2" onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();Claude.send(this.value);}"></textarea>
                <button class="claude-send" onclick="Claude.send(document.getElementById('claude-input').value)">→</button>
            </div>
        </div>
    </div>

    <!-- Floating Claude Button -->
    <button class="claude-float-btn" onclick="Claude.toggle()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"/>
            <path d="M8 14s1.5 2 4 2 4-2 4-2"/>
            <line x1="9" y1="9" x2="9.01" y2="9"/>
            <line x1="15" y1="9" x2="15.01" y2="9"/>
        </svg>
    </button>

    <!-- Toast Container -->
    <div id="toast-container" class="toast-container"></div>

    <script src="/assets/js/app.js"></script>
    <script>
        // Logout function
        App.logout = async function() {
            try {
                await this.api('/auth/logout', { method: 'POST' });
                window.location.href = '/login';
            } catch (e) {
                window.location.href = '/login';
            }
        };
    </script>
    <!-- Claude AI Assistant Panel -->
<div id="claude-panel" style="position:fixed;top:0;right:-400px;width:400px;height:100vh;background:var(--bg-secondary);border-left:1px solid var(--border-default);z-index:1001;transition:right 0.3s ease;display:flex;flex-direction:column;">
    <div style="padding:16px 20px;border-bottom:1px solid var(--border-default);display:flex;justify-content:space-between;align-items:center;">
        <span style="font-weight:600;">Claude Assistant</span>
        <button onclick="Claude.close()" style="background:none;border:none;color:var(--text-secondary);cursor:pointer;font-size:20px;">&times;</button>
    </div>
    <div id="claude-messages" style="flex:1;overflow-y:auto;padding:20px;"></div>
    <div style="padding:16px 20px;border-top:1px solid var(--border-default);">
        <div style="display:flex;gap:8px;">
            <input type="text" id="claude-input" class="form-input" placeholder="Ask Claude to refine your post..." onkeypress="if(event.key==='Enter')Claude.send(this.value)" style="flex:1;">
            <button id="claude-send" class="btn btn-primary" onclick="Claude.send(document.getElementById('claude-input').value)">Send</button>
        </div>
    </div>
</div>

<style>
#claude-panel.open { right: 0; }
</style>
</body>
</html>
