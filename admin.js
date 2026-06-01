// MIYIZE Admin Panel Module
const ADMIN_PASS = process.env.MIYIZE_ADMIN_PASS || 'miyize2024';
const sessions = new Set();
const catMap = {latest:'\u0ca4\u0cbe\u0c9c\u0cbe \u0cb8\u0cc1\u0ca6\u0ccd\u0ca6\u0cbf',karnataka:'\u0c95\u0cb0\u0ccd\u0ca8\u0cbe\u0c9f\u0c95',india:'\u0cad\u0cbe\u0cb0\u0ca4',world:'\u0cb5\u0cbf\u0cb6\u0ccd\u0cb5',business:'\u0cb5\u0ccd\u0caf\u0cbe\u0caa\u0cbe\u0cb0',sports:'\u0c95\u0ccd\u0cb0\u0cc0\u0ca1\u0cc6',cinema:'\u0cb8\u0cbf\u0ca8\u0cbf\u0cae\u0cbe',technology:'\u0ca4\u0c82\u0ca4\u0ccd\u0cb0\u0c9c\u0ccd\u0c9e\u0cbe\u0ca8','fact-check':'\u0cab\u0ccd\u0caf\u0cbe\u0c95\u0ccd\u0c9f\u0ccd \u0c9a\u0cc6\u0c95\u0ccd'};

function adminShell(title, nav, body) {
    return `<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>${title} — MIYIZE Admin</title><link rel="stylesheet" href="/assets/css/admin.css"></head><body>
<div class="admin-wrap">
<aside class="admin-sidebar">
  <div class="admin-sidebar__brand"><strong>MIYIZE</strong><span>ADMIN</span></div>
  <nav class="admin-nav">
    <div class="admin-nav__section">Main</div>
    <a href="/admin" class="${nav==='dash'?'is-active':''}"><svg viewBox="0 0 24 24"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>Dashboard</a>
    <a href="/admin/articles" class="${nav==='articles'?'is-active':''}"><svg viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm-1 2l5 5h-5V4zM6 20V4h6v7h6v9H6z"/></svg>Articles</a>
    <a href="/admin/seo" class="${nav==='seo'?'is-active':''}"><svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>SEO Audit</a>
    <div class="admin-nav__section">Actions</div>
    <a href="/admin/refresh" class="${nav==='refresh'?'is-active':''}"><svg viewBox="0 0 24 24"><path d="M17.65 6.35A7.96 7.96 0 0 0 12 4c-4.42 0-8 3.58-8 8s3.58 8 8 8c3.73 0 6.84-2.55 7.73-6h-2.08A5.99 5.99 0 0 1 12 18c-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/></svg>Force Refresh</a>
  </nav>
  <div class="admin-sidebar__footer"><a href="/">← Back to Site</a></div>
</aside>
<main class="admin-main">${body}</main>
</div></body></html>`;
}

function loginPage(error='') {
    return `<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Login — MIYIZE Admin</title><link rel="stylesheet" href="/assets/css/admin.css"></head><body>
<div class="login-wrap"><div class="login-box glass">
  <h1>MIYIZE</h1><p>Admin Panel Login</p>
  ${error?`<div style="color:#ef4444;font-size:13px;margin-bottom:12px">${error}</div>`:''}
  <form method="POST" action="/admin/login">
    <input class="form-input" type="password" name="pass" placeholder="Password" autofocus>
    <button class="btn btn-primary" style="width:100%;margin-top:8px" type="submit">Login</button>
  </form>
</div></div></body></html>`;
}

function checkAuth(req) {
    const cookie = (req.headers.cookie||'').match(/miyize_admin=([^;]+)/);
    return cookie && sessions.has(cookie[1]);
}

function dashboardPage(allArticles, state) {
    const total = allArticles.length;
    const withImg = allArticles.filter(a=>a.image).length;
    const cats = {};
    allArticles.forEach(a=>{cats[a.category]=(cats[a.category]||0)+1;});
    const catRows = Object.entries(cats).map(([k,v])=>`<tr><td>${k}</td><td>${v}</td><td><span class="pill pill-green">Active</span></td></tr>`).join('');

    return adminShell('Dashboard','dash',`
<div class="admin-topbar"><h1>📊 Dashboard</h1><div class="admin-topbar__actions"><a class="btn btn-primary" href="/admin/refresh">🔄 Force Refresh</a></div></div>
<div class="stats-grid">
  <div class="stat-card glass"><div class="stat-card__icon blue">📰</div><div class="stat-card__val">${total}</div><div class="stat-card__label">Total Articles</div><div class="stat-card__3d"></div></div>
  <div class="stat-card glass"><div class="stat-card__icon green">🖼️</div><div class="stat-card__val">${withImg}</div><div class="stat-card__label">With Images</div><div class="stat-card__3d"></div></div>
  <div class="stat-card glass"><div class="stat-card__icon amber">📂</div><div class="stat-card__val">${Object.keys(cats).length}</div><div class="stat-card__label">Categories</div><div class="stat-card__3d"></div></div>
  <div class="stat-card glass"><div class="stat-card__icon red">⏰</div><div class="stat-card__val">${state.last_refresh_at?new Date(state.last_refresh_at).toLocaleTimeString('en-IN',{hour:'2-digit',minute:'2-digit'}):'—'}</div><div class="stat-card__label">Last Refresh</div><div class="stat-card__3d"></div></div>
</div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
  <div class="glass" style="padding:18px"><h3 style="margin-bottom:10px;font-size:14px;color:#888">Category Distribution</h3><table class="admin-table"><thead><tr><th>Category</th><th>Count</th><th>Status</th></tr></thead><tbody>${catRows}</tbody></table></div>
  <div class="glass" style="padding:18px"><h3 style="margin-bottom:10px;font-size:14px;color:#888">System Status</h3>
    <div style="display:grid;gap:8px;font-size:13px">
      <div style="display:flex;justify-content:space-between"><span style="color:#888">Auto Writing</span><span class="pill pill-green">ON</span></div>
      <div style="display:flex;justify-content:space-between"><span style="color:#888">Auto Images</span><span class="pill pill-green">ON</span></div>
      <div style="display:flex;justify-content:space-between"><span style="color:#888">Auto Publish</span><span class="pill pill-green">ON</span></div>
      <div style="display:flex;justify-content:space-between"><span style="color:#888">RSS Feeds</span><span class="pill pill-blue">9 Active</span></div>
      <div style="display:flex;justify-content:space-between"><span style="color:#888">Refresh Interval</span><span style="color:#e0e0e0">10 min</span></div>
    </div>
  </div>
</div>`);
}

function articlesPage(allArticles, query='') {
    const filtered = query ? allArticles.filter(a=>(a.title+a.category+a.source).toLowerCase().includes(query.toLowerCase())) : allArticles;
    const rows = filtered.slice(0,60).map(a=>{
        const pub = a.published!==false;
        return `<tr>
<td class="title-cell"><a href="/admin/article/${encodeURIComponent(a.slug)}">${(a.title||'').slice(0,65)}${(a.title||'').length>65?'…':''}</a></td>
<td><span class="pill pill-blue">${a.category||'latest'}</span></td>
<td>${a.source||'—'}</td>
<td><span class="pill ${pub?'pill-green':'pill-red'}">${pub?'Published':'Draft'}</span></td>
<td>${a.trend_score||'—'}</td>
<td style="font-size:12px;color:#666">${a.published_at?new Date(a.published_at).toLocaleDateString('en-IN',{day:'2-digit',month:'short'}):'—'}</td>
<td><a class="btn btn-ghost btn-sm" href="/admin/article/${encodeURIComponent(a.slug)}">Edit</a></td>
</tr>`;}).join('');

    return adminShell('Articles','articles',`
<div class="admin-topbar"><h1>📰 Articles (${filtered.length})</h1><div class="admin-topbar__actions">
  <form class="admin-search" method="get" action="/admin/articles"><input name="q" value="${query}" placeholder="Search articles..."><button type="submit">🔍</button></form>
</div></div>
<div class="glass" style="overflow:hidden"><table class="admin-table"><thead><tr><th>Title</th><th>Category</th><th>Source</th><th>Status</th><th>Trend</th><th>Date</th><th></th></tr></thead><tbody>${rows||'<tr><td colspan="7" style="text-align:center;padding:30px;color:#666">No articles found</td></tr>'}</tbody></table></div>`);
}

function editPage(article) {
    if(!article) return adminShell('Not Found','articles','<div style="padding:40px;text-align:center"><h2>Article not found</h2><a href="/admin/articles">← Back</a></div>');
    const catOpts = Object.entries(catMap).map(([k,v])=>`<option value="${k}" ${article.category===k?'selected':''}>${v}</option>`).join('');
    const esc = s => String(s||'').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;');

    return adminShell('Edit Article','articles',`
<div class="admin-topbar"><h1>✏️ Edit Article</h1><div class="admin-topbar__actions"><a class="btn btn-ghost" href="/admin/articles">← Back</a><a class="btn btn-ghost" href="/article/${encodeURIComponent(article.slug)}" target="_blank">👁️ View</a></div></div>
<form method="POST" action="/admin/article/${encodeURIComponent(article.slug)}">
<div style="display:grid;grid-template-columns:1fr 300px;gap:16px;align-items:start">
<div class="glass" style="padding:20px">
  <div class="form-group"><label>Title</label><input class="form-input" name="title" value="${esc(article.title)}"></div>
  <div class="form-group"><label>Summary</label><textarea class="form-input" name="summary" rows="3">${esc(article.summary)}</textarea></div>
  <div class="form-group"><label>Full Article Content</label><textarea class="form-input" name="full_content" rows="12">${esc(article.full_content)}</textarea></div>
  <div class="form-row">
    <div class="form-group"><label>SEO Title</label><input class="form-input" name="seo_title" value="${esc(article.seo_title)}"></div>
    <div class="form-group"><label>Meta Description</label><input class="form-input" name="meta_description" value="${esc(article.meta_description)}"></div>
  </div>
  <div class="form-group"><label>Tags (comma separated)</label><input class="form-input" name="tags" value="${esc((article.tags||[]).join(', '))}"></div>
  <div class="form-actions"><button class="btn btn-primary" type="submit">💾 Save Changes</button>
    <a class="btn ${article.published!==false?'btn-danger':'btn-success'}" href="/admin/article/${encodeURIComponent(article.slug)}/${article.published!==false?'unpublish':'publish'}">${article.published!==false?'⏸ Unpublish':'▶ Publish'}</a>
    <a class="btn btn-danger" href="/admin/article/${encodeURIComponent(article.slug)}/delete" onclick="return confirm('Delete this article?')">🗑 Delete</a>
  </div>
</div>
<div style="display:grid;gap:14px">
  <div class="glass" style="padding:16px">
    <div class="form-group"><label>Category</label><select class="form-input" name="category">${catOpts}</select></div>
    <div class="form-group"><label>Image URL</label><input class="form-input" name="image" value="${esc(article.image)}"></div>
    ${article.image?`<img src="${esc(article.image)}" style="border-radius:6px;margin-top:8px" onerror="this.style.display='none'">`:''}
    <div class="form-group"><label>Source</label><input class="form-input" name="source" value="${esc(article.source)}" readonly></div>
    <div class="form-group"><label>Source URL</label><input class="form-input" name="source_url" value="${esc(article.source_url)}" readonly></div>
  </div>
  <div class="glass" style="padding:16px">
    <h3 style="font-size:13px;color:#888;margin-bottom:8px">Quick Info</h3>
    <div style="display:grid;gap:6px;font-size:12px">
      <div style="display:flex;justify-content:space-between"><span style="color:#666">Trend Score</span><strong style="color:#60a5fa">${article.trend_score||0}/100</strong></div>
      <div style="display:flex;justify-content:space-between"><span style="color:#666">Reading Time</span><strong>${article.reading_minutes||1} min</strong></div>
      <div style="display:flex;justify-content:space-between"><span style="color:#666">Auto Written</span><span class="pill ${article.auto_written?'pill-green':'pill-gray'}">${article.auto_written?'Yes':'No'}</span></div>
      <div style="display:flex;justify-content:space-between"><span style="color:#666">Status</span><span class="pill ${article.published!==false?'pill-green':'pill-red'}">${article.published!==false?'Published':'Draft'}</span></div>
    </div>
  </div>
</div>
</div></form>`);
}

function seoPage(allArticles) {
    const issues = [];
    allArticles.forEach(a=>{
        const p = [];
        if(!a.image) p.push({t:'No Image',c:'fail'});
        if(!a.meta_description||a.meta_description.length<50) p.push({t:'Short meta desc',c:'warn'});
        if(!a.seo_title||a.seo_title.length<20) p.push({t:'Short SEO title',c:'warn'});
        if((a.title||'').length<10) p.push({t:'Title too short',c:'fail'});
        if(!a.full_content||a.full_content.length<200) p.push({t:'Short content',c:'warn'});
        if(p.length) issues.push({article:a,problems:p});
    });
    const ok = allArticles.length - issues.length;
    const rows = issues.slice(0,40).map(i=>`<div class="seo-item">
      <div><a href="/admin/article/${encodeURIComponent(i.article.slug)}" style="font-weight:600;color:#e0e0e0">${(i.article.title||'').slice(0,60)}</a></div>
      <div style="display:flex;gap:6px;margin-left:auto">${i.problems.map(p=>`<span class="pill pill-${p.c==='fail'?'red':'amber'}">${p.t}</span>`).join('')}</div>
    </div>`).join('');

    return adminShell('SEO Audit','seo',`
<div class="admin-topbar"><h1>🔍 SEO Audit</h1></div>
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr)">
  <div class="stat-card glass"><div class="stat-card__icon green">✅</div><div class="stat-card__val">${ok}</div><div class="stat-card__label">SEO Healthy</div></div>
  <div class="stat-card glass"><div class="stat-card__icon amber">⚠️</div><div class="stat-card__val">${issues.length}</div><div class="stat-card__label">Need Attention</div></div>
  <div class="stat-card glass"><div class="stat-card__icon blue">📊</div><div class="stat-card__val">${Math.round(ok/Math.max(1,allArticles.length)*100)}%</div><div class="stat-card__label">SEO Score</div></div>
</div>
<div class="glass" style="overflow:hidden">${rows||'<div style="padding:30px;text-align:center;color:#666">All articles are SEO healthy! 🎉</div>'}</div>`);
}

module.exports = { ADMIN_PASS, sessions, adminShell, loginPage, checkAuth, dashboardPage, articlesPage, editPage, seoPage };
