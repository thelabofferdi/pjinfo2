<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class EncadreurRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function lister(): array
    {
        return $this->pdo->query(
            'SELECT en.id, u.nom, u.prenom, en.grade
               FROM encadreur en JOIN utilisateur u ON u.id = en.utilisateur_id
              ORDER BY u.nom, u.prenom'
        )->fetchAll();
    }

    public function existe(int $id): bool
    {
        $requete = $this->pdo->prepare('SELECT COUNT(*) FROM encadreur WHERE id = :id');
        $requete->execute([':id' => $id]);
        return (int) $requete->fetchColumn() > 0;
    }
}
