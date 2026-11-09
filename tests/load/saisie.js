import http from 'k6/http';
import { check } from 'k6';

// Scénario de pic du cahier des charges : 500 saisies de code par minute.
const BASE = __ENV.BASE_URL;
const token = (html, name) => (html.match(new RegExp(`name="${name}" value="([^"]+)"`)) || [])[1];

export const options = {
  scenarios: { pic: { executor: 'constant-arrival-rate', rate: 500, timeUnit: '1m', duration: __ENV.DURATION || '1m', preAllocatedVUs: 10, maxVUs: 40 } },
  thresholds: { 'http_req_duration{name:saisie}': ['p(95)<300'], 'http_req_failed{name:saisie}': ['rate==0'] }
};

export function setup() {
  const jar = http.cookieJar();
  const email = `k6-${Date.now()}@exemple.fr`;
  const page = http.get(`${BASE}/inscription`, { jar });
  http.post(`${BASE}/inscription`, { _token: token(page.body, '_token'), prenom: 'Charge', nom: 'Test', email, date_naissance: '1990-01-01', civilite: 'autre', password: 'MotDePasseK6202611', reglement: 'on' }, { jar });
  const cookies = jar.cookiesForURL(BASE);
  const gains = http.get(`${BASE}/mes-participations`, { jar });

  return { sid: cookies.ttt_sid[0], t: token(gains.body, '_token') };
}

export default function (d) {
  const r = http.post(`${BASE}/mes-participations/saisir`, { _token: d.t, code: 'ZZZZZZZZZZ' }, { cookies: { ttt_sid: d.sid }, redirects: 0, tags: { name: 'saisie' }, responseCallback: http.expectedStatuses(302) });
  check(r, { 'redirection attendue': (x) => x.status === 302 });
}
