<?php

/**
 * TWUNGURANE - AnalyticsController
 * 
 * Proxy vers le microservice Python pour les analyses financières
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Services\PythonApiService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class AnalyticsController extends Controller
{
    protected PythonApiService $pythonApi;

    public function __construct(PythonApiService $pythonApi)
    {
        $this->pythonApi = $pythonApi;
    }

    /**
     * Calculer le score de risque d'un membre
     */
    public function riskScore(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'group_id' => 'required|exists:groups,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $group = Group::find($request->group_id);

        // Vérifier l'accès
        if (!$group->isAdminOrTresorier($user) && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Permissions insuffisantes'
            ], 403);
        }

        try {
            $result = $this->pythonApi->calculateRiskScore(
                $request->user_id,
                $request->group_id
            );

            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du calcul du score de risque',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Projection financière
     */
    public function financialProjection(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'group_id' => 'required|exists:groups,id',
            'mois' => 'required|integer|min:1|max:24',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $group = Group::find($request->group_id);

        if (!$group->hasMember($user) && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé'
            ], 403);
        }

        try {
            $result = $this->pythonApi->getFinancialProjection(
                $request->group_id,
                $request->mois
            );

            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la projection',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Indicateurs de santé d'un groupe
     */
    public function groupHealth(Request $request, Group $group): JsonResponse
    {
        $user = $request->user();

        if (!$group->hasMember($user) && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé'
            ], 403);
        }

        try {
            $result = $this->pythonApi->getGroupHealth($group->id);

            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'analyse de santé',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Classement des membres d'un groupe
     */
    public function memberRanking(Request $request, Group $group): JsonResponse
    {
        $user = $request->user();

        if (!$group->hasMember($user) && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé'
            ], 403);
        }

        try {
            $result = $this->pythonApi->getMemberRanking($group->id);

            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du classement',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Simulation de cycle d'épargne
     */
    public function cycleSimulation(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'montant_contribution' => 'required|numeric|min:1000',
            'nombre_membres' => 'required|integer|min:2|max:50',
            'frequence' => 'required|in:hebdomadaire,bimensuelle,mensuelle',
            'duree_mois' => 'required|integer|min:1|max:24',
            'taux_interet_pret' => 'nullable|numeric|min:0|max:50',
            'taux_utilisation_fonds' => 'nullable|numeric|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->pythonApi->simulateCycle([
                'montant_contribution' => $request->montant_contribution,
                'nombre_membres' => $request->nombre_membres,
                'frequence' => $request->frequence,
                'duree_mois' => $request->duree_mois,
                'taux_interet_pret' => $request->taux_interet_pret ?? 10,
                'taux_utilisation_fonds' => $request->taux_utilisation_fonds ?? 80,
            ]);

            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la simulation',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
