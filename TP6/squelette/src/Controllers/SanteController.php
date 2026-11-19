<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Reponse;
use App\Services\SanteService;

// Contrôleur : lit la requête, appelle le service, écrit la réponse. Aucune règle métier ici.
final class SanteController
{
    public function __construct(private SanteService $service)
    {
    }

    public function sante(): void
    {
        $etat = $this->service->etat();

        // 200 si la base répond, 500 sinon : « base injoignable » relève de l'erreur serveur (cours, §6.5).
        Reponse::json($etat['statut'] === 'ok' ? 200 : 500, $etat);
    }
}
