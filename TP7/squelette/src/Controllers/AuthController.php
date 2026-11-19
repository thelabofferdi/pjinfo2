<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\ApiException;
use App\Http\Requete;
use App\Http\Reponse;
use App\Http\Session;
use App\Services\AuthService;

final class AuthController
{
    public function __construct(private AuthService $service)
    {
    }

    public function connecter(): void
    {
        $corps = Requete::corps();
        $email = $corps['email'] ?? null;
        $motDePasse = $corps['motDePasse'] ?? null;
        if (!is_string($email) || !is_string($motDePasse) || $email === '' || $motDePasse === '') {
            throw new ApiException(400, 'Email et motDePasse requis.');
        }
        $adresse = $_SERVER['REMOTE_ADDR'] ?? 'inconnue';
        $resultat = $this->service->connecter($email, $motDePasse, $adresse);
        Reponse::json(200, ['connecte' => true, 'role' => $resultat['role']]);
    }

    public function changerMotDePasse(): void
    {
        $utilisateur = Session::utilisateur();
        $corps = Requete::corps();
        $ancien = $corps['ancien'] ?? null;
        $nouveau = $corps['nouveau'] ?? null;
        if (!is_string($ancien) || !is_string($nouveau)) {
            throw new ApiException(400, 'Ancien et nouveau mot de passe requis.');
        }
        $this->service->changerMotDePasse($utilisateur, $ancien, $nouveau);
        Reponse::json(200, ['modifie' => true]);
    }

    public function jeton(): void
    {
        Reponse::json(200, ['jeton' => Session::jetonCsrf()]);
    }
}
