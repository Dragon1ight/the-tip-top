(function () {
  'use strict';
  var store = {
    get: function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
    set: function (k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
  };
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

  // Menu mobile
  var burger = $('.burger'), menu = $('#menu');
  if (burger) {
    burger.addEventListener('click', function () {
      var open = menu.classList.toggle('open');
      burger.setAttribute('aria-expanded', String(open));
      if (open) { var a = $('a', menu); if (a) a.focus(); }
    });
  }

  // Compte à rebours
  var count = $('[data-countdown]');
  function tick() {
    var left = Math.max(0, new Date(count.dataset.countdown) - Date.now());
    $('[data-d]', count).textContent = Math.floor(left / 864e5);
    $('[data-h]', count).textContent = Math.floor(left / 36e5) % 24;
    $('[data-m]', count).textContent = Math.floor(left / 6e4) % 60;
  }
  if (count) { tick(); setInterval(tick, 30000); }

  // Réglages d'accessibilité
  $$('[data-a11y]').forEach(function (b) {
    var k = b.dataset.a11y, on = store.get('a11y-' + k) === '1';
    function apply() { document.documentElement.classList.toggle('a11y-' + k, on); b.setAttribute('aria-pressed', String(on)); }
    apply();
    b.addEventListener('click', function () { on = !on; store.set('a11y-' + k, on ? '1' : '0'); apply(); });
  });

  $$('[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) { if (!window.confirm(f.dataset.confirm)) e.preventDefault(); });
  });

  // Consentement et mesure d'audience : rien n'est chargé avant l'accord
  var ga = document.currentScript && document.currentScript.dataset.ga;
  var banner = $('#cookie');
  window.dataLayer = window.dataLayer || [];
  function gtag() { window.dataLayer.push(arguments); }
  var loaded = false;
  function loadGa() {
    if (!ga || loaded) return;
    loaded = true;
    var s = document.createElement('script');
    s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(ga);
    s.async = true;
    document.head.appendChild(s);
    gtag('js', new Date());
    gtag('config', ga, { anonymize_ip: true });
    if ($('[data-page="lots"]')) gtag('event', 'vue_page_lots');
    if ($('[data-won]')) gtag('event', 'participation_code');
    if (store.get('ga-inscription') === '1') { gtag('event', 'soumission_inscription'); store.set('ga-inscription', '0'); }
  }
  function choice(v) { store.set('consent_state', v); banner.hidden = true; if (v === 'accept') loadGa(); }
  var state = store.get('consent_state');
  if (banner) {
    if (!state) banner.hidden = false; else if (state === 'accept') loadGa();
    $$('[data-consent]').forEach(function (b) { b.addEventListener('click', function () { choice(b.dataset.consent); }); });
    $$('[data-cookies]').forEach(function (b) { b.addEventListener('click', function () { banner.hidden = false; $('[data-consent]', banner).focus(); }); });
  }
  $$('[data-cta]').forEach(function (b) { b.addEventListener('click', function () { if (loaded) gtag('event', 'clic_cta_participer'); }); });
  var reg = $('form[data-track="inscription"]');
  if (reg) reg.addEventListener('submit', function () { store.set('ga-inscription', '1'); });
})();
