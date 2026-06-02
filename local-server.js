const fs = require('fs');
const http = require('http');
const path = require('path');
const { URL } = require('url');
const admin = require('./admin');

const root = __dirname;
const publicDir = path.join(root, 'public_html');
const dataDir = path.join(publicDir, 'data');
const statePath = path.join(dataDir, 'state.json');
const articlesPath = path.join(dataDir, 'articles.json');
const siteName = 'MIYIZE Kannada News';
const siteUrl = String(process.env.MIYIZE_SITE_URL || 'https://miyize.com').replace(/\/+$/, '');
const siteTagline = 'ವೇಗದ, ವಿಶ್ವಾಸಾರ್ಹ ಕನ್ನಡ ಸುದ್ದಿ';
const fallbackImage = '/assets/images/newsroom-fallback.png';
const categories = {
    latest: 'ತಾಜಾ ಸುದ್ದಿ',
    karnataka: 'ಕರ್ನಾಟಕ',
    india: 'ಭಾರತ',
    world: 'ವಿಶ್ವ',
    politics: 'ರಾಜಕೀಯ',
    business: 'ವ್ಯಾಪಾರ',
    sports: 'ಕ್ರೀಡೆ',
    cinema: 'ಸಿನಿಮಾ',
    technology: 'ತಂತ್ರಜ್ಞಾನ',
    health: 'ಆರೋಗ್ಯ',
    education: 'ಶಿಕ್ಷಣ',
    crime: 'ಅಪರಾಧ',
    'fact-check': 'ಫ್ಯಾಕ್ಟ್ ಚೆಕ್',
    agriculture: 'ಕೃಷಿ',
    lifestyle: 'ಜೀವನಶೈಲಿ',
    automobile: 'ಆಟೋಮೊಬೈಲ್',
    career: 'ಉದ್ಯೋಗ',
    astrology: 'ಭವಿಷ್ಯ',
    trends: 'ಟ್ರೆಂಡಿಂಗ್',
};

const feedQueries = {
    latest: 'ಕನ್ನಡ ಸುದ್ದಿ',
    karnataka: 'ಕರ್ನಾಟಕ ಸುದ್ದಿ ಬೆಂಗಳೂರು ಮೈಸೂರು',
    india: 'ಭಾರತ ಸುದ್ದಿ ಕನ್ನಡ',
    world: 'ವಿಶ್ವ ಸುದ್ದಿ ಕನ್ನಡ',
    politics: 'ಕರ್ನಾಟಕ ರಾಜಕೀಯ',
    business: 'ವ್ಯಾಪಾರ ಆರ್ಥಿಕತೆ ಷೇರು ಮಾರುಕಟ್ಟೆ ಕನ್ನಡ',
    sports: 'ಕ್ರೀಡೆ ಕ್ರಿಕೆಟ್ ಕನ್ನಡ',
    cinema: 'ಕನ್ನಡ ಸಿನಿಮಾ ಸುದ್ದಿ',
    technology: 'ತಂತ್ರಜ್ಞಾನ AI ಮೊಬೈಲ್ ಕನ್ನಡ',
    health: 'ಆರೋಗ್ಯ ಸುದ್ದಿ',
    education: 'ಶಿಕ್ಷಣ ಸುದ್ದಿ',
    crime: 'ಅಪರಾಧ ಸುದ್ದಿ ಕರ್ನಾಟಕ',
    'fact-check': 'ಫ್ಯಾಕ್ಟ್ ಚೆಕ್ ಕನ್ನಡ',
    agriculture: 'ಕೃಷಿ ಸುದ್ದಿ',
    lifestyle: 'ಜೀವನಶೈಲಿ ಫ್ಯಾಷನ್ ಆಹಾರ',
    automobile: 'ಆಟೋಮೊಬೈಲ್ ಕಾರು ಬೈಕು launch',
    career: 'ಉದ್ಯೋಗ ಸುದ್ದಿ',
    astrology: 'ರಾಶಿ ಭವಿಷ್ಯ ದಿನಭವಿಷ್ಯ',
    trends: 'https://trends.google.com/trends/trendingsearches/daily/rss?geo=IN',
};

const directFeeds = [
    'https://kannada.asianetnews.com/rss',
];

const autoRefreshMs = Number(process.env.MIYIZE_REFRESH_MS || 5 * 60 * 1000);
let refreshInFlight = null;

function readJson(file, fallback) {
    try {
        return JSON.parse(fs.readFileSync(file, 'utf8'));
    } catch {
        return fallback;
    }
}

function writeJson(file, value) {
    fs.mkdirSync(path.dirname(file), { recursive: true });
    fs.writeFileSync(file, JSON.stringify(value, null, 2), 'utf8');
}

function esc(value = '') {
    return String(value).replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    })[char]);
}

function decodeHtml(value = '') {
    return String(value)
        .replace(/<!\[CDATA\[([\s\S]*?)\]\]>/g, '$1')
        .replace(/&nbsp;/g, ' ')
        .replace(/&amp;/g, '&')
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .replace(/&quot;/g, '"')
        .replace(/&#39;/g, "'")
        .replace(/&#x27;/g, "'");
}

function stripHtml(value = '') {
    return decodeHtml(value).replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
}

function tag(xml, name) {
    const match = String(xml).match(new RegExp(`<${name}[^>]*>([\\s\\S]*?)<\\/${name}>`, 'i'));
    return match ? decodeHtml(match[1]).trim() : '';
}

function attr(xml, tagName, attrName) {
    const match = String(xml).match(new RegExp(`<${tagName}[^>]*${attrName}=["']([^"']+)["'][^>]*>`, 'i'));
    return match ? decodeHtml(match[1]).trim() : '';
}

function cleanNewsTitle(title, source = '') {
    let clean = stripHtml(title);
    if (source) {
        clean = clean.replace(new RegExp(`\\s+-\\s+${source.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}$`, 'iu'), '');
    }
    return clean;
}

function slugFor(title, url) {
    const ascii = title.toLowerCase().replace(/[^a-z0-9]+/ig, '-').replace(/-+/g, '-').replace(/^-|-$/g, '');
    const crypto = require('crypto');
    return `${ascii || 'kannada-news'}-${crypto.createHash('sha1').update(url).digest('hex').slice(0, 10)}`;
}

function rssUrl(query) {
    if (query.startsWith('http')) return query;
    const params = new URLSearchParams({ q: query, hl: 'kn', gl: 'IN', ceid: 'IN:kn' });
    return `https://news.google.com/rss/search?${params.toString()}`;
}

function articles(limit = 0) {
    const items = readJson(articlesPath, []);
    items.sort((a, b) => String(b.published_at || '').localeCompare(String(a.published_at || '')));
    return limit > 0 ? items.slice(0, limit) : items;
}

function findArticle(slug) {
    return articles().find((article) => article.slug === slug);
}

function mergeKey(article) {
    const url = article.source_url || '';
    if (url && !url.startsWith('/')) return url;
    return article.slug || article.id || `${article.category}:${article.title}`;
}

function categoryArticles(slug, limit = 12) {
    const filtered = articles().filter((article) => slug === 'latest' || article.category === slug);
    return limit > 0 ? filtered.slice(0, limit) : filtered;
}

function articleUrl(article) {
    return `/article/${encodeURIComponent(article.slug || '')}`;
}

function categoryUrl(slug) {
    return `/category/${encodeURIComponent(slug)}`;
}

function absoluteUrl(route = '/') {
    const normalized = String(route).startsWith('/') ? route : `/${route}`;
    return `${siteUrl}${normalized}`;
}

function articleImage(article) {
    return article.image || fallbackImage;
}

function excerpt(value = '', length = 180) {
    const clean = String(value).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
    return clean.length > length ? `${clean.slice(0, length).trim()}...` : clean;
}

function formatDate(value) {
    const time = value ? Date.parse(value) : 0;
    if (!time) return 'ಇತ್ತೀಚೆಗೆ';
    const diff = Date.now() - time;
    if (diff < 60000) return 'ಈಗಷ್ಟೆ';
    if (diff < 3600000) return `${Math.floor(diff / 60000)} ನಿಮಿಷ ಹಿಂದೆ`;
    if (diff < 86400000) return `${Math.floor(diff / 3600000)} ಗಂಟೆ ಹಿಂದೆ`;
    return new Date(time).toLocaleString('en-IN', { dateStyle: 'medium', timeStyle: 'short' });
}

async function fetchText(url, timeoutMs = 10000) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), timeoutMs);
    try {
        const response = await fetch(url, {
            signal: controller.signal,
            redirect: 'follow',
            headers: {
                'user-agent': 'MIYIZE Kannada News Bot/1.0 (+local preview)',
                accept: 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            },
        });
        if (!response.ok) return '';
        return Buffer.from(await response.arrayBuffer()).toString('utf8');
    } catch {
        return '';
    } finally {
        clearTimeout(timer);
    }
}

function metaImageFromHtml(html, baseUrl) {
    const patterns = [
        /<meta[^>]+(?:property|name)=["'](?:og:image|twitter:image|twitter:image:src)["'][^>]+content=["']([^"']+)["'][^>]*>/i,
        /<meta[^>]+content=["']([^"']+)["'][^>]+(?:property|name)=["'](?:og:image|twitter:image|twitter:image:src)["'][^>]*>/i,
        /<link[^>]+rel=["']image_src["'][^>]+href=["']([^"']+)["'][^>]*>/i,
    ];

    for (const pattern of patterns) {
        const match = html.match(pattern);
        if (match && match[1]) {
            try {
                return new URL(decodeHtml(match[1]), baseUrl).toString();
            } catch {
                return decodeHtml(match[1]);
            }
        }
    }
    return '';
}

async function fetchMetaImage(url) {
    const html = await fetchText(url, 6500);
    return html ? metaImageFromHtml(html, url) : '';
}

function feedImageFromItem(item) {
    const media = attr(item, 'media:content', 'url') || attr(item, 'media:thumbnail', 'url') || attr(item, 'enclosure', 'url');
    if (media) return media;
    const description = tag(item, 'description');
    return attr(description, 'img', 'src');
}

async function geminiWriteArticle(article) {
    const apiKey = process.env.MIYIZE_GEMINI_KEY || process.env.GEMINI_API_KEY || '';
    if (!apiKey) return null;

    const title = article.title || '';
    const summary = article.summary || '';
    const source = article.source || '';
    const cat = article.category_label || '';

    const prompt = `You are a professional Kannada news journalist. Write a complete, detailed, and highly engaging news article in Kannada based on the following information.

Title: ${title}
Summary: ${summary}
Source: ${source}
Category: ${cat}

Requirements:
- IMPORTANT: Even if the provided Summary is extremely short, generic, or only contains 1 or 2 sentences, you must use your general knowledge to fully expand the topic. Write a comprehensive, detailed article of 400 to 800 words containing 5 to 8 paragraphs. Deeply elaborate on the background context, implications, key figures or organizations, and future expectations. Never explain the lack of info; write a full, rich news report based on the Title alone.
- Do NOT output any introductory or concluding meta-text, explanations, or notes (e.g. do not say things like 'Here is the expanded news...', 'Based on the short summary...', 'I have used my general knowledge...', or 'Here are some additional explanations...'). The output must start directly with the news content and contain only the article itself.
- Do NOT restrict the length or paragraph count to a fixed format. Write dynamically and organically to thoroughly cover all aspects of the news story.
- Include deep context, professional background details, potential societal or political impact, and quotes (inferred in a realistic and professional journalistic manner).
- Start with the most important news fact.
- Use rich, formal, and appealing Kannada vocabulary.
- Do NOT include markdown headers, bold titles, or the title in the body.
- Separate paragraphs with a blank line.
- Write only in Kannada (using the Kannada script).

Write the article now:`;

    try {
        const url = `https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=${apiKey}`;
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                contents: [{ parts: [{ text: prompt }] }],
                generationConfig: {
                    temperature: 0.75,
                    maxOutputTokens: 2048,
                }
            })
        });
        if (!response.ok) return null;
        const data = await response.json();
        const text = data?.candidates?.[0]?.content?.parts?.[0]?.text;
        return text ? text.trim() : null;
    } catch (e) {
        console.error('Gemini API error in local-server:', e);
        return null;
    }
}

function generateDynamicKannadaBody(title, summary, source, category, catSlug) {
    title = (title || '').trim();
    summary = stripHtml(summary || '').trim();
    if (!summary || summary.length < 10) {
        summary = title;
    }

    const summarySentences = summary.split(/(?<=[.!?।])\s+/u).map(s => s.trim()).filter(Boolean);

    const intros = [
        `${title}. ಈ ಮಹತ್ವದ ಬೆಳವಣಿಗೆಯ ಕುರಿತು ಪ್ರಸ್ತುತ ವ್ಯಾಪಕ ಚರ್ಚೆಗಳು ನಡೆಯುತ್ತಿದ್ದು, ಸಾರ್ವಜನಿಕ ವಲಯದಲ್ಲಿ ತೀವ್ರ ಕುತೂಹಲ ಮೂಡಿಸಿದೆ.`,
        `ಇಂದಿನ ಪ್ರಮುಖ ವಿದ್ಯಮಾನಗಳಲ್ಲಿ ಒಂದಾದ ${title} ವಿಷಯವು ಸಾರ್ವಜನಿಕರ ಗಮನ ಸೆಳೆದಿದೆ. ಇದಕ್ಕೆ ಸಂಬಂಧಿಸಿದ ಮಹತ್ವದ ವರದಿಗಳು ಹೊರಬಿದ್ದಿವೆ.`,
        `${title}. ವರದಿಗಳ ಪ್ರಕಾರ, ಈ ಹೊಸ ಬೆಳವಣಿಗೆಯು ಪ್ರಸ್ತುತ ಪರಿಸ್ಥಿತಿಯಲ್ಲಿ ಅತ್ಯಂತ ಪ್ರಭಾವಶಾಲಿ ಪರಿಣಾಮ ಬೀರಲಿದೆ ಎಂದು ಅಂದಾಜಿಸಲಾಗಿದೆ.`
    ];

    const connectors = [
        `ಲಭ್ಯವಿರುವ ಪ್ರಾಥಮಿಕ ಮಾಹಿತಿಯ ಪ್ರಕಾರ, `,
        `ಈ ವಿಷಯಕ್ಕೆ ಸಂಬಂಧಿಸಿದಂತೆ ಪ್ರಕಟವಾಗಿರುವ ಅಧಿಕೃತ ವರದಿಯಲ್ಲಿ, `,
        `ಪ್ರಸ್ತುತ ಲಭ್ಯವಿರುವ ವಿವರಗಳ ಅನ್ವಯ, `
    ];

    const details = [
        `ಮುಂಬರುವ ದಿನಗಳಲ್ಲಿ ಈ ನಿರ್ಧಾರವು ಇಡೀ ಕ್ಷೇತ್ರದ ಮೇಲೆ ದೊಡ್ಡ ಪ್ರಮಾಣದ ಪರಿಣಾಮ ಬೀರಬಹುದು ಎಂದು ತಜ್ಞರು ವಿಶ್ಲೇಷಿಸುತ್ತಿದ್ದಾರೆ.`,
        `ಸಾರ್ವಜನಿಕರು ಈ ಬದಲಾವಣೆಗೆ ಸಂಬಂಧಿಸಿದಂತೆ ಮಿಶ್ರ ಪ್ರತಿಕ್ರಿಯೆಗಳನ್ನು ವ್ಯಕ್ತಪಡಿಸುತ್ತಿದ್ದು, ಮುಂದಿನ ಹೆಜ್ಜೆಗಳ ಮೇಲೆ ನಿಗา ಇರಿಸಿದ್ದಾರೆ.`,
        `ಸಂಬಂಧಪಟ್ಟ ಅಧಿಕಾರಿಗಳು ಈ ಹೊಸ ಮಾರ್ಗಸೂಚಿಗಳನ್ನು ಜಾರಿಗೆ ತರಲು ಸಿದ್ಧತೆ ನಡೆಸುತ್ತಿದ್ದು, ಶೀಘ್ರದಲ್ಲೇ ಹೆಚ್ಚಿನ ಸ್ಪಷ್ಟತೆ ಸಿಗುವ ಸಾಧ್ಯತೆಯಿದೆ.`
    ];

    const outros = [
        `ಈ ವಿಷಯದ ಪ್ರತಿಯೊಂದು ಹಂತದ ಬೆಳವಣಿಗೆಗಳನ್ನು ಸೂಕ್ಷ್ಮವಾಗಿ ಗಮನಿಸಲಾಗುತ್ತಿದ್ದು, ಹೆಚ್ಚಿನ ಮಾಹಿತಿಗಾಗಿ ಮೂಲ ವರದಿಯನ್ನು ಪರಿಶೀಲಿಸಬಹುದಾಗಿದೆ.`,
        `ಹೆಚ್ಚಿನ ವಿವರಗಳು ಮತ್ತು ಅಧಿಕೃತ ಹೇಳಿಕೆಗಳು ಲಭ್ಯವಾದ ತಕ್ಷಣ ಈ ವರದಿಯನ್ನು ನವೀಕರಿಸಲಾಗುತ್ತದೆ.`
    ];

    const catContext = {
        karnataka: 'ಕರ್ನಾಟಕ ರಾಜ್ಯದ ಹಿತಾಸಕ್ತಿಗಳು ಮತ್ತು ಸ್ಥಳೀಯ ನಾಗರಿಕರ ದೃಷ್ಟಿಯಿಂದ ಈ ಸುದ್ದಿ ಅತ್ಯಂತ ಮಹತ್ವದ್ದಾಗಿದ್ದು, ಪ್ರಮುಖ ಸ್ಥಳಗಳಲ್ಲಿ ಇದರ ಚರ್ಚೆಗಳು ತೀವ್ರಗೊಂಡಿವೆ.',
        india: 'ರಾಷ್ಟ್ರೀಯ ಮಟ್ಟದಲ್ಲಿ ಕಂಡುಬರುತ್ತಿರುವ ಪ್ರಮುಖ ಆರ್ಥಿಕ ಮತ್ತು ರಾಜಕೀಯ ಸಮೀಕರಣಗಳಿಗೆ ಈ ಘಟನೆಯು ಹೊಸ ತಿರುವು ನೀಡಬಹುದು ಎಂದು ಭಾವಿಸಲಾಗಿದೆ.',
        world: 'ಜಾಗತಿಕ ವಿದ್ಯಮಾನಗಳ ಮೇಲೆ ಈ ಬೆಳವಣಿಗೆಯು ಸೂಕ್ಷ್ಮ ಪರಿಣಾಮ ಬೀರಲಿದ್ದು, ಅಂತರಾಷ್ಟ್ರೀಯ ವಲಯದಲ್ಲಿ ತೀವ್ರ ನಿಗಾ ವಹಿಸಲಾಗಿದೆ.',
        business: 'ಹಣಕಾಸು ವಲಯ, ಗ್ರಾಹಕ ಮಾರುಕಟ್ಟೆ ಮತ್ತು ಹೂಡಿಕೆದಾರರ ಮೇಲೆ ಈ ನಿರ್ಧಾರ ನೇರ ಪ್ರಭಾವ ಬೀರಲಿದ್ದು, ಮಾರುಕಟ್ಟೆಯಲ್ಲಿ ಹೊಸ ಚಲನೆಗಳು ಗೋಚರಿಸುತ್ತಿವೆ.',
        sports: 'ಕ್ರೀಡಾ ಲೋಕದಲ್ಲಿ ಈ ವಿಷಯವು ಭಾರಿ ಕುತೂಹಲ ಮೂಡಿಸಿದ್ದು, ಕ್ರೀಡಾಭಿಮಾನಿಗಳು ಮತ್ತು ತಜ್ಞರು ಈ ಬಗ್ಗೆ ತಮ್ಮದೇ ಆದ ವಿಶ್ಲೇಷಣೆಗಳನ್ನು ನೀಡುತ್ತಿದ್ದಾರೆ.',
        cinema: 'ಮನರಂಜನಾ ಕ್ಷೇತ್ರ ಮತ್ತು ಚಿತ್ರರಂಗದಲ್ಲಿ ಈ ಅಪ್ಡೇಟ್ ಹೊಸ ಆಸಕ್ತಿ ಮೂಡಿಸಿದ್ದು, ಪ್ರೇಕ್ಷಕರ ವಲಯದಲ್ಲಿ ಬಿಸಿ ಬಿಸಿ ಚರ್ಚೆಗಳು ನಡೆಯುತ್ತಿವೆ.',
        technology: 'ತಾಂತ್ರಿಕ ಆವಿಷ್ಕಾರಗಳು ಮತ್ತು ಡಿಜಿಟಲ್ ಸೇವೆಗಳ ಬಳಕೆಯ ಮೇಲೆ ಈ ಬೆಳವಣಿಗೆ ಮಹತ್ವದ ಪ್ರಭಾವ ಬೀರಲಿದೆ ಎಂದು ನಿರೀಕ್ಷಿಸಲಾಗಿದೆ.',
        'fact-check': 'ಸಾಮಾಜಿಕ ಮಾಧ್ಯಮಗಳಲ್ಲಿ ಹರಡುತ್ತಿರುವ ಮಾಹಿತಿಗಳ ನೈಜತೆಯನ್ನು ಪರಿಶೀಲಿಸುವುದು ನಾಗರಿಕರ ಜವಾಬ್ದಾರಿಯಾಗಿದ್ದು, ಅಧಿಕೃತ ಮೂಲಗಳನ್ನೇ ನಂಬಬೇಕು.',
        politics: 'ರಾಜಕೀಯ ವಲಯದಲ್ಲಿ ಈ ವಿದ್ಯಮಾನವು ಹೊಸ ತಂತ್ರಗಾರಿಕೆಗಳಿಗೆ ಕಾರಣವಾಗಲಿದ್ದು, ಮುಂಬರುವ ದಿನಗಳಲ್ಲಿ ತೀವ್ರ ರಾಜಕೀಯ ಧ್ರುವೀಕರಣಕ್ಕೆ ಕಾರಣವಾಗಬಹುದು.',
        health: 'ಜನಸಾಮಾನ್ಯರ ಆರೋಗ್ಯ ಸುಧಾರಣೆ ಮತ್ತು ದಿನನಿತ್ಯದ ಸುರಕ್ಷತೆಗೆ ಸಂಬಂಧಿಸಿದ ಮಹತ್ವದ ಮಾರ್ಗಸೂಚಿಗಳು ಇದರಲ್ಲಿ ಅಡಕವಾಗಿವೆ.',
        education: 'ಶಿಕ್ಷಣ ಕ್ಷೇತ್ರ ಮತ್ತು ವಿದ್ಯಾರ್ಥಿಗಳ ಭವಿಷ್ಯದ ಹಿತದೃಷ್ಟಿಯಿಂದ ಈ ನಿಯಮಗಳು ಅತ್ಯಂತ ನಿರ್ಣಾಯಕ ಪಾತ್ರ ವಹಿಸಲಿವೆ.',
        crime: 'ಪೊಲೀಸ್ ಇಲಾಖೆಯು ಪ್ರಕರಣದ ತನಿಖೆಯನ್ನು ತೀವ್ರಗೊಳಿಸಿದ್ದು, ಮುಂಜಾಗ್ರತಾ ಕ್ರಮಗಳನ್ನು ಕೈಗೊಳ್ಳಲು ಸಾರ್ವಜನಿಕರಿಗೆ ಸೂಚಿಸಿದೆ.',
        agriculture: 'ರೈತ ಮಿತ್ರರ ಆರ್ಥಿಕ ಪ್ರಗತಿ ಮತ್ತು ಕೃಷಿ ವಲಯದ ಸುಧಾರಣೆಗಳಿಗೆ ಪೂರಕವಾದ ಮಹತ್ವದ ಮಾರ್ಗಸೂಚಿಗಳು ಇಲ್ಲಿ ಪ್ರಸ್ತಾಪವಾಗಿವೆ.',
        lifestyle: 'ಆರೋಗ್ಯಕರ ಮತ್ತು ಸುಲಭ ಜೀವನಶೈಲಿಯನ್ನು ಅಳವಡಿಸಿಕೊಳ್ಳಲು ಇಂತಹ ಪ್ರಾಯೋಗಿಕ ಮಾಹಿತಿಗಳು ಸಹಕಾರಿಯಾಗಲಿವೆ.',
        automobile: 'ಮಾರುಕಟ್ಟೆಗೆ ಹೊಸ ವಾಹನಗಳ ಪ್ರವೇಶ ಹಾಗೂ ಸುರಕ್ಷತಾ ನಿಯಮಗಳ ಜಾರಿಯು ಗ್ರಾಹಕರಿಗೆ ಹೊಸ ಅನುಭವ ನೀಡಲಿದೆ.',
        career: 'ಕರ್ನಾಟಕದಲ್ಲಿ ಸರ್ಕಾರಿ ಉದ್ಯೋಗಾವಕಾಶಗಳು ಮತ್ತು ಸ್ಪರ್ಧಾತ್ಮಕ ಪರೀಕ್ಷೆಗಳ ಸಿದ್ಧತೆಯ ವಿವರಗಳು ಇದರಲ್ಲಿವೆ.',
        astrology: 'ದಿನನಿತ್ಯದ ಗ್ರಹಗತಿಗಳು ಮತ್ತು ರಾಶಿಫಲದ ಅನ್ವಯ, ಜ್ಯೋತಿಷಿಗಳು ಈ ದಿನದ ಶುಭ ಫಲಗಳ ಬಗ್ಗೆ ವಿವರಣೆ ನೀಡಿದ್ದಾರೆ.'
    };

    const paragraphs = [];
    const shuffle = (arr) => arr.sort(() => Math.random() - 0.5);

    shuffle(intros);
    paragraphs.push(intros[0]);

    shuffle(connectors);
    paragraphs.push(connectors[0] + " " + summarySentences.join(" "));

    paragraphs.push(catContext[catSlug] || 'ಈ ವಿದ್ಯಮಾನಗಳು ಸಾರ್ವಜನಿಕ ವಲಯದಲ್ಲಿ ತೀವ್ರ ಆಸಕ್ತಿಯನ್ನು ಉಂಟುಮಾಡಿದ್ದು, ಮುಂದಿನ ಹಂತದ ಮಾಹಿತಿ ನಿರೀಕ್ಷಿಸಲಾಗುತ್ತಿದೆ.');

    paragraphs.push("<!-- AD_SLOT -->");

    shuffle(details);
    const detailCount = Math.floor(Math.random() * 2) + 2;
    for (let i = 0; i < detailCount; i++) {
        paragraphs.push(details[i]);
    }

    shuffle(outros);
    paragraphs.push(outros[0]);

    paragraphs.push(`ಮಾಹಿತಿ ಕೃಪೆ: ${source}`);

    return paragraphs.join('\n\n');
}

function autoWrite(article) {
    const title = cleanNewsTitle(article.title, article.source);
    const source = article.source || 'ಮೂಲ ವರದಿ';
    const category = article.category_label || categories[article.category] || 'ಸುದ್ದಿ';
    const catSlug = article.category || 'latest';
    let summary = stripHtml(article.summary || '');

    if (summary.length < 90 || summary === title) {
        summary = `${source} ಪ್ರಕಟಿಸಿದ ವರದಿ ಪ್ರಕಾರ, ${title} ವಿಷಯಕ್ಕೆ ಸಂಬಂಧಿಸಿದ ಪ್ರಮುಖ ಅಪ್ಡೇಟ್ ಬಂದಿದೆ. ${category} ವಿಭಾಗದ ಈ ಸುದ್ದಿಯಲ್ಲಿ ಮೂಲ ವರದಿಯ ಮುಖ್ಯಾಂಶ, ಸಮಯ ಮತ್ತು ಸಂಬಂಧಿತ ಹಿನ್ನೆಲೆಯನ್ನು ಓದುಗರಿಗೆ ವೇಗವಾಗಿ ತಲುಪಿಸಲಾಗುತ್ತಿದೆ.`;
    }

    const score = 42 + (Math.abs(hashCode(`${title}${source}`)) % 57);
    const titleWords = title.split(/\s+/).filter(w => w.length > 2).slice(0, 6);
    const tags = [...new Set([category, source, ...titleWords, 'Kannada News', 'MIYIZE', catSlug])].slice(0, 10);

    const pubLabel = formatDate(article.published_at);
    const highlightWord = titleWords[Math.floor(Math.random() * titleWords.length)] || category;

    let full_content = article.full_content || generateDynamicKannadaBody(title, summary, source, category, catSlug);
    if (highlightWord.length > 3) {
        const regex = new RegExp(`(${highlightWord})`, 'gi');
        full_content = full_content.replace(regex, '<span class="highlight">$1</span>');
    }

    return {
        ...article,
        title,
        summary,
        full_content,
        seo_title: excerpt(`${title} | ${siteName}`, 68),
        meta_description: excerpt(summary, 155),
        key_points: [
            `${category} ವಿಭಾಗದ ಪ್ರಮುಖ ಅಪ್ಡೇಟ್ — ${pubLabel} ಪ್ರಕಟಣೆ.`,
            `${source} ಪ್ರಕಟಿಸಿರುವ ವರದಿಯ ಪ್ರಮುಖ ಮುಖ್ಯಾಂಶಗಳು.`,
            `ವಿಷಯಕ್ಕೆ ಸಂಬಂಧಿಸಿದ ಮುಂದಿನ ವಿವರಗಳನ್ನು ನಿರಂತರವಾಗಿ ಪರಿಶೀಲಿಸಲಾಗುತ್ತಿದೆ.`,
            `ಲಭ್ಯವಾಗುವ ಹೊಸ ಮಾಹಿತಿಯೊಂದಿಗೆ ಈ ಪುಟವನ್ನು ನವೀಕರಿಸಲಾಗುತ್ತದೆ.`,
            `ಹೆಚ್ಚಿನ ವಿವರಗಳಿಗಾಗಿ ಕೆಳಗೆ ನೀಡಲಾದ ಮೂಲ ಲಿಂಕ್ ಅನ್ನು ಕ್ಲಿಕ್ ಮಾಡಿ.`,
        ],
        quick_facts: [
            { label: 'ವಿಭಾಗ', value: category },
            { label: 'ಮೂಲ', value: source },
            { label: 'ಪ್ರಕಟಣೆ', value: pubLabel },
            { label: 'ಸ್ಥಿತಿ', value: 'ಪ್ರಕಟಿತ' },
            { label: 'ಟ್ರೆಂಡ್ ಸ್ಕೋರ್', value: `${score}/100` },
            { label: 'ಓದುವ ಸಮಯ', value: `${Math.max(1, Math.ceil(full_content.length / 650))} ನಿಮಿಷ` },
        ],
        tags,
        trend_score: score,
        reading_minutes: Math.max(1, Math.ceil(full_content.length / 650)),
        auto_written: true,
        published: article.published !== false,
        ai_generated: Boolean(article.ai_generated),
    };
}

function hashCode(value) {
    let hash = 0;
    for (let index = 0; index < value.length; index += 1) {
        hash = ((hash << 5) - hash) + value.charCodeAt(index);
        hash |= 0;
    }
    return hash;
}

async function parseFeed(category, query) {
    const xml = await fetchText(rssUrl(query), 12000);
    if (!xml) return [];

    const items = [...xml.matchAll(/<item>([\s\S]*?)<\/item>/gi)].slice(0, 8);
    const parsed = [];
    for (const [, item] of items) {
        const link = stripHtml(tag(item, 'link'));
        const source = stripHtml(tag(item, 'source')) || 'Google News';
        let title = cleanNewsTitle(tag(item, 'title'), source);
        if (!title || !link) continue;

        const rawDescription = tag(item, 'description');
        let image = feedImageFromItem(item);
        if (!image) {
            image = await fetchMetaImage(link);
        }

        const publishedAt = new Date(stripHtml(tag(item, 'pubDate')) || Date.now());
        const itemObj = {
            id: hashCode(link).toString(16),
            slug: slugFor(title, link),
            title,
            summary: excerpt(stripHtml(rawDescription), 380),
            source,
            source_url: link,
            category,
            category_label: categories[category] || category,
            image,
            published_at: Number.isNaN(publishedAt.getTime()) ? new Date().toISOString() : publishedAt.toISOString(),
            updated_at: new Date().toISOString(),
            ai_generated: false,
            full_content: ''
        };

        const aiContent = await geminiWriteArticle(itemObj);
        if (aiContent) {
            itemObj.full_content = aiContent;
            itemObj.ai_generated = true;
        }

        parsed.push(autoWrite(itemObj));
    }

    return parsed;
}

function guessCategoryFromText(title, link, description = '') {
    const haystack = `${title} ${link} ${description}`.toLowerCase();
    const rules = [
        ['fact-check', ['ಫ್ಯಾಕ್ಟ್', 'fact', 'ವೈರಲ್', 'ಸುಳ್ಳು', 'ನಿಜವಾ', 'fake']],
        ['technology', ['ತಂತ್ರಜ್ಞಾನ', 'ಮೊಬೈಲ್', 'ai', 'ಕೃತಕ ಬುದ್ಧಿಮತ್ತೆ', 'whatsapp', 'smartphone', 'tech']],
        ['cinema', ['ಸಿನಿಮಾ', 'ನಟ', 'ನಟಿ', 'ಚಿತ್ರ', 'ott', 'movie', 'film', 'serial', 'celebrity']],
        ['sports', ['ಕ್ರೀಡೆ', 'ಕ್ರಿಕೆಟ್', 'ipl', 't20', 'football', 'match', 'score']],
        ['business', ['ವ್ಯಾಪಾರ', 'ಷೇರು', 'ಮಾರುಕಟ್ಟೆ', 'ಚಿನ್ನ', 'ಬೆಲೆ', 'bank', 'tax', 'business', 'market']],
        ['world', ['ವಿಶ್ವ', 'ಅಮೆರಿಕ', 'ಚೀನಾ', 'ರಷ್ಯಾ', 'trump', 'world', 'global']],
        ['india', ['ಭಾರತ', 'ದೆಹಲಿ', 'ಸರ್ಕಾರ', 'ಲೋಕಸಭೆ', 'ಮೋದಿ', 'india', 'national']],
        ['karnataka', ['ಕರ್ನಾಟಕ', 'ಬೆಂಗಳೂರು', 'ಮೈಸೂರು', 'ಮಂಗಳೂರು', 'ಹುಬ್ಬಳ್ಳಿ', 'ಬೆಳಗಾವಿ', 'bengaluru', 'karnataka']],
    ];

    for (const [category, keywords] of rules) {
        if (keywords.some((keyword) => haystack.includes(keyword))) return category;
    }
    return 'latest';
}

async function parseDirectFeed(feedUrl) {
    const xml = await fetchText(feedUrl, 12000);
    if (!xml) return [];

    const items = [...xml.matchAll(/<item>([\s\S]*?)<\/item>/gi)].slice(0, 60);
    const parsed = [];
    for (const [, item] of items) {
        const link = stripHtml(tag(item, 'link'));
        const source = stripHtml(tag(item, 'source')) || new URL(feedUrl).hostname.replace(/^www\./, '');
        const rawDescription = tag(item, 'description') || tag(item, 'content:encoded');
        let title = cleanNewsTitle(tag(item, 'title'), source);
        if (!title || !link) continue;

        const category = guessCategoryFromText(title, link, rawDescription);
        let image = feedImageFromItem(item);
        if (!image) image = await fetchMetaImage(link);

        const publishedAt = new Date(stripHtml(tag(item, 'pubDate')) || Date.now());
        const itemObj = {
            id: hashCode(link).toString(16),
            slug: slugFor(title, link),
            title,
            summary: excerpt(stripHtml(rawDescription), 520),
            source,
            source_url: link,
            category,
            category_label: categories[category] || categories.latest,
            image,
            published_at: Number.isNaN(publishedAt.getTime()) ? new Date().toISOString() : publishedAt.toISOString(),
            updated_at: new Date().toISOString(),
            ai_generated: false,
            full_content: ''
        };

        const aiContent = await geminiWriteArticle(itemObj);
        if (aiContent) {
            itemObj.full_content = aiContent;
            itemObj.ai_generated = true;
        }

        parsed.push(autoWrite(itemObj));
    }

    return parsed;
}

async function refreshNews({ force = false } = {}) {
    if (refreshInFlight) return refreshInFlight;

    const state = readJson(statePath, {});
    const last = state.last_refresh_at ? Date.parse(state.last_refresh_at) : 0;
    if (!force && last && Date.now() - last < autoRefreshMs) {
        return state;
    }

    refreshInFlight = (async () => {
        const existing = articles();
        const bySource = new Map(existing.map((article) => [mergeKey(article), article]));
        let added = 0;

        const sourceGroups = [];
        for (const feedUrl of directFeeds) {
            sourceGroups.push(await parseDirectFeed(feedUrl));
        }
        for (const [category, query] of Object.entries(feedQueries)) {
            sourceGroups.push(await parseFeed(category, query));
        }

        for (const fetched of sourceGroups) {
            for (const item of fetched) {
                const key = mergeKey(item);
                const previous = bySource.get(key);
                if (!previous) added += 1;
                bySource.set(key, {
                    ...item,
                    summary: previous?.summary && previous.summary.length > item.summary.length ? previous.summary : item.summary,
                    image: item.image || previous?.image || '',
                    ai_generated: previous?.ai_generated || item.ai_generated,
                });
            }
        }

        const sevenDaysAgo = Date.now() - (7 * 24 * 60 * 60 * 1000);
        const merged = [...bySource.values()]
            .map((article) => autoWrite(article))
            .filter((article) => !article.published_at || new Date(article.published_at).getTime() > sevenDaysAgo)
            .sort((a, b) => String(b.published_at || '').localeCompare(String(a.published_at || '')))
            .slice(0, 300);

        writeJson(articlesPath, merged);
        const nextState = {
            last_refresh_at: new Date().toISOString(),
            article_count: merged.length,
            new_articles: added,
            image_count: merged.filter((article) => article.image).length,
            auto_writing: true,
            auto_images: true,
        };
        writeJson(statePath, nextState);
        return nextState;
    })().finally(() => {
        refreshInFlight = null;
    });

    return refreshInFlight;
}

function shell(title, description, active, body, article = null, canonicalPath = '/') {
    const pageTitle = title === siteName ? title : `${title} | ${siteName}`;
    const image = article ? articleImage(article) : fallbackImage;
    const imageAbsolute = image.startsWith('http') ? image : absoluteUrl(image);
    const canonical = absoluteUrl(canonicalPath);
    const activeFeed = categories[active] ? rssFeedUrl(active) : rssFeedUrl('latest');
    const activeApi = categories[active] ? apiCategoryUrl(active) : apiCategoryUrl('latest');
    const keywords = article && Array.isArray(article.tags) && article.tags.length
        ? article.tags.map(String).join(', ')
        : 'Kannada News, Karnataka News, Breaking News Kannada, MIYIZE Kannada News, Live News';
    const websiteSchema = JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'WebSite',
        name: siteName,
        url: siteUrl,
        potentialAction: {
            '@type': 'SearchAction',
            target: `${siteUrl}/search.php?q={search_term_string}`,
            'query-input': 'required name=search_term_string',
        },
    });
    const orgSchema = JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'NewsMediaOrganization',
        name: siteName,
        url: siteUrl,
        logo: `${siteUrl}/assets/images/newsroom-fallback.png`,
    });
    const breadcrumbSchema = canonicalPath !== '/' ? JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'BreadcrumbList',
        itemListElement: [
            { '@type': 'ListItem', position: 1, name: 'Home', item: `${siteUrl}/` },
            { '@type': 'ListItem', position: 2, name: String(title), item: canonical },
        ],
    }) : '';

    return `<!doctype html>
<html lang="kn-IN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>${esc(pageTitle)}</title>
    <meta name="description" content="${esc(description)}">
    <meta name="keywords" content="${esc(keywords)}">
    <link rel="canonical" href="${esc(canonical)}">
    <meta name="robots" content="index,follow,max-image-preview:large">
    <meta property="og:site_name" content="${esc(siteName)}">
    <meta property="og:type" content="${article ? 'article' : 'website'}">
    <meta property="og:title" content="${esc(pageTitle)}">
    <meta property="og:description" content="${esc(description)}">
    <meta property="og:url" content="${esc(canonical)}">
    <meta property="og:image" content="${esc(imageAbsolute)}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="${esc(pageTitle)}">
    <meta name="twitter:description" content="${esc(description)}">
    <meta name="twitter:image" content="${esc(imageAbsolute)}">
    <script type="application/ld+json">${websiteSchema}</script>
    <script type="application/ld+json">${orgSchema}</script>
    ${breadcrumbSchema ? `<script type="application/ld+json">${breadcrumbSchema}</script>` : ''}
    <link rel="alternate" type="application/rss+xml" title="${esc(siteName)} RSS" href="/feed.xml">
    <link rel="alternate" type="application/rss+xml" title="${esc(pageTitle)} RSS" href="${esc(activeFeed)}">
    <link rel="alternate" type="application/json" title="${esc(pageTitle)} API" href="${esc(activeApi)}">
    <link rel="alternate" type="application/json" title="${esc(siteName)} Categories API" href="/api/categories.php">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="/assets/css/styles.css">
    <style>
      .hero-slider{position:relative;height:100%;overflow:hidden}
      .slider-dots{position:absolute;bottom:20px;left:50%;transform:translateX(-50%);display:flex;gap:8px;z-index:10}
      .slider-dot{width:10px;height:10px;border-radius:50%;background:rgba(255,255,255,0.4);border:none;cursor:pointer;transition:all 0.3s}
      .slider-dot.active{background:#fff;width:24px;border-radius:5px}
    </style>
    ${process.env.MIYIZE_GOOGLE_ANALYTICS_ID ? `
    <!-- Google Analytics -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=${esc(process.env.MIYIZE_GOOGLE_ANALYTICS_ID)}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', '${esc(process.env.MIYIZE_GOOGLE_ANALYTICS_ID)}');
    </script>
    ` : ''}
</head>
<body>
<a class="skip-link" href="#main">ಮುಖ್ಯ ವಿಷಯಕ್ಕೆ ಹೋಗಿ</a>
    ${header(active)}
    <main id="main">${body}</main>
    ${footer()}
    <script src="/assets/js/app.js" defer></script>
</body>
</html>`;
}

function header(active = '') {
    const state = readJson(statePath, {});
    const dayName = new Date().toLocaleDateString('kn-IN', { weekday: 'long' });
    const dateStr = new Date().toLocaleDateString('kn-IN', { day: 'numeric', month: 'long', year: 'numeric' });
    const nav = Object.entries(categories).map(([slug, label]) => {
        const href = slug === 'latest' ? '/' : categoryUrl(slug);
        const isActive = active === slug ? ' is-active' : '';
        return `<a class="${isActive.trim()}" href="${href}">${esc(label)}</a>`;
    }).join('');
    const homeActive = active === 'latest' ? ' is-active' : '';
    return `<header class="site-header">
<div class="topbar"><div class="container topbar__inner">
  <span class="topbar__date">${esc(dayName)}, ${esc(dateStr)}</span>
  <span class="topbar__weather"><svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M12 2a5 5 0 1 1 0 10A5 5 0 0 1 12 2zm0 2a3 3 0 1 0 0 6 3 3 0 0 0 0-6zm9 10h-1a1 1 0 0 0 0 2h1a1 1 0 0 0 0-2zM3 14H2a1 1 0 0 0 0 2h1a1 1 0 0 0 0-2zm8-8V5a1 1 0 0 0-2 0v1a1 1 0 0 0 2 0zm0 12v-1a1 1 0 0 0-2 0v1a1 1 0 0 0 2 0z"/></svg><span>ಬೆಂಗಳೂರು 27°C</span></span>
  <span class="topbar__spacer"></span>
  <a class="topbar__link" href="/contact.php"><svg viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.123.554 4.112 1.522 5.838L0 24l6.336-1.461A11.945 11.945 0 0 0 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 22a9.954 9.954 0 0 1-5.193-1.454l-.371-.221-3.762.987.986-3.668-.242-.389A9.953 9.953 0 0 1 2 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10z"/></svg>WhatsApp ನಲ್ಲಿ ಅನುಸರಿಸಿ</a>
  <a class="topbar__link" href="/contact.php"><svg viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>ನ್ಯೂಸ್ ಲೆಟರ್</a>
  <span class="live-badge"><span class="dot"></span>LIVE</span>
</div></div>
<div class="container masthead">
  <button class="hamburger" aria-label="Menu"><span></span><span></span><span></span></button>
  <a class="brand" href="/"><span class="brand__logo">MIYIZE</span><span class="brand__sub">Kannada News</span></a>
  <div class="masthead-search">
    <form class="search-form" method="get" action="/search.php" role="search">
      <label class="sr-only" for="q">ಸುದ್ದಿ ಹುಡುಕಿ</label>
      <input id="q" name="q" type="search" placeholder="ಸುದ್ದಿ ಹುಡುಕಿ..." autocomplete="off">
      <button type="submit" aria-label="ಹುಡುಕಿ"><svg viewBox="0 0 24 24"><path d="m21 21-4.35-4.35M17 11a6 6 0 1 1-12 0 6 6 0 0 1 12 0Z"/></svg></button>
    </form>
  </div>
  <aside class="masthead-ad"><small>SPONSOR</small><strong>ವಿಜ್ಞಾಪನೆ ಜಾಗ</strong><em>728 x 90</em></aside>
</div>
<nav class="cat-nav" aria-label="ಮುಖ್ಯ ವಿಭಾಗಗಳು"><div class="container cat-nav__scroll">
  <a class="home-icon${homeActive}" href="/" aria-label="Home"><svg viewBox="0 0 24 24" width="16" height="16"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg></a>
  ${nav}
</div></nav>
</header>`;
}

function footer() {
    const links = Object.entries(categories).map(([slug, label]) => {
        const href = slug === 'latest' ? '/' : categoryUrl(slug);
        return `<a href="${href}">${esc(label)}</a>`;
    }).join('');
    return `<footer class="site-footer"><div class="container">
  <div class="footer-grid">
    <div class="footer-brand"><a href="/" class="brand"><span class="brand__logo">MIYIZE</span><span class="brand__sub">Kannada News</span></a><p>${esc(siteTagline)}</p></div>
    <div class="footer-col"><h3>ವಿಭಾಗಗಳು</h3><div class="footer-links">${links}</div></div>
    <div class="footer-col"><h3>ಸೈಟ್</h3><div class="footer-links"><a href="/about.php">About</a><a href="/contact.php">Contact</a><a href="/privacy.php">Privacy</a><a href="/feed.xml">RSS Feed</a><a href="/sitemap.xml">Sitemap</a></div></div>
  </div>
  <div class="footer-bottom"><span>&copy; ${new Date().getFullYear()} MIYIZE Kannada News. All rights reserved.</span><span>Made for Kannada readers</span></div>
</div></footer>`;
}

function adSlot(variant = 'wide') {
    return `<div class="ad-wide" aria-label="Advertisement"><small>Advertisement</small><strong>AdSense slot ready</strong></div>`;
}

function thumbCard(article) {
    return `<a class="bento-card" style="flex-direction:row;gap:14px;padding:12px;min-height:100px;" href="${articleUrl(article)}">
  <div class="bento-img-wrap" style="width:100px;height:80px;border-radius:12px;flex-shrink:0;">
    <img src="${esc(articleImage(article))}" alt="" loading="lazy" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB2aWV3Qm94PSIwIDAgMSAxIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPjxyZWN0IHdpZHRoPSIxMDAlIiBoZWlnaHQ9IjEwMCUiIGZpbGw9IiNlOGU4ZTgiLz48L3N2Zz4='">
  </div>
  <div style="flex:1;display:flex;flex-direction:column;justify-content:center;min-width:0;">
    <div class="bento-title-small" style="margin-bottom:6px;">${esc(excerpt(article.title, 75))}</div>
    <div class="bento-meta" style="margin-top:0;">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" style="opacity:0.6"><path d="M12 2a10 10 0 1 1 0 20A10 10 0 0 1 12 2zm1 5h-2v6l5.25 3.15.75-1.23-4-2.42V7z"/></svg>
      ${esc(formatDate(article.published_at))}
    </div>
  </div>
</a>`;
}

function card(article, variant = 'standard') {
    const isLead = variant === 'lead';
    return `<div class="bento-card">
  <a class="bento-img-wrap" href="${articleUrl(article)}" style="${isLead ? 'height:240px;' : 'height:180px;'}">
    <img src="${esc(articleImage(article))}" alt="" loading="${isLead ? 'eager' : 'lazy'}" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB2aWV3Qm94PSIwIDAgMTYgOSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZThlOGU4Ii8+PC9zdmc+'">
  </a>
  <div class="bento-body">
    <a class="bento-cat" href="${articleUrl(article)}">${esc(article.category_label || 'ಸುದ್ದಿ')}</a>
    <a class="bento-title" href="${articleUrl(article)}" style="${isLead ? 'font-size:20px;' : ''}">${esc(excerpt(article.title, 80))}</a>
    <div class="bento-meta">
      <span>${esc(article.source || 'MIYIZE')}</span>
      <span style="width:4px;height:4px;border-radius:50%;background:#cbd5e1;"></span>
      <span>${esc(formatDate(article.published_at))}</span>
    </div>
  </div>
</div>`;
}

function ticker(items) {
    return '';
}

function homePage() {
    refreshNews().catch(() => {}); // Auto-refresh in background if 5m elapsed
    const items = articles(48);
    const state = readJson(statePath, {});
    const sliderItems = items.slice(0, 5);
    const liveItems = items.slice(0, 6);
    const tazaItems = items.slice(0, 8);
    const karnatakaItems = categoryArticles('karnataka', 4);
    const trendingItems = items.slice(0, 5);
    const featureItems = items.slice(8, 14);
    const mediaItems = items.slice(14, 20);
    const catSlugs = ['india','world','business','sports','cinema','technology','fact-check'];

    const slides = sliderItems.map((a, i) => `<a class="hero-tile${i === 0 ? ' active' : ''}" href="${articleUrl(a)}">
  <img src="${esc(articleImage(a))}" alt="" loading="${i === 0 ? 'eager' : 'lazy'}" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB2aWV3Qm94PSIwIDAgMTYgOSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZThlOGU4Ii8+PC9zdmc+'">
  <div class="hero-tile__overlay"></div>
  <div class="hero-tile__content">
    <span class="hero-tile__cat">${esc(a.category_label || 'ತಾಜಾ ಸುದ್ದಿ')}</span>
    <span class="hero-tile__title">${esc(excerpt(a.title, 100))}</span>
    <div class="hero-tile__meta"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 1 1 0 20A10 10 0 0 1 12 2zm0 2a8 8 0 1 0 0 16A8 8 0 0 0 12 4zm0 3v5l3 3-1 1-3.5-3.5V7h1.5z"/></svg>${esc(formatDate(a.published_at))}<span>${esc(a.source || '')}</span></div>
  </div>
</a>`).join('');
    const dots = sliderItems.map((_, i) => `<button class="slider-dot${i === 0 ? ' active' : ''}" aria-label="Slide ${i+1}"></button>`).join('');

    const liveHtml = liveItems.map(a => `<a class="live-list-item" href="${articleUrl(a)}">
  <span class="live-time">${esc(formatDate(a.published_at).replace(' ಹಿಂದೆ','').replace('ಈಗಷ್ಟೆ','ಈಗ'))}</span>
  <span class="live-text">${esc(excerpt(a.title, 80))}</span>
</a>`).join('');

    const kFirst = karnatakaItems[0];
    const tazaAll = tazaItems.length ? tazaItems.slice(0,4).map(a => thumbCard(a)).join('') : '';

    const trendHtml = trendingItems.map((a, i) => `<a class="compact-item" href="${articleUrl(a)}">
  <span class="trend-num">${i+1}</span>
  <span class="compact-text">${esc(excerpt(a.title, 65))}</span>
</a>`).join('');

    const featureGridHtml = featureItems.map((a) => `<a class="feature-mini" href="${articleUrl(a)}">
  <img src="${esc(articleImage(a))}" alt="" loading="lazy" onerror="this.src='${fallbackImage}'">
  <span>${esc(excerpt(a.title, 64))}</span>
</a>`).join('');

    const mediaGridHtml = mediaItems.slice(0, 4).map((a) => {
        const query = encodeURIComponent(`${excerpt(a.title, 80)} Kannada News`);
        return `<a class="media-tile" href="https://www.youtube.com/results?search_query=${query}" target="_blank" rel="noopener noreferrer">
  <img src="${esc(articleImage(a))}" alt="" loading="lazy" onerror="this.src='${fallbackImage}'">
  <span>${esc(excerpt(a.title, 56))}</span>
</a>`;
    }).join('');

    const pulseRows = Object.entries(categories)
        .filter(([slug]) => slug !== 'latest')
        .map(([slug, label]) => ({
            slug,
            label,
            count: categoryArticles(slug, 0).length,
        }))
        .sort((a, b) => b.count - a.count)
        .slice(0, 5)
        .map((row) => `<tr><td><a href="${categoryUrl(row.slug)}">${esc(row.label)}</a></td><td>${row.count}</td></tr>`)
        .join('');

    const keywordCloud = [...new Set(items.flatMap((a) => Array.isArray(a.tags) ? a.tags : []))]
        .filter((v) => typeof v === 'string' && v.trim())
        .slice(0, 16)
        .map((tag) => `<a href="/search.php?q=${encodeURIComponent(tag)}">${esc(tag)}</a>`)
        .join('');

    const faqJson = JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'FAQPage',
        mainEntity: [
            {
                '@type': 'Question',
                name: 'MIYIZE Kannada News refresh interval ಯಾವಷ್ಟು?',
                acceptedAnswer: { '@type': 'Answer', text: 'ಸಿಸ್ಟಮ್ ಪ್ರತಿ 5 ನಿಮಿಷಕ್ಕೆ ಸ್ವಯಂ ಫೆಚ್ ಓಡಿಸಿ ಲೈವ್ ಫೀಡ್ ನವೀಕರಿಸುತ್ತದೆ.' },
            },
            {
                '@type': 'Question',
                name: 'Auto summary ಮತ್ತು image ಹೇಗೆ ಸೇರಿಸಲಾಗುತ್ತದೆ?',
                acceptedAnswer: { '@type': 'Answer', text: 'ವಿಶ್ವಾಸಾರ್ಹ ಮೂಲಗಳಿಂದ ಬಂದ ಸುದ್ದಿಗೆ ಮುಖ್ಯಾಂಶ, ಸಾರಾಂಶ ಮತ್ತು ಸಂಬಂಧಿತ ಚಿತ್ರಗಳನ್ನು ಸ್ವಯಂ ಸಂಯೋಜಿಸಲಾಗುತ್ತದೆ.' },
            },
        ],
    });

    const catBottomHtml = catSlugs.map(slug => {
        const label = categories[slug] || slug;
        const list = categoryArticles(slug, 4);
        if (!list.length) return '';
        const first = list[0];
        const rest = list.slice(1);
        return `<div class="bento-card bento-span-4 cat-ribbon">
  <div class="cat-ribbon__head"><span class="cat-ribbon__title">${esc(label)}</span><a class="cat-ribbon__more" href="${categoryUrl(slug)}">ಎಲ್ಲಾ ಸುದ್ದಿ ›</a></div>
  <div class="cat-ribbon__grid">
    <a class="bento-img-wrap" style="border-radius:12px;display:block;" href="${articleUrl(first)}"><img src="${esc(articleImage(first))}" alt="" loading="lazy"></a>
    <div style="grid-column:span 3;display:grid;grid-template-columns:repeat(3,1fr);gap:16px;">
       ${list.slice(0,3).map(a => `<a style="display:flex;flex-direction:column;" href="${articleUrl(a)}"><span class="bento-title-small" style="margin-bottom:8px;">${esc(excerpt(a.title, 70))}</span><span class="bento-meta">${esc(formatDate(a.published_at))}</span></a>`).join('')}
    </div>
  </div>
</div>`;
    }).join('');

    const emptySlider = `<div class="empty-state"><h1>ಸುದ್ದಿ ಫೀಡ್ ಸಿದ್ಧ</h1><p>RSS refresh ನಂತರ ಸುದ್ದಿ ಕಾಣಿಸುತ್ತದೆ.</p></div>`;

    const body = `<div class="container">
  <div class="bento">
    <!-- Row 1 & 2 Left: Slider -->
    <div class="bento-card bento-span-2x2">
      <div class="hero-slider" id="heroSlider">${slides || emptySlider}<div class="slider-dots">${dots}</div></div>
    </div>
    
    <!-- Row 1 Right: Live -->
    <div class="bento-card bento-span-1x2">
      <div class="live-tile__head"><span class="live-tile__title">ಲೈವ್ ಅಪ್ಡೇಟ್ಸ್</span><span class="live-badge-glass"><span class="dot"></span>LIVE</span></div>
      <div class="live-list">${liveHtml}</div>
      <a class="live-updates__more" href="/feed.xml" style="margin-top:auto;padding:16px;text-align:center;font-weight:700;color:#d81f2a;">ಇನ್ನಷ್ಟು ಸುದ್ದಿ →</a>
    </div>

    <!-- Row 1 Top Right: Trending -->
    <div class="bento-card bento-span-1x1">
       <div class="list-tile__head">🔥 ಟ್ರೆಂಡಿಂಗ್</div>
       <div class="live-list">${trendHtml}</div>
    </div>

    <!-- Row 2 Top Right: Newsletter -->
    <div class="bento-card nl-tile bento-span-1x1">
      <div class="bento-body">
        <h3>ಸಬ್ಸ್‌ಕ್ರೈಬ್</h3>
        <p>ದಿನದ ಮುಖ್ಯ ಸುದ್ದಿಗಳನ್ನು ಪಡೆಯಿರಿ.</p>
        <a class="nl-btn" href="/contact.php">Join Now</a>
      </div>
    </div>

    <!-- Row 3: Taza & Karnataka -->
    <div class="bento-card bento-span-2x1">
       <div class="list-tile__head" style="display:flex;justify-content:space-between;"><span>ತಾಜಾ ಸುದ್ದಿ</span><a href="/" style="font-size:12px;color:#d81f2a;">ಎಲ್ಲಾ ›</a></div>
       <div class="bento-body" style="padding:16px;"><div class="thumb-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">${tazaAll}</div></div>
    </div>

    <div class="bento-card bento-span-2x1">
       <div class="list-tile__head" style="display:flex;justify-content:space-between;"><span>ಕರ್ನಾಟಕ</span><a href="${categoryUrl('karnataka')}" style="font-size:12px;color:#d81f2a;">ಎಲ್ಲಾ ›</a></div>
       <div class="bento-body" style="flex-direction:row;gap:16px;padding:16px;">
         ${kFirst ? `<a class="bento-img-wrap" style="width:160px;height:120px;border-radius:12px;flex-shrink:0;" href="${articleUrl(kFirst)}"><img src="${esc(articleImage(kFirst))}" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB2aWV3Qm94PSIwIDAgMSAxIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPjxyZWN0IHdpZHRoPSIxMDAlIiBoZWlnaHQ9IjEwMCUiIGZpbGw9IiNlOGU4ZTgiLz48L3N2Zz4='"></a>
                     <div style="flex:1;display:flex;flex-direction:column;min-width:0;"><a href="${articleUrl(kFirst)}" class="bento-title-small">${esc(excerpt(kFirst.title, 80))}</a><div class="bento-meta" style="margin:10px 0;">${esc(formatDate(kFirst.published_at))}</div>
                     ${karnatakaItems.slice(1,3).map(a=>`<a href="${articleUrl(a)}" style="display:block;font-size:12px;font-weight:700;color:#eaf2ff;padding:6px 0;border-top:1px solid rgba(255,255,255,0.18);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${esc(excerpt(a.title,65))}</a>`).join('')}</div>` : ''}
       </div>
    </div>

    <div class="bento-card bento-span-2x1 feature-grid-card">
      <div class="list-tile__head"><span>Ultra Grid: Top Stories</span></div>
      <div class="feature-grid">${featureGridHtml}</div>
    </div>

    <div class="bento-card bento-span-1x1 pulse-card">
      <div class="list-tile__head"><span>Live Pulse</span></div>
      <div class="pulse-metrics">
        <div><strong>${items.length}</strong><span>Stories</span></div>
        <div><strong>${items.filter((a) => !!a.image).length}</strong><span>Images</span></div>
        <div><strong>${state.last_refresh_at ? esc(formatDate(state.last_refresh_at)) : 'now'}</strong><span>Updated</span></div>
      </div>
      <table class="pulse-table"><tbody>${pulseRows}</tbody></table>
    </div>

    <div class="bento-card bento-span-1x1 keyword-card">
      <div class="list-tile__head"><span>SEO Keyword Cloud</span></div>
      <div class="keyword-cloud">${keywordCloud || '<a href="/search.php?q=Kannada+News">Kannada News</a>'}</div>
    </div>

    <div class="bento-card bento-span-2x1 media-wall-card">
      <div class="list-tile__head"><span>Video & Social Wall</span></div>
      <div class="media-wall">${mediaGridHtml}</div>
    </div>

    ${catBottomHtml}

    <div class="bento-card bento-span-4 faq-card">
      <script type="application/ld+json">${faqJson}</script>
      <div class="list-tile__head"><span>Live System FAQ</span></div>
      <div class="faq-grid">
        <article><h3>Auto Fetch Every 5 Minutes</h3><p>System refreshes stories automatically and keeps the homepage constantly updated.</p></article>
        <article><h3>Auto Summary and Image Enrichment</h3><p>Stories include auto summary, quick facts, and related image handling for richer reading.</p></article>
        <article><h3>Retention and Fast Indexing Structure</h3><p>Older stories are pruned to keep pages light, crawlable, and SEO-friendly.</p></article>
      </div>
    </div>

    <div class="bento-span-4">${adSlot()}</div>
  </div>
</div>
<script>
  (function(){
    const slider = document.getElementById('heroSlider');
    if(!slider) return;
    const slides = slider.querySelectorAll('.hero-tile');
    const dots = slider.querySelectorAll('.slider-dot');
    let current = 0;
    function show(index) {
      slides.forEach((s, i) => s.classList.toggle('active', i === index));
      dots.forEach((d, i) => d.classList.toggle('active', i === index));
    }
    function next() { current = (current + 1) % slides.length; show(current); }
    let timer = setInterval(next, 5000);
    dots.forEach((d, i) => d.addEventListener('click', () => { 
      clearInterval(timer);
      current = i; 
      show(current); 
      timer = setInterval(next, 5000);
    }));
  })();
</script>`;
    return shell(siteName, siteTagline, 'latest', body, null, '/');
}

function homePageV2() {
    refreshNews().catch(() => {});
    const items = articles(96);
    const pickItems = (start, count) => {
        if (!items.length) return [];
        return Array.from({ length: count }, (_, index) => items[(start + index) % items.length]).filter(Boolean);
    };
    const lead = items[0] || null;
    const sliderItems = pickItems(0, 5);
    const liveItems = pickItems(0, 10);
    const trendingItems = pickItems(0, 12);
    const tazaItems = pickItems(1, 12);
    const moreGridItems = pickItems(12, 48);
    const denseGridItems = pickItems(24, 54);
    const karnatakaBaseItems = categoryArticles('karnataka', 16);
    const karnatakaItems = (karnatakaBaseItems.length >= 10 ? karnatakaBaseItems : [...karnatakaBaseItems, ...pickItems(4, 16)]).slice(0, 16);
    const sectionSlugs = ['india', 'world', 'business', 'sports', 'cinema', 'technology', 'fact-check'];

    const slides = sliderItems.map((article, index) => `<a class="hero-tile${index === 0 ? ' active' : ''}" href="${articleUrl(article)}">
  <img src="${esc(articleImage(article))}" alt="" loading="${index === 0 ? 'eager' : 'lazy'}" onerror="this.src='${fallbackImage}'">
  <div class="hero-tile__overlay"></div>
  <div class="hero-tile__content">
    <span class="hero-tile__cat">${esc(article.category_label || 'Latest')}</span>
    <span class="hero-tile__title">${esc(excerpt(article.title, 110))}</span>
    <div class="hero-tile__meta"><span>${esc(formatDate(article.published_at))}</span><span>${esc(article.source || 'MIYIZE')}</span></div>
  </div>
</a>`).join('');
    const dots = sliderItems.map((_, index) => `<button class="slider-dot${index === 0 ? ' active' : ''}" aria-label="Slide ${index + 1}"></button>`).join('');

    const liveHtml = liveItems.map((article) => `<a class="live-item" href="${articleUrl(article)}">
  <time>${esc(formatDate(article.published_at))}</time>
  <span>${esc(excerpt(article.title, 88))}</span>
</a>`).join('');

    const trendHtml = trendingItems.map((article, index) => `<a class="compact-item" href="${articleUrl(article)}">
  <span class="trend-num">${index + 1}</span>
  <span class="compact-text">${esc(excerpt(article.title, 72))}</span>
</a>`).join('');

    const tazaHtml = tazaItems.map((article) => `<a class="ref-mini-card" href="${articleUrl(article)}">
  <img src="${esc(articleImage(article))}" alt="" loading="lazy" onerror="this.src='${fallbackImage}'">
  <h3>${esc(excerpt(article.title, 74))}</h3>
  <span>${esc(formatDate(article.published_at))}</span>
</a>`).join('');

    const kLead = karnatakaItems[0];
    const kList = karnatakaItems.slice(1, 13).map((article) => `<a class="ref-k-mini" href="${articleUrl(article)}">
  <img src="${esc(articleImage(article))}" alt="" loading="lazy" onerror="this.src='${fallbackImage}'">
  <span>${esc(excerpt(article.title, 66))}</span>
</a>`).join('');
    const kPanel = kLead
        ? `<a class="ref-karnataka-image" href="${articleUrl(kLead)}"><img src="${esc(articleImage(kLead))}" alt="" loading="lazy" onerror="this.src='${fallbackImage}'"></a>
           <h3><a href="${articleUrl(kLead)}">${esc(excerpt(kLead.title, 90))}</a></h3>
           <span>${esc(formatDate(kLead.published_at))}</span>
           <div class="ref-k-list">${kList}</div>`
        : '<p>No Karnataka stories yet.</p>';

    const categoryColumns = sectionSlugs.map((slug) => {
        const label = categories[slug] || slug;
        const list = categoryArticles(slug, 4);
        if (!list.length) return '';
        const first = list[0];
        const rest = list.slice(1).map((article) => `<li><a href="${articleUrl(article)}">${esc(excerpt(article.title, 66))}</a></li>`).join('');
        return `<section class="ref-category-col">
  <div class="ref-col-head"><h2>${esc(label)}</h2><a href="${categoryUrl(slug)}">More</a></div>
  <a class="ref-col-image" href="${articleUrl(first)}"><img src="${esc(articleImage(first))}" alt="" loading="lazy" onerror="this.src='${fallbackImage}'"></a>
  <h3><a href="${articleUrl(first)}">${esc(excerpt(first.title, 72))}</a></h3>
  <ul>${rest}</ul>
</section>`;
    }).join('');

    const moreGridHtml = moreGridItems.map((article) => `<a class="ref-more-card" href="${articleUrl(article)}">
  <img src="${esc(articleImage(article))}" alt="" loading="lazy" onerror="this.src='${fallbackImage}'">
  <strong>${esc(article.category_label || 'News')}</strong>
  <h3>${esc(excerpt(article.title, 74))}</h3>
  <span>${esc(formatDate(article.published_at))}</span>
</a>`).join('');

    const denseGridHtml = denseGridItems.map((article, index) => `<a class="ref-news-cell${index % 9 === 0 ? ' is-wide' : ''}" href="${articleUrl(article)}">
  <img src="${esc(articleImage(article))}" alt="" loading="lazy" onerror="this.src='${fallbackImage}'">
  <span>${esc(article.category_label || 'News')}</span>
  <h3>${esc(excerpt(article.title, index % 9 === 0 ? 96 : 68))}</h3>
  <small>${esc(formatDate(article.published_at))}</small>
</a>`).join('');

    const topicSectionsHtml = sectionSlugs.map((slug, sectionIndex) => {
        const label = categories[slug] || slug;
        const list = categoryArticles(slug, 8);
        const sectionItems = (list.length >= 5 ? list : [...list, ...pickItems(sectionIndex * 6, 8)]).slice(0, 8);
        const cards = sectionItems.map((article) => `<a class="ref-topic-card" href="${articleUrl(article)}">
  <img src="${esc(articleImage(article))}" alt="" loading="lazy" onerror="this.src='${fallbackImage}'">
  <h3>${esc(excerpt(article.title, 64))}</h3>
  <span>${esc(formatDate(article.published_at))}</span>
</a>`).join('');
        return `<section class="ref-topic-section">
  <div class="ref-col-head"><h2>${esc(label)}</h2><a href="${categoryUrl(slug)}">More</a></div>
  <div class="ref-topic-grid">${cards}</div>
</section>`;
    }).join('');

    const faqJson = JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'FAQPage',
        mainEntity: [
            { '@type': 'Question', name: 'How fast does MIYIZE refresh?', acceptedAnswer: { '@type': 'Answer', text: 'The news engine auto-refreshes every 5 minutes with latest stories and metadata updates.' } },
            { '@type': 'Question', name: 'Does MIYIZE support images and auto summary?', acceptedAnswer: { '@type': 'Answer', text: 'Yes. Stories are enriched with image and summary generation blocks.' } },
        ],
    });

    const body = `<div class="container ref-home">
  <script type="application/ld+json">${faqJson}</script>
  <section class="ref-hero-grid">
    <div class="ref-hero-main"><div class="hero-slider" id="heroSlider">${slides}<div class="slider-dots">${dots}</div></div></div>
    <aside class="ref-live-panel">
      <div class="section-title"><h2>Live Updates</h2><a href="/feed.xml">RSS</a></div>
      <div class="ref-live-list">${liveHtml}</div>
    </aside>
    <aside class="ref-tools-panel">
      <div class="ref-tool-box">
        <h2>Newsletter & WhatsApp</h2>
        <p>Get fast alerts on your phone and inbox.</p>
        <a class="button" href="/contact.php">Subscribe</a>
      </div>
      <div class="ref-ad-box"><small>SPONSOR</small><strong>300 x 250</strong></div>
    </aside>
  </section>

  <section class="ref-second-grid">
    <div class="ref-block">
      <div class="section-title"><h2>ತಾಜಾ ಸುದ್ದಿ</h2><a href="/">All News</a></div>
      <div class="ref-mini-grid">${tazaHtml}</div>
    </div>
    <div class="ref-block">
      <div class="section-title"><h2>ಕರ್ನಾಟಕ</h2><a href="${categoryUrl('karnataka')}">All</a></div>
      <div class="ref-karnataka-wrap">${kPanel}</div>
    </div>
    <div class="ref-block">
      <div class="section-title"><h2>Trending</h2></div>
      <div class="ref-trending-list">${trendHtml}</div>
    </div>
  </section>

  <section class="ref-category-row">${categoryColumns}</section>

  <section class="ref-topic-wall">
    <div class="section-title"><h2>Category Grids</h2><a href="/sitemap.xml">All Sections</a></div>
    <div class="ref-topic-wall-grid">${topicSectionsHtml}</div>
  </section>

  <section class="ref-dense-section">
    <div class="section-title"><h2>All News Grid</h2><a href="/feed.xml">Live Feed</a></div>
    <div class="ref-news-grid">${denseGridHtml}</div>
  </section>

  <section class="ref-more-section">
    <div class="section-title"><h2>More Stories</h2><a href="/sitemap.xml">Sitemap</a></div>
    <div class="ref-more-grid">${moreGridHtml}</div>
  </section>
</div>
<script>
  (function(){
    const slider = document.getElementById('heroSlider');
    if(!slider) return;
    const slides = slider.querySelectorAll('.hero-tile');
    const dots = slider.querySelectorAll('.slider-dot');
    if(!slides.length || !dots.length) return;
    let current = 0;
    function show(index){
      slides.forEach((s, i) => s.classList.toggle('active', i === index));
      dots.forEach((d, i) => d.classList.toggle('active', i === index));
    }
    function next(){
      current = (current + 1) % slides.length;
      show(current);
    }
    let timer = setInterval(next, 5000);
    dots.forEach((dot, i) => dot.addEventListener('click', () => {
      clearInterval(timer);
      current = i;
      show(current);
      timer = setInterval(next, 5000);
    }));
  })();
</script>`;
    return shell(siteName, siteTagline, 'latest', body, lead, '/');
}

function categoryPage(slug) {
    if (!categories[slug]) return notFound();
    const label = categories[slug];
    const list = categoryArticles(slug, 48);
    const trending = articles(5);
    const trendNums = ['n1','n2','n3','n4','n5'];
    const trendHtml = trending.map((a, i) => `<a class="trending-item" href="${articleUrl(a)}">
  <span class="trending-num ${trendNums[i] || ''}">${i+1}</span>
  <span class="trending-title">${esc(excerpt(a.title, 70))}</span>
</a>`).join('');

    const otherCatHtml = Object.entries(categories)
        .filter(([s]) => s !== slug && s !== 'latest')
        .slice(0, 5)
        .map(([s, l]) => `<a href="${categoryUrl(s)}" style="display:flex;align-items:center;gap:9px;padding:8px 12px;border-bottom:1px solid #f4f4f4;font-size:13px;font-weight:600;color:#222;transition:color .15s;" onmouseover="this.style.color='#d81f2a'" onmouseout="this.style.color='#222'">${esc(l)}</a>`)
        .join('');

    const body = `<div class="container" style="padding-top:14px;">
  <div class="bento" style="margin-bottom:18px;">
    <div class="bento-card bento-span-4" style="padding:20px;flex-direction:row;justify-content:space-between;align-items:center;background:rgba(216,31,42,0.1);border-color:rgba(216,31,42,0.2);">
      <h1 style="font-size:24px;font-weight:900;color:#d81f2a;margin:0;">${esc(label)}</h1>
      <span style="font-size:12px;color:#d81f2a;font-weight:800;background:#fff;padding:4px 12px;border-radius:12px;box-shadow:0 4px 12px rgba(216,31,42,.1);">${list.length} ಸುದ್ದಿ</span>
    </div>
  </div>
  ${adSlot()}
  <div class="bento">
    ${list.map((a, i) => i === 0 ? `<div class="bento-card bento-span-2x2"><a class="hero-tile" href="${articleUrl(a)}"><img src="${esc(articleImage(a))}"><div class="hero-tile__overlay"></div><div class="hero-tile__content"><span class="hero-tile__cat">${esc(a.category_label||label)}</span><span class="hero-tile__title">${esc(excerpt(a.title, 80))}</span></div></a></div>` : `<div class="bento-card"><a class="bento-img-wrap" href="${articleUrl(a)}"><img src="${esc(articleImage(a))}"></a><div class="bento-body"><a class="bento-cat" href="${articleUrl(a)}">${esc(a.category_label||label)}</a><a class="bento-title" href="${articleUrl(a)}">${esc(excerpt(a.title, 65))}</a><div class="bento-meta">${esc(formatDate(a.published_at))}</div></div></div>`).join('') || '<div class="bento-card bento-span-4 empty-state"><h1>ಸುದ್ದಿ ಲೋಡ್ ಆಗುತ್ತಿದೆ</h1></div>'}
  </div>
</div>`;
    return shell(label, `${esc(label)} ವಿಭಾಗದ ತಾಜಾ ಕನ್ನಡ ಸುದ್ದಿ - MIYIZE Kannada News`, slug, body, null, categoryUrl(slug));
}

function articlePage(slug) {
    const article = findArticle(slug);
    if (!article) return notFound();
    const related = categoryArticles(article.category || 'latest', 8).filter(i => i.slug !== article.slug).slice(0, 5);
    const score = Math.min(100, Math.max(8, Number(article.trend_score || 55)));
    const scoreBar = `<div style="margin-top:8px;height:6px;background:#eee;border-radius:999px;overflow:hidden;"><div style="width:${score}%;height:100%;background:linear-gradient(90deg,#d81f2a,#c8960c);"></div></div>`;
    const relatedHtml = related.map(a => `<a class="compact-item" href="${articleUrl(a)}"><div class="compact-img"><img src="${esc(articleImage(a))}" alt="" loading="lazy" onerror="this.src='/assets/images/newsroom-fallback.png'"></div><div><div class="compact-text">${esc(excerpt(a.title, 65))}</div><div class="bento-meta" style="margin-top:4px;">${esc(formatDate(a.published_at))}</div></div></a>`).join('');
    const tagsHtml = (article.tags||[]).map(t=>`<a href="/search.php?q=${encodeURIComponent(t)}" style="display:inline-block;padding:3px 10px;border-radius:999px;font-size:11px;font-weight:600;background:#f0f0f0;color:#555;margin:2px;">${esc(t)}</a>`).join('');
    const contentParas = (article.full_content||article.summary||'').split('\n\n').filter(p=>p.trim()).map(p=>{
        if(p.trim()==='<!-- AD_SLOT -->') return adSlot();
        let safeP = esc(p).replace(/&lt;span class=&quot;highlight&quot;&gt;(.*?)&lt;\/span&gt;/g, '<span class="highlight">$1</span>');
        return `<p>${safeP}</p>`;
    }).join('');
    const shareUrl = articleUrl(article);
    const jsonLd = JSON.stringify({"@context":"https://schema.org","@type":"NewsArticle",headline:article.title,image:articleImage(article),datePublished:article.published_at,dateModified:article.updated_at||article.published_at,author:{"@type":"Organization",name:siteName},publisher:{"@type":"Organization",name:siteName,logo:{"@type":"ImageObject",url:"/assets/images/newsroom-fallback.png"}},description:excerpt(article.summary,160)});

    const body = `<script type="application/ld+json">${jsonLd}</script>
<div class="container article-bento" style="padding-top:14px;">
  <article class="article-body">
    <div class="article-meta"><span class="realtime-badge"><span class="dot"></span>Live Fetch</span><a href="${categoryUrl(article.category||'latest')}">${esc(article.category_label||'ತಾಜಾ ಸುದ್ದಿ')}</a><span>📖 ${article.reading_minutes||1} ನಿಮಿಷ</span><span>${esc(formatDate(article.published_at))}</span><span>${esc(article.source||'Source')}</span></div>
    <h1>${esc(article.title)}</h1>
    <div style="display:flex;gap:8px;margin-bottom:24px;"><a href="https://api.whatsapp.com/send?text=${encodeURIComponent(article.title+' '+shareUrl)}" target="_blank" style="padding:6px 16px;background:#25d366;color:#fff;border-radius:12px;font-size:13px;font-weight:800;box-shadow:0 4px 12px rgba(37,211,102,.2);">WhatsApp</a><a href="https://twitter.com/intent/tweet?text=${encodeURIComponent(article.title)}&url=${encodeURIComponent(shareUrl)}" target="_blank" style="padding:6px 16px;background:#1da1f2;color:#fff;border-radius:12px;font-size:13px;font-weight:800;box-shadow:0 4px 12px rgba(29,161,242,.2);">Twitter</a></div>
    <figure><img src="${esc(articleImage(article))}" alt="" loading="eager" onerror="this.src='/assets/images/newsroom-fallback.png'"></figure>
    <div class="article-content">${contentParas}</div>
    <div class="bento-card" style="padding:0;margin:24px 0;"><div class="list-tile__head">ಪ್ರಮುಖ ಮುಖ್ಯಾಂಶಗಳು (Main Highlights)</div><ul style="padding:16px 16px 16px 36px;font-size:14px;color:#333;line-height:1.6;font-weight:600;">${(article.key_points||[]).map(p=>`<li style="margin-bottom:8px;">${esc(p)}</li>`).join('')}</ul></div>

    ${tagsHtml?`<div style="margin:24px 0;display:flex;align-items:center;gap:12px;flex-wrap:wrap;"><strong style="font-size:12px;color:#888;">TAGS</strong> ${tagsHtml}</div>`:''}
    ${adSlot()}
    <div class="bento-card" style="padding:24px;margin-top:24px;text-align:center;background:rgba(0,0,0,0.02);"><h2 style="font-size:16px;font-weight:900;margin-bottom:8px;">ಮೂಲ ವರದಿ</h2><p style="font-size:14px;color:#666;margin-bottom:16px;">ಈ ಲೇಖನವು ಮೂಲ ವರದಿಯ ಸಾರಾಂಶ. ಪೂರ್ಣ ವಿವರಕ್ಕಾಗಿ ಮೂಲ ವೆಬ್‌ಸೈಟ್‌ಗೆ ಭೇಟಿ ನೀಡಿ.</p><a class="nl-btn" href="${esc(article.source_url||'#')}" target="_blank" rel="nofollow noopener">ಮೂಲ ಸುದ್ದಿ ಓದಿ →</a></div>
  </article>
  <aside class="article-sidebar">
     <div class="bento-card" style="padding:0;margin-bottom:20px;">
        <div class="list-tile__head">ಸಂಬಂಧಿತ ಸುದ್ದಿ</div>
        ${relatedHtml}
     </div>
     ${adSlot()}
  </aside>
</div>`;
    return shell(article.seo_title||article.title, article.meta_description||excerpt(article.summary,160), article.category||'latest', body, article, articleUrl(article));
}

function searchPage(query) {
    const needle = query.trim().toLowerCase();
    const results = needle ? articles().filter(a => [a.title, a.summary, a.source, a.category_label].join(' ').toLowerCase().includes(needle)).slice(0, 30) : [];
    const body = `<div class="container" style="padding-top:14px;">
  <h1 style="font-size:28px;font-weight:900;margin-bottom:16px;">ಸುದ್ದಿ ಹುಡುಕಿ</h1>
  <form class="search-page-form" method="get" action="/search.php"><input name="q" type="search" value="${esc(query)}" placeholder="ವಿಷಯ, ನಗರ, ವ್ಯಕ್ತಿ..."><button type="submit">ಹುಡುಕಿ</button></form>
  ${needle ? `<p style="margin-bottom:16px;color:#888;font-size:14px;font-weight:700;">${results.length} ಫಲಿತಾಂಶಗಳು</p>
  <div class="bento">
    ${results.map(a => `<div class="bento-card"><a class="bento-img-wrap" href="${articleUrl(a)}"><img src="${esc(articleImage(a))}"></a><div class="bento-body"><a class="bento-cat" href="${articleUrl(a)}">${esc(a.category_label||'latest')}</a><a class="bento-title" href="${articleUrl(a)}">${esc(excerpt(a.title, 65))}</a><div class="bento-meta">${esc(formatDate(a.published_at))}</div></div></div>`).join('')}
  </div>` : ''}
</div>`;
    const searchCanonical = needle ? `/search.php?q=${encodeURIComponent(query)}` : '/search.php';
    return shell('ಹುಡುಕಾಟ', 'ಕನ್ನಡ ಸುದ್ದಿಗಳಲ್ಲಿ ಹುಡುಕಿ.', '', body, null, searchCanonical);
}

function simplePage(title, html, canonicalPath = '/') {
    return shell(title, `${title} - ${siteName}`, '', `<div class="container" style="max-width:860px;padding-top:22px;padding-bottom:40px;">${html}</div>`, null, canonicalPath);
}

function notFound() {
    return shell('ಸುದ್ದಿ ಕಂಡುಬಂದಿಲ್ಲ', 'ಈ ಪುಟ ಲಭ್ಯವಿಲ್ಲ.', '', '<div class="container" style="padding-top:40px;"><div class="empty-state"><h1>ಸುದ್ದಿ ಕಂಡುಬಂದಿಲ್ಲ</h1><p>Homepage ಗೆ ಮರಳಿ.</p><a href="/">Homepage</a></div></div>', null, '/404');
}

function rssFeedUrl(slug = 'latest') {
    return slug === 'latest' ? '/feed.xml' : `/rss/${encodeURIComponent(slug)}.xml`;
}

function apiCategoryUrl(slug = 'latest') {
    return slug === 'latest' ? '/api/latest.php' : `/api/category/${encodeURIComponent(slug)}.json`;
}

function feedXml(base, slug = 'latest') {
    const safeSlug = categories[slug] ? slug : 'latest';
    const label = categories[safeSlug] || categories.latest;
    const channelLink = safeSlug === 'latest' ? '/' : categoryUrl(safeSlug);
    const feedDescription = safeSlug === 'latest' ? siteTagline : `${label} Kannada news RSS feed from ${siteName}`;
    const items = categoryArticles(safeSlug, 60).map((article) => `<item><title>${esc(article.title)}</title><link>${base}${articleUrl(article)}</link><guid isPermaLink="true">${base}${articleUrl(article)}</guid><pubDate>${new Date(article.published_at || Date.now()).toUTCString()}</pubDate><category>${esc(article.category_label || label)}</category><description>${esc(excerpt(article.summary, 260))}</description></item>`).join('');
    return `<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel><title>${esc(`${siteName} - ${label}`)}</title><link>${base}${channelLink}</link><description>${esc(feedDescription)}</description><language>kn-IN</language><lastBuildDate>${new Date().toUTCString()}</lastBuildDate>${items}</channel></rss>`;
}

function sitemapXml(base) {
    const staticUrls = ['/', ...Object.keys(categories).filter((slug) => slug !== 'latest').map(categoryUrl)];
    const urls = [...staticUrls, ...articles(180).map(articleUrl)].map((url) => `<url><loc>${base}${url}</loc><changefreq>hourly</changefreq><priority>0.8</priority></url>`).join('');
    return `<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">${urls}</urlset>`;
}

function apiArticle(article) {
    return {
        id: article.slug || '',
        title: article.title || '',
        slug: article.slug || '',
        url: articleUrl(article),
        absolute_url: `${siteUrl}${articleUrl(article)}`,
        category: article.category_label || '',
        category_slug: article.category || 'latest',
        image: articleImage(article),
        source: article.source || '',
        source_url: article.source_url || '',
        summary: excerpt(article.summary || article.full_content || '', 220),
        published_at: article.published_at || '',
        updated_at: article.updated_at || article.published_at || '',
        time_label: formatDate(article.published_at),
        tags: Array.isArray(article.tags) ? article.tags : [],
    };
}

function apiCategory(slug = 'latest', limit = 24) {
    const safeSlug = categories[slug] ? slug : 'latest';
    const label = categories[safeSlug] || categories.latest;
    return JSON.stringify({
        state: readJson(statePath, {}),
        category: {
            slug: safeSlug,
            label,
            page_url: categoryUrl(safeSlug),
            rss_url: rssFeedUrl(safeSlug),
            api_url: apiCategoryUrl(safeSlug),
        },
        count: categoryArticles(safeSlug, limit).length,
        articles: categoryArticles(safeSlug, limit).map(apiArticle),
    });
}

function apiCategories(base) {
    return JSON.stringify({
        site: siteName,
        generated_at: new Date().toISOString(),
        categories: Object.entries(categories).map(([slug, label]) => ({
            slug,
            label,
            page_url: `${base}${slug === 'latest' ? '/' : categoryUrl(slug)}`,
            rss_url: `${base}${rssFeedUrl(slug)}`,
            api_url: `${base}${apiCategoryUrl(slug)}`,
            count: categoryArticles(slug, 0).length,
        })),
    });
}

function mime(file) {
    const ext = path.extname(file).toLowerCase();
    return {
        '.css': 'text/css; charset=utf-8',
        '.js': 'application/javascript; charset=utf-8',
        '.json': 'application/json; charset=utf-8',
        '.png': 'image/png',
        '.jpg': 'image/jpeg',
        '.jpeg': 'image/jpeg',
        '.webp': 'image/webp',
        '.svg': 'image/svg+xml; charset=utf-8',
        '.ico': 'image/x-icon',
    }[ext] || 'application/octet-stream';
}

function serveStatic(res, requestPath) {
    const normalized = path.normalize(decodeURIComponent(requestPath)).replace(/^(\.\.[/\\])+/, '');
    const filePath = path.join(publicDir, normalized);
    if (!filePath.startsWith(publicDir) || !fs.existsSync(filePath) || !fs.statSync(filePath).isFile()) return false;
    res.writeHead(200, { 'Content-Type': mime(filePath), 'Cache-Control': 'public, max-age=120' });
    fs.createReadStream(filePath).pipe(res);
    return true;
}

const server = http.createServer(async (req, res) => {
    const host = req.headers.host || `localhost:${server.address()?.port || 8080}`;
    const base = `http://${host}`;
    const url = new URL(req.url || '/', base);
    const route = decodeURIComponent(url.pathname);

    if (route.startsWith('/assets/') && serveStatic(res, route)) return;
    refreshNews().catch(() => {});

    let status = 200;
    let type = 'text/html; charset=utf-8';
    let body = '';

    // --- ADMIN ROUTES ---
    if (route === '/admin/login' && req.method === 'POST') {
        let rawBody = ''; req.on('data',c=>rawBody+=c); await new Promise(r=>req.on('end',r));
        const pass = new URLSearchParams(rawBody).get('pass');
        if (pass === admin.ADMIN_PASS) {
            const sid = require('crypto').randomBytes(16).toString('hex');
            admin.sessions.add(sid);
            res.writeHead(302,{'Set-Cookie':`miyize_admin=${sid};Path=/;HttpOnly`,'Location':'/admin'}); res.end(); return;
        }
        res.writeHead(200,{'Content-Type':'text/html; charset=utf-8'}); res.end(admin.loginPage('Wrong password')); return;
    }
    if (route === '/admin/login') { res.writeHead(200,{'Content-Type':'text/html; charset=utf-8'}); res.end(admin.loginPage()); return; }
    if (route.startsWith('/admin') && route !== '/admin/login') {
        if (!admin.checkAuth(req)) { res.writeHead(302,{'Location':'/admin/login'}); res.end(); return; }
        type = 'text/html; charset=utf-8';
        const allItems = articles();
        const state = readJson(statePath, {});
        if (route === '/admin') { body = admin.dashboardPage(allItems, state); }
        else if (route === '/admin/articles') { body = admin.articlesPage(allItems, url.searchParams.get('q')||''); }
        else if (route.match(/^\/admin\/article\/[^/]+$/) && req.method === 'POST') {
            const editSlug = decodeURIComponent(route.split('/')[3]);
            let rawBody=''; req.on('data',c=>rawBody+=c); await new Promise(r=>req.on('end',r));
            const params = new URLSearchParams(rawBody);
            const all = articles(); const idx = all.findIndex(a=>a.slug===editSlug);
            if (idx>=0) {
                all[idx].title = params.get('title')||all[idx].title;
                all[idx].summary = params.get('summary')||all[idx].summary;
                all[idx].full_content = params.get('full_content')||all[idx].full_content;
                all[idx].seo_title = params.get('seo_title')||all[idx].seo_title;
                all[idx].meta_description = params.get('meta_description')||all[idx].meta_description;
                all[idx].category = params.get('category')||all[idx].category;
                all[idx].category_label = categories[all[idx].category]||all[idx].category;
                all[idx].image = params.get('image')||all[idx].image;
                all[idx].tags = (params.get('tags')||'').split(',').map(t=>t.trim()).filter(Boolean);
                all[idx].updated_at = new Date().toISOString();
                writeJson(articlesPath, all);
            }
            res.writeHead(302,{'Location':`/admin/article/${encodeURIComponent(editSlug)}`}); res.end(); return;
        }
        else if (route.match(/^\/admin\/article\/[^/]+\/publish$/)) {
            const s=decodeURIComponent(route.split('/')[3]); const all=articles(); const i=all.findIndex(a=>a.slug===s);
            if(i>=0){all[i].published=true;writeJson(articlesPath,all);}
            res.writeHead(302,{'Location':`/admin/article/${encodeURIComponent(s)}`}); res.end(); return;
        }
        else if (route.match(/^\/admin\/article\/[^/]+\/unpublish$/)) {
            const s=decodeURIComponent(route.split('/')[3]); const all=articles(); const i=all.findIndex(a=>a.slug===s);
            if(i>=0){all[i].published=false;writeJson(articlesPath,all);}
            res.writeHead(302,{'Location':`/admin/article/${encodeURIComponent(s)}`}); res.end(); return;
        }
        else if (route.match(/^\/admin\/article\/[^/]+\/delete$/)) {
            const s=decodeURIComponent(route.split('/')[3]); const all=articles().filter(a=>a.slug!==s);
            writeJson(articlesPath,all);
            res.writeHead(302,{'Location':'/admin/articles'}); res.end(); return;
        }
        else if (route.match(/^\/admin\/article\/[^/]+$/)) {
            const s=decodeURIComponent(route.split('/')[3]); body = admin.editPage(findArticle(s));
        }
        else if (route === '/admin/seo') { body = admin.seoPage(allItems); }
        else if (route === '/admin/refresh') {
            await refreshNews({force:true});
            res.writeHead(302,{'Location':'/admin'}); res.end(); return;
        }
        else { body = admin.dashboardPage(allItems, state); }
        res.writeHead(200,{'Content-Type':type}); res.end(body); return;
    }

    // --- PUBLIC ROUTES ---
    if (route === '/cron/fetch_news.php') {
        type = 'application/json; charset=utf-8';
        body = JSON.stringify(await refreshNews({ force: true }));
    } else if (route === '/') body = homePageV2();
    else if (route.match(/^\/category\/[^/]+\/feed\.xml$/)) {
        const slug = route.split('/').filter(Boolean)[1] || 'latest';
        type = 'application/rss+xml; charset=utf-8';
        body = feedXml(base, slug);
    } else if (route.startsWith('/category/')) {
        const slug = route.split('/').filter(Boolean)[1] || 'latest';
        if (!categories[slug]) status = 404;
        body = categoryPage(slug);
    } else if (route.startsWith('/article/')) {
        const slug = route.split('/').filter(Boolean)[1] || '';
        if (!findArticle(slug)) status = 404;
        body = articlePage(slug);
    }
    else if (route === '/search.php') body = searchPage(url.searchParams.get('q') || '');
    else if (route === '/api/latest.php') {
        type = 'application/json; charset=utf-8';
        body = apiCategory('latest', 24);
    } else if (route === '/api/categories.php') {
        type = 'application/json; charset=utf-8';
        body = apiCategories(base);
    } else if (route === '/api/category.php') {
        type = 'application/json; charset=utf-8';
        body = apiCategory(url.searchParams.get('category') || url.searchParams.get('slug') || 'latest', Number(url.searchParams.get('limit') || 24));
    } else if (route.match(/^\/api\/category\/[^/]+\.json$/)) {
        const slug = path.basename(route, '.json');
        type = 'application/json; charset=utf-8';
        body = apiCategory(slug, Number(url.searchParams.get('limit') || 24));
    } else if (route.match(/^\/api\/[^/]+\.json$/)) {
        const slug = path.basename(route, '.json');
        type = 'application/json; charset=utf-8';
        body = apiCategory(slug, Number(url.searchParams.get('limit') || 24));
    } else if (route === '/feed.xml' || route === '/rss.php') {
        type = 'application/rss+xml; charset=utf-8';
        body = feedXml(base, url.searchParams.get('category') || url.searchParams.get('slug') || 'latest');
    } else if (route.match(/^\/rss\/[^/]+\.xml$/) || route.match(/^\/feed\/[^/]+\.xml$/)) {
        const slug = path.basename(route, '.xml');
        type = 'application/rss+xml; charset=utf-8';
        body = feedXml(base, slug);
    } else if (route === '/sitemap.xml' || route === '/sitemap.php') {
        type = 'application/xml; charset=utf-8';
        body = sitemapXml(base);
    } else if (route === '/robots.txt') {
        type = 'text/plain; charset=utf-8';
        body = `User-agent: *\nAllow: /\nSitemap: ${base}/sitemap.xml\n`;
    } else if (route === '/about.php') body = simplePage('About', '<h1>About MIYIZE Kannada News</h1><p>MIYIZE Kannada News ಕನ್ನಡ ಓದುಗರಿಗೆ ವೇಗವಾಗಿ ಸುದ್ದಿ ತಲುಪಿಸಲು ನಿರ್ಮಿಸಿದ ಡಿಜಿಟಲ್ ನ್ಯೂಸ್ ವೇದಿಕೆ.</p>');
    else if (route === '/contact.php') body = simplePage('Contact', '<h1>Contact</h1><p>ಸುದ್ದಿ ತಿದ್ದುಪಡಿ, ಜಾಹೀರಾತು, ಸಹಯೋಗ ಮತ್ತು ಫೀಡ್ ವಿಚಾರಗಳಿಗೆ ನಿಮ್ಮ ಅಧಿಕೃತ ಇಮೇಲ್ ಸೇರಿಸಿ.</p><p><strong>Email:</strong> editor@example.com</p>');
    else if (route === '/privacy.php') body = simplePage('Privacy Policy', '<h1>Privacy Policy</h1><p>ಈ ವೆಬ್‌ಸೈಟ್ ಬಳಕೆ ಅನುಭವ, ಜಾಹೀರಾತು ಮತ್ತು ವಿಶ್ಲೇಷಣೆಗಾಗಿ cookies ಅಥವಾ third-party services ಬಳಸಬಹುದು.</p>');
    else {
        status = 404;
        body = notFound();
    }

    const cacheHeader = type.includes('html') ? 'public, max-age=60' : 'public, max-age=300';
    res.writeHead(status, { 'Content-Type': type, 'Cache-Control': cacheHeader });
    res.end(body);
});

function listen(port) {
    server.once('error', (error) => {
        if (error.code === 'EADDRINUSE' && port < 8090) {
            listen(port + 1);
            return;
        }
        throw error;
    });

    server.listen(port, '127.0.0.1', () => {
        const info = { url: `http://127.0.0.1:${port}`, started_at: new Date().toISOString(), pid: process.pid };
        writeJson(path.join(root, '.local-server.json'), info);
        console.log(`MIYIZE local server running at ${info.url}`);
    });
}

refreshNews({ force: true })
    .catch((error) => {
        console.warn(`Initial refresh skipped: ${error.message}`);
    })
    .finally(() => {
        listen(Number(process.env.PORT || 8080));
        setInterval(() => {
            refreshNews().catch(() => {});
        }, autoRefreshMs).unref();
    });
