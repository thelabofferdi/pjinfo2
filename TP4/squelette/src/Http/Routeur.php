<?php
declare(strict_types=1);

namespace App\Http;

// Table des routes. Une route déclarée sans action (null) figure au contrat
// mais n'est pas encore implémentée : elle répond 501, jamais 200 par erreur.
final class Routeur
{
    /** @var list<array{methode: string, motif: string, action: ?callable}> */
    private array $routes = [];

    public function declarer(string $methode, string $chemin, ?callable $action): void
    {
        // Un segment {id} correspond à n'importe quelle valeur sans « / ».
        $segments = array_map(
            static fn (string $s): string => preg_match('/^\{[a-z]+\}$/', $s)
                ? '[^/]+'
                : preg_quote($s, '#'),
            explode('/', $chemin)
        );
        $this->routes[] = [
            'methode' => $methode,
            'motif'   => '#^' . implode('/', $segments) . '$#',
            'action'  => $action,
        ];
    }

    public function repondre(string $methode, string $chemin): void
    {
        $chemin_connu = false;
        foreach ($this->routes as $route) {
            if (!preg_match($route['motif'], $chemin)) {
                continue;
            }
            $chemin_connu = true;
            if ($route['methode'] !== $methode) {
                continue;
            }
            if ($route['action'] === null) {
                Reponse::json(501, ['erreur' => 'Route prévue au contrat, non encore implémentée.']);
                return;
            }
            ($route['action'])();
            return;
        }

        if ($chemin_connu) {
            Reponse::json(405, ['erreur' => 'Méthode non autorisée pour cette ressource.']);
            return;
        }
        Reponse::json(404, ['erreur' => 'Ressource introuvable.']);
    }
}
