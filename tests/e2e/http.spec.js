const { test, expect } = require('@playwright/test');
const { nextCode, mail, PASSWORD, token } = require('./helpers');

async function register(request) {
  const page = await (await request.get('/inscription')).text();
  const r = await request.post('/inscription', { form: { _token: token(page), prenom: 'Http', nom: 'Test', email: mail('http'), date_naissance: '1990-01-01', civilite: 'autre', password: PASSWORD, reglement: 'on' } });
  expect(r.status()).toBe(200);
}

test('pages publiques', async ({ request }) => {
  for (const p of ['/', '/les-lots', '/comment-participer', '/le-concept', '/reglement', '/mentions-legales', '/cgu', '/confidentialite', '/cookies', '/accessibilite', '/contact']) {
    expect((await request.get(p)).status(), p).toBe(200);
  }
});

test('page 404 personnalisée', async ({ request }) => {
  const r = await request.get('/page-qui-n-existe-pas');
  expect(r.status()).toBe(404);
  expect(await r.text()).toContain('introuvable');
});

test('en-têtes de sécurité', async ({ request }) => {
  const h = (await request.get('/')).headers();
  expect(h['x-content-type-options']).toBe('nosniff');
  expect(h['x-frame-options']).toBe('DENY');
  expect(h['content-security-policy']).toContain("default-src 'self'");
});

test('SEO : robots et sitemap', async ({ request }) => {
  expect(await (await request.get('/robots.txt')).text()).toContain('Sitemap:');
  const s = await request.get('/sitemap.xml');
  expect(s.status()).toBe(200);
  expect(await s.text()).toContain('/reglement');
});

test('espaces protégés', async ({ request }) => {
  for (const p of ['/admin', '/boutique', '/mes-participations']) {
    const r = await request.get(p, { maxRedirects: 0 });
    expect(r.status(), p).toBe(302);
  }
});

test('inscription refusée sans règlement', async ({ request }) => {
  const page = await (await request.get('/inscription')).text();
  const r = await request.post('/inscription', { form: { _token: token(page), prenom: 'A', nom: 'B', email: mail('x'), date_naissance: '1990-01-01', civilite: 'autre', password: PASSWORD } });
  expect(r.status()).toBe(422);
});

test('inscription refusée aux mineurs', async ({ request }) => {
  const page = await (await request.get('/inscription')).text();
  const r = await request.post('/inscription', { form: { _token: token(page), prenom: 'A', nom: 'B', email: mail('x'), date_naissance: '2015-01-01', civilite: 'autre', password: PASSWORD, reglement: 'on' } });
  expect(r.status()).toBe(422);
  expect(await r.text()).toContain('majeures');
});

test('soumissions simultanées : un seul gain', async ({ request }) => {
  await register(request);
  const code = nextCode();
  const t = token(await (await request.get('/mes-participations')).text());
  await Promise.all(Array.from({ length: 6 }, () => request.post('/mes-participations/saisir', { form: { _token: t, code }, maxRedirects: 0 })));
  const html = await (await request.get('/mes-participations')).text();
  expect(html.split(code).length - 1).toBe(1);
});

test('POST avec jeton CSRF : code conservé pour la connexion', async ({ request }) => {
  const t = token(await (await request.get('/')).text());
  const r = await request.post('/jouer', { form: { _token: t, code: 'ABCDEFGHJK' } });
  expect(r.url()).toContain('/connexion');
  expect(await r.text()).toContain('Votre code est conservé');
});

// Un visiteur anonyme sans jeton est renvoyé vers la connexion et son code n'est pas traité.
test('POST sans jeton CSRF refusé', async ({ request }) => {
  const r = await request.post('/jouer', { form: { code: 'ABCDEFGHJK' } });
  expect(r.url()).toContain('/connexion');
  expect(await r.text()).not.toContain('Votre code est conservé');
});
