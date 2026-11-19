<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

// Seul point de création de la connexion à la base.
final class Connexion
{
    public static function creer(array $config): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $config['host'],
            $config['port'],
            $config['nom']
        );

        return new PDO($dsn, $config['utilisateur'], $config['mot_de_passe'], [
            // Une erreur SQL lève une exception : elle n'est jamais ignorée silencieusement.
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Vraies requêtes préparées côté serveur MySQL, pas d'émulation.
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 3,
        ]);
    }
}
