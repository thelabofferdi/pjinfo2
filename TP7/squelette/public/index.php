<?php
declare(strict_types=1);

// Point d'entrée unique : toutes les requêtes passent ici.
require dirname(__DIR__) . '/config/autoload.php';

use App\Controllers\AuthController;
use App\Controllers\DemandeController;
use App\Controllers\SanteController;
use App\Http\Routeur;
use App\Http\Session;
use App\Repositories\Connexion;
use App\Repositories\DemandeRepository;
use App\Repositories\SanteRepository;
use App\Repositories\UtilisateurRepository;
use App\Services\AuthService;
use App\Services\DemandeService;
use App\Services\SanteService;

Session::demarrer();
$config = require dirname(__DIR__) . '/config/config.php';

// Chaque requête ouvre sa connexion : pas d'état partagé entre requêtes.
$fabriquePdo = static fn () => Connexion::creer($config['db']);

$sante = new SanteController(new SanteService(new SanteRepository(static fn () => $fabriquePdo())));
$demandes = static function () use ($fabriquePdo): DemandeController {
    $pdo = $fabriquePdo();
    return new DemandeController(new DemandeService($pdo, new DemandeRepository($pdo)));
};

// Contrat des routes (voir docs/contrat_routes.md).
$routeur = new Routeur();
$routeur->declarer('GET', '/api/sante', fn (array $p) => $sante->sante());
$routeur->declarer('POST', '/api/connexion', function (array $p) use ($fabriquePdo) {
    $service = new AuthService(new UtilisateurRepository($fabriquePdo()));
    (new AuthController($service))->connecter();
});
$routeur->declarer('GET', '/api/demandes', fn (array $p) => $demandes()->lister());
$routeur->declarer('POST', '/api/demandes', fn (array $p) => $demandes()->creer());
$routeur->declarer('GET', '/api/mes-demandes', fn (array $p) => $demandes()->listerMesDemandes());
$routeur->declarer('PATCH', '/api/demandes/{id}/statut', fn (array $p) => $demandes()->changerStatut($p));

$chemin = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$routeur->repondre($_SERVER['REQUEST_METHOD'] ?? 'GET', rtrim($chemin, '/') ?: '/');
