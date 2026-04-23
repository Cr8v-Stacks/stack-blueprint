/**
 * Stack Blueprint — Admin JS v1.2.0
 * Author: Cr8v Stacks | cr8vstacks.com
 *
 * Fixes in this version:
 * - Downloads use fetch → Blob → anchor click (no auth header issues)
 * - Save to Elementor auto-downloads companion CSS simultaneously
 * - CSS prefix detection uses frequency analysis across ALL class occurrences
 * - Result panel stores full JSON/CSS content for immediate blob download
 * - Engine tab wiring complete
 */
/* global SB */

(function () {
	'use strict';

	// ── State ─────────────────────────────────────────────────
	const S = {
		page:     '',
		convId:   null,
		convData: null,   // full conversion record stored after convert
		converting: false,
		htmlFile: null,
		cssFile:  null,
		jsFile:   null,
		strategy: 'v2',
		engine:   'ai',
		extractedTokens: { colors: {}, fonts: [] },
	};

	// ── Boot ──────────────────────────────────────────────────
	document.addEventListener('DOMContentLoaded', () => {
		createToastContainer();
		detectPage();

		if (S.page === 'converter') initConverter();
		if (S.page === 'history')   initHistory();
		if (S.page === 'tokens')    initTokensPage();
		if (S.page === 'settings')  initSettings();
	});

	function detectPage() {
		const map = {
			'sb-converter-page': 'converter',
			'sb-history-page':   'history',
			'sb-tokens-page':    'tokens',
			'sb-settings-page':  'settings',
		};
		for (const [id, name] of Object.entries(map)) {
			if (document.getElementById(id)) { S.page = name; break; }
		}
	}

	// ── Converter Page ────────────────────────────────────────
	function initConverter() {
		initEngineTabs();
		initStrategyCards();
		initDropzone();
		initMiniUploads();

		qs('#sb-form')?.addEventListener('submit', async e => {
			e.preventDefault();
			if (S.converting) return;
			if (!S.htmlFile) { toast('Upload an HTML file first.', 'err'); return; }
			await runConversion();
		});

		qs('#sb-save-tmpl')?.addEventListener('click', saveToElementorAndDownloadCSS);
		qs('#sb-dl-json')?.addEventListener('click', e => { e.preventDefault(); blobDownload('json'); });
		qs('#sb-dl-css')?.addEventListener('click',  e => { e.preventDefault(); blobDownload('css'); });
		qs('#sb-push-tokens')?.addEventListener('click', pushTokensFromWidget);
	}

	// ── Engine Tabs ───────────────────────────────────────────
	function initEngineTabs() {
		qsa('.sb-engine-tab').forEach(tab => {
			tab.addEventListener('click', () => {
				qsa('.sb-engine-tab').forEach(t => t.classList.remove('is-active'));
				tab.classList.add('is-active');
				S.engine = tab.dataset.engine;

				const engineInput = qs('#sb-engine');
				if (engineInput) engineInput.value = S.engine;

				qs('#sb-info-ai')?.style.setProperty('display', S.engine === 'ai' ? '' : 'none');
				qs('#sb-info-native')?.style.setProperty('display', S.engine === 'native' ? '' : 'none');

				// Both engines support V1 and V2. Do NOT dim strategy cards.
				// V1 native = aggressive HTML widget preservation.
				// V2 native = maximum native widget conversion.
				qs('#sb-strategy-area')?.classList.remove('is-dimmed');
			});
		});
	}

	// ── Strategy Cards ────────────────────────────────────────
	function initStrategyCards() {
		qsa('.sb-strategy-card').forEach(card => {
			card.addEventListener('click', () => {
				qsa('.sb-strategy-card').forEach(c => c.classList.remove('is-selected'));
				card.classList.add('is-selected');
				S.strategy = card.dataset.strategy;
				const radio = card.querySelector('input[type="radio"]');
				if (radio) radio.checked = true;
			});
		});
		const checked = qs('input[name="strategy"]:checked');
		if (checked) {
			S.strategy = checked.value;
			checked.closest('.sb-strategy-card')?.classList.add('is-selected');
		}
	}

	// ── Dropzone ──────────────────────────────────────────────
	function initDropzone() {
		const zone  = qs('#sb-dropzone');
		const input = qs('#sb-html-file');
		if (!zone || !input) return;

		zone.addEventListener('dragover',  e => { e.preventDefault(); zone.classList.add('drag'); });
		zone.addEventListener('dragleave', ()  => zone.classList.remove('drag'));
		zone.addEventListener('drop', e => {
			e.preventDefault();
			zone.classList.remove('drag');
			const file = e.dataTransfer?.files?.[0];
			if (file) handleHtmlFile(file);
		});
		input.addEventListener('change', () => { if (input.files[0]) handleHtmlFile(input.files[0]); });
	}

	function handleHtmlFile(file) {
		if (!file.name.match(/\.(html?|htm)$/i)) { toast('Please upload an HTML or HTM file.', 'err'); return; }
		S.htmlFile = file;

		const zone = qs('#sb-dropzone');
		zone?.classList.add('has-file');
		const nameEl = qs('#sb-file-name');
		if (nameEl) nameEl.textContent = `${file.name} (${fmtBytes(file.size)})`;

		// Read file to auto-detect prefix and extract tokens.
		readText(file).then(html => {
			const prefix = detectPrefix(html);
			const input  = qs('#sb-prefix');
			const badge  = qs('#sb-prefix-badge');

			if (prefix && input && !input.value) {
				input.value = prefix;
				input.classList.add('auto');
				badge?.classList.add('show');
			}

			const tokens = extractTokens(html);
			S.extractedTokens = tokens;
			renderTokenWidget(tokens);
		}).catch(() => {});
	}

	/**
	 * Detect CSS prefix from HTML source.
	 *
	 * Strategy: scan every class attribute and every CSS class selector in the file.
	 * Count the leading segment (before the first `-`) for all class names.
	 * The prefix with the highest occurrence count (minimum 8 uses) wins.
	 *
	 * This correctly handles classes like `nx-hero`, `nx-btn-primary`, `nexus-hero`
	 * and will ignore generic classes like `container`, `wrapper`, `active`.
	 */
	function detectPrefix(html) {
		const counts = {};

		// From class="" attributes — capture every value.
		const attrRe = /class\s*=\s*["']([^"']+)["']/gi;
		let m;
		while ((m = attrRe.exec(html)) !== null) {
			m[1].split(/\s+/).forEach(cls => tally(cls, counts));
		}

		// From CSS class selectors — .prefix-something {
		const cssRe = /\.([\w-]+)\s*[{,:\[>~+\s]/g;
		while ((m = cssRe.exec(html)) !== null) {
			tally(m[1], counts);
		}

		function tally(cls, counts) {
			// Extract the first segment (everything before the first hyphen).
			const seg = cls.match(/^([a-z][a-z0-9]{1,6})-/i);
			if (!seg) return;
			const p = seg[1].toLowerCase();
			// Ignore overly generic prefixes.
			if (['col', 'row', 'btn', 'is', 'has', 'no', 'card', 'hero', 'grid', 'main', 'page', 'site', 'app', 'cad', 'nl', 'wp', 'el'].includes(p)) return;
			counts[p] = (counts[p] || 0) + 1;
		}

		// Sort by count, require at least 8 occurrences.
		const sorted = Object.entries(counts)
			.filter(([, v]) => v >= 8)
			.sort((a, b) => b[1] - a[1]);

		return isSafePrefix(sorted[0]?.[0] || '') ? sorted[0][0] : '';
	}

	function isSafePrefix(prefix) {
		return /^[a-z][a-z0-9-]{1,5}$/i.test(prefix || '') &&
			!['card', 'hero', 'grid', 'main', 'page', 'site', 'app', 'cad', 'nl', 'wp', 'el', 'div', 'row', 'col', 'btn', 'my', 'new', 'old', 'test'].includes((prefix || '').toLowerCase());
	}

	// ── Mini File Uploads ─────────────────────────────────────
	function initMiniUploads() {
		qs('#sb-css-file')?.addEventListener('change', function () {
			S.cssFile = this.files[0] || null;
			qs('#sb-css-up')?.classList.toggle('has-file', !!S.cssFile);
			const nm = qs('#sb-css-name');
			if (nm) nm.textContent = S.cssFile ? S.cssFile.name : 'No file';
		});
		qs('#sb-js-file')?.addEventListener('change', function () {
			S.jsFile = this.files[0] || null;
			qs('#sb-js-up')?.classList.toggle('has-file', !!S.jsFile);
			const nm = qs('#sb-js-name');
			if (nm) nm.textContent = S.jsFile ? S.jsFile.name : 'No file';
		});
	}

	// ── Conversion ────────────────────────────────────────────
	async function runConversion() {
		S.converting = true;
		const btn = qs('#sb-convert-btn');
		btnLoad(btn, true);
		showProgress();
		hideResult();

		const fd = new FormData();
		fd.append('html_file', S.htmlFile);
		if (S.cssFile) fd.append('css_file', S.cssFile);
		if (S.jsFile)  fd.append('js_file',  S.jsFile);

		const name   = qs('#sb-project-name')?.value?.trim() || 'my-project';
		const prefix = qs('#sb-prefix')?.value?.trim() || '';

		fd.append('project_name', name);
		fd.append('prefix', prefix);
		fd.append('strategy', S.strategy);
		fd.append('converter', S.engine);

		// Generate a unique transaction ID for progress polling
		const txId = Date.now().toString(36) + Math.random().toString(36).slice(2);
		fd.append('tx_id', txId);

		// ── 9-Pass pipeline real-time polling ────────────────────────
		const PASS_LABELS = [
			'Pass 1 — Document Intelligence',
			'Pass 2 — Layout Analysis',
			'Pass 3 — Content Classification',
			'Pass 4 — Style Resolution',
			'Pass 5 — Class & ID Generation',
			'Pass 6 — Global Setup Synthesis',
			'Pass 7 — JSON Assembly',
			'Pass 8 — Companion CSS',
			'Pass 9 — Validation & Repair',
		];
		const PASS_BARS  = [8, 18, 30, 42, 52, 62, 75, 88, 95];

		function setPassLabel(idx) {
			const lbl = qs('.sb-progress__lbl');
			if (lbl) lbl.textContent = PASS_LABELS[idx] ?? 'Processing';
		}

		let currentPass = 0;
		setStep(0); setBar(0); setPassLabel(0);

		// Poll server every 250ms for real-time progress.
		const pollInterval = setInterval(async () => {
			try {
				const response = await fetch(`${stackBlueprint.restUrl}/convert-progress?tx_id=${txId}`);
				if (response.ok) {
					const data = await response.json();
					const passNum = parseInt(data.pass, 10);
					if (passNum > 0 && passNum <= 9) {
						let idx = passNum - 1;
						// Only move forward, prevent jumping backward
						if (idx > currentPass) {
							currentPass = idx;
							setStep(idx);
							setBar(PASS_BARS[idx]);
							setPassLabel(idx);
						}
					}
				}
			} catch(e) { /* ignore network errors during poll */ }
		}, 300);

		try {
			// ── Fire the conversion request ──────────────────────
			const res = await api('/convert', { method: 'POST', body: fd }, false);
			clearInterval(pollInterval);

			// If it finished too quickly (Native engine), animate through missed initial passes
			if (currentPass < 5) {
				for (let p = currentPass; p <= 5; p++) {
					setStep(p);
					setBar(PASS_BARS[p]);
					setPassLabel(p);
					await sleep(120);
				}
			}

			// Flash through passes 7, 8, 9 quickly to show server completed them.
			for (let p = 6; p <= 8; p++) {
				setStep(p);
				setBar(PASS_BARS[p]);
				setPassLabel(p);
				await sleep(280);
			}

			S.convId = res.conversion_id;

			// Mark all done, fetch full record.
			setBar(98);
			const record = await api(`/convert/${S.convId}`);
			S.convData = record;
			setBar(100);

			// Mark every step as done.
			qsa('.sb-progress__step').forEach(s => {
				s.classList.remove('active');
				s.classList.add('done');
			});
			const lbl = qs('.sb-progress__lbl');
			if (lbl) lbl.textContent = 'Complete';

			await sleep(350);

			hideProgress();
			showResult(res, record);
			toast('Conversion complete!', 'ok');

		} catch (e) {
			if (typeof stepInterval !== 'undefined') clearInterval(stepInterval);
			hideProgress();
			toast(e.message || 'Conversion failed. Check the HTML prototype and try again.', 'err');
		}

		S.converting = false;
		btnLoad(btn, false);
	}

	function showProgress() { qs('#sb-progress')?.classList.add('show'); }
	function hideProgress() { qs('#sb-progress')?.classList.remove('show'); }
	function setBar(pct)    { const f = qs('#sb-prog-fill'); if (f) f.style.width = pct + '%'; }

	function setStep(idx) {
		qsa('.sb-progress__step').forEach((s, i) => {
			s.classList.toggle('active', i === idx);
			s.classList.toggle('done',   i < idx);
		});
	}

	function showResult(res, record) {
		const panel = qs('#sb-result');
		if (!panel) return;
		panel.classList.add('show');

		// Engine badge.
		const badge = qs('#sb-result-engine-badge');
		if (badge) {
			badge.textContent = S.engine === 'native' ? 'Native Engine' : 'AI Engine';
			badge.style.color = S.engine === 'native' ? 'var(--sb-accent-2)' : 'var(--sb-accent)';
		}

		// Warnings.
		const wc = qs('#sb-warnings');
		if (wc) {
			wc.innerHTML = '';
			(res.warnings || record?.warnings || []).forEach(w => {
				const d = document.createElement('div');
				d.className = 'sb-warning';
				d.innerHTML = `<svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M6 1l5 10H1L6 1z"/><line x1="6" y1="5" x2="6" y2="7.5"/><circle cx="6" cy="9.5" r="0.5" fill="currentColor"/></svg><span>${esc(w)}</span>`;
				wc.appendChild(d);
			});
		}

		// Class map.
		const tbody = qs('#sb-cmap-body');
		if (tbody) {
			tbody.innerHTML = '';
			(res.class_map || record?.class_map || []).forEach(e => {
				const tr = document.createElement('tr');
				tr.innerHTML = `<td class="cls">.${esc(e.class)}</td><td>${esc(e.element)}</td><td>${esc(e.location)}</td>`;
				tbody.appendChild(tr);
			});
		}
	}

	function hideResult() { qs('#sb-result')?.classList.remove('show'); }

	// ── Blob Downloads ────────────────────────────────────────
	/**
	 * Download JSON or CSS using a Blob — no auth headers needed,
	 * no filename mangling by the browser.
	 */
	async function blobDownload(type) {
		if (!S.convData) { toast('No conversion data available.', 'err'); return; }

		const content  = type === 'json' ? S.convData.json_output : S.convData.css_output;
		const mimeType = type === 'json' ? 'application/json'     : 'text/css';
		const name     = (qs('#sb-project-name')?.value?.trim() || 'template').replace(/\s+/g, '-');
		const filename  = `${name}-elementor.${type}`;

		if (!content) { toast(`No ${type.toUpperCase()} output available.`, 'err'); return; }

		triggerBlobDownload(content, mimeType, filename);
		toast(`Downloading ${filename}`, 'inf');
	}

	function triggerBlobDownload(content, mimeType, filename) {
		const blob = new Blob([content], { type: mimeType });
		const url  = URL.createObjectURL(blob);
		const a    = document.createElement('a');
		a.href     = url;
		a.download = filename;
		document.body.appendChild(a);
		a.click();
		document.body.removeChild(a);
		setTimeout(() => URL.revokeObjectURL(url), 5000);
	}

	// ── Save to Elementor + Auto-download CSS ─────────────────
	/**
	 * Saves the JSON template to the Elementor library,
	 * then automatically triggers the companion CSS download.
	 * This solves the UX gap: Elementor can't store CSS with a template,
	 * so we always bundle the download alongside the save action.
	 */
	async function saveToElementorAndDownloadCSS() {
		if (!S.convId) { toast('No conversion to save.', 'err'); return; }

		const btn = qs('#sb-save-tmpl');
		btnLoad(btn, true);

		try {
			await api('/save-template', {
				method: 'POST',
				body: JSON.stringify({ conversion_id: S.convId }),
			});

			toast('Template saved to Elementor Library!', 'ok');

			// Auto-download the companion CSS immediately.
			await sleep(300);
			await blobDownload('css');
			toast('Companion CSS downloaded — paste it into Elementor Site Settings → Custom CSS.', 'inf');

		} catch (e) {
			toast(e.message || 'Could not save template.', 'err');
		}

		btnLoad(btn, false);
	}

	// ── Token Widget (Converter sidebar) ──────────────────────
	function renderTokenWidget(tokens) {
		const widget = qs('#sb-token-widget');
		if (!widget) return;
		widget.style.display = 'block';

		const sc = qs('#sb-tok-swatches');
		if (sc) {
			sc.innerHTML = '';
			const entries = Object.entries(tokens.colors);
			if (!entries.length) {
				sc.innerHTML = '<p style="font-size:11px;color:var(--sb-text-3)">No colours detected in CSS variables or direct style declarations.</p>';
			} else {
				entries.slice(0, 20).forEach(([name, val]) => {
					const d = document.createElement('div');
					d.className = 'sb-swatch';
					d.style.background = val;
					d.innerHTML = `<span class="sb-swatch__tip">${esc(name)}<br>${esc(val)}</span>`;
					sc.appendChild(d);
				});
			}
		}

		const fc = qs('#sb-tok-fonts');
		if (fc) {
			fc.innerHTML = '';
			if (!tokens.fonts.length) {
				fc.innerHTML = '<p style="font-size:11px;color:var(--sb-text-3)">No Google Fonts detected.</p>';
			} else {
				tokens.fonts.forEach(font => {
					const d = document.createElement('div');
					d.className = 'sb-token-font';
					d.innerHTML = `<span class="sb-token-font__pre" style="font-family:'${esc(font)}'">Aa</span><div><p class="sb-token-font__name">${esc(font)}</p></div>`;
					fc.appendChild(d);
				});
			}
		}
	}

	async function pushTokensFromWidget() {
		await doPushTokens(S.extractedTokens, qs('#sb-push-tokens'), qs('#sb-push-msg'));
	}

	// ── History Page ──────────────────────────────────────────
	async function initHistory() {
		const tbody = qs('#sb-history-tbody');
		if (!tbody) return;
		try {
			const rows = await api('/history');
			tbody.innerHTML = '';

			if (!rows.length) {
				tbody.innerHTML = `<tr><td colspan="6"><div class="sb-empty">
					<p class="sb-empty__title">No conversions yet</p>
					<p class="sb-empty__desc">Your history appears here after your first conversion.</p>
				</div></td></tr>`;
				return;
			}

			rows.forEach(row => {
				const tr = document.createElement('tr');
				const nonce = SB.nonce;

				const actions = row.status === 'complete'
					? `<div style="display:flex;gap:6px">
						<button class="sb-btn sb-btn--ghost sb-btn--sm" onclick="SBHistoryDL(${row.id},'json')">JSON</button>
						<button class="sb-btn sb-btn--ghost sb-btn--sm" onclick="SBHistoryDL(${row.id},'css')">CSS</button>
					   </div>`
					: `<span style="font-size:11px;color:var(--sb-text-3)">${esc(row.error_message || '—')}</span>`;

				tr.innerHTML = `
					<td><strong style="color:var(--sb-text)">${esc(row.project_name)}</strong></td>
					<td><span class="sb-badge sb-badge--${row.strategy}">${esc(row.strategy.toUpperCase())}</span></td>
					<td><span class="sb-badge sb-badge--${row.status}">${esc(row.status)}</span></td>
					<td style="font-family:var(--sb-font-mono);font-size:11px;color:var(--sb-text-3)">${esc(row.prefix || '—')}</td>
					<td style="font-size:11px;color:var(--sb-text-3)">${fmtDate(row.created_at)}</td>
					<td>${actions}</td>`;
				tbody.appendChild(tr);
			});
		} catch (e) {
			toast(e.message, 'err');
		}
	}

	// Exposed globally for inline history download buttons.
	window.SBHistoryDL = async function(id, type) {
		try {
			const record = await apiFetch(`${SB.apiBase}/convert/${id}`, {
				headers: { 'X-WP-Nonce': SB.nonce },
			});
			const content  = type === 'json' ? record.json_output : record.css_output;
			const mimeType = type === 'json' ? 'application/json' : 'text/css';
			const filename  = `conversion-${id}-elementor.${type}`;
			if (!content) { toast('No output available.', 'err'); return; }
			triggerBlobDownload(content, mimeType, filename);
		} catch (e) {
			toast(e.message, 'err');
		}
	};

	async function apiFetch(url, options = {}) {
		const res = await fetch(url, { credentials: 'same-origin', ...options });
		if (!res.ok) {
			const err = await readApiError(res);
			throw new Error(err.message || 'Request failed');
		}
		return res.json();
	}

	// ── Tokens Full Page ──────────────────────────────────────
	function initTokensPage() {
		qs('#sb-extract-btn')?.addEventListener('click', () => {
			const html = qs('#sb-tok-html')?.value?.trim();
			if (!html) { toast('Paste your HTML prototype first.', 'err'); return; }
			const tokens = extractTokens(html);
			S.extractedTokens = tokens;
			renderFullTokens(tokens);
		});

		qs('#sb-push-globals')?.addEventListener('click', () => {
			doPushTokens(S.extractedTokens, qs('#sb-push-globals'), qs('#sb-push-result'));
		});
	}

	function renderFullTokens(tokens) {
		const colorPanel = qs('#sb-colors-panel');
		const colorGrid  = qs('#sb-color-grid');
		const colorCount = qs('#sb-color-count');
		const colorMap   = qs('#sb-color-map-rows');
		const fontPanel  = qs('#sb-fonts-panel');
		const fontList   = qs('#sb-font-list');
		const fontCount  = qs('#sb-font-count');
		const fontMap    = qs('#sb-font-map-rows');
		const pushPanel  = qs('#sb-push-panel');

		const entries = Object.entries(tokens.colors);

		if (colorPanel) colorPanel.style.display = entries.length ? 'block' : 'none';
		if (colorCount) colorCount.textContent = `${entries.length} found`;

		if (colorGrid) {
			colorGrid.innerHTML = '';
			entries.forEach(([name, val]) => {
				const chip = document.createElement('div');
				chip.className = 'sb-token-chip';
				chip.innerHTML = `<div class="sb-token-chip__sw" style="background:${esc(val)}"></div><p class="sb-token-chip__nm">${esc(name)}</p><p class="sb-token-chip__val">${esc(val)}</p>`;
				colorGrid.appendChild(chip);
			});
		}

		if (colorMap) {
			colorMap.innerHTML = '';
			entries.forEach(([name, val]) => {
				const slug      = name.replace(/^--/, '').replace(/-/g, ' ');
				const suggested = suggestTokenName(slug);
				const row = document.createElement('div');
				row.style.cssText = 'display:flex;align-items:center;gap:10px;margin-bottom:8px';
				row.innerHTML = `
					<div style="width:20px;height:20px;border-radius:3px;background:${esc(val)};border:1px solid rgba(255,255,255,.08);flex-shrink:0"></div>
					<span style="font-family:var(--sb-font-mono);font-size:10px;color:var(--sb-accent);min-width:140px">${esc(name)}</span>
					<input type="text" class="sb-input sb-color-token-name" data-var="${esc(name)}" data-val="${esc(val)}" value="${esc(suggested)}" placeholder="Token name in Elementor" style="flex:1">`;
				colorMap.appendChild(row);
			});
		}

		if (fontPanel) fontPanel.style.display = tokens.fonts.length ? 'block' : 'none';
		if (fontCount) fontCount.textContent = `${tokens.fonts.length} found`;

		if (fontList) {
			fontList.innerHTML = '';
			tokens.fonts.forEach(font => {
				const d = document.createElement('div');
				d.className = 'sb-token-font';
				d.style.marginBottom = '6px';
				d.innerHTML = `<span class="sb-token-font__pre" style="font-family:'${esc(font)}'">Aa</span><div><p class="sb-token-font__name">${esc(font)}</p><p class="sb-token-font__type">${esc(detectFontRole(font))}</p></div>`;
				fontList.appendChild(d);
			});
		}

		if (fontMap) {
			fontMap.innerHTML = '';
			tokens.fonts.forEach(font => {
				const row = document.createElement('div');
				row.style.cssText = 'display:flex;align-items:center;gap:10px;margin-bottom:8px';
				row.innerHTML = `
					<span style="font-size:18px;font-weight:700;font-family:'${esc(font)}';color:var(--sb-accent);width:32px;text-align:center">Aa</span>
					<span style="font-size:12px;color:var(--sb-text);min-width:140px">${esc(font)}</span>
					<input type="text" class="sb-input sb-font-token-name" data-font="${esc(font)}" value="${esc(detectFontRole(font))}" placeholder="e.g. Font Display" style="flex:1">`;
				fontMap.appendChild(row);
			});
		}

		if (pushPanel && (entries.length || tokens.fonts.length)) {
			pushPanel.style.display = 'block';
		}
	}

	async function doPushTokens(tokens, btn, msgEl) {
		if (!tokens || (!Object.keys(tokens.colors).length && !tokens.fonts.length)) {
			toast('No tokens to push. Extract tokens first.', 'err');
			return;
		}

		const colorInputs = qsa('.sb-color-token-name');
		const fontInputs  = qsa('.sb-font-token-name');

		const colorMappings = {};
		colorInputs.forEach(inp => {
			if (inp.value.trim()) colorMappings[inp.dataset.var] = { name: inp.value.trim(), value: inp.dataset.val };
		});

		const fontMappings = {};
		fontInputs.forEach(inp => {
			if (inp.value.trim()) fontMappings[inp.dataset.font] = inp.value.trim();
		});

		// Auto-generate names if no input fields present (widget mode).
		if (!colorInputs.length) {
			Object.entries(tokens.colors).forEach(([k, v]) => {
				colorMappings[k] = { name: suggestTokenName(k.replace(/^--/,'').replace(/-/g,' ')), value: v };
			});
		}
		if (!fontInputs.length) {
			tokens.fonts.forEach(f => { fontMappings[f] = detectFontRole(f); });
		}

		btnLoad(btn, true);
		if (msgEl) { msgEl.textContent = 'Pushing to Elementor…'; msgEl.className = 'sb-push-msg'; }

		try {
			const r = await api('/push-globals', {
				method: 'POST',
				body: JSON.stringify({ colors: colorMappings, fonts: fontMappings }),
			});
			const msg = `Pushed ${r.pushed_colors || 0} colours and ${r.pushed_fonts || 0} fonts to Elementor globals.`;
			if (msgEl) { msgEl.textContent = msg; msgEl.className = 'sb-push-msg ok'; }
			toast(msg, 'ok');
		} catch (e) {
			if (msgEl) { msgEl.textContent = e.message; msgEl.className = 'sb-push-msg err'; }
			toast(e.message, 'err');
		}

		btnLoad(btn, false);
	}

	// ── Settings Page ─────────────────────────────────────────
	async function initSettings() {
		await loadSettings();

		qsa('.sb-mode-tab').forEach(tab => {
			tab.addEventListener('click', () => {
				qsa('.sb-mode-tab').forEach(t => t.classList.remove('is-active'));
				qsa('.sb-mode-panel').forEach(p => p.classList.remove('is-active'));
				tab.classList.add('is-active');
				qs(`#sb-mode-${tab.dataset.mode}`)?.classList.add('is-active');
				const hid = qs('#sb-api-mode-val');
				if (hid) hid.value = tab.dataset.mode;
			});
		});

		qs('#sb-save-settings')?.addEventListener('click', async () => {
			const btn = qs('#sb-save-settings');
			btnLoad(btn, true);
			try { await saveSettings(); toast('Settings saved.', 'ok'); }
			catch (e) { toast(e.message, 'err'); }
			btnLoad(btn, false);
		});

		qs('#sb-test-key')?.addEventListener('click', async () => {
			const btn = qs('#sb-test-key');
			btnLoad(btn, true);
			try {
				await api('/test-api', { method: 'POST', body: JSON.stringify({}) });
				toast('API key valid ✓', 'ok');
				setApiStatus(true);
			} catch (e) {
				toast('API key invalid: ' + e.message, 'err');
				setApiStatus(false);
			}
			btnLoad(btn, false);
		});

		qs('.sb-key-toggle')?.addEventListener('click', () => {
			const inp = qs('#sb-api-key');
			if (inp) inp.type = inp.type === 'password' ? 'text' : 'password';
		});
	}

	async function loadSettings() {
		try {
			const d = await api('/settings');
			const set = (id, val) => { const el = qs(`#${id}`); if (el && val !== undefined) el.value = val; };
			set('sb-api-model',       d.api_model       || '');
			set('sb-default-strategy', d.default_strategy || 'v2');
			set('sb-max-size',         d.max_file_size    || 5);
			if (d.api_key_set) { set('sb-api-key', d.api_key || ''); setApiStatus(true); }

			// Set mode tabs.
			if (d.api_mode) {
				qsa('.sb-mode-tab').forEach(t => t.classList.toggle('is-active', t.dataset.mode === d.api_mode));
				qsa('.sb-mode-panel').forEach(p => p.classList.toggle('is-active', p.id === `sb-mode-${d.api_mode}`));
				const hid = qs('#sb-api-mode-val');
				if (hid) hid.value = d.api_mode;
			}
		} catch (e) { /* silent on settings load */ }
	}

	async function saveSettings() {
		const val  = id => qs(`#${id}`)?.value;
		const key  = val('sb-api-key');
		const mode = qs('#sb-api-mode-val')?.value || 'own';
		await api('/settings', {
			method: 'POST',
			body: JSON.stringify({
				api_key:          key?.includes('•') ? undefined : key,
				api_model:        val('sb-api-model'),
				api_mode:         mode,
				default_strategy: val('sb-default-strategy'),
				max_file_size:    parseInt(val('sb-max-size') || '5', 10),
			}),
		});
	}

	function setApiStatus(ok) {
		const dot = qs('#sb-api-dot');
		const txt = qs('#sb-api-status-txt');
		if (dot) dot.className = 'sb-conn-dot' + (ok ? ' live' : '');
		if (txt) txt.textContent = ok ? 'Connected' : 'Not connected';
	}

	// ── Token Extraction ──────────────────────────────────────
	function extractTokens(html) {
		const colors = {};
		const fonts  = [];
		const discovered = new Set();

		const re = /--([\w-]+)\s*:\s*([^;}{]+)/g;
		let m;
		while ((m = re.exec(html)) !== null) {
			const name = '--' + m[1].trim();
			const val  = m[2].trim();
			if (/^(#[0-9a-f]{3,8}|rgba?\(|hsla?\(|transparent)/i.test(val)) {
				colors[name] = val;
				discovered.add(normColor(val));
			}
		}

		// Fallback: collect direct color declarations when CSS vars are absent.
		// This keeps token extraction useful for raw CSS that does not define --vars.
		const colorDeclRe = /(?:^|[;{\s])(color|background(?:-color)?|border(?:-color)?|outline-color|fill|stroke)\s*:\s*([^;}{]+)/gi;
		let colorIdx = 1;
		while ((m = colorDeclRe.exec(html)) !== null) {
			const raw = String(m[2] || '').trim();
			const first = raw.split(/\s+/)[0];
			if (!isColorValue(first)) continue;
			const normalized = normColor(first);
			if (discovered.has(normalized)) continue;
			discovered.add(normalized);
			colors[`--detected-color-${colorIdx}`] = first;
			colorIdx++;
			if (colorIdx > 30) break;
		}

		const fr = /family=([^&"')\s]+)/g;
		while ((m = fr.exec(html)) !== null) {
			const fam = decodeURIComponent(m[1].split(':')[0]).replace(/\+/g, ' ').replace(/['"]/g, '');
			if (fam && !fonts.includes(fam)) fonts.push(fam);
		}

		return { colors, fonts };
	}

	function isColorValue(value) {
		return /^(#[0-9a-f]{3,8}|rgba?\(|hsla?\(|hwb\(|lab\(|lch\(|oklab\(|oklch\(|transparent|currentColor|[a-z]{3,})/i.test(String(value || '').trim());
	}

	function normColor(value) {
		return String(value || '').trim().toLowerCase().replace(/\s+/g, '');
	}

	function suggestTokenName(slug) {
		const map = [
			['bg','Brand Background'],['background','Brand Background'],
			['accent','Brand Primary'],['primary','Brand Primary'],['acid','Brand Primary'],
			['text','Brand Text'],['body','Brand Text'],['foreground','Brand Text'],
			['surface','Brand Surface'],['card','Brand Surface'],['void','Brand Surface'],
			['border','Brand Border'],['stroke','Brand Border'],
			['secondary','Brand Secondary'],['ink','Brand Text Dark'],
			['paper','Brand Text Light'],
		];
		const lower = slug.toLowerCase();
		for (const [kw, name] of map) { if (lower.includes(kw)) return name; }
		return slug.split(' ').map(w => w[0]?.toUpperCase() + w.slice(1)).join(' ');
	}

	function detectFontRole(font) {
		const lower = font.toLowerCase();
		if (['syne','playfair','cormorant','dm serif','libre baskerville','fraunces','clash','cabinet'].some(f => lower.includes(f))) return 'Font Display';
		if (['mono','code','fira','roboto mono','dm mono','space mono','jetbrains','courier','ibm plex mono'].some(f => lower.includes(f))) return 'Font Mono';
		return 'Font Body';
	}

	// ── REST API Helper ───────────────────────────────────────
	async function api(endpoint, opts = {}, json = true) {
		const url = SB.apiBase + endpoint;
		const headers = { 'X-WP-Nonce': SB.nonce };
		if (json && !(opts.body instanceof FormData)) {
			headers['Content-Type'] = 'application/json';
		}
		const res = await fetch(url, {
			...opts,
			credentials: 'same-origin',
			headers: { ...headers, ...(opts.headers || {}) },
		});
		if (!res.ok) {
			const err = await readApiError(res);
			throw new Error(err.message || 'Request failed');
		}
		return res.json();
	}

	async function readApiError(res) {
		const fallback = { message: 'Request failed' };
		const text = await res.text().catch(() => '');
		if (!text) return fallback;
		try {
			const parsed = JSON.parse(text);
			if (parsed && parsed.message) return parsed;
		} catch (_) {
			// Not JSON, continue with heuristic handling.
		}
		if (/cookie check failed/i.test(text)) {
			return { message: 'Cookie/session check failed. Reload WordPress admin and try again.' };
		}
		if (/nonce|rest_cookie_invalid_nonce|invalid_nonce/i.test(text)) {
			return { message: 'Security token expired. Reload the admin page to refresh nonce/cookie.' };
		}
		if (/<html/i.test(text) && /wp-login|login/i.test(text)) {
			return { message: 'Authentication expired. Log in again, then retry conversion.' };
		}
		return fallback;
	}

	// ── Toast ─────────────────────────────────────────────────
	let toastWrap;
	function createToastContainer() {
		toastWrap = document.createElement('div');
		toastWrap.className = 'sb-toasts';
		document.body.appendChild(toastWrap);
	}

	function toast(msg, type = 'inf') {
		const t = document.createElement('div');
		t.className = `sb-toast ${type}`;
		t.textContent = msg;
		toastWrap.appendChild(t);
		setTimeout(() => t?.remove(), 5000);
	}

	// ── Utilities ─────────────────────────────────────────────
	const qs    = sel => document.querySelector(sel);
	const qsa   = sel => document.querySelectorAll(sel);
	const esc   = s   => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
	const sleep = ms  => new Promise(r => setTimeout(r, ms));
	const readText = file => new Promise((res, rej) => { const r = new FileReader(); r.onload = e => res(e.target.result); r.onerror = () => rej(); r.readAsText(file); });
	const fmtBytes = b => b < 1024 ? b + ' B' : b < 1048576 ? (b/1024).toFixed(1) + ' KB' : (b/1048576).toFixed(2) + ' MB';
	const fmtDate  = s => s ? new Date(s).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' }) : '—';

	function btnLoad(btn, on) {
		if (!btn) return;
		btn.disabled = on;
		btn.classList.toggle('loading', on);
	}

	function triggerBlobDownload(content, mimeType, filename) {
		const blob = new Blob([content], { type: mimeType });
		const url  = URL.createObjectURL(blob);
		const a    = document.createElement('a');
		a.href = url; a.download = filename;
		document.body.appendChild(a);
		a.click();
		document.body.removeChild(a);
		setTimeout(() => URL.revokeObjectURL(url), 5000);
	}

})();
