<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\ApiException;
use App\Http\Requete;
use App\Http\Reponse;
use App\Http\Session;
use App\Services\DemandeService;

// Contrôleur : lit la requête, appelle le service, écrit la réponse. Aucune règle métier ici.
final class DemandeController
{
    public function __construct(private DemandeService $service)
    {
    }

    public function lister(): void
    {
        Session::exiger(['RESPONSABLE', 'SECRETARIAT']);
        Reponse::json(200, ['demandes' => $this->service->lister()]);
    }

    public function listerMesDemandes(): void
    {
        $utilisateur = Session::exiger(['ETUDIANT']);
        Reponse::json(200, ['demandes' => $this->service->listerPourEtudiant($utilisateur['etudiant_id'])]);
    }

    public function creer(): void
    {
        $utilisateur = Session::exiger(['ETUDIANT']);
        $corps = Requete::corps();
        $demande = $this->service->creer($utilisateur, $corps['entrepriseId'] ?? null);
        Reponse::json(201, ['demande' => $demande]);
    }

    public function changerStatut(array $parametres): void
    {
        $utilisateur = Session::utilisateur();
        if (!ctype_digit($parametres['id'] ?? '')) {
            throw new ApiException(404, 'Demande introuvable.');
        }
        $corps = Requete::corps();
        $demande = $this->service->changer(
            (int) $parametres['id'],
            $corps['statut'] ?? null,
            $corps['motif'] ?? null,
            $utilisateur
        );
        Reponse::json(200, ['demande' => $demande]);
    }
}
