<?php

/**
 * TWUNGURANE - PythonApiService
 * 
 * Service de communication avec le microservice Python/FastAPI
 * Gère les appels aux endpoints d'analyse financière
 */

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Client\RequestException;

class PythonApiService
{
    /**
     * URL du service Python
     */
    protected string $baseUrl;

    /**
     * Token d'authentification interne
     */
    protected string $token;

    /**
     * Timeout en secondes
     */
    protected int $timeout = 30;

    /**
     * Nombre de tentatives en cas d'échec
     */
    protected int $retries = 3;

    /**
     * Délai entre les tentatives (ms)
     */
    protected int $retryDelay = 1000;

    /**
     * Constructeur
     */
    public function __construct()
    {
        $this->baseUrl = config('services.python.url', env('PYTHON_SERVICE_URL', 'http://python:8000'));
        $this->token = config('services.python.token', env('PYTHON_SERVICE_TOKEN', 'twungurane_internal_token_2024'));
    }

    /**
     * Effectuer une requête GET
     * 
     * @param string $endpoint
     * @param array $params
     * @return array
     * @throws \Exception
     */
    public function get(string $endpoint, array $params = []): array
    {
        return $this->request('GET', $endpoint, $params);
    }

    /**
     * Effectuer une requête POST
     * 
     * @param string $endpoint
     * @param array $data
     * @return array
     * @throws \Exception
     */
    public function post(string $endpoint, array $data = []): array
    {
        return $this->request('POST', $endpoint, $data);
    }

    /**
     * Effectuer une requête HTTP vers le service Python
     * 
     * @param string $method
     * @param string $endpoint
     * @param array $data
     * @return array
     * @throws \Exception
     */
    protected function request(string $method, string $endpoint, array $data = []): array
    {
        $url = rtrim($this->baseUrl, '/') . '/api/v1/' . ltrim($endpoint, '/');

        $attempt = 0;
        $lastException = null;

        while ($attempt < $this->retries) {
            $attempt++;

            try {
                $client = Http::withToken($this->token)
                    ->timeout($this->timeout)
                    ->acceptJson();

                $response = match (strtoupper($method)) {
                    'GET' => $client->get($url, $data),
                    'POST' => $client->post($url, $data),
                    'PUT' => $client->put($url, $data),
                    'DELETE' => $client->delete($url, $data),
                    default => throw new \Exception("Méthode HTTP non supportée: {$method}"),
                };

                if ($response->successful()) {
                    return $response->json();
                }

                // Gérer les erreurs HTTP
                if ($response->status() === 401) {
                    throw new \Exception('Token d\'authentification invalide pour le service Python');
                }

                if ($response->status() === 404) {
                    throw new \Exception("Endpoint non trouvé: {$endpoint}");
                }

                if ($response->status() >= 500) {
                    // Erreur serveur, on peut réessayer
                    $lastException = new \Exception("Erreur serveur Python: " . $response->body());
                    Log::warning("Python API error (attempt {$attempt})", [
                        'endpoint' => $endpoint,
                        'status' => $response->status(),
                        'response' => $response->body(),
                    ]);
                    usleep($this->retryDelay * 1000);
                    continue;
                }

                // Autres erreurs
                throw new \Exception("Erreur Python API: " . ($response->json()['detail'] ?? $response->body()));

            } catch (RequestException $e) {
                $lastException = $e;
                Log::warning("Python API connection error (attempt {$attempt})", [
                    'endpoint' => $endpoint,
                    'error' => $e->getMessage(),
                ]);
                usleep($this->retryDelay * 1000);
            }
        }

        Log::error('Python API failed after all retries', [
            'endpoint' => $endpoint,
            'attempts' => $attempt,
        ]);

        throw new \Exception('Service d\'analyse temporairement indisponible. Veuillez réessayer.');
    }

    /**
     * Vérifier la santé du service Python
     * 
     * @return array
     */
    public function healthCheck(): array
    {
        try {
            $response = Http::timeout(5)->get($this->baseUrl . '/health');
            
            return [
                'status' => $response->successful() ? 'healthy' : 'unhealthy',
                'response' => $response->json(),
                'response_time_ms' => $response->transferStats?->getTransferTime() * 1000,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unreachable',
                'error' => $e->getMessage(),
            ];
        }
    }

    // =========================================================================
    // ENDPOINTS D'ANALYSE
    // =========================================================================

    /**
     * Calculer le score de risque d'un membre
     * 
     * @param array $memberData
     * @return array
     */
    public function calculateRiskScore(array $memberData): array
    {
        $cacheKey = 'risk_score_' . md5(json_encode($memberData));

        return Cache::remember($cacheKey, 300, function () use ($memberData) {
            return $this->post('risk-score', $memberData);
        });
    }

    /**
     * Obtenir une projection financière
     * 
     * @param array $projectionParams
     * @return array
     */
    public function getFinancialProjection(array $projectionParams): array
    {
        return $this->post('financial-projection', $projectionParams);
    }

    /**
     * Obtenir le score de santé d'un groupe
     * 
     * @param int $groupId
     * @param array $groupData
     * @return array
     */
    public function getGroupHealth(int $groupId, array $groupData): array
    {
        $cacheKey = "group_health_{$groupId}_" . md5(json_encode($groupData));

        return Cache::remember($cacheKey, 600, function () use ($groupData) {
            return $this->post('group-health', $groupData);
        });
    }

    /**
     * Obtenir le classement des membres d'un groupe
     * 
     * @param int $groupId
     * @param array $membersData
     * @return array
     */
    public function getMemberRanking(int $groupId, array $membersData): array
    {
        $cacheKey = "member_ranking_{$groupId}_" . md5(json_encode($membersData));

        return Cache::remember($cacheKey, 300, function () use ($groupId, $membersData) {
            return $this->get("member-ranking/{$groupId}", ['members' => $membersData]);
        });
    }

    /**
     * Simuler un cycle d'épargne
     * 
     * @param array $simulationParams
     * @return array
     */
    public function simulateCycle(array $simulationParams): array
    {
        return $this->post('cycle-simulation', $simulationParams);
    }

    /**
     * Préparer les données d'un groupe pour l'analyse
     * 
     * @param \App\Models\Group $group
     * @return array
     */
    public function prepareGroupData(\App\Models\Group $group): array
    {
        $group->load(['members.user', 'contributions', 'loans']);

        return [
            'group_id' => $group->id,
            'type' => $group->type,
            'montant_contribution' => $group->montant_contribution,
            'frequence' => $group->frequence,
            'duree_cycle' => $group->duree_cycle,
            'date_debut' => $group->date_debut_cycle?->format('Y-m-d'),
            'balance' => $group->balance,
            'membres' => $group->members->map(function ($member) {
                return [
                    'id' => $member->id,
                    'user_id' => $member->user_id,
                    'role' => $member->role_dans_groupe,
                    'date_adhesion' => $member->date_adhesion?->format('Y-m-d'),
                    'statut' => $member->statut,
                ];
            })->toArray(),
            'contributions' => $group->contributions->map(function ($c) {
                return [
                    'user_id' => $c->user_id,
                    'montant' => $c->montant,
                    'type' => $c->type,
                    'date' => $c->date_contribution?->format('Y-m-d'),
                ];
            })->toArray(),
            'prets' => $group->loans->map(function ($l) {
                return [
                    'user_id' => $l->user_id,
                    'montant' => $l->montant,
                    'montant_restant' => $l->montant_restant,
                    'statut' => $l->statut,
                    'date_creation' => $l->created_at?->format('Y-m-d'),
                ];
            })->toArray(),
            'statistiques' => [
                'total_contributions' => $group->total_contributions,
                'total_prets' => $group->active_loans_amount,
                'membres_actifs' => $group->active_members_count,
            ],
        ];
    }

    /**
     * Préparer les données d'un membre pour l'analyse de risque
     * 
     * @param \App\Models\User $user
     * @param \App\Models\Group $group
     * @return array
     */
    public function prepareMemberData(\App\Models\User $user, \App\Models\Group $group): array
    {
        $membership = $group->members()->where('user_id', $user->id)->first();
        
        $contributions = $group->contributions()
            ->where('user_id', $user->id)
            ->get();

        $loans = $group->loans()
            ->where('user_id', $user->id)
            ->get();

        return [
            'user_id' => $user->id,
            'group_id' => $group->id,
            'date_adhesion' => $membership?->date_adhesion?->format('Y-m-d'),
            'role' => $membership?->role_dans_groupe,
            'contributions' => [
                'total' => $contributions->where('type', 'epargne')->sum('montant'),
                'count' => $contributions->where('type', 'epargne')->count(),
                'historique' => $contributions->map(function ($c) {
                    return [
                        'montant' => $c->montant,
                        'type' => $c->type,
                        'date' => $c->date_contribution?->format('Y-m-d'),
                    ];
                })->toArray(),
            ],
            'prets' => [
                'total_emprunte' => $loans->sum('montant'),
                'total_rembourse' => $loans->sum('montant') - $loans->sum('montant_restant'),
                'en_cours' => $loans->whereIn('statut', ['approuve', 'en_cours'])->sum('montant_restant'),
                'historique' => $loans->map(function ($l) {
                    return [
                        'montant' => $l->montant,
                        'statut' => $l->statut,
                        'date_creation' => $l->created_at?->format('Y-m-d'),
                    ];
                })->toArray(),
            ],
            'groupe' => [
                'montant_contribution' => $group->montant_contribution,
                'frequence' => $group->frequence,
            ],
        ];
    }
}
