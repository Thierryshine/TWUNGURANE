<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Modèle Transaction
 * TWUNGURANE - Historique immuable de toutes les transactions
 */
class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'user_id',
        'group_id',
        'montant',
        'type',
        'source',
        'metadata',
        'statut',
        'description',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'montant' => 'decimal:2',
        'metadata' => 'array',
    ];

    /**
     * Générer une référence unique
     */
    public static function generateReference(): string
    {
        return 'TXN-' . date('Ymd') . '-' . strtoupper(uniqid());
    }

    /**
     * Relation: Utilisateur
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relation: Groupe
     */
    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * Scope: Transactions traitées
     */
    public function scopeProcessed($query)
    {
        return $query->where('statut', 'traite');
    }

    /**
     * Scope: Par type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
