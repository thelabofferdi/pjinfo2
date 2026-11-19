<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class UtilisateurRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function parEmail(string $email): ?array
    {
        $requete = $this->pdo->prepare(
            'SELECT id, role, mot_de_passe_hash FROM utilisateur WHERE email = :email AND actif = 1'
        );
        $requete->execute([':email' => $email]);
        return $requete->fetch() ?: null;
    }

    public function etudiantIdPour(int $utilisateurId): ?int
    {
        $requete = $this->pdo->prepare('SELECT id FROM etudiant WHERE utilisateur_id = :u');
        $requete->execute([':u' => $utilisateurId]);
        $id = $requete->fetchColumn();
        return $id === false ? null : (int) $id;
    }
}
