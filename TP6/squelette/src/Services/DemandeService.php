<?php
declare(strict_types=1);

namespace App\Services;

use App\Domaine\Transitions;
use App\Http\ApiException;
use App\Repositories\DemandeRepository;
use PDO;
use Throwable;

// Règles métier des demandes. Chaque écriture de statut et son historique forment une seule transaction.
final class DemandeService
{
    public function __construct(private PDO $pdo, private DemandeRepository $depot)
    {
    }

    public function lister(): array
    {
        return $this->depot->lister();
    }

    public function listerPourEtudiant(?int $etudiantId): array
    {
        return $etudiantId === null ? [] : $this->depot->listerPourEtudiant($etudiantId);
    }

    public function creer(array $auteur, mixed $entrepriseId): array
    {
        if ($auteur['etudiant_id'] === null) {
            throw new ApiException(403, 'Aucune fiche étudiant liée à ce compte.');
        }
        if (!is_int($entrepriseId) || $entrepriseId < 1 || !$this->depot->entrepriseExiste($entrepriseId)) {
            throw new ApiException(400, 'Entreprise inconnue ou identifiant invalide.');
        }

        $this->pdo->beginTransaction();
        try {
            $maintenant = date('Y-m-d H:i:s');
            $id = $this->depot->creer($auteur['etudiant_id'], $entrepriseId, $maintenant);
            $this->depot->historiser($id, null, 'SOUMISE', $auteur['id'], null);
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return $this->depot->trouver($id);
    }

    public function changer(int $demandeId, mixed $cible, mixed $motif, array $auteur): array
    {
        if (!is_string($cible) || !Transitions::connu($cible)) {
            throw new ApiException(400, 'Statut demandé inconnu.');
        }
        // 403 : le rôle ne peut pas demander ce statut, quelle que soit la demande.
        if ($auteur['role'] !== Transitions::roleRequis($cible)) {
            throw new ApiException(403, 'Ce rôle ne peut pas effectuer cette transition.');
        }
        $texteMotif = is_string($motif) ? trim($motif) : '';
        if ($cible === 'REFUSEE' && $texteMotif === '') {
            throw new ApiException(400, 'Motif de refus obligatoire.');
        }

        $this->pdo->beginTransaction();
        try {
            $demande = $this->depot->trouverVerrouillee($demandeId);
            if ($demande === null) {
                throw new ApiException(404, 'Demande introuvable.');
            }
            // Un étudiant ne traite que ses propres demandes.
            if ($auteur['role'] === 'ETUDIANT' && (int) $demande['etudiant_id'] !== $auteur['etudiant_id']) {
                throw new ApiException(403, 'Cette demande ne vous appartient pas.');
            }
            if (!Transitions::peutPasser($demande['statut'], $cible)) {
                throw new ApiException(409, "Transition {$demande['statut']} vers $cible interdite.");
            }

            $maintenant = date('Y-m-d H:i:s');
            $this->depot->majStatut(
                $demandeId,
                $cible,
                $cible === 'REFUSEE' ? $texteMotif : null,
                $cible === 'SOUMISE' ? $maintenant : null,
                in_array($cible, ['VALIDEE', 'REFUSEE'], true) ? $maintenant : null
            );
            $this->depot->historiser(
                $demandeId,
                $demande['statut'],
                $cible,
                $auteur['id'],
                $cible === 'REFUSEE' ? $texteMotif : null
            );
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $this->depot->trouver($demandeId);
    }
}
