<?php

/**
 * TWUNGURANE - MobileMoneyService
 * 
 * Service d'intégration avec les opérateurs Mobile Money du Burundi
 * Lumicash, EcoCash, M-Pesa
 * 
 * NOTE: Ce service est préparé pour l'intégration future avec les API réelles.
 * Les méthodes actuelles sont des simulations pour le développement.
 */

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MobileMoneyService
{
    /**
     * Opérateurs supportés
     */
    const PROVIDER_LUMICASH = 'lumicash';
    const PROVIDER_ECOCASH = 'ecocash';
    const PROVIDER_MPESA = 'mpesa';

    /**
     * Configuration des opérateurs
     */
    protected array $providers = [
        self::PROVIDER_LUMICASH => [
            'name' => 'Lumicash',
            'operator' => 'Lumitel',
            'prefix' => ['+25779', '+25761', '+25762'],
            'api_url' => 'https://api.lumicash.bi', // URL fictive
            'min_amount' => 100,
            'max_amount' => 5000000,
            'fees_percent' => 1.5,
        ],
        self::PROVIDER_ECOCASH => [
            'name' => 'EcoCash',
            'operator' => 'Econet Leo',
            'prefix' => ['+25771', '+25772'],
            'api_url' => 'https://api.ecocash.bi', // URL fictive
            'min_amount' => 100,
            'max_amount' => 3000000,
            'fees_percent' => 2.0,
        ],
        self::PROVIDER_MPESA => [
            'name' => 'M-Pesa',
            'operator' => 'Vodacom',
            'prefix' => ['+25776', '+25777', '+25778'],
            'api_url' => 'https://api.mpesa.vodacom.bi', // URL fictive
            'min_amount' => 500,
            'max_amount' => 4000000,
            'fees_percent' => 1.8,
        ],
    ];

    /**
     * Détecter l'opérateur à partir du numéro de téléphone
     * 
     * @param string $phone
     * @return string|null
     */
    public function detectProvider(string $phone): ?string
    {
        $phone = $this->normalizePhone($phone);

        foreach ($this->providers as $provider => $config) {
            foreach ($config['prefix'] as $prefix) {
                if (str_starts_with($phone, $prefix)) {
                    return $provider;
                }
            }
        }

        return null;
    }

    /**
     * Normaliser un numéro de téléphone au format international
     * 
     * @param string $phone
     * @return string
     */
    public function normalizePhone(string $phone): string
    {
        // Supprimer les espaces et caractères spéciaux
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // Ajouter le préfixe international si nécessaire
        if (str_starts_with($phone, '0')) {
            $phone = '+257' . substr($phone, 1);
        } elseif (!str_starts_with($phone, '+')) {
            $phone = '+257' . $phone;
        }

        return $phone;
    }

    /**
     * Valider un numéro de téléphone burundais
     * 
     * @param string $phone
     * @return bool
     */
    public function validatePhone(string $phone): bool
    {
        $phone = $this->normalizePhone($phone);
        return preg_match('/^\+257[67][0-9]{7}$/', $phone) === 1;
    }

    /**
     * Calculer les frais de transaction
     * 
     * @param string $provider
     * @param float $amount
     * @return array
     */
    public function calculateFees(string $provider, float $amount): array
    {
        $config = $this->providers[$provider] ?? null;

        if (!$config) {
            throw new \Exception('Opérateur non supporté');
        }

        $fees = ($amount * $config['fees_percent']) / 100;
        $fees = ceil($fees / 100) * 100; // Arrondir aux 100 FBU supérieurs

        return [
            'montant' => $amount,
            'frais' => $fees,
            'total' => $amount + $fees,
            'pourcentage_frais' => $config['fees_percent'],
        ];
    }

    /**
     * Initier un paiement entrant (collecte)
     * L'utilisateur paie vers le compte TWUNGURANE
     * 
     * @param User $user
     * @param float $amount
     * @param string $provider
     * @param string $description
     * @return array
     */
    public function initiateCollection(User $user, float $amount, string $provider, string $description = ''): array
    {
        $config = $this->providers[$provider] ?? null;

        if (!$config) {
            throw new \Exception('Opérateur non supporté');
        }

        if ($amount < $config['min_amount'] || $amount > $config['max_amount']) {
            throw new \Exception("Montant hors limites. Min: {$config['min_amount']} FBU, Max: {$config['max_amount']} FBU");
        }

        // Générer une référence unique
        $reference = 'COL-' . strtoupper(Str::random(12));

        // En production, appeler l'API Mobile Money ici
        // Pour le développement, on simule une réponse réussie

        Log::info('Mobile Money Collection Initiated', [
            'reference' => $reference,
            'provider' => $provider,
            'user_id' => $user->id,
            'phone' => $user->telephone,
            'amount' => $amount,
        ]);

        // Simulation de la réponse API
        $response = [
            'success' => true,
            'reference' => $reference,
            'provider' => $provider,
            'provider_name' => $config['name'],
            'phone' => $user->telephone,
            'amount' => $amount,
            'fees' => $this->calculateFees($provider, $amount),
            'status' => 'pending',
            'message' => "Veuillez confirmer le paiement de " . number_format($amount) . " FBU sur votre téléphone {$config['name']}",
            'expires_at' => now()->addMinutes(15)->toISOString(),
            'ussd_code' => $this->getUssdCode($provider, $amount, $reference),
        ];

        return $response;
    }

    /**
     * Initier un paiement sortant (décaissement)
     * TWUNGURANE paie vers le compte de l'utilisateur
     * 
     * @param User $user
     * @param float $amount
     * @param string $provider
     * @param string $description
     * @return array
     */
    public function initiateDisbursement(User $user, float $amount, string $provider, string $description = ''): array
    {
        $config = $this->providers[$provider] ?? null;

        if (!$config) {
            throw new \Exception('Opérateur non supporté');
        }

        // Générer une référence unique
        $reference = 'DIS-' . strtoupper(Str::random(12));

        Log::info('Mobile Money Disbursement Initiated', [
            'reference' => $reference,
            'provider' => $provider,
            'user_id' => $user->id,
            'phone' => $user->telephone,
            'amount' => $amount,
        ]);

        // Simulation de la réponse API
        return [
            'success' => true,
            'reference' => $reference,
            'provider' => $provider,
            'provider_name' => $config['name'],
            'phone' => $user->telephone,
            'amount' => $amount,
            'status' => 'processing',
            'message' => "Le montant de " . number_format($amount) . " FBU sera envoyé à votre compte {$config['name']}",
            'estimated_completion' => now()->addMinutes(5)->toISOString(),
        ];
    }

    /**
     * Vérifier le statut d'une transaction
     * 
     * @param string $reference
     * @param string $provider
     * @return array
     */
    public function checkTransactionStatus(string $reference, string $provider): array
    {
        // En production, appeler l'API Mobile Money pour vérifier le statut
        // Pour le développement, on simule différents statuts

        // Simulation: transactions alternent entre statuts
        $statuses = ['pending', 'completed', 'completed', 'failed'];
        $status = $statuses[crc32($reference) % count($statuses)];

        return [
            'reference' => $reference,
            'status' => $status,
            'provider' => $provider,
            'checked_at' => now()->toISOString(),
            'message' => match($status) {
                'pending' => 'Transaction en attente de confirmation',
                'completed' => 'Transaction réussie',
                'failed' => 'Transaction échouée',
                default => 'Statut inconnu',
            },
        ];
    }

    /**
     * Obtenir le code USSD pour un paiement manuel
     * 
     * @param string $provider
     * @param float $amount
     * @param string $reference
     * @return string
     */
    protected function getUssdCode(string $provider, float $amount, string $reference): string
    {
        // Codes USSD fictifs pour chaque opérateur
        return match($provider) {
            self::PROVIDER_LUMICASH => "*150*1*123456*{$amount}*{$reference}#",
            self::PROVIDER_ECOCASH => "*151*1*789012*{$amount}*{$reference}#",
            self::PROVIDER_MPESA => "*152*1*345678*{$amount}*{$reference}#",
            default => '',
        };
    }

    /**
     * Envoyer un SMS (simulation)
     * 
     * @param string $phone
     * @param string $message
     * @return bool
     */
    public function sendSms(string $phone, string $message): bool
    {
        $phone = $this->normalizePhone($phone);

        Log::info('SMS Sent (Simulation)', [
            'phone' => $phone,
            'message' => $message,
        ]);

        // En production, intégrer avec un fournisseur SMS (Africa's Talking, Twilio, etc.)
        return true;
    }

    /**
     * Obtenir la liste des opérateurs disponibles
     * 
     * @return array
     */
    public function getAvailableProviders(): array
    {
        return array_map(function ($key, $config) {
            return [
                'code' => $key,
                'name' => $config['name'],
                'operator' => $config['operator'],
                'min_amount' => $config['min_amount'],
                'max_amount' => $config['max_amount'],
                'fees_percent' => $config['fees_percent'],
            ];
        }, array_keys($this->providers), $this->providers);
    }

    /**
     * Callback pour les notifications de paiement (webhook)
     * 
     * @param array $payload
     * @param string $provider
     * @return array
     */
    public function handleCallback(array $payload, string $provider): array
    {
        Log::info('Mobile Money Callback Received', [
            'provider' => $provider,
            'payload' => $payload,
        ]);

        // Valider la signature du callback (en production)
        // $this->validateCallbackSignature($payload, $provider);

        $reference = $payload['reference'] ?? null;
        $status = $payload['status'] ?? 'unknown';

        if (!$reference) {
            return ['success' => false, 'message' => 'Référence manquante'];
        }

        // Mettre à jour la transaction correspondante
        $transaction = Transaction::where('reference', $reference)->first();

        if ($transaction) {
            $transaction->update([
                'metadata' => array_merge($transaction->metadata ?? [], [
                    'mobile_money_status' => $status,
                    'callback_received_at' => now()->toISOString(),
                ]),
            ]);
        }

        return [
            'success' => true,
            'reference' => $reference,
            'status' => $status,
            'processed_at' => now()->toISOString(),
        ];
    }
}
