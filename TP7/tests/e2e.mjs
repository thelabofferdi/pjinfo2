// Scénario de bout en bout de l'interface, joué dans un vrai Chrome via le protocole DevTools.
// Usage (depuis TP7/, pile lancée) : node tests/e2e.mjs
// Sorties : preuves/e2e.tsv et captures PNG dans preuves/.
import { spawn, execSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, readdirSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const PORT = 9333;
const BASE = 'http://localhost:8081';
const DB = 'tp7-db';
const RACINE = process.cwd();
const PUBLIC = join(RACINE, 'squelette', 'public');
const PREUVES = join(RACINE, 'preuves');
mkdirSync(PREUVES, { recursive: true });

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const resultats = [];
function verifier(cas, attendu, obtenu, detail = '') {
  const ok = attendu === obtenu;
  resultats.push({ cas, attendu: String(attendu), obtenu: String(obtenu), conforme: ok ? 'OUI' : 'NON', detail });
  console.log(`${ok ? '[OK]' : '[NON]'} ${cas}${detail ? ' — ' + detail : ''}`);
}

// --- Chrome et protocole DevTools
const chrome = spawn('google-chrome', [
  '--headless=new', '--disable-gpu', '--no-sandbox', '--no-first-run',
  `--remote-debugging-port=${PORT}`, `--user-data-dir=${mkdtempSync(join(tmpdir(), 'tp7-'))}`,
  'about:blank',
], { stdio: 'ignore' });

let cibles = [];
for (let i = 0; i < 60; i++) {
  try {
    cibles = await (await fetch(`http://127.0.0.1:${PORT}/json/list`)).json();
    if (cibles.some((c) => c.type === 'page')) break;
  } catch (erreur) { /* Chrome n'est pas encore prêt */ }
  await sleep(250);
}
const page = cibles.find((c) => c.type === 'page');
const ws = new WebSocket(page.webSocketDebuggerUrl);
await new Promise((r) => { ws.onopen = r; });
let compteur = 0;
const attentes = new Map();
ws.onmessage = (message) => {
  const data = JSON.parse(message.data);
  if (data.id && attentes.has(data.id)) { attentes.get(data.id)(data); attentes.delete(data.id); }
};
function cdp(method, params = {}) {
  const id = ++compteur;
  ws.send(JSON.stringify({ id, method, params }));
  return new Promise((r) => attentes.set(id, r));
}
async function evaluer(expression) {
  const r = await cdp('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
  return r.result?.result?.value;
}
async function attendre(expression, delai = 10000) {
  const fin = Date.now() + delai;
  while (Date.now() < fin) {
    if (await evaluer(expression)) return true;
    await sleep(150);
  }
  return false;
}
const js = (s) => JSON.stringify(s);
const texte = (sel) => evaluer(`document.querySelector(${js(sel)})?.textContent ?? ''`);
const remplir = (sel, valeur) => evaluer(`(() => { const e = document.querySelector(${js(sel)}); e.value = ${js(valeur)}; e.dispatchEvent(new Event('input')); return true; })()`);
const soumettre = (sel) => evaluer(`document.querySelector(${js(sel)}).requestSubmit()`);
const cliquer = (sel) => evaluer(`document.querySelector(${js(sel)}).click()`);
const chemin = () => evaluer('location.pathname');
async function aller(url) {
  await cdp('Page.navigate', { url: BASE + url });
  await sleep(400);
  await attendre("document.readyState === 'complete'");
}
async function capturer(nom) {
  const r = await cdp('Page.captureScreenshot', { format: 'png' });
  writeFileSync(join(PREUVES, nom), Buffer.from(r.result.data, 'base64'));
}
function attendreBase() {
  for (let i = 0; i < 60; i++) {
    try {
      execSync(`docker exec ${DB} mariadb -uroot -e 'SELECT 1'`, { stdio: 'ignore' });
      return;
    } catch (erreur) { /* pas encore prête */ }
    execSync('sleep 2');
  }
  throw new Error('base non prête');
}

const XSS = '<img src=x onerror="window.__xss=1">';

try {
  await cdp('Page.enable');
  await cdp('Runtime.enable');
  await cdp('Network.clearBrowserCookies');
  await cdp('Emulation.setDeviceMetricsOverride', { width: 900, height: 760, deviceScaleFactor: 1, mobile: false });

  // T1 : page protégée sans session -> redirection et message
  await aller('/etudiant.html');
  await attendre("location.pathname.endsWith('connexion.html')");
  await attendre("!document.getElementById('message-session').hidden");
  const msgSession = await texte('#message-session');
  verifier('T1 Page étudiant sans session : redirection vers la connexion avec message (401)',
    true, (await chemin()).endsWith('connexion.html') && msgSession.includes('session'), msgSession);
  await capturer('capture_connexion_session_expiree.png');

  // T2 : connexion de l'étudiante
  await remplir('#email', 'aicha.bello@univ.test');
  await remplir('#motDePasse', 'Demo1234!');
  await soumettre('#formulaire-connexion');
  const connecte = await attendre("location.pathname.endsWith('etudiant.html')");
  await attendre("document.querySelector('#liste-demandes') !== null");
  verifier('T2 Connexion de l\'étudiante et arrivée sur sa page', true, connecte);

  // T3 : entreprise inconnue -> 400 affiché près du champ
  await remplir('#entrepriseId', '999');
  await soumettre('#formulaire-depot');
  await attendre("!document.getElementById('erreur-entreprise').hidden");
  const e400 = await texte('#erreur-entreprise');
  verifier('T3 Entreprise inconnue : erreur 400 affichée près du champ', true, e400.includes('Entreprise inconnue'), e400);
  await capturer('capture_etudiant_erreur_400.png');

  // T4 : deux dépôts réussis (201), lus dans la confirmation
  await remplir('#entrepriseId', '2');
  await soumettre('#formulaire-depot');
  await attendre("/déposée/.test(document.getElementById('confirmation').textContent)");
  const confA = await texte('#confirmation');
  const idA = Number(confA.match(/n° (\d+)/)[1]);
  await remplir('#entrepriseId', '2');
  await soumettre('#formulaire-depot');
  await attendre(`document.getElementById('confirmation').textContent !== ${js(confA)} && /déposée/.test(document.getElementById('confirmation').textContent)`);
  const idB = Number((await texte('#confirmation')).match(/n° (\d+)/)[1]);
  await attendre("document.querySelectorAll('#liste-demandes li').length >= 2");
  verifier('T4 Dépôt de deux demandes (201) et affichage dans la liste',
    true, idA !== idB && (await evaluer("document.querySelectorAll('#liste-demandes li').length")) >= 2, `demandes ${idA} et ${idB}`);
  await capturer('capture_etudiant_liste.png');

  const etiquettes = await evaluer(`[...document.querySelectorAll('input,select,textarea')].filter(e => !document.querySelector('label[for="' + e.id + '"]')).map(e => e.id)`);
  verifier('T5 Formulaire accessible : chaque champ a une étiquette liée (for/id)', true, etiquettes.length === 0, etiquettes.length ? 'sans étiquette : ' + etiquettes.join(',') : 'tous étiquetés');

  // T6 : base injoignable -> 500 affiché sous forme de message neutre
  execSync(`docker stop ${DB}`);
  await remplir('#entrepriseId', '2');
  await soumettre('#formulaire-depot');
  await attendre("!document.getElementById('erreur-generale').hidden");
  const e500 = await texte('#erreur-generale');
  execSync(`docker start ${DB}`);
  attendreBase();
  verifier('T6 Base injoignable : message neutre affiché (500), sans détail technique',
    true, e500.includes('pas pu traiter') && !/SQLSTATE|PDO|tp7-db/i.test(e500), e500);
  await capturer('capture_etudiant_erreur_500.png');

  // T7 : connexion du responsable
  await aller('/connexion.html');
  await remplir('#email', 'resp.pedago@univ.test');
  await remplir('#motDePasse', 'Demo1234!');
  await soumettre('#formulaire-connexion');
  const responsable = await attendre("location.pathname.endsWith('responsable.html')");
  await attendre("document.querySelector('#liste-demandes li') !== null");
  const labelsResp = await evaluer(`[...document.querySelectorAll('input,select,textarea')].filter(e => !document.querySelector('label[for="' + e.id + '"]')).length === 0`);
  verifier('T7 Connexion du responsable et étiquettes liées sur sa page', true, responsable && labelsResp);

  // T9 : retrouver la demande par filtre, puis la valider (200)
  await evaluer(`(() => { const s = document.getElementById('filtre-statut'); s.value = 'SOUMISE'; s.dispatchEvent(new Event('change')); })()`);
  const trouvee = await evaluer(`!!document.querySelector('#liste-demandes li[data-id="${idA}"]')`);
  verifier('T8 Retrouver la demande par filtre (statut Soumise)', true, trouvee);
  await cliquer(`button[data-action="valider"][data-id="${idA}"]`);
  await attendre(`/validée/.test(document.getElementById('confirmation').textContent)`);
  verifier('T9 Validation : Soumise vers Validée (200)', true, /validée/.test(await texte('#confirmation')));

  // T11 : refus sans motif -> 400 affiché près du champ motif
  await evaluer(`(() => { const s = document.getElementById('filtre-statut'); s.value = ''; s.dispatchEvent(new Event('change')); })()`);
  await attendre(`!!document.querySelector('#liste-demandes li[data-id="${idB}"]')`);
  await cliquer(`button[data-action="refuser"][data-id="${idB}"]`);
  await attendre(`!document.getElementById('erreur-motif-${idB}').hidden`);
  const e400motif = await texte(`#erreur-motif-${idB}`);
  verifier('T10 Refus sans motif : erreur 400 affichée près du champ motif', true, e400motif.includes('Motif de refus obligatoire'), e400motif);
  await capturer('capture_responsable_erreur_400.png');

  // T12 : refus motivé avec une saisie contenant du HTML
  await remplir(`#motif-${idB}`, XSS);
  await cliquer(`button[data-action="refuser"][data-id="${idB}"]`);
  await attendre(`/refusée/.test(document.querySelector('#liste-demandes li[data-id="${idB}"]')?.textContent ?? '')`);
  const surResp = await evaluer(`({
    xss: window.__xss === undefined,
    images: document.querySelectorAll('img').length,
    texte: document.querySelector('#liste-demandes li[data-id="${idB}"]')?.textContent.includes('${XSS.replace(/"/g, '\\"')}') ?? false
  })`);
  verifier('T11 Refus motivé : la saisie HTML reste du texte (aucune exécution, aucune image créée)',
    true, surResp.xss && surResp.images === 0 && surResp.texte, JSON.stringify(surResp));
  await capturer('capture_responsable_apres_refus.png');

  // T13 : persistance après rafraîchissement
  await cdp('Page.reload');
  await sleep(500);
  await attendre("document.readyState === 'complete'");
  await attendre(`!!document.querySelector('#liste-demandes li[data-id="${idA}"]')`);
  await evaluer(`(() => { const s = document.getElementById('filtre-statut'); s.value = ''; s.dispatchEvent(new Event('change')); })()`);
  const persiste = await evaluer(`document.querySelector('#liste-demandes li[data-id="${idA}"]')?.dataset.statut === 'VALIDEE' && document.querySelector('#liste-demandes li[data-id="${idB}"]')?.dataset.statut === 'REFUSEE'`);
  verifier('T12 Après rafraîchissement : statuts Validée et Refusée conservés en base', true, persiste);

  // T14 : l'étudiante voit le motif en texte, sans exécution
  await aller('/connexion.html');
  await remplir('#email', 'aicha.bello@univ.test');
  await remplir('#motDePasse', 'Demo1234!');
  await soumettre('#formulaire-connexion');
  await attendre("location.pathname.endsWith('etudiant.html')");
  await attendre(`!!document.querySelector('#liste-demandes li[data-id="${idB}"]')`);
  const surEtu = await evaluer(`({
    xss: window.__xss === undefined,
    images: document.querySelectorAll('img').length,
    texte: document.querySelector('#liste-demandes li[data-id="${idB}"]')?.textContent.includes('${XSS.replace(/"/g, '\\"')}') ?? false
  })`);
  verifier('T13 Côté étudiante : motif affiché comme texte, aucune exécution', true, surEtu.xss && surEtu.images === 0 && surEtu.texte, JSON.stringify(surEtu));
  await capturer('capture_etudiant_xss_texte.png');

  // T15 : droits : l'étudiante ne peut pas lire la liste du personnel
  const statutListe = await evaluer("fetch('api/demandes').then(r => r.status)");
  verifier('T14 Étudiante sur la liste du personnel : 403', 403, statutListe);

  // T15 à T18 : parcours de l'étudiante (brouillon, soumission, renvoi après refus, protection CSRF)
  await aller('/etudiant.html');
  await attendre("document.querySelectorAll('#liste-entreprises option').length >= 2");
  await cliquer('#brouillon');
  await remplir('#entrepriseId', '1');
  await soumettre('#formulaire-depot');
  await attendre("/brouillon/.test(document.getElementById('confirmation').textContent)");
  const idC = Number((await texte('#confirmation')).match(/n° (\d+)/)[1]);
  const statutBrouillon = await evaluer(`document.querySelector('#liste-demandes li[data-id="${idC}"]')?.dataset.statut`);
  verifier('T15 Enregistrement en brouillon (statut Brouillon)', 'BROUILLON', statutBrouillon);

  await cliquer(`button[data-action="soumettre"][data-id="${idC}"]`);
  await attendre(`document.querySelector('#liste-demandes li[data-id="${idC}"]')?.dataset.statut === 'SOUMISE'`);
  verifier('T16 Soumission du brouillon : statut Soumise', 'SOUMISE',
    await evaluer(`document.querySelector('#liste-demandes li[data-id="${idC}"]')?.dataset.statut`));

  await attendre(`document.querySelector('#liste-demandes li[data-id="${idB}"]')?.dataset.statut === 'REFUSEE'`);
  await cliquer(`button[data-action="renvoyer"][data-id="${idB}"]`);
  await attendre(`document.querySelector('#liste-demandes li[data-id="${idB}"]')?.dataset.statut === 'SOUMISE'`);
  const motifRetire = !(await texte(`#liste-demandes li[data-id="${idB}"]`)).includes('Motif du refus');
  verifier('T17 Renvoi d\'une demande refusée : Refusée, Brouillon, Soumise, motif retiré', true, motifRetire);

  const sansJeton = await evaluer("fetch('api/demandes', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({entrepriseId: 1})}).then(r => r.status)");
  verifier('T18 Action modifiant les données sans jeton CSRF : refusée (403)', 403, sansJeton);

  // T19 : déconnexion depuis l'en-tête : retour à la connexion, puis session refusée
  await cliquer('#entete button');
  const deco = await attendre("location.pathname.endsWith('connexion.html')");
  const apresDeco = await evaluer("fetch('api/mes-demandes').then(r => r.status)");
  verifier('T19 Déconnexion : retour à la connexion et session refusée ensuite (401)', true, deco && apresDeco === 401, `statut après déconnexion : ${apresDeco}`);

  // T20 à T26 : secrétariat, encadreur, mon compte, limitation des connexions
  await aller('/connexion.html');
  await remplir('#email', 'secretariat@univ.test');
  await remplir('#motDePasse', 'Demo1234!');
  await soumettre('#formulaire-connexion');
  await attendre("location.pathname.endsWith('secretariat.html')");
  await evaluer(`(() => { document.getElementById('filtre-statut').value = 'VALIDEE'; document.getElementById('formulaire-recherche').requestSubmit(); })()`);
  await attendre(`!!document.querySelector('#liste-demandes li[data-id="${idA}"]') && !!document.getElementById('encadreur-${idA}')`);
  await evaluer(`(() => { document.getElementById('encadreur-${idA}').value = '2'; })()`);
  await cliquer(`button[data-action="affecter"][data-id="${idA}"]`);
  await attendre("/affecté/.test(document.getElementById('confirmation').textContent)");
  verifier('T20 Affectation d\'un encadreur à une demande validée (200)', true, /affecté/.test(await texte('#confirmation')));
  await capturer('capture_secretariat_affectation.png');

  await evaluer(`(() => { document.getElementById('filtre-statut').value = ''; document.getElementById('recherche-nom').value = 'bello'; document.getElementById('formulaire-recherche').requestSubmit(); })()`);
  await attendre("!document.getElementById('liste-demandes').textContent.includes('DOSSOU')");
  const parNom = await evaluer(`(() => { const lignes = [...document.querySelectorAll('#liste-demandes li[data-id]')]; return lignes.length > 0 && lignes.every((li) => li.textContent.includes('BELLO')); })()`);
  verifier('T21 Recherche par nom d\'étudiant : seules les demandes correspondantes', true, parNom);

  await aller(`/detail.html?id=${idA}`);
  await attendre("document.querySelectorAll('#historique li').length >= 3");
  const nbEvenements = await evaluer("document.querySelectorAll('#historique li').length");
  verifier('T22 Détail d\'une demande avec son historique (au moins 3 événements)', true, nbEvenements >= 3, `${nbEvenements} événements`);
  await capturer('capture_detail_historique.png');

  await cliquer('#entete button');
  await attendre("location.pathname.endsWith('connexion.html')");
  await remplir('#email', 'paul.sossou@univ.test');
  await remplir('#motDePasse', 'Demo1234!');
  await soumettre('#formulaire-connexion');
  await attendre("location.pathname.endsWith('encadreur.html')");
  const dansMesEtudiants = await attendre(`!!document.querySelector('#liste-demandes li[data-id="${idA}"]')`);
  verifier('T23 Encadreur : la demande affectée apparaît dans « Mes étudiants »', true, dansMesEtudiants);

  await aller('/compte.html');
  await remplir('#ancien', 'Demo1234!');
  await remplir('#nouveau', 'court');
  await soumettre('#formulaire-mot-de-passe');
  await attendre("/au moins 10/.test(document.getElementById('erreur-generale').textContent)");
  verifier('T24 Mot de passe trop court : refusé (400)', true, true, await texte('#erreur-generale'));

  await remplir('#ancien', 'faux-mot-de-passe');
  await remplir('#nouveau', 'NouveauMotDePasse1');
  await soumettre('#formulaire-mot-de-passe');
  await attendre("/Ancien mot de passe incorrect/.test(document.getElementById('erreur-generale').textContent)");
  verifier('T25 Mot de passe actuel erroné : refusé (400)', true, true, await texte('#erreur-generale'));

  await aller('/connexion.html');
  const essais = await evaluer(`(async () => { const codes = []; for (let i = 0; i < 6; i++) { const r = await fetch('api/connexion', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ email: 'admin@univ.test', motDePasse: 'faux' }) }); codes.push(r.status); } return codes.join(','); })()`);
  verifier('T26 Cinq échecs de connexion puis blocage (429)', '401,401,401,401,401,429', essais);

  // T27 : aucune insertion de HTML brut dans le code de l'interface
  const fichiers = readdirSync(PUBLIC).filter((f) => /\.(html|js)$/.test(f));
  const risques = fichiers.flatMap((f) => (readFileSync(join(PUBLIC, f), 'utf8').match(/innerHTML|insertAdjacentHTML|document\.write/g) || []));
  verifier('T27 Code de l\'interface : aucun innerHTML, insertAdjacentHTML ni document.write', 0, risques.length, fichiers.join(', '));
} catch (erreur) {
  verifier('Exécution du scénario sans erreur technique', true, false, erreur.message);
} finally {
  ws.close();
  chrome.kill('SIGTERM');
  const entete = 'numero\tcas\tattendu\tobtenu\tconforme\tdetail';
  const lignes = resultats.map((r, i) => [i + 1, r.cas, r.attendu, r.obtenu, r.conforme, r.detail.replace(/\s+/g, ' ')].join('\t'));
  writeFileSync(join(PREUVES, 'e2e.tsv'), [entete, ...lignes].join('\n') + '\n');
  const ok = resultats.filter((r) => r.conforme === 'OUI').length;
  console.log(`\n${ok}/${resultats.length} cas conformes`);
}
