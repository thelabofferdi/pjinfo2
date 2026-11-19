<?php
declare(strict_types=1);

// Test de connexion : ouvre la connexion PDO et exécute une requête.
// Usage : php tests/test_connexion.php   (code retour 0 = réussi, 1 = échec)

require dirname(__DIR__) . '/config/autoload.php';
$config = require dirname(__DIR__) . '/config/config.php';

use App\Repositories\Connexion;

try {
    $pdo = Connexion::creer($config['db']);
    $ok = (int) $pdo->query('SELECT 1')->fetchColumn() === 1;
    echo $ok
        ? "OK : connexion PDO établie et requête SELECT 1 réussie.\n"
        : "ECHEC : réponse inattendue de la base.\n";
    exit($ok ? 0 : 1);
} catch (Throwable $e) {
    fwrite(STDERR, 'ECHEC : ' . $e->getMessage() . "\n");
    exit(1);
}
