<?php
declare(strict_types=1);

// Tests unitaires du cycle de vie des demandes, sans base de données.
// Usage : php tests/test_transitions.php  (code retour 0 = tout passe)
require dirname(__DIR__) . '/config/autoload.php';

use App\Domaine\Transitions;

$cas = [
    ['SOUMISE', 'VALIDEE', true],
    ['SOUMISE', 'REFUSEE', true],
    ['REFUSEE', 'BROUILLON', true],
    ['BROUILLON', 'SOUMISE', true],
    ['VALIDEE', 'REFUSEE', false],
    ['BROUILLON', 'VALIDEE', false],
    ['VALIDEE', 'SOUMISE', false],
    ['SOUMISE', 'INCONNU', false],
];
$echecs = 0;
foreach ($cas as [$de, $vers, $attendu]) {
    $obtenu = Transitions::peutPasser($de, $vers);
    if ($obtenu !== $attendu) {
        $echecs++;
        echo "ECHEC : $de -> $vers attendu " . var_export($attendu, true) . "\n";
    }
}
echo $echecs === 0 ? "OK : " . count($cas) . " transitions vérifiées.\n" : "$echecs échec(s).\n";
exit($echecs === 0 ? 0 : 1);
