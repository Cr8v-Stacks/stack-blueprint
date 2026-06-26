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
		previewDevice: 'desktop',
		previewExpanded: false,
		previewSanitizeDiagnostics: [],
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
		initLivePreview();

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
		const projectInput = qs('#sb-project-name');
		if (projectInput) {
			projectInput.value = projectNameFromFile(file.name);
			projectInput.classList.add('auto');
		}

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

	function projectNameFromFile(filename) {
		const base = String(filename || '')
			.replace(/\.[^.]+$/, '')
			.replace(/[_\s]+/g, '-')
			.replace(/[^a-zA-Z0-9-]+/g, '-')
			.replace(/-+/g, '-')
			.replace(/^-|-$/g, '');
		return base || 'my-project';
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

		renderLivePreview(record);
	}

	function hideResult() {
		qs('#sb-result')?.classList.remove('show');
		resetLivePreview();
	}

	function initLivePreview() {
		qsa('.sb-preview-device').forEach(btn => {
			btn.addEventListener('click', () => {
				setPreviewDevice(btn.dataset.previewDevice || 'desktop');
			});
		});

		qs('#sb-preview-expand')?.addEventListener('click', () => {
			togglePreviewExpanded();
		});

		qs('#sb-preview-refresh')?.addEventListener('click', () => {
			if (!S.convData) {
				toast('No converted JSON available yet.', 'err');
				return;
			}
			renderLivePreview(S.convData);
			toast('Live preview refreshed.', 'inf');
		});

		setPreviewDevice(S.previewDevice);
		setPreviewExpandedButton();
		resetLivePreview();
	}

	function setPreviewDevice(device) {
		S.previewDevice = ['desktop', 'tablet', 'mobile'].includes(device) ? device : 'desktop';
		const wrap = qs('#sb-preview-frame-wrap');
		if (wrap) {
			wrap.classList.remove('is-desktop', 'is-tablet', 'is-mobile');
			wrap.classList.add(`is-${S.previewDevice}`);
		}
		qsa('.sb-preview-device').forEach(btn => {
			btn.classList.toggle('is-active', btn.dataset.previewDevice === S.previewDevice);
		});
	}

	function resetLivePreview() {
		const frame = qs('#sb-preview-frame');
		if (frame) frame.removeAttribute('srcdoc');
		S.previewSanitizeDiagnostics = [];
		setPreviewStatus('Awaiting conversion');
		setPreviewMeta('Sandboxed preview of the converted Elementor JSON with companion CSS.');
		renderPreviewAuditBadges(null);
		renderPreviewSanitizeDiagnostics();
		togglePreviewEmpty(true, 'Run a conversion to inspect the generated layout here without opening Elementor.');
	}

	function togglePreviewExpanded(force = null) {
		S.previewExpanded = typeof force === 'boolean' ? force : !S.previewExpanded;
		qs('#sb-live-preview-card')?.classList.toggle('is-expanded', S.previewExpanded);
		document.body.classList.toggle('sb-preview-open', S.previewExpanded);
		setPreviewExpandedButton();
	}

	function setPreviewExpandedButton() {
		const btn = qs('#sb-preview-expand');
		if (btn) btn.textContent = S.previewExpanded ? 'Close Fullscreen' : 'Fullscreen';
	}

	function renderLivePreview(record) {
		const frame = qs('#sb-preview-frame');
		if (!frame) return;

		let template;
		try {
			template = JSON.parse(record?.json_output || '{}');
		} catch (e) {
			togglePreviewEmpty(true, 'Preview unavailable because the generated JSON could not be parsed.');
			setPreviewStatus('Preview error');
			setPreviewMeta('The latest conversion record returned JSON that the preview renderer could not parse.');
			return;
		}

		const doc = buildLivePreviewDocument(template, record?.css_output || '');
		frame.srcdoc = doc;

		const topLevel = Array.isArray(template?.content) ? template.content.length : 0;
		const warningCount = Array.isArray(record?.warnings) ? record.warnings.length : 0;
		const audit = extractPreviewAuditSummary(record);
		setPreviewStatus('Preview ready');
		setPreviewMeta(`${topLevel} top-level elements rendered. ${warningCount} warnings in this conversion. Device: ${S.previewDevice}.`);
		renderPreviewAuditBadges(audit);
		renderPreviewSanitizeDiagnostics();
		togglePreviewEmpty(false);
	}

	function setPreviewStatus(text) {
		const el = qs('#sb-preview-status');
		if (el) el.textContent = text;
	}

	function setPreviewMeta(text) {
		const el = qs('#sb-preview-meta');
		if (el) el.textContent = text;
	}

	function togglePreviewEmpty(show, message = '') {
		const empty = qs('#sb-preview-empty');
		if (!empty) return;
		if (message) {
			const desc = empty.querySelector('.sb-preview-empty__desc');
			if (desc) desc.textContent = message;
		}
		empty.hidden = !show;
	}

	function renderPreviewAuditBadges(audit) {
		const wrap = qs('#sb-preview-audits');
		if (!wrap) return;

		if (!audit) {
			wrap.innerHTML = `
				<span class="sb-preview-badge">Content pending</span>
				<span class="sb-preview-badge">Selectors pending</span>
				<span class="sb-preview-badge">Bridge pending</span>`;
			return;
		}

		const textState = audit.missingTextCount > 0 ? (audit.missingTextCount > 5 ? 'err' : 'warn') : 'ok';
		const selectorState = audit.missingSelectorCount > 0 ? (audit.missingSelectorCount > 3 ? 'err' : 'warn') : 'ok';
		const bridgeState = audit.hasOutputCss ? 'ok' : (audit.hasSourceCss ? 'warn' : 'ok');

		wrap.innerHTML = `
			<span class="sb-preview-badge ${textState}">Content miss ${audit.missingTextCount}/${audit.sourceTextCount}</span>
			<span class="sb-preview-badge ${selectorState}">Selector miss ${audit.missingSelectorCount}/${audit.sourceSelectorCount}</span>
			<span class="sb-preview-badge ${bridgeState}">CSS bridge ${audit.hasOutputCss ? 'active' : (audit.hasSourceCss ? 'thin' : 'none')}</span>
			<span class="sb-preview-badge ${S.previewSanitizeDiagnostics.length ? 'warn' : 'ok'}">Preview strips ${S.previewSanitizeDiagnostics.length}</span>`;
	}

	function renderPreviewSanitizeDiagnostics() {
		const wrap = qs('#sb-preview-sanitize-report');
		if (!wrap) return;
		const items = Array.isArray(S.previewSanitizeDiagnostics) ? S.previewSanitizeDiagnostics.slice(0, 6) : [];
		if (!items.length) {
			wrap.innerHTML = '';
			return;
		}

		wrap.innerHTML = items.map(item => {
			const bits = [];
			if (item.removedScripts) bits.push(`${item.removedScripts} script tags`);
			if (item.removedBlockedTags) bits.push(`${item.removedBlockedTags} blocked tags`);
			if (item.removedUnknownTags) bits.push(`${item.removedUnknownTags} unsupported tags`);
			if (item.removedEventAttrs) bits.push(`${item.removedEventAttrs} inline handlers`);
			if (item.removedUnsafeUrls) bits.push(`${item.removedUnsafeUrls} javascript: URLs`);
			if (item.cleanedStyleAttrs) bits.push(`${item.cleanedStyleAttrs} style attrs`);
			if (item.cssSanitized) bits.push('CSS sanitized');
			const meta = bits.length ? bits.join(' • ') : 'Content sanitized for preview safety.';
			return `<div class="sb-preview-sanitize-item"><p class="sb-preview-sanitize-title">${escHtmlAttr(item.label || 'Preview content')}</p><p class="sb-preview-sanitize-meta">${escHtmlAttr(meta)}</p></div>`;
		}).join('');
	}

	function extractPreviewAuditSummary(record) {
		const diagnostics = Array.isArray(record?.diagnostics) ? record.diagnostics : [];
		const report = diagnostics.find(item => item?.code === 'conversion_run_report')?.context || {};
		const coverage = report.coverage || {};
		const selectorBridge = report.bridges?.selector || {};

		return {
			sourceTextCount: Number(coverage.text?.source_phrase_count || 0),
			missingTextCount: Number(coverage.text?.missing_count || 0),
			sourceSelectorCount: Number(coverage.selectors?.source_contract_count || 0),
			missingSelectorCount: Number(coverage.selectors?.missing_count || 0),
			hasSourceCss: Boolean(selectorBridge.has_source_css),
			hasOutputCss: Boolean(selectorBridge.has_output_css),
		};
	}

	function buildLivePreviewDocument(template, cssOutput) {
		S.previewSanitizeDiagnostics = [];
		const content = Array.isArray(template?.content) ? template.content : [];
		const markup = content.map(renderPreviewElement).join('');
		const title = escHtmlAttr(template?.title || 'Stack Blueprint Preview');
		const sanitizedCss = sanitizePreviewCss(cssOutput || '', 'Companion CSS');

		return `<!doctype html>
<html class="sb-preview-root" lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="Content-Security-Policy" content="default-src 'none'; img-src data: blob: http: https:; media-src data: blob: http: https:; frame-src data: blob: http: https:; child-src data: blob: http: https:; style-src 'unsafe-inline' data: blob:; font-src data: blob: http: https:;">
<title>${title}</title>
<style>
html,body{margin:0;padding:0;min-height:100%;background:#ffffff;color:#111827}
body{font-family:Arial,sans-serif;line-height:1.5}
*,*::before,*::after{box-sizing:border-box}
img{max-width:100%;height:auto;display:block}
a{text-decoration:none;color:inherit}
.elementor,.elementor-page{width:100%}
.e-con{display:flex;position:relative;width:100%}
.e-con-inner{display:flex;width:100%}
.elementor-widget{width:100%;position:relative}
.elementor-widget-container{width:100%}
.elementor-button{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:12px 20px;border-radius:8px;border:0;background:#111827;color:#fff;font:inherit;cursor:pointer}
.elementor-heading-title{margin:0}
.elementor-icon-list-items{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:8px}
.elementor-icon-list-item{display:flex;align-items:flex-start;gap:8px}
.elementor-html{width:100%}
.sb-preview-page{width:100%;min-height:100vh;overflow-x:hidden}
${sanitizedCss}
</style>
</head>
<body class="elementor-page">
<div class="elementor elementor-page sb-preview-page">
${markup || '<div style="padding:32px;font-family:Arial,sans-serif;color:#475569">No previewable content in generated JSON.</div>'}
</div>
</body>
</html>`;
	}

	function renderPreviewElement(node) {
		if (!node || typeof node !== 'object') return '';

		if (node.elType === 'container') {
			const attrs = buildPreviewAttributes(node, ['e-con', 'sb-preview-container']);
			const styles = buildPreviewStyles(node.settings || {}, true);
			const children = Array.isArray(node.elements) ? node.elements.map(renderPreviewElement).join('') : '';
			return `<section ${attrs} style="${escHtmlAttr(styles)}">${children}</section>`;
		}

		if (node.elType === 'widget') {
			return renderPreviewWidget(node);
		}

		return '';
	}

	function renderPreviewWidget(node) {
		const settings = node.settings || {};
		const type = String(node.widgetType || '').toLowerCase();
		const attrs = buildPreviewAttributes(node, [`elementor-widget`, `elementor-widget-${type || 'unknown'}`]);
		const styles = buildPreviewStyles(settings, false);
		const label = buildPreviewNodeLabel(node);

		if (type === 'heading') {
			const tag = sanitizeTagName(settings.header_size || 'h2', ['h1','h2','h3','h4','h5','h6','div']);
			return `<div ${attrs} style="${escHtmlAttr(styles)}"><div class="elementor-widget-container"><${tag} class="elementor-heading-title">${sanitizePreviewInlineHtml(settings.title || '', `${label} title`)}</${tag}></div></div>`;
		}

		if (type === 'text-editor') {
			return `<div ${attrs} style="${escHtmlAttr(styles)}"><div class="elementor-widget-container">${sanitizePreviewHtml(settings.editor || settings.text || '', label)}</div></div>`;
		}

		if (type === 'button') {
			const url = sanitizePreviewUrl(settings.link?.url || settings.url || '#');
			const text = settings.text || settings.button_text || settings.title || 'Button';
			return `<div ${attrs} style="${escHtmlAttr(styles)}"><div class="elementor-widget-container"><a class="elementor-button" href="${escHtmlAttr(url)}"><span class="elementor-button-text">${sanitizePreviewInlineHtml(text, `${label} text`)}</span></a></div></div>`;
		}

		if (type === 'image') {
			const imageUrl = sanitizePreviewUrl(settings.image?.url || settings.url || '');
			const alt = settings.image?.alt || settings.alt || '';
			return `<div ${attrs} style="${escHtmlAttr(styles)}"><div class="elementor-widget-container">${imageUrl ? `<img src="${escHtmlAttr(imageUrl)}" alt="${escHtmlAttr(alt)}">` : ''}</div></div>`;
		}

		if (type === 'icon-list') {
			const items = Array.isArray(settings.icon_list) ? settings.icon_list : [];
			const listHtml = items.map((item, index) => {
				const text = sanitizePreviewInlineHtml(item.text || '', `${label} item ${index + 1}`);
				const href = sanitizePreviewUrl(item.link?.url || '#');
				return `<li class="elementor-icon-list-item"><span class="elementor-icon-list-text"><a href="${escHtmlAttr(href)}">${text}</a></span></li>`;
			}).join('');
			return `<div ${attrs} style="${escHtmlAttr(styles)}"><div class="elementor-widget-container"><ul class="elementor-icon-list-items">${listHtml}</ul></div></div>`;
		}

		if (type === 'video') {
			const source = sanitizePreviewUrl(settings.youtube_url || settings.vimeo_url || settings.link || settings.video_url || '');
			const mediaHtml = source ? renderPreviewVideo(source) : '';
			return `<div ${attrs} style="${escHtmlAttr(styles)}"><div class="elementor-widget-container">${mediaHtml}</div></div>`;
		}

		if (type === 'html') {
			return `<div ${attrs} style="${escHtmlAttr(styles)}"><div class="elementor-widget-container elementor-html">${sanitizePreviewHtml(settings.html || '', label)}</div></div>`;
		}

		const fallback = sanitizePreviewHtml(settings.editor || settings.text || settings.title || settings.html || '', `${label} fallback`);
		return `<div ${attrs} style="${escHtmlAttr(styles)}"><div class="elementor-widget-container">${fallback}</div></div>`;
	}

	function buildPreviewNodeLabel(node) {
		const settings = node?.settings || {};
		const type = String(node?.widgetType || node?.elType || 'element').toLowerCase();
		const elementId = settings._element_id || '';
		const cssClasses = String(settings._css_classes || '').trim().split(/\s+/).filter(Boolean);
		const hook = elementId || cssClasses[0] || node?.id || 'preview-node';
		return `${type} ${hook}`;
	}

	function renderPreviewVideo(source) {
		const safeSource = escHtmlAttr(source);
		if (/youtube\.com|youtu\.be|vimeo\.com/i.test(source)) {
			return `<iframe src="${safeSource}" style="width:100%;min-height:320px;border:0" allowfullscreen loading="lazy"></iframe>`;
		}
		return `<video src="${safeSource}" style="width:100%;height:auto" controls></video>`;
	}

	function buildPreviewAttributes(node, extraClasses = []) {
		const settings = node.settings || {};
		const classes = [];
		if (Array.isArray(extraClasses)) classes.push(...extraClasses);
		if (settings._css_classes) classes.push(String(settings._css_classes));
		const attrs = [];
		if (classes.length) attrs.push(`class="${escHtmlAttr(classes.join(' ').trim())}"`);
		if (settings._element_id) attrs.push(`id="${escHtmlAttr(settings._element_id)}"`);
		if (node.id) attrs.push(`data-elementor-id="${escHtmlAttr(node.id)}"`);
		if (node.widgetType) attrs.push(`data-widget-type="${escHtmlAttr(node.widgetType)}"`);
		return attrs.join(' ');
	}

	function buildPreviewStyles(settings, isContainer) {
		const styles = [];

		if (isContainer) {
			styles.push('display:flex');
			styles.push(`flex-direction:${settings.flex_direction || 'column'}`);
			styles.push(`flex-wrap:${settings.flex_wrap || 'nowrap'}`);
			if (settings.justify_content) styles.push(`justify-content:${settings.justify_content}`);
			if (settings.align_items) styles.push(`align-items:${settings.align_items}`);
		}

		const gap = cssGap(settings.gap);
		if (gap) styles.push(`gap:${gap}`);

		const padding = cssBoxValue(settings.padding);
		if (padding) styles.push(`padding:${padding}`);

		const margin = cssBoxValue(settings.margin);
		if (margin) styles.push(`margin:${margin}`);

		const minHeight = cssSizeValue(settings.min_height);
		if (minHeight) styles.push(`min-height:${minHeight}`);

		const width = cssSizeValue(settings.width);
		if (width) styles.push(`width:${width}`);

		const maxWidth = cssSizeValue(settings.max_width);
		if (maxWidth) styles.push(`max-width:${maxWidth}`);

		if (settings.background_color) styles.push(`background:${settings.background_color}`);
		if (settings.title_color) styles.push(`color:${settings.title_color}`);
		else if (settings.text_color) styles.push(`color:${settings.text_color}`);
		else if (settings.color) styles.push(`color:${settings.color}`);
		if (settings.overflow) styles.push(`overflow:${settings.overflow}`);
		if (settings.align) styles.push(`text-align:${settings.align}`);
		if (settings.border_border) styles.push(`border-style:${settings.border_border}`);
		if (settings.border_color) styles.push(`border-color:${settings.border_color}`);

		const borderWidth = cssBoxValue(settings.border_width);
		if (borderWidth) styles.push(`border-width:${borderWidth}`);

		const borderRadius = cssBoxValue(settings.border_radius);
		if (borderRadius) styles.push(`border-radius:${borderRadius}`);

		appendTypographyStyles(styles, settings);

		return styles.join(';');
	}

	function appendTypographyStyles(styles, settings) {
		const families = [
			settings.typography_font_family,
			settings.content_typography_font_family,
		].filter(Boolean);
		if (families[0]) styles.push(`font-family:${families[0]}`);

		const weights = [
			settings.typography_font_weight,
			settings.content_typography_font_weight,
		].filter(Boolean);
		if (weights[0]) styles.push(`font-weight:${weights[0]}`);

		const fontSize = cssSizeValue(settings.typography_font_size || settings.content_typography_font_size);
		if (fontSize) styles.push(`font-size:${fontSize}`);

		const lineHeight = cssSizeValue(settings.typography_line_height || settings.content_typography_line_height);
		if (lineHeight) styles.push(`line-height:${lineHeight}`);

		const letterSpacing = cssSizeValue(settings.typography_letter_spacing || settings.content_typography_letter_spacing);
		if (letterSpacing) styles.push(`letter-spacing:${letterSpacing}`);

		if (settings.typography_text_transform) styles.push(`text-transform:${settings.typography_text_transform}`);
	}

	function cssGap(value) {
		if (!value || typeof value !== 'object') return '';
		const row = cssNumericValue(value.row, value.unit || 'px');
		const column = cssNumericValue(value.column, value.unit || 'px');
		const size = cssNumericValue(value.size, value.unit || 'px');
		if (row && column) return `${row} ${column}`;
		return size || row || column || '';
	}

	function cssBoxValue(value) {
		if (!value) return '';
		if (typeof value === 'string' || typeof value === 'number') return cssNumericValue(value, 'px');
		if (typeof value !== 'object') return '';

		const unit = value.unit || 'px';
		const top = cssNumericValue(value.top, unit) || '0';
		const right = cssNumericValue(value.right, unit) || top;
		const bottom = cssNumericValue(value.bottom, unit) || top;
		const left = cssNumericValue(value.left, unit) || right;
		return `${top} ${right} ${bottom} ${left}`;
	}

	function cssSizeValue(value) {
		if (!value && value !== 0) return '';
		if (typeof value === 'string' || typeof value === 'number') return cssNumericValue(value, 'px');
		if (typeof value !== 'object') return '';
		return cssNumericValue(value.size, value.unit || 'px');
	}

	function cssNumericValue(value, unit = 'px') {
		if (value === '' || value === null || typeof value === 'undefined') return '';
		if (typeof value === 'string' && /[a-z%]+$/i.test(value.trim())) return value.trim();
		return `${value}${unit || 'px'}`;
	}

	function sanitizeTagName(tag, allowed) {
		const normalized = String(tag || '').toLowerCase();
		return allowed.includes(normalized) ? normalized : allowed[0];
	}

	function sanitizePreviewHtml(html, contextLabel = '') {
		return sanitizePreviewMarkup(html, {
			allowedTags: new Set([
				'a','abbr','article','aside','b','blockquote','br','button','caption','code','div','em','figcaption','figure',
				'h1','h2','h3','h4','h5','h6','hr','i','iframe','img','li','main','ol','p','picture','pre','section','small',
				'source','span','strong','sub','sup','svg','path','g','circle','rect','line','polyline','polygon','ellipse',
				'table','tbody','thead','tfoot','tr','th','td','u','ul','video'
			]),
			unwrapUnknown: true,
			contextLabel,
		});
	}

	function sanitizePreviewInlineHtml(html, contextLabel = '') {
		return sanitizePreviewMarkup(html, {
			allowedTags: new Set(['span','strong','em','b','i','small','sup','sub','u','br','mark','code']),
			unwrapUnknown: true,
			contextLabel,
		});
	}

	function sanitizePreviewMarkup(html, options = {}) {
		const source = String(html || '');
		if (!source) return '';

		const parser = new DOMParser();
		const doc = parser.parseFromString(`<div>${source}</div>`, 'text/html');
		const root = doc.body.firstElementChild;
		if (!root) return '';

		const allowedTags = options.allowedTags instanceof Set ? options.allowedTags : new Set();
		const unwrapUnknown = options.unwrapUnknown !== false;
		const contextLabel = String(options.contextLabel || '').trim();
		const blockedTags = new Set(['script', 'style', 'link', 'meta', 'base', 'object', 'embed', 'form', 'input', 'textarea', 'select']);
		const meta = {
			label: contextLabel || 'Preview content',
			removedScripts: (source.match(/<script\b/gi) || []).length,
			removedEventAttrs: (source.match(/\s+on[a-z-]+\s*=/gi) || []).length,
			removedUnsafeUrls: (source.match(/\b(?:href|src|xlink:href)\s*=\s*("javascript:[^"]*"|'javascript:[^']*'|javascript:[^\s>]+)/gi) || []).length,
			removedBlockedTags: 0,
			removedUnknownTags: 0,
			cleanedStyleAttrs: 0,
		};
		const walker = doc.createTreeWalker(root, NodeFilter.SHOW_ELEMENT);
		const elements = [];
		let current = walker.nextNode();
		while (current) {
			elements.push(current);
			current = walker.nextNode();
		}

		elements.forEach(el => {
			const tag = String(el.tagName || '').toLowerCase();
			if (blockedTags.has(tag)) {
				meta.removedBlockedTags += 1;
				el.remove();
				return;
			}

			if (allowedTags.size && !allowedTags.has(tag)) {
				meta.removedUnknownTags += 1;
				if (unwrapUnknown) {
					while (el.firstChild) {
						el.parentNode?.insertBefore(el.firstChild, el);
					}
				}
				el.remove();
				return;
			}

			Array.from(el.attributes).forEach(attr => {
				const name = String(attr.name || '').toLowerCase();
				const value = String(attr.value || '');
				if (name.startsWith('on')) {
					el.removeAttribute(attr.name);
					return;
				}

				if ((name === 'href' || name === 'src' || name === 'xlink:href') && /^\s*javascript:/i.test(value)) {
					el.setAttribute(attr.name, '#');
					return;
				}

				if (name === 'style') {
					const safeStyle = sanitizePreviewStyleAttribute(value);
					if (safeStyle !== String(value || '').trim()) {
						meta.cleanedStyleAttrs += 1;
					}
					if (safeStyle) el.setAttribute('style', safeStyle);
					else el.removeAttribute('style');
					return;
				}

				if (!isAllowedPreviewAttribute(name, tag)) {
					el.removeAttribute(attr.name);
				}
			});
		});

		const sanitized = root.innerHTML;
		recordPreviewSanitizeDiagnostic(meta, source, sanitized);
		return sanitized;
	}

	function isAllowedPreviewAttribute(name, tag) {
		if (['class', 'id', 'title', 'role', 'aria-label', 'aria-hidden', 'alt', 'width', 'height', 'viewbox', 'fill', 'stroke', 'stroke-width', 'd', 'cx', 'cy', 'r', 'x', 'y', 'x1', 'x2', 'y1', 'y2', 'points', 'preserveaspectratio', 'style', 'controls', 'poster', 'loading', 'allowfullscreen', 'frameborder'].includes(name)) {
			return true;
		}

		if (name.startsWith('data-') || name.startsWith('aria-')) {
			return true;
		}

		if ((name === 'href' || name === 'target' || name === 'rel') && tag === 'a') {
			return true;
		}

		if ((name === 'src' || name === 'srcset' || name === 'sizes') && ['img', 'iframe', 'source', 'video'].includes(tag)) {
			return true;
		}

		return false;
	}

	function sanitizePreviewStyleAttribute(style) {
		return String(style || '')
			.replace(/expression\s*\([^)]*\)/gi, '')
			.replace(/url\s*\(\s*(['"]?)\s*javascript:[^)]+\)/gi, 'none')
			.replace(/<\/?style[^>]*>/gi, '')
			.trim();
	}

	function sanitizePreviewCss(css, contextLabel = '') {
		const source = String(css || '');
		const sanitized = source
			.replace(/<\/style/gi, '<\\/style')
			.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '')
			.replace(/expression\s*\([^)]*\)/gi, '')
			.replace(/url\s*\(\s*(['"]?)\s*javascript:[^)]+\)/gi, 'url(#)')
			.replace(/@import\s+url\(\s*(['"]?)\s*javascript:[^)]+\);?/gi, '')
			.replace(/@import\s+['"]\s*javascript:[^'"]+['"];?/gi, '');
		recordPreviewSanitizeDiagnostic({
			label: contextLabel || 'Preview CSS',
			cssSanitized: sanitized !== source,
			removedScripts: (source.match(/<script\b/gi) || []).length,
			removedUnsafeUrls: (source.match(/javascript:/gi) || []).length,
			cleanedStyleAttrs: 0,
			removedBlockedTags: 0,
			removedUnknownTags: 0,
			removedEventAttrs: 0,
		}, source, sanitized);
		return sanitized;
	}

	function recordPreviewSanitizeDiagnostic(meta, original, sanitized) {
		if (!meta || String(original || '') === String(sanitized || '')) return;
		const key = JSON.stringify([
			meta.label || '',
			meta.removedScripts || 0,
			meta.removedBlockedTags || 0,
			meta.removedUnknownTags || 0,
			meta.removedEventAttrs || 0,
			meta.removedUnsafeUrls || 0,
			meta.cleanedStyleAttrs || 0,
			meta.cssSanitized ? 1 : 0,
		]);
		if (!Array.isArray(S.previewSanitizeDiagnostics)) {
			S.previewSanitizeDiagnostics = [];
		}
		if (S.previewSanitizeDiagnostics.some(item => item._key === key)) {
			return;
		}
		S.previewSanitizeDiagnostics.push({ ...meta, _key: key });
	}

	function sanitizePreviewUrl(url) {
		const value = String(url || '').trim();
		if (!value) return '';
		if (/^javascript:/i.test(value)) return '#';
		return value;
	}

	function escHtmlAttr(value) {
		return String(value ?? '')
			.replace(/&/g, '&amp;')
			.replace(/"/g, '&quot;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;');
	}

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
		// Tab Switching
		qsa('.sb-tab-btn').forEach(btn => {
			btn.addEventListener('click', () => {
				qsa('.sb-tab-btn').forEach(b => b.classList.remove('active'));
				qsa('.sb-tab-content').forEach(c => c.classList.remove('active'));
				btn.classList.add('active');
				const target = qs(`#${btn.dataset.target}`);
				if (target) target.classList.add('active');
			});
		});

		// Toggle Password Visibility
		qsa('.sb-toggle-password').forEach(btn => {
			btn.addEventListener('click', () => {
				const wrap = btn.closest('.sb-key-wrap');
				const input = wrap ? wrap.querySelector('input') : null;
				if (input) {
					input.type = input.type === 'password' ? 'text' : 'password';
				}
			});
		});

		// Change Key (hide configured text, show input)
		qsa('.sb-change-key-btn').forEach(btn => {
			btn.addEventListener('click', () => {
				const provider = btn.dataset.provider;
				const configured = qs(`#sb-${getProviderPrefix(provider)}-configured`);
				const edit = qs(`#sb-${getProviderPrefix(provider)}-edit`);
				if (configured) configured.style.display = 'none';
				if (edit) edit.style.display = 'block';
			});
		});

		// Remove Key (hide configured text, show input, clear input)
		qsa('.sb-remove-key-btn').forEach(btn => {
			btn.addEventListener('click', () => {
				const provider = btn.dataset.provider;
				const prefix = getProviderPrefix(provider);
				const configured = qs(`#sb-${prefix}-configured`);
				const edit = qs(`#sb-${prefix}-edit`);
				const input = qs(getProviderInputId(provider));
				
				if (input) input.value = '';
				if (configured) configured.style.display = 'none';
				if (edit) edit.style.display = 'block';
			});
		});

		qs('#sb-save-settings')?.addEventListener('click', async () => {
			const btn = qs('#sb-save-settings');
			btnLoad(btn, true);
			try { await saveSettings(); toast('Settings saved.', 'ok'); }
			catch (e) { toast(e.message, 'err'); }
			btnLoad(btn, false);
		});
	}

	function getProviderPrefix(provider) {
		if (provider === 'anthropic') return 'ant';
		if (provider === 'openai') return 'oai';
		if (provider === 'gemini') return 'gem';
		return '';
	}

	function getProviderInputId(provider) {
		if (provider === 'anthropic') return '#sb-api-key';
		if (provider === 'openai') return '#sb-openai-key';
		if (provider === 'gemini') return '#sb-gemini-key';
		return '';
	}

	async function saveSettings() {
		const val = id => qs(`#${id}`)?.value;
		await api('/settings', {
			method: 'POST',
			body: JSON.stringify({
				api_key:          val('sb-api-key'),
				openai_key:       val('sb-openai-key'),
				gemini_key:       val('sb-gemini-key'),
				api_model:        val('sb-api-model'),
				openai_model:     val('sb-openai-model'),
				gemini_model:     val('sb-gemini-model'),
				default_strategy: val('sb-default-strategy'),
				max_file_size:    parseInt(val('sb-max-size') || '5', 10),
			}),
		});
		
		// Optional: If saved successfully, you could reload the page to refresh the "configured" states.
		setTimeout(() => window.location.reload(), 600);
	}

	function setApiStatus(ok) {
		const dot = qs('#sb-api-dot');
		const txt = qs('#sb-api-status-txt');
		if (dot) dot.className = 'sb-conn-dot' + (ok ? ' live' : '');
		if (txt) txt.textContent = ok ? 'Keys Configured' : 'No Keys Configured';
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
