<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modèle Loan
 * TWUNGURANE - Prêts VSLA
 */
class Loan extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'group_id',
        'user_id',
        'montant',
        'taux_interet',
        'duree',
        'montant_rembourse',
        'montant_restant',
        'motif',
        'statut',
        'approuve_par',
        'approved_at',
        'date_echeance',
        'date_remboursement_complet',
        'notes_approbation',
    ];

    protected $casts = [
        'montant' => 'decimal:2',
        'taux_interet' => 'decimal:2',
        'montant_rembourse' => 'decimal:2',
        'montant_restant' => 'decimal:2',
        'duree' => 'integer',
        'approved_at' => 'datetime',
        'date_echeance' => 'date',
        'date_remboursement_complet' => 'date',
    ];

    /**
     * Relation: Groupe
     */
    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * Relation: Utilisateur emprunteur
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relation: Utilisateur qui a approuvé
     */
    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approuve_par');
    }

    /**
     * Calculer le montant total avec intérêts
     */
    public function getMontantTotalAttribute(): float
    {
        return $this->montant * (1 + ($this->taux_interet / 100));
    }

    /**
     * Calculer le pourcentage de remboursement
     */
    public function getPourcentageRembourseAttribute(): float
    {
        if ($this->montant == 0) {
            return 0;
        }
        return ($this->montant_rembourse / $this->montant) * 100;
    }

    /**
     * Vérifier si le prêt est complètement remboursé
     */
    public function isFullyRepaid(): bool
    {
        return $this->montant_restant <= 0;
    }

    /**
     * Scope: Prêts en attente
     */
    public function scopePending($query)
    {
        return $query->where('statut', 'en_attente');
    }

    /**
     * Scope: Prêts actifs
     */
    public function scopeActive($query)
    {
        return $query->where('statut', 'actif');
    }
}
