<?php
declare(strict_types=1);

namespace App\Repositories;

use Closure;
use PDO;

// Dépôt de la route de santé : la seule couche qui touche la base.
final class SanteRepository
{
    /** @var Closure(): PDO */
    private Closure $fabrique;

    public function __construct(Closure $fabrique)
    {
        $this->fabrique = $fabrique;
    }

    // Renvoie true si la base répond à une requête préparée triviale.
    public function joignable(): bool
    {
        $pdo = ($this->fabrique)();
        $requete = $pdo->prepare('SELECT 1 AS ok');
        $requete->execute();

        return (int) $requete->fetchColumn() === 1;
    }
}
