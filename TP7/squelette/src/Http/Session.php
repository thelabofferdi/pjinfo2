<?php
declare(strict_types=1);

namespace App\Http;

// Session côté serveur. Les rôles sont contrôlés ici, à chaque requête, jamais dans l'interface.
final class Session
{
    public static function demarrer(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    // Après une connexion réussie : identifiant régénéré (anti fixation) et nouveau jeton CSRF.
    public static function ouvrir(int $utilisateurId, string $role, ?int $ficheId): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $utilisateurId;
        $_SESSION['role'] = $role;
        $_SESSION['fiche_id'] = $ficheId;
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }

    public static function detruire(): void
    {
        $_SESSION = [];
        session_destroy();
    }

    /** @return array{id: int, role: string, fiche_id: ?int} */
    public static function utilisateur(): array
    {
        if (!isset($_SESSION['user_id'])) {
            throw new ApiException(401, 'Authentification requise.');
        }
        return [
            'id' => (int) $_SESSION['user_id'],
            'role' => (string) $_SESSION['role'],
            'fiche_id' => isset($_SESSION['fiche_id']) ? (int) $_SESSION['fiche_id'] : null,
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

    public static function jetonCsrf(): string
    {
        self::utilisateur();
        if (!isset($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(16));
        }
        return $_SESSION['csrf'];
    }

    // Toute action qui modifie des données doit présenter le jeton de la session.
    public static function exigerCsrf(): void
    {
        self::utilisateur();
        $recu = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $recu)) {
            throw new ApiException(403, 'Jeton de sécurité absent ou invalide. Rechargez la page.');
        }
    }
}
