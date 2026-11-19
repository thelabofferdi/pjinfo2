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

    public function parId(int $id): ?array
    {
        $requete = $this->pdo->prepare('SELECT id, role, mot_de_passe_hash FROM utilisateur WHERE id = :id');
        $requete->execute([':id' => $id]);
        return $requete->fetch() ?: null;
    }

    // Identifiant de la fiche liée au compte : étudiant ou encadreur. Null pour les autres rôles.
    public function ficheIdPour(int $utilisateurId, string $role): ?int
    {
        $table = match ($role) {
            'ETUDIANT' => 'etudiant',
            'ENCADREUR' => 'encadreur',
            default => null,
        };
        if ($table === null) {
            return null;
        }
        $requete = $this->pdo->prepare("SELECT id FROM $table WHERE utilisateur_id = :u");
        $requete->execute([':u' => $utilisateurId]);
        $id = $requete->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    public function majMotDePasse(int $id, string $hash): void
    {
        $requete = $this->pdo->prepare('UPDATE utilisateur SET mot_de_passe_hash = :h WHERE id = :id');
        $requete->execute([':h' => $hash, ':id' => $id]);
    }
}
