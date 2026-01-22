/**
 * BWD Blog Platform - Main Application
 * 
 * Single Page Application handling all dashboard functionality
 */

// ==================== CONFIGURATION ====================
const App = {
    csrfToken: null,
    user: null,
    currentView: 'dashboard',
    
    // API helper
    async api(endpoint, options = {}) {
        const url = `/api${endpoint}`;
        const config = {
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': this.csrfToken
            },
            ...options
        };
        
        if (config.body && typeof config.body === 'object') {
            config.body = JSON.stringify(config.body);
        }
        
        try {
            const response = await fetch(url, config);
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.error?.message || 'An error occurred');
            }
            
            return data.data;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    },
    
    // Toast notifications
    toast(message, type = 'success') {
        const container = document.getElementById('toast-container') || this.createToastContainer();
        
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `
            <span>${message}</span>
            <button onclick="this.parentElement.remove()" style="background:none;border:none;color:var(--text-secondary);cursor:pointer;margin-left:auto;">×</button>
        `;
        
        container.appendChild(toast);
        
        setTimeout(() => toast.remove(), 5000);
    },
    
    createToastContainer() {
        const container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'toast-container';
        document.body.appendChild(container);
        return container;
    },
    
    // Loading state
    setLoading(element, loading = true) {
        if (loading) {
            element.classList.add('loading');
            element.dataset.originalContent = element.innerHTML;
            element.innerHTML = '<div class="spinner"></div>';
        } else {
            element.classList.remove('loading');
            element.innerHTML = element.dataset.originalContent || '';
        }
    },
    
    // Initialize application
    async init() {
        // Get CSRF token from meta tag
        this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        
        // Check session
        try {
            const session = await this.api('/auth/session');
            if (session.authenticated) {
                this.user = session.user;
                this.csrfToken = session.csrf_token;
            }
        } catch (e) {
            console.error('Session check failed:', e);
        }
        
        // Setup navigation
        this.setupNavigation();
        
        // Load initial view based on URL
        this.handleRoute();
        
        // Listen for browser navigation
        window.addEventListener('popstate', () => this.handleRoute());
    },
    
    // Navigation setup
    setupNavigation() {
        document.querySelectorAll('[data-navigate]').forEach(el => {
            el.addEventListener('click', (e) => {
                e.preventDefault();
                const path = el.dataset.navigate;
                this.navigate(path);
            });
        });
    },
    
    // Navigate to path
    navigate(path) {
        window.history.pushState({}, '', path);
        this.handleRoute();
    },
    
    // Handle current route
    handleRoute() {
        const path = window.location.pathname;
        
        // Update active nav item
        document.querySelectorAll('.nav-item').forEach(el => {
            el.classList.remove('active');
            if (el.dataset.navigate === path) {
                el.classList.add('active');
            }
        });
        
        // Load view
        if (path === '/' || path === '/dashboard') {
            this.loadDashboard();
        } else if (path === '/posts') {
            this.loadPosts();
        } else if (path === '/posts/new') {
            this.loadPostEditor();
        } else if (path.match(/^\/posts\/\d+$/)) {
            const id = path.split('/')[2];
            this.loadPostEditor(id);
        } else if (path === '/roadmap') {
            this.loadRoadmap();
        } else if (path === '/brainstorm') {
            this.loadBrainstorm();
        } else if (path === '/products') {
            this.loadProducts();
        } else if (path === '/settings') {
            this.loadSettings();
        } else if (path === '/settings/brand-voice') {
            this.loadBrandVoice();
        } else if (path === '/settings/sync') {
            this.loadSync();
        }
    }
};

// ==================== VIEW LOADERS ====================

App.loadDashboard = async function() {
    const main = document.getElementById('main-content');
    main.innerHTML = '<div class="loading"><div class="spinner"></div></div>';
    
    try {
        const [stats, posts, activity] = await Promise.all([
            this.api('/stats/dashboard'),
            this.api('/posts?status=scheduled,review,draft&limit=5'),
            this.api('/activity?limit=5')
        ]);
        
        main.innerHTML = `
            <div class="page-header">
                <div>
                    <h1 class="page-title">Dashboard</h1>
                    <p class="page-subtitle">${new Date().toLocaleDateString('en-GB', { month: 'long', year: 'numeric' })} Overview</p>
                </div>
                <div style="display: flex; gap: 12px;">
                    <button class="btn btn-secondary" onclick="Claude.open()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <path d="M8 14s1.5 2 4 2 4-2 4-2"/>
                            <line x1="9" y1="9" x2="9.01" y2="9"/>
                            <line x1="15" y1="9" x2="15.01" y2="9"/>
                        </svg>
                        Ask Claude
                    </button>
                    <button class="btn btn-primary" onclick="App.navigate('/posts/new')">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"/>
                            <line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        New Post
                    </button>
                </div>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Published This Month</div>
                    <div class="stat-value">${stats.published_this_month || 0}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Scheduled</div>
                    <div class="stat-value">${stats.scheduled || 0}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">In Draft</div>
                    <div class="stat-value">${stats.draft || 0}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Ideas</div>
                    <div class="stat-value">${stats.ideas || 0}</div>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
                <div class="card">
                    <div class="card-header">
                        <span class="card-title">Upcoming Posts</span>
                        <a href="/posts" onclick="event.preventDefault(); App.navigate('/posts');" style="font-size: 12px; color: var(--text-secondary);">View All →</a>
                    </div>
                    <div class="table-container">
                        <table class="data-table">
                            <tbody>
                                ${posts.length ? posts.map(post => `
                                    <tr onclick="App.navigate('/posts/${post.id}')" style="cursor: pointer;">
                                        <td>
                                            <div style="font-weight: 500;">${this.escapeHtml(post.title)}</div>
                                            <div style="font-size: 12px; color: var(--text-secondary);">${post.section_count || 0} sections</div>
                                        </td>
                                        <td style="color: var(--text-secondary);">${post.scheduled_date ? new Date(post.scheduled_date).toLocaleDateString('en-GB', { month: 'short', day: 'numeric' }) : '—'}</td>
                                        <td><span class="status-badge status-${post.status}"><span class="status-dot"></span> ${post.status}</span></td>
                                    </tr>
                                `).join('') : '<tr><td colspan="3" class="empty-state">No posts yet</td></tr>'}
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <span class="card-title">Recent Activity</span>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        ${activity.length ? activity.map(item => `
                            <div style="padding: 16px 24px; border-bottom: 1px solid var(--border-default); display: flex; gap: 12px;">
                                <div style="font-size: 18px;">${this.getActivityIcon(item.action)}</div>
                                <div>
                                    <div style="font-size: 13px;">${this.escapeHtml(item.description || item.action)}</div>
                                    <div style="font-size: 11px; color: var(--text-muted);">${this.timeAgo(item.created_at)}</div>
                                </div>
                            </div>
                        `).join('') : '<div class="empty-state">No recent activity</div>'}
                    </div>
                </div>
            </div>
        `;
    } catch (error) {
        main.innerHTML = `<div class="empty-state"><div class="empty-state-title">Error loading dashboard</div><p>${error.message}</p></div>`;
    }
};

App.loadPosts = async function() {
    const main = document.getElementById('main-content');
    main.innerHTML = '<div class="loading"><div class="spinner"></div></div>';
    
    try {
        const posts = await this.api('/posts');
        
        main.innerHTML = `
            <div class="page-header">
                <div>
                    <h1 class="page-title">Posts</h1>
                    <p class="page-subtitle">Manage your blog content</p>
                </div>
                <button class="btn btn-primary" onclick="App.navigate('/posts/new')">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    New Post
                </button>
            </div>
            
            <div class="tabs">
                <div class="tab active" data-filter="all">All (${posts.length})</div>
                <div class="tab" data-filter="published">Published</div>
                <div class="tab" data-filter="scheduled">Scheduled</div>
                <div class="tab" data-filter="draft">Draft</div>
                <div class="tab" data-filter="idea">Ideas</div>
            </div>
            
            <div class="card">
                <div class="table-container">
                    <table class="data-table" id="posts-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Date</th>
                                <th>Event</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${posts.map(post => `
                                <tr data-status="${post.status}" onclick="App.navigate('/posts/${post.id}')" style="cursor: pointer;">
                                    <td>
                                        <div style="font-weight: 500;">${this.escapeHtml(post.title)}</div>
                                        <div style="font-size: 12px; color: var(--text-secondary);">${post.section_count || 0} sections</div>
                                    </td>
                                    <td style="color: var(--text-secondary);">
                                        ${post.scheduled_date ? new Date(post.scheduled_date).toLocaleDateString('en-GB', { month: 'short', day: 'numeric', year: 'numeric' }) : '—'}
                                    </td>
                                    <td>${post.event_name ? `<span style="font-size: 12px; padding: 4px 10px; background: var(--bg-tertiary);">${this.escapeHtml(post.event_name)}</span>` : '—'}</td>
                                    <td><span class="status-badge status-${post.status}"><span class="status-dot"></span> ${post.status}</span></td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
        
        // Tab filtering
        document.querySelectorAll('.tab[data-filter]').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                
                const filter = tab.dataset.filter;
                document.querySelectorAll('#posts-table tbody tr').forEach(row => {
                    if (filter === 'all' || row.dataset.status === filter) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        });
    } catch (error) {
        main.innerHTML = `<div class="empty-state"><div class="empty-state-title">Error loading posts</div><p>${error.message}</p></div>`;
    }
};

// ==================== UTILITY FUNCTIONS ====================

App.escapeHtml = function(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
};

App.timeAgo = function(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const seconds = Math.floor((now - date) / 1000);
    
    if (seconds < 60) return 'Just now';
    if (seconds < 3600) return `${Math.floor(seconds / 60)} min ago`;
    if (seconds < 86400) return `${Math.floor(seconds / 3600)} hours ago`;
    if (seconds < 604800) return `${Math.floor(seconds / 86400)} days ago`;
    return date.toLocaleDateString('en-GB');
};

App.getActivityIcon = function(action) {
    const icons = {
        'post_created': '📝',
        'post_updated': '✏️',
        'post_published': '✅',
        'post_scheduled': '📅',
        'ai_generated': '🤖',
        'sync_completed': '🔄',
        'login_success': '👤',
        'default': '📌'
    };
    return icons[action] || icons.default;
};

// ==================== CLAUDE AI PANEL ====================

const Claude = {
    isOpen: false,
    messages: [],
    
    open() {
        const panel = document.getElementById('claude-panel');
        if (panel) {
            panel.classList.add('open');
            this.isOpen = true;
        }
    },
    
    close() {
        const panel = document.getElementById('claude-panel');
        if (panel) {
            panel.classList.remove('open');
            this.isOpen = false;
        }
    },
    
    toggle() {
        this.isOpen ? this.close() : this.open();
    },
    
    async send(message) {
        if (!message.trim()) return;
        
        // Add user message
        this.messages.push({ role: 'user', content: message });
        this.renderMessages();
        
        // Clear input
        const input = document.getElementById('claude-input');
        if (input) input.value = '';
        
        // Get AI response
        try {
            const response = await App.api('/claude/chat', {
                method: 'POST',
                body: { message }
            });
            
            this.messages.push({ role: 'assistant', content: response });
            this.renderMessages();
        } catch (error) {
            App.toast(error.message, 'error');
        }
    },
    
    renderMessages() {
        const container = document.getElementById('claude-messages');
        if (!container) return;
        
        container.innerHTML = this.messages.map(msg => `
            <div class="claude-message claude-message-${msg.role}">
                ${msg.role === 'assistant' ? this.formatAiResponse(msg.content) : App.escapeHtml(msg.content)}
            </div>
        `).join('');
        
        container.scrollTop = container.scrollHeight;
    },
    
    formatAiResponse(content) {
        // Basic markdown-like formatting
        let html = App.escapeHtml(content);
        html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        html = html.replace(/\n\n/g, '</p><p>');
        html = html.replace(/\n/g, '<br>');
        return `<p>${html}</p>`;
    }
};

// ==================== INITIALIZE ====================

document.addEventListener('DOMContentLoaded', () => {
    App.init();
});

// Export for global access
window.App = App;
window.Claude = Claude;
