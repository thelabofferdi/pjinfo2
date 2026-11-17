# Reproduire ce travail sur son propre projet

Un seul fichier, toutes les commandes dedans. Objectif : qu'un autre groupe produise les
mêmes livrables (TP1 → TP10) sur son propre sujet, sans refaire les erreurs qui ont coûté
des allers-retours ici.

Le dépôt sert de **modèle** : on le clone, on vide son historique, on remplace les noms et
on adapte les contenus.

---

## 1. Ce qu'il faut sur le poste

| Outil | À quoi il sert | Vérification |
|---|---|---|
| `git` + `gh` authentifié | dépôt local et distant | `gh auth status` |
| `node` + paquet `docx` | générer les `.docx` par script | `node -e "require('docx')"` |
| Google Chrome | rendre un `.html` en `.pdf` | `google-chrome --version` |
| `java` | exécuter PlantUML (diagrammes) | `java -version` |
| `graphviz` (`dot`) | disposition des diagrammes | `dot -V` |
| `docker` | MariaDB jetable pour les scripts SQL | `docker info` |

**LibreOffice n'est pas nécessaire**, mais son absence a une conséquence : on ne peut pas
convertir un `.docx` en PDF en local. Le PDF de contrôle est donc produit depuis une source
HTML (§4.2), et le `.docx` reste le livrable de référence.

---

## 2. Créer son dépôt

```bash
git init -b main

printf '.env\nvendor/\nnode_modules/\n*.log\n' > .gitignore

# un .docx ou un .pdf ne fusionne pas : on le déclare binaire
printf '*.docx binary\n*.doc binary\n*.pdf binary\n' > .gitattributes

gh repo create <nom-du-depot> --public --source=. --remote=origin --push
```

Une branche courte par travail pratique, fusion relue, tag de fin :

```bash
git switch -c tp1
git add TP1/
git commit -m "Ajoute la fiche de cadrage (TP1)"
git push -u origin tp1

git switch main
git merge --no-ff tp1 -m "Fusionne tp1 : fiche de cadrage"
git tag -a tp1-v1.0 -m "TP1 livré"
git push && git push origin tp1-v1.0
```

Messages de commit : à l'impératif, courts, et qui disent **ce qui change**
(« Ajoute… », « Corrige… », « Fusionne… »).

---

## 3. Structure conseillée

```
README.md            présentation, membres, périmètre, avancement
GUIDE.md             ce fichier
.gitignore .gitattributes
TP1/ … TP10/         un dossier par travail pratique
```

---

## 4. Produire les livrables

### 4.1 Le document Word (`.docx`)

Généré par script avec le paquet npm `docx` : le contenu est dans des tableaux JavaScript,
donc corriger une ligne se fait dans le code et le document se régénère à l'identique.

```bash
mkdir -p ~/gen && cd ~/gen && npm install docx      # une seule fois par poste
node mon-generateur.js ../TP1/fiche.docx
```

Squelette minimal (les fonctions `p`, `tableau`, `image` se recopient du dépôt) :

```js
const { Document, Packer, Paragraph, TextRun } = require("docx");
const fs = require("fs");

const doc = new Document({
  sections: [{
    properties: { page: { margin: { top: 900, bottom: 850, left: 964, right: 964 } } },
    children: [new Paragraph({ children: [new TextRun({ text: "Bonjour", bold: true })] })],
  }],
});

Packer.toBuffer(doc).then((buf) => fs.writeFileSync(process.argv[2], buf));
```

Réglages qui tiennent sur **2 pages A4** (format demandé pour la fiche de cadrage) :

| Réglage | Valeur |
|---|---|
| Marges | 1,7 cm (≈ 964 twips) |
| Corps | Calibri 9 pt (taille 19 en demi-points) |
| Tableaux | 7,5 à 8 pt (16) |
| Titres de section | 10,5 pt (21), gras |
| Interligne | 240 (simple) |

> Piège : un tableau a besoin de **deux largeurs** — `columnWidths` sur le tableau **et**
> `width` sur chaque cellule, les deux en `WidthType.DXA`. Sinon Word casse la mise en page.

### 4.2 Le PDF de contrôle

```bash
google-chrome --headless --disable-gpu --no-sandbox --no-pdf-header-footer \
  --print-to-pdf=livrable.pdf livrable.html
pdfinfo livrable.pdf | grep Pages
```

La source HTML reprend le même contenu, avec `@page { size: A4; margin: 17mm 18mm; }`.
Elle sert à **vérifier** pagination et lisibilité — pas à remplacer le `.docx`.

Pour compter les lignes d'un paragraphe (une consigne « cinq lignes » veut dire cinq lignes
rendues) :

```bash
pdftotext -layout livrable.pdf - | sed -n "/1. Problème/,/2. Indicateurs/p"
```

### 4.3 Les diagrammes UML

PlantUML transforme un fichier texte en image : le diagramme est versionné et corrigeable.

```bash
# une seule fois : télécharger le jar (22 Mo)
curl -fsSL -o plantuml.jar \
  https://repo1.maven.org/maven2/net/sourceforge/plantuml/plantuml/1.2024.7/plantuml-1.2024.7.jar

# à chaque modification d'un .puml
java -jar plantuml.jar -tpng -charset UTF-8 TP3/cas_usage.puml TP3/classes.puml
```

Réglages retenus ici, après plusieurs essais :

```
skinparam monochrome true          ' sobre, pas de couleurs
skinparam shadowing false
skinparam linetype polyline        ' traits droits, pas de courbes
skinparam nodesep 80               ' aère les étiquettes de cardinalité
hide circle
hide empty members
```

- **Cardinalités des deux côtés** de chaque association (`"1" -- "0..*"`), sinon la lecture
  est incomplète.
- Un `1—0..1` devient une clé étrangère **UNIQUE** du côté « plusieurs » ; un `1—0..*` une
  simple FK.
- Les classes du diagramme doivent être **exactement** celles demandées par la consigne.

### 4.4 Le schéma SQL et sa vérification

Base jetable dans Docker, scripts exécutés dans l'ordre, sortie conservée comme preuve :

```bash
docker run -d --name tp-db -e MARIADB_ALLOW_EMPTY_ROOT_PASSWORD=yes mariadb:11

# l'image démarre un serveur temporaire pour l'initialisation, puis le relance :
# attendre deux connexions réussies espacées de quelques secondes
for i in $(seq 1 30); do
  docker exec tp-db mariadb -uroot -e 'SELECT 1' >/dev/null 2>&1 && sleep 3 \
    && docker exec tp-db mariadb -uroot -e 'SELECT 1' >/dev/null 2>&1 && break
  sleep 2
done

docker exec tp-db mariadb -uroot -e "CREATE DATABASE projet CHARACTER SET utf8mb4"
docker exec -i tp-db mariadb -uroot projet < TP3/001_schema.sql
docker exec -i tp-db mariadb -uroot projet < TP3/002_seed.sql
docker exec -i tp-db mariadb -uroot --table projet < TP3/003_verification.sql | tee sortie.txt

docker rm -f tp-db        # la base n'est jamais conservée
```

| Fichier | Contenu |
|---|---|
| `001_schema.sql` | `DROP` dans l'ordre inverse des dépendances, puis `CREATE TABLE` (PK, FK, UNIQUE, CHECK), vues |
| `002_seed.sql` | jeu d'essai **fictif**, ids explicites pour pouvoir référencer |
| `003_verification.sql` | `COUNT(*)` par table, répartition par statut, indicateurs |

Tester aussi les refus : une insertion invalide doit être rejetée par la base, et le message
d'erreur (`1062` doublon, `1452` clé étrangère, `4025` contrainte CHECK) est une preuve
utile à citer dans le document.

---

## 5. Les règles de rédaction qui évitent les retours

1. **Ne jamais inventer un chiffre.** Un indicateur non mesuré s'écrit « à relever », avec le
   protocole (source, échantillon, auteur, date). Un chiffre inventé se voit à l'oral.
2. **Respecter la consigne à la lettre** : « trois acteurs » = trois, « cinq classes » = cinq.
   Ce n'est pas le moment d'innover.
3. **Un nombre de lignes demandé est un nombre de lignes physiques** : on vérifie sur le PDF,
   pas à l'œil.
4. **Une exigence sans test n'est pas livrée** ; un test sans preuve non plus. Tant qu'aucun
   test n'a tourné, le statut est « à faire ».
5. **Pas de faux écran de terminal** : les résultats SQL se présentent en tableaux, et la
   commande qui les reproduit est indiquée.
6. **Cohérence entre documents** : même périmètre, mêmes noms, mêmes cardinalités dans le
   diagramme et dans le SQL.

---

## 6. Ce qu'il faut produire, TP par TP

| TP | Livrable | Preuve attendue |
|---|---|---|
| 1 | Fiche de cadrage (2 pages) | relecture par un autre groupe |
| 2 | 8 EF + 3 ENF + 2 user stories + matrice de traçabilité | un test relié à chaque Must |
| 3 | Diagrammes UML + `001_schema.sql` + `002_seed.sql` | sortie des `COUNT(*)` |
| 4 | Arborescence, contrat des routes, `GET /api/sante`, ADR-001 | la route répond 200 |
| 5 | Dépôt Git, Kanban, historique | `git log --oneline --graph` |
| 6 | API des demandes (dépôts, service, transactions) | 6 codes HTTP testés |
| 7 | Interface + intégration `fetch` | scénario de bout en bout |
| 8 | Matrice de tests + revue de code | sorties de test, 403 |
| 9 | README d'installation, sauvegarde restaurée | installation sur un 2ᵉ poste |
| 10 | Dossier de livraison + démonstration | liste de contrôle, rétrospective |

---

## 7. Repartir de ce dépôt

```bash
git clone https://github.com/thelabofferdi/pjinfo2.git mon-projet
cd mon-projet
rm -rf .git                    # historique neuf
git init -b main

# remplacer l'URL du dépôt et les noms du groupe partout
grep -rl "thelabofferdi/pjinfo2" . | xargs sed -i 's|thelabofferdi/pjinfo2|<compte>/<depot>|g'
grep -rl "ABDOULAYE\|KOUAGOU\|YACOUBOU\|AHOUANSÉ" . \
  | xargs sed -i 's|ABDOULAYE Mansouratou|NOM1 Prénom1|g; s|AHOUANSÉ Léa|NOM2 Prénom2|g; s|KOUAGOU Fernandez|NOM3 Prénom3|g; s|YACOUBOU FAISSOL|NOM4 Prénom4|g'
```

Les `.docx` générés ne se modifient pas au `sed` : il faut régénérer les documents après
avoir changé les noms dans le générateur (les noms y figurent dans une constante).

Puis, dans chaque dossier `TPn/` : adapter les tableaux de données en tête du générateur
(`const EF = [...]`, `const MULTI = [...]`, …), régénérer, relire le PDF.

---

## 8. Pièges déjà rencontrés (et leurs solutions)

| Symptôme | Cause | Solution |
|---|---|---|
| `Cannot find module 'docx'` | paquet non installé | `npm install docx` |
| `.docx` impossible à convertir en PDF | LibreOffice absent | produire le PDF depuis la source HTML |
| Diagramme illisible, traits courbes | réglages par défaut | `linetype polyline`, `monochrome`, `nodesep` |
| Étiquette `0..*` barrée par un trait | boîtes trop proches | augmenter `nodesep` / `ranksep` |
| `docker pull` échoue (reset réseau) | incident réseau | relancer : l'image reste en cache |
| `Can't connect to local server through socket` | MariaDB redémarre après l'initialisation | attendre deux connexions réussies (voir §4.4) |
| Vue SQL : `Unknown column 'e.nom'` | colonne cherchée dans la mauvaise table | vérifier les jointures (les noms sont dans `utilisateur`) |
| Fusion impossible sur un `.docx` | fichier binaire | `.gitattributes` + une seule personne l'édite à la fois |
