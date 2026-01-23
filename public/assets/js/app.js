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
                    <div style="display:flex;gap:8px;">
                        <button class="btn btn-secondary" onclick="App.navigate('/autopilot')">⚙ Auto-Pilot Settings</button>
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
                            <div style="display:flex;gap:8px;">
                                <button class="btn btn-secondary" onclick="App.generateContent()" id="generate-btn">Generate Content</button>
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
    
    async generateContent() {
        const btn = document.getElementById('generate-btn');
        btn.disabled = true;
        btn.textContent = 'Generating...';
        
        try {
            const result = await this.api('/content/generate-pending', { method: 'POST' });
            this.toast(result.message || 'Content generated!', 'success');
            this.loadDashboard();
        } catch (error) {
            this.toast(error.message, 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Generate Content';
        }
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
            const [events, categories, authors, brands, productCategories] = await Promise.all([
                this.api('/events'),
                this.api('/categories'),
                this.api('/authors'),
                this.api('/products/brands'),
                this.api('/products/categories')
            ]);
            
            // Store brands and categories for use in renderSection
            this.brandsList = brands;
            this.categoriesList = productCategories;
            
            let post = {
                title: '',
                intro_content: '',
                outro_content: '',
                meta_description: '',
                status: 'idea',
                seasonal_event_id: '',
                wp_category_id: '',
                wp_author_id: '',
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
                                        ${events.map(e => `<option value="${e.id}" ${post.seasonal_event_id == e.id ? 'selected' : ''}>${this.escapeHtml(e.name)}</option>`).join('')}
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Category</label>
                                    <select id="post-category" class="form-input form-select">
                                        <option value="">— Select —</option>
                                        ${categories.map(c => `<option value="${c.id}" ${post.wp_category_id == c.id ? 'selected' : ''}>${this.escapeHtml(c.name)}</option>`).join('')}
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Author</label>
                                    <select id="post-author" class="form-input form-select">
                                        <option value="">— Select —</option>
                                        ${authors.map(a => `<option value="${a.id}" ${post.wp_author_id == a.id ? 'selected' : ''}>${this.escapeHtml(a.name)}</option>`).join('')}
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
    async loadRoadmap() {
        const main = document.getElementById('main-content');
        main.innerHTML = '<div class="loading"><div class="spinner"></div></div>';
        
        const now = new Date();
        const year = now.getFullYear();
        const month = now.getMonth() + 1;
        
        try {
            const data = await this.api(`/roadmap/${year}/${month}`);
            
            main.innerHTML = `
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Content Roadmap</h1>
                        <p class="page-subtitle">${new Date(year, month-1).toLocaleDateString('en-GB', { month: 'long', year: 'numeric' })}</p>
                    </div>
                    <button class="btn btn-primary" onclick="App.navigate('/posts/new')">+ New Post</button>
                </div>
                
                ${data.events.length ? `
                <div class="card" style="margin-bottom:24px;">
                    <div class="card-header"><span class="card-title">Active Events This Month</span></div>
                    <div class="card-body" style="display:flex;gap:12px;flex-wrap:wrap;">
                        ${data.events.map(e => `<span style="padding:8px 16px;background:var(--bg-tertiary);font-size:13px;">${this.escapeHtml(e.name)}</span>`).join('')}
                    </div>
                </div>
                ` : ''}
                
                <div class="card">
                    <div class="card-body">
                        <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:4px;text-align:center;">
                            <div style="padding:8px;font-weight:600;color:var(--text-secondary);">Mon</div>
                            <div style="padding:8px;font-weight:600;color:var(--text-secondary);">Tue</div>
                            <div style="padding:8px;font-weight:600;color:var(--text-secondary);">Wed</div>
                            <div style="padding:8px;font-weight:600;color:var(--text-secondary);">Thu</div>
                            <div style="padding:8px;font-weight:600;color:var(--text-secondary);">Fri</div>
                            <div style="padding:8px;font-weight:600;color:var(--text-secondary);">Sat</div>
                            <div style="padding:8px;font-weight:600;color:var(--text-secondary);">Sun</div>
                            ${this.renderCalendar(data.calendar, year, month)}
                        </div>
                    </div>
                </div>
            `;
        } catch (error) {
            main.innerHTML = `<div class="empty-state"><div class="empty-state-title">Error</div><p>${error.message}</p></div>`;
        }
    },
    
    renderCalendar(calendar, year, month) {
        const firstDay = new Date(year, month - 1, 1).getDay();
        const offset = firstDay === 0 ? 6 : firstDay - 1;
        
        let html = '';
        for (let i = 0; i < offset; i++) {
            html += '<div style="padding:8px;"></div>';
        }
        
        calendar.forEach(day => {
            const hasPost = day.posts && day.posts.length > 0;
            const isToday = day.date === new Date().toISOString().split('T')[0];
            html += `
                <div style="padding:8px;min-height:60px;background:${isToday ? 'var(--bg-tertiary)' : 'var(--bg-card)'};border:1px solid var(--border-default);">
                    <div style="font-size:12px;color:var(--text-secondary);">${day.day}</div>
                    ${hasPost ? day.posts.map(p => `<div style="font-size:10px;margin-top:4px;padding:2px 4px;background:var(--status-${p.status});color:white;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${this.escapeHtml(p.title)}</div>`).join('') : ''}
                </div>
            `;
        });
        
        return html;
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
                                    <button class="btn btn-sm btn-secondary" onclick="App.convertIdea(${idea.id})">Convert to Post</button>
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
        if (!prompt) return;
        
        const results = document.getElementById('brainstorm-results');
        results.innerHTML = '<div class="loading"><div class="spinner"></div></div>';
        
        try {
            const data = await this.api('/claude/brainstorm', { method: 'POST', body: { prompt } });
            results.innerHTML = data.map(idea => `
                <div style="padding:16px;border:1px solid var(--border-default);margin-bottom:12px;">
                    <div style="font-weight:500;">${this.escapeHtml(idea.title)}</div>
                    <div style="font-size:13px;color:var(--text-secondary);margin-top:4px;">${this.escapeHtml(idea.description)}</div>
                    <button class="btn btn-sm btn-secondary" style="margin-top:8px;" onclick="App.saveIdea('${this.escapeHtml(idea.title).replace(/'/g, "\\'")}', '${this.escapeHtml(idea.description).replace(/'/g, "\\'")}')">Save Idea</button>
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
    
    async convertIdea(id) {
        try {
            const result = await this.api(`/brainstorm/${id}/convert`, { method: 'POST' });
            this.toast('Converted to post!');
            this.navigate(`/posts/${result.post_id}`);
        } catch (error) {
            this.toast(error.message, 'error');
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
            this.toast(result.message || `${type} synced successfully!`, 'success');
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
                            <button class="btn btn-secondary" onclick="App.generateContent()">🤖 Generate Pending Content</button>
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
        this.toast('Generating content calendar...');
        try {
            const result = await this.api('/content/calendar/generate', { method: 'POST', body: { months: 3 } });
            this.toast(result.message || 'Calendar generated!', 'success');
            this.loadAutoPilot();
        } catch (error) {
            this.toast(error.message, 'error');
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
        
        this.toast('Generating content...');
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
            
            this.toast('Content generated!');
        } catch (error) {
            this.toast(error.message, 'error');
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
        
        if (updates.sections !== undefined) {
            updates.sections.forEach(sectionUpdate => {
                const sectionEl = document.querySelector(`.section-item[data-index="${sectionUpdate.index}"]`);
                if (sectionEl) {
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
                        sectionEl.querySelector('.section-carousel-brand').value = sectionUpdate.carousel_brand_id || '';
                    }
                    if (sectionUpdate.carousel_category_id !== undefined) {
                        sectionEl.querySelector('.section-carousel-category').value = sectionUpdate.carousel_category_id || '';
                    }
                    changesMade.push(`Section ${sectionUpdate.index + 1}`);
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
        document.getElementById('claude-send').textContent = '...';
        
        try {
            const postState = this.getCurrentPostState();
            
            const response = await App.api('/claude/post-assistant', { 
                method: 'POST', 
                body: { 
                    message,
                    post: postState,
                    history: this.messages.slice(-10) // Send last 10 messages for context
                } 
            });
            
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
            App.toast(error.message, 'error');
            this.messages.push({ role: 'assistant', content: 'Sorry, I encountered an error. Please try again.' });
            this.renderMessages();
        } finally {
            document.getElementById('claude-send').disabled = false;
            document.getElementById('claude-send').textContent = 'Send';
        }
    },
    
    renderMessages() {
        const container = document.getElementById('claude-messages');
        if (!container) return;
        
        container.innerHTML = this.messages.map(msg => `
            <div class="claude-message claude-message-${msg.role}" style="margin-bottom:16px;padding:12px;${msg.role === 'user' ? 'background:var(--bg-tertiary);margin-left:40px;' : 'background:var(--bg-secondary);border-left:2px solid var(--text-primary);'}">
                ${App.escapeHtml(msg.content).replace(/\n/g, '<br>')}
            </div>
        `).join('');
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