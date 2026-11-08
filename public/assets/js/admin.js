(function () {
  'use strict';
  var root = document.getElementById('dash');
  var h = Vue.h;
  function kpi(v, l) { return h('li', [h('strong', Number(v).toLocaleString('fr-FR')), l]); }
  function bar(id) { return h('div', { class: 'chart' }, [h('canvas', { id: id, role: 'img', 'aria-label': 'Graphique, données détaillées dans le tableau' })]); }
  function pie(id, title) { return h('div', { class: 'card' }, [h('h2', title), bar(id)]); }
  function draw(id, rows) {
    var c = document.getElementById(id);
    if (c && rows.length) new Chart(c, { type: 'bar', data: { labels: rows.map(function (r) { return r.k; }), datasets: [{ data: rows.map(function (r) { return +r.n; }), backgroundColor: '#1B4332' }] }, options: { animation: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } } });
  }
  fetch(root.dataset.url, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (d) {
    Vue.createApp({
      mounted: function () { draw('age', d.age); draw('civ', d.civilite); draw('zone', d.zone); draw('bq', d.boutique); },
      render: function () {
        return h('div', [
          h('ul', { class: 'kpis' }, [kpi(d.codes.generes, 'Codes générés'), kpi(d.codes.distribues, 'Tickets distribués'), kpi(d.codes.utilises, 'Codes utilisés'), kpi(d.remis, 'Lots remis'), kpi(d.participants, 'Participants'), kpi(d.optin, 'Opt-in marketing')]),
          h('div', { class: 'card' }, [h('h2', 'Conformité de la répartition'), h('div', { class: 'scroll' }, [h('table', { class: 'tbl' }, [
            h('thead', [h('tr', ['Lot', 'Cible', 'Réel', 'Générés', 'Utilisés', 'Remis'].map(function (t) { return h('th', { scope: 'col' }, t); }))]),
            h('tbody', d.lots.map(function (l) { return h('tr', [h('td', l.libelle), h('td', l.pourcentage_cible + ' %'), h('td', l.pourcentage_reel + ' %'), h('td', l.generes), h('td', l.utilises || 0), h('td', l.remis || 0)]); }))
          ])])]),
          h('div', { class: 'grid2' }, [pie('age', 'Participants par âge'), pie('civ', 'Participants par civilité'), pie('zone', 'Participants par zone'), pie('bq', 'Lots par boutique')])
        ]);
      }
    }).mount(root);
  }).catch(function () { root.textContent = 'Statistiques indisponibles.'; });
})();
