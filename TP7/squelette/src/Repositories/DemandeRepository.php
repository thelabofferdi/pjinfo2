<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

// Seule couche qui écrit du SQL pour les demandes. Toutes les valeurs passent par des paramètres liés.
final class DemandeRepository
{
    private const SELECTION = 'd.id, d.etudiant_id, d.entreprise_id, e.nom AS entreprise_nom, e.ville AS entreprise_ville,
        d.encadreur_id, ue.nom AS etudiant_nom, ue.prenom AS etudiant_prenom,
        ua.nom AS encadreur_nom, ua.prenom AS encadreur_prenom,
        d.statut, d.motif_refus, d.date_depot, d.date_decision';
    private const DEPUIS = 'FROM demande_stage d
        JOIN entreprise e ON e.id = d.entreprise_id
        JOIN etudiant et ON et.id = d.etudiant_id
        JOIN utilisateur ue ON ue.id = et.utilisateur_id
        LEFT JOIN encadreur en ON en.id = d.encadreur_id
        LEFT JOIN utilisateur ua ON ua.id = en.utilisateur_id';

    public function __construct(private PDO $pdo)
    {
    }

    // Recherche par statut et par nom d'étudiant : les critères absents ne filtrent pas.
    public function rechercher(?string $statut, ?string $nom): array
    {
        $conditions = ['1 = 1'];
        $parametres = [];
        if ($statut !== null) {
            $conditions[] = 'd.statut = :statut';
            $parametres[':statut'] = $statut;
        }
        if ($nom !== null) {
            $conditions[] = "CONCAT(ue.nom, ' ', ue.prenom) LIKE :nom";
            $parametres[':nom'] = '%' . $nom . '%';
        }
        $requete = $this->pdo->prepare(
            'SELECT ' . self::SELECTION . ' ' . self::DEPUIS . ' WHERE ' . implode(' AND ', $conditions) . ' ORDER BY d.id'
        );
        $requete->execute($parametres);
        return $requete->fetchAll();
    }

    public function listerPourEtudiant(int $etudiantId): array
    {
        $requete = $this->pdo->prepare('SELECT ' . self::SELECTION . ' ' . self::DEPUIS . ' WHERE d.etudiant_id = :e ORDER BY d.id');
        $requete->execute([':e' => $etudiantId]);
        return $requete->fetchAll();
    }

    public function listerPourEncadreur(int $encadreurId): array
    {
        $requete = $this->pdo->prepare('SELECT ' . self::SELECTION . ' ' . self::DEPUIS . ' WHERE d.encadreur_id = :n ORDER BY d.id');
        $requete->execute([':n' => $encadreurId]);
        return $requete->fetchAll();
    }

    public function trouver(int $id): ?array
    {
        $requete = $this->pdo->prepare('SELECT ' . self::SELECTION . ' ' . self::DEPUIS . ' WHERE d.id = :id');
        $requete->execute([':id' => $id]);
        return $requete->fetch() ?: null;
    }

    // Verrou de ligne : deux décisions simultanées sur la même demande ne peuvent pas se croiser.
    public function trouverVerrouillee(int $id): ?array
    {
        $requete = $this->pdo->prepare(
            'SELECT id, etudiant_id, encadreur_id, statut FROM demande_stage WHERE id = :id FOR UPDATE'
        );
        $requete->execute([':id' => $id]);
        return $requete->fetch() ?: null;
    }

    public function historique(int $demandeId): array
    {
        $requete = $this->pdo->prepare(
            'SELECT h.ancien_statut, h.nouveau_statut, h.motif, h.date_evenement, u.nom, u.prenom
               FROM historique h JOIN utilisateur u ON u.id = h.auteur_id
              WHERE h.demande_id = :id ORDER BY h.id'
        );
        $requete->execute([':id' => $demandeId]);
        return $requete->fetchAll();
    }

    public function entrepriseExiste(int $id): bool
    {
        $requete = $this->pdo->prepare('SELECT COUNT(*) FROM entreprise WHERE id = :id');
        $requete->execute([':id' => $id]);
        return (int) $requete->fetchColumn() > 0;
    }

    public function creer(int $etudiantId, int $entrepriseId, string $statut, ?string $dateDepot): int
    {
        $requete = $this->pdo->prepare(
            'INSERT INTO demande_stage (etudiant_id, entreprise_id, statut, date_depot)
             VALUES (:e, :n, :s, :d)'
        );
        $requete->execute([':e' => $etudiantId, ':n' => $entrepriseId, ':s' => $statut, ':d' => $dateDepot]);
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

    public function majEncadreur(int $id, int $encadreurId): void
    {
        $requete = $this->pdo->prepare('UPDATE demande_stage SET encadreur_id = :n WHERE id = :id');
        $requete->execute([':n' => $encadreurId, ':id' => $id]);
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
