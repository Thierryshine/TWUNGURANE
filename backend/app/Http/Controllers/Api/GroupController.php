<?php

/**
 * TWUNGURANE - GroupController
 * 
 * Gestion des groupes d'épargne communautaire (VSLA, Tontines)
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class GroupController extends Controller
{
    /**
     * Liste des groupes de l'utilisateur
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Group::forUser($user)
            ->withCount(['memberships as membres_actifs' => function($q) {
                $q->where('statut', 'actif');
            }])
            ->with(['creator:id,nom,prenom']);

        // Filtres
        if ($request->has('type')) {
            $query->ofType($request->type);
        }
        if ($request->has('statut')) {
            $query->where('statut', $request->statut);
        }
        if ($request->has('province')) {
            $query->inProvince($request->province);
        }

        $groups = $query->orderBy('created_at', 'desc')->paginate(15);

        // Ajouter les statistiques pour chaque groupe
        $groups->getCollection()->transform(function($group) {
            $group->balance = $group->balance;
            $group->total_contributions = $group->total_contributions;
            return $group;
        });

        return response()->json([
            'success' => true,
            'data' => $groups
        ]);
    }

    /**
     * Créer un nouveau groupe
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nom' => 'required|string|max:150',
            'type' => 'required|in:vsla,tontine,groupe_solidaire',
            'description' => 'nullable|string|max:1000',
            'localisation' => 'required|string|max:200',
            'province' => 'required|string|max:50',
            'commune' => 'required|string|max:50',
            'montant_contribution' => 'required|numeric|min:1000|max:10000000',
            'frequence' => 'required|in:hebdomadaire,bimensuelle,mensuelle',
            'duree_cycle' => 'required|integer|min:1|max:24',
            'max_membres' => 'nullable|integer|min:2|max:50',
            'taux_interet_pret' => 'nullable|numeric|min:0|max:100',
            'penalite_retard' => 'nullable|numeric|min:0|max:100',
            'date_debut_cycle' => 'nullable|date|after_or_equal:today',
            'regles' => 'nullable|array',
        ], [
            'montant_contribution.min' => 'Le montant minimum de contribution est de 1000 FBU',
            'duree_cycle.max' => 'La durée maximum d\'un cycle est de 24 mois',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        DB::beginTransaction();
        try {
            // Créer le groupe
            $group = Group::create([
                'nom' => $request->nom,
                'type' => $request->type,
                'description' => $request->description,
                'localisation' => $request->localisation,
                'province' => $request->province,
                'commune' => $request->commune,
                'montant_contribution' => $request->montant_contribution,
                'frequence' => $request->frequence,
                'duree_cycle' => $request->duree_cycle,
                'max_membres' => $request->max_membres ?? 20,
                'taux_interet_pret' => $request->taux_interet_pret ?? 10,
                'penalite_retard' => $request->penalite_retard ?? 5,
                'date_debut_cycle' => $request->date_debut_cycle ?? now(),
                'date_fin_cycle' => ($request->date_debut_cycle ?? now())->addMonths($request->duree_cycle),
                'regles' => $request->regles,
                'created_by' => $user->id,
                'statut' => Group::STATUT_ACTIF,
            ]);

            // Ajouter le créateur comme admin du groupe
            GroupMember::create([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'role_dans_groupe' => 'admin',
                'date_adhesion' => now(),
                'statut' => 'actif',
            ]);

            // Log de l'action
            AuditLog::logAction($user, 'creation_groupe', Group::class, $group->id);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Groupe créé avec succès',
                'data' => ['group' => $group->load('creator')]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du groupe',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Afficher un groupe
     * 
     * @param Request $request
     * @param Group $group
     * @return JsonResponse
     */
    public function show(Request $request, Group $group): JsonResponse
    {
        $user = $request->user();

        // Vérifier que l'utilisateur est membre
        if (!$group->hasMember($user) && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé à ce groupe'
            ], 403);
        }

        $group->load([
            'creator:id,nom,prenom,telephone',
            'activeMembers:id,nom,prenom,telephone',
            'contributions' => function($q) {
                $q->latest()->limit(10);
            },
            'loans' => function($q) {
                $q->latest()->limit(5);
            }
        ]);

        // Ajouter les statistiques
        $group->statistics = $group->getStatistics();
        $group->available_loan_amount = $group->getAvailableLoanAmount();

        return response()->json([
            'success' => true,
            'data' => ['group' => $group]
        ]);
    }

    /**
     * Mettre à jour un groupe
     * 
     * @param Request $request
     * @param Group $group
     * @return JsonResponse
     */
    public function update(Request $request, Group $group): JsonResponse
    {
        $user = $request->user();

        // Vérifier les permissions
        if (!$group->isAdminOrTresorier($user) && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'avez pas la permission de modifier ce groupe'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'nom' => 'sometimes|string|max:150',
            'description' => 'nullable|string|max:1000',
            'localisation' => 'sometimes|string|max:200',
            'montant_contribution' => 'sometimes|numeric|min:1000',
            'frequence' => 'sometimes|in:hebdomadaire,bimensuelle,mensuelle',
            'max_membres' => 'sometimes|integer|min:2|max:50',
            'taux_interet_pret' => 'sometimes|numeric|min:0|max:100',
            'penalite_retard' => 'sometimes|numeric|min:0|max:100',
            'regles' => 'nullable|array',
            'statut' => 'sometimes|in:actif,inactif,termine',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $oldValues = $group->toArray();
        
        $group->update($request->only([
            'nom', 'description', 'localisation', 'montant_contribution',
            'frequence', 'max_membres', 'taux_interet_pret', 'penalite_retard',
            'regles', 'statut'
        ]));

        // Log de l'action
        AuditLog::logAction($user, 'modification_groupe', Group::class, $group->id, $oldValues, $group->fresh()->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Groupe mis à jour avec succès',
            'data' => ['group' => $group->fresh()]
        ]);
    }

    /**
     * Supprimer un groupe (soft delete)
     * 
     * @param Request $request
     * @param Group $group
     * @return JsonResponse
     */
    public function destroy(Request $request, Group $group): JsonResponse
    {
        $user = $request->user();

        // Seul le créateur ou un admin peut supprimer
        if ($group->created_by !== $user->id && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'avez pas la permission de supprimer ce groupe'
            ], 403);
        }

        // Vérifier qu'il n'y a pas de prêts en cours
        if ($group->loans()->active()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de supprimer un groupe avec des prêts en cours'
            ], 422);
        }

        // Log de l'action
        AuditLog::logAction($user, 'suppression_groupe', Group::class, $group->id);

        $group->delete();

        return response()->json([
            'success' => true,
            'message' => 'Groupe supprimé avec succès'
        ]);
    }

    /**
     * Statistiques d'un groupe
     * 
     * @param Request $request
     * @param Group $group
     * @return JsonResponse
     */
    public function statistics(Request $request, Group $group): JsonResponse
    {
        $user = $request->user();

        if (!$group->hasMember($user) && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé'
            ], 403);
        }

        $stats = $group->getStatistics();

        // Statistiques mensuelles sur les 6 derniers mois
        $monthlyStats = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $monthlyStats[] = [
                'mois' => $date->format('Y-m'),
                'label' => $date->translatedFormat('M Y'),
                'contributions' => $group->contributions()
                    ->whereMonth('date_contribution', $date->month)
                    ->whereYear('date_contribution', $date->year)
                    ->where('type', 'epargne')
                    ->sum('montant'),
                'nouveaux_membres' => $group->memberships()
                    ->whereMonth('date_adhesion', $date->month)
                    ->whereYear('date_adhesion', $date->year)
                    ->count(),
            ];
        }

        // Top contributeurs
        $topContributors = $group->contributions()
            ->select('user_id', DB::raw('SUM(montant) as total'))
            ->where('type', 'epargne')
            ->where('statut', 'valide')
            ->groupBy('user_id')
            ->orderBy('total', 'desc')
            ->limit(5)
            ->with('user:id,nom,prenom')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $stats,
                'monthly' => $monthlyStats,
                'top_contributors' => $topContributors,
            ]
        ]);
    }

    /**
     * Solde du groupe
     * 
     * @param Request $request
     * @param Group $group
     * @return JsonResponse
     */
    public function balance(Request $request, Group $group): JsonResponse
    {
        $user = $request->user();

        if (!$group->hasMember($user) && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'solde_total' => $group->balance,
                'total_epargne' => $group->total_contributions,
                'prets_actifs' => $group->active_loans_amount,
                'disponible_prets' => $group->getAvailableLoanAmount(),
                'penalites_collectees' => $group->contributions()
                    ->where('type', 'penalite')
                    ->sum('montant'),
                'interets_collectes' => $group->contributions()
                    ->where('type', 'interet')
                    ->sum('montant'),
            ]
        ]);
    }
}
