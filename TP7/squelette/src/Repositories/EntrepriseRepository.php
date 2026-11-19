<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class EntrepriseRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function lister(): array
    {
        return $this->pdo->query('SELECT id, nom, ville FROM entreprise ORDER BY nom')->fetchAll();
    }
}
