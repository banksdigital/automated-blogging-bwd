/**
 * BWD Blog Platform - Main Application
 */

const App = {
    csrfToken: null,
    user: null,
    
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
    
    // Initialize application
    async init() {
        this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        
        try {
            const session = await this.api('/auth/session');
            if (session.authenticated) {
                this.user = session.user;
                this.csrfToken = session.csrf_token;
            }
        } catch (e) {
            console.error('Session check failed:', e);
        }
        
        this.setupNavigation();
        this.handleRoute();
        window.addEventListener('popstate', () => this.handleRoute());
    },
    
    setupNavigation() {
        document.querySelectorAll('[data-navigate]').forEach(el => {
            el.addEventListener('click', (e) => {
                e.preventDefault();
                const path = el.dataset.navigate;
                this.navigate(path);
            });
        });
    },
    
    navigate(path) {
        window.history.pushState({}, '', path);
        this.handleRoute();
    },
    
    handleRoute() {
        const path = window.location.pathname;
        
        document.querySelectorAll('.nav-item').forEach(el => {
            el.classList.remove('active');
            if (el.dataset.navigate === path) {
                el.classList.add('active');
            }
        });
        
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
        } else if (path === '/calendar-events') {
            this.loadCalendarEvents();
        } else if (path === '/taxonomy-seo') {
            this.loadTaxonomySeo();
        } else if (path === '/edit-manager') {
            this.loadEditManager();
        } else if (path === '/brainstorm') {
            this.loadBrainstorm();
        } else if (path === '/products') {
            this.loadProducts();
        } else if (path === '/autopilot') {
            this.loadAutoPilot();
        } else if (path === '/settings') {
            this.loadSettings();
        } else if (path === '/settings/defaults') {
            this.loadDefaultSettings();
        } else if (path === '/settings/brand-voice') {
            this.loadBrandVoice();
        } else if (path === '/settings/writing-guidelines') {
            this.loadWritingGuidelines();
        } else if (path === '/settings/sync') {
            this.loadSync();
        } else if (path === '/settings/maintenance') {
            this.loadMaintenance();
        }
    },

    // ==================== DASHBOARD ====================
    async loadDashboard() {
        const main = document.getElementById('main-content');
        main.innerHTML = '<div class="loading"><div class="spinner"></div></div>';
        
        try {
            const [stats, contentStats, reviewQueue, posts] = await Promise.all([
                this.api('/stats/dashboard'),
                this.api('/content/stats').catch(() => ({ pending_generation: 0, awaiting_review: 0, scheduled: 0, publishing_this_week: 0 })),
                this.api('/content/review-queue').catch(() => []),
                this.api('/posts?status=scheduled&limit=5')
            ]);
            
            main.innerHTML = `
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Content Dashboard</h1>
                        <p class="page-subtitle">${new Date().toLocaleDateString('en-GB', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' })}</p>
                    </div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <button class="btn btn-secondary" onclick="App.navigate('/edit-manager')">✨ Edit Manager</button>
                        <button class="btn btn-secondary" onclick="App.navigate('/taxonomy-seo')">🔍 Taxonomy SEO</button>
                        <button class="btn btn-secondary" onclick="App.navigate('/calendar-events')">📅 Calendar Events</button>
                        <button class="btn btn-secondary" onclick="App.navigate('/autopilot')">⚙ Auto-Pilot</button>
                        <button class="btn btn-primary" onclick="App.navigate('/posts/new')">+ New Post</button>
                    </div>
                </div>
                
                <!-- Auto-Pilot Status -->
                <div class="card" style="margin-bottom:24px;border-left:4px solid var(--accent-primary);">
                    <div class="card-body">
                        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
                            <div>
                                <div style="font-weight:600;font-size:16px;">🤖 Auto-Pilot Status</div>
                                <div style="font-size:13px;color:var(--text-secondary);margin-top:4px;">
                                    ${contentStats.awaiting_review > 0 
                                        ? `<span style="color:var(--status-review);">●</span> ${contentStats.awaiting_review} posts awaiting your review` 
                                        : '<span style="color:var(--status-published);">●</span> All caught up!'}
                                </div>
                            </div>
                            <div style="display:flex;gap:8px;align-items:center;">
                                <select id="autopilot-weeks" class="form-input" style="width:auto;padding:8px 12px;">
                                    <option value="1">1 week</option>
                                    <option value="2">2 weeks</option>
                                    <option value="3" selected>3 weeks</option>
                                    <option value="4">4 weeks</option>
                                    <option value="6">6 weeks</option>
                                    <option value="8">8 weeks</option>
                                </select>
                                <button class="btn btn-secondary" onclick="App.previewAutoPilot()">👁️ Preview</button>
                                <button class="btn btn-primary" onclick="App.runAutoPilot()" id="generate-btn">▶ Run Auto-Pilot</button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="stats-grid">
                    <div class="stat-card" style="cursor:pointer;" onclick="App.navigate('/posts?status=review')">
                        <div class="stat-label">Awaiting Review</div>
                        <div class="stat-value" style="color:${contentStats.awaiting_review > 0 ? 'var(--status-review)' : 'inherit'};">${contentStats.awaiting_review || 0}</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Scheduled</div>
                        <div class="stat-value">${contentStats.scheduled || stats.scheduled || 0}</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Publishing This Week</div>
                        <div class="stat-value">${contentStats.publishing_this_week || 0}</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Published This Month</div>
                        <div class="stat-value">${stats.published_this_month || 0}</div>
                    </div>
                </div>
                
                <!-- Review Queue -->
                ${reviewQueue.length > 0 ? `
                <div class="card" style="margin-bottom:24px;">
                    <div class="card-header">
                        <span class="card-title">📝 Review Queue</span>
                        <span style="font-size:12px;color:var(--text-secondary);">AI-generated posts awaiting your approval</span>
                    </div>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Post</th>
                                    <th>Type</th>
                                    <th>Publish Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${reviewQueue.map(post => `
                                    <tr>
                                        <td>
                                            <div style="font-weight:500;">${this.escapeHtml(post.title)}</div>
                                            <div style="font-size:12px;color:var(--text-secondary);">${post.event_name || post.template_name || 'Manual'}</div>
                                        </td>
                                        <td><span class="badge">${post.content_type || 'post'}</span></td>
                                        <td>${post.target_publish_date ? new Date(post.target_publish_date).toLocaleDateString('en-GB', { month: 'short', day: 'numeric' }) : '—'}</td>
                                        <td>
                                            <div style="display:flex;gap:8px;">
                                                <button class="btn btn-secondary btn-sm" onclick="App.navigate('/posts/${post.id}')">Review</button>
                                                <button class="btn btn-primary btn-sm" onclick="App.approvePost(${post.id})">Approve</button>
                                            </div>
                                        </td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
                ` : ''}
                
                <!-- Upcoming Scheduled Posts -->
                <div class="card">
                    <div class="card-header">
                        <span class="card-title">📅 Upcoming Posts</span>
                        <a href="/roadmap" onclick="event.preventDefault(); App.navigate('/roadmap');" style="font-size:12px;color:var(--text-secondary);">View Roadmap →</a>
                    </div>
                    <div class="table-container">
                        <table class="data-table">
                            <tbody>
                                ${posts.length ? posts.map(post => `
                                    <tr onclick="App.navigate('/posts/${post.id}')" style="cursor:pointer;">
                                        <td>
                                            <div style="font-weight:500;">${this.escapeHtml(post.title)}</div>
                                            <div style="font-size:12px;color:var(--text-secondary);">${post.section_count || 0} sections</div>
                                        </td>
                                        <td style="color:var(--text-secondary);">${post.scheduled_date ? new Date(post.scheduled_date).toLocaleDateString('en-GB', { weekday: 'short', month: 'short', day: 'numeric' }) : '—'}</td>
                                        <td><span class="status-badge status-${post.status}"><span class="status-dot"></span> ${post.status}</span></td>
                                    </tr>
                                `).join('') : '<tr><td colspan="3" style="text-align:center;padding:40px;color:var(--text-secondary);">No scheduled posts. Generate some content!</td></tr>'}
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
        } catch (error) {
            main.innerHTML = `<div class="empty-state"><div class="empty-state-title">Error loading dashboard</div><p>${error.message}</p></div>`;
        }
    },
    
    async previewAutoPilot() {
        const weeks = document.getElementById('autopilot-weeks')?.value || 3;
        
        try {
            const preview = await this.api('/content/preview-autopilot', { 
                method: 'POST',
                body: JSON.stringify({ weeks: parseInt(weeks) })
            });
            
            // Build preview modal content
            let eventsHtml = '';
            if (preview.events && preview.events.length > 0) {
                eventsHtml = `
                    <div style="margin-bottom:16px;">
                        <strong>📅 Upcoming Events (${preview.events.length}):</strong>
                        <div style="margin-top:8px;display:flex;flex-wrap:wrap;gap:8px;">
                            ${preview.events.map(e => `
                                <span style="background:var(--status-review);color:white;padding:4px 10px;border-radius:4px;font-size:12px;">
                                    ${this.escapeHtml(e.name)} (${new Date(e.start_date).toLocaleDateString()})
                                </span>
                            `).join('')}
                        </div>
                    </div>
                `;
            }
            
            let itemsHtml = '';
            if (preview.items && preview.items.length > 0) {
                itemsHtml = `
                    <div style="max-height:300px;overflow-y:auto;">
                        <table style="width:100%;border-collapse:collapse;font-size:13px;">
                            <thead>
                                <tr style="border-bottom:1px solid var(--border-default);">
                                    <th style="text-align:left;padding:8px;">Publish Date</th>
                                    <th style="text-align:left;padding:8px;">Template</th>
                                    <th style="text-align:left;padding:8px;">Event</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${preview.items.map(item => `
                                    <tr style="border-bottom:1px solid var(--border-default);">
                                        <td style="padding:8px;">${new Date(item.target_publish_date).toLocaleDateString()}</td>
                                        <td style="padding:8px;">${this.escapeHtml(item.template_name)}</td>
                                        <td style="padding:8px;">${item.event_name ? this.escapeHtml(item.event_name) : '<span style="color:var(--text-muted);">—</span>'}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                `;
            } else {
                itemsHtml = '<p style="color:var(--text-muted);text-align:center;padding:20px;">No pending content to generate for this period.</p>';
            }
            
            // Show modal
            const modal = document.createElement('div');
            modal.id = 'autopilot-preview-modal';
            modal.innerHTML = `
                <div style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.7);display:flex;align-items:center;justify-content:center;z-index:9999;">
                    <div style="background:var(--bg-card);padding:32px;border-radius:8px;width:100%;max-width:700px;max-height:90vh;overflow-y:auto;">
                        <h2 style="margin-bottom:16px;">Auto-Pilot Preview (${weeks} weeks)</h2>
                        
                        <div style="background:var(--bg-tertiary);padding:16px;border-radius:8px;margin-bottom:20px;">
                            <div style="font-size:24px;font-weight:600;color:var(--status-published);">${preview.total_pending}</div>
                            <div style="color:var(--text-secondary);">posts will be generated</div>
                        </div>
                        
                        ${eventsHtml}
                        ${itemsHtml}
                        
                        <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:24px;">
                            <button class="btn btn-secondary" onclick="document.getElementById('autopilot-preview-modal').remove()">Cancel</button>
                            ${preview.total_pending > 0 ? `
                                <button class="btn btn-primary" onclick="document.getElementById('autopilot-preview-modal').remove(); App.runAutoPilot();">
                                    ▶ Generate ${preview.total_pending} Posts
                                </button>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            
        } catch (error) {
            this.toast(error.message, 'error');
        }
    },

    async runAutoPilot() {
        const weeks = document.getElementById('autopilot-weeks')?.value || 3;
        const btn = document.getElementById('generate-btn');
        btn.disabled = true;
        btn.textContent = '⏳ Starting...';
        
        // Show loading overlay with status updates
        const overlay = document.createElement('div');
        overlay.id = 'autopilot-loading-overlay';
        overlay.innerHTML = `
            <div style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.7);display:flex;align-items:center;justify-content:center;z-index:9999;">
                <div style="background:var(--bg-card);padding:40px 60px;border-radius:8px;text-align:center;min-width:400px;">
                    <div class="spinner" style="margin:0 auto 20px;"></div>
                    <div style="font-size:18px;font-weight:600;margin-bottom:8px;">Auto-Pilot Running</div>
                    <div id="autopilot-status" style="color:var(--text-secondary);font-size:14px;">Generating content for ${weeks} weeks...</div>
                    <div style="margin-top:20px;background:var(--bg-tertiary);border-radius:4px;padding:16px;text-align:left;">
                        <div id="autopilot-log" style="font-size:12px;color:var(--text-muted);max-height:150px;overflow-y:auto;">
                            <div>⏳ Starting auto-pilot for ${weeks} weeks...</div>
                        </div>
                    </div>
                    <div style="color:var(--text-muted);font-size:12px;margin-top:16px;">This may take 30-60 seconds per post</div>
                </div>
            </div>
        `;
        document.body.appendChild(overlay);
        
        const updateStatus = (status, log) => {
            const statusEl = document.getElementById('autopilot-status');
            const logEl = document.getElementById('autopilot-log');
            if (statusEl) statusEl.textContent = status;
            if (logEl && log) {
                logEl.innerHTML += `<div>${log}</div>`;
                logEl.scrollTop = logEl.scrollHeight;
            }
        };
        
        try {
            updateStatus('Finding scheduled content...', '🔍 Checking scheduled_content table...');
            
            const result = await this.api('/content/generate-pending', { 
                method: 'POST',
                body: JSON.stringify({ weeks: parseInt(weeks) })
            });
            
            if (result.generated && result.generated > 0) {
                updateStatus('Complete!', `✅ Generated ${result.generated} of ${result.total} post(s)!`);
            } else {
                updateStatus('Complete!', 'ℹ️ No pending content to generate');
            }
            
            // Brief pause to show completion
            await new Promise(resolve => setTimeout(resolve, 1500));
            
            this.toast(result.message || 'Auto-pilot complete!', 'success');
            this.loadDashboard();
        } catch (error) {
            updateStatus('Error occurred', `❌ ${error.message}`);
            await new Promise(resolve => setTimeout(resolve, 2000));
            this.toast(error.message, 'error');
        } finally {
            const existingOverlay = document.getElementById('autopilot-loading-overlay');
            if (existingOverlay) existingOverlay.remove();
            btn.disabled = false;
            btn.textContent = '▶ Run Auto-Pilot';
        }
    },

    // Keep old function name for backwards compatibility
    async generateContent() {
        return this.runAutoPilot();
    },
    
    async approvePost(postId) {
        try {
            await this.api(`/content/approve/${postId}`, { method: 'POST' });
            this.toast('Post approved and scheduled!', 'success');
            this.loadDashboard();
        } catch (error) {
            this.toast(error.message, 'error');
        }
    },

    // ==================== POSTS ====================
    async loadPosts() {
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
                    <button class="btn btn-primary" onclick="App.navigate('/posts/new')">+ New Post</button>
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
                                    <th style="width:50px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                ${posts.length ? posts.map(post => `
                                    <tr data-status="${post.status}">
                                        <td onclick="App.navigate('/posts/${post.id}')" style="cursor:pointer;">
                                            <div style="font-weight: 500;">${this.escapeHtml(post.title)}</div>
                                            <div style="font-size: 12px; color: var(--text-secondary);">${post.section_count || 0} sections</div>
                                        </td>
                                        <td onclick="App.navigate('/posts/${post.id}')" style="cursor:pointer;color: var(--text-secondary);">
                                            ${post.scheduled_date ? new Date(post.scheduled_date).toLocaleDateString('en-GB', { month: 'short', day: 'numeric', year: 'numeric' }) : '—'}
                                        </td>
                                        <td onclick="App.navigate('/posts/${post.id}')" style="cursor:pointer;">${post.event_name ? `<span style="font-size: 12px; padding: 4px 10px; background: var(--bg-tertiary);">${this.escapeHtml(post.event_name)}</span>` : '—'}</td>
                                        <td onclick="App.navigate('/posts/${post.id}')" style="cursor:pointer;"><span class="status-badge status-${post.status}"><span class="status-dot"></span> ${post.status}</span></td>
                                        <td>
                                            <button class="btn btn-sm" style="background:transparent;color:var(--status-error);padding:6px 8px;" onclick="App.deletePost(${post.id}, event)" title="Delete">🗑️</button>
                                        </td>
                                    </tr>
                                `).join('') : '<tr><td colspan="5" style="text-align:center;padding:40px;color:var(--text-secondary);">No posts yet</td></tr>'}
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
            
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
    },
    
    async deletePost(id, e) {
        e.stopPropagation();
        if (!confirm('Delete this post? This cannot be undone.')) return;
        
        try {
            await this.api(`/posts/${id}`, { method: 'DELETE' });
            this.toast('Post deleted', 'success');
            this.loadPosts();
        } catch (error) {
            this.toast(error.message, 'error');
        }
    },

    // ==================== POST EDITOR ====================
    async loadPostEditor(id = null) {
        const main = document.getElementById('main-content');
        main.innerHTML = '<div class="loading"><div class="spinner"></div></div>';
        
        try {
            const [events, categories, authors, brands, productCategories, defaults] = await Promise.all([
                this.api('/events'),
                this.api('/categories'),
                this.api('/authors'),
                this.api('/products/brands'),
                this.api('/products/categories'),
                this.api('/settings/defaults').catch(() => ({})) // Catch in case defaults not set
            ]);
            
            // Debug: log defaults
            console.log('Loaded defaults:', defaults);
            
            // Store brands and categories for use in renderSection
            this.brandsList = brands;
            this.categoriesList = productCategories;
            
            // Get default values, ensuring they're strings for comparison
            const defaultCategoryId = defaults?.default_category_id ? String(defaults.default_category_id) : '';
            const defaultAuthorId = defaults?.default_author_id ? String(defaults.default_author_id) : '';
            
            console.log('Default category ID:', defaultCategoryId, 'Default author ID:', defaultAuthorId);
            
            let post = {
                title: '',
                intro_content: '',
                outro_content: '',
                meta_description: '',
                status: 'draft',
                seasonal_event_id: '',
                wp_category_id: defaultCategoryId,
                wp_author_id: defaultAuthorId,
                scheduled_date: '',
                sections: []
            };
            
            if (id) {
                post = await this.api(`/posts/${id}`);
            }
            
            const isExistingPost = !!id;
            const isPublished = post.wp_post_id;
            
            // Reset Claude assistant for new post
            Claude.reset();
            
            main.innerHTML = `
                <div class="page-header" ${isPublished ? `data-wp-post-id="${post.wp_post_id}"` : ''}>
                    <div>
                        <h1 class="page-title">${id ? 'Edit Post' : 'New Post'}</h1>
                        ${isPublished ? `<p class="page-subtitle" style="color:var(--status-published);">✓ Published to WordPress (ID: ${post.wp_post_id})</p>` : ''}
                    </div>
                    <div style="display: flex; gap: 12px;">
                        <button class="btn btn-secondary" onclick="App.navigate('/posts')">Cancel</button>
                        <button class="btn btn-primary" onclick="App.savePost(${id || 'null'})">Save Post</button>
                        ${isExistingPost ? `
                            <button class="btn" style="background:var(--status-published);color:white;" onclick="App.publishToWordPress(${id})">
                                ${isPublished ? '↻ Update in WordPress' : '🚀 Publish to WordPress'}
                            </button>
                        ` : ''}
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
                    <div>
                        <div class="card" style="margin-bottom: 24px;">
                            <div class="card-body">
                                <div class="form-group">
                                    <label class="form-label">Title</label>
                                    <input type="text" id="post-title" class="form-input form-input-lg" value="${this.escapeHtml(post.title)}" placeholder="Enter post title...">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Intro Content</label>
                                    <textarea id="post-intro" class="form-input form-textarea" rows="4" placeholder="Opening paragraph to hook readers...">${this.escapeHtml(post.intro_content || '')}</textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Outro Content</label>
                                    <textarea id="post-outro" class="form-input form-textarea" rows="3" placeholder="Closing paragraph...">${this.escapeHtml(post.outro_content || '')}</textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Meta Description <span style="color:var(--text-muted);">(SEO)</span></label>
                                    <textarea id="post-meta" class="form-input" rows="2" maxlength="160" placeholder="Compelling description for search results...">${this.escapeHtml(post.meta_description || '')}</textarea>
                                    <div class="char-count"><span id="meta-count">${(post.meta_description || '').length}</span>/160</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header">
                                <span class="card-title">Sections</span>
                                <button class="btn btn-secondary btn-sm" onclick="App.addSection()">+ Add Section</button>
                            </div>
                            <div class="card-body" id="sections-container">
                                ${post.sections && post.sections.length ? post.sections.map((s, i) => this.renderSection(s, i)).join('') : '<p style="color:var(--text-secondary);text-align:center;padding:20px;">No sections yet. Add a section to structure your post.</p>'}
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <div class="card" style="margin-bottom: 24px;">
                            <div class="card-header">
                                <span class="card-title">Settings</span>
                            </div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label class="form-label">Status</label>
                                    <select id="post-status" class="form-input form-select">
                                        <option value="idea" ${post.status === 'idea' ? 'selected' : ''}>Idea</option>
                                        <option value="draft" ${post.status === 'draft' ? 'selected' : ''}>Draft</option>
                                        <option value="review" ${post.status === 'review' ? 'selected' : ''}>Review</option>
                                        <option value="scheduled" ${post.status === 'scheduled' ? 'selected' : ''}>Scheduled</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Seasonal Event</label>
                                    <select id="post-event" class="form-input form-select">
                                        <option value="">— None —</option>
                                        ${events.map(e => `<option value="${e.id}" ${String(post.seasonal_event_id) === String(e.id) ? 'selected' : ''}>${this.escapeHtml(e.name)}</option>`).join('')}
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Category</label>
                                    <select id="post-category" class="form-input form-select">
                                        <option value="">— Select —</option>
                                        ${categories.map(c => `<option value="${c.id}" ${String(post.wp_category_id) === String(c.id) ? 'selected' : ''}>${this.escapeHtml(c.name)}</option>`).join('')}
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Author</label>
                                    <select id="post-author" class="form-input form-select">
                                        <option value="">— Select —</option>
                                        ${authors.map(a => `<option value="${a.id}" ${String(post.wp_author_id) === String(a.id) ? 'selected' : ''}>${this.escapeHtml(a.name)}</option>`).join('')}
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Scheduled Date</label>
                                    <input type="date" id="post-date" class="form-input" value="${post.scheduled_date || ''}">
                                </div>
                            </div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header">
                                <span class="card-title">AI Assistant</span>
                            </div>
                            <div class="card-body">
                                <button class="btn btn-secondary" style="width:100%;margin-bottom:8px;" onclick="App.aiGeneratePost()">✨ Generate Full Post</button>
                                <button class="btn btn-secondary" style="width:100%;margin-bottom:8px;" onclick="App.aiGenerateMeta()">📝 Generate Meta Description</button>
                                <button class="btn btn-primary" style="width:100%;" onclick="Claude.open(${id || 'null'})">💬 Refine with AI</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('post-meta').addEventListener('input', (e) => {
                document.getElementById('meta-count').textContent = e.target.value.length;
            });
            
        } catch (error) {
            main.innerHTML = `<div class="empty-state"><div class="empty-state-title">Error</div><p>${error.message}</p></div>`;
        }
    },
    
    renderSection(section, index) {
        const brandOptions = (this.brandsList || []).map(b => 
            `<option value="${b.wp_term_id}" ${section.carousel_brand_id == b.wp_term_id ? 'selected' : ''}>${this.escapeHtml(b.brand_name)}</option>`
        ).join('');
        
        const categoryOptions = (this.categoriesList || []).map(c => 
            `<option value="${c.wp_term_id}" ${section.carousel_category_id == c.wp_term_id ? 'selected' : ''}>${this.escapeHtml(c.name)}</option>`
        ).join('');
        
        return `
            <div class="section-item" data-index="${index}" style="border: 1px solid var(--border-default); padding: 20px; margin-bottom: 16px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 12px;">
                    <strong>Section ${index + 1}</strong>
                    <button onclick="App.removeSection(${index})" style="background:none;border:none;color:var(--text-secondary);cursor:pointer;">✕</button>
                </div>
                <div class="form-group">
                    <input type="text" class="form-input section-heading" value="${this.escapeHtml(section.heading || '')}" placeholder="Section heading...">
                </div>
                <div class="form-group">
                    <textarea class="form-input form-textarea section-content" rows="4" placeholder="Section content...">${this.escapeHtml(section.content || '')}</textarea>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                    <input type="text" class="form-input section-cta-text" value="${this.escapeHtml(section.cta_text || '')}" placeholder="CTA text...">
                    <input type="text" class="form-input section-cta-url" value="${this.escapeHtml(section.cta_url || '')}" placeholder="CTA URL...">
                </div>
                <div style="background: var(--bg-secondary); padding: 12px; border-radius: 6px;">
                    <div style="font-size: 12px; font-weight: 600; color: var(--text-secondary); margin-bottom: 8px;">🛍️ Product Carousel</div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div>
                            <label style="font-size: 11px; color: var(--text-muted);">Brand</label>
                            <select class="form-input form-select section-carousel-brand">
                                <option value="">No brand filter</option>
                                ${brandOptions}
                            </select>
                        </div>
                        <div>
                            <label style="font-size: 11px; color: var(--text-muted);">Product Category</label>
                            <select class="form-input form-select section-carousel-category">
                                <option value="">No category filter</option>
                                ${categoryOptions}
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        `;
    },
    
    addSection() {
        const container = document.getElementById('sections-container');
        const sections = container.querySelectorAll('.section-item');
        const index = sections.length;
        
        if (index === 0) {
            container.innerHTML = '';
        }
        
        container.insertAdjacentHTML('beforeend', this.renderSection({}, index));
    },
    
    removeSection(index) {
        const container = document.getElementById('sections-container');
        const section = container.querySelector(`[data-index="${index}"]`);
        if (section) {
            section.remove();
            if (container.children.length === 0) {
                container.innerHTML = '<p style="color:var(--text-secondary);text-align:center;padding:20px;">No sections yet.</p>';
            }
        }
    },
    
    async savePost(id) {
        const sections = [];
        document.querySelectorAll('.section-item').forEach((el, i) => {
            sections.push({
                heading: el.querySelector('.section-heading').value,
                content: el.querySelector('.section-content').value,
                cta_text: el.querySelector('.section-cta-text').value,
                cta_url: el.querySelector('.section-cta-url').value,
                carousel_brand_id: el.querySelector('.section-carousel-brand')?.value || null,
                carousel_category_id: el.querySelector('.section-carousel-category')?.value || null
            });
        });
        
        const data = {
            title: document.getElementById('post-title').value,
            intro_content: document.getElementById('post-intro').value,
            outro_content: document.getElementById('post-outro').value,
            meta_description: document.getElementById('post-meta').value,
            status: document.getElementById('post-status').value,
            seasonal_event_id: document.getElementById('post-event').value || null,
            wp_category_id: document.getElementById('post-category').value || null,
            wp_author_id: document.getElementById('post-author').value || null,
            scheduled_date: document.getElementById('post-date').value || null,
            sections: sections
        };
        
        try {
            if (id) {
                await this.api(`/posts/${id}`, { method: 'PUT', body: data });
            } else {
                const result = await this.api('/posts', { method: 'POST', body: data });
                id = result.id;
            }
            this.toast('Post saved successfully!');
            this.navigate(`/posts/${id}`);
        } catch (error) {
            this.toast(error.message, 'error');
        }
    },
    
    async publishToWordPress(postId) {
        // Show confirmation dialog
        const status = await this.showPublishDialog();
        if (!status) return;
        
        this.toast('Publishing to WordPress...');
        
        try {
            const result = await this.api(`/wordpress/publish/${postId}`, {
                method: 'POST',
                body: { wp_status: status }
            });
            
            this.toast('Published to WordPress!', 'success');
            
            // Show success modal with links
            const modal = document.createElement('div');
            modal.className = 'modal-overlay active';
            modal.innerHTML = `
                <div class="modal" style="max-width:450px;">
                    <div class="modal-header">
                        <h3 class="modal-title">🎉 Published Successfully!</h3>
                    </div>
                    <div class="modal-body">
                        <p style="margin-bottom:16px;">Your post has been sent to WordPress.</p>
                        <div style="display:flex;flex-direction:column;gap:12px;">
                            <a href="${result.edit_url}" target="_blank" class="btn btn-secondary" style="text-align:center;text-decoration:none;">
                                ✏️ Edit in WordPress
                            </a>
                            <a href="${result.view_url}" target="_blank" class="btn btn-primary" style="text-align:center;text-decoration:none;">
                                👁️ View Post
                            </a>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" onclick="this.closest('.modal-overlay').remove()">Close</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            
            // Refresh the page
            this.loadPostEditor(postId);
            
        } catch (error) {
            this.toast(error.message || 'Failed to publish', 'error');
        }
    },
    
    showPublishDialog() {
        return new Promise((resolve) => {
            const modal = document.createElement('div');
            modal.className = 'modal-overlay active';
            modal.innerHTML = `
                <div class="modal" style="max-width:400px;">
                    <div class="modal-header">
                        <h3 class="modal-title">Publish to WordPress</h3>
                    </div>
                    <div class="modal-body">
                        <p style="margin-bottom:16px;">How would you like to publish this post?</p>
                        <div style="display:flex;flex-direction:column;gap:12px;">
                            <button class="btn btn-secondary" onclick="this.closest('.modal-overlay').dataset.result='draft';this.closest('.modal-overlay').remove();">
                                📝 Save as Draft
                            </button>
                            <button class="btn btn-primary" onclick="this.closest('.modal-overlay').dataset.result='publish';this.closest('.modal-overlay').remove();">
                                🚀 Publish Now
                            </button>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn" onclick="this.closest('.modal-overlay').remove();">Cancel</button>
                    </div>
                </div>
            `;
            
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.remove();
                    resolve(null);
                }
            });
            
            const observer = new MutationObserver(() => {
                if (!document.body.contains(modal)) {
                    resolve(modal.dataset.result || null);
                    observer.disconnect();
                }
            });
            observer.observe(document.body, { childList: true });
            
            document.body.appendChild(modal);
        });
    },

    // ==================== PRODUCTS ====================
    currentProductPage: 1,
    currentProductSearch: '',
    currentProductBrand: '',
    productColumns: null,
    
    getProductColumns() {
        if (!this.productColumns) {
            const saved = localStorage.getItem('productColumns');
            this.productColumns = saved ? JSON.parse(saved) : {
                product_id: false,
                image: true,
                title: true,
                sku: true,
                brand: true,
                price: true,
                regular_price: false,
                sale_price: false,
                categories: false,
                stock: true
            };
        }
        return this.productColumns;
    },
    
    saveProductColumns() {
        localStorage.setItem('productColumns', JSON.stringify(this.productColumns));
    },
    
    toggleProductColumn(col) {
        this.productColumns[col] = !this.productColumns[col];
        this.saveProductColumns();
        this.renderProductTable();
    },
    
    async loadProducts(page = 1) {
        const main = document.getElementById('main-content');
        main.innerHTML = '<div class="loading"><div class="spinner"></div></div>';
        
        this.currentProductPage = page;
        const cols = this.getProductColumns();
        
        try {
            const params = new URLSearchParams({
                page: page,
                per_page: 50
            });
            
            if (this.currentProductSearch) {
                params.append('search', this.currentProductSearch);
            }
            if (this.currentProductBrand) {
                params.append('brand', this.currentProductBrand);
            }
            
            const [response, brands] = await Promise.all([
                this.api(`/products?${params}`),
                this.api('/products/brands')
            ]);
            
            this.currentProducts = response.products;
            this.currentPagination = response.pagination;
            this.currentBrands = brands;
            
            main.innerHTML = `
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Products</h1>
                        <p class="page-subtitle">${response.pagination.total} in-stock products synced from WooCommerce</p>
                    </div>
                    <button class="btn btn-secondary" onclick="App.navigate('/settings/sync')">Sync Products</button>
                </div>
                
                <div class="card" style="margin-bottom: 24px;">
                    <div class="card-body">
                        <div style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
                            <input type="text" id="product-search" class="form-input" style="flex:1;min-width:200px;" placeholder="Search products, SKUs, descriptions..." value="${this.escapeHtml(this.currentProductSearch)}">
                            <select id="brand-filter" class="form-input form-select" style="width:200px;">
                                <option value="">All Brands</option>
                                ${brands.map(b => `<option value="${b.brand_slug}" ${this.currentProductBrand === b.brand_slug ? 'selected' : ''}>${this.escapeHtml(b.brand_name)} (${b.product_count})</option>`).join('')}
                            </select>
                            <button class="btn btn-primary" onclick="App.searchProducts()">Search</button>
                            <div style="position:relative;">
                                <button class="btn btn-secondary" onclick="App.toggleColumnMenu()" id="column-btn">⚙ Columns</button>
                                <div id="column-menu" style="display:none;position:absolute;right:0;top:100%;margin-top:4px;background:var(--bg-primary);border:1px solid var(--border-default);border-radius:8px;padding:8px 0;min-width:180px;z-index:100;box-shadow:0 4px 12px rgba(0,0,0,0.15);">
                                    <div style="padding:8px 12px;font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;">Show Columns</div>
                                    <label style="display:flex;align-items:center;gap:8px;padding:8px 12px;cursor:pointer;"><input type="checkbox" ${cols.product_id ? 'checked' : ''} onchange="App.toggleProductColumn('product_id')"> Product ID</label>
                                    <label style="display:flex;align-items:center;gap:8px;padding:8px 12px;cursor:pointer;"><input type="checkbox" ${cols.image ? 'checked' : ''} onchange="App.toggleProductColumn('image')"> Image</label>
                                    <label style="display:flex;align-items:center;gap:8px;padding:8px 12px;cursor:pointer;"><input type="checkbox" ${cols.title ? 'checked' : ''} onchange="App.toggleProductColumn('title')"> Title</label>
                                    <label style="display:flex;align-items:center;gap:8px;padding:8px 12px;cursor:pointer;"><input type="checkbox" ${cols.sku ? 'checked' : ''} onchange="App.toggleProductColumn('sku')"> SKU</label>
                                    <label style="display:flex;align-items:center;gap:8px;padding:8px 12px;cursor:pointer;"><input type="checkbox" ${cols.brand ? 'checked' : ''} onchange="App.toggleProductColumn('brand')"> Brand</label>
                                    <label style="display:flex;align-items:center;gap:8px;padding:8px 12px;cursor:pointer;"><input type="checkbox" ${cols.price ? 'checked' : ''} onchange="App.toggleProductColumn('price')"> Price</label>
                                    <label style="display:flex;align-items:center;gap:8px;padding:8px 12px;cursor:pointer;"><input type="checkbox" ${cols.regular_price ? 'checked' : ''} onchange="App.toggleProductColumn('regular_price')"> Regular Price</label>
                                    <label style="display:flex;align-items:center;gap:8px;padding:8px 12px;cursor:pointer;"><input type="checkbox" ${cols.sale_price ? 'checked' : ''} onchange="App.toggleProductColumn('sale_price')"> Sale Price</label>
                                    <label style="display:flex;align-items:center;gap:8px;padding:8px 12px;cursor:pointer;"><input type="checkbox" ${cols.categories ? 'checked' : ''} onchange="App.toggleProductColumn('categories')"> Categories</label>
                                    <label style="display:flex;align-items:center;gap:8px;padding:8px 12px;cursor:pointer;"><input type="checkbox" ${cols.stock ? 'checked' : ''} onchange="App.toggleProductColumn('stock')"> Stock</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="table-container" id="products-table-container">
                        ${this.renderProductTableHTML()}
                    </div>
                    
                    ${response.pagination.total_pages > 1 ? `
                    <div class="card-body" style="border-top:1px solid var(--border-default);display:flex;justify-content:space-between;align-items:center;">
                        <div style="font-size:13px;color:var(--text-secondary);">
                            Showing ${response.pagination.from}–${response.pagination.to} of ${response.pagination.total} products
                        </div>
                        <div style="display:flex;gap:8px;">
                            <button class="btn btn-secondary" onclick="App.loadProducts(${response.pagination.current_page - 1})" ${response.pagination.current_page <= 1 ? 'disabled' : ''}>← Previous</button>
                            <span style="padding:8px 12px;font-size:13px;">Page ${response.pagination.current_page} of ${response.pagination.total_pages}</span>
                            <button class="btn btn-secondary" onclick="App.loadProducts(${response.pagination.current_page + 1})" ${response.pagination.current_page >= response.pagination.total_pages ? 'disabled' : ''}>Next →</button>
                        </div>
                    </div>
                    ` : ''}
                </div>
            `;
            
            document.getElementById('product-search').addEventListener('keypress', (e) => {
                if (e.key === 'Enter') this.searchProducts();
            });
            
            // Close column menu when clicking outside
            document.addEventListener('click', (e) => {
                const menu = document.getElementById('column-menu');
                const btn = document.getElementById('column-btn');
                if (menu && !menu.contains(e.target) && e.target !== btn) {
                    menu.style.display = 'none';
                }
            });
            
        } catch (error) {
            main.innerHTML = `<div class="empty-state"><div class="empty-state-title">Error</div><p>${error.message}</p></div>`;
        }
    },
    
    toggleColumnMenu() {
        const menu = document.getElementById('column-menu');
        menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
    },
    
    renderProductTableHTML() {
        const cols = this.getProductColumns();
        const products = this.currentProducts || [];
        
        let headers = '';
        if (cols.product_id) headers += '<th>ID</th>';
        if (cols.image || cols.title || cols.sku) headers += '<th>Product</th>';
        if (cols.brand) headers += '<th>Brand</th>';
        if (cols.price) headers += '<th>Price</th>';
        if (cols.regular_price) headers += '<th>Regular</th>';
        if (cols.sale_price) headers += '<th>Sale</th>';
        if (cols.categories) headers += '<th>Categories</th>';
        if (cols.stock) headers += '<th>Stock</th>';
        
        const rows = products.length ? products.map(p => {
            let row = '<tr>';
            if (cols.product_id) row += `<td style="font-family:monospace;font-size:12px;">${p.wc_product_id}</td>`;
            if (cols.image || cols.title || cols.sku) {
                row += '<td><div style="display:flex;align-items:center;gap:12px;">';
                if (cols.image) {
                    row += p.image_url ? `<img src="${p.image_url}" style="width:40px;height:40px;object-fit:cover;">` : '<div style="width:40px;height:40px;background:var(--bg-tertiary);"></div>';
                }
                if (cols.title || cols.sku) {
                    row += '<div>';
                    if (cols.title) row += `<div style="font-weight:500;">${this.escapeHtml(p.title)}</div>`;
                    if (cols.sku) row += `<div style="font-size:11px;color:var(--text-muted);">${p.sku || ''}</div>`;
                    row += '</div>';
                }
                row += '</div></td>';
            }
            if (cols.brand) row += `<td>${this.escapeHtml(p.brand_name || '—')}</td>`;
            if (cols.price) row += `<td>£${p.price || '—'}</td>`;
            if (cols.regular_price) row += `<td>£${p.regular_price || '—'}</td>`;
            if (cols.sale_price) row += `<td>${p.sale_price ? '£' + p.sale_price : '—'}</td>`;
            if (cols.categories) {
                const cats = p.category_names ? JSON.parse(p.category_names) : [];
                row += `<td style="font-size:12px;">${cats.slice(0,2).join(', ')}${cats.length > 2 ? '...' : ''}</td>`;
            }
            if (cols.stock) row += `<td><span style="color:var(--status-published);">●</span> In Stock</td>`;
            row += '</tr>';
            return row;
        }).join('') : `<tr><td colspan="10" style="text-align:center;padding:40px;color:var(--text-secondary);">No products found</td></tr>`;
        
        return `
            <table class="data-table" id="products-table">
                <thead><tr>${headers}</tr></thead>
                <tbody>${rows}</tbody>
            </table>
        `;
    },
    
    renderProductTable() {
        const container = document.getElementById('products-table-container');
        if (container) {
            container.innerHTML = this.renderProductTableHTML();
        }
    },
    
    searchProducts() {
        this.currentProductSearch = document.getElementById('product-search').value;
        this.currentProductBrand = document.getElementById('brand-filter').value;
        this.loadProducts(1);
    },

    // ==================== ROADMAP ====================
    roadmapYear: null,
    roadmapMonth: null,
    roadmapView: 'calendar', // 'calendar' or 'timeline'
    
    async loadRoadmap() {
        const main = document.getElementById('main-content');
        main.innerHTML = '<div class="loading"><div class="spinner"></div></div>';
        
        const now = new Date();
        if (!this.roadmapYear) this.roadmapYear = now.getFullYear();
        if (!this.roadmapMonth) this.roadmapMonth = now.getMonth() + 1;
        
        try {
            const data = await this.api(`/roadmap/${this.roadmapYear}/${this.roadmapMonth}`);
            const upcoming = await this.api('/roadmap/upcoming').catch(() => []);
            
            main.innerHTML = `
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Content Roadmap</h1>
                        <p class="page-subtitle">Plan and visualize your content schedule</p>
                    </div>
                    <div style="display:flex;gap:8px;">
                        <button class="btn btn-secondary" onclick="App.navigate('/calendar-events')">📅 Manage Events</button>
                        <button class="btn btn-primary" onclick="App.navigate('/posts/new')">+ New Post</button>
                    </div>
                </div>
                
                <!-- View Toggle -->
                <div style="display:flex;gap:8px;margin-bottom:24px;">
                    <button class="btn ${this.roadmapView === 'calendar' ? 'btn-primary' : 'btn-secondary'}" onclick="App.setRoadmapView('calendar')">📅 Calendar</button>
                    <button class="btn ${this.roadmapView === 'timeline' ? 'btn-primary' : 'btn-secondary'}" onclick="App.setRoadmapView('timeline')">📋 Timeline</button>
                </div>
                
                ${this.roadmapView === 'calendar' ? this.renderCalendarView(data) : this.renderTimelineView(upcoming)}
            `;
        } catch (error) {
            main.innerHTML = `<div class="empty-state"><div class="empty-state-title">Error</div><p>${error.message}</p></div>`;
        }
    },
    
    setRoadmapView(view) {
        this.roadmapView = view;
        this.loadRoadmap();
    },
    
    changeRoadmapMonth(delta) {
        this.roadmapMonth += delta;
        if (this.roadmapMonth > 12) {
            this.roadmapMonth = 1;
            this.roadmapYear++;
        } else if (this.roadmapMonth < 1) {
            this.roadmapMonth = 12;
            this.roadmapYear--;
        }
        this.loadRoadmap();
    },
    
    goToToday() {
        const now = new Date();
        this.roadmapYear = now.getFullYear();
        this.roadmapMonth = now.getMonth() + 1;
        this.loadRoadmap();
    },
    
    renderCalendarView(data) {
        const monthName = new Date(this.roadmapYear, this.roadmapMonth - 1).toLocaleDateString('en-GB', { month: 'long', year: 'numeric' });
        
        // Calculate days until each event for display
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        const formatEventDate = (event) => {
            if (!event.start_date) return '';
            const startDate = new Date(event.start_date);
            const daysUntil = Math.ceil((startDate - today) / (1000 * 60 * 60 * 24));
            
            if (daysUntil < 0) {
                return '<span style="color:var(--status-published);">Active now</span>';
            } else if (daysUntil === 0) {
                return '<span style="color:var(--status-review);font-weight:600;">Today!</span>';
            } else if (daysUntil <= 7) {
                return `<span style="color:var(--status-review);">${daysUntil} day${daysUntil !== 1 ? 's' : ''} away</span>`;
            } else if (daysUntil <= 14) {
                return `<span style="color:var(--status-scheduled);">${Math.ceil(daysUntil / 7)} weeks away</span>`;
            } else {
                return startDate.toLocaleDateString('en-GB', {day:'numeric', month:'short'});
            }
        };
        
        return `
            ${data.events?.length ? `
            <div class="card" style="margin-bottom:24px;">
                <div class="card-header">
                    <span class="card-title">🎯 Upcoming Events</span>
                    <span style="font-size:12px;color:var(--text-muted);">Next 6 weeks</span>
                </div>
                <div class="card-body" style="display:flex;gap:12px;flex-wrap:wrap;">
                    ${data.events.map(e => `
                        <span style="padding:10px 16px;background:var(--bg-tertiary);font-size:13px;border-left:3px solid var(--status-scheduled);display:flex;flex-direction:column;gap:4px;">
                            <strong>${this.escapeHtml(e.name)}</strong>
                            <span style="font-size:11px;">${formatEventDate(e)}</span>
                        </span>
                    `).join('')}
                </div>
            </div>
            ` : ''}
            
            <div class="card">
                <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
                    <div style="display:flex;align-items:center;gap:16px;">
                        <button class="btn btn-sm btn-secondary" onclick="App.changeRoadmapMonth(-1)">← Prev</button>
                        <span class="card-title" style="min-width:180px;text-align:center;">${monthName}</span>
                        <button class="btn btn-sm btn-secondary" onclick="App.changeRoadmapMonth(1)">Next →</button>
                    </div>
                    <button class="btn btn-sm" onclick="App.goToToday()">Today</button>
                </div>
                <div class="card-body">
                    <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:4px;text-align:center;">
                        <div style="padding:8px;font-weight:600;color:var(--text-secondary);">Mon</div>
                        <div style="padding:8px;font-weight:600;color:var(--text-secondary);">Tue</div>
                        <div style="padding:8px;font-weight:600;color:var(--text-secondary);">Wed</div>
                        <div style="padding:8px;font-weight:600;color:var(--text-secondary);">Thu</div>
                        <div style="padding:8px;font-weight:600;color:var(--text-secondary);">Fri</div>
                        <div style="padding:8px;font-weight:600;color:var(--text-secondary);">Sat</div>
                        <div style="padding:8px;font-weight:600;color:var(--text-secondary);">Sun</div>
                        ${this.renderCalendarDays(data.calendar, this.roadmapYear, this.roadmapMonth)}
                    </div>
                </div>
            </div>
        `;
    },
    
    renderCalendarDays(calendar, year, month) {
        const firstDay = new Date(year, month - 1, 1).getDay();
        const offset = firstDay === 0 ? 6 : firstDay - 1;
        const today = new Date().toISOString().split('T')[0];
        
        let html = '';
        for (let i = 0; i < offset; i++) {
            html += '<div style="padding:8px;background:var(--bg-primary);"></div>';
        }
        
        calendar.forEach(day => {
            const hasPost = day.posts && day.posts.length > 0;
            const isToday = day.date === today;
            const isPast = day.date < today;
            
            html += `
                <div style="padding:8px;min-height:80px;background:${isToday ? 'var(--bg-hover)' : 'var(--bg-card)'};border:1px solid ${isToday ? 'var(--text-primary)' : 'var(--border-default)'};opacity:${isPast ? '0.6' : '1'};">
                    <div style="font-size:12px;color:${isToday ? 'var(--text-primary)' : 'var(--text-secondary)'};font-weight:${isToday ? '600' : '400'};">${day.day}</div>
                    ${hasPost ? day.posts.map(p => `
                        <div onclick="App.navigate('/posts/${p.id}')" style="font-size:10px;margin-top:4px;padding:4px 6px;background:var(--status-${p.status});color:white;cursor:pointer;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;border-radius:2px;" title="${this.escapeHtml(p.title)}">
                            ${this.escapeHtml(p.title.substring(0, 20))}${p.title.length > 20 ? '...' : ''}
                        </div>
                    `).join('') : ''}
                </div>
            `;
        });
        
        return html;
    },
    
    renderTimelineView(upcoming) {
        if (!upcoming || upcoming.length === 0) {
            return `
                <div class="card">
                    <div class="card-body">
                        <div class="empty-state">
                            <div class="empty-state-icon">📋</div>
                            <div class="empty-state-title">No Upcoming Content</div>
                            <p>Schedule some posts or generate content from Auto-pilot to see them here.</p>
                        </div>
                    </div>
                </div>
            `;
        }
        
        // Group by month
        const grouped = {};
        upcoming.forEach(post => {
            const date = new Date(post.scheduled_date);
            const key = date.toLocaleDateString('en-GB', { month: 'long', year: 'numeric' });
            if (!grouped[key]) grouped[key] = [];
            grouped[key].push(post);
        });
        
        let html = '';
        
        Object.entries(grouped).forEach(([month, posts]) => {
            html += `
                <div class="card" style="margin-bottom:24px;">
                    <div class="card-header">
                        <span class="card-title">📅 ${month}</span>
                        <span style="color:var(--text-secondary);font-size:13px;">${posts.length} post${posts.length !== 1 ? 's' : ''}</span>
                    </div>
                    <div class="card-body" style="padding:0;">
                        ${posts.map((post, i) => {
                            const date = new Date(post.scheduled_date);
                            const dayName = date.toLocaleDateString('en-GB', { weekday: 'short' });
                            const dayNum = date.getDate();
                            const isPast = date < new Date();
                            
                            return `
                                <div style="display:flex;border-bottom:${i < posts.length - 1 ? '1px solid var(--border-default)' : 'none'};opacity:${isPast ? '0.6' : '1'};">
                                    <!-- Date Column -->
                                    <div style="width:80px;padding:20px;text-align:center;border-right:1px solid var(--border-default);background:var(--bg-tertiary);">
                                        <div style="font-size:24px;font-weight:600;">${dayNum}</div>
                                        <div style="font-size:12px;color:var(--text-secondary);">${dayName}</div>
                                    </div>
                                    
                                    <!-- Content Column -->
                                    <div style="flex:1;padding:20px;">
                                        <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:8px;">
                                            <div style="font-weight:600;font-size:15px;cursor:pointer;" onclick="App.navigate('/posts/${post.id}')">${this.escapeHtml(post.title)}</div>
                                            <span class="status-badge status-${post.status}"><span class="status-dot"></span> ${post.status}</span>
                                        </div>
                                        
                                        <!-- Targeting Reason -->
                                        <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:12px;">
                                            ${post.event_name ? `
                                                <span style="display:inline-flex;align-items:center;gap:4px;padding:4px 10px;background:var(--status-scheduled);color:white;font-size:11px;border-radius:2px;">
                                                    🎯 ${this.escapeHtml(post.event_name)}
                                                </span>
                                            ` : ''}
                                            ${post.category_name ? `
                                                <span style="display:inline-flex;align-items:center;gap:4px;padding:4px 10px;background:var(--bg-tertiary);font-size:11px;">
                                                    📁 ${this.escapeHtml(post.category_name)}
                                                </span>
                                            ` : ''}
                                            ${post.section_count ? `
                                                <span style="padding:4px 10px;background:var(--bg-tertiary);font-size:11px;">
                                                    ${post.section_count} sections
                                                </span>
                                            ` : ''}
                                        </div>
                                        
                                        ${post.meta_description ? `
                                            <div style="margin-top:12px;font-size:13px;color:var(--text-secondary);line-height:1.5;">
                                                ${this.escapeHtml(post.meta_description.substring(0, 150))}${post.meta_description.length > 150 ? '...' : ''}
                                            </div>
                                        ` : ''}
                                    </div>
                                    
                                    <!-- Timeline Connector -->
                                    <div style="width:40px;position:relative;display:flex;align-items:center;justify-content:center;">
                                        <div style="width:12px;height:12px;background:var(--status-${post.status});border-radius:50%;z-index:1;"></div>
                                        <div style="position:absolute;top:0;bottom:0;left:50%;width:2px;background:var(--border-default);transform:translateX(-50%);"></div>
                                    </div>
                                </div>
                            `;
                        }).join('')}
                    </div>
                </div>
            `;
        });
        
        return html;
    },

    // ==================== CALENDAR EVENTS ====================
    async loadCalendarEvents() {
        const main = document.getElementById('main-content');
        main.innerHTML = '<div class="loading"><div class="spinner"></div></div>';
        
        try {
            const events = await this.api('/events');
            
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            // Categorize events
            const pastEvents = events.filter(e => new Date(e.start_date) < today);
            const upcomingEvents = events.filter(e => new Date(e.start_date) >= today);
            
            main.innerHTML = `
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Calendar Events</h1>
                        <p class="page-subtitle">Manage seasonal events for content generation</p>
                    </div>
                    <button class="btn btn-primary" onclick="App.showAddEventModal()">+ Add Event</button>
                </div>
                
                <div class="card" style="margin-bottom:24px;">
                    <div class="card-header">
                        <span class="card-title">🎯 Upcoming Events</span>
                        <span style="color:var(--text-secondary);font-size:13px;">${upcomingEvents.length} event${upcomingEvents.length !== 1 ? 's' : ''}</span>
                    </div>
                    <div class="card-body">
                        ${upcomingEvents.length ? `
                            <table style="width:100%;border-collapse:collapse;">
                                <thead>
                                    <tr style="border-bottom:1px solid var(--border-default);">
                                        <th style="text-align:left;padding:12px 8px;font-size:12px;color:var(--text-secondary);">Event</th>
                                        <th style="text-align:left;padding:12px 8px;font-size:12px;color:var(--text-secondary);">Start Date</th>
                                        <th style="text-align:left;padding:12px 8px;font-size:12px;color:var(--text-secondary);">End Date</th>
                                        <th style="text-align:center;padding:12px 8px;font-size:12px;color:var(--text-secondary);">Posts</th>
                                        <th style="text-align:right;padding:12px 8px;font-size:12px;color:var(--text-secondary);">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${upcomingEvents.map(e => this.renderEventRow(e, today)).join('')}
                                </tbody>
                            </table>
                        ` : '<p style="text-align:center;color:var(--text-secondary);padding:20px;">No upcoming events. Add some to enable seasonal content generation.</p>'}
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <span class="card-title">📜 Past Events</span>
                        <span style="color:var(--text-secondary);font-size:13px;">${pastEvents.length} event${pastEvents.length !== 1 ? 's' : ''}</span>
                    </div>
                    <div class="card-body">
                        ${pastEvents.length ? `
                            <table style="width:100%;border-collapse:collapse;">
                                <thead>
                                    <tr style="border-bottom:1px solid var(--border-default);">
                                        <th style="text-align:left;padding:12px 8px;font-size:12px;color:var(--text-secondary);">Event</th>
                                        <th style="text-align:left;padding:12px 8px;font-size:12px;color:var(--text-secondary);">Start Date</th>
                                        <th style="text-align:left;padding:12px 8px;font-size:12px;color:var(--text-secondary);">End Date</th>
                                        <th style="text-align:center;padding:12px 8px;font-size:12px;color:var(--text-secondary);">Posts</th>
                                        <th style="text-align:right;padding:12px 8px;font-size:12px;color:var(--text-secondary);">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${pastEvents.map(e => this.renderEventRow(e, today)).join('')}
                                </tbody>
                            </table>
                        ` : '<p style="text-align:center;color:var(--text-secondary);padding:20px;">No past events.</p>'}
                    </div>
                </div>
                
                <!-- Add/Edit Event Modal -->
                <div id="event-modal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.7);z-index:9999;align-items:center;justify-content:center;">
                    <div style="background:var(--bg-card);padding:32px;border-radius:8px;width:100%;max-width:500px;max-height:90vh;overflow-y:auto;">
                        <h2 id="event-modal-title" style="margin-bottom:24px;">Add Event</h2>
                        <input type="hidden" id="event-id">
                        
                        <div class="form-group">
                            <label class="form-label">Event Name *</label>
                            <input type="text" id="event-name" class="form-input" placeholder="e.g., Valentine's Day">
                        </div>
                        
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                            <div class="form-group">
                                <label class="form-label">Start Date *</label>
                                <input type="date" id="event-start-date" class="form-input">
                            </div>
                            <div class="form-group">
                                <label class="form-label">End Date</label>
                                <input type="date" id="event-end-date" class="form-input">
                                <small style="color:var(--text-muted);font-size:11px;">Optional - for multi-day events</small>
                            </div>
                        </div>
                        
                        <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:24px;">
                            <button class="btn btn-secondary" onclick="App.closeEventModal()">Cancel</button>
                            <button class="btn btn-primary" onclick="App.saveEvent()">Save Event</button>
                        </div>
                    </div>
                </div>
            `;
        } catch (error) {
            main.innerHTML = `<div class="empty-state"><div class="empty-state-title">Error</div><p>${error.message}</p></div>`;
        }
    },
    
    renderEventRow(event, today) {
        const startDate = new Date(event.start_date);
        const daysUntil = Math.ceil((startDate - today) / (1000 * 60 * 60 * 24));
        
        let statusBadge = '';
        if (daysUntil < 0) {
            statusBadge = '<span style="font-size:11px;color:var(--text-muted);">Past</span>';
        } else if (daysUntil === 0) {
            statusBadge = '<span style="font-size:11px;padding:2px 8px;background:var(--status-review);color:white;border-radius:4px;">Today!</span>';
        } else if (daysUntil <= 7) {
            statusBadge = `<span style="font-size:11px;padding:2px 8px;background:var(--status-review);color:white;border-radius:4px;">${daysUntil}d away</span>`;
        } else if (daysUntil <= 30) {
            statusBadge = `<span style="font-size:11px;padding:2px 8px;background:var(--status-scheduled);color:white;border-radius:4px;">${Math.ceil(daysUntil/7)}w away</span>`;
        }
        
        return `
            <tr style="border-bottom:1px solid var(--border-default);">
                <td style="padding:12px 8px;">
                    <div style="font-weight:500;">${this.escapeHtml(event.name)}</div>
                    ${statusBadge ? `<div style="margin-top:4px;">${statusBadge}</div>` : ''}
                </td>
                <td style="padding:12px 8px;color:var(--text-secondary);">
                    ${startDate.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })}
                </td>
                <td style="padding:12px 8px;color:var(--text-secondary);">
                    ${event.end_date ? new Date(event.end_date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : '—'}
                </td>
                <td style="padding:12px 8px;text-align:center;">
                    <span style="padding:2px 8px;background:var(--bg-tertiary);border-radius:4px;font-size:12px;">${event.post_count || 0}</span>
                </td>
                <td style="padding:12px 8px;text-align:right;">
                    <button class="btn btn-sm btn-secondary" onclick="App.editEvent(${event.id})" style="margin-right:4px;">Edit</button>
                    <button class="btn btn-sm" style="background:transparent;color:var(--status-error);" onclick="App.deleteEvent(${event.id}, '${this.escapeHtml(event.name).replace(/'/g, "\\'")}')">🗑️</button>
                </td>
            </tr>
        `;
    },
    
    showAddEventModal() {
        document.getElementById('event-modal-title').textContent = 'Add Event';
        document.getElementById('event-id').value = '';
        document.getElementById('event-name').value = '';
        document.getElementById('event-start-date').value = '';
        document.getElementById('event-end-date').value = '';
        document.getElementById('event-modal').style.display = 'flex';
    },
    
    async editEvent(id) {
        try {
            const events = await this.api('/events');
            const event = events.find(e => e.id === id);
            
            if (!event) {
                this.toast('Event not found', 'error');
                return;
            }
            
            document.getElementById('event-modal-title').textContent = 'Edit Event';
            document.getElementById('event-id').value = event.id;
            document.getElementById('event-name').value = event.name || '';
            document.getElementById('event-start-date').value = event.start_date || '';
            document.getElementById('event-end-date').value = event.end_date || '';
            document.getElementById('event-modal').style.display = 'flex';
        } catch (error) {
            this.toast(error.message, 'error');
        }
    },
    
    closeEventModal() {
        document.getElementById('event-modal').style.display = 'none';
    },
    
    async saveEvent() {
        const id = document.getElementById('event-id').value;
        const name = document.getElementById('event-name').value.trim();
        const startDate = document.getElementById('event-start-date').value;
        const endDate = document.getElementById('event-end-date').value;
        
        if (!name) {
            this.toast('Event name is required', 'error');
            return;
        }
        
        if (!startDate) {
            this.toast('Start date is required', 'error');
            return;
        }
        
        const data = {
            name,
            start_date: startDate,
            end_date: endDate || null
        };
        
        try {
            if (id) {
                await this.api(`/events/${id}`, { method: 'PUT', body: data });
                this.toast('Event updated!', 'success');
            } else {
                await this.api('/events', { method: 'POST', body: data });
                this.toast('Event created!', 'success');
            }
            
            this.closeEventModal();
            this.loadCalendarEvents();
        } catch (error) {
            this.toast(error.message, 'error');
        }
    },
    
    async deleteEvent(id, name) {
        if (!confirm(`Delete "${name}"? Any posts linked to this event will be unlinked.`)) {
            return;
        }
        
        try {
            await this.api(`/events/${id}`, { method: 'DELETE' });
            this.toast('Event deleted', 'success');
            this.loadCalendarEvents();
        } catch (error) {
            this.toast(error.message, 'error');
        }
    },

    // ==================== TAXONOMY SEO ====================
    taxonomySeoTab: 'brands',
    
    async loadTaxonomySeo() {
        const main = document.getElementById('main-content');
        main.innerHTML = '<div class="loading"><div class="spinner"></div></div>';
        
        try {
            main.innerHTML = `
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Taxonomy SEO</h1>
                        <p class="page-subtitle">Generate and manage SEO content for brands and categories</p>
                    </div>
                    <div>
                        <button class="btn btn-secondary" onclick="App.testTaxonomyApi()">🔧 Test API Connection</button>
                    </div>
                </div>
                
                <!-- Tab Navigation -->
                <div style="display:flex;gap:8px;margin-bottom:24px;">
                    <button class="btn ${this.taxonomySeoTab === 'brands' ? 'btn-primary' : 'btn-secondary'}" onclick="App.setTaxonomySeoTab('brands')">🏷️ Brands</button>
                    <button class="btn ${this.taxonomySeoTab === 'categories' ? 'btn-primary' : 'btn-secondary'}" onclick="App.setTaxonomySeoTab('categories')">📁 Categories</button>
                    <button class="btn ${this.taxonomySeoTab === 'edits' ? 'btn-primary' : 'btn-secondary'}" onclick="App.setTaxonomySeoTab('edits')">✨ Edits</button>
                </div>
                
                <div id="taxonomy-seo-content">
                    <div class="loading"><div class="spinner"></div></div>
                </div>
                
                <!-- Debug Modal -->
                <div id="debug-modal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.7);z-index:9999;align-items:center;justify-content:center;overflow-y:auto;padding:20px;">
                    <div style="background:var(--bg-card);padding:32px;border-radius:8px;width:100%;max-width:800px;margin:auto;">
                        <h2 style="margin-bottom:24px;">🔧 API Debug Results</h2>
                        <pre id="debug-results" style="background:var(--bg-tertiary);padding:16px;border-radius:4px;overflow-x:auto;font-size:12px;max-height:400px;overflow-y:auto;"></pre>
                        <div style="margin-top:16px;text-align:right;">
                            <button class="btn btn-secondary" onclick="document.getElementById('debug-modal').style.display='none'">Close</button>
                        </div>
                    </div>
                </div>
                
                <!-- Edit Modal -->
                <div id="seo-modal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.7);z-index:9999;align-items:center;justify-content:center;overflow-y:auto;padding:20px;">
                    <div style="background:var(--bg-card);padding:32px;border-radius:8px;width:100%;max-width:800px;margin:auto;">
                        <h2 id="seo-modal-title" style="margin-bottom:24px;">Edit SEO Content</h2>
                        <input type="hidden" id="seo-edit-id">
                        <input type="hidden" id="seo-edit-type">
                        
                        <div class="form-group">
                            <label class="form-label">Intro Description <span style="color:var(--text-muted);font-weight:normal;">(50-80 words)</span></label>
                            <textarea id="seo-description" class="form-input form-textarea" rows="4" placeholder="Brief intro paragraph with links to related categories..."></textarea>
                            <small style="color:var(--text-muted);font-size:11px;">Short introduction. Include 1-2 links to parent/related categories.</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">SEO Content <span style="color:var(--text-muted);font-weight:normal;">(200-300 words)</span></label>
                            <textarea id="seo-meta-description" class="form-input form-textarea" rows="10" placeholder="Detailed SEO content with brand/category links..."></textarea>
                            <small style="color:var(--text-muted);font-size:11px;">Detailed SEO content. Include 3-5 links to brands or related categories. <span id="meta-char-count">0</span> words</small>
                        </div>
                        
                        <div id="seo-related-info" style="margin-bottom:20px;padding:16px;background:var(--bg-tertiary);border-radius:8px;"></div>
                        
                        <div style="display:flex;gap:12px;justify-content:space-between;margin-top:24px;">
                            <div style="display:flex;gap:8px;">
                                <button class="btn btn-secondary" onclick="App.pullSeoFromWordPress()">⬇️ Pull from WP</button>
                                <button class="btn" style="background:var(--status-published);color:white;" onclick="App.pushSeoToWordPress()">⬆️ Push to WP</button>
                            </div>
                            <div style="display:flex;gap:8px;">
                                <button class="btn btn-secondary" onclick="App.closeSeoModal()">Cancel</button>
                                <button class="btn btn-primary" onclick="App.saveSeoContent()">Save</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Add word counter for SEO content
            document.getElementById('seo-meta-description').addEventListener('input', (e) => {
                const wordCount = e.target.value.trim() ? e.target.value.trim().split(/\s+/).length : 0;
                document.getElementById('meta-char-count').textContent = wordCount;
            });
            
            this.loadTaxonomySeoTab();
        } catch (error) {
            main.innerHTML = `<div class="empty-state"><div class="empty-state-title">Error</div><p>${error.message}</p></div>`;
        }
    },
    
    setTaxonomySeoTab(tab) {
        this.taxonomySeoTab = tab;
        document.querySelectorAll('.page-header + div .btn').forEach((btn, i) => {
            const isActive = (i === 0 && tab === 'brands') || (i === 1 && tab === 'categories') || (i === 2 && tab === 'edits');
            btn.className = `btn ${isActive ? 'btn-primary' : 'btn-secondary'}`;
        });
        this.loadTaxonomySeoTab();
    },
    
    async loadTaxonomySeoTab() {
        const container = document.getElementById('taxonomy-seo-content');
        container.innerHTML = '<div class="loading"><div class="spinner"></div></div>';
        
        try {
            if (this.taxonomySeoTab === 'brands') {
                const brands = await this.api('/taxonomy-seo/brands');
                container.innerHTML = this.renderBrandsSeoTable(brands);
            } else if (this.taxonomySeoTab === 'categories') {
                const categories = await this.api('/taxonomy-seo/categories');
                container.innerHTML = this.renderCategoriesSeoTable(categories);
            } else if (this.taxonomySeoTab === 'edits') {
                const edits = await this.api('/taxonomy-seo/edits');
                container.innerHTML = this.renderEditsSeoTable(edits);
            }
        } catch (error) {
            container.innerHTML = `<div class="card"><div class="card-body"><p style="color:var(--status-error);">Error: ${error.message}</p></div></div>`;
        }
    },
    
    renderBrandsSeoTable(brands) {
        const withSeo = brands.filter(b => b.seo_description);
        const withoutSeo = brands.filter(b => !b.seo_description);
        
        return `
            <div class="card" style="margin-bottom:24px;">
                <div class="card-header">
                    <span class="card-title">📊 SEO Status</span>
                </div>
                <div class="card-body">
                    <div style="display:flex;gap:24px;">
                        <div><strong style="color:var(--status-published);">${withSeo.length}</strong> brands with SEO content</div>
                        <div><strong style="color:var(--status-review);">${withoutSeo.length}</strong> brands need SEO content</div>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
                    <span class="card-title">🏷️ Brands (${brands.length})</span>
                    <div id="bulk-actions-brands" style="display:none;gap:8px;">
                        <span id="selected-count-brands" style="color:var(--text-secondary);margin-right:8px;">0 selected</span>
                        <button class="btn btn-sm btn-primary" onclick="App.bulkGenerateBrands()">✨ Generate Selected</button>
                        <button class="btn btn-sm" style="background:var(--status-published);color:white;" onclick="App.bulkPushBrands()">⬆️ Push Selected to WP</button>
                    </div>
                </div>
                <div class="card-body" style="padding:0;">
                    <table style="width:100%;border-collapse:collapse;">
                        <thead>
                            <tr style="border-bottom:1px solid var(--border-default);">
                                <th style="text-align:center;padding:12px 8px;width:40px;">
                                    <input type="checkbox" id="select-all-brands" onchange="App.toggleSelectAllBrands(this)">
                                </th>
                                <th style="text-align:left;padding:12px 16px;font-size:12px;color:var(--text-secondary);">Brand</th>
                                <th style="text-align:center;padding:12px 8px;font-size:12px;color:var(--text-secondary);">Products</th>
                                <th style="text-align:center;padding:12px 8px;font-size:12px;color:var(--text-secondary);">Categories</th>
                                <th style="text-align:center;padding:12px 8px;font-size:12px;color:var(--text-secondary);">SEO Status</th>
                                <th style="text-align:right;padding:12px 16px;font-size:12px;color:var(--text-secondary);">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${brands.map(b => `
                                <tr style="border-bottom:1px solid var(--border-default);" data-brand-id="${b.id}">
                                    <td style="padding:12px 8px;text-align:center;">
                                        <input type="checkbox" class="brand-checkbox" value="${b.id}" onchange="App.updateBrandSelection()">
                                    </td>
                                    <td style="padding:12px 16px;">
                                        <div style="font-weight:500;">${this.escapeHtml(b.name)}</div>
                                        <div style="font-size:12px;color:var(--text-secondary);">/brand/${b.slug}/</div>
                                    </td>
                                    <td style="padding:12px 8px;text-align:center;">${b.product_count || 0}</td>
                                    <td style="padding:12px 8px;text-align:center;">${b.category_count || 0}</td>
                                    <td style="padding:12px 8px;text-align:center;">
                                        ${b.seo_description 
                                            ? '<span style="color:var(--status-published);">✓ Has SEO</span>' 
                                            : '<span style="color:var(--text-muted);">—</span>'}
                                    </td>
                                    <td style="padding:12px 16px;text-align:right;">
                                        <button class="btn btn-sm btn-secondary" onclick="App.generateBrandSeo(${b.id})" style="margin-right:4px;">✨ Generate</button>
                                        <button class="btn btn-sm btn-secondary" onclick="App.editBrandSeo(${b.id})">Edit</button>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    },
    
    renderCategoriesSeoTable(categories) {
        const withSeo = categories.filter(c => c.seo_description);
        const withoutSeo = categories.filter(c => !c.seo_description);
        
        return `
            <div class="card" style="margin-bottom:24px;">
                <div class="card-header">
                    <span class="card-title">📊 SEO Status</span>
                </div>
                <div class="card-body">
                    <div style="display:flex;gap:24px;">
                        <div><strong style="color:var(--status-published);">${withSeo.length}</strong> categories with SEO content</div>
                        <div><strong style="color:var(--status-review);">${withoutSeo.length}</strong> categories need SEO content</div>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
                    <span class="card-title">📁 Categories (${categories.length})</span>
                    <div id="bulk-actions-categories" style="display:none;gap:8px;">
                        <span id="selected-count-categories" style="color:var(--text-secondary);margin-right:8px;">0 selected</span>
                        <button class="btn btn-sm btn-primary" onclick="App.bulkGenerateCategories()">✨ Generate Selected</button>
                        <button class="btn btn-sm" style="background:var(--status-published);color:white;" onclick="App.bulkPushCategories()">⬆️ Push Selected to WP</button>
                    </div>
                </div>
                <div class="card-body" style="padding:0;">
                    <table style="width:100%;border-collapse:collapse;">
                        <thead>
                            <tr style="border-bottom:1px solid var(--border-default);">
                                <th style="text-align:center;padding:12px 8px;width:40px;">
                                    <input type="checkbox" id="select-all-categories" onchange="App.toggleSelectAllCategories(this)">
                                </th>
                                <th style="text-align:left;padding:12px 16px;font-size:12px;color:var(--text-secondary);">Category</th>
                                <th style="text-align:center;padding:12px 8px;font-size:12px;color:var(--text-secondary);">Products</th>
                                <th style="text-align:center;padding:12px 8px;font-size:12px;color:var(--text-secondary);">Brands</th>
                                <th style="text-align:center;padding:12px 8px;font-size:12px;color:var(--text-secondary);">SEO Status</th>
                                <th style="text-align:right;padding:12px 16px;font-size:12px;color:var(--text-secondary);">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${categories.map(c => `
                                <tr style="border-bottom:1px solid var(--border-default);${c.parent_id ? 'background:var(--bg-tertiary);' : ''}" data-category-id="${c.id}">
                                    <td style="padding:12px 8px;text-align:center;">
                                        <input type="checkbox" class="category-checkbox" value="${c.id}" onchange="App.updateCategorySelection()">
                                    </td>
                                    <td style="padding:12px 16px;${c.parent_id ? 'padding-left:32px;' : ''}">
                                        <div style="font-weight:500;">${c.parent_id ? '↳ ' : ''}${this.escapeHtml(c.name)}</div>
                                        <div style="font-size:12px;color:var(--text-secondary);">
                                            ${c.parent_name ? `<span style="color:var(--text-muted);">${this.escapeHtml(c.parent_name)} → </span>` : ''}/product-category/${c.slug}/
                                        </div>
                                    </td>
                                    <td style="padding:12px 8px;text-align:center;">${c.product_count || 0}</td>
                                    <td style="padding:12px 8px;text-align:center;">${c.brand_count || 0}</td>
                                    <td style="padding:12px 8px;text-align:center;">
                                        ${c.seo_description 
                                            ? '<span style="color:var(--status-published);">✓ Has SEO</span>' 
                                            : '<span style="color:var(--text-muted);">—</span>'}
                                    </td>
                                    <td style="padding:12px 16px;text-align:right;">
                                        <button class="btn btn-sm btn-secondary" onclick="App.generateCategorySeo(${c.id})" style="margin-right:4px;">✨ Generate</button>
                                        <button class="btn btn-sm btn-secondary" onclick="App.editCategorySeo(${c.id})">Edit</button>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    },
    
    renderEditsSeoTable(edits) {
        const withSeo = edits.filter(e => e.seo_description);
        const withoutSeo = edits.filter(e => !e.seo_description);
        const withProducts = edits.filter(e => e.product_count > 0);
        
        return `
            <div class="card" style="margin-bottom:24px;">
                <div class="card-header">
                    <span class="card-title">📊 Edits SEO Status</span>
                </div>
                <div class="card-body">
                    <div style="display:flex;gap:24px;flex-wrap:wrap;">
                        <div><strong style="color:var(--status-published);">${withSeo.length}</strong> edits with SEO content</div>
                        <div><strong style="color:var(--status-review);">${withoutSeo.length}</strong> edits need SEO content</div>
                        <div><strong>${withProducts.length}</strong> edits have products</div>
                    </div>
                    <p style="margin-top:12px;color:var(--text-muted);font-size:13px;">
                        💡 Edits target specific events/seasons with longer-tail keywords. Only edits with products can have SEO generated.
                    </p>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
                    <span class="card-title">✨ Edits (${edits.length})</span>
                    <div id="bulk-actions-edits" style="display:none;gap:8px;">
                        <span id="selected-count-edits" style="color:var(--text-secondary);margin-right:8px;">0 selected</span>
                        <button class="btn btn-sm btn-primary" onclick="App.bulkGenerateEdits()">✨ Generate Selected</button>
                        <button class="btn btn-sm" style="background:var(--status-published);color:white;" onclick="App.bulkPushEdits()">⬆️ Push Selected to WP</button>
                    </div>
                </div>
                <div class="card-body" style="padding:0;">
                    <table style="width:100%;border-collapse:collapse;">
                        <thead>
                            <tr style="border-bottom:1px solid var(--border-default);">
                                <th style="text-align:center;padding:12px 8px;width:40px;">
                                    <input type="checkbox" id="select-all-edits" onchange="App.toggleSelectAllEdits(this)">
                                </th>
                                <th style="text-align:left;padding:12px 16px;font-size:12px;color:var(--text-secondary);">Edit</th>
                                <th style="text-align:center;padding:12px 8px;font-size:12px;color:var(--text-secondary);">Products</th>
                                <th style="text-align:center;padding:12px 8px;font-size:12px;color:var(--text-secondary);">SEO Status</th>
                                <th style="text-align:right;padding:12px 16px;font-size:12px;color:var(--text-secondary);">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${edits.map(e => `
                                <tr style="border-bottom:1px solid var(--border-default);" data-edit-id="${e.id}">
                                    <td style="padding:12px 8px;text-align:center;">
                                        <input type="checkbox" class="edit-checkbox" value="${e.id}" onchange="App.updateEditSelection()" ${e.product_count === 0 ? 'disabled title="No products in this edit"' : ''}>
                                    </td>
                                    <td style="padding:12px 16px;">
                                        <div style="font-weight:500;">${this.escapeHtml(e.name)}</div>
                                        <div style="font-size:12px;color:var(--text-secondary);">/edit/${e.slug}/</div>
                                    </td>
                                    <td style="padding:12px 8px;text-align:center;">
                                        ${e.product_count > 0 
                                            ? `<span style="color:var(--status-published);">${e.product_count}</span>` 
                                            : '<span style="color:var(--text-muted);">0</span>'}
                                    </td>
                                    <td style="padding:12px 8px;text-align:center;">
                                        ${e.seo_description 
                                            ? '<span style="color:var(--status-published);">✓ Has SEO</span>' 
                                            : '<span style="color:var(--text-muted);">—</span>'}
                                    </td>
                                    <td style="padding:12px 16px;text-align:right;">
                                        ${e.product_count > 0 
                                            ? `<button class="btn btn-sm btn-secondary" onclick="App.generateEditSeo(${e.id})" style="margin-right:4px;">✨ Generate</button>`
                                            : `<button class="btn btn-sm btn-secondary" disabled title="Add products first" style="margin-right:4px;opacity:0.5;">✨ Generate</button>`
                                        }
                                        <button class="btn btn-sm btn-secondary" onclick="App.editEditSeo(${e.id})">Edit</button>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    },
    
    // Bulk selection functions for Edits
    toggleSelectAllEdits(checkbox) {
        const checkboxes = document.querySelectorAll('.edit-checkbox:not(:disabled)');
        checkboxes.forEach(cb => cb.checked = checkbox.checked);
        this.updateEditSelection();
    },
    
    updateEditSelection() {
        const checkboxes = document.querySelectorAll('.edit-checkbox:checked');
        const count = checkboxes.length;
        const bulkActions = document.getElementById('bulk-actions-edits');
        const countSpan = document.getElementById('selected-count-edits');
        
        if (count > 0) {
            bulkActions.style.display = 'flex';
            countSpan.textContent = `${count} selected`;
        } else {
            bulkActions.style.display = 'none';
        }
        
        const allCheckboxes = document.querySelectorAll('.edit-checkbox:not(:disabled)');
        const selectAll = document.getElementById('select-all-edits');
        selectAll.checked = count === allCheckboxes.length && count > 0;
        selectAll.indeterminate = count > 0 && count < allCheckboxes.length;
    },
    
    getSelectedEditIds() {
        return Array.from(document.querySelectorAll('.edit-checkbox:checked')).map(cb => parseInt(cb.value));
    },
    
    async bulkGenerateEdits() {
        const ids = this.getSelectedEditIds();
        if (ids.length === 0) return;
        
        if (!confirm(`Generate SEO content for ${ids.length} edits? This may take a while.`)) return;
        
        this.toast(`Generating SEO for ${ids.length} edits...`, 'info');
        
        let success = 0;
        let failed = 0;
        
        for (const id of ids) {
            try {
                await this.api(`/taxonomy-seo/edits/${id}/generate`, { method: 'POST' });
                success++;
                this.toast(`Generated ${success}/${ids.length}...`, 'info');
            } catch (error) {
                failed++;
                console.error(`Failed to generate edit ${id}:`, error);
            }
        }
        
        this.toast(`Completed: ${success} generated, ${failed} failed`, success > 0 ? 'success' : 'error');
        this.loadTaxonomySeoTab();
    },
    
    async bulkPushEdits() {
        const ids = this.getSelectedEditIds();
        if (ids.length === 0) return;
        
        if (!confirm(`Push SEO content for ${ids.length} edits to WordPress?`)) return;
        
        this.toast(`Pushing ${ids.length} edits to WordPress...`, 'info');
        
        let success = 0;
        let failed = 0;
        
        for (const id of ids) {
            try {
                await this.api(`/taxonomy-seo/edits/${id}/push`, { method: 'POST' });
                success++;
            } catch (error) {
                failed++;
                console.error(`Failed to push edit ${id}:`, error);
            }
        }
        
        this.toast(`Completed: ${success} pushed, ${failed} failed`, success > 0 ? 'success' : 'error');
    },
    
    async generateEditSeo(id) {
        if (!confirm('Generate SEO content for this edit? This will overwrite any existing content.')) return;
        
        const btn = event.target;
        const originalText = btn.textContent;
        btn.textContent = '⏳ Generating...';
        btn.disabled = true;
        
        try {
            const result = await this.api(`/taxonomy-seo/edits/${id}/generate`, { method: 'POST' });
            
            let message = 'Edit SEO generated!';
            if (result.context) {
                const cats = result.context.categories_used?.length || 0;
                const brands = result.context.brands_used?.length || 0;
                message += ` Used ${cats} categories and ${brands} brands.`;
            }
            this.toast(message, 'success');
            
            this.loadTaxonomySeoTab();
        } catch (error) {
            this.toast(error.message, 'error');
            btn.textContent = originalText;
            btn.disabled = false;
        }
    },
    
    async editEditSeo(id) {
        try {
            const edit = await this.api(`/taxonomy-seo/edits/${id}`);
            
            document.getElementById('seo-modal-title').textContent = `Edit SEO: ${edit.name}`;
            document.getElementById('seo-edit-id').value = id;
            document.getElementById('seo-edit-type').value = 'edit';
            document.getElementById('seo-description').value = edit.seo_description || '';
            document.getElementById('seo-meta-description').value = edit.seo_meta_description || '';
            const wordCount = (edit.seo_meta_description || '').trim() ? (edit.seo_meta_description || '').trim().split(/\\s+/).length : 0;
            document.getElementById('meta-char-count').textContent = wordCount;
            
            // Show related info
            let relatedHtml = `<strong>Products in this edit:</strong> ${edit.product_ids?.length || 0}<br><br>`;
            
            if (edit.categories && edit.categories.length > 0) {
                relatedHtml += `<strong>Categories:</strong> ${edit.categories.map(c => `${c.name} (${c.product_count})`).join(', ')}<br><br>`;
            }
            if (edit.brands && edit.brands.length > 0) {
                relatedHtml += `<strong>Brands:</strong> ${edit.brands.map(b => `${b.name} (${b.product_count})`).join(', ')}`;
            }
            
            document.getElementById('seo-related-info').innerHTML = relatedHtml;
            document.getElementById('seo-modal').style.display = 'flex';
        } catch (error) {
            this.toast(error.message, 'error');
        }
    },
    
    async generateBrandSeo(id) {
        if (!confirm('Generate SEO content for this brand? This will overwrite any existing content.')) return;
        
        const btn = event.target;
        const originalText = btn.textContent;
        btn.textContent = '⏳ Generating...';
        btn.disabled = true;
        
        try {
            const result = await this.api(`/taxonomy-seo/brands/${id}/generate`, { method: 'POST' });
            
            // Show context in toast
            let message = 'Brand SEO generated!';
            if (result.context && result.context.categories_used) {
                message += ` Used ${result.context.categories_used.length} categories: ${result.context.categories_used.slice(0, 3).join(', ')}${result.context.categories_used.length > 3 ? '...' : ''}`;
            }
            this.toast(message, 'success');
            console.log('Brand SEO context:', result.context);
            
            this.loadTaxonomySeoTab();
        } catch (error) {
            this.toast(error.message, 'error');
            btn.textContent = originalText;
            btn.disabled = false;
        }
    },
    
    async generateCategorySeo(id) {
        if (!confirm('Generate SEO content for this category? This will overwrite any existing content.')) return;
        
        const btn = event.target;
        const originalText = btn.textContent;
        btn.textContent = '⏳ Generating...';
        btn.disabled = true;
        
        try {
            const result = await this.api(`/taxonomy-seo/categories/${id}/generate`, { method: 'POST' });
            
            // Show context in toast
            let message = 'Category SEO generated!';
            if (result.context && result.context.brands_used) {
                message += ` Used ${result.context.brands_used.length} brands: ${result.context.brands_used.slice(0, 3).join(', ')}${result.context.brands_used.length > 3 ? '...' : ''}`;
            }
            this.toast(message, 'success');
            console.log('Category SEO context:', result.context);
            
            this.loadTaxonomySeoTab();
        } catch (error) {
            this.toast(error.message, 'error');
            btn.textContent = originalText;
            btn.disabled = false;
        }
    },
    
    // Bulk selection functions for Brands
    toggleSelectAllBrands(checkbox) {
        const checkboxes = document.querySelectorAll('.brand-checkbox');
        checkboxes.forEach(cb => cb.checked = checkbox.checked);
        this.updateBrandSelection();
    },
    
    updateBrandSelection() {
        const checkboxes = document.querySelectorAll('.brand-checkbox:checked');
        const count = checkboxes.length;
        const bulkActions = document.getElementById('bulk-actions-brands');
        const countSpan = document.getElementById('selected-count-brands');
        
        if (count > 0) {
            bulkActions.style.display = 'flex';
            countSpan.textContent = `${count} selected`;
        } else {
            bulkActions.style.display = 'none';
        }
        
        // Update select all checkbox state
        const allCheckboxes = document.querySelectorAll('.brand-checkbox');
        const selectAll = document.getElementById('select-all-brands');
        selectAll.checked = count === allCheckboxes.length && count > 0;
        selectAll.indeterminate = count > 0 && count < allCheckboxes.length;
    },
    
    getSelectedBrandIds() {
        return Array.from(document.querySelectorAll('.brand-checkbox:checked')).map(cb => parseInt(cb.value));
    },
    
    async bulkGenerateBrands() {
        const ids = this.getSelectedBrandIds();
        if (ids.length === 0) return;
        
        if (!confirm(`Generate SEO content for ${ids.length} brands? This may take a while.`)) return;
        
        this.toast(`Generating SEO for ${ids.length} brands...`, 'info');
        
        let success = 0;
        let failed = 0;
        
        for (const id of ids) {
            try {
                await this.api(`/taxonomy-seo/brands/${id}/generate`, { method: 'POST' });
                success++;
                this.toast(`Generated ${success}/${ids.length}...`, 'info');
            } catch (error) {
                failed++;
                console.error(`Failed to generate brand ${id}:`, error);
            }
        }
        
        this.toast(`Completed: ${success} generated, ${failed} failed`, success > 0 ? 'success' : 'error');
        this.loadTaxonomySeoTab();
    },
    
    async bulkPushBrands() {
        const ids = this.getSelectedBrandIds();
        if (ids.length === 0) return;
        
        if (!confirm(`Push SEO content for ${ids.length} brands to WordPress?`)) return;
        
        this.toast(`Pushing ${ids.length} brands to WordPress...`, 'info');
        
        let success = 0;
        let failed = 0;
        
        for (const id of ids) {
            try {
                await this.api(`/taxonomy-seo/brands/${id}/push`, { method: 'POST' });
                success++;
            } catch (error) {
                failed++;
                console.error(`Failed to push brand ${id}:`, error);
            }
        }
        
        this.toast(`Completed: ${success} pushed, ${failed} failed`, success > 0 ? 'success' : 'error');
    },
    
    // Bulk selection functions for Categories
    toggleSelectAllCategories(checkbox) {
        const checkboxes = document.querySelectorAll('.category-checkbox');
        checkboxes.forEach(cb => cb.checked = checkbox.checked);
        this.updateCategorySelection();
    },
    
    updateCategorySelection() {
        const checkboxes = document.querySelectorAll('.category-checkbox:checked');
        const count = checkboxes.length;
        const bulkActions = document.getElementById('bulk-actions-categories');
        const countSpan = document.getElementById('selected-count-categories');
        
        if (count > 0) {
            bulkActions.style.display = 'flex';
            countSpan.textContent = `${count} selected`;
        } else {
            bulkActions.style.display = 'none';
        }
        
        // Update select all checkbox state
        const allCheckboxes = document.querySelectorAll('.category-checkbox');
        const selectAll = document.getElementById('select-all-categories');
        selectAll.checked = count === allCheckboxes.length && count > 0;
        selectAll.indeterminate = count > 0 && count < allCheckboxes.length;
    },
    
    getSelectedCategoryIds() {
        return Array.from(document.querySelectorAll('.category-checkbox:checked')).map(cb => parseInt(cb.value));
    },
    
    async bulkGenerateCategories() {
        const ids = this.getSelectedCategoryIds();
        if (ids.length === 0) return;
        
        if (!confirm(`Generate SEO content for ${ids.length} categories? This may take a while.`)) return;
        
        this.toast(`Generating SEO for ${ids.length} categories...`, 'info');
        
        let success = 0;
        let failed = 0;
        
        for (const id of ids) {
            try {
                await this.api(`/taxonomy-seo/categories/${id}/generate`, { method: 'POST' });
                success++;
                this.toast(`Generated ${success}/${ids.length}...`, 'info');
            } catch (error) {
                failed++;
                console.error(`Failed to generate category ${id}:`, error);
            }
        }
        
        this.toast(`Completed: ${success} generated, ${failed} failed`, success > 0 ? 'success' : 'error');
        this.loadTaxonomySeoTab();
    },
    
    async bulkPushCategories() {
        const ids = this.getSelectedCategoryIds();
        if (ids.length === 0) return;
        
        if (!confirm(`Push SEO content for ${ids.length} categories to WordPress?`)) return;
        
        this.toast(`Pushing ${ids.length} categories to WordPress...`, 'info');
        
        let success = 0;
        let failed = 0;
        
        for (const id of ids) {
            try {
                await this.api(`/taxonomy-seo/categories/${id}/push`, { method: 'POST' });
                success++;
            } catch (error) {
                failed++;
                console.error(`Failed to push category ${id}:`, error);
            }
        }
        
        this.toast(`Completed: ${success} pushed, ${failed} failed`, success > 0 ? 'success' : 'error');
    },
    
    async editBrandSeo(id) {
        try {
            const brand = await this.api(`/taxonomy-seo/brands/${id}`);
            
            document.getElementById('seo-modal-title').textContent = `Edit SEO: ${brand.name}`;
            document.getElementById('seo-edit-id').value = id;
            document.getElementById('seo-edit-type').value = 'brand';
            document.getElementById('seo-description').value = brand.seo_description || '';
            document.getElementById('seo-meta-description').value = brand.seo_meta_description || '';
            const wordCount = (brand.seo_meta_description || '').trim() ? (brand.seo_meta_description || '').trim().split(/\s+/).length : 0;
            document.getElementById('meta-char-count').textContent = wordCount;
            
            // Show related categories
            const relatedHtml = brand.categories && brand.categories.length > 0
                ? `<strong>Categories this brand has products in:</strong><br>${brand.categories.map(c => `<a href="/product-category/${c.slug}/" target="_blank">${c.name}</a> (${c.product_count})`).join(', ')}`
                : '<em>No category data available</em>';
            document.getElementById('seo-related-info').innerHTML = relatedHtml;
            
            document.getElementById('seo-modal').style.display = 'flex';
        } catch (error) {
            this.toast(error.message, 'error');
        }
    },
    
    async editCategorySeo(id) {
        try {
            const category = await this.api(`/taxonomy-seo/categories/${id}`);
            
            document.getElementById('seo-modal-title').textContent = `Edit SEO: ${category.name}`;
            document.getElementById('seo-edit-id').value = id;
            document.getElementById('seo-edit-type').value = 'category';
            document.getElementById('seo-description').value = category.seo_description || '';
            document.getElementById('seo-meta-description').value = category.seo_meta_description || '';
            const wordCount = (category.seo_meta_description || '').trim() ? (category.seo_meta_description || '').trim().split(/\s+/).length : 0;
            document.getElementById('meta-char-count').textContent = wordCount;
            
            // Show related brands
            const relatedHtml = category.brands && category.brands.length > 0
                ? `<strong>Brands with products in this category:</strong><br>${category.brands.map(b => `<a href="/brand/${b.slug}/" target="_blank">${b.name}</a> (${b.product_count})`).join(', ')}`
                : '<em>No brand data available</em>';
            document.getElementById('seo-related-info').innerHTML = relatedHtml;
            
            document.getElementById('seo-modal').style.display = 'flex';
        } catch (error) {
            this.toast(error.message, 'error');
        }
    },
    
    closeSeoModal() {
        document.getElementById('seo-modal').style.display = 'none';
    },
    
    async saveSeoContent() {
        const id = document.getElementById('seo-edit-id').value;
        const type = document.getElementById('seo-edit-type').value;
        const description = document.getElementById('seo-description').value;
        const seoContent = document.getElementById('seo-meta-description').value;
        
        // Determine the correct API endpoint
        let endpoint;
        if (type === 'brand') {
            endpoint = 'brands';
        } else if (type === 'category') {
            endpoint = 'categories';
        } else if (type === 'edit') {
            endpoint = 'edits';
        }
        
        try {
            await this.api(`/taxonomy-seo/${endpoint}/${id}`, {
                method: 'PUT',
                body: {
                    description: description,
                    seo_content: seoContent
                }
            });
            this.toast('SEO content saved!', 'success');
            this.closeSeoModal();
            this.loadTaxonomySeoTab();
        } catch (error) {
            this.toast(error.message, 'error');
        }
    },
    
    async pushSeoToWordPress() {
        const id = document.getElementById('seo-edit-id').value;
        const type = document.getElementById('seo-edit-type').value;
        
        if (!confirm('Push SEO content to WordPress? This will update the live site.')) return;
        
        let endpoint;
        if (type === 'brand') {
            endpoint = 'brands';
        } else if (type === 'category') {
            endpoint = 'categories';
        } else if (type === 'edit') {
            endpoint = 'edits';
        }
        
        try {
            await this.api(`/taxonomy-seo/${endpoint}/${id}/push`, { method: 'POST' });
            this.toast('SEO content pushed to WordPress!', 'success');
        } catch (error) {
            this.toast(error.message, 'error');
        }
    },
    
    async pullSeoFromWordPress() {
        const id = document.getElementById('seo-edit-id').value;
        const type = document.getElementById('seo-edit-type').value;
        
        if (!confirm('Pull SEO content from WordPress? This will overwrite local changes.')) return;
        
        let endpoint;
        if (type === 'brand') {
            endpoint = 'brands';
        } else if (type === 'category') {
            endpoint = 'categories';
        } else if (type === 'edit') {
            endpoint = 'edits';
        }
        
        try {
            const result = await this.api(`/taxonomy-seo/${endpoint}/${id}/pull`, { method: 'POST' });
            
            // Update the form with pulled data
            document.getElementById('seo-description').value = result.description || '';
            document.getElementById('seo-meta-description').value = result.meta_description || '';
            const wordCount = (result.meta_description || '').trim() ? (result.meta_description || '').trim().split(/\s+/).length : 0;
            document.getElementById('meta-char-count').textContent = wordCount;
            
            this.toast('SEO content pulled from WordPress!', 'success');
        } catch (error) {
            this.toast(error.message, 'error');
        }
    },
    
    async testTaxonomyApi() {
        const modal = document.getElementById('debug-modal');
        const results = document.getElementById('debug-results');
        
        modal.style.display = 'flex';
        results.textContent = 'Testing WordPress REST API connection...\n\n';
        
        try {
            // Test BRANDS
            results.textContent += '═══ TESTING BRANDS ═══\n\n';
            const brands = await this.api('/taxonomy-seo/brands');
            if (brands.length > 0) {
                const firstBrand = brands[0];
                results.textContent += `Testing brand: "${firstBrand.name}" (wp_term_id: ${firstBrand.wp_term_id})\n`;
                
                const debug = await this.api(`/wordpress/debug/brand/${firstBrand.wp_term_id}`);
                const single = debug.single_endpoint;
                
                results.textContent += `   Endpoint: ${single.url}\n`;
                results.textContent += `   HTTP Status: ${single.http_code}\n`;
                results.textContent += `   Has 'acf' key: ${single.has_acf_key ? '✅ YES' : '❌ NO'}\n`;
                
                if (single.has_acf_key && single.acf_fields) {
                    // Brands use: taxonomy_description, taxonomy_seo_description
                    const hasContent = single.acf_fields.taxonomy_description || single.acf_fields.taxonomy_seo_description;
                    results.textContent += `   Has SEO content: ${hasContent ? '✅ YES' : '⚪ Empty'}\n`;
                    results.textContent += `   Fields: taxonomy_description, taxonomy_seo_description\n`;
                    if (single.acf_fields.taxonomy_description) {
                        results.textContent += `   Description: "${single.acf_fields.taxonomy_description.substring(0, 50)}..."\n`;
                    }
                }
            } else {
                results.textContent += 'No brands in database.\n';
            }
            
            // Test CATEGORIES
            results.textContent += '\n═══ TESTING CATEGORIES ═══\n\n';
            const categories = await this.api('/taxonomy-seo/categories');
            if (categories.length > 0) {
                const firstCat = categories[0];
                results.textContent += `Testing category: "${firstCat.name}" (wp_term_id: ${firstCat.wp_term_id})\n`;
                
                const catDebug = await this.api(`/wordpress/debug/category/${firstCat.wp_term_id}`);
                
                results.textContent += `   Endpoint: ${catDebug.url}\n`;
                results.textContent += `   HTTP Status: ${catDebug.http_code}\n`;
                results.textContent += `   Has 'acf' key: ${catDebug.has_acf_key ? '✅ YES' : '❌ NO'}\n`;
                
                if (catDebug.has_acf_key && catDebug.acf_fields) {
                    // Categories use DIFFERENT fields: category_description, seo_description
                    const hasContent = catDebug.acf_fields.category_description || catDebug.acf_fields.seo_description;
                    results.textContent += `   Has SEO content: ${hasContent ? '✅ YES' : '⚪ Empty'}\n`;
                    results.textContent += `   Fields: category_description, seo_description\n`;
                    if (catDebug.acf_fields.category_description) {
                        results.textContent += `   Description: "${catDebug.acf_fields.category_description.substring(0, 50)}..."\n`;
                    }
                }
            } else {
                results.textContent += 'No categories in database.\n';
            }
            
            results.textContent += '\n═══ SUMMARY ═══\n\n';
            results.textContent += 'If both show ✅ for "Has acf key", the REST API is configured correctly.\n';
            results.textContent += 'If fields show "Empty", the content just needs to be generated.\n';
            results.textContent += '\nField names:\n';
            results.textContent += '  Brands: taxonomy_description, taxonomy_seo_description\n';
            results.textContent += '  Categories: category_description, seo_description';
            
        } catch (error) {
            results.textContent += '\n\nError: ' + error.message + '\n\nCheck the browser console for more details.';
            console.error('testTaxonomyApi error:', error);
        }
    },

    // ==================== EDIT MANAGER ====================
    
    editManagerState: {
        currentEditId: null,
        previewProducts: []
    },
    
    async loadEditManager() {
        const main = document.getElementById('main-content');
        main.innerHTML = '<div class="loading"><div class="spinner"></div></div>';
        
        try {
            const edits = await this.api('/edit-suggestions');
            
            const suggested = edits.filter(e => e.status === 'suggested');
            const approved = edits.filter(e => e.status === 'approved');
            const active = edits.filter(e => e.status === 'active' || e.status === 'created');
            
            main.innerHTML = `
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Edit Manager</h1>
                        <p class="page-subtitle">Create and manage curated product collections for SEO</p>
                    </div>
                    <div style="display:flex;gap:8px;">
                        <button class="btn btn-secondary" onclick="App.generateEditSuggestions()">🔄 Generate Suggestions</button>
                        <button class="btn btn-primary" onclick="App.showCreateEditModal()">+ New Edit</button>
                    </div>
                </div>
                
                <!-- Stats -->
                <div class="stats-grid" style="margin-bottom:24px;">
                    <div class="stat-card">
                        <div class="stat-label">Suggested</div>
                        <div class="stat-value" style="color:var(--text-muted);">${suggested.length}</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Approved</div>
                        <div class="stat-value" style="color:var(--status-review);">${approved.length}</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Active in WP</div>
                        <div class="stat-value" style="color:var(--status-published);">${active.length}</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Total Products</div>
                        <div class="stat-value">${edits.reduce((sum, e) => sum + (e.in_stock_products || 0), 0)}</div>
                    </div>
                </div>
                
                <!-- Active Edits -->
                ${active.length > 0 ? `
                <div class="card" style="margin-bottom:24px;">
                    <div class="card-header">
                        <span class="card-title">🟢 Active Edits (${active.length})</span>
                    </div>
                    <div class="card-body" style="padding:0;">
                        ${this.renderEditTable(active, 'active')}
                    </div>
                </div>
                ` : ''}
                
                <!-- Approved Edits -->
                ${approved.length > 0 ? `
                <div class="card" style="margin-bottom:24px;">
                    <div class="card-header">
                        <span class="card-title">🟡 Approved - Ready to Create (${approved.length})</span>
                    </div>
                    <div class="card-body" style="padding:0;">
                        ${this.renderEditTable(approved, 'approved')}
                    </div>
                </div>
                ` : ''}
                
                <!-- Suggested Edits -->
                <div class="card">
                    <div class="card-header">
                        <span class="card-title">💡 Suggested Edits (${suggested.length})</span>
                        <span style="font-size:12px;color:var(--text-secondary);">Review and approve to activate</span>
                    </div>
                    <div class="card-body" style="padding:0;">
                        ${suggested.length > 0 ? this.renderEditTable(suggested, 'suggested') : `
                            <div style="padding:40px;text-align:center;color:var(--text-muted);">
                                <p>No edit suggestions yet.</p>
                                <button class="btn btn-secondary" onclick="App.generateEditSuggestions()" style="margin-top:12px;">🔄 Generate Suggestions</button>
                            </div>
                        `}
                    </div>
                </div>
                
                <!-- Edit Detail Modal -->
                <div id="edit-detail-modal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.7);z-index:9999;overflow-y:auto;padding:20px;">
                    <div style="background:var(--bg-card);border-radius:8px;width:100%;max-width:1200px;margin:20px auto;max-height:calc(100vh - 40px);display:flex;flex-direction:column;">
                        <div id="edit-detail-content" style="flex:1;overflow-y:auto;"></div>
                    </div>
                </div>
                
                <!-- Create Edit Modal -->
                <div id="create-edit-modal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.7);z-index:9999;display:none;align-items:center;justify-content:center;">
                    <div style="background:var(--bg-card);padding:32px;border-radius:8px;width:100%;max-width:600px;">
                        <h2 style="margin-bottom:24px;">Create New Edit</h2>
                        <div class="form-group">
                            <label class="form-label">Edit Name</label>
                            <input type="text" id="new-edit-name" class="form-input" placeholder="e.g. Valentine's Day Gifting">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Description</label>
                            <textarea id="new-edit-description" class="form-input form-textarea" rows="2" placeholder="Brief description of this edit"></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Categories (comma-separated)</label>
                            <input type="text" id="new-edit-categories" class="form-input" placeholder="dresses, accessories, jewellery">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Keywords (comma-separated)</label>
                            <input type="text" id="new-edit-keywords" class="form-input" placeholder="gift, romantic, elegant">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Colors (comma-separated, optional)</label>
                            <input type="text" id="new-edit-colors" class="form-input" placeholder="red, pink, burgundy">
                        </div>
                        <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:24px;">
                            <button class="btn btn-secondary" onclick="document.getElementById('create-edit-modal').style.display='none'">Cancel</button>
                            <button class="btn btn-primary" onclick="App.createNewEdit()">Create Edit</button>
                        </div>
                    </div>
                </div>
            `;
        } catch (error) {
            main.innerHTML = `<div class="empty-state"><div class="empty-state-title">Error</div><p>${error.message}</p></div>`;
        }
    },
    
    renderEditTable(edits, type) {
        return `
            <table style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr style="border-bottom:1px solid var(--border-default);">
                        <th style="text-align:left;padding:12px 16px;font-size:12px;color:var(--text-secondary);">Edit</th>
                        <th style="text-align:center;padding:12px 8px;font-size:12px;color:var(--text-secondary);">Products</th>
                        <th style="text-align:center;padding:12px 8px;font-size:12px;color:var(--text-secondary);">In Stock</th>
                        <th style="text-align:center;padding:12px 8px;font-size:12px;color:var(--text-secondary);">Pending</th>
                        ${type === 'active' ? '<th style="text-align:center;padding:12px 8px;font-size:12px;color:var(--text-secondary);">Synced</th>' : ''}
                        <th style="text-align:right;padding:12px 16px;font-size:12px;color:var(--text-secondary);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ${edits.map(edit => `
                        <tr style="border-bottom:1px solid var(--border-default);">
                            <td style="padding:12px 16px;">
                                <div style="font-weight:500;">${this.escapeHtml(edit.name)}</div>
                                <div style="font-size:12px;color:var(--text-secondary);">
                                    ${edit.source_type === 'calendar' ? '📅 Calendar' : 
                                      edit.source_type === 'seasonal' ? '🌿 Seasonal' :
                                      edit.source_type === 'occasion' ? '🎉 Occasion' :
                                      edit.source_type === 'category' ? '📁 Category' : '✏️ Custom'}
                                    ${edit.auto_regenerate ? ' • 🔄 Auto' : ''}
                                </div>
                            </td>
                            <td style="padding:12px 8px;text-align:center;">
                                <span style="font-weight:500;">${edit.total_products || 0}</span>
                            </td>
                            <td style="padding:12px 8px;text-align:center;">
                                <span style="color:${(edit.in_stock_products || 0) > 0 ? 'var(--status-published)' : 'var(--text-muted)'};">
                                    ${edit.in_stock_products || 0}
                                </span>
                            </td>
                            <td style="padding:12px 8px;text-align:center;">
                                ${(edit.pending_products || 0) > 0 
                                    ? `<span style="background:var(--status-review);color:white;padding:2px 8px;border-radius:10px;font-size:11px;">${edit.pending_products}</span>`
                                    : '<span style="color:var(--text-muted);">—</span>'}
                            </td>
                            ${type === 'active' ? `
                                <td style="padding:12px 8px;text-align:center;">
                                    <span style="color:var(--status-published);">${edit.synced_products || 0}</span>
                                </td>
                            ` : ''}
                            <td style="padding:12px 16px;text-align:right;">
                                <button class="btn btn-sm btn-secondary" onclick="App.openEditDetail(${edit.id})" style="margin-right:4px;">View</button>
                                ${type === 'suggested' ? `
                                    <button class="btn btn-sm btn-primary" onclick="App.previewAndApproveEdit(${edit.id})">Preview Products</button>
                                ` : type === 'approved' ? `
                                    <button class="btn btn-sm" style="background:var(--status-published);color:white;" onclick="App.createEditInWP(${edit.id})">Create in WP</button>
                                ` : `
                                    <button class="btn btn-sm btn-secondary" onclick="App.regenerateEditProducts(${edit.id})" style="margin-right:4px;">🔄 Regenerate</button>
                                    <button class="btn btn-sm" style="background:var(--status-published);color:white;" onclick="App.syncEditToWP(${edit.id})">Sync to WP</button>
                                `}
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    },
    
    async generateEditSuggestions() {
        try {
            this.toast('Generating edit suggestions...', 'info');
            const result = await this.api('/edit-suggestions/generate', { method: 'POST' });
            // result is data.data from API, so message might be in result or we build it
            const msg = result.message || `Created ${result.created || 0}, skipped ${result.skipped || 0}`;
            this.toast(msg, 'success');
            this.loadEditManager();
        } catch (error) {
            this.toast(error.message || 'Failed to generate suggestions', 'error');
        }
    },
    
    showCreateEditModal() {
        document.getElementById('create-edit-modal').style.display = 'flex';
    },
    
    async createNewEdit() {
        const name = document.getElementById('new-edit-name').value.trim();
        const description = document.getElementById('new-edit-description').value.trim();
        const categories = document.getElementById('new-edit-categories').value.split(',').map(s => s.trim()).filter(s => s);
        const keywords = document.getElementById('new-edit-keywords').value.split(',').map(s => s.trim()).filter(s => s);
        const colors = document.getElementById('new-edit-colors').value.split(',').map(s => s.trim()).filter(s => s);
        
        if (!name) {
            this.toast('Name is required', 'error');
            return;
        }
        
        try {
            await this.api('/edit-suggestions', {
                method: 'POST',
                body: { name, description, categories, keywords, colors }
            });
            this.toast('Edit created!', 'success');
            document.getElementById('create-edit-modal').style.display = 'none';
            this.loadEditManager();
        } catch (error) {
            this.toast(error.message, 'error');
        }
    },
    
    async openEditDetail(id) {
        const modal = document.getElementById('edit-detail-modal');
        const content = document.getElementById('edit-detail-content');
        modal.style.display = 'block';
        content.innerHTML = '<div class="loading" style="padding:40px;"><div class="spinner"></div></div>';
        
        try {
            const edit = await this.api(`/edit-suggestions/${id}`);
            this.editManagerState.currentEditId = id;
            
            const rules = edit.matching_rules || {};
            
            content.innerHTML = `
                <div style="padding:24px;border-bottom:1px solid var(--border-default);display:flex;justify-content:space-between;align-items:start;">
                    <div>
                        <h2 style="margin-bottom:8px;">${this.escapeHtml(edit.name)}</h2>
                        <p style="color:var(--text-secondary);margin-bottom:12px;">${this.escapeHtml(edit.description || '')}</p>
                        <div style="display:flex;gap:16px;font-size:13px;">
                            <span><strong>Status:</strong> ${edit.status}</span>
                            <span><strong>Products:</strong> ${edit.stats.total}</span>
                            <span style="color:var(--status-published);"><strong>In Stock:</strong> ${edit.stats.in_stock}</span>
                            <span style="color:var(--text-muted);"><strong>Out of Stock:</strong> ${edit.stats.out_of_stock}</span>
                        </div>
                    </div>
                    <button class="btn btn-secondary" onclick="document.getElementById('edit-detail-modal').style.display='none'">✕ Close</button>
                </div>
                
                <div style="display:grid;grid-template-columns:300px 1fr;height:calc(100vh - 200px);">
                    <!-- Rules Panel -->
                    <div style="border-right:1px solid var(--border-default);padding:20px;overflow-y:auto;">
                        <h3 style="margin-bottom:16px;">Matching Rules</h3>
                        
                        <div class="form-group">
                            <label class="form-label">Categories</label>
                            <textarea id="edit-rule-categories" class="form-input form-textarea" rows="3" placeholder="One per line">${(rules.categories || []).join('\n')}</textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Keywords</label>
                            <textarea id="edit-rule-keywords" class="form-input form-textarea" rows="3" placeholder="One per line">${(rules.keywords || []).join('\n')}</textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Colors</label>
                            <textarea id="edit-rule-colors" class="form-input form-textarea" rows="2" placeholder="One per line">${(rules.colors || []).join('\n')}</textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Exclude Categories</label>
                            <textarea id="edit-rule-exclude" class="form-input form-textarea" rows="2" placeholder="One per line">${(rules.exclude_categories || []).join('\n')}</textarea>
                        </div>
                        
                        <div style="display:flex;gap:8px;margin-top:16px;">
                            <button class="btn btn-secondary" onclick="App.saveEditRules(${id})" style="flex:1;">Save Rules</button>
                            <button class="btn btn-primary" onclick="App.previewEditProducts(${id})" style="flex:1;">Preview</button>
                        </div>
                        
                        <div style="margin-top:20px;padding-top:20px;border-top:1px solid var(--border-default);">
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                                <input type="checkbox" id="edit-auto-regenerate" ${edit.auto_regenerate ? 'checked' : ''}>
                                <span>Auto-regenerate monthly</span>
                            </label>
                        </div>
                        
                        <div style="margin-top:20px;">
                            <button class="btn btn-secondary" onclick="App.regenerateEditProducts(${id})" style="width:100%;">🔄 Regenerate Products Now</button>
                        </div>
                        
                        ${edit.wp_term_id ? `
                            <div style="margin-top:12px;">
                                <button class="btn" style="width:100%;background:var(--status-published);color:white;" onclick="App.syncEditToWP(${id})">⬆️ Sync to WordPress</button>
                            </div>
                        ` : edit.status === 'approved' || edit.status === 'suggested' ? `
                            <div style="margin-top:12px;">
                                <button class="btn btn-primary" style="width:100%;" onclick="App.createEditInWP(${id})">Create in WordPress</button>
                            </div>
                        ` : ''}
                    </div>
                    
                    <!-- Products Panel -->
                    <div style="padding:20px;overflow-y:auto;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                            <h3>Products (${edit.products.length})</h3>
                            <div style="display:flex;gap:8px;">
                                <button class="btn btn-sm btn-secondary" onclick="App.approveAllEditProducts(${id})">✓ Approve All Pending</button>
                            </div>
                        </div>
                        
                        ${edit.products.length > 0 ? `
                            <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(200px, 1fr));gap:16px;">
                                ${edit.products.map(p => `
                                    <div style="border:1px solid var(--border-default);border-radius:8px;overflow:hidden;position:relative;">
                                        ${p.status === 'pending' ? '<div style="position:absolute;top:8px;left:8px;background:var(--status-review);color:white;padding:2px 8px;border-radius:4px;font-size:10px;">PENDING</div>' : ''}
                                        ${p.synced_to_wp ? '<div style="position:absolute;top:8px;right:8px;background:var(--status-published);color:white;padding:2px 8px;border-radius:4px;font-size:10px;">SYNCED</div>' : ''}
                                        ${p.stock_status !== 'instock' ? '<div style="position:absolute;top:8px;right:8px;background:var(--status-error);color:white;padding:2px 8px;border-radius:4px;font-size:10px;">OUT OF STOCK</div>' : ''}
                                        <div style="height:150px;background:var(--bg-tertiary);display:flex;align-items:center;justify-content:center;">
                                            ${p.image_url ? `<img src="${p.image_url}" style="max-height:100%;max-width:100%;object-fit:contain;">` : '📷'}
                                        </div>
                                        <div style="padding:12px;">
                                            <div style="font-size:12px;color:var(--text-muted);">${this.escapeHtml(p.brand_name || '')}</div>
                                            <div style="font-weight:500;font-size:13px;margin:4px 0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${this.escapeHtml(p.title)}</div>
                                            <div style="font-size:13px;">£${p.price}</div>
                                            <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">
                                                Score: ${p.match_score}
                                            </div>
                                            <div style="display:flex;gap:4px;margin-top:8px;">
                                                ${p.status === 'pending' ? `
                                                    <button class="btn btn-sm btn-primary" onclick="App.approveEditProduct(${id}, ${p.wc_product_id})" style="flex:1;">✓</button>
                                                ` : ''}
                                                <button class="btn btn-sm btn-secondary" onclick="App.rejectEditProduct(${id}, ${p.wc_product_id})" style="flex:1;">✕</button>
                                            </div>
                                        </div>
                                    </div>
                                `).join('')}
                            </div>
                        ` : `
                            <div style="text-align:center;padding:40px;color:var(--text-muted);">
                                <p>No products yet. Click "Preview" to see matching products.</p>
                            </div>
                        `}
                    </div>
                </div>
            `;
        } catch (error) {
            content.innerHTML = `<div style="padding:40px;text-align:center;color:var(--status-error);">Error: ${error.message}</div>`;
        }
    },
    
    async previewAndApproveEdit(id) {
        const modal = document.getElementById('edit-detail-modal');
        const content = document.getElementById('edit-detail-content');
        modal.style.display = 'block';
        content.innerHTML = '<div class="loading" style="padding:40px;"><div class="spinner"></div></div>';
        
        try {
            const preview = await this.api(`/edit-suggestions/${id}/preview`);
            this.editManagerState.currentEditId = id;
            this.editManagerState.previewProducts = preview.products;
            
            content.innerHTML = `
                <div style="padding:24px;border-bottom:1px solid var(--border-default);display:flex;justify-content:space-between;align-items:start;">
                    <div>
                        <h2 style="margin-bottom:8px;">Preview: ${this.escapeHtml(preview.edit.name)}</h2>
                        <p style="color:var(--text-secondary);">
                            Found <strong>${preview.stats.total}</strong> matching products 
                            (<span style="color:var(--status-published);">${preview.stats.in_stock} in stock</span>)
                        </p>
                    </div>
                    <div style="display:flex;gap:8px;">
                        <button class="btn btn-secondary" onclick="document.getElementById('edit-detail-modal').style.display='none'">Cancel</button>
                        <button class="btn btn-primary" onclick="App.quickApproveAll(${id})">✓ Approve All & Save</button>
                    </div>
                </div>
                
                <div style="padding:20px;overflow-y:auto;max-height:calc(100vh - 200px);">
                    <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(180px, 1fr));gap:12px;">
                        ${preview.products.map((p, idx) => `
                            <div class="preview-product" data-idx="${idx}" data-wc-id="${p.wc_product_id}" style="border:1px solid var(--border-default);border-radius:8px;overflow:hidden;cursor:pointer;transition:all 0.2s;" onclick="App.togglePreviewProduct(this)">
                                <div style="height:120px;background:var(--bg-tertiary);display:flex;align-items:center;justify-content:center;">
                                    ${p.image_url ? `<img src="${p.image_url}" style="max-height:100%;max-width:100%;object-fit:contain;">` : '📷'}
                                </div>
                                <div style="padding:10px;">
                                    <div style="font-size:11px;color:var(--text-muted);">${this.escapeHtml(p.brand_name || '')}</div>
                                    <div style="font-weight:500;font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${this.escapeHtml(p.title)}</div>
                                    <div style="font-size:12px;margin-top:4px;">£${p.price} • Score: ${p.match_score}</div>
                                    <div style="font-size:10px;color:var(--text-secondary);margin-top:4px;">${(p.match_reasons || []).slice(0, 2).join(', ')}</div>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        } catch (error) {
            content.innerHTML = `<div style="padding:40px;text-align:center;color:var(--status-error);">Error: ${error.message}</div>`;
        }
    },
    
    togglePreviewProduct(el) {
        el.classList.toggle('rejected');
        if (el.classList.contains('rejected')) {
            el.style.opacity = '0.3';
            el.style.borderColor = 'var(--status-error)';
        } else {
            el.style.opacity = '1';
            el.style.borderColor = 'var(--border-default)';
        }
    },
    
    async quickApproveAll(id) {
        // Get all non-rejected products
        const products = document.querySelectorAll('.preview-product:not(.rejected)');
        const wcProductIds = Array.from(products).map(el => parseInt(el.dataset.wcId));
        
        if (wcProductIds.length === 0) {
            this.toast('No products selected', 'error');
            return;
        }
        
        try {
            this.toast(`Approving ${wcProductIds.length} products...`, 'info');
            
            // First regenerate to save products
            await this.api(`/edit-suggestions/${id}/regenerate`, { method: 'POST' });
            
            // Then approve all
            await this.api(`/edit-suggestions/${id}/approve`, {
                method: 'POST',
                body: { approve_all: true }
            });
            
            this.toast(`${wcProductIds.length} products approved!`, 'success');
            document.getElementById('edit-detail-modal').style.display = 'none';
            this.loadEditManager();
        } catch (error) {
            this.toast(error.message, 'error');
        }
    },
    
    async previewEditProducts(id) {
        try {
            this.toast('Previewing products...', 'info');
            const preview = await this.api(`/edit-suggestions/${id}/preview`);
            this.toast(`Found ${preview.stats.total} products (${preview.stats.in_stock} in stock)`, 'success');
            this.openEditDetail(id);
        } catch (error) {
            this.toast(error.message, 'error');
        }
    },
    
    async saveEditRules(id) {
        const categories = document.getElementById('edit-rule-categories').value.split('\n').map(s => s.trim()).filter(s => s);
        const keywords = document.getElementById('edit-rule-keywords').value.split('\n').map(s => s.trim()).filter(s => s);
        const colors = document.getElementById('edit-rule-colors').value.split('\n').map(s => s.trim()).filter(s => s);
        const excludeCategories = document.getElementById('edit-rule-exclude').value.split('\n').map(s => s.trim()).filter(s => s);
        const autoRegenerate = document.getElementById('edit-auto-regenerate').checked;
        
        try {
            await this.api(`/edit-suggestions/${id}/rules`, {
                method: 'PUT',
                body: { categories, keywords, colors, exclude_categories: excludeCategories, auto_regenerate: autoRegenerate }
            });
            this.toast('Rules saved!', 'success');
        } catch (error) {
            this.toast(error.message, 'error');
        }
    },
    
    async regenerateEditProducts(id) {
        if (!confirm('Regenerate products based on current rules? This will add new matches and remove products that no longer match (except manually added ones).')) return;
        
        try {
            this.toast('Regenerating products...', 'info');
            const result = await this.api(`/edit-suggestions/${id}/regenerate`, { method: 'POST' });
            this.toast(result.message, 'success');
            this.openEditDetail(id);
        } catch (error) {
            this.toast(error.message, 'error');
        }
    },
    
    async approveEditProduct(editId, wcProductId) {
        try {
            await this.api(`/edit-suggestions/${editId}/approve`, {
                method: 'POST',
                body: { wc_product_ids: [wcProductId] }
            });
            this.toast('Product approved', 'success');
            this.openEditDetail(editId);
        } catch (error) {
            this.toast(error.message, 'error');
        }
    },
    
    async approveAllEditProducts(id) {
        try {
            await this.api(`/edit-suggestions/${id}/approve`, {
                method: 'POST',
                body: { approve_all: true }
            });
            this.toast('All pending products approved', 'success');
            this.openEditDetail(id);
        } catch (error) {
            this.toast(error.message, 'error');
        }
    },
    
    async rejectEditProduct(editId, wcProductId) {
        try {
            await this.api(`/edit-suggestions/${editId}/reject`, {
                method: 'POST',
                body: { wc_product_ids: [wcProductId] }
            });
            this.toast('Product removed', 'success');
            this.openEditDetail(editId);
        } catch (error) {
            this.toast(error.message, 'error');
        }
    },
    
    async createEditInWP(id) {
        if (!confirm('Create this Edit in WordPress? This will create a new Edit taxonomy term.')) return;
        
        try {
            this.toast('Creating Edit in WordPress...', 'info');
            const result = await this.api(`/edit-suggestions/${id}/create-wp`, { method: 'POST' });
            this.toast(result.message, 'success');
            this.loadEditManager();
        } catch (error) {
            this.toast(error.message, 'error');
        }
    },
    
    async syncEditToWP(id) {
        if (!confirm('Sync products to WordPress? This will assign/remove the Edit taxonomy from products.')) return;
        
        try {
            this.toast('Syncing to WordPress...', 'info');
            const result = await this.api(`/edit-suggestions/${id}/sync`, { method: 'POST' });
            this.toast(result.message, 'success');
            this.openEditDetail(id);
        } catch (error) {
            this.toast(error.message, 'error');
        }
    },

    // ==================== BRAINSTORM ====================
    async loadBrainstorm() {
        const main = document.getElementById('main-content');
        main.innerHTML = '<div class="loading"><div class="spinner"></div></div>';
        
        try {
            const ideas = await this.api('/brainstorm');
            
            main.innerHTML = `
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Brainstorm</h1>
                        <p class="page-subtitle">Content ideas and inspiration</p>
                    </div>
                    <button class="btn btn-primary" onclick="App.showNewIdeaModal()">+ New Idea</button>
                </div>
                
                <div class="card" style="margin-bottom:24px;">
                    <div class="card-header"><span class="card-title">AI Brainstorm</span></div>
                    <div class="card-body">
                        <div style="display:flex;gap:12px;">
                            <input type="text" id="brainstorm-prompt" class="form-input" style="flex:1;" placeholder="E.g., Blog ideas for summer holiday season...">
                            <button class="btn btn-primary" onclick="App.aiBrainstorm()">Generate Ideas</button>
                        </div>
                        <div id="brainstorm-results" style="margin-top:16px;"></div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header"><span class="card-title">Saved Ideas</span></div>
                    <div class="card-body">
                        ${ideas.length ? ideas.map(idea => `
                            <div style="padding:16px;border:1px solid var(--border-default);margin-bottom:12px;">
                                <div style="display:flex;justify-content:space-between;align-items:start;">
                                    <div>
                                        <div style="font-weight:500;">${this.escapeHtml(idea.title)}</div>
                                        <div style="font-size:13px;color:var(--text-secondary);margin-top:4px;">${this.escapeHtml(idea.description || '')}</div>
                                    </div>
                                    <button class="btn btn-sm btn-primary" onclick="App.convertIdea(${idea.id}, this)">🚀 Generate Post</button>
                                </div>
                            </div>
                        `).join('') : '<p style="text-align:center;color:var(--text-secondary);padding:20px;">No ideas saved yet. Use AI brainstorm or add manually.</p>'}
                    </div>
                </div>
            `;
        } catch (error) {
            main.innerHTML = `<div class="empty-state"><div class="empty-state-title">Error</div><p>${error.message}</p></div>`;
        }
    },
    
    async aiBrainstorm() {
        const prompt = document.getElementById('brainstorm-prompt').value;
        if (!prompt) {
            this.toast('Please enter a topic first', 'error');
            return;
        }
        
        const results = document.getElementById('brainstorm-results');
        results.innerHTML = '<div class="loading"><div class="spinner"></div></div>';
        
        try {
            const data = await this.api('/claude/brainstorm', { method: 'POST', body: { prompt } });
            const ideas = data.ideas || data || [];
            
            if (!ideas.length) {
                results.innerHTML = '<p style="color:var(--text-secondary);">No ideas generated. Try a different topic.</p>';
                return;
            }
            
            results.innerHTML = ideas.map(idea => `
                <div style="padding:16px;border:1px solid var(--border-default);margin-bottom:12px;">
                    <div style="font-weight:600;font-size:15px;">${this.escapeHtml(idea.title)}</div>
                    <div style="font-size:13px;color:var(--text-secondary);margin-top:8px;">${this.escapeHtml(idea.description || '')}</div>
                    
                    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:12px;">
                        ${idea.content_type ? `<span style="font-size:11px;padding:4px 8px;background:var(--status-scheduled);color:white;border-radius:2px;">${this.escapeHtml(idea.content_type)}</span>` : ''}
                        ${idea.target_audience ? `<span style="font-size:11px;padding:4px 8px;background:var(--bg-tertiary);">🎯 ${this.escapeHtml(idea.target_audience)}</span>` : ''}
                    </div>
                    
                    ${idea.suggested_brands?.length ? `
                        <div style="margin-top:12px;">
                            <span style="font-size:11px;color:var(--text-muted);">Suggested brands:</span>
                            <span style="font-size:12px;margin-left:4px;">${idea.suggested_brands.map(b => this.escapeHtml(b)).join(', ')}</span>
                        </div>
                    ` : ''}
                    
                    ${idea.suggested_categories?.length ? `
                        <div style="margin-top:4px;">
                            <span style="font-size:11px;color:var(--text-muted);">Categories:</span>
                            <span style="font-size:12px;margin-left:4px;">${idea.suggested_categories.map(c => this.escapeHtml(c)).join(', ')}</span>
                        </div>
                    ` : ''}
                    
                    <button class="btn btn-sm btn-primary" style="margin-top:12px;" onclick="App.saveIdea(\`${this.escapeHtml(idea.title).replace(/`/g, '\\`')}\`, \`${this.escapeHtml(idea.description || '').replace(/`/g, '\\`')}\`)">💾 Save Idea</button>
                </div>
            `).join('');
        } catch (error) {
            results.innerHTML = `<p style="color:var(--status-error);">${error.message}</p>`;
        }
    },
    
    async saveIdea(title, description) {
        try {
            await this.api('/brainstorm', { method: 'POST', body: { title, description } });
            this.toast('Idea saved!');
            this.loadBrainstorm();
        } catch (error) {
            this.toast(error.message, 'error');
        }
    },
    
    async convertIdea(id, buttonEl) {
        // Show loading state
        const originalText = buttonEl ? buttonEl.innerHTML : '';
        if (buttonEl) {
            buttonEl.disabled = true;
            buttonEl.innerHTML = '⏳ Generating...';
        }
        
        // Show full-screen loading overlay
        const overlay = document.createElement('div');
        overlay.id = 'convert-loading-overlay';
        overlay.innerHTML = `
            <div style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.7);display:flex;align-items:center;justify-content:center;z-index:9999;">
                <div style="background:var(--bg-card);padding:40px 60px;border-radius:8px;text-align:center;">
                    <div class="spinner" style="margin:0 auto 20px;"></div>
                    <div style="font-size:18px;font-weight:600;margin-bottom:8px;">Generating Full Post</div>
                    <div style="color:var(--text-secondary);font-size:14px;">Claude is creating intro, sections, carousels & meta description...</div>
                    <div style="color:var(--text-muted);font-size:12px;margin-top:12px;">This may take 15-30 seconds</div>
                </div>
            </div>
        `;
        document.body.appendChild(overlay);
        
        try {
            const result = await this.api(`/brainstorm/${id}/convert`, { method: 'POST' });
            this.toast('Post generated successfully!', 'success');
            this.navigate(`/posts/${result.post_id}`);
        } catch (error) {
            this.toast(error.message, 'error');
            // Restore button
            if (buttonEl) {
                buttonEl.disabled = false;
                buttonEl.innerHTML = originalText;
            }
        } finally {
            // Remove overlay
            const existingOverlay = document.getElementById('convert-loading-overlay');
            if (existingOverlay) existingOverlay.remove();
        }
    },

    // ==================== SETTINGS ====================
    async loadSettings() {
        const main = document.getElementById('main-content');
        main.innerHTML = `
            <div class="page-header">
                <h1 class="page-title">Settings</h1>
            </div>
            
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:24px;">
                <div class="card" onclick="App.navigate('/settings/defaults')" style="cursor:pointer;">
                    <div class="card-body" style="text-align:center;padding:40px;">
                        <div style="font-size:32px;margin-bottom:12px;">⚙️</div>
                        <div style="font-weight:600;margin-bottom:4px;">Default Settings</div>
                        <div style="font-size:13px;color:var(--text-secondary);">Default author, category for new posts</div>
                    </div>
                </div>
                
                <div class="card" onclick="App.navigate('/settings/sync')" style="cursor:pointer;">
                    <div class="card-body" style="text-align:center;padding:40px;">
                        <div style="font-size:32px;margin-bottom:12px;">🔄</div>
                        <div style="font-weight:600;margin-bottom:4px;">Sync Data</div>
                        <div style="font-size:13px;color:var(--text-secondary);">Sync products, categories, authors from WordPress</div>
                    </div>
                </div>
                
                <div class="card" onclick="App.navigate('/settings/brand-voice')" style="cursor:pointer;">
                    <div class="card-body" style="text-align:center;padding:40px;">
                        <div style="font-size:32px;margin-bottom:12px;">✍️</div>
                        <div style="font-weight:600;margin-bottom:4px;">Brand Voice</div>
                        <div style="font-size:13px;color:var(--text-secondary);">Configure AI writing style and tone</div>
                    </div>
                </div>
                
                <div class="card" onclick="App.navigate('/settings/writing-guidelines')" style="cursor:pointer;">
                    <div class="card-body" style="text-align:center;padding:40px;">
                        <div style="font-size:32px;margin-bottom:12px;">🚫</div>
                        <div style="font-weight:600;margin-bottom:4px;">Writing Guidelines</div>
                        <div style="font-size:13px;color:var(--text-secondary);">Words and phrases AI should avoid</div>
                    </div>
                </div>
                
                <div class="card" onclick="App.navigate('/calendar-events')" style="cursor:pointer;">
                    <div class="card-body" style="text-align:center;padding:40px;">
                        <div style="font-size:32px;margin-bottom:12px;">📅</div>
                        <div style="font-weight:600;margin-bottom:4px;">Calendar Events</div>
                        <div style="font-size:13px;color:var(--text-secondary);">Manage seasonal events for content</div>
                    </div>
                </div>
                
                <div class="card" onclick="App.navigate('/taxonomy-seo')" style="cursor:pointer;">
                    <div class="card-body" style="text-align:center;padding:40px;">
                        <div style="font-size:32px;margin-bottom:12px;">🔍</div>
                        <div style="font-weight:600;margin-bottom:4px;">Taxonomy SEO</div>
                        <div style="font-size:13px;color:var(--text-secondary);">SEO content for brands & categories</div>
                    </div>
                </div>
                
                <div class="card" onclick="App.navigate('/settings/maintenance')" style="cursor:pointer;">
                    <div class="card-body" style="text-align:center;padding:40px;">
                        <div style="font-size:32px;margin-bottom:12px;">🧹</div>
                        <div style="font-weight:600;margin-bottom:4px;">Maintenance</div>
                        <div style="font-size:13px;color:var(--text-secondary);">Clear posts, reset scheduled content</div>
                    </div>
                </div>
            </div>
        `;
    },
    
    // ==================== DEFAULT SETTINGS ====================
    async loadDefaultSettings() {
        const main = document.getElementById('main-content');
        main.innerHTML = '<div class="loading"><div class="spinner"></div></div>';
        
        try {
            const [authors, categories, settings] = await Promise.all([
                this.api('/authors'),
                this.api('/categories'),
                this.api('/settings/defaults')
            ]);
            
            const authorOptions = authors.map(a => 
                `<option value="${a.id}" ${settings.default_author_id == a.id ? 'selected' : ''}>${this.escapeHtml(a.name)}</option>`
            ).join('');
            
            const categoryOptions = categories.map(c => 
                `<option value="${c.id}" ${settings.default_category_id == c.id ? 'selected' : ''}>${this.escapeHtml(c.name)}</option>`
            ).join('');
            
            main.innerHTML = `
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Default Settings</h1>
                        <p class="page-subtitle">Set defaults for all new posts</p>
                    </div>
                </div>
                
                <div class="card" style="max-width:500px;">
                    <div class="card-body">
                        <div class="form-group">
                            <label class="form-label">Default Author</label>
                            <select id="default-author" class="form-input form-select">
                                <option value="">-- Select Author --</option>
                                ${authorOptions}
                            </select>
                            <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">All new posts will be assigned to this author</div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Default Category</label>
                            <select id="default-category" class="form-input form-select">
                                <option value="">-- Select Category --</option>
                                ${categoryOptions}
                            </select>
                            <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">All new posts will be assigned to this category</div>
                        </div>
                        
                        <button class="btn btn-primary" onclick="App.saveDefaultSettings()">Save Defaults</button>
                    </div>
                </div>
            `;
        } catch (error) {
            main.innerHTML = `<div class="empty-state"><div class="empty-state-title">Error</div><p>${error.message}</p></div>`;
        }
    },
    
    async saveDefaultSettings() {
        const data = {
            default_author_id: document.getElementById('default-author').value || null,
            default_category_id: document.getElementById('default-category').value || null
        };
        
        try {
            await this.api('/settings/defaults', { method: 'POST', body: data });
            this.toast('Default settings saved!', 'success');
        } catch (error) {
            this.toast(error.message, 'error');
        }
    },
    
    // ==================== MAINTENANCE ====================
    async loadMaintenance() {
        const main = document.getElementById('main-content');
        main.innerHTML = '<div class="loading"><div class="spinner"></div></div>';
        
        try {
            const stats = await this.api('/maintenance/stats');
            
            main.innerHTML = `
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Maintenance</h1>
                        <p class="page-subtitle">Database cleanup and reset options</p>
                    </div>
                </div>
                
                <div style="display:grid;gap:24px;max-width:600px;">
                    <div class="card">
                        <div class="card-header">
                            <span class="card-title">📝 Posts</span>
                            <span style="color:var(--text-secondary);">${stats.posts || 0} posts, ${stats.sections || 0} sections</span>
                        </div>
                        <div class="card-body">
                            <p style="margin-bottom:16px;color:var(--text-secondary);">Delete all posts and their sections. This will also reset scheduled content back to pending.</p>
                            <button class="btn btn-danger" onclick="App.clearAllPosts()">🗑️ Clear All Posts</button>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <span class="card-title">📅 Scheduled Content</span>
                            <span style="color:var(--text-secondary);">${stats.scheduled || 0} items</span>
                        </div>
                        <div class="card-body">
                            <p style="margin-bottom:16px;color:var(--text-secondary);">Reset all scheduled content to pending status (keeps posts but allows regeneration).</p>
                            <button class="btn btn-secondary" onclick="App.resetScheduledContent()">↩️ Reset to Pending</button>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <span class="card-title">🗓️ Seasonal Events</span>
                            <span style="color:var(--text-secondary);">${stats.events || 0} events</span>
                        </div>
                        <div class="card-body">
                            <p style="margin-bottom:16px;color:var(--text-secondary);">Re-seed seasonal events (Valentine's, Mother's Day, Black Friday, etc).</p>
                            <button class="btn btn-secondary" onclick="App.reseedEvents()">🌱 Re-seed Events</button>
                        </div>
                    </div>
                </div>
            `;
        } catch (error) {
            main.innerHTML = `<div class="empty-state"><div class="empty-state-title">Error</div><p>${error.message}</p></div>`;
        }
    },
    
    async clearAllPosts() {
        if (!confirm('Are you sure you want to delete ALL posts and sections? This cannot be undone.')) return;
        if (!confirm('Really sure? This will delete everything and reset scheduled content.')) return;
        
        try {
            const result = await this.api('/maintenance/clear-posts', { method: 'POST' });
            this.toast(result.message || 'All posts cleared!', 'success');
            this.loadMaintenance();
        } catch (error) {
            this.toast(error.message, 'error');
        }
    },
    
    async resetScheduledContent() {
        if (!confirm('Reset all scheduled content to pending? Posts will remain but can be regenerated.')) return;
        
        try {
            const result = await this.api('/maintenance/reset-scheduled', { method: 'POST' });
            this.toast(result.message || 'Scheduled content reset!', 'success');
            this.loadMaintenance();
        } catch (error) {
            this.toast(error.message, 'error');
        }
    },
    
    async reseedEvents() {
        if (!confirm('Re-seed seasonal events? This will add any missing events.')) return;
        
        try {
            const result = await this.api('/maintenance/reseed-events', { method: 'POST' });
            this.toast(result.message || 'Events re-seeded!', 'success');
            this.loadMaintenance();
        } catch (error) {
            this.toast(error.message, 'error');
        }
    },

    // ==================== SYNC ====================
    async loadSync() {
        const main = document.getElementById('main-content');
        main.innerHTML = '<div class="loading"><div class="spinner"></div></div>';
        
        try {
            const status = await this.api('/wordpress/sync/status');
            
            main.innerHTML = `
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Sync Data</h1>
                        <p class="page-subtitle">Sync content from WordPress and WooCommerce</p>
                    </div>
                </div>
                
                <div style="display:grid;gap:24px;max-width:600px;">
                    <div class="card">
                        <div class="card-body" style="display:flex;justify-content:space-between;align-items:center;">
                            <div>
                                <div style="font-weight:500;">🏷️ Brands</div>
                                <div style="font-size:13px;color:var(--text-secondary);">${status.brands?.count || 0} synced${status.brands?.last_sync ? ' • Last: ' + new Date(status.brands.last_sync).toLocaleString('en-GB', {day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit'}) : ''}</div>
                            </div>
                            <button class="btn btn-secondary" onclick="App.runSync('brands')">Sync Now</button>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-body" style="display:flex;justify-content:space-between;align-items:center;">
                            <div>
                                <div style="font-weight:500;">✨ Edits</div>
                                <div style="font-size:13px;color:var(--text-secondary);">${status.edits?.count || 0} synced${status.edits?.last_sync ? ' • Last: ' + new Date(status.edits.last_sync).toLocaleString('en-GB', {day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit'}) : ''}</div>
                            </div>
                            <button class="btn btn-secondary" onclick="App.runSync('edits')">Sync Now</button>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-body" style="display:flex;justify-content:space-between;align-items:center;">
                            <div>
                                <div style="font-weight:500;">📁 Product Categories</div>
                                <div style="font-size:13px;color:var(--text-secondary);">${status.product_categories?.count || 0} synced${status.product_categories?.last_sync ? ' • Last: ' + new Date(status.product_categories.last_sync).toLocaleString('en-GB', {day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit'}) : ''}</div>
                            </div>
                            <button class="btn btn-secondary" onclick="App.runSync('product-categories')">Sync Now</button>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-body" style="display:flex;justify-content:space-between;align-items:center;">
                            <div>
                                <div style="font-weight:500;">🛍️ Products</div>
                                <div style="font-size:13px;color:var(--text-secondary);">${status.products.count} synced${status.products.last_sync ? ' • Last: ' + new Date(status.products.last_sync).toLocaleString('en-GB', {day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit'}) : ''}</div>
                            </div>
                            <button class="btn btn-secondary" onclick="App.runSync('products')">Sync Now</button>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-body" style="display:flex;justify-content:space-between;align-items:center;">
                            <div>
                                <div style="font-weight:500;">📝 Blog Categories</div>
                                <div style="font-size:13px;color:var(--text-secondary);">${status.categories.count} synced${status.categories.last_sync ? ' • Last: ' + new Date(status.categories.last_sync).toLocaleString('en-GB', {day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit'}) : ''}</div>
                            </div>
                            <button class="btn btn-secondary" onclick="App.runSync('categories')">Sync Now</button>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-body" style="display:flex;justify-content:space-between;align-items:center;">
                            <div>
                                <div style="font-weight:500;">👤 Authors</div>
                                <div style="font-size:13px;color:var(--text-secondary);">${status.authors.count} synced${status.authors.last_sync ? ' • Last: ' + new Date(status.authors.last_sync).toLocaleString('en-GB', {day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit'}) : ''}</div>
                            </div>
                            <button class="btn btn-secondary" onclick="App.runSync('authors')">Sync Now</button>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-body" style="display:flex;justify-content:space-between;align-items:center;">
                            <div>
                                <div style="font-weight:500;">🧩 Page Blocks</div>
                                <div style="font-size:13px;color:var(--text-secondary);">${status.blocks.count} synced${status.blocks.last_sync ? ' • Last: ' + new Date(status.blocks.last_sync).toLocaleString('en-GB', {day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit'}) : ''}</div>
                            </div>
                            <button class="btn btn-secondary" onclick="App.runSync('blocks')">Sync Now</button>
                        </div>
                    </div>
                </div>
            `;
        } catch (error) {
            main.innerHTML = `<div class="empty-state"><div class="empty-state-title">Error</div><p>${error.message}</p></div>`;
        }
    },
    
    async runSync(type) {
        const btn = event.target;
        const originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Syncing...';
        
        this.toast(`Syncing ${type}...`);
        try {
            const result = await this.api(`/wordpress/sync/${type}`, { method: 'POST' });
            
            // Show detailed debug info if available
            let message = result.message || `${type} synced successfully!`;
            if (result.debug && Object.keys(result.debug).length > 0) {
                console.log('Sync debug info:', result.debug);
                if (result.debug.has_acf_key === false) {
                    message += '\n\n⚠️ Debug: No ACF fields found in API response. Check ACF field group settings.';
                }
            }
            
            this.toast(message, 'success');
            this.loadSync();
        } catch (error) {
            this.toast(error.message || 'Sync failed', 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = originalText;
        }
    },

    // ==================== AUTO-PILOT ====================
    async loadAutoPilot() {
        const main = document.getElementById('main-content');
        main.innerHTML = '<div class="loading"><div class="spinner"></div></div>';
        
        try {
            const [templates, scheduled, stats] = await Promise.all([
                this.api('/content/templates').catch(() => []),
                this.api('/content/scheduled').catch(() => []),
                this.api('/content/stats').catch(() => ({}))
            ]);
            
            main.innerHTML = `
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Auto-Pilot Settings</h1>
                        <p class="page-subtitle">Configure automated content generation</p>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="card" style="margin-bottom:24px;">
                    <div class="card-header">
                        <span class="card-title">Quick Actions</span>
                    </div>
                    <div class="card-body">
                        <div style="display:flex;gap:12px;flex-wrap:wrap;">
                            <button class="btn btn-primary" onclick="App.seedEvents()">🗓 Seed Seasonal Events</button>
                            <button class="btn btn-primary" onclick="App.seedTemplates()">📝 Seed Content Templates</button>
                            <button class="btn btn-secondary" onclick="App.generateCalendar()">📅 Generate 3-Month Calendar</button>
                            <button class="btn btn-primary" onclick="App.generateContent()" id="generate-btn">▶ Run Auto-Pilot</button>
                        </div>
                        <p style="font-size:12px;color:var(--text-secondary);margin-top:12px;">
                            First time? Click "Seed Seasonal Events" and "Seed Content Templates" to set up the defaults, then "Generate 3-Month Calendar" to create scheduled content slots.
                        </p>
                    </div>
                </div>
                
                <!-- Stats -->
                <div class="stats-grid" style="margin-bottom:24px;">
                    <div class="stat-card">
                        <div class="stat-label">Pending Generation</div>
                        <div class="stat-value">${stats.pending_generation || 0}</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Awaiting Review</div>
                        <div class="stat-value">${stats.awaiting_review || 0}</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Scheduled</div>
                        <div class="stat-value">${stats.scheduled || 0}</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">This Week</div>
                        <div class="stat-value">${stats.publishing_this_week || 0}</div>
                    </div>
                </div>
                
                <!-- Content Templates -->
                <div class="card" style="margin-bottom:24px;">
                    <div class="card-header">
                        <span class="card-title">Content Templates</span>
                        <span style="font-size:12px;color:var(--text-secondary);">${templates.length} active templates</span>
                    </div>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Template</th>
                                    <th>Category</th>
                                    <th>Type</th>
                                    <th>Frequency</th>
                                    <th>Lead Days</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${templates.length ? templates.map(t => `
                                    <tr>
                                        <td style="font-weight:500;">${this.escapeHtml(t.name)}</td>
                                        <td><span class="badge badge-${t.category}">${t.category}</span></td>
                                        <td>${t.content_type}</td>
                                        <td>${t.frequency || 'One-time'}</td>
                                        <td>${t.lead_days} days</td>
                                    </tr>
                                `).join('') : '<tr><td colspan="5" style="text-align:center;padding:40px;color:var(--text-secondary);">No templates yet. Click "Seed Content Templates" above.</td></tr>'}
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Scheduled Content -->
                <div class="card">
                    <div class="card-header">
                        <span class="card-title">Scheduled Content Pipeline</span>
                        <span style="font-size:12px;color:var(--text-secondary);">${scheduled.length} items in pipeline</span>
                    </div>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Content</th>
                                    <th>Event</th>
                                    <th>Publish Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${scheduled.length ? scheduled.map(s => `
                                    <tr>
                                        <td style="font-weight:500;">${this.escapeHtml(s.template_name || 'Unknown')}</td>
                                        <td>${s.event_name || '—'}</td>
                                        <td>${new Date(s.target_publish_date).toLocaleDateString('en-GB', { month: 'short', day: 'numeric', year: 'numeric' })}</td>
                                        <td><span class="status-badge status-${s.status}"><span class="status-dot"></span> ${s.status}</span></td>
                                    </tr>
                                `).join('') : '<tr><td colspan="4" style="text-align:center;padding:40px;color:var(--text-secondary);">No scheduled content. Click "Generate 3-Month Calendar" above.</td></tr>'}
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
        } catch (error) {
            main.innerHTML = `<div class="empty-state"><div class="empty-state-title">Error</div><p>${error.message}</p></div>`;
        }
    },
    
    async seedEvents() {
        this.toast('Seeding seasonal events...');
        try {
            const result = await this.api('/content/seed-events', { method: 'POST' });
            this.toast(result.message || 'Events seeded!', 'success');
            this.loadAutoPilot();
        } catch (error) {
            this.toast(error.message, 'error');
        }
    },
    
    async seedTemplates() {
        this.toast('Seeding content templates...');
        try {
            const result = await this.api('/content/seed-templates', { method: 'POST' });
            this.toast(result.message || 'Templates seeded!', 'success');
            this.loadAutoPilot();
        } catch (error) {
            this.toast(error.message, 'error');
        }
    },
    
    async generateCalendar() {
        // Show loading overlay
        const overlay = document.createElement('div');
        overlay.id = 'calendar-loading-overlay';
        overlay.innerHTML = `
            <div style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.7);display:flex;align-items:center;justify-content:center;z-index:9999;">
                <div style="background:var(--bg-card);padding:40px 60px;border-radius:8px;text-align:center;">
                    <div class="spinner" style="margin:0 auto 20px;"></div>
                    <div style="font-size:18px;font-weight:600;margin-bottom:8px;">Generating Content Calendar</div>
                    <div style="color:var(--text-secondary);font-size:14px;">Creating 3-month schedule based on seasonal events...</div>
                    <div style="color:var(--text-muted);font-size:12px;margin-top:12px;">This may take a few seconds</div>
                </div>
            </div>
        `;
        document.body.appendChild(overlay);
        
        try {
            const result = await this.api('/content/calendar/generate', { method: 'POST', body: { months: 3 } });
            this.toast(result.message || 'Calendar generated!', 'success');
            this.loadAutoPilot();
        } catch (error) {
            this.toast(error.message, 'error');
        } finally {
            const existingOverlay = document.getElementById('calendar-loading-overlay');
            if (existingOverlay) existingOverlay.remove();
        }
    },

    // ==================== BRAND VOICE ====================
    async loadBrandVoice() {
        const main = document.getElementById('main-content');
        main.innerHTML = '<div class="loading"><div class="spinner"></div></div>';
        
        try {
            const voice = await this.api('/settings/brand-voice');
            
            main.innerHTML = `
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Brand Voice</h1>
                        <p class="page-subtitle">Configure how Claude writes for Black White Denim</p>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-body">
                        ${voice.map(v => `
                            <div style="padding:16px;border:1px solid var(--border-default);margin-bottom:12px;">
                                <div style="display:flex;justify-content:space-between;align-items:start;">
                                    <div>
                                        <div style="font-weight:600;">${this.escapeHtml(v.attribute)}</div>
                                        <div style="font-size:13px;color:var(--text-secondary);margin-top:4px;">${this.escapeHtml(v.description)}</div>
                                        <div style="font-size:12px;color:var(--text-muted);margin-top:8px;">Examples: ${JSON.parse(v.examples || '[]').join(', ')}</div>
                                    </div>
                                    <div style="display:flex;align-items:center;gap:8px;">
                                        <span style="font-size:12px;color:var(--text-muted);">Weight: ${v.weight}</span>
                                        <span style="color:${v.is_active ? 'var(--status-published)' : 'var(--text-muted)'};">●</span>
                                    </div>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        } catch (error) {
            main.innerHTML = `<div class="empty-state"><div class="empty-state-title">Error</div><p>${error.message}</p></div>`;
        }
    },

    // ==================== WRITING GUIDELINES ====================
    async loadWritingGuidelines() {
        const main = document.getElementById('main-content');
        main.innerHTML = '<div class="loading"><div class="spinner"></div></div>';
        
        try {
            const guidelines = await this.api('/settings/writing-guidelines');
            
            main.innerHTML = `
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Writing Guidelines</h1>
                        <p class="page-subtitle">Words, phrases, and style rules for AI content generation</p>
                    </div>
                    <button class="btn btn-primary" onclick="App.seedWritingGuidelines()">Seed Defaults</button>
                </div>
                
                <!-- Avoid Words -->
                <div class="card" style="margin-bottom:24px;">
                    <div class="card-header">
                        <span class="card-title">🚫 Words to Avoid</span>
                        <span style="font-size:12px;color:var(--text-secondary);">${guidelines.avoid_words?.length || 0} words</span>
                    </div>
                    <div class="card-body">
                        <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px;">
                            ${(guidelines.avoid_words || []).map(g => `
                                <span class="badge" style="display:inline-flex;align-items:center;gap:4px;padding:4px 8px;">
                                    ${this.escapeHtml(g.value)}
                                    <button onclick="App.deleteGuideline(${g.id})" style="background:none;border:none;cursor:pointer;color:var(--text-muted);font-size:14px;">&times;</button>
                                </span>
                            `).join('')}
                        </div>
                        <div style="display:flex;gap:8px;">
                            <input type="text" id="new-avoid-word" class="form-input" placeholder="Add word to avoid..." style="flex:1;">
                            <button class="btn btn-secondary" onclick="App.addGuideline('avoid_words', document.getElementById('new-avoid-word').value)">Add</button>
                        </div>
                    </div>
                </div>
                
                <!-- Avoid Phrases -->
                <div class="card" style="margin-bottom:24px;">
                    <div class="card-header">
                        <span class="card-title">🚫 Phrases to Avoid</span>
                        <span style="font-size:12px;color:var(--text-secondary);">${guidelines.avoid_phrases?.length || 0} phrases</span>
                    </div>
                    <div class="card-body">
                        <div style="margin-bottom:16px;">
                            ${(guidelines.avoid_phrases || []).map(g => `
                                <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border-default);">
                                    <span style="font-size:13px;">"${this.escapeHtml(g.value)}"</span>
                                    <button onclick="App.deleteGuideline(${g.id})" class="btn btn-sm" style="color:var(--text-muted);">&times;</button>
                                </div>
                            `).join('') || '<p style="color:var(--text-muted);font-size:13px;">No phrases added yet</p>'}
                        </div>
                        <div style="display:flex;gap:8px;">
                            <input type="text" id="new-avoid-phrase" class="form-input" placeholder="Add phrase to avoid..." style="flex:1;">
                            <button class="btn btn-secondary" onclick="App.addGuideline('avoid_phrases', document.getElementById('new-avoid-phrase').value)">Add</button>
                        </div>
                    </div>
                </div>
                
                <!-- Style Rules -->
                <div class="card">
                    <div class="card-header">
                        <span class="card-title">📝 Style Rules</span>
                        <span style="font-size:12px;color:var(--text-secondary);">${guidelines.style_rules?.length || 0} rules</span>
                    </div>
                    <div class="card-body">
                        <div style="margin-bottom:16px;">
                            ${(guidelines.style_rules || []).map(g => `
                                <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid var(--border-default);">
                                    <span style="font-size:13px;">${this.escapeHtml(g.value)}</span>
                                    <button onclick="App.deleteGuideline(${g.id})" class="btn btn-sm" style="color:var(--text-muted);">&times;</button>
                                </div>
                            `).join('') || '<p style="color:var(--text-muted);font-size:13px;">No style rules added yet</p>'}
                        </div>
                        <div style="display:flex;gap:8px;">
                            <input type="text" id="new-style-rule" class="form-input" placeholder="Add style rule..." style="flex:1;">
                            <button class="btn btn-secondary" onclick="App.addGuideline('style_rules', document.getElementById('new-style-rule').value)">Add</button>
                        </div>
                    </div>
                </div>
            `;
            
            // Enter key to add
            ['new-avoid-word', 'new-avoid-phrase', 'new-style-rule'].forEach(id => {
                document.getElementById(id)?.addEventListener('keypress', (e) => {
                    if (e.key === 'Enter') {
                        const category = id.includes('word') ? 'avoid_words' : id.includes('phrase') ? 'avoid_phrases' : 'style_rules';
                        this.addGuideline(category, e.target.value);
                    }
                });
            });
            
        } catch (error) {
            main.innerHTML = `<div class="empty-state"><div class="empty-state-title">Error</div><p>${error.message}</p></div>`;
        }
    },
    
    async addGuideline(category, value) {
        if (!value.trim()) return;
        try {
            await this.api('/settings/writing-guidelines', { method: 'POST', body: { category, value } });
            this.toast('Guideline added');
            this.loadWritingGuidelines();
        } catch (error) {
            this.toast(error.message, 'error');
        }
    },
    
    async deleteGuideline(id) {
        try {
            await this.api(`/settings/writing-guidelines/${id}`, { method: 'DELETE' });
            this.loadWritingGuidelines();
        } catch (error) {
            this.toast(error.message, 'error');
        }
    },
    
    async seedWritingGuidelines() {
        this.toast('Seeding default guidelines...');
        try {
            const result = await this.api('/settings/writing-guidelines/seed', { method: 'POST' });
            this.toast(result.message || 'Guidelines seeded!', 'success');
            this.loadWritingGuidelines();
        } catch (error) {
            this.toast(error.message, 'error');
        }
    },

    // ==================== AI HELPERS ====================
    async aiGeneratePost() {
        const title = document.getElementById('post-title').value;
        if (!title) {
            this.toast('Enter a title first', 'error');
            return;
        }
        
        // Show loading overlay
        const overlay = document.createElement('div');
        overlay.id = 'generate-loading-overlay';
        overlay.innerHTML = `
            <div style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.7);display:flex;align-items:center;justify-content:center;z-index:9999;">
                <div style="background:var(--bg-card);padding:40px 60px;border-radius:8px;text-align:center;">
                    <div class="spinner" style="margin:0 auto 20px;"></div>
                    <div style="font-size:18px;font-weight:600;margin-bottom:8px;">Generating Content</div>
                    <div style="color:var(--text-secondary);font-size:14px;">Claude is writing your blog post...</div>
                    <div style="color:var(--text-muted);font-size:12px;margin-top:12px;">This may take 15-30 seconds</div>
                </div>
            </div>
        `;
        document.body.appendChild(overlay);
        
        try {
            const result = await this.api('/claude/generate-post', { 
                method: 'POST', 
                body: { 
                    topic: title,
                    sections: ['Section 1', 'Section 2', 'Section 3']
                } 
            });
            
            if (result.intro) document.getElementById('post-intro').value = result.intro;
            if (result.outro) document.getElementById('post-outro').value = result.outro;
            if (result.meta_description) document.getElementById('post-meta').value = result.meta_description;
            
            if (result.sections && result.sections.length) {
                const container = document.getElementById('sections-container');
                container.innerHTML = '';
                result.sections.forEach((s, i) => {
                    container.insertAdjacentHTML('beforeend', this.renderSection(s, i));
                });
            }
            
            this.toast('Content generated!', 'success');
        } catch (error) {
            this.toast(error.message, 'error');
        } finally {
            const existingOverlay = document.getElementById('generate-loading-overlay');
            if (existingOverlay) existingOverlay.remove();
        }
    },
    
    async aiGenerateMeta() {
        const title = document.getElementById('post-title').value;
        const intro = document.getElementById('post-intro').value;
        
        if (!title) {
            this.toast('Enter a title first', 'error');
            return;
        }
        
        try {
            const result = await this.api('/claude/meta-description', {
                method: 'POST',
                body: { title, content: intro }
            });
            
            document.getElementById('post-meta').value = result;
            document.getElementById('meta-count').textContent = result.length;
            this.toast('Meta description generated!');
        } catch (error) {
            this.toast(error.message, 'error');
        }
    },

    // ==================== UTILITIES ====================
    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },
    
    async logout() {
        try {
            await this.api('/auth/logout', { method: 'POST' });
        } catch (e) {}
        window.location.href = '/login';
    }
};

// ==================== CLAUDE PANEL ====================
const Claude = {
    isOpen: false,
    messages: [],
    currentPostId: null,
    
    open(postId = null) {
        this.currentPostId = postId;
        document.getElementById('claude-panel').classList.add('open');
        this.isOpen = true;
        
        // Show context-aware welcome message if we have a post
        if (postId && this.messages.length === 0) {
            const postTitle = document.getElementById('post-title')?.value || 'this post';
            this.messages.push({ 
                role: 'assistant', 
                content: `I can help you refine "${postTitle}". Try asking me to:\n\n• "Make the intro more casual"\n• "Shorten section 2"\n• "Remove the carousel from section 3"\n• "Rewrite the outro with a stronger call to action"\n• "Change the CTA in section 1 to Shop Sunglasses"\n\nWhat would you like to change?`
            });
            this.renderMessages();
        }
    },
    
    close() {
        document.getElementById('claude-panel').classList.remove('open');
        this.isOpen = false;
    },
    
    toggle() {
        this.isOpen ? this.close() : this.open(this.currentPostId);
    },
    
    // Get current post state from the form
    getCurrentPostState() {
        const sections = [];
        document.querySelectorAll('.section-item').forEach((el, i) => {
            sections.push({
                index: i,
                heading: el.querySelector('.section-heading')?.value || '',
                content: el.querySelector('.section-content')?.value || '',
                cta_text: el.querySelector('.section-cta-text')?.value || '',
                cta_url: el.querySelector('.section-cta-url')?.value || '',
                carousel_brand_id: el.querySelector('.section-carousel-brand')?.value || null,
                carousel_category_id: el.querySelector('.section-carousel-category')?.value || null
            });
        });
        
        return {
            id: this.currentPostId,
            title: document.getElementById('post-title')?.value || '',
            intro_content: document.getElementById('post-intro')?.value || '',
            outro_content: document.getElementById('post-outro')?.value || '',
            meta_description: document.getElementById('post-meta')?.value || '',
            sections: sections
        };
    },
    
    // Apply updates from Claude to the form
    applyUpdates(updates) {
        if (!updates) return;
        
        let changesMade = [];
        
        if (updates.title !== undefined) {
            document.getElementById('post-title').value = updates.title;
            changesMade.push('Title');
        }
        
        if (updates.intro_content !== undefined) {
            document.getElementById('post-intro').value = updates.intro_content;
            changesMade.push('Intro');
        }
        
        if (updates.outro_content !== undefined) {
            document.getElementById('post-outro').value = updates.outro_content;
            changesMade.push('Outro');
        }
        
        if (updates.meta_description !== undefined) {
            document.getElementById('post-meta').value = updates.meta_description;
            document.getElementById('meta-count').textContent = updates.meta_description.length;
            changesMade.push('Meta description');
        }
        
        if (updates.sections !== undefined && Array.isArray(updates.sections)) {
            const sectionsContainer = document.getElementById('sections-container');
            
            updates.sections.forEach(sectionUpdate => {
                const sectionEl = document.querySelector(`.section-item[data-index="${sectionUpdate.index}"]`);
                
                if (sectionEl) {
                    // Update existing section
                    if (sectionUpdate.heading !== undefined) {
                        sectionEl.querySelector('.section-heading').value = sectionUpdate.heading;
                    }
                    if (sectionUpdate.content !== undefined) {
                        sectionEl.querySelector('.section-content').value = sectionUpdate.content;
                    }
                    if (sectionUpdate.cta_text !== undefined) {
                        sectionEl.querySelector('.section-cta-text').value = sectionUpdate.cta_text;
                    }
                    if (sectionUpdate.cta_url !== undefined) {
                        sectionEl.querySelector('.section-cta-url').value = sectionUpdate.cta_url;
                    }
                    if (sectionUpdate.carousel_brand_id !== undefined) {
                        const brandSelect = sectionEl.querySelector('.section-carousel-brand');
                        if (brandSelect) brandSelect.value = sectionUpdate.carousel_brand_id || '';
                    }
                    if (sectionUpdate.carousel_category_id !== undefined) {
                        const catSelect = sectionEl.querySelector('.section-carousel-category');
                        if (catSelect) catSelect.value = sectionUpdate.carousel_category_id || '';
                    }
                    changesMade.push(`Section ${sectionUpdate.index + 1} updated`);
                } else if (sectionsContainer) {
                    // Create new section - ensure we have brand/category lists
                    if (!App.brandsList || !App.categoriesList) {
                        console.warn('Brands/categories list not loaded, section may not have carousel options');
                    }
                    
                    const newSection = {
                        heading: sectionUpdate.heading || '',
                        content: sectionUpdate.content || '',
                        cta_text: sectionUpdate.cta_text || '',
                        cta_url: sectionUpdate.cta_url || '',
                        carousel_brand_id: sectionUpdate.carousel_brand_id || null,
                        carousel_category_id: sectionUpdate.carousel_category_id || null
                    };
                    
                    // Log what we're creating for debugging
                    console.log('Creating new section with carousel:', {
                        brand_id: newSection.carousel_brand_id,
                        category_id: newSection.carousel_category_id
                    });
                    
                    // Get current section count
                    const existingSections = sectionsContainer.querySelectorAll('.section-item').length;
                    const newIndex = sectionUpdate.index !== undefined ? sectionUpdate.index : existingSections;
                    
                    // Render new section using App's renderSection
                    const sectionHtml = App.renderSection(newSection, newIndex);
                    sectionsContainer.insertAdjacentHTML('beforeend', sectionHtml);
                    
                    // After inserting, manually set the select values (in case renderSection didn't match)
                    setTimeout(() => {
                        const newSectionEl = document.querySelector(`.section-item[data-index="${newIndex}"]`);
                        if (newSectionEl && newSection.carousel_brand_id) {
                            const brandSelect = newSectionEl.querySelector('.section-carousel-brand');
                            if (brandSelect) {
                                brandSelect.value = newSection.carousel_brand_id;
                                console.log('Set brand select to:', newSection.carousel_brand_id, 'Result:', brandSelect.value);
                            }
                        }
                        if (newSectionEl && newSection.carousel_category_id) {
                            const catSelect = newSectionEl.querySelector('.section-carousel-category');
                            if (catSelect) {
                                catSelect.value = newSection.carousel_category_id;
                                console.log('Set category select to:', newSection.carousel_category_id, 'Result:', catSelect.value);
                            }
                        }
                    }, 100);
                    
                    changesMade.push(`Section ${newIndex + 1} created`);
                }
            });
        }
        
        if (changesMade.length > 0) {
            App.toast(`Updated: ${changesMade.join(', ')}`, 'success');
        }
        
        return changesMade;
    },
    
    async send(message) {
        if (!message.trim()) return;
        
        this.messages.push({ role: 'user', content: message });
        this.renderMessages();
        
        document.getElementById('claude-input').value = '';
        document.getElementById('claude-send').disabled = true;
        document.getElementById('claude-send').textContent = '⏳';
        document.getElementById('claude-input').disabled = true;
        document.getElementById('claude-input').placeholder = 'Claude is thinking...';
        
        // Add typing indicator
        this.messages.push({ role: 'assistant', content: 'typing', isTyping: true });
        this.renderMessages();
        
        try {
            const postState = this.getCurrentPostState();
            
            const response = await App.api('/claude/post-assistant', { 
                method: 'POST', 
                body: { 
                    message,
                    post: postState,
                    history: this.messages.filter(m => !m.isTyping).slice(-10)
                } 
            });
            
            // Remove typing indicator
            this.messages = this.messages.filter(m => !m.isTyping);
            
            // Apply any updates Claude suggested
            if (response.updates) {
                this.applyUpdates(response.updates);
            }
            
            // Add Claude's response message
            let assistantMessage = response.message;
            
            // If post is already on WordPress and changes were made, offer to push
            if (response.updates && postState.id) {
                const wpPostId = document.querySelector('[data-wp-post-id]')?.dataset.wpPostId;
                if (wpPostId) {
                    assistantMessage += '\n\n✅ Changes applied! Click "Save Post" then "Update in WordPress" to push changes live.';
                } else {
                    assistantMessage += '\n\n✅ Changes applied! Remember to save the post.';
                }
            }
            
            this.messages.push({ role: 'assistant', content: assistantMessage });
            this.renderMessages();
            
        } catch (error) {
            // Remove typing indicator
            this.messages = this.messages.filter(m => !m.isTyping);
            
            App.toast(error.message, 'error');
            this.messages.push({ role: 'assistant', content: 'Sorry, I encountered an error. Please try again.' });
            this.renderMessages();
        } finally {
            document.getElementById('claude-send').disabled = false;
            document.getElementById('claude-send').textContent = 'Send';
            document.getElementById('claude-input').disabled = false;
            document.getElementById('claude-input').placeholder = 'Ask Claude to refine your post...';
        }
    },
    
    renderMessages() {
        const container = document.getElementById('claude-messages');
        if (!container) return;
        
        // Ensure typing animation CSS exists
        if (!document.getElementById('typing-animation-css')) {
            const style = document.createElement('style');
            style.id = 'typing-animation-css';
            style.textContent = `
                @keyframes typingDot {
                    0%, 60%, 100% { opacity: 0.2; transform: scale(0.8); }
                    30% { opacity: 1; transform: scale(1); }
                }
                .typing-dots span {
                    display: inline-block;
                    color: var(--text-primary);
                    font-size: 14px;
                }
            `;
            document.head.appendChild(style);
        }
        
        container.innerHTML = this.messages.map(msg => {
            if (msg.isTyping) {
                return `
                    <div class="claude-message claude-message-assistant" style="margin-bottom:16px;padding:12px;background:var(--bg-secondary);border-left:2px solid var(--text-primary);">
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div class="typing-dots">
                                <span style="animation:typingDot 1.4s infinite;animation-delay:0s;">●</span>
                                <span style="animation:typingDot 1.4s infinite;animation-delay:0.2s;">●</span>
                                <span style="animation:typingDot 1.4s infinite;animation-delay:0.4s;">●</span>
                            </div>
                            <span style="color:var(--text-muted);font-size:13px;">Claude is thinking...</span>
                        </div>
                    </div>
                `;
            }
            return `
                <div class="claude-message claude-message-${msg.role}" style="margin-bottom:16px;padding:12px;${msg.role === 'user' ? 'background:var(--bg-tertiary);margin-left:40px;' : 'background:var(--bg-secondary);border-left:2px solid var(--text-primary);'}">
                    ${App.escapeHtml(msg.content).replace(/\n/g, '<br>')}
                </div>
            `;
        }).join('');
        container.scrollTop = container.scrollHeight;
    },
    
    reset() {
        this.messages = [];
        this.currentPostId = null;
    }
};

// Initialize
document.addEventListener('DOMContentLoaded', () => App.init());

window.App = App;
window.Claude = Claude;