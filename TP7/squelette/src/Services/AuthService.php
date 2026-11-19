<?php
declare(strict_types=1);

namespace App\Services;

use App\Http\ApiException;
use App\Http\LimiteurConnexion;
use App\Http\Session;
use App\Repositories\UtilisateurRepository;

final class AuthService
{
    public function __construct(private UtilisateurRepository $depot)
    {
    }

    public function connecter(string $email, string $motDePasse, string $adresse): array
    {
        $cle = $adresse . '|' . strtolower($email);
        if (LimiteurConnexion::bloque($cle)) {
            throw new ApiException(429, 'Trop de tentatives. Réessayez dans quinze minutes.');
        }

        $utilisateur = $this->depot->parEmail($email);
        // Même message pour un compte inconnu et un mot de passe faux.
        if ($utilisateur === null || !password_verify($motDePasse, $utilisateur['mot_de_passe_hash'])) {
            LimiteurConnexion::echec($cle);
            throw new ApiException(401, 'Identifiants incorrects.');
        }

        LimiteurConnexion::reinitialiser($cle);
        $ficheId = $this->depot->ficheIdPour((int) $utilisateur['id'], $utilisateur['role']);
        Session::ouvrir((int) $utilisateur['id'], $utilisateur['role'], $ficheId);
        return ['role' => $utilisateur['role']];
    }

    public function changerMotDePasse(array $auteur, string $ancien, string $nouveau): void
    {
        if (strlen($nouveau) < 10) {
            throw new ApiException(400, 'Le nouveau mot de passe doit comporter au moins 10 caractères.');
        }
        $utilisateur = $this->depot->parId($auteur['id']);
        if ($utilisateur === null || !password_verify($ancien, $utilisateur['mot_de_passe_hash'])) {
            throw new ApiException(400, 'Ancien mot de passe incorrect.');
        }
        $this->depot->majMotDePasse($auteur['id'], password_hash($nouveau, PASSWORD_DEFAULT));
    }
}
