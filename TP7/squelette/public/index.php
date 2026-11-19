<?php
declare(strict_types=1);

// Point d'entrée unique : toutes les requêtes passent ici.
require dirname(__DIR__) . '/config/autoload.php';

use App\Controllers\AuthController;
use App\Controllers\DemandeController;
use App\Controllers\EncadreurController;
use App\Controllers\EntrepriseController;
use App\Controllers\SanteController;
use App\Http\Reponse;
use App\Http\Routeur;
use App\Http\Session;
use App\Repositories\Connexion;
use App\Repositories\DemandeRepository;
use App\Repositories\EncadreurRepository;
use App\Repositories\EntrepriseRepository;
use App\Repositories\SanteRepository;
use App\Repositories\UtilisateurRepository;
use App\Services\AuthService;
use App\Services\DemandeService;
use App\Services\SanteService;

Session::demarrer();
$config = require dirname(__DIR__) . '/config/config.php';

// Chaque appel ouvre sa connexion : pas d'état partagé entre requêtes.
$pdo = static fn () => Connexion::creer($config['db']);

$routeur = new Routeur();

// Santé
$routeur->declarer('GET', '/api/sante', fn (array $p) => (new SanteController(
    new SanteService(new SanteRepository(static fn () => $pdo()))
))->sante());

// Comptes
$routeur->declarer('GET', '/api/csrf', fn (array $p) => (new AuthController(
    new AuthService(new UtilisateurRepository($pdo()))
))->jeton());
$routeur->declarer('POST', '/api/connexion', fn (array $p) => (new AuthController(
    new AuthService(new UtilisateurRepository($pdo()))
))->connecter());
$routeur->declarer('POST', '/api/deconnexion', function (array $p): void {
    Session::detruire();
    Reponse::json(200, ['deconnecte' => true]);
});
$routeur->declarer('PATCH', '/api/mon-mot-de-passe', fn (array $p) => (new AuthController(
    new AuthService(new UtilisateurRepository($pdo()))
))->changerMotDePasse());

// Référentiels
$routeur->declarer('GET', '/api/entreprises', fn (array $p) => (new EntrepriseController(
    new EntrepriseRepository($pdo())
))->lister());
$routeur->declarer('GET', '/api/encadreurs', fn (array $p) => (new EncadreurController(
    new EncadreurRepository($pdo())
))->lister());

// Demandes
$demandes = static fn () => new DemandeController(new DemandeService(
    $pdo(), new DemandeRepository($pdo()), new EncadreurRepository($pdo())
));
$routeur->declarer('GET', '/api/demandes', fn (array $p) => $demandes()->lister());
$routeur->declarer('POST', '/api/demandes', fn (array $p) => $demandes()->creer());
$routeur->declarer('GET', '/api/demandes/{id}', fn (array $p) => $demandes()->detail($p));
$routeur->declarer('PATCH', '/api/demandes/{id}/statut', fn (array $p) => $demandes()->changerStatut($p));
$routeur->declarer('PATCH', '/api/demandes/{id}/encadreur', fn (array $p) => $demandes()->affecter($p));
$routeur->declarer('GET', '/api/mes-demandes', fn (array $p) => $demandes()->mesDemandes());
$routeur->declarer('GET', '/api/mes-etudiants', fn (array $p) => $demandes()->mesEtudiants());

$chemin = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$routeur->repondre($_SERVER['REQUEST_METHOD'] ?? 'GET', rtrim($chemin, '/') ?: '/');
