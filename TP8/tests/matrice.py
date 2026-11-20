#!/usr/bin/env python3
# Exécute la matrice de tests du TP8 contre la pile de test (port 8081, base tp7-db).
# Les attendus sont lus dans preuves/attendus.tsv, écrits AVANT l'exécution.
# Sortie : preuves/matrice.tsv (attendu, observé, conforme, preuve brute).
import csv
import http.cookiejar
import json
import os
import subprocess
import urllib.error
import urllib.request

RACINE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
B = 'http://localhost:8081'
MDP = 'Demo1234!'

def session():
    jar = http.cookiejar.CookieJar()
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar)), jar

def req(op, methode, chemin, corps=None, jeton=None):
    entetes = {}
    donnees = None
    if corps is not None:
        donnees = json.dumps(corps).encode()
        entetes['Content-Type'] = 'application/json'
    if jeton:
        entetes['X-CSRF-Token'] = jeton
    requete = urllib.request.Request(B + chemin, data=donnees, method=methode, headers=entetes)
    try:
        with op.open(requete) as reponse:
            return reponse.status, reponse.read().decode()
    except urllib.error.HTTPError as erreur:
        return erreur.code, erreur.read().decode()

def jeton(op):
    return json.loads(req(op, 'GET', '/api/csrf')[1])['jeton']

def connecter(email, motDePasse):
    op, jar = session()
    code, _ = req(op, 'POST', '/api/connexion', {'email': email, 'motDePasse': motDePasse})
    return op, jar, code

def sql(requete):
    resultat = subprocess.run(
        ['docker', 'exec', 'tp7-db', 'mariadb', '-uroot', 'stages', '-N', '-e', requete],
        capture_output=True, text=True, check=True)
    return resultat.stdout.strip()

def statut(id_):
    return sql(f'SELECT statut FROM demande_stage WHERE id = {int(id_)}')

def nb_demandes():
    return int(sql('SELECT COUNT(*) FROM demande_stage'))

resultats = {}

def noter(id_, observe, conforme, preuve):
    resultats[id_] = (observe, 'OUI' if conforme else 'NON', preuve)
    print(f'{id_} : {observe} -> {"conforme" if conforme else "NON CONFORME"}')

# Acteurs
resp_op, _, c_resp = connecter('resp.pedago@univ.test', MDP)
aicha_op, _, c_aicha = connecter('aicha.bello@univ.test', MDP)
assert c_resp == 200 and c_aicha == 200, 'connexion de référence impossible'
jr = jeton(resp_op)
ja = jeton(aicha_op)

# T-01 : valider une demande inexistante
code, corps = req(resp_op, 'PATCH', '/api/demandes/999/statut', {'statut': 'VALIDEE'}, jr)
noter('T-01', f'{code} {corps}', code == 404, f'HTTP {code} ; {corps}')

# T-02 : refus sans motif, statut inchangé
avant = statut(2)
code, corps = req(resp_op, 'PATCH', '/api/demandes/2/statut', {'statut': 'REFUSEE'}, jr)
apres = statut(2)
noter('T-02', f'{code} ; statut {apres}', code == 400 and apres == avant == 'SOUMISE',
      f'HTTP {code} ; {corps} ; statut avant {avant}, après {apres}')

# T-03 : validation répétée
code, corps = req(resp_op, 'PATCH', '/api/demandes/3/statut', {'statut': 'VALIDEE'}, jr)
s = statut(3)
noter('T-03', f'{code} ; statut {s}', code == 409 and s == 'VALIDEE', f'HTTP {code} ; {corps} ; statut {s}')

# T-04 : identifiant d'entreprise invalide (remplace le cas « date invalide », sans objet ici)
avant = nb_demandes()
code, corps = req(aicha_op, 'POST', '/api/demandes', {'entrepriseId': 'abc'}, ja)
apres = nb_demandes()
noter('T-04', f'{code} ; lignes {avant} -> {apres}', code == 400 and apres == avant,
      f'HTTP {code} ; {corps}')

# T-05 : lecture du dossier d'un autre étudiant
code, corps = req(aicha_op, 'GET', '/api/demandes/1')
fuite = 'Koffi' in corps or '"demande"' in corps
noter('T-05', f'{code} ; donnée du dossier : {"oui" if fuite else "non"}', code == 403 and not fuite,
      f'HTTP {code} ; {corps}')

# T-06 : injection
code_id, corps_id = req(resp_op, 'GET', '/api/demandes/1%20OR%201=1')
code_nom, corps_nom = req(resp_op, 'GET', '/api/demandes?nom=%27%20OR%20%271%27%3D%271')
resultats_nom = len(json.loads(corps_nom).get('demandes', [])) if code_nom == 200 else -1
noter('T-06', f'identifiant : {code_id} ; recherche : {resultats_nom} résultat(s)',
      code_id == 404 and resultats_nom == 0,
      f'identifiant : HTTP {code_id} ; recherche : HTTP {code_nom}, {resultats_nom} résultat(s)')

# T-07 : mots de passe hachés dans la base
total = int(sql('SELECT COUNT(*) FROM utilisateur'))
hors_format = int(sql('SELECT COUNT(*) FROM utilisateur WHERE LEFT(mot_de_passe_hash, 4) <> "$2y$"'))
noter('T-07', f'{total} comptes ; {hors_format} hachage(s) hors format bcrypt', total > 0 and hors_format == 0,
      f'{total} comptes ; hachages hors $2y$ : {hors_format}')

# T-08 : action sans jeton CSRF
avant = nb_demandes()
code, corps = req(aicha_op, 'POST', '/api/demandes', {'entrepriseId': 2})
apres = nb_demandes()
noter('T-08', f'{code} ; lignes {avant} -> {apres}', code == 403 and apres == avant, f'HTTP {code} ; {corps}')

# T-09 : même message pour un compte inconnu et un mot de passe faux
op, _ = session()
c1, b1 = req(op, 'POST', '/api/connexion', {'email': 'aicha.bello@univ.test', 'motDePasse': 'faux-mot-de-passe'})
c2, b2 = req(op, 'POST', '/api/connexion', {'email': 'inconnu@univ.test', 'motDePasse': 'faux-mot-de-passe'})
noter('T-09', f'{c1} et {c2} ; messages {"identiques" if b1 == b2 else "différents"}',
      c1 == c2 == 401 and b1 == b2, f'mot de passe faux : {c1} {b1} ; compte inconnu : {c2} {b2}')

# T-10 : blocage après cinq échecs
op, _ = session()
codes = [req(op, 'POST', '/api/connexion', {'email': 'secretariat@univ.test', 'motDePasse': 'faux'})[0] for _ in range(6)]
noter('T-10', ','.join(map(str, codes)), codes == [401] * 5 + [429], f'codes successifs : {codes}')

# T-11 : identifiant de session changé à la connexion
op, jar = session()
req(op, 'GET', '/api/sante')
avant = [c.value for c in jar if c.name == 'PHPSESSID']
req(op, 'POST', '/api/connexion', {'email': 'aicha.bello@univ.test', 'motDePasse': MDP})
apres = [c.value for c in jar if c.name == 'PHPSESSID']
changee = bool(avant) and bool(apres) and avant[-1] != apres[-1]
noter('T-11', 'identifiant changé' if changee else 'identifiant inchangé', changee,
      f'avant : {avant[-1][:8] + "…" if avant else "aucun"} ; après : {apres[-1][:8] + "…" if apres else "aucun"}')

# T-12 : l'étudiante tente de valider sa propre demande
code, corps = req(aicha_op, 'PATCH', '/api/demandes/2/statut', {'statut': 'VALIDEE'}, ja)
s = statut(2)
noter('T-12', f'{code} ; statut {s}', code == 403 and s == 'SOUMISE', f'HTTP {code} ; {corps} ; statut {s}')

# T-13 : l'étudiante tente de soumettre le brouillon d'une autre étudiante
code, corps = req(aicha_op, 'PATCH', '/api/demandes/1/statut', {'statut': 'SOUMISE'}, ja)
s = statut(1)
noter('T-13', f'{code} ; statut {s}', code == 403 and s == 'BROUILLON', f'HTTP {code} ; {corps} ; statut {s}')

# Sortie : attendus lus depuis le fichier écrit avant l'exécution
with open(os.path.join(RACINE, 'preuves', 'attendus.tsv'), encoding='utf8') as f:
    attendus = list(csv.DictReader(f, delimiter='\t'))
with open(os.path.join(RACINE, 'preuves', 'matrice.tsv'), 'w', encoding='utf8', newline='') as f:
    ecrivain = csv.writer(f, delimiter='\t')
    ecrivain.writerow(['id', 'type', 'exigence', 'donnees', 'attendu', 'observe', 'conforme', 'preuve'])
    for ligne in attendus:
        observe, conforme, preuve = resultats[ligne['id']]
        ecrivain.writerow([ligne['id'], ligne['type'], ligne['exigence'], ligne['donnees'],
                           ligne['attendu'], observe, conforme, preuve])
print(f"\n{sum(1 for v in resultats.values() if v[1] == 'OUI')}/{len(resultats)} conformes")
