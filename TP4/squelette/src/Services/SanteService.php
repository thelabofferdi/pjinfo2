<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\SanteRepository;
use Throwable;

// Règle métier : l'application est « disponible » si la base est joignable.
final class SanteService
{
    public function __construct(private SanteRepository $depot)
    {
    }

    /** @return array{statut: string, base: string} */
    public function etat(): array
    {
        try {
            $ok = $this->depot->joignable();
        } catch (Throwable $e) {
            // Le détail technique va dans le journal serveur, jamais dans la réponse.
            error_log('[sante] base injoignable : ' . $e->getMessage());
            $ok = false;
        }

        return [
            'statut' => $ok ? 'ok' : 'indisponible',
            'base'   => $ok ? 'joignable' : 'injoignable',
        ];
    }
}
