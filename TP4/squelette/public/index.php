<?php
declare(strict_types=1);

// Point d'entrée unique : toutes les requêtes passent ici.
require dirname(__DIR__) . '/config/autoload.php';

use App\Controllers\SanteController;
use App\Http\Routeur;
use App\Repositories\Connexion;
use App\Repositories\SanteRepository;
use App\Services\SanteService;

$config = require dirname(__DIR__) . '/config/config.php';

// Assemblage des couches : dépôt -> service -> contrôleur.
$depot = new SanteRepository(static fn () => Connexion::creer($config['db']));
$controleur = new SanteController(new SanteService($depot));

// Contrat des routes (voir docs/contrat_routes.md).
$routeur = new Routeur();
$routeur->declarer('GET', '/api/sante', [$controleur, 'sante']);
$routeur->declarer('GET', '/api/demandes', null);
$routeur->declarer('POST', '/api/demandes', null);
$routeur->declarer('PATCH', '/api/demandes/{id}/statut', null);

$chemin = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$routeur->repondre($_SERVER['REQUEST_METHOD'] ?? 'GET', rtrim($chemin, '/') ?: '/');
