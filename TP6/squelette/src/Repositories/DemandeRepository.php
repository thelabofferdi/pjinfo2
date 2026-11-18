<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

// Seule couche qui écrit du SQL pour les demandes. Toutes les valeurs passent par des paramètres liés.
final class DemandeRepository
{
    private const COLONNES = 'id, etudiant_id, entreprise_id, encadreur_id, statut, motif_refus, date_depot, date_decision';

    public function __construct(private PDO $pdo)
    {
    }

    public function lister(): array
    {
        return $this->pdo->query('SELECT ' . self::COLONNES . ' FROM demande_stage ORDER BY id')->fetchAll();
    }

    public function listerPourEtudiant(int $etudiantId): array
    {
        $requete = $this->pdo->prepare('SELECT ' . self::COLONNES . ' FROM demande_stage WHERE etudiant_id = :e ORDER BY id');
        $requete->execute([':e' => $etudiantId]);
        return $requete->fetchAll();
    }

    public function trouver(int $id): ?array
    {
        $requete = $this->pdo->prepare('SELECT ' . self::COLONNES . ' FROM demande_stage WHERE id = :id');
        $requete->execute([':id' => $id]);
        return $requete->fetch() ?: null;
    }

    // Verrou de ligne : deux décisions simultanées sur la même demande ne peuvent pas se croiser.
    public function trouverVerrouillee(int $id): ?array
    {
        $requete = $this->pdo->prepare('SELECT ' . self::COLONNES . ' FROM demande_stage WHERE id = :id FOR UPDATE');
        $requete->execute([':id' => $id]);
        return $requete->fetch() ?: null;
    }

    public function entrepriseExiste(int $id): bool
    {
        $requete = $this->pdo->prepare('SELECT COUNT(*) FROM entreprise WHERE id = :id');
        $requete->execute([':id' => $id]);
        return (int) $requete->fetchColumn() > 0;
    }

    public function creer(int $etudiantId, int $entrepriseId, string $dateDepot): int
    {
        $requete = $this->pdo->prepare(
            "INSERT INTO demande_stage (etudiant_id, entreprise_id, statut, date_depot)
             VALUES (:e, :n, 'SOUMISE', :d)"
        );
        $requete->execute([':e' => $etudiantId, ':n' => $entrepriseId, ':d' => $dateDepot]);
        return (int) $this->pdo->lastInsertId();
    }

    public function majStatut(int $id, string $statut, ?string $motif, ?string $dateDepot, ?string $dateDecision): void
    {
        $requete = $this->pdo->prepare(
            'UPDATE demande_stage
                SET statut = :s,
                    motif_refus = COALESCE(:m, motif_refus),
                    date_depot = COALESCE(:dp, date_depot),
                    date_decision = COALESCE(:dc, date_decision)
              WHERE id = :id'
        );
        $requete->execute([
            ':s' => $statut, ':m' => $motif, ':dp' => $dateDepot, ':dc' => $dateDecision, ':id' => $id,
        ]);
    }

    public function historiser(int $demandeId, ?string $ancien, string $nouveau, int $auteurId, ?string $motif): void
    {
        $requete = $this->pdo->prepare(
            'INSERT INTO historique (demande_id, ancien_statut, nouveau_statut, auteur_id, motif)
             VALUES (:d, :a, :n, :u, :m)'
        );
        $requete->execute([':d' => $demandeId, ':a' => $ancien, ':n' => $nouveau, ':u' => $auteurId, ':m' => $motif]);
    }
}
