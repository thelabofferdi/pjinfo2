<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Reponse;
use App\Http\Session;
use App\Repositories\EntrepriseRepository;

final class EntrepriseController
{
    public function __construct(private EntrepriseRepository $depot)
    {
    }

    // Accessible à tout utilisateur connecté : la liste sert au formulaire de dépôt.
    public function lister(): void
    {
        Session::utilisateur();
        Reponse::json(200, ['entreprises' => $this->depot->lister()]);
    }
}
