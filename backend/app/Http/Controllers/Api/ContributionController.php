<?php

/**
 * TWUNGURANE - ContributionController
 * 
 * Gestion des contributions (épargnes, pénalités, remboursements)
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Contribution;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ContributionController extends Controller
{
    /**
     * Liste de toutes les contributions de l'utilisateur
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Contribution::whereHas('group', function($q) use ($user) {
            $q->forUser($user);
        })->with(['user:id,nom,prenom', 'group:id,nom']);

        // Filtres
        if ($request->has('type')) {
            $query->ofType($request->type);
        }
        if ($request->has('statut')) {
            $query->where('statut', $request->statut);
        }
        if ($request->has('moyen_paiement')) {
            $query->byPaymentMethod($request->moyen_paiement);
        }
        if ($request->has('date_debut') && $request->has('date_fin')) {
            $query->betweenDates($request->date_debut, $request->date_fin);
        }

        $contributions = $query->orderBy('date_contribution', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $contributions
        ]);
    }

    /**
     * Contributions d'un groupe spécifique
     */
    public function indexByGroup(Request $request, Group $group): JsonResponse
    {
        $user = $request->user();

        if (!$group->hasMember($user) && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé'
            ], 403);
        }

        $query = $group->contributions()
            ->with(['user:id,nom,prenom,telephone']);

        // Filtres
        if ($request->has('type')) {
            $query->ofType($request->type);
        }
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->has('statut')) {
            $query->where('statut', $request->statut);
        }
        if ($request->has('mois')) {
            $query->whereMonth('date_contribution', $request->mois);
        }

        $contributions = $query->orderBy('date_contribution', 'desc')->paginate(20);

        // Statistiques
        $stats = [
            'total_epargne' => $group->contributions()->savings()->validated()->sum('montant'),
            'total_penalites' => $group->contributions()->ofType('penalite')->validated()->sum('montant'),
            'total_remboursements' => $group->contributions()->ofType('remboursement')->validated()->sum('montant'),
            'contributions_ce_mois' => $group->contributions()->savings()->thisMonth()->sum('montant'),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'contributions' => $contributions,
                'statistics' => $stats,
            ]
        ]);
    }

    /**
     * Enregistrer une nouvelle contribution
     */
    public function store(Request $request, Group $group): JsonResponse
    {
        $authUser = $request->user();

        // Vérifier les permissions (admin ou trésorier)
        if (!$group->isAdminOrTresorier($authUser) && !$authUser->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Seuls les administrateurs et trésoriers peuvent enregistrer des contributions'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'montant' => 'required|numeric|min:100',
            'type' => 'required|in:epargne,penalite,remboursement,interet,frais',
            'moyen_paiement' => 'required|in:lumicash,ecocash,mpesa,especes,virement',
            'reference_paiement' => 'nullable|string|max:100',
            'date_contribution' => 'required|date|before_or_equal:today',
            'loan_id' => 'required_if:type,remboursement|exists:loans,id',
            'notes' => 'nullable|string|max:500',
        ], [
            'montant.min' => 'Le montant minimum est de 100 FBU',
            'date_contribution.before_or_equal' => 'La date ne peut pas être dans le futur',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        // Vérifier que l'utilisateur est membre du groupe
        if (!$group->memberships()->where('user_id', $request->user_id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'L\'utilisateur n\'est pas membre de ce groupe'
            ], 422);
        }

        DB::beginTransaction();
        try {
            $contribution = Contribution::create([
                'group_id' => $group->id,
                'user_id' => $request->user_id,
                'loan_id' => $request->loan_id,
                'montant' => $request->montant,
                'type' => $request->type,
                'moyen_paiement' => $request->moyen_paiement,
                'reference_paiement' => $request->reference_paiement,
                'date_contribution' => $request->date_contribution,
                'notes' => $request->notes,
                'statut' => Contribution::STATUT_VALIDE, // Auto-validé par admin/trésorier
                'validated_by' => $authUser->id,
                'validated_at' => now(),
            ]);

            // Si c'est un remboursement, mettre à jour le prêt
            if ($request->type === 'remboursement' && $request->loan_id) {
                $loan = $group->loans()->find($request->loan_id);
                if ($loan) {
                    $loan->recordRepayment($request->montant);
                }
            }

            // Créer la transaction associée
            $contribution->transaction()->create([
                'user_id' => $request->user_id,
                'group_id' => $group->id,
                'reference' => $this->generateReference($request->type),
                'montant' => $request->montant,
                'type' => 'contribution_' . $request->type,
                'source' => $request->moyen_paiement,
                'metadata' => [
                    'contribution_id' => $contribution->id,
                    'reference_paiement' => $request->reference_paiement,
                ],
            ]);

            // Log
            AuditLog::logAction($authUser, 'creation_contribution', Contribution::class, $contribution->id);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Contribution enregistrée avec succès',
                'data' => [
                    'contribution' => $contribution->load(['user:id,nom,prenom', 'group:id,nom'])
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'enregistrement',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Afficher une contribution
     */
    public function show(Request $request, Contribution $contribution): JsonResponse
    {
        $user = $request->user();

        // Vérifier l'accès
        if (!$contribution->group->hasMember($user) && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé'
            ], 403);
        }

        $contribution->load([
            'user:id,nom,prenom,telephone',
            'group:id,nom',
            'validator:id,nom,prenom',
            'loan',
            'transaction'
        ]);

        return response()->json([
            'success' => true,
            'data' => ['contribution' => $contribution]
        ]);
    }

    /**
     * Modifier une contribution
     */
    public function update(Request $request, Contribution $contribution): JsonResponse
    {
        $user = $request->user();

        if (!$contribution->group->isAdminOrTresorier($user) && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Permissions insuffisantes'
            ], 403);
        }

        // On ne peut modifier que les contributions en attente
        if ($contribution->statut !== Contribution::STATUT_EN_ATTENTE) {
            return response()->json([
                'success' => false,
                'message' => 'Seules les contributions en attente peuvent être modifiées'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'montant' => 'sometimes|numeric|min:100',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $oldValues = $contribution->toArray();
        $contribution->update($request->only(['montant', 'notes']));

        AuditLog::logAction($user, 'modification_contribution', Contribution::class, $contribution->id, $oldValues, $contribution->fresh()->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Contribution mise à jour',
            'data' => ['contribution' => $contribution->fresh()]
        ]);
    }

    /**
     * Supprimer une contribution
     */
    public function destroy(Request $request, Contribution $contribution): JsonResponse
    {
        $user = $request->user();

        if (!$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Seul un administrateur peut supprimer une contribution'
            ], 403);
        }

        // Vérifier si c'est un remboursement - si oui, attention au prêt
        if ($contribution->type === 'remboursement' && $contribution->loan) {
            // Annuler le remboursement sur le prêt
            $contribution->loan->decrement('montant_rembourse', $contribution->montant);
        }

        AuditLog::logAction($user, 'suppression_contribution', Contribution::class, $contribution->id);

        $contribution->delete();

        return response()->json([
            'success' => true,
            'message' => 'Contribution supprimée'
        ]);
    }

    /**
     * Générer une référence unique
     */
    private function generateReference(string $type): string
    {
        $prefix = match ($type) {
            'epargne' => 'EPG',
            'penalite' => 'PEN',
            'remboursement' => 'RMB',
            'interet' => 'INT',
            default => 'CON',
        };

        return $prefix . '-' . now()->format('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    }
}
