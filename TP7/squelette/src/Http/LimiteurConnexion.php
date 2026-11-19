<?php
declare(strict_types=1);

namespace App\Http;

// Limite les essais de connexion : 5 échecs par couple (adresse, e-mail) dans une fenêtre de 15 minutes.
final class LimiteurConnexion
{
    private const MAX_ECHECS = 5;
    private const FENETRE = 900;

    private static function fichier(string $cle): string
    {
        return sys_get_temp_dir() . '/limite_connexion_' . hash('sha256', $cle);
    }

    private static function lire(string $cle): ?array
    {
        $fichier = self::fichier($cle);
        if (!is_file($fichier)) {
            return null;
        }
        $donnees = json_decode((string) file_get_contents($fichier), true);
        if (!is_array($donnees) || time() - $donnees['debut'] > self::FENETRE) {
            @unlink($fichier);
            return null;
        }
        return $donnees;
    }

    public static function bloque(string $cle): bool
    {
        $donnees = self::lire($cle);
        return $donnees !== null && $donnees['echecs'] >= self::MAX_ECHECS;
    }

    public static function echec(string $cle): void
    {
        $donnees = self::lire($cle) ?? ['debut' => time(), 'echecs' => 0];
        $donnees['echecs']++;
        file_put_contents(self::fichier($cle), json_encode($donnees));
    }

    public static function reinitialiser(string $cle): void
    {
        @unlink(self::fichier($cle));
    }
}
