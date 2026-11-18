<?php
declare(strict_types=1);

namespace App\Http;

final class Requete
{
    // Lit le corps JSON. Un corps absent ou mal formé est une erreur 400.
    public static function corps(): array
    {
        $brut = file_get_contents('php://input') ?: '';
        $donnees = json_decode($brut, true);
        if (!is_array($donnees)) {
            throw new ApiException(400, 'Corps JSON attendu.');
        }
        return $donnees;
    }
}
