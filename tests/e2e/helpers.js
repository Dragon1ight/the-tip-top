const codes = (process.env.CODES || '').split(',').filter(Boolean);
let next = 0;

exports.nextCode = () => {
  if (next >= codes.length) throw new Error('Plus de codes de test disponibles');
  return codes[next++];
};
exports.mail = (p) => `${p}-${Date.now()}-${Math.floor(Math.random() * 1e6)}@exemple.fr`;
exports.PASSWORD = 'MotDePasseE2e2026';
exports.ADMIN = { login: process.env.ADMIN_LOGIN || 'admin', password: process.env.ADMIN_PASSWORD || 'LocalAdmin2026x' };
exports.SHOP = { login: process.env.EMPLOYE_LOGIN || 'boutique', password: process.env.EMPLOYE_PASSWORD || 'LocalShop2026xx' };
exports.token = (html, name = '_token') => {
  const m = html.match(new RegExp(`name="${name}" value="([^"]+)"`));
  if (!m) throw new Error(`Jeton ${name} introuvable`);
  return m[1];
};
