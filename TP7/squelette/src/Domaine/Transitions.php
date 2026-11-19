<?php
declare(strict_types=1);

namespace App\Domaine;

// Cycle de vie d'une demande (figure 6.1 du cours) : seules ces transitions sont acceptées.
final class Transitions
{
    public const TRANSITIONS = [
        'BROUILLON' => ['SOUMISE'],
        'SOUMISE' => ['VALIDEE', 'REFUSEE'],
        'REFUSEE' => ['BROUILLON'],
        'VALIDEE' => [],
    ];

    // Qui peut demander chaque statut cible.
    private const ROLE_PAR_CIBLE = [
        'BROUILLON' => 'ETUDIANT',
        'SOUMISE' => 'ETUDIANT',
        'VALIDEE' => 'RESPONSABLE',
        'REFUSEE' => 'RESPONSABLE',
    ];

    public static function connu(string $statut): bool
    {
        return array_key_exists($statut, self::TRANSITIONS);
    }

    public static function roleRequis(string $cible): string
    {
        return self::ROLE_PAR_CIBLE[$cible];
    }

    public static function peutPasser(string $de, string $vers): bool
    {
        return in_array($vers, self::TRANSITIONS[$de] ?? [], true);
    }
}
