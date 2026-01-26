<?php

/**
 * TWUNGURANE - LoanController
 * 
 * Gestion des prêts VSLA
 * Demandes, approbations, remboursements
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Loan;
use App\Models\Contribution;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class LoanController extends Controller
{
    /**
     * Liste des prêts de l'utilisateur
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Loan::whereHas('group', function($q) use ($user) {
            $q->forUser($user);
        })->with(['user:id,nom,prenom', 'group:id,nom']);

        // Filtres
        if ($request->has('statut')) {
            $query->where('statut', $request->statut);
        }
        if ($request->has('group_id')) {
            $query->where('group_id', $request->group_id);
        }

        $loans = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $loans
        ]);
    }

    /**
     * Prêts d'un groupe spécifique
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

        $query = $group->loans()->with(['user:id,nom,prenom,telephone']);

        if ($request->has('statut')) {
            $query->where('statut', $request->statut);
        }

        $loans = $query->orderBy('created_at', 'desc')->paginate(15);

        // Statistiques
        $stats = [
            'total_prets_octroyes' => $group->loans()->whereIn('statut', ['approuve', 'en_cours', 'rembourse'])->sum('montant'),
            'total_en_cours' => $group->loans()->active()->sum('montant'),
            'total_rembourse' => $group->loans()->where('statut', 'rembourse')->sum('montant_rembourse'),
            'prets_en_attente' => $group->loans()->where('statut', 'en_attente')->count(),
            'disponible' => $group->getAvailableLoanAmount(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'loans' => $loans,
                'statistics' => $stats,
            ]
        ]);
    }

    /**
     * Demander un prêt
     */
    public function store(Request $request, Group $group): JsonResponse
    {
        $user = $request->user();

        if (!$group->hasMember($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Vous devez être membre du groupe pour demander un prêt'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'montant' => 'required|numeric|min:5000',
            'duree_mois' => 'required|integer|min:1|max:12',
            'motif' => 'required|string|max:500',
            'garantie' => 'nullable|string|max:500',
        ], [
            'montant.min' => 'Le montant minimum d\'un prêt est de 5 000 FBU',
            'duree_mois.max' => 'La durée maximum d\'un prêt est de 12 mois',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        // Vérifier la disponibilité des fonds
        $disponible = $group->getAvailableLoanAmount();
        if ($request->montant > $disponible) {
            return response()->json([
                'success' => false,
                'message' => "Fonds insuffisants. Montant disponible: {$disponible} FBU"
            ], 422);
        }

        // Vérifier si l'utilisateur a des prêts non remboursés
        $loansEnCours = $group->loans()
            ->where('user_id', $user->id)
            ->active()
            ->count();

        if ($loansEnCours > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Vous avez déjà un prêt en cours dans ce groupe'
            ], 422);
        }

        // Calculer les intérêts
        $tauxInteret = $group->taux_interet_pret ?? 10;
        $interets = ($request->montant * $tauxInteret * $request->duree_mois) / (100 * 12);
        $montantTotal = $request->montant + $interets;
        $mensualite = $montantTotal / $request->duree_mois;

        $loan = Loan::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'montant' => $request->montant,
            'taux_interet' => $tauxInteret,
            'duree_mois' => $request->duree_mois,
            'montant_interets' => $interets,
            'montant_total' => $montantTotal,
            'mensualite' => $mensualite,
            'motif' => $request->motif,
            'garantie' => $request->garantie,
            'statut' => Loan::STATUT_EN_ATTENTE,
            'date_demande' => now(),
        ]);

        AuditLog::logAction($user, 'demande_pret', Loan::class, $loan->id);

        return response()->json([
            'success' => true,
            'message' => 'Demande de prêt soumise avec succès. En attente d\'approbation.',
            'data' => [
                'loan' => $loan->load(['user:id,nom,prenom', 'group:id,nom']),
                'echeancier' => $loan->generateSchedule(),
            ]
        ], 201);
    }

    /**
     * Afficher un prêt
     */
    public function show(Request $request, Loan $loan): JsonResponse
    {
        $user = $request->user();

        if (!$loan->group->hasMember($user) && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé'
            ], 403);
        }

        $loan->load([
            'user:id,nom,prenom,telephone',
            'group:id,nom',
            'approvedBy:id,nom,prenom',
            'repayments'
        ]);

        $loan->echeancier = $loan->generateSchedule();
        $loan->progression = $loan->getProgressionPercentage();

        return response()->json([
            'success' => true,
            'data' => ['loan' => $loan]
        ]);
    }

    /**
     * Approuver un prêt
     */
    public function approve(Request $request, Loan $loan): JsonResponse
    {
        $user = $request->user();

        // Vérifier les permissions
        if (!$loan->group->isAdminOrTresorier($user) && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Permissions insuffisantes pour approuver un prêt'
            ], 403);
        }

        if ($loan->statut !== Loan::STATUT_EN_ATTENTE) {
            return response()->json([
                'success' => false,
                'message' => 'Ce prêt ne peut plus être approuvé'
            ], 422);
        }

        // Revérifier la disponibilité
        $disponible = $loan->group->getAvailableLoanAmount();
        if ($loan->montant > $disponible) {
            return response()->json([
                'success' => false,
                'message' => 'Fonds insuffisants pour approuver ce prêt'
            ], 422);
        }

        DB::beginTransaction();
        try {
            $loan->update([
                'statut' => Loan::STATUT_APPROUVE,
                'approved_by' => $user->id,
                'approved_at' => now(),
                'date_debut' => now(),
                'date_fin_prevue' => now()->addMonths($loan->duree_mois),
            ]);

            // Créer la transaction de décaissement
            $loan->transactions()->create([
                'user_id' => $loan->user_id,
                'group_id' => $loan->group_id,
                'reference' => 'PRT-' . now()->format('Ymd') . '-' . strtoupper(substr(uniqid(), -6)),
                'montant' => $loan->montant,
                'type' => 'decaissement_pret',
                'source' => 'fonds_groupe',
                'metadata' => ['loan_id' => $loan->id],
            ]);

            AuditLog::logAction($user, 'approbation_pret', Loan::class, $loan->id);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Prêt approuvé avec succès',
                'data' => ['loan' => $loan->fresh()->load('user:id,nom,prenom')]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'approbation',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Rejeter un prêt
     */
    public function reject(Request $request, Loan $loan): JsonResponse
    {
        $user = $request->user();

        if (!$loan->group->isAdminOrTresorier($user) && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Permissions insuffisantes'
            ], 403);
        }

        if ($loan->statut !== Loan::STATUT_EN_ATTENTE) {
            return response()->json([
                'success' => false,
                'message' => 'Ce prêt ne peut plus être rejeté'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'motif_rejet' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $loan->update([
            'statut' => Loan::STATUT_REJETE,
            'motif_rejet' => $request->motif_rejet,
            'rejected_by' => $user->id,
            'rejected_at' => now(),
        ]);

        AuditLog::logAction($user, 'rejet_pret', Loan::class, $loan->id);

        return response()->json([
            'success' => true,
            'message' => 'Prêt rejeté',
            'data' => ['loan' => $loan->fresh()]
        ]);
    }

    /**
     * Enregistrer un remboursement
     */
    public function repay(Request $request, Loan $loan): JsonResponse
    {
        $user = $request->user();

        // Le propriétaire ou un admin/trésorier peut enregistrer
        if ($loan->user_id !== $user->id && !$loan->group->isAdminOrTresorier($user) && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Permissions insuffisantes'
            ], 403);
        }

        if (!in_array($loan->statut, [Loan::STATUT_APPROUVE, Loan::STATUT_EN_COURS])) {
            return response()->json([
                'success' => false,
                'message' => 'Ce prêt n\'est pas actif'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'montant' => 'required|numeric|min:100',
            'moyen_paiement' => 'required|in:lumicash,ecocash,mpesa,especes,virement',
            'reference_paiement' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $restant = $loan->montant_total - $loan->montant_rembourse;
        $montant = min($request->montant, $restant); // Ne pas dépasser le restant

        DB::beginTransaction();
        try {
            // Créer la contribution de remboursement
            $contribution = Contribution::create([
                'group_id' => $loan->group_id,
                'user_id' => $loan->user_id,
                'loan_id' => $loan->id,
                'montant' => $montant,
                'type' => 'remboursement',
                'moyen_paiement' => $request->moyen_paiement,
                'reference_paiement' => $request->reference_paiement,
                'date_contribution' => now(),
                'statut' => Contribution::STATUT_VALIDE,
                'validated_by' => $user->id,
                'validated_at' => now(),
            ]);

            // Mettre à jour le prêt
            $loan->increment('montant_rembourse', $montant);
            
            if ($loan->statut === Loan::STATUT_APPROUVE) {
                $loan->update(['statut' => Loan::STATUT_EN_COURS]);
            }

            // Vérifier si le prêt est totalement remboursé
            if ($loan->fresh()->montant_rembourse >= $loan->montant_total) {
                $loan->update([
                    'statut' => Loan::STATUT_REMBOURSE,
                    'date_remboursement_complet' => now(),
                ]);
            }

            // Créer la transaction
            $contribution->transaction()->create([
                'user_id' => $loan->user_id,
                'group_id' => $loan->group_id,
                'reference' => 'RMB-' . now()->format('Ymd') . '-' . strtoupper(substr(uniqid(), -6)),
                'montant' => $montant,
                'type' => 'remboursement_pret',
                'source' => $request->moyen_paiement,
                'metadata' => [
                    'loan_id' => $loan->id,
                    'contribution_id' => $contribution->id,
                ],
            ]);

            AuditLog::logAction($user, 'remboursement_pret', Loan::class, $loan->id);

            DB::commit();

            $loan = $loan->fresh();

            return response()->json([
                'success' => true,
                'message' => 'Remboursement enregistré avec succès',
                'data' => [
                    'contribution' => $contribution,
                    'loan' => [
                        'id' => $loan->id,
                        'montant_total' => $loan->montant_total,
                        'montant_rembourse' => $loan->montant_rembourse,
                        'reste_a_payer' => $loan->montant_total - $loan->montant_rembourse,
                        'statut' => $loan->statut,
                        'progression' => $loan->getProgressionPercentage(),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du remboursement',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Obtenir l'échéancier de remboursement
     */
    public function repaymentSchedule(Request $request, Loan $loan): JsonResponse
    {
        $user = $request->user();

        if (!$loan->group->hasMember($user) && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'loan' => [
                    'id' => $loan->id,
                    'montant' => $loan->montant,
                    'montant_total' => $loan->montant_total,
                    'montant_rembourse' => $loan->montant_rembourse,
                    'mensualite' => $loan->mensualite,
                ],
                'echeancier' => $loan->generateSchedule(),
            ]
        ]);
    }
}
