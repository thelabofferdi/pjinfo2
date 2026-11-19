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
    private const STATUTS = ['BROUILLON', 'SOUMISE', 'VALIDEE', 'REFUSEE'];

    public function __construct(private DemandeService $service)
    {
    }

    public function lister(): void
    {
        Session::exiger(['RESPONSABLE', 'SECRETARIAT']);
        $statut = $_GET['statut'] ?? '';
        $statut = in_array($statut, self::STATUTS, true) ? $statut : null;
        $nom = trim((string) ($_GET['nom'] ?? ''));
        $nom = $nom === '' ? null : mb_substr($nom, 0, 60);
        Reponse::json(200, ['demandes' => $this->service->rechercher($statut, $nom)]);
    }

    public function mesDemandes(): void
    {
        $utilisateur = Session::exiger(['ETUDIANT']);
        Reponse::json(200, ['demandes' => $this->service->listerPourEtudiant($utilisateur['fiche_id'])]);
    }

    public function mesEtudiants(): void
    {
        $utilisateur = Session::exiger(['ENCADREUR']);
        Reponse::json(200, ['demandes' => $this->service->listerPourEncadreur($utilisateur['fiche_id'])]);
    }

    public function detail(array $parametres): void
    {
        $utilisateur = Session::utilisateur();
        if (!ctype_digit($parametres['id'] ?? '')) {
            throw new ApiException(404, 'Demande introuvable.');
        }
        Reponse::json(200, $this->service->detail((int) $parametres['id'], $utilisateur));
    }

    public function creer(): void
    {
        $utilisateur = Session::exiger(['ETUDIANT']);
        $corps = Requete::corps();
        $demande = $this->service->creer(
            $utilisateur,
            $corps['entrepriseId'] ?? null,
            ($corps['brouillon'] ?? false) === true
        );
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

    public function affecter(array $parametres): void
    {
        $utilisateur = Session::exiger(['SECRETARIAT']);
        if (!ctype_digit($parametres['id'] ?? '')) {
            throw new ApiException(404, 'Demande introuvable.');
        }
        $corps = Requete::corps();
        $demande = $this->service->affecter((int) $parametres['id'], $corps['encadreurId'] ?? null, $utilisateur);
        Reponse::json(200, ['demande' => $demande]);
    }
}
