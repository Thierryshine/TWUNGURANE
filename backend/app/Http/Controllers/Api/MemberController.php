<?php

/**
 * TWUNGURANE - MemberController
 * 
 * Gestion des membres des groupes d'épargne
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class MemberController extends Controller
{
    /**
     * Liste des membres d'un groupe
     */
    public function index(Request $request, Group $group): JsonResponse
    {
        $user = $request->user();

        if (!$group->hasMember($user) && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé'
            ], 403);
        }

        $members = $group->memberships()
            ->with(['user:id,nom,prenom,telephone,email,avatar'])
            ->orderBy('date_adhesion', 'desc')
            ->get()
            ->map(function($membership) use ($group) {
                return [
                    'id' => $membership->id,
                    'user_id' => $membership->user_id,
                    'user' => $membership->user,
                    'role_dans_groupe' => $membership->role_dans_groupe,
                    'date_adhesion' => $membership->date_adhesion,
                    'statut' => $membership->statut,
                    'total_contributions' => $group->contributions()
                        ->where('user_id', $membership->user_id)
                        ->where('type', 'epargne')
                        ->sum('montant'),
                    'prets_actifs' => $group->loans()
                        ->where('user_id', $membership->user_id)
                        ->active()
                        ->count(),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'members' => $members,
                'total' => $members->count(),
                'max_membres' => $group->max_membres,
            ]
        ]);
    }

    /**
     * Ajouter un membre au groupe
     */
    public function store(Request $request, Group $group): JsonResponse
    {
        $authUser = $request->user();

        // Vérifier les permissions
        if (!$group->isAdminOrTresorier($authUser) && !$authUser->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'avez pas la permission d\'ajouter des membres'
            ], 403);
        }

        // Vérifier la capacité
        if (!$group->canAcceptMembers()) {
            return response()->json([
                'success' => false,
                'message' => 'Le groupe a atteint sa capacité maximale de membres'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'user_id' => 'required_without:telephone|exists:users,id',
            'telephone' => 'required_without:user_id|string|regex:/^\+257[0-9]{8}$/',
            'role_dans_groupe' => 'nullable|in:membre,tresorier,admin',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        // Trouver ou inviter l'utilisateur
        $user = null;
        if ($request->has('user_id')) {
            $user = User::find($request->user_id);
        } else {
            $user = User::where('telephone', $request->telephone)->first();
            
            if (!$user) {
                // L'utilisateur n'existe pas encore - créer une invitation
                return response()->json([
                    'success' => false,
                    'message' => 'Cet utilisateur n\'a pas de compte TWUNGURANE. Invitez-le à s\'inscrire.',
                    'error_code' => 'USER_NOT_FOUND',
                    'data' => [
                        'telephone' => $request->telephone,
                        'invite_link' => config('app.url') . '/register?invite=' . $group->id
                    ]
                ], 404);
            }
        }

        // Vérifier si déjà membre
        if ($group->hasMember($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Cet utilisateur est déjà membre du groupe'
            ], 422);
        }

        // Ajouter le membre
        $membership = GroupMember::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'role_dans_groupe' => $request->role_dans_groupe ?? 'membre',
            'date_adhesion' => now(),
            'statut' => 'actif',
        ]);

        // Log
        AuditLog::logAction($authUser, 'ajout_membre', GroupMember::class, $membership->id);

        return response()->json([
            'success' => true,
            'message' => 'Membre ajouté avec succès',
            'data' => [
                'membership' => $membership->load('user:id,nom,prenom,telephone')
            ]
        ], 201);
    }

    /**
     * Afficher un membre
     */
    public function show(Request $request, Group $group, GroupMember $member): JsonResponse
    {
        $user = $request->user();

        if (!$group->hasMember($user) && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé'
            ], 403);
        }

        // Vérifier que le membre appartient au groupe
        if ($member->group_id !== $group->id) {
            return response()->json([
                'success' => false,
                'message' => 'Membre non trouvé dans ce groupe'
            ], 404);
        }

        $member->load('user:id,nom,prenom,telephone,email,avatar,province,commune');

        // Statistiques du membre
        $stats = [
            'total_contributions' => $group->contributions()
                ->where('user_id', $member->user_id)
                ->where('type', 'epargne')
                ->sum('montant'),
            'contributions_ce_mois' => $group->contributions()
                ->where('user_id', $member->user_id)
                ->where('type', 'epargne')
                ->whereMonth('date_contribution', now()->month)
                ->sum('montant'),
            'penalites' => $group->contributions()
                ->where('user_id', $member->user_id)
                ->where('type', 'penalite')
                ->sum('montant'),
            'prets_actifs' => $group->loans()
                ->where('user_id', $member->user_id)
                ->active()
                ->sum('montant'),
            'prets_rembourses' => $group->loans()
                ->where('user_id', $member->user_id)
                ->where('statut', 'rembourse')
                ->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'member' => $member,
                'statistics' => $stats,
            ]
        ]);
    }

    /**
     * Modifier un membre
     */
    public function update(Request $request, Group $group, GroupMember $member): JsonResponse
    {
        $authUser = $request->user();

        if (!$group->isAdminOrTresorier($authUser) && !$authUser->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Permissions insuffisantes'
            ], 403);
        }

        if ($member->group_id !== $group->id) {
            return response()->json([
                'success' => false,
                'message' => 'Membre non trouvé dans ce groupe'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'statut' => 'sometimes|in:actif,inactif,suspendu',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $oldValues = $member->toArray();
        $member->update($request->only(['statut']));

        AuditLog::logAction($authUser, 'modification_membre', GroupMember::class, $member->id, $oldValues, $member->fresh()->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Membre mis à jour',
            'data' => ['member' => $member->fresh()]
        ]);
    }

    /**
     * Retirer un membre
     */
    public function destroy(Request $request, Group $group, GroupMember $member): JsonResponse
    {
        $authUser = $request->user();

        if (!$group->isAdminOrTresorier($authUser) && !$authUser->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Permissions insuffisantes'
            ], 403);
        }

        if ($member->group_id !== $group->id) {
            return response()->json([
                'success' => false,
                'message' => 'Membre non trouvé'
            ], 404);
        }

        // Vérifier les prêts en cours
        $activeLoans = $group->loans()
            ->where('user_id', $member->user_id)
            ->active()
            ->exists();

        if ($activeLoans) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de retirer un membre avec des prêts en cours'
            ], 422);
        }

        AuditLog::logAction($authUser, 'retrait_membre', GroupMember::class, $member->id);

        $member->delete();

        return response()->json([
            'success' => true,
            'message' => 'Membre retiré du groupe'
        ]);
    }

    /**
     * Changer le rôle d'un membre
     */
    public function updateRole(Request $request, Group $group, GroupMember $member): JsonResponse
    {
        $authUser = $request->user();

        // Seul un admin du groupe peut changer les rôles
        $authMembership = $group->memberships()
            ->where('user_id', $authUser->id)
            ->where('role_dans_groupe', 'admin')
            ->first();

        if (!$authMembership && !$authUser->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Seul un administrateur du groupe peut modifier les rôles'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'role_dans_groupe' => 'required|in:membre,tresorier,admin',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $oldRole = $member->role_dans_groupe;
        $member->update(['role_dans_groupe' => $request->role_dans_groupe]);

        AuditLog::logAction(
            $authUser,
            'changement_role_membre',
            GroupMember::class,
            $member->id,
            ['role' => $oldRole],
            ['role' => $request->role_dans_groupe]
        );

        return response()->json([
            'success' => true,
            'message' => 'Rôle mis à jour avec succès',
            'data' => ['member' => $member->fresh()->load('user:id,nom,prenom')]
        ]);
    }
}
