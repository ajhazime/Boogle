<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Boogle</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Mono:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg: #0a0a0a;
            --surface: #111111;
            --border: #222222;
            --accent: #c8f05a;
            --accent-dim: rgba(200, 240, 90, 0.12);
            --text: #e8e8e0;
            --text-dim: #666660;
            --text-muted: #333330;
        }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: 'DM Mono', monospace;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Grid overlay */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image:
                linear-gradient(rgba(200,240,90,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(200,240,90,0.03) 1px, transparent 1px);
            background-size: 60px 60px;
            pointer-events: none;
            z-index: 0;
        }

        .container {
            position: relative;
            z-index: 1;
            max-width: 800px;
            margin: 0 auto;
            padding: 0 24px;
        }

        /* Hero */
        .hero {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            transition: min-height 0.6s cubic-bezier(0.16, 1, 0.3, 1);
            padding: 80px 0 40px;
        }

        .hero.has-results {
            min-height: auto;
            padding: 60px 0 40px;
        }

        .logo-wrap {
            text-align: center;
            margin-bottom: 48px;
            animation: fadeUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) both;
        }

        .logo {
            font-family: 'DM Serif Display', serif;
            font-size: clamp(64px, 12vw, 100px);
            letter-spacing: -4px;
            line-height: 0.9;
            color: var(--text);
        }

        .logo span {
            color: var(--accent);
        }

        .tagline {
            font-size: 11px;
            letter-spacing: 4px;
            text-transform: uppercase;
            color: var(--text-dim);
            margin-top: 12px;
        }

        /* Search box */
        .search-wrap {
            width: 100%;
            animation: fadeUp 0.8s 0.1s cubic-bezier(0.16, 1, 0.3, 1) both;
        }

        .search-box {
            display: flex;
            align-items: center;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 4px;
            overflow: hidden;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .search-box:focus-within {
            border-color: var(--accent);
            box-shadow: 0 0 0 1px var(--accent), 0 0 40px rgba(200,240,90,0.08);
        }

        .search-input {
            flex: 1;
            background: transparent;
            border: none;
            outline: none;
            padding: 18px 20px;
            font-family: 'DM Mono', monospace;
            font-size: 15px;
            color: var(--text);
            caret-color: var(--accent);
        }

        .search-input::placeholder {
            color: var(--text-muted);
        }

        .search-btn {
            padding: 18px 24px;
            background: var(--accent);
            border: none;
            cursor: pointer;
            font-family: 'DM Mono', monospace;
            font-size: 11px;
            font-weight: 500;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #0a0a0a;
            transition: opacity 0.2s;
            white-space: nowrap;
        }

        .search-btn:hover { opacity: 0.85; }
        .search-btn:active { opacity: 0.7; }

        /* Stats bar */
        .stats {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 16px;
            font-size: 11px;
            color: var(--text-dim);
            letter-spacing: 1px;
            animation: fadeIn 0.3s ease both;
        }

        .stats-left { display: flex; gap: 20px; }

        /* Results */
        .results {
            margin-top: 48px;
            display: flex;
            flex-direction: column;
            gap: 2px;
            animation: fadeUp 0.4s cubic-bezier(0.16, 1, 0.3, 1) both;
        }

        .result-item {
            display: flex;
            align-items: flex-start;
            gap: 20px;
            padding: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            transition: background 0.15s, border-color 0.15s;
        }

        .result-item:hover {
            background: var(--surface);
            border-color: var(--border);
        }

        .result-rank {
            font-size: 11px;
            color: var(--text-muted);
            padding-top: 3px;
            min-width: 24px;
            text-align: right;
        }

        .result-body { flex: 1; min-width: 0; }

        .result-url {
            font-size: 11px;
            color: var(--text-dim);
            letter-spacing: 0.5px;
            margin-bottom: 6px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .result-title {
            font-family: 'DM Serif Display', serif;
            font-size: 18px;
            color: var(--text);
            margin-bottom: 6px;
            line-height: 1.3;
        }

        .result-score {
            font-size: 10px;
            color: var(--accent);
            letter-spacing: 1px;
        }

        .result-divider {
            width: 1px;
            background: var(--border);
            align-self: stretch;
            flex-shrink: 0;
        }

        /* Loading */
        .loader {
            display: none;
            text-align: center;
            padding: 60px 0;
            color: var(--text-dim);
            font-size: 12px;
            letter-spacing: 3px;
            text-transform: uppercase;
        }

        .loader.active { display: block; }

        .loader::after {
            content: '';
            display: block;
            width: 2px;
            height: 40px;
            background: var(--accent);
            margin: 20px auto 0;
            animation: blink 1s step-end infinite;
        }

        /* Empty */
        .empty {
            display: none;
            text-align: center;
            padding: 60px 0;
            color: var(--text-dim);
        }

        .empty.active { display: block; }
        .empty-title { font-family: 'DM Serif Display', serif; font-size: 32px; margin-bottom: 12px; }
        .empty-sub { font-size: 12px; letter-spacing: 2px; text-transform: uppercase; }

        /* Footer */
        .footer {
            text-align: center;
            padding: 60px 0 40px;
            font-size: 11px;
            color: var(--text-muted);
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="hero" id="hero">
        <div class="logo-wrap">
            <div class="logo"><span>B</span>oogle</div>
            <div class="tagline">Search the web</div>
        </div>

        <div class="search-wrap">
            <div class="search-box">
                <input
                    type="text"
                    class="search-input"
                    id="searchInput"
                    placeholder="search anything..."
                    autofocus
                >
                <button class="search-btn" id="searchBtn">Search</button>
            </div>
            <div class="stats" id="stats" style="display:none">
                <div class="stats-left">
                    <span id="resultCount">—</span>
                    <span id="queryTime">—</span>
                </div>
                <span id="queryDisplay"></span>
            </div>
        </div>

        <div class="loader" id="loader">Searching</div>

        <div class="empty" id="empty">
            <div class="empty-title">No results</div>
            <div class="empty-sub">Try different keywords</div>
        </div>

        <div class="results" id="results"></div>
    </div>

    <div class="footer">Boogle &mdash; Built with Go, Python, PHP &amp; AWS</div>
</div>

<script>
    const input = document.getElementById('searchInput');
    const btn = document.getElementById('searchBtn');
    const resultsEl = document.getElementById('results');
    const loader = document.getElementById('loader');
    const empty = document.getElementById('empty');
    const stats = document.getElementById('stats');
    const hero = document.getElementById('hero');

    function extractTitle(url) {
        try {
            const path = new URL(url).pathname;
            const parts = path.split('/').filter(Boolean);
            const last = parts[parts.length - 1] || '';
            if (last === 'index.html') {
                const folder = parts[parts.length - 2] || '';
                return folder.replace(/[-_]/g, ' ').replace(/\d+$/, '').trim();
            }
            return last.replace(/[-_]/g, ' ').replace('.html', '').replace(/\d+/g, '').trim();
        } catch { return url; }
    }

    function formatScore(score) {
        return (score * 1000000).toFixed(2) + ' pts';
    }

    async function doSearch() {
        const q = input.value.trim();
        window.history.pushState({}, '', `/search?q=${encodeURIComponent(q)}`)
        if (!q) return;

        resultsEl.innerHTML = '';
        loader.classList.add('active');
        empty.classList.remove('active');
        stats.style.display = 'none';
        hero.classList.add('has-results');

        const t0 = performance.now();

        try {
            const res = await fetch(`/search?q=${encodeURIComponent(q)}`);
            const data = await res.json();
            const elapsed = ((performance.now() - t0) / 1000).toFixed(2);

            loader.classList.remove('active');

            const entries = Object.entries(data);

            if (entries.length === 0) {
                empty.classList.add('active');
                return;
            }

            document.getElementById('resultCount').textContent = `${entries.length} results`;
            document.getElementById('queryTime').textContent = `${elapsed}s`;
            document.getElementById('queryDisplay').textContent = `"${q}"`;
            stats.style.display = 'flex';

            entries.forEach(([url, score], i) => {
                const item = document.createElement('a');
                item.href = url;
                item.target = '_blank';
                item.rel = 'noopener';
                item.className = 'result-item';

                const title = extractTitle(url) || url;

                item.innerHTML = `
                    <div class="result-rank">${String(i + 1).padStart(2, '0')}</div>
                    <div class="result-divider"></div>
                    <div class="result-body">
                        <div class="result-url">${url}</div>
                        <div class="result-title">${title}</div>
                        <div class="result-score">${formatScore(score)}</div>
                    </div>
                `;
                resultsEl.appendChild(item);
            });

        } catch (err) {
            loader.classList.remove('active');
            empty.classList.add('active');
        }
    }

    btn.addEventListener('click', doSearch);
    btn.addEventListener('touchend', doSearch);
    input.addEventListener('keydown', e => { if (e.key === 'Enter') doSearch(); });
</script>
</body>
</html> 