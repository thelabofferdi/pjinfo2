<?php
declare(strict_types=1);

namespace App\Services;

use App\Domaine\Transitions;
use App\Http\ApiException;
use App\Repositories\DemandeRepository;
use App\Repositories\EncadreurRepository;
use PDO;
use Throwable;

// Règles métier des demandes. Chaque changement de statut et son historique forment une seule transaction.
final class DemandeService
{
    public function __construct(
        private PDO $pdo,
        private DemandeRepository $depot,
        private EncadreurRepository $encadreurs,
    ) {
    }

    public function rechercher(?string $statut, ?string $nom): array
    {
        return $this->depot->rechercher($statut, $nom);
    }

    public function listerPourEtudiant(?int $ficheId): array
    {
        return $ficheId === null ? [] : $this->depot->listerPourEtudiant($ficheId);
    }

    public function listerPourEncadreur(?int $ficheId): array
    {
        return $ficheId === null ? [] : $this->depot->listerPourEncadreur($ficheId);
    }

    // Détail et historique. Un étudiant ne voit que ses demandes, un encadreur que celles qui lui sont affectées.
    public function detail(int $id, array $auteur): array
    {
        $demande = $this->depot->trouver($id);
        if ($demande === null) {
            throw new ApiException(404, 'Demande introuvable.');
        }
        $autorise = match ($auteur['role']) {
            'RESPONSABLE', 'SECRETARIAT' => true,
            'ETUDIANT' => (int) $demande['etudiant_id'] === $auteur['fiche_id'],
            'ENCADREUR' => (int) $demande['encadreur_id'] === $auteur['fiche_id'],
            default => false,
        };
        if (!$autorise) {
            throw new ApiException(403, 'Cette demande ne vous est pas accessible.');
        }
        return ['demande' => $demande, 'historique' => $this->depot->historique($id)];
    }

    public function creer(array $auteur, mixed $entrepriseId, bool $brouillon): array
    {
        if ($auteur['fiche_id'] === null) {
            throw new ApiException(403, 'Aucune fiche étudiant liée à ce compte.');
        }
        if (!is_int($entrepriseId) || $entrepriseId < 1 || !$this->depot->entrepriseExiste($entrepriseId)) {
            throw new ApiException(400, 'Entreprise inconnue ou identifiant invalide.');
        }

        $statut = $brouillon ? 'BROUILLON' : 'SOUMISE';
        $this->pdo->beginTransaction();
        try {
            $depot = $brouillon ? null : date('Y-m-d H:i:s');
            $id = $this->depot->creer($auteur['fiche_id'], $entrepriseId, $statut, $depot);
            $this->depot->historiser($id, null, $statut, $auteur['id'], null);
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
            if ($auteur['role'] === 'ETUDIANT' && (int) $demande['etudiant_id'] !== $auteur['fiche_id']) {
                throw new ApiException(403, 'Cette demande ne vous appartient pas.');
            }
            if (!Transitions::peutPasser($demande['statut'], $cible)) {
                throw new ApiException(409, "Transition {$demande['statut']} vers $cible interdite.");
            }

            $maintenant = date('Y-m-d H:i:s');
            // Motif : renseigné pour un refus, vidé pour une nouvelle soumission, conservé sinon.
            $motifEnregistre = match ($cible) {
                'REFUSEE' => $texteMotif,
                'BROUILLON', 'SOUMISE' => '',
                default => null,
            };
            $this->depot->majStatut(
                $demandeId,
                $cible,
                $motifEnregistre,
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

    // Affectation d'un encadreur : seulement sur une demande validée. Tracée dans l'historique.
    public function affecter(int $demandeId, mixed $encadreurId, array $auteur): array
    {
        if (!is_int($encadreurId) || !$this->encadreurs->existe($encadreurId)) {
            throw new ApiException(400, 'Encadreur inconnu ou identifiant invalide.');
        }

        $this->pdo->beginTransaction();
        try {
            $demande = $this->depot->trouverVerrouillee($demandeId);
            if ($demande === null) {
                throw new ApiException(404, 'Demande introuvable.');
            }
            if ($demande['statut'] !== 'VALIDEE') {
                throw new ApiException(409, 'Seule une demande validée peut recevoir un encadreur.');
            }
            $this->depot->majEncadreur($demandeId, $encadreurId);
            $this->depot->historiser(
                $demandeId, 'VALIDEE', 'VALIDEE', $auteur['id'], "Affectation de l'encadreur n° $encadreurId"
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
