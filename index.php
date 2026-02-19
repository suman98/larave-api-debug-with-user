<?php

require './load-laravel.php';

// --- Handle form submission ---
$submittedIndex = null;
$response = null;
$rawContent = null;
$error = null;
$statusCode = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_action']) && $_POST['_action'] === 'call_api') {
    $submittedIndex = (int) ($_POST['_form_index'] ?? 0);
    $api_url     = trim($_POST['api_url'] ?? '');
    $user_id     = (int) ($_POST['user_id'] ?? 0);
    $http_method = strtoupper(trim($_POST['http_method'] ?? 'GET'));
    $payload_mode = $_POST['payload_mode'] ?? 'json';

    $payload = [];
    try {
        if ($payload_mode === 'json') {
            $raw = trim($_POST['payload_json'] ?? '{}');
            if ($raw !== '' && $raw !== '{}') {
                $decoded = json_decode($raw, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception('Invalid JSON: ' . json_last_error_msg());
                }
                $payload = $decoded ?? [];
            }
        } else {
            $keys   = $_POST['payload_keys'] ?? [];
            $values = $_POST['payload_values'] ?? [];
            foreach ($keys as $i => $key) {
                $key = trim($key);
                if ($key !== '') {
                    $payload[$key] = $values[$i] ?? '';
                }
            }
        }

        $caller     = InternalApiCaller::call($api_url, $user_id ?: null, $payload, $http_method);
        $statusCode = $caller->getStatusCode();
        $response   = $caller->getJsonContent();
        $rawContent = $caller->getResponse()->getContent();
    } catch (\Throwable $e) {
        $error = $e->getMessage() . "\n" . $e->getTraceAsString();
    }
}

// Encode response data for JS
$responseJson = json_encode([
    'index'      => $submittedIndex,
    'status'     => $statusCode,
    'body'       => $response,
    'rawContent' => $rawContent,
    'error'      => $error,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Internal API Tester</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0f1117;
            color: #e1e4e8;
            min-height: 100vh;
            padding: 2rem;
        }
        .container { max-width: 960px; margin: 0 auto; }
        .top-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
        }
        h1 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #58a6ff;
            display: flex;
            align-items: center;
            gap: .5rem;
        }
        h1 span { font-size: 1.2rem; }
        .card {
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            position: relative;
        }
        .card.highlight { border-color: #58a6ff; }
        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }
        .card-title {
            font-size: .95rem;
            font-weight: 600;
            color: #8b949e;
        }
        .row { display: flex; gap: 1rem; margin-bottom: 1rem; }
        .row > * { flex: 1; }
        label {
            display: block;
            font-size: .8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #8b949e;
            margin-bottom: .35rem;
        }
        input, select, textarea {
            width: 100%;
            padding: .6rem .75rem;
            background: #0d1117;
            border: 1px solid #30363d;
            border-radius: 6px;
            color: #e1e4e8;
            font-size: .95rem;
            font-family: 'SF Mono', 'Fira Code', monospace;
            transition: border-color .15s;
        }
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #58a6ff;
            box-shadow: 0 0 0 3px rgba(88,166,255,.15);
        }
        textarea { resize: vertical; min-height: 100px; }
        select { cursor: pointer; appearance: auto; }

        .method-select { max-width: 140px; flex: 0 0 140px; }
        .url-field { flex: 1; }

        .tabs { display: flex; gap: 0; margin-bottom: .75rem; }
        .tab-btn {
            padding: .4rem .85rem;
            background: transparent;
            border: 1px solid #30363d;
            color: #8b949e;
            cursor: pointer;
            font-size: .75rem;
            font-weight: 600;
            transition: all .15s;
        }
        .tab-btn:first-child { border-radius: 6px 0 0 6px; }
        .tab-btn:last-child { border-radius: 0 6px 6px 0; }
        .tab-btn.active {
            background: #58a6ff;
            border-color: #58a6ff;
            color: #fff;
        }
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        .kv-row { display: flex; gap: .5rem; margin-bottom: .5rem; align-items: center; }
        .kv-row input { flex: 1; }
        .btn-icon {
            background: none;
            border: 1px solid #30363d;
            color: #8b949e;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: all .15s;
        }
        .btn-icon:hover { border-color: #58a6ff; color: #58a6ff; }
        .btn-icon.danger { border-color: #da3633; color: #da3633; }
        .btn-icon.danger:hover { background: #da3633; color: #fff; }

        .btn-add-kv {
            background: transparent;
            border: 1px dashed #30363d;
            color: #8b949e;
            padding: .4rem .75rem;
            border-radius: 6px;
            cursor: pointer;
            font-size: .78rem;
            width: 100%;
            transition: all .15s;
        }
        .btn-add-kv:hover { border-color: #58a6ff; color: #58a6ff; }

        .btn-submit {
            background: #238636;
            border: none;
            color: #fff;
            padding: .6rem 1.5rem;
            border-radius: 6px;
            font-size: .9rem;
            font-weight: 600;
            cursor: pointer;
            transition: background .15s;
            display: inline-flex;
            align-items: center;
            gap: .5rem;
        }
        .btn-submit:hover { background: #2ea043; }

        .btn-add-form {
            background: #21262d;
            border: 1px solid #30363d;
            color: #58a6ff;
            padding: .55rem 1.2rem;
            border-radius: 6px;
            font-size: .85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all .15s;
            display: inline-flex;
            align-items: center;
            gap: .4rem;
        }
        .btn-add-form:hover { background: #30363d; }

        .actions-row {
            display: flex;
            align-items: center;
            gap: .75rem;
            margin-top: 1rem;
        }

        .response-section { margin-top: 1rem; display: none; }
        .response-section.visible { display: block; }
        .response-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: .5rem;
        }
        .response-header h3 { font-size: .9rem; color: #e1e4e8; }
        .status-badge {
            padding: .2rem .6rem;
            border-radius: 20px;
            font-size: .75rem;
            font-weight: 700;
            font-family: monospace;
        }
        .status-2xx { background: rgba(35,134,54,.25); color: #3fb950; }
        .status-3xx { background: rgba(88,166,255,.2); color: #58a6ff; }
        .status-4xx { background: rgba(218,54,51,.2); color: #f85149; }
        .status-5xx { background: rgba(218,54,51,.35); color: #ff7b72; }
        .status-err { background: rgba(218,54,51,.35); color: #ff7b72; }

        pre.response-body {
            background: #0d1117;
            border: 1px solid #30363d;
            border-radius: 6px;
            padding: .75rem;
            overflow-x: auto;
            font-size: .8rem;
            line-height: 1.5;
            max-height: 400px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .response-iframe {
            width: 100%;
            min-height: 400px;
            border: 1px solid #30363d;
            border-radius: 6px;
            background: #fff;
        }

        .response-toggle {
            display: flex;
            gap: 0;
            margin-bottom: .75rem;
        }
        .response-toggle button {
            padding: .35rem .75rem;
            background: transparent;
            border: 1px solid #30363d;
            color: #8b949e;
            cursor: pointer;
            font-size: .7rem;
            font-weight: 600;
            transition: all .15s;
        }
        .response-toggle button:first-child { border-radius: 6px 0 0 6px; }
        .response-toggle button:last-child { border-radius: 0 6px 6px 0; }
        .response-toggle button.active {
            background: #58a6ff;
            border-color: #58a6ff;
            color: #fff;
        }

        .divider {
            border: none;
            border-top: 1px solid #21262d;
            margin: 0;
        }

        /* JSON syntax highlighting */
        .json-key { color: #79c0ff; }
        .json-string { color: #a5d6ff; }
        .json-number { color: #f0883e; }
        .json-boolean { color: #ff7b72; }
        .json-null { color: #8b949e; font-style: italic; }

        /* Fullscreen button */
        .btn-fullscreen {
            background: none;
            border: 1px solid #30363d;
            color: #8b949e;
            width: 28px;
            height: 28px;
            border-radius: 6px;
            cursor: pointer;
            font-size: .85rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all .15s;
            margin-left: .5rem;
        }
        .btn-fullscreen:hover { border-color: #58a6ff; color: #58a6ff; }

        /* Fullscreen modal overlay */
        .fullscreen-overlay {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 9999;
            background: #0d1117;
            padding: 0;
            flex-direction: column;
        }
        .fullscreen-overlay.open { display: flex; }
        .fullscreen-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: .75rem 1.25rem;
            background: #161b22;
            border-bottom: 1px solid #30363d;
            flex-shrink: 0;
        }
        .fullscreen-toolbar h3 {
            font-size: 1rem;
            color: #e1e4e8;
            display: flex;
            align-items: center;
            gap: .5rem;
        }
        .fullscreen-toolbar .status-badge { font-size: .8rem; }
        .fullscreen-actions { display: flex; gap: .5rem; align-items: center; }
        .btn-close-fullscreen {
            background: #21262d;
            border: 1px solid #30363d;
            color: #e1e4e8;
            padding: .4rem 1rem;
            border-radius: 6px;
            cursor: pointer;
            font-size: .85rem;
            font-weight: 600;
            transition: all .15s;
        }
        .btn-close-fullscreen:hover { background: #30363d; }
        .fullscreen-body {
            flex: 1;
            overflow: auto;
            padding: 1.25rem;
        }
        .fullscreen-body pre {
            background: transparent;
            border: none;
            padding: 0;
            margin: 0;
            font-size: .85rem;
            line-height: 1.6;
            white-space: pre-wrap;
            word-break: break-word;
            max-height: none;
            overflow: visible;
            color: #e1e4e8;
        }
        .fullscreen-body iframe {
            width: 100%;
            height: 100%;
            border: none;
            border-radius: 6px;
            background: #fff;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="top-bar">
        <h1><span>⚡</span> Internal API Tester</h1>
        <button class="btn-add-form" onclick="addForm(); return false;">+ Add Request</button>
    </div>
    <div id="forms-container"></div>
</div>

<!-- Fullscreen modal -->
<div class="fullscreen-overlay" id="fullscreenOverlay">
    <div class="fullscreen-toolbar">
        <h3>Response <span class="status-badge" id="fsStatusBadge"></span></h3>
        <div class="fullscreen-actions">
            <div class="response-toggle" id="fsToggle" style="display:none;">
                <button type="button" class="active" data-fs-view="formatted">Formatted</button>
                <button type="button" data-fs-view="html">HTML Preview</button>
            </div>
            <button class="btn-close-fullscreen" onclick="closeFullscreen()">✕ Close</button>
        </div>
    </div>
    <div class="fullscreen-body" id="fsBody">
        <pre id="fsPre"></pre>
        <iframe id="fsIframe" style="display:none;" sandbox="allow-same-origin"></iframe>
    </div>
</div>

<script>
    let formCounter = 0;
    const STORAGE_KEY = 'api_tester_forms';
    const serverResponse = <?= $responseJson ?>;

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    }

    function escapeAttr(str) {
        return escapeHtml(str).replace(/"/g, '&quot;');
    }

    function statusClass(code) {
        if (code >= 200 && code < 300) return 'status-2xx';
        if (code >= 300 && code < 400) return 'status-3xx';
        if (code >= 400 && code < 500) return 'status-4xx';
        if (code >= 500) return 'status-5xx';
        return 'status-err';
    }

    function addForm(data) {
        const idx = formCounter++;
        const d = data || { http_method: 'GET', api_url: '', user_id: '', payload_json: '', payload_mode: 'kv', payload_keys: [''], payload_values: [''] };
        const container = document.getElementById('forms-container');

        const card = document.createElement('div');
        card.className = 'card';
        card.dataset.formIndex = idx;

        let kvHtml = '';
        (d.payload_keys || ['']).forEach((key, i) => {
            kvHtml += `<div class="kv-row">
                <input type="text" name="payload_keys[]" placeholder="Key" value="${escapeAttr(key)}">
                <input type="text" name="payload_values[]" placeholder="Value" value="${escapeAttr((d.payload_values || [])[i] || '')}">
                <button type="button" class="btn-icon danger" onclick="this.parentElement.remove(); saveForms();">×</button>
            </div>`;
        });

        card.innerHTML = `
            <form method="POST" onsubmit="saveBeforeSubmit(${idx})">
                <input type="hidden" name="_action" value="call_api">
                <input type="hidden" name="_form_index" value="${idx}">
                <input type="hidden" name="payload_mode" value="${escapeAttr(d.payload_mode || 'kv')}">

                <div class="card-header">
                    <span class="card-title">Request #${idx + 1}</span>
                    <button type="button" class="btn-icon danger" title="Remove request" onclick="removeForm(${idx})">🗑</button>
                </div>
                <div class="row">
                    <div class="method-select">
                        <label>Method</label>
                        <select name="http_method">
                            ${['GET','POST','PUT','PATCH','DELETE'].map(m =>
                                `<option value="${m}" ${d.http_method === m ? 'selected' : ''}>${m}</option>`
                            ).join('')}
                        </select>
                    </div>
                    <div class="url-field">
                        <label>API URL</label>
                        <input type="text" name="api_url" placeholder="/api/v1/..." value="${escapeAttr(d.api_url)}" required>
                    </div>
                    <div style="max-width:140px;flex:0 0 140px;">
                        <label>User ID</label>
                        <input type="number" name="user_id" placeholder="1" value="${escapeAttr(d.user_id)}">
                    </div>
                </div>
                <label>Payload</label>
                <div class="tabs">
                    <button type="button" class="tab-btn ${d.payload_mode !== 'kv' ? 'active' : ''}" data-tab="json" onclick="switchTab(${idx}, 'json')">JSON</button>
                    <button type="button" class="tab-btn ${d.payload_mode === 'kv' ? 'active' : ''}" data-tab="kv" onclick="switchTab(${idx}, 'kv')">Key-Value</button>
                </div>
                <div class="tab-content ${d.payload_mode !== 'kv' ? 'active' : ''}" data-tab-content="json">
                    <textarea name="payload_json" placeholder='{"key": "value"}'>${escapeHtml(d.payload_json)}</textarea>
                </div>
                <div class="tab-content ${d.payload_mode === 'kv' ? 'active' : ''}" data-tab-content="kv">
                    <div class="kv-container">${kvHtml}</div>
                    <button type="button" class="btn-add-kv" onclick="addKvRow(${idx})">+ Add Parameter</button>
                </div>
                <div class="actions-row">
                    <button type="submit" class="btn-submit">🚀 Send</button>
                </div>
            </form>
            <div class="response-section" data-response>
                <hr class="divider" style="margin: 1rem 0;">
                <div class="response-header">
                    <h3>Response</h3>
                    <div style="display:flex;align-items:center;">
                        <span class="status-badge" data-status-badge></span>
                        <button type="button" class="btn-fullscreen" data-btn-fullscreen title="Fullscreen">⛶</button>
                    </div>
                </div>
                <div class="response-toggle" data-response-toggle style="display:none;">
                    <button type="button" class="active" data-view="formatted">Formatted</button>
                    <button type="button" data-view="html">HTML Preview</button>
                </div>
                <pre class="response-body" data-response-body></pre>
                <iframe class="response-iframe" data-response-iframe style="display:none;" sandbox="allow-same-origin"></iframe>
            </div>
        `;
        container.appendChild(card);
        saveForms();
        return card;
    }

    function removeForm(idx) {
        const card = document.querySelector(`.card[data-form-index="${idx}"]`);
        if (card) card.remove();
        saveForms();
        // Re-number visible cards
        document.querySelectorAll('.card').forEach((c, i) => {
            c.querySelector('.card-title').textContent = 'Request #' + (i + 1);
        });
    }

    function switchTab(idx, tab) {
        const card = document.querySelector(`.card[data-form-index="${idx}"]`);
        if (!card) return;
        card.querySelectorAll('.tab-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
        card.querySelectorAll('.tab-content').forEach(c => c.classList.toggle('active', c.dataset.tabContent === tab));
        card.querySelector('input[name="payload_mode"]').value = tab;
        saveForms();
    }

    function addKvRow(idx) {
        const card = document.querySelector(`.card[data-form-index="${idx}"]`);
        if (!card) return;
        const container = card.querySelector('.kv-container');
        const row = document.createElement('div');
        row.className = 'kv-row';
        row.innerHTML = `
            <input type="text" name="payload_keys[]" placeholder="Key">
            <input type="text" name="payload_values[]" placeholder="Value">
            <button type="button" class="btn-icon danger" onclick="this.parentElement.remove(); saveForms();">×</button>
        `;
        container.appendChild(row);
        saveForms();
    }

    function getFormData(card) {
        const activeTab = card.querySelector('.tab-btn.active')?.dataset.tab || 'json';
        const kvKeys = [], kvValues = [];
        card.querySelectorAll('.kv-container .kv-row').forEach(row => {
            const inputs = row.querySelectorAll('input');
            kvKeys.push(inputs[0].value);
            kvValues.push(inputs[1].value);
        });
        return {
            http_method:    card.querySelector('select[name="http_method"]').value,
            api_url:        card.querySelector('input[name="api_url"]').value,
            user_id:        card.querySelector('input[name="user_id"]').value,
            payload_json:   card.querySelector('textarea[name="payload_json"]').value,
            payload_mode:   activeTab,
            payload_keys:   kvKeys,
            payload_values: kvValues,
        };
    }

    // Save all forms to localStorage before a form submits
    function saveBeforeSubmit(idx) {
        saveForms();
        // Update _form_index to the logical position (not the counter ID)
        const cards = [...document.querySelectorAll('.card')];
        const card = document.querySelector(`.card[data-form-index="${idx}"]`);
        const position = cards.indexOf(card);
        card.querySelector('input[name="_form_index"]').value = position;
    }

    // --- LocalStorage ---
    function saveForms() {
        const cards = document.querySelectorAll('.card');
        const data = [];
        cards.forEach(card => data.push(getFormData(card)));
        localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
    }

    function restoreForms() {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (!raw) { addForm(); return; }
        try {
            const list = JSON.parse(raw);
            if (!Array.isArray(list) || list.length === 0) { addForm(); return; }
            list.forEach(d => addForm(d));
        } catch (e) { addForm(); }
    }

    // JSON syntax highlighting
    function syntaxHighlight(json) {
        if (typeof json !== 'string') json = JSON.stringify(json, null, 2);
        json = json.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        return json.replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*")(\s*:)?|\b(true|false)\b|\bnull\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?/g,
            function (match, str, _, colon, bool) {
                let cls = 'json-number';
                if (str) {
                    cls = colon ? 'json-key' : 'json-string';
                } else if (bool) {
                    cls = 'json-boolean';
                } else if (/null/.test(match)) {
                    cls = 'json-null';
                }
                return '<span class="' + cls + '">' + match + '</span>';
            });
    }

    // Fullscreen state
    let fsData = { rawContent: '', isJson: false, jsonBody: null, statusCode: null, error: null, hasHtml: false };

    function openFullscreen() {
        const overlay = document.getElementById('fullscreenOverlay');
        const badge = document.getElementById('fsStatusBadge');
        const pre = document.getElementById('fsPre');
        const iframe = document.getElementById('fsIframe');
        const toggle = document.getElementById('fsToggle');

        if (fsData.error) {
            badge.className = 'status-badge status-err';
            badge.textContent = 'ERROR';
            pre.textContent = fsData.error;
            pre.style.display = 'block';
            iframe.style.display = 'none';
            toggle.style.display = 'none';
        } else {
            badge.className = 'status-badge ' + statusClass(fsData.statusCode);
            badge.textContent = fsData.statusCode;

            if (fsData.isJson) {
                pre.innerHTML = syntaxHighlight(JSON.stringify(fsData.jsonBody, null, 2));
            } else {
                pre.textContent = fsData.rawContent;
            }

            if (fsData.hasHtml) {
                toggle.style.display = 'flex';
                if (!fsData.isJson) {
                    pre.style.display = 'none';
                    iframe.style.display = 'block';
                    toggle.querySelectorAll('button').forEach(b => b.classList.remove('active'));
                    toggle.querySelector('[data-fs-view="html"]').classList.add('active');
                } else {
                    pre.style.display = 'block';
                    iframe.style.display = 'none';
                    toggle.querySelectorAll('button').forEach(b => b.classList.remove('active'));
                    toggle.querySelector('[data-fs-view="formatted"]').classList.add('active');
                }
                const blob = new Blob([fsData.rawContent], { type: 'text/html' });
                iframe.src = URL.createObjectURL(blob);

                toggle.querySelectorAll('button').forEach(btn => {
                    btn.onclick = () => {
                        toggle.querySelectorAll('button').forEach(b => b.classList.remove('active'));
                        btn.classList.add('active');
                        if (btn.dataset.fsView === 'html') {
                            pre.style.display = 'none';
                            iframe.style.display = 'block';
                        } else {
                            pre.style.display = 'block';
                            iframe.style.display = 'none';
                        }
                    };
                });
            } else {
                toggle.style.display = 'none';
                pre.style.display = 'block';
                iframe.style.display = 'none';
            }
        }

        overlay.classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeFullscreen() {
        document.getElementById('fullscreenOverlay').classList.remove('open');
        document.body.style.overflow = '';
    }

    // Close on Escape key
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeFullscreen(); });

    // Show server response on the correct card
    function showServerResponse() {
        if (serverResponse.index === null) return;
        const cards = document.querySelectorAll('.card');
        const card = cards[serverResponse.index];
        if (!card) return;

        const respSection = card.querySelector('[data-response]');
        const badge = card.querySelector('[data-status-badge]');
        const body = card.querySelector('[data-response-body]');
        const iframe = card.querySelector('[data-response-iframe]');
        const toggle = card.querySelector('[data-response-toggle]');
        const btnFs = card.querySelector('[data-btn-fullscreen]');

        respSection.classList.add('visible');
        card.classList.add('highlight');

        const isJson = serverResponse.body !== null;
        const rawContent = serverResponse.rawContent || '';
        const looksLikeHtml = rawContent.trim().startsWith('<') || rawContent.includes('<!DOCTYPE') || rawContent.includes('<html');

        // Store for fullscreen
        fsData = {
            rawContent: rawContent,
            isJson: isJson,
            jsonBody: serverResponse.body,
            statusCode: serverResponse.status,
            error: serverResponse.error,
            hasHtml: looksLikeHtml && !!rawContent,
        };

        if (serverResponse.error) {
            badge.className = 'status-badge status-err';
            badge.textContent = 'ERROR';
            body.textContent = serverResponse.error;
        } else {
            badge.className = 'status-badge ' + statusClass(serverResponse.status);
            badge.textContent = serverResponse.status;

            if (isJson) {
                body.innerHTML = syntaxHighlight(JSON.stringify(serverResponse.body, null, 2));
            } else {
                body.textContent = rawContent;
            }

            // Always load raw content into iframe for HTML preview
            if (rawContent && looksLikeHtml) {
                const blob = new Blob([rawContent], { type: 'text/html' });
                iframe.src = URL.createObjectURL(blob);
                iframe.onload = () => {
                    try {
                        const h = iframe.contentDocument.documentElement.scrollHeight;
                        iframe.style.minHeight = Math.min(Math.max(h + 20, 200), 600) + 'px';
                    } catch(e) {}
                };
                toggle.style.display = 'flex';

                if (!isJson) {
                    body.style.display = 'none';
                    iframe.style.display = 'block';
                    toggle.querySelectorAll('button').forEach(b => b.classList.remove('active'));
                    toggle.querySelector('[data-view="html"]').classList.add('active');
                }

                toggle.querySelectorAll('button').forEach(btn => {
                    btn.onclick = () => {
                        toggle.querySelectorAll('button').forEach(b => b.classList.remove('active'));
                        btn.classList.add('active');
                        if (btn.dataset.view === 'html') {
                            body.style.display = 'none';
                            iframe.style.display = 'block';
                        } else {
                            body.style.display = 'block';
                            iframe.style.display = 'none';
                        }
                    };
                });
            }
        }

        // Wire up fullscreen button
        btnFs.onclick = () => openFullscreen();

        // Scroll to the response
        card.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    // Auto-save on any input change (debounced)
    let saveTimer;
    document.addEventListener('input', () => { clearTimeout(saveTimer); saveTimer = setTimeout(saveForms, 300); });
    document.addEventListener('change', () => { clearTimeout(saveTimer); saveTimer = setTimeout(saveForms, 300); });

    // Init
    restoreForms();
    showServerResponse();
</script>
</body>
</html>