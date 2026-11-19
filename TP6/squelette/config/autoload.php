<?php
declare(strict_types=1);

// Chargement des classes App\... depuis src/ (pas de Composer, pour rester simple).
spl_autoload_register(function (string $classe): void {
    $prefixe = 'App\\';
    if (strncmp($classe, $prefixe, strlen($prefixe)) !== 0) {
        return;
    }
    $relatif = str_replace('\\', '/', substr($classe, strlen($prefixe)));
    $chemin = dirname(__DIR__) . '/src/' . $relatif . '.php';
    if (is_file($chemin)) {
        require $chemin;
    }
});
