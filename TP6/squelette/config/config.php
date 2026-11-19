<?php
declare(strict_types=1);

// Lit un fichier .env local (non versionné) s'il existe. Les variables déjà présentes
// dans l'environnement ont la priorité : en production, on ne dépend pas d'un fichier.
function charger_env(string $fichier): void
{
    if (!is_file($fichier)) {
        return;
    }
    foreach (file($fichier, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ligne) {
        $ligne = trim($ligne);
        if ($ligne === '' || $ligne[0] === '#' || !str_contains($ligne, '=')) {
            continue;
        }
        [$cle, $valeur] = array_map('trim', explode('=', $ligne, 2));
        if (getenv($cle) === false) {
            putenv($cle . '=' . $valeur);
        }
    }
}

charger_env(dirname(__DIR__) . '/.env');

return [
    'db' => [
        'host'         => getenv('DB_HOST') ?: '127.0.0.1',
        'port'         => (int) (getenv('DB_PORT') ?: 3306),
        'nom'          => getenv('DB_NAME') ?: 'stages',
        'utilisateur'  => getenv('DB_USER') ?: '',
        'mot_de_passe' => getenv('DB_PASSWORD') ?: '',
    ],
];
