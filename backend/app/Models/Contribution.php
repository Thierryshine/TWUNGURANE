<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modèle Contribution
 * TWUNGURANE - Contributions des membres
 */
class Contribution extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'group_id',
        'user_id',
        'montant',
        'type',
        'moyen_paiement',
        'date_contribution',
        'reference_externe',
        'notes',
        'statut',
        'enregistre_par',
    ];

    protected $casts = [
        'montant' => 'decimal:2',
        'date_contribution' => 'date',
    ];

    /**
     * Relation: Groupe
     */
    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * Relation: Utilisateur
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relation: Utilisateur qui a enregistré
     */
    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'enregistre_par');
    }

    /**
     * Scope: Contributions confirmées
     */
    public function scopeConfirmed($query)
    {
        return $query->where('statut', 'confirme');
    }

    /**
     * Scope: Par type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
