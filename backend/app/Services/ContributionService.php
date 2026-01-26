<?php

namespace App\Services;

use App\Models\Contribution;
use App\Models\Group;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

/**
 * Service ContributionService
 * TWUNGURANE - Validation et enregistrement des contributions
 */
class ContributionService
{
    /**
     * Enregistrer une contribution
     */
    public function createContribution(array $data, int $userId): Contribution
    {
        return DB::transaction(function () use ($data, $userId) {
            $group = Group::findOrFail($data['group_id']);

            // Vérifier que l'utilisateur est membre du groupe
            $member = $group->groupMembers()
                ->where('user_id', $userId)
                ->where('statut', 'actif')
                ->firstOrFail();

            $contribution = Contribution::create([
                'group_id' => $data['group_id'],
                'user_id' => $userId,
                'group_member_id' => $member->id,
                'montant' => $data['montant'],
                'type' => $data['type'] ?? 'epargne',
                'moyen_paiement' => $data['moyen_paiement'],
                'reference_paiement' => $data['reference_paiement'] ?? null,
                'date_contribution' => $data['date_contribution'] ?? now(),
                'notes' => $data['notes'] ?? null,
                'statut' => 'valide',
                'enregistre_par' => $userId,
            ]);

            // Mettre à jour le solde du groupe
            if ($contribution->type === 'epargne' || $contribution->type === 'remboursement') {
                $group->increment('solde_total', $contribution->montant);
            } elseif ($contribution->type === 'retrait' || $contribution->type === 'pret') {
                $group->decrement('solde_total', $contribution->montant);
            }

            // Mettre à jour les statistiques du membre
            if ($contribution->type === 'epargne') {
                $member->increment('total_contributions', $contribution->montant);
                $member->increment('nombre_contributions');
            }

            // Créer une transaction
            Transaction::create([
                'reference' => Transaction::generateReference(),
                'user_id' => $userId,
                'group_id' => $group->id,
                'montant' => $contribution->montant,
                'type' => $contribution->type,
                'source' => $contribution->moyen_paiement,
                'metadata' => [
                    'contribution_id' => $contribution->id,
                    'reference_paiement' => $contribution->reference_paiement,
                ],
                'statut' => 'complete',
                'description' => "Contribution {$contribution->type} - Groupe: {$group->nom}",
            ]);

            return $contribution->fresh();
        });
    }
}
