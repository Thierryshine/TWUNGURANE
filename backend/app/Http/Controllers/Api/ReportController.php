<?php

/**
 * TWUNGURANE - ReportController
 * 
 * Génération de rapports et statistiques
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\User;
use App\Models\GroupMember;
use App\Models\Contribution;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * Tableau de bord de l'utilisateur
     */
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Groupes de l'utilisateur
        $groups = Group::forUser($user)->active()->get();
        $groupIds = $groups->pluck('id');

        // Statistiques générales
        $stats = [
            'total_groupes' => $groups->count(),
            'total_epargne' => Contribution::whereIn('group_id', $groupIds)
                ->where('user_id', $user->id)
                ->where('type', 'epargne')
                ->where('statut', 'valide')
                ->sum('montant'),
            'prets_actifs' => Loan::whereIn('group_id', $groupIds)
                ->where('user_id', $user->id)
                ->active()
                ->count(),
            'total_emprunte' => Loan::whereIn('group_id', $groupIds)
                ->where('user_id', $user->id)
                ->whereIn('statut', ['approuve', 'en_cours', 'rembourse'])
                ->sum('montant'),
            'total_rembourse' => Loan::whereIn('group_id', $groupIds)
                ->where('user_id', $user->id)
                ->sum('montant_rembourse'),
        ];

        // Prochaines échéances
        $prochaines_echeances = Loan::whereIn('group_id', $groupIds)
            ->where('user_id', $user->id)
            ->active()
            ->orderBy('date_fin_prevue')
            ->limit(5)
            ->get(['id', 'montant_total', 'montant_rembourse', 'mensualite', 'date_fin_prevue']);

        // Contributions récentes
        $contributions_recentes = Contribution::whereIn('group_id', $groupIds)
            ->where('user_id', $user->id)
            ->with('group:id,nom')
            ->orderBy('date_contribution', 'desc')
            ->limit(5)
            ->get();

        // Évolution mensuelle sur 6 mois
        $evolution_mensuelle = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $evolution_mensuelle[] = [
                'mois' => $date->translatedFormat('M Y'),
                'epargne' => Contribution::whereIn('group_id', $groupIds)
                    ->where('user_id', $user->id)
                    ->where('type', 'epargne')
                    ->whereMonth('date_contribution', $date->month)
                    ->whereYear('date_contribution', $date->year)
                    ->sum('montant'),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'statistics' => $stats,
                'groupes' => $groups->map(fn($g) => [
                    'id' => $g->id,
                    'nom' => $g->nom,
                    'type' => $g->type,
                    'balance' => $g->balance,
                    'membres' => $g->active_members_count,
                ]),
                'prochaines_echeances' => $prochaines_echeances,
                'contributions_recentes' => $contributions_recentes,
                'evolution_mensuelle' => $evolution_mensuelle,
            ]
        ]);
    }

    /**
     * Rapport détaillé d'un groupe
     */
    public function groupReport(Request $request, Group $group): JsonResponse
    {
        $user = $request->user();

        if (!$group->hasMember($user) && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé'
            ], 403);
        }

        $periode = $request->get('periode', 'mois'); // mois, trimestre, annee
        $dateDebut = match($periode) {
            'trimestre' => now()->subMonths(3)->startOfMonth(),
            'annee' => now()->subYear()->startOfMonth(),
            default => now()->startOfMonth(),
        };

        // Statistiques du groupe
        $stats = $group->getStatistics();

        // Contributions par type
        $contributions_par_type = $group->contributions()
            ->where('date_contribution', '>=', $dateDebut)
            ->selectRaw('type, SUM(montant) as total, COUNT(*) as count')
            ->where('statut', 'valide')
            ->groupBy('type')
            ->get();

        // Contributions par membre
        $contributions_par_membre = $group->contributions()
            ->where('date_contribution', '>=', $dateDebut)
            ->where('type', 'epargne')
            ->where('statut', 'valide')
            ->selectRaw('user_id, SUM(montant) as total')
            ->groupBy('user_id')
            ->with('user:id,nom,prenom')
            ->orderBy('total', 'desc')
            ->get();

        // Prêts par statut
        $prets_par_statut = $group->loans()
            ->selectRaw('statut, COUNT(*) as count, SUM(montant) as total')
            ->groupBy('statut')
            ->get();

        // Évolution mensuelle
        $evolution = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $evolution[] = [
                'mois' => $date->translatedFormat('M Y'),
                'contributions' => $group->contributions()
                    ->where('type', 'epargne')
                    ->whereMonth('date_contribution', $date->month)
                    ->whereYear('date_contribution', $date->year)
                    ->sum('montant'),
                'prets_decaisses' => $group->loans()
                    ->whereMonth('approved_at', $date->month)
                    ->whereYear('approved_at', $date->year)
                    ->sum('montant'),
                'remboursements' => $group->contributions()
                    ->where('type', 'remboursement')
                    ->whereMonth('date_contribution', $date->month)
                    ->whereYear('date_contribution', $date->year)
                    ->sum('montant'),
            ];
        }

        // Taux de participation
        $membres_actifs = $group->memberships()->where('statut', 'actif')->count();
        $membres_ayant_cotise = $group->contributions()
            ->where('type', 'epargne')
            ->whereMonth('date_contribution', now()->month)
            ->distinct('user_id')
            ->count();
        
        $taux_participation = $membres_actifs > 0 
            ? round(($membres_ayant_cotise / $membres_actifs) * 100, 1) 
            : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'groupe' => [
                    'id' => $group->id,
                    'nom' => $group->nom,
                    'type' => $group->type,
                    'date_creation' => $group->created_at,
                ],
                'statistics' => $stats,
                'taux_participation' => $taux_participation,
                'contributions_par_type' => $contributions_par_type,
                'contributions_par_membre' => $contributions_par_membre,
                'prets_par_statut' => $prets_par_statut,
                'evolution_mensuelle' => $evolution,
            ]
        ]);
    }

    /**
     * Rapport d'un membre
     */
    public function memberReport(Request $request, GroupMember $member): JsonResponse
    {
        $user = $request->user();

        // Vérifier l'accès
        if ($member->user_id !== $user->id && !$member->group->isAdminOrTresorier($user) && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé'
            ], 403);
        }

        $group = $member->group;
        $memberUser = $member->user;

        // Statistiques du membre dans le groupe
        $stats = [
            'total_contributions' => $group->contributions()
                ->where('user_id', $memberUser->id)
                ->where('type', 'epargne')
                ->where('statut', 'valide')
                ->sum('montant'),
            'nombre_contributions' => $group->contributions()
                ->where('user_id', $memberUser->id)
                ->where('type', 'epargne')
                ->count(),
            'total_penalites' => $group->contributions()
                ->where('user_id', $memberUser->id)
                ->where('type', 'penalite')
                ->sum('montant'),
            'prets_recus' => $group->loans()
                ->where('user_id', $memberUser->id)
                ->whereIn('statut', ['approuve', 'en_cours', 'rembourse'])
                ->count(),
            'total_emprunte' => $group->loans()
                ->where('user_id', $memberUser->id)
                ->whereIn('statut', ['approuve', 'en_cours', 'rembourse'])
                ->sum('montant'),
            'total_rembourse' => $group->loans()
                ->where('user_id', $memberUser->id)
                ->sum('montant_rembourse'),
        ];

        // Score de fiabilité (basique)
        $totalAttendues = $member->created_at->diffInMonths(now()) * $group->montant_contribution;
        $score_fiabilite = $totalAttendues > 0 
            ? min(100, round(($stats['total_contributions'] / $totalAttendues) * 100))
            : 100;

        // Historique des contributions
        $historique = $group->contributions()
            ->where('user_id', $memberUser->id)
            ->orderBy('date_contribution', 'desc')
            ->limit(20)
            ->get();

        // Prêts du membre
        $prets = $group->loans()
            ->where('user_id', $memberUser->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'membre' => [
                    'id' => $member->id,
                    'user' => [
                        'id' => $memberUser->id,
                        'nom' => $memberUser->nom,
                        'prenom' => $memberUser->prenom,
                    ],
                    'role_dans_groupe' => $member->role_dans_groupe,
                    'date_adhesion' => $member->date_adhesion,
                ],
                'groupe' => [
                    'id' => $group->id,
                    'nom' => $group->nom,
                ],
                'statistics' => $stats,
                'score_fiabilite' => $score_fiabilite,
                'historique_contributions' => $historique,
                'prets' => $prets,
            ]
        ]);
    }

    /**
     * Résumé des contributions
     */
    public function contributionsSummary(Request $request): JsonResponse
    {
        $user = $request->user();
        $groupIds = Group::forUser($user)->pluck('id');

        $summary = Contribution::whereIn('group_id', $groupIds)
            ->selectRaw('
                type,
                moyen_paiement,
                COUNT(*) as nombre,
                SUM(montant) as total,
                AVG(montant) as moyenne
            ')
            ->where('statut', 'valide')
            ->groupBy('type', 'moyen_paiement')
            ->get();

        // Par mois
        $parMois = Contribution::whereIn('group_id', $groupIds)
            ->where('statut', 'valide')
            ->selectRaw('DATE_FORMAT(date_contribution, "%Y-%m") as mois, type, SUM(montant) as total')
            ->groupBy('mois', 'type')
            ->orderBy('mois', 'desc')
            ->limit(36)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $summary,
                'par_mois' => $parMois,
            ]
        ]);
    }

    /**
     * Résumé des prêts
     */
    public function loansSummary(Request $request): JsonResponse
    {
        $user = $request->user();
        $groupIds = Group::forUser($user)->pluck('id');

        $summary = Loan::whereIn('group_id', $groupIds)
            ->selectRaw('
                statut,
                COUNT(*) as nombre,
                SUM(montant) as total_principal,
                SUM(montant_interets) as total_interets,
                SUM(montant_rembourse) as total_rembourse,
                AVG(taux_interet) as taux_moyen
            ')
            ->groupBy('statut')
            ->get();

        // Prêts en retard
        $enRetard = Loan::whereIn('group_id', $groupIds)
            ->active()
            ->where('date_fin_prevue', '<', now())
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $summary,
                'prets_en_retard' => $enRetard,
            ]
        ]);
    }

    /**
     * Export des données
     */
    public function export(Request $request, string $type): JsonResponse
    {
        $user = $request->user();
        $groupIds = Group::forUser($user)->pluck('id');

        $data = match($type) {
            'contributions' => Contribution::whereIn('group_id', $groupIds)
                ->with(['user:id,nom,prenom', 'group:id,nom'])
                ->where('statut', 'valide')
                ->orderBy('date_contribution', 'desc')
                ->get(),
            'loans' => Loan::whereIn('group_id', $groupIds)
                ->with(['user:id,nom,prenom', 'group:id,nom'])
                ->orderBy('created_at', 'desc')
                ->get(),
            'transactions' => Transaction::whereIn('group_id', $groupIds)
                ->with(['user:id,nom,prenom', 'group:id,nom'])
                ->orderBy('created_at', 'desc')
                ->get(),
            default => null,
        };

        if ($data === null) {
            return response()->json([
                'success' => false,
                'message' => 'Type d\'export non valide'
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
            'export_date' => now()->toISOString(),
            'total_records' => $data->count(),
        ]);
    }

    /**
     * Statistiques admin (admin uniquement)
     */
    public function adminStatistics(Request $request): JsonResponse
    {
        $stats = [
            'utilisateurs' => [
                'total' => User::count(),
                'actifs' => User::active()->count(),
                'nouveaux_ce_mois' => User::whereMonth('created_at', now()->month)->count(),
            ],
            'groupes' => [
                'total' => Group::count(),
                'actifs' => Group::active()->count(),
                'par_type' => Group::selectRaw('type, COUNT(*) as count')->groupBy('type')->get(),
            ],
            'contributions' => [
                'total_montant' => Contribution::where('statut', 'valide')->sum('montant'),
                'ce_mois' => Contribution::where('statut', 'valide')
                    ->whereMonth('date_contribution', now()->month)
                    ->sum('montant'),
            ],
            'prets' => [
                'total_octroyes' => Loan::whereIn('statut', ['approuve', 'en_cours', 'rembourse'])->sum('montant'),
                'en_cours' => Loan::active()->sum('montant'),
                'taux_remboursement' => $this->calculateRepaymentRate(),
            ],
            'par_province' => Group::selectRaw('province, COUNT(*) as groupes')
                ->groupBy('province')
                ->orderBy('groupes', 'desc')
                ->get(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Logs d'audit (admin uniquement)
     */
    public function auditLogs(Request $request): JsonResponse
    {
        $query = AuditLog::with('user:id,nom,prenom');

        if ($request->has('action')) {
            $query->where('action', $request->action);
        }
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->has('model')) {
            $query->where('model', $request->model);
        }
        if ($request->has('date_debut')) {
            $query->whereDate('created_at', '>=', $request->date_debut);
        }

        $logs = $query->orderBy('created_at', 'desc')->paginate(50);

        return response()->json([
            'success' => true,
            'data' => $logs
        ]);
    }

    /**
     * Calculer le taux de remboursement global
     */
    private function calculateRepaymentRate(): float
    {
        $totalDu = Loan::whereIn('statut', ['approuve', 'en_cours', 'rembourse'])->sum('montant_total');
        $totalRembourse = Loan::sum('montant_rembourse');
        
        return $totalDu > 0 ? round(($totalRembourse / $totalDu) * 100, 1) : 0;
    }
}
