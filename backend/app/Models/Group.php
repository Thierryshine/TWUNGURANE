<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modèle Group
 * TWUNGURANE - Groupes d'épargne communautaire
 * 
 * @property int $id
 * @property string $nom
 * @property string $type
 * @property string $localisation
 * @property string|null $province
 * @property string|null $commune
 * @property float $montant_contribution
 * @property string $frequence
 * @property int $duree_cycle
 * @property int $max_members
 * @property float $solde_total
 * @property int $created_by
 * @property string|null $description
 * @property string $statut
 */
class Group extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'nom',
        'type',
        'localisation',
        'province',
        'commune',
        'montant_contribution',
        'frequence',
        'duree_cycle',
        'max_members',
        'solde_total',
        'created_by',
        'description',
        'statut',
    ];

    protected $casts = [
        'montant_contribution' => 'decimal:2',
        'solde_total' => 'decimal:2',
        'duree_cycle' => 'integer',
        'max_members' => 'integer',
    ];

    /**
     * Relation: Créateur du groupe
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relation: Membres du groupe
     */
    public function members()
    {
        return $this->belongsToMany(User::class, 'group_members')
            ->withPivot('role_dans_groupe', 'date_adhesion', 'statut', 'total_contributions')
            ->withTimestamps();
    }

    /**
     * Relation: Membres via GroupMember
     */
    public function groupMembers()
    {
        return $this->hasMany(GroupMember::class);
    }

    /**
     * Relation: Contributions du groupe
     */
    public function contributions()
    {
        return $this->hasMany(Contribution::class);
    }

    /**
     * Relation: Prêts du groupe
     */
    public function loans()
    {
        return $this->hasMany(Loan::class);
    }

    /**
     * Relation: Transactions du groupe
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Obtenir le nombre de membres actifs
     */
    public function getActiveMembersCountAttribute(): int
    {
        return $this->groupMembers()->where('statut', 'actif')->count();
    }

    /**
     * Vérifier si le groupe peut accepter de nouveaux membres
     */
    public function canAcceptMembers(): bool
    {
        return $this->active_members_count < $this->max_members && $this->statut === 'actif';
    }
}
