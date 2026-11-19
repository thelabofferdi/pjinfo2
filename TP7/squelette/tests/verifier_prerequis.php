<?php
declare(strict_types=1);

// Vérifie les prérequis avant installation (cours, §4.4).
// Usage : php tests/verifier_prerequis.php   (code retour 0 = tout est présent, 1 = au moins un manquant)

$erreurs = 0;

function verifier(string $libelle, bool $ok, int &$erreurs): void
{
    echo ($ok ? '[OK]     ' : '[MANQUE] ') . $libelle . "\n";
    if (!$ok) {
        $erreurs++;
    }
}

verifier('PHP 8.1 ou plus (version ' . PHP_VERSION . ')', version_compare(PHP_VERSION, '8.1.0', '>='), $erreurs);
verifier('extension pdo', extension_loaded('pdo'), $erreurs);
verifier('pilote PDO MySQL (pdo_mysql)', in_array('mysql', PDO::getAvailableDrivers(), true), $erreurs);

exec('git --version 2>&1', $sortie, $code);
verifier('Git (' . ($code === 0 ? trim($sortie[0] ?? '') : 'introuvable') . ')', $code === 0, $erreurs);

echo $erreurs === 0 ? "Tous les prérequis sont présents.\n" : "$erreurs prérequis manquant(s).\n";
exit($erreurs === 0 ? 0 : 1);
