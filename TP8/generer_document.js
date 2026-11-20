// TP8 — Tests et sécurité. Lit preuves/matrice.tsv (sorties réelles de tests/matrice.py).
// Usage : NODE_PATH=<dossier node_modules contenant docx> node generer_document.js <dossier TP8>
const {
  Document, Packer, Paragraph, TextRun, Table, TableRow, TableCell,
  WidthType, BorderStyle, AlignmentType, Footer, Header, PageNumber, VerticalAlign,
} = require("docx");
const fs = require("fs");

const DIR = process.argv[2];
const FONT = "Calibri", BODY = 19, CELL = 15, HEAD = 21, UTIL = 9960;
const GROUPE = "ABDOULAYE Mansouratou – AHOUANSÉ Léa – KOUAGOU Fernandez – YACOUBOU FAISSOL";
const DEPOT = "https://github.com/thelabofferdi/pjinfo2";
const TRAIT = { style: BorderStyle.SINGLE, size: 4, color: "808080" };
const BORDS = { top: TRAIT, bottom: TRAIT, left: TRAIT, right: TRAIT };

const lignes = fs.readFileSync(`${DIR}/preuves/matrice.tsv`, "utf8").trimEnd().split("\n").slice(1)
  .map((l) => l.split("\t"));   // id, type, exigence, donnees, attendu, observe, conforme, preuve
const conformes = lignes.filter((l) => l[6] === "OUI").length;

const p = (t, o = {}) => new Paragraph({
  spacing: { before: 0, after: o.after ?? 50, line: 240 }, alignment: o.align,
  children: [new TextRun({ text: t || " ", bold: o.bold, italics: o.italics, size: o.size ?? BODY, font: FONT })],
});
const h1 = (t) => new Paragraph({
  spacing: { before: 170, after: 70, line: 240 }, keepNext: true,
  children: [new TextRun({ text: t, bold: true, size: HEAD, font: FONT })],
});
const cell = (t, w, gras = false) => new TableCell({
  width: { size: w, type: WidthType.DXA }, borders: BORDS, verticalAlign: VerticalAlign.TOP,
  margins: { top: 30, bottom: 30, left: 60, right: 60 },
  children: [new Paragraph({ spacing: { before: 0, after: 0, line: 220 },
    children: [new TextRun({ text: t || " ", bold: gras, size: CELL, font: FONT })] })],
});
const table = (ws, rows, head) => {
  const sum = ws.reduce((a, b) => a + b, 0);
  const w = ws.map((x) => Math.round((x * UTIL) / sum));
  return new Table({
    width: { size: UTIL, type: WidthType.DXA }, columnWidths: w, layout: "fixed",
    rows: [
      new TableRow({ tableHeader: true, children: head.map((h, i) => cell(h, w[i], true)) }),
      ...rows.map((r) => new TableRow({ cantSplit: true, children: r.map((c, i) => cell(c, w[i])) })),
    ],
  });
};

const doc = new Document({
  styles: { default: { document: { run: { font: FONT, size: BODY }, paragraph: { spacing: { line: 240 } } } } },
  sections: [{
    properties: { page: { margin: { top: 900, bottom: 850, left: 964, right: 964 } } },
    headers: { default: new Header({ children: [new Paragraph({
      spacing: { after: 0, line: 240 },
      border: { bottom: { style: BorderStyle.SINGLE, size: 4, color: "808080", space: 2 } },
      children: [new TextRun({ text: `Gestion des stages — L3 SIL  ·  ${DEPOT}`, size: 15, font: FONT, color: "333333" })],
    })] }) },
    footers: { default: new Footer({ children: [new Paragraph({ alignment: AlignmentType.CENTER,
      children: [new TextRun({ children: ["Page ", PageNumber.CURRENT, " sur ", PageNumber.TOTAL_PAGES], size: 15, font: FONT, color: "555555" })] })] }) },
    children: [
      p("Projet informatique 2 — L3 SIL", { align: AlignmentType.CENTER, size: 17, after: 0 }),
      new Paragraph({ spacing: { before: 20, after: 40 }, alignment: AlignmentType.CENTER,
        children: [new TextRun({ text: "Tests et sécurité", bold: true, size: 26, font: FONT })] }),
      new Paragraph({ spacing: { after: 60 }, alignment: AlignmentType.CENTER,
        children: [new TextRun({ text: "TP8 — 20 novembre 2026", size: 17, font: FONT })] }),
      p(`Groupe : ${GROUPE}`, { align: AlignmentType.CENTER, size: 17, after: 0 }),
      p(`Dépôt Git : ${DEPOT}`, { align: AlignmentType.CENTER, size: 17, after: 140 }),

      h1("1. Objet"),
      p(`Matrice de tests de l'API et de l'interface, exécutée contre MariaDB 11.8 et l'API réelle. ${lignes.length} tests, ${conformes} conformes à l'attendu. `
        + "Les attendus ont été écrits dans preuves/attendus.tsv avant la première exécution ; les résultats ont été relevés ensuite, sans modification des attendus."),

      h1("2. Matrice de tests"),
      p("Chaque ligne relie un test à une exigence du TP2. Les codes HTTP et les états de la base sont ceux relevés pendant l'exécution.", { size: 17, italics: true }),
      table([6, 12, 8, 26, 20, 16, 9],
        lignes.map((l) => [l[0], l[1], l[2], l[3], l[4], l[5], l[6]]),
        ["N°", "Type", "Exigence", "Données", "Attendu", "Observé", "Conforme"]),

      h1("3. Cas limites du cours"),
      p("Le cours demande quatre cas limites. Trois sont tels quels (T-01, T-02, T-03). Le quatrième, « date invalide », ne s'applique pas à ce projet : "
        + "l'application ne reçoit aucune date saisie par l'utilisateur, la programmation de la soutenance étant hors périmètre (TP1). "
        + "Il est remplacé par un identifiant d'entreprise invalide (T-04), qui relève de la même règle : la validation côté serveur (ENF-02)."),

      h1("4. Autorisation et injection"),
      p("Une étudiante ne lit ni ne modifie les dossiers d'une autre étudiante (T-05, T-12, T-13). La chaîne « 1 OR 1=1 » est refusée avant toute requête "
        + "sur l'identifiant (T-06), et la même chaîne dans la recherche par nom ne renvoie aucun résultat : les valeurs sont liées comme paramètres."),

      h1("5. Mots de passe, CSRF et connexion"),
      p("Tous les hachages de la table utilisateur sont au format bcrypt (T-07). Une action qui modifie des données sans jeton CSRF est refusée (T-08). "
        + "Un mauvais mot de passe et un compte inconnu renvoient le même message (T-09). Cinq échecs bloquent les essais (T-10). "
        + "L'identifiant de session change à la connexion, ce qui empêche la fixation de session (T-11)."),

      h1("6. Fiche de revue de code (autre groupe)"),
      p("À remplir lors de l'échange avec un autre groupe. Chaque remarque est classée : acceptée, reportée ou rejetée, avec une justification.", { size: 17, italics: true }),
      table([6, 44, 20, 30], [["1", "", "", ""], ["2", "", "", ""], ["3", "", "", ""], ["4", "", "", ""]],
        ["N°", "Remarque du groupe relecteur", "Classement", "Justification"]),

      h1("7. Justification (preuve de travail)"),
      p("Les attendus ont été écrits avant l'exécution et les tests sont rejouables : tests/matrice.py, après tests/lancer_stack.sh (dossier TP7). "
        + "Le cas « date invalide » est remplacé et la raison est indiquée. La revue de code par un autre groupe reste à faire."),
    ],
  }],
});

Packer.toBuffer(doc).then((buf) => {
  fs.writeFileSync(`${DIR}/tests_securite.docx`, buf);
  console.log("docx écrit");
});
