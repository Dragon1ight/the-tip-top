const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: '.',
  timeout: 60000,
  workers: 1,
  reporter: [['list'], ['junit', { outputFile: 'reports/junit.xml' }], ['html', { outputFolder: 'reports/html', open: 'never' }]],
  use: { baseURL: process.env.BASE_URL || 'http://localhost:8080', locale: 'fr-FR' },
  projects: [
    { name: 'http', testMatch: /http\.spec\.js/ },
    { name: 'chromium', testMatch: /browser\.spec\.js/, use: { browserName: 'chromium', viewport: { width: 1280, height: 850 } } }
  ]
});
