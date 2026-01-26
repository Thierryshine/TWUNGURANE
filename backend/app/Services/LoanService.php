<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\Group;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

/**
 * Service LoanService
 * TWUNGURANE - Workflow d'approbation et remboursement des prêts
 */
class LoanService
{
    /**
     * Créer une demande de prêt
     */
    public function createLoan(array $data, int $userId): Loan
    {
        return DB::transaction(function () use ($data, $userId) {
            $group = Group::findOrFail($data['group_id']);

            // Vérifier que l'utilisateur est membre du groupe
            $member = $group->groupMembers()
                ->where('user_id', $userId)
                ->where('statut', 'actif')
                ->firstOrFail();

            // Calculer le montant total avec intérêts
            $tauxInteret = $data['taux_interet'] ?? 0;
            $montantTotal = $data['montant'] * (1 + ($tauxInteret / 100));

            $loan = Loan::create([
                'group_id' => $data['group_id'],
                'user_id' => $userId,
                'group_member_id' => $member->id,
                'montant' => $data['montant'],
                'taux_interet' => $tauxInteret,
                'duree' => $data['duree'],
                'montant_total' => $montantTotal,
                'montant_restant' => $montantTotal,
                'motif' => $data['motif'],
                'statut' => 'en_attente',
            ]);

            return $loan->fresh();
        });
    }

    /**
     * Approuver un prêt
     */
    public function approveLoan(int $loanId, int $approverId): Loan
    {
        return DB::transaction(function () use ($loanId, $approverId) {
            $loan = Loan::findOrFail($loanId);

            if ($loan->statut !== 'en_attente') {
                throw new \Exception('Ce prêt ne peut pas être approuvé');
            }

            $group = $loan->group;

            // Vérifier que le groupe a suffisamment de fonds
            if ($group->solde_total < $loan->montant) {
                throw new \Exception('Solde insuffisant dans le groupe');
            }

            $loan->update([
                'statut' => 'approuve',
                'approuve_par' => $approverId,
                'approved_at' => now(),
                'date_debut' => now(),
                'date_echeance' => now()->addMonths($loan->duree),
            ]);

            // Débiter le groupe
            $group->decrement('solde_total', $loan->montant);

            // Créer une transaction
            Transaction::create([
                'reference' => Transaction::generateReference(),
                'user_id' => $loan->user_id,
                'group_id' => $loan->group_id,
                'montant' => $loan->montant,
                'type' => 'pret',
                'source' => 'interne',
                'metadata' => ['loan_id' => $loan->id],
                'statut' => 'complete',
                'description' => "Prêt approuvé - Groupe: {$group->nom}",
            ]);

            return $loan->fresh();
        });
    }

    /**
     * Enregistrer un remboursement
     */
    public function repayLoan(int $loanId, float $montant, string $moyenPaiement): Loan
    {
        return DB::transaction(function () use ($loanId, $montant, $moyenPaiement) {
            $loan = Loan::findOrFail($loanId);

            if ($loan->statut !== 'approuve' && $loan->statut !== 'actif') {
                throw new \Exception('Ce prêt ne peut pas être remboursé');
            }

            $montantRestant = $loan->montant_restant - $montant;

            $loan->update([
                'montant_rembourse' => $loan->montant_rembourse + $montant,
                'montant_restant' => max(0, $montantRestant),
                'statut' => $montantRestant <= 0 ? 'termine' : 'actif',
                'date_fin' => $montantRestant <= 0 ? now() : null,
                'nombre_remboursements' => $loan->nombre_remboursements + 1,
            ]);

            // Créditer le groupe
            $loan->group->increment('solde_total', $montant);

            // Créer une transaction
            Transaction::create([
                'reference' => Transaction::generateReference(),
                'user_id' => $loan->user_id,
                'group_id' => $loan->group_id,
                'montant' => $montant,
                'type' => 'remboursement',
                'source' => $moyenPaiement,
                'metadata' => ['loan_id' => $loan->id],
                'statut' => 'complete',
                'description' => "Remboursement prêt - Groupe: {$loan->group->nom}",
            ]);

            return $loan->fresh();
        });
    }
}
