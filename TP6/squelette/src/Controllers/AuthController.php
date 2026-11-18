<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\ApiException;
use App\Http\Requete;
use App\Http\Reponse;
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

        $resultat = $this->service->connecter($email, $motDePasse);
        Reponse::json(200, ['connecte' => true, 'role' => $resultat['role']]);
    }
}
