<?php
declare(strict_types=1);

namespace App\Http;

use Throwable;

// Table des routes. Un segment {nom} capture une valeur (sans « / ») transmise à l'action.
final class Routeur
{
    /** @var list<array{methode: string, motif: string, action: callable}> */
    private array $routes = [];

    public function declarer(string $methode, string $chemin, callable $action): void
    {
        $segments = array_map(
            static fn (string $s): string => preg_match('/^\{([a-z]+)\}$/', $s, $m) === 1
                ? '(?P<' . $m[1] . '>[^/]+)'
                : preg_quote($s, '#'),
            explode('/', $chemin)
        );
        $this->routes[] = [
            'methode' => $methode,
            'motif' => '#^' . implode('/', $segments) . '$#',
            'action' => $action,
        ];
    }

    public function repondre(string $methode, string $chemin): void
    {
        $cheminConnu = false;
        foreach ($this->routes as $route) {
            if (preg_match($route['motif'], $chemin, $correspondance) !== 1) {
                continue;
            }
            $cheminConnu = true;
            if ($route['methode'] !== $methode) {
                continue;
            }
            $parametres = array_filter($correspondance, 'is_string', ARRAY_FILTER_USE_KEY);
            try {
                ($route['action'])($parametres);
            } catch (ApiException $e) {
                Reponse::erreur($e->statutHttp, $e->getMessage());
            } catch (Throwable $e) {
                // Détail technique dans le journal serveur uniquement.
                error_log('[api] ' . $e->getMessage());
                Reponse::erreur(500, 'Erreur serveur.');
            }
            return;
        }

        if ($cheminConnu) {
            Reponse::erreur(405, 'Méthode non autorisée pour cette ressource.');
            return;
        }
        Reponse::erreur(404, 'Ressource introuvable.');
    }
}
