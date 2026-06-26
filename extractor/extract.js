#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const http = require('http');
const { chromium } = require('playwright');

const DEFAULT_VIEWPORTS = [
    { name: 'desktop', width: 1440, height: 900 },
    { name: 'tablet', width: 1024, height: 1366 },
    { name: 'mobile', width: 390, height: 844 }
];

const MIME_TYPES = {
    '.html': 'text/html; charset=utf-8',
    '.htm': 'text/html; charset=utf-8',
    '.css': 'text/css; charset=utf-8',
    '.js': 'application/javascript; charset=utf-8',
    '.json': 'application/json; charset=utf-8',
    '.svg': 'image/svg+xml',
    '.png': 'image/png',
    '.jpg': 'image/jpeg',
    '.jpeg': 'image/jpeg',
    '.gif': 'image/gif',
    '.webp': 'image/webp',
    '.woff': 'font/woff',
    '.woff2': 'font/woff2',
    '.ttf': 'font/ttf',
    '.otf': 'font/otf'
};

function resolveBrowserExecutable() {
    const candidates = [];

    if (process.env.SB_BROWSER_EXECUTABLE) {
        candidates.push(process.env.SB_BROWSER_EXECUTABLE);
    }

    try {
        candidates.push(chromium.executablePath());
    } catch (error) {
        // Ignore and fall back to local browser discovery.
    }

    if (process.platform === 'win32') {
        candidates.push(
            'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
            path.join(process.env.LOCALAPPDATA || '', 'Google', 'Chrome', 'Application', 'chrome.exe'),
            'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
            'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
            path.join(process.env.LOCALAPPDATA || '', 'Microsoft', 'Edge', 'Application', 'msedge.exe')
        );
    }

    return candidates.find((candidate) => candidate && fs.existsSync(candidate)) || null;
}

function parseArgs(argv) {
    const parsed = {};
    for (let i = 2; i < argv.length; i += 1) {
        const key = argv[i];
        const next = argv[i + 1];
        if (!key.startsWith('--')) {
            continue;
        }
        const name = key.slice(2);
        if (typeof next === 'undefined' || next.startsWith('--')) {
            parsed[name] = true;
            i -= 1;
            continue;
        }
        parsed[name] = next;
    }
    return parsed;
}

function readJsonFile(filePath) {
    return JSON.parse(fs.readFileSync(filePath, 'utf8'));
}

function ensureFile(filePath, label) {
    if (!filePath || !fs.existsSync(filePath) || !fs.statSync(filePath).isFile()) {
        throw new Error(`${label} not found: ${filePath || '(missing)'}`);
    }
}

function normalizeViewportList(viewports) {
    if (!Array.isArray(viewports) || viewports.length === 0) {
        return DEFAULT_VIEWPORTS;
    }

    return viewports
        .map((viewport, index) => ({
            name: String(viewport.name || `viewport-${index + 1}`),
            width: Math.max(1, Number(viewport.width || 1)),
            height: Math.max(1, Number(viewport.height || 1))
        }))
        .filter((viewport) => Number.isFinite(viewport.width) && Number.isFinite(viewport.height));
}

function sanitizePathname(pathname) {
    try {
        return decodeURIComponent(pathname.split('?')[0].split('#')[0]);
    } catch (error) {
        return pathname.split('?')[0].split('#')[0];
    }
}

function createStaticServer(baseDir) {
    const root = path.resolve(baseDir);

    return new Promise((resolve, reject) => {
        const server = http.createServer((request, response) => {
            const rawPath = sanitizePathname(request.url || '/');
            const requested = rawPath === '/' ? '/index.html' : rawPath;
            const filePath = path.resolve(root, `.${requested}`);

            if (!filePath.startsWith(root)) {
                response.writeHead(403, { 'Content-Type': 'text/plain; charset=utf-8' });
                response.end('Forbidden');
                return;
            }

            fs.readFile(filePath, (error, data) => {
                if (error) {
                    response.writeHead(404, { 'Content-Type': 'text/plain; charset=utf-8' });
                    response.end('Not found');
                    return;
                }

                const ext = path.extname(filePath).toLowerCase();
                response.writeHead(200, {
                    'Content-Type': MIME_TYPES[ext] || 'application/octet-stream',
                    'Cache-Control': 'no-store'
                });
                response.end(data);
            });
        });

        server.listen(0, '127.0.0.1', () => {
            const address = server.address();
            if (!address || typeof address === 'string') {
                reject(new Error('Could not determine static server address.'));
                return;
            }
            resolve({
                server,
                origin: `http://127.0.0.1:${address.port}`
            });
        });

        server.on('error', reject);
    });
}

async function waitForPageSettling(page, waitAfterLoadMs) {
    await page.waitForLoadState('domcontentloaded');
    try {
        await page.waitForLoadState('networkidle', { timeout: 5000 });
    } catch (error) {
        // Some pages never reach networkidle cleanly. This is not fatal for the spike.
    }

    try {
        await page.evaluate(async () => {
            if (document.fonts && typeof document.fonts.ready?.then === 'function') {
                await document.fonts.ready;
            }
        });
    } catch (error) {
        // Fonts API is not always available or may reject; ignore for the spike.
    }

    if (waitAfterLoadMs > 0) {
        await page.waitForTimeout(waitAfterLoadMs);
    }
}

function buildExtractionScript() {
    return () => {
        const RELEVANT_STYLES = [
            'display',
            'position',
            'top',
            'right',
            'bottom',
            'left',
            'z-index',
            'width',
            'height',
            'min-width',
            'min-height',
            'max-width',
            'max-height',
            'margin-top',
            'margin-right',
            'margin-bottom',
            'margin-left',
            'padding-top',
            'padding-right',
            'padding-bottom',
            'padding-left',
            'background-color',
            'background-image',
            'background-size',
            'background-position',
            'background-repeat',
            'color',
            'opacity',
            'visibility',
            'overflow',
            'overflow-x',
            'overflow-y',
            'font-family',
            'font-size',
            'font-weight',
            'font-style',
            'line-height',
            'letter-spacing',
            'text-align',
            'text-transform',
            'white-space',
            'text-decoration-line',
            'flex-direction',
            'flex-wrap',
            'justify-content',
            'align-items',
            'align-content',
            'gap',
            'row-gap',
            'column-gap',
            'grid-template-columns',
            'grid-template-rows',
            'grid-column',
            'grid-row',
            'border-top-width',
            'border-right-width',
            'border-bottom-width',
            'border-left-width',
            'border-top-style',
            'border-right-style',
            'border-bottom-style',
            'border-left-style',
            'border-top-color',
            'border-right-color',
            'border-bottom-color',
            'border-left-color',
            'border-radius',
            'border-top-left-radius',
            'border-top-right-radius',
            'border-bottom-left-radius',
            'border-bottom-right-radius',
            'box-shadow',
            'transform',
            'transition',
            'animation',
            'object-fit',
            'object-position',
            'aspect-ratio',
            'pointer-events',
            'cursor'
        ];

        function readComputedStyles(element) {
            const computed = window.getComputedStyle(element);
            const styles = {};
            for (const property of RELEVANT_STYLES) {
                const value = computed.getPropertyValue(property);
                if (!value) {
                    continue;
                }
                const trimmed = value.trim();
                if (
                    trimmed === '' ||
                    trimmed === 'auto' ||
                    trimmed === 'normal' ||
                    trimmed === 'none' ||
                    trimmed === '0px' ||
                    trimmed === 'rgba(0, 0, 0, 0)'
                ) {
                    continue;
                }
                styles[property] = trimmed;
            }

            styles.display = computed.display;
            styles.position = computed.position;
            styles.color = computed.color;
            styles['background-color'] = computed.backgroundColor;
            styles['font-family'] = computed.fontFamily;

            return styles;
        }

        function readBox(element) {
            const rect = element.getBoundingClientRect();
            return {
                x: Math.round(rect.x),
                y: Math.round(rect.y),
                width: Math.round(rect.width),
                height: Math.round(rect.height),
                top: Math.round(rect.top),
                right: Math.round(rect.right),
                bottom: Math.round(rect.bottom),
                left: Math.round(rect.left)
            };
        }

        function isVisible(element) {
            const styles = window.getComputedStyle(element);
            if (styles.display === 'none' || styles.visibility === 'hidden') {
                return false;
            }
            if (parseFloat(styles.opacity || '1') === 0) {
                return false;
            }
            const rect = element.getBoundingClientRect();
            if (rect.width === 0 && rect.height === 0 && styles.position !== 'fixed' && styles.position !== 'absolute') {
                return false;
            }
            return true;
        }

        function collectAttributes(element) {
            const attributes = {};
            for (const attr of Array.from(element.attributes || [])) {
                attributes[attr.name] = attr.value;
            }
            return attributes;
        }

        function readPseudoStyles(element, pseudo) {
            const computed = window.getComputedStyle(element, pseudo);
            const content = computed.getPropertyValue('content');
            if (!content || content === 'none' || content === 'normal' || content === '""') {
                return null;
            }
            return {
                content,
                color: computed.getPropertyValue('color').trim(),
                backgroundColor: computed.getPropertyValue('background-color').trim(),
                fontSize: computed.getPropertyValue('font-size').trim(),
                fontWeight: computed.getPropertyValue('font-weight').trim(),
                position: computed.getPropertyValue('position').trim(),
                width: computed.getPropertyValue('width').trim(),
                height: computed.getPropertyValue('height').trim(),
                transform: computed.getPropertyValue('transform').trim()
            };
        }

        function directTextContent(element) {
            let text = '';
            for (const node of Array.from(element.childNodes || [])) {
                if (node.nodeType === Node.TEXT_NODE) {
                    text += node.textContent || '';
                }
            }
            return text.replace(/\s+/g, ' ').trim();
        }

        function inferRoleHints(tag, element, styles, directText, innerText) {
            const text = (innerText || '').trim();
            const id = (element.id || '').toLowerCase();
            const classes = Array.from(element.classList || []).map((item) => item.toLowerCase());
            const href = (element.getAttribute('href') || '').trim();
            const roleHints = [];

            if (/^h[1-6]$/.test(tag)) roleHints.push('heading');
            if (tag === 'p' || (text && element.children.length === 0 && tag !== 'button' && tag !== 'a')) roleHints.push('text');
            if (tag === 'a' || tag === 'button' || element.getAttribute('role') === 'button') roleHints.push('action');
            if (tag === 'img' || tag === 'picture' || tag === 'svg' || tag === 'video' || tag === 'canvas') roleHints.push('media');
            if (tag === 'ul' || tag === 'ol') roleHints.push('list');
            if (tag === 'nav') roleHints.push('nav');
            if (tag === 'header') roleHints.push('header');
            if (tag === 'footer') roleHints.push('footer');
            if (styles.display === 'flex' || styles.display === 'grid' || element.children.length > 0) roleHints.push('container');
            if (href.startsWith('#')) roleHints.push('anchor-link');
            if (element.hasAttribute('data-tab') || element.hasAttribute('data-filter') || element.hasAttribute('data-panel')) roleHints.push('stateful');
            if (id.includes('hero') || classes.some((item) => item.includes('hero'))) roleHints.push('hero');
            if (id.includes('price') || classes.some((item) => item.includes('price'))) roleHints.push('pricing');
            if (id.includes('stat') || classes.some((item) => item.includes('stat'))) roleHints.push('stat');
            if (directText && directText.length <= 48 && tag === 'a') roleHints.push('link-label');

            return Array.from(new Set(roleHints));
        }

        function classifyElement(tag, element, styles, box, roleHints) {
            if (tag === 'canvas') return 'CANVAS';
            if (tag === 'video') return 'VIDEO';
            if (tag === 'img' || tag === 'picture' || tag === 'svg') return 'IMAGE';
            if (tag === 'table') return 'HTML_WIDGET';
            if (styles.position === 'fixed') return 'FIXED';
            if (styles.animation && styles.animation !== 'none') return 'HTML_WIDGET';
            if (styles.display === 'grid') return 'GRID_CONTAINER';
            if (styles.display === 'flex') return styles['flex-direction'] === 'column' ? 'FLEX_COLUMN' : 'FLEX_ROW';
            if (/^h[1-6]$/.test(tag)) return 'HEADING';
            if (tag === 'button') return 'BUTTON';
            if (tag === 'a') {
                const hasBackground = !!styles['background-color'] && styles['background-color'] !== 'rgba(0, 0, 0, 0)';
                const hasPadding = parseInt(styles['padding-top'] || '0', 10) > 4 || parseInt(styles['padding-left'] || '0', 10) > 8;
                return hasBackground || hasPadding ? 'BUTTON' : 'LINK';
            }
            if (tag === 'ul' || tag === 'ol') return 'LIST';
            if (tag === 'section' || tag === 'article' || tag === 'main' || tag === 'aside') return 'SECTION';
            if (tag === 'header') return 'HEADER_SECTION';
            if (tag === 'footer') return 'FOOTER_SECTION';
            if (roleHints.includes('text')) return 'TEXT';
            if (box.width === 0 && box.height === 0) return 'HIDDEN';
            if (element.children.length > 0) return 'CONTAINER';
            return 'DECORATIVE';
        }

        function shouldCaptureHtml(tag, classification) {
            if (classification === 'HTML_WIDGET') {
                return true;
            }
            return ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'a', 'button', 'li', 'blockquote'].includes(tag);
        }

        function extractNode(element, depth) {
            if (!element || element.nodeType !== Node.ELEMENT_NODE) {
                return null;
            }

            const tag = element.tagName.toLowerCase();
            if (['script', 'style', 'meta', 'link', 'noscript', 'head', 'title'].includes(tag)) {
                return null;
            }

            const visible = isVisible(element);
            if (!visible && depth > 0) {
                return null;
            }

            const computedStyles = readComputedStyles(element);
            const box = readBox(element);
            const directText = directTextContent(element);
            const innerText = (element.innerText || '').replace(/\s+/g, ' ').trim();
            const roleHints = inferRoleHints(tag, element, computedStyles, directText, innerText);
            const classification = classifyElement(tag, element, computedStyles, box, roleHints);

            const children = [];
            for (const child of Array.from(element.children || [])) {
                const extracted = extractNode(child, depth + 1);
                if (extracted) {
                    children.push(extracted);
                }
            }

            return {
                tag,
                id: element.id || null,
                classes: Array.from(element.classList || []),
                attributes: collectAttributes(element),
                classification,
                roleHints,
                visible,
                depth,
                text: innerText,
                directText,
                html: shouldCaptureHtml(tag, classification) ? element.outerHTML : null,
                computedStyles,
                box,
                pseudoBefore: readPseudoStyles(element, '::before'),
                pseudoAfter: readPseudoStyles(element, '::after'),
                children
            };
        }

        function extractDesignTokens() {
            const tokens = {};
            for (const sheet of Array.from(document.styleSheets || [])) {
                let cssRules;
                try {
                    cssRules = sheet.cssRules || [];
                } catch (error) {
                    continue;
                }
                for (const rule of Array.from(cssRules)) {
                    if (rule.selectorText !== ':root') {
                        continue;
                    }
                    const style = rule.style;
                    for (const property of Array.from(style || [])) {
                        if (property.startsWith('--')) {
                            tokens[property] = style.getPropertyValue(property).trim();
                        }
                    }
                }
            }
            return tokens;
        }

        function extractFonts() {
            const fonts = new Set();
            for (const element of Array.from(document.querySelectorAll('*'))) {
                const family = window.getComputedStyle(element).fontFamily;
                if (!family) {
                    continue;
                }
                const primary = family.split(',')[0].replace(/['"]/g, '').trim();
                if (primary) {
                    fonts.add(primary);
                }
            }
            return Array.from(fonts);
        }

        function extractKeyframes() {
            const keyframes = {};
            for (const sheet of Array.from(document.styleSheets || [])) {
                let cssRules;
                try {
                    cssRules = sheet.cssRules || [];
                } catch (error) {
                    continue;
                }
                for (const rule of Array.from(cssRules)) {
                    if (typeof CSSRule !== 'undefined' && rule.type === CSSRule.KEYFRAMES_RULE) {
                        keyframes[rule.name] = rule.cssText;
                    }
                }
            }
            return keyframes;
        }

        function countNodes(node) {
            if (!node) {
                return 0;
            }
            return 1 + (node.children || []).reduce((sum, child) => sum + countNodes(child), 0);
        }

        const bodyNode = extractNode(document.body, 0);

        return {
            title: document.title || '',
            url: window.location.href,
            viewport: {
                width: window.innerWidth,
                height: window.innerHeight
            },
            documentMetrics: {
                scrollWidth: document.documentElement.scrollWidth,
                scrollHeight: document.documentElement.scrollHeight,
                bodyWidth: document.body ? document.body.scrollWidth : 0,
                bodyHeight: document.body ? document.body.scrollHeight : 0
            },
            designTokens: extractDesignTokens(),
            fonts: extractFonts(),
            keyframes: extractKeyframes(),
            root: bodyNode,
            stats: {
                totalNodes: countNodes(bodyNode)
            }
        };
    };
}

async function extractWithBrowser(config) {
    ensureFile(config.inputPath, 'Input HTML');

    const baseDir = path.resolve(config.baseDir || path.dirname(config.inputPath));
    const inputFile = path.resolve(config.inputPath);
    const relativeInput = path.relative(baseDir, inputFile).split(path.sep).join('/');
    const entryPath = relativeInput.startsWith('.') ? path.basename(inputFile) : relativeInput;

    const staticServer = await createStaticServer(baseDir);
    const executablePath = resolveBrowserExecutable();
    const browser = await chromium.launch({
        headless: true,
        ...(executablePath ? { executablePath } : {})
    });
    const viewports = normalizeViewportList(config.viewports);
    const waitAfterLoadMs = Math.max(0, Number(config.waitAfterLoadMs || 750));

    const output = {
        extractorVersion: '0.1.0',
        generatedAt: new Date().toISOString(),
        input: {
            inputPath: inputFile,
            baseDir,
            entryPath
        },
        runtime: {
            nodeVersion: process.version,
            platform: process.platform,
            executablePath: executablePath || ''
        },
        viewports: {}
    };

    try {
        for (const viewport of viewports) {
            const page = await browser.newPage({
                viewport: {
                    width: viewport.width,
                    height: viewport.height
                }
            });

            const targetUrl = `${staticServer.origin}/${entryPath}`;
            await page.goto(targetUrl, {
                waitUntil: 'load',
                timeout: Number(config.timeoutMs || 45000)
            });
            await waitForPageSettling(page, waitAfterLoadMs);

            const extraction = await page.evaluate(buildExtractionScript());
            output.viewports[viewport.name] = extraction;

            await page.close();
        }

        output.primaryViewport = viewports[0]?.name || 'desktop';
        output.title = output.viewports[output.primaryViewport]?.title || '';
        output.fonts = output.viewports[output.primaryViewport]?.fonts || [];
        output.designTokens = output.viewports[output.primaryViewport]?.designTokens || {};
        output.keyframes = output.viewports[output.primaryViewport]?.keyframes || {};

        return output;
    } finally {
        await browser.close().catch(() => {});
        await new Promise((resolve) => staticServer.server.close(resolve));
    }
}

async function main() {
    const args = parseArgs(process.argv);
    if (!args.config) {
        throw new Error('Usage: node extract.js --config <config-json-path>');
    }

    const config = readJsonFile(args.config);
    const result = await extractWithBrowser(config);

    if (config.outputPath) {
        fs.writeFileSync(config.outputPath, JSON.stringify(result, null, 2));
    } else {
        process.stdout.write(JSON.stringify(result, null, 2));
    }
}

main().catch((error) => {
    process.stderr.write(`Extraction failed: ${error.message}\n`);
    process.exit(1);
});
