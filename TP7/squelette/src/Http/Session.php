<?php
declare(strict_types=1);

namespace App\Http;

// Session côté serveur. Le contrôle des rôles se fait ici, jamais dans l'interface.
final class Session
{
    public static function demarrer(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    // Après une connexion réussie, l'identifiant de session est régénéré (anti fixation).
    public static function ouvrir(int $utilisateurId, string $role, ?int $etudiantId): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $utilisateurId;
        $_SESSION['role'] = $role;
        $_SESSION['etudiant_id'] = $etudiantId;
    }

    /** @return array{id: int, role: string, etudiant_id: ?int} */
    public static function utilisateur(): array
    {
        if (!isset($_SESSION['user_id'])) {
            throw new ApiException(401, 'Authentification requise.');
        }
        return [
            'id' => (int) $_SESSION['user_id'],
            'role' => (string) $_SESSION['role'],
            'etudiant_id' => isset($_SESSION['etudiant_id']) ? (int) $_SESSION['etudiant_id'] : null,
        ];
    }

    /** @param list<string> $roles */
    public static function exiger(array $roles): array
    {
        $utilisateur = self::utilisateur();
        if (!in_array($utilisateur['role'], $roles, true)) {
            throw new ApiException(403, 'Accès interdit pour ce rôle.');
        }
        return $utilisateur;
    }
}
