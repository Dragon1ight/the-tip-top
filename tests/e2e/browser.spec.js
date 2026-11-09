const { test, expect } = require('@playwright/test');
const { nextCode, mail, PASSWORD, ADMIN, SHOP } = require('./helpers');

async function login(page, u) {
  await page.goto('/connexion');
  await page.fill('#email', u.login);
  await page.fill('#password', u.password);
  await page.click('button:has-text("Se connecter")');
}

test.beforeEach(async ({ page }) => {
  await page.goto('/');
  await page.evaluate(() => localStorage.setItem('consent_state', 'refuse'));
});

test('parcours participant, boutique et administration', async ({ page }) => {
  const code = nextCode();
  const email = mail('nav');
  await page.goto('/');
  await expect(page.locator('h1')).toHaveCount(1);
  await page.fill('#code', code.slice(0, 4).toLowerCase() + ' ' + code.slice(4));
  await page.click('.code-form button');
  await expect(page).toHaveURL(/connexion/);
  await page.click('text=Créer un compte');
  await page.fill('#prenom', 'Claire');
  await page.fill('#nom', 'Lambert');
  await page.fill('#reg-email', email);
  await page.fill('#dn', '1990-04-01');
  await page.selectOption('#civ', 'madame');
  await page.fill('#reg-pw', PASSWORD);
  await page.check('input[name=reglement]');
  await page.click('button:has-text("Créer mon compte")');
  await expect(page).toHaveURL(/nouveau=1/);
  await page.selectOption('#bq', '10');
  await page.click('button:has-text("Enregistrer mon choix")');
  await expect(page.locator('.hist li')).toHaveCount(1);
  await page.goto('/deconnexion');

  await login(page, SHOP);
  await expect(page).toHaveURL(/boutique/);
  await page.fill('#q', code);
  await page.click('button:has-text("Rechercher")');
  await page.click('button:has-text("Valider la remise")');
  await expect(page.locator('.flash-success')).toContainText('Remise');
  await page.goto('/deconnexion');

  await login(page, ADMIN);
  await expect(page).toHaveURL(/admin/);
  await expect(page.locator('.kpis')).toContainText('Codes générés');
});

test('code déjà utilisé et code inconnu', async ({ page }) => {
  const code = nextCode();
  await page.goto('/inscription');
  await page.fill('#prenom', 'Marc');
  await page.fill('#nom', 'Delattre');
  await page.fill('#reg-email', mail('nav2'));
  await page.fill('#dn', '1982-02-02');
  await page.selectOption('#civ', 'monsieur');
  await page.fill('#reg-pw', PASSWORD);
  await page.check('input[name=reglement]');
  await page.click('button:has-text("Créer mon compte")');
  await page.goto('/mes-participations');
  await page.fill('#code', code);
  await page.click('.code-form button');
  await expect(page).toHaveURL(/nouveau=1/);
  await page.goto('/mes-participations');
  await page.fill('#code', code);
  await page.click('.code-form button');
  await expect(page.locator('.flash-error')).toContainText('déjà');
  await page.goto('/mes-participations');
  await page.fill('#code', 'ZZZZZZZZZZ');
  await page.click('.code-form button');
  await expect(page.locator('.flash-error')).toContainText('aucun ticket');
});

test('un participant n\'accède pas à l\'administration', async ({ page }) => {
  await page.goto('/inscription');
  await page.fill('#prenom', 'Ines');
  await page.fill('#nom', 'Haddad');
  await page.fill('#reg-email', mail('nav3'));
  await page.fill('#dn', '2000-03-03');
  await page.selectOption('#civ', 'madame');
  await page.fill('#reg-pw', PASSWORD);
  await page.check('input[name=reglement]');
  await page.click('button:has-text("Créer mon compte")');
  expect((await page.goto('/admin')).status()).toBe(403);
});

test('affichage mobile sans débordement', async ({ page }) => {
  await page.setViewportSize({ width: 375, height: 780 });
  await page.goto('/');
  expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(375);
});
