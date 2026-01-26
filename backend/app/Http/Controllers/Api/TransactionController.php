<?php

/**
 * TWUNGURANE - TransactionController
 * 
 * Gestion de l'historique des transactions
 * Consultation et traçabilité
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TransactionController extends Controller
{
    /**
     * Liste des transactions de l'utilisateur
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Transaction::whereHas('group', function($q) use ($user) {
            $q->forUser($user);
        })->with(['user:id,nom,prenom', 'group:id,nom']);

        // Filtres
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }
        if ($request->has('source')) {
            $query->where('source', $request->source);
        }
        if ($request->has('group_id')) {
            $query->where('group_id', $request->group_id);
        }
        if ($request->has('date_debut')) {
            $query->whereDate('created_at', '>=', $request->date_debut);
        }
        if ($request->has('date_fin')) {
            $query->whereDate('created_at', '<=', $request->date_fin);
        }
        if ($request->has('search')) {
            $query->where('reference', 'like', "%{$request->search}%");
        }

        // Tri
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $transactions = $query->paginate($request->get('per_page', 20));

        // Statistiques globales
        $stats = [
            'total_entrees' => Transaction::whereHas('group', function($q) use ($user) {
                $q->forUser($user);
            })->whereIn('type', ['contribution_epargne', 'contribution_penalite', 'remboursement_pret'])->sum('montant'),
            
            'total_sorties' => Transaction::whereHas('group', function($q) use ($user) {
                $q->forUser($user);
            })->whereIn('type', ['decaissement_pret'])->sum('montant'),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'transactions' => $transactions,
                'statistics' => $stats,
            ]
        ]);
    }

    /**
     * Transactions d'un groupe spécifique
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

        $query = $group->transactions()->with(['user:id,nom,prenom']);

        // Filtres
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->has('date_debut')) {
            $query->whereDate('created_at', '>=', $request->date_debut);
        }
        if ($request->has('date_fin')) {
            $query->whereDate('created_at', '<=', $request->date_fin);
        }

        $transactions = $query->orderBy('created_at', 'desc')->paginate(20);

        // Résumé par type
        $summary = $group->transactions()
            ->selectRaw('type, SUM(montant) as total, COUNT(*) as count')
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        return response()->json([
            'success' => true,
            'data' => [
                'transactions' => $transactions,
                'summary' => $summary,
            ]
        ]);
    }

    /**
     * Afficher une transaction
     */
    public function show(Request $request, Transaction $transaction): JsonResponse
    {
        $user = $request->user();

        // Vérifier l'accès
        if ($transaction->group && !$transaction->group->hasMember($user) && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé'
            ], 403);
        }

        $transaction->load([
            'user:id,nom,prenom,telephone',
            'group:id,nom',
            'contribution',
            'loan'
        ]);

        return response()->json([
            'success' => true,
            'data' => ['transaction' => $transaction]
        ]);
    }
}
