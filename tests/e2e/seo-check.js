// Contrôle SEO et poids de page : bloque le déploiement si un seuil n'est pas atteint.
const { execFileSync } = require('child_process');
const fs = require('fs');
const { chromium } = require('@playwright/test');

const URL_SITE = process.env.BASE_URL || 'http://web:10000';
fs.mkdirSync('reports', { recursive: true });
try {
  execFileSync('npx', ['lighthouse', URL_SITE + '/', '--quiet', '--only-categories=seo,accessibility,performance', '--output=json', '--output-path=reports/lighthouse.json', '--chrome-flags=--headless --no-sandbox --disable-dev-shm-usage'], { stdio: 'inherit', env: { ...process.env, CHROME_PATH: chromium.executablePath() } });
} catch (e) {
  if (!fs.existsSync('reports/lighthouse.json')) throw e;
}
const r = JSON.parse(fs.readFileSync('reports/lighthouse.json'));
const score = (c) => Math.round(r.categories[c].score * 100);
const weight = r.audits['total-byte-weight'].numericValue / 1024;
const requests = r.audits['network-requests'].details.items.length;
const checks = [
  ['SEO >= 90', score('seo') >= 90, score('seo')],
  ['Accessibilité >= 90', score('accessibility') >= 90, score('accessibility')],
  ['Poids < 500 Ko', weight < 500, Math.round(weight) + ' Ko'],
  ['Requêtes < 40', requests < 40, requests],
  ['Balise title', r.audits['document-title'].score === 1, ''],
  ['Méta description', r.audits['meta-description'].score === 1, '']
];
let ok = true;
for (const [n, pass, v] of checks) { console.log(`${pass ? 'OK ' : 'KO '} ${n} ${v}`); ok = ok && pass; }
process.exit(ok ? 0 : 1);
