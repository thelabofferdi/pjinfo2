// Outils communs aux pages. Les données reçues de l'API sont toujours affichées avec textContent,
// (propriété de texte), jamais comme du HTML : une saisie contenant des balises reste du texte.

const MESSAGE_SERVEUR = "Le serveur n'a pas pu traiter la demande. Réessayez dans quelques minutes.";
const MESSAGE_DROITS = "Ce compte n'a pas le droit d'effectuer cette action.";
const LIBELLES_STATUT = { BROUILLON: 'Brouillon', SOUMISE: 'Soumise', VALIDEE: 'Validée', REFUSEE: 'Refusée' };

async function appeler(methode, chemin, corps) {
  const options = { method: methode, headers: {} };
  if (corps !== undefined) {
    options.headers['Content-Type'] = 'application/json';
    options.body = JSON.stringify(corps);
  }
  const reponse = await fetch(chemin, options);
  let donnees = {};
  try {
    donnees = await reponse.json();
  } catch (erreur) {
    donnees = {};
  }
  return { statut: reponse.status, donnees };
}

// Message lisible pour l'utilisateur, selon le code HTTP.
function texteDErreur(resultat) {
  if (resultat.statut === 500) return MESSAGE_SERVEUR;
  if (resultat.statut === 403) return MESSAGE_DROITS;
  return resultat.donnees.erreur || 'Une erreur est survenue.';
}

function afficher(zone, texte) {
  zone.textContent = texte;
  zone.hidden = texte === '';
}

function allerVersConnexion() {
  sessionStorage.setItem('message-connexion', 'Votre session a expiré. Reconnectez-vous.');
  window.location.href = 'connexion.html';
}

function ligneDemande(demande) {
  const li = document.createElement('li');
  li.dataset.id = String(demande.id);
  li.dataset.statut = demande.statut;

  const titre = document.createElement('p');
  titre.textContent = `Demande n° ${demande.id} — `;
  const statut = document.createElement('span');
  statut.className = 'statut';
  statut.textContent = LIBELLES_STATUT[demande.statut] ?? demande.statut;
  titre.appendChild(statut);
  li.appendChild(titre);

  if (demande.date_depot) {
    const depot = document.createElement('p');
    depot.className = 'aide';
    depot.textContent = `Déposée le ${demande.date_depot}`;
    li.appendChild(depot);
  }
  if (demande.motif_refus) {
    const motif = document.createElement('p');
    motif.textContent = `Motif du refus : ${demande.motif_refus}`;
    li.appendChild(motif);
  }
  return li;
}
