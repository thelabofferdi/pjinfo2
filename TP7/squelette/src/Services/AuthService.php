<?php
declare(strict_types=1);

namespace App\Services;

use App\Http\ApiException;
use App\Http\Session;
use App\Repositories\UtilisateurRepository;

final class AuthService
{
    public function __construct(private UtilisateurRepository $depot)
    {
    }

    public function connecter(string $email, string $motDePasse): array
    {
        $utilisateur = $this->depot->parEmail($email);
        // Même message pour un compte inconnu et un mot de passe faux.
        if ($utilisateur === null || !password_verify($motDePasse, $utilisateur['mot_de_passe_hash'])) {
            throw new ApiException(401, 'Identifiants incorrects.');
        }

        $etudiantId = $utilisateur['role'] === 'ETUDIANT'
            ? $this->depot->etudiantIdPour((int) $utilisateur['id'])
            : null;
        Session::ouvrir((int) $utilisateur['id'], $utilisateur['role'], $etudiantId);

        return ['role' => $utilisateur['role']];
    }
}
