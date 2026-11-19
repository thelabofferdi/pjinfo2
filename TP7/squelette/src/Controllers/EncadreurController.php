<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Reponse;
use App\Http\Session;
use App\Repositories\EncadreurRepository;

final class EncadreurController
{
    public function __construct(private EncadreurRepository $depot)
    {
    }

    public function lister(): void
    {
        Session::exiger(['RESPONSABLE', 'SECRETARIAT']);
        Reponse::json(200, ['encadreurs' => $this->depot->lister()]);
    }
}
