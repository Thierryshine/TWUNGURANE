<?php

namespace App\Services;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service GroupService
 * TWUNGURANE - Logique métier des groupes d'épargne
 */
class GroupService
{
    /**
     * Créer un nouveau groupe
     */
    public function createGroup(array $data, int $userId): Group
    {
        return DB::transaction(function () use ($data, $userId) {
            $group = Group::create([
                'nom' => $data['nom'],
                'type' => $data['type'],
                'localisation' => $data['localisation'],
                'province' => $data['province'],
                'commune' => $data['commune'],
                'montant_contribution' => $data['montant_contribution'],
                'frequence' => $data['frequence'],
                'duree_cycle' => $data['duree_cycle'],
                'max_members' => $data['max_members'] ?? 20,
                'description' => $data['description'] ?? null,
                'created_by' => $userId,
                'date_debut' => now(),
            ]);

            // Ajouter le créateur comme admin
            $this->addMember($group->id, $userId, 'admin');

            return $group->fresh();
        });
    }

    /**
     * Ajouter un membre à un groupe
     */
    public function addMember(int $groupId, int $userId, string $role = 'membre'): GroupMember
    {
        $group = Group::findOrFail($groupId);

        // Vérifier si le groupe est complet
        if ($group->isComplet()) {
            throw new \Exception('Le groupe a atteint le nombre maximum de membres');
        }

        // Vérifier si l'utilisateur est déjà membre
        if ($group->members()->where('user_id', $userId)->exists()) {
            throw new \Exception('L\'utilisateur est déjà membre de ce groupe');
        }

        $member = GroupMember::create([
            'group_id' => $groupId,
            'user_id' => $userId,
            'role_dans_groupe' => $role,
            'date_adhesion' => now(),
            'statut' => 'actif',
        ]);

        // Mettre à jour le nombre de membres
        $group->increment('current_members');

        return $member;
    }

    /**
     * Calculer les statistiques d'un groupe
     */
    public function getStatistics(int $groupId): array
    {
        $group = Group::findOrFail($groupId);

        return [
            'solde_total' => $group->solde_total,
            'nombre_membres' => $group->current_membres,
            'nombre_contributions' => $group->contributions()->where('statut', 'valide')->count(),
            'total_contributions' => $group->contributions()
                ->where('statut', 'valide')
                ->where('type', 'epargne')
                ->sum('montant'),
            'nombre_prets_actifs' => $group->loans()->where('statut', 'actif')->count(),
            'montant_prets_actifs' => $group->loans()
                ->where('statut', 'actif')
                ->sum('montant_restant'),
        ];
    }
}
