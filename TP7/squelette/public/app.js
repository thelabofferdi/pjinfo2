// Outils communs aux pages. Les données reçues de l'API sont toujours affichées avec textContent
// (propriété de texte), jamais comme du HTML : une saisie contenant des balises reste du texte.

const MESSAGE_SERVEUR = "Le serveur n'a pas pu traiter la demande. Réessayez dans quelques minutes.";
const MESSAGE_DROITS = "Ce compte n'a pas le droit d'effectuer cette action.";
const LIBELLES_STATUT = { BROUILLON: 'Brouillon', SOUMISE: 'Soumise', VALIDEE: 'Validée', REFUSEE: 'Refusée' };

let jetonCsrf = null;

async function obtenirJeton() {
  if (!jetonCsrf) {
    const reponse = await fetch('api/csrf');
    if (reponse.ok) jetonCsrf = (await reponse.json()).jeton;
  }
  return jetonCsrf;
}

async function appeler(methode, chemin, corps) {
  const options = { method: methode, headers: {} };
  if (methode !== 'GET') {
    const jeton = await obtenirJeton();
    if (jeton) options.headers['X-CSRF-Token'] = jeton;
  }
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
  if (resultat.statut === 403 && !resultat.donnees.erreur) return MESSAGE_DROITS;
  return resultat.donnees.erreur || 'Une erreur est survenue.';
}

function afficher(zone, texte) {
  zone.textContent = texte;
  zone.hidden = texte === '';
}

function allerVersConnexion(message = 'Votre session a expiré. Reconnectez-vous.') {
  sessionStorage.setItem('message-connexion', message);
  window.location.href = 'connexion.html';
}

// En-tête commun : nom de l'application, liens du rôle, mon compte, déconnexion.
function enTete(role) {
  const zone = document.getElementById('entete');
  if (!zone) return;
  const marque = document.createElement('span');
  marque.className = 'marque';
  marque.textContent = 'Gestion des stages';
  const navigation = document.createElement('nav');
  navigation.setAttribute('aria-label', 'Navigation principale');
  const liens = {
    ETUDIANT: [['etudiant.html', 'Mes demandes']],
    RESPONSABLE: [['responsable.html', 'Demandes à traiter']],
    SECRETARIAT: [['secretariat.html', 'Affectations et recherche']],
    ENCADREUR: [['encadreur.html', 'Mes étudiants']],
  };
  if (role) {
    for (const [href, texte] of liens[role] ?? []) {
      const lien = document.createElement('a');
      lien.href = href;
      lien.textContent = texte;
      if (window.location.pathname.endsWith(href)) lien.setAttribute('aria-current', 'page');
      navigation.appendChild(lien);
    }
    const compte = document.createElement('a');
    compte.href = 'compte.html';
    compte.textContent = 'Mon compte';
    if (window.location.pathname.endsWith('compte.html')) compte.setAttribute('aria-current', 'page');
    navigation.appendChild(compte);
  }
  zone.replaceChildren(marque, navigation);
  if (role) {
    const sortie = document.createElement('button');
    sortie.type = 'button';
    sortie.className = 'secondaire';
    sortie.textContent = 'Se déconnecter';
    sortie.addEventListener('click', async () => {
      await appeler('POST', 'api/deconnexion');
      jetonCsrf = null;
      sessionStorage.removeItem('role');
      allerVersConnexion('Vous êtes déconnecté.');
    });
    zone.appendChild(sortie);
  }
}

function personne(prenom, nom) {
  return `${prenom} ${nom}`;
}

function ligneDemande(demande) {
  const li = document.createElement('li');
  li.className = 'carte-demande';
  li.dataset.id = String(demande.id);
  li.dataset.statut = demande.statut;

  const entete = document.createElement('p');
  entete.className = 'entete-demande';
  const numero = document.createElement('strong');
  numero.textContent = `Demande n° ${demande.id}`;
  const badge = document.createElement('span');
  badge.className = `statut statut-${demande.statut}`;
  badge.textContent = LIBELLES_STATUT[demande.statut] ?? demande.statut;
  entete.append(numero, ' ', badge);
  li.appendChild(entete);

  if (demande.etudiant_nom) {
    const etudiant = document.createElement('p');
    etudiant.textContent = `Étudiant : ${personne(demande.etudiant_prenom, demande.etudiant_nom)}`;
    li.appendChild(etudiant);
  }
  if (demande.entreprise_nom) {
    const entreprise = document.createElement('p');
    entreprise.textContent = `Entreprise : ${demande.entreprise_nom} (${demande.entreprise_ville})`;
    li.appendChild(entreprise);
  }
  if (demande.encadreur_nom) {
    const encadreur = document.createElement('p');
    encadreur.textContent = `Encadreur : ${personne(demande.encadreur_prenom, demande.encadreur_nom)}`;
    li.appendChild(encadreur);
  }
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

  const detail = document.createElement('a');
  detail.className = 'lien-action';
  detail.href = `detail.html?id=${demande.id}`;
  detail.textContent = `Voir le détail de la demande n° ${demande.id}`;
  li.appendChild(detail);
  return li;
}

function listeVide(liste, message) {
  const vide = document.createElement('li');
  vide.className = 'carte-demande';
  vide.textContent = message;
  liste.replaceChildren(vide);
}
