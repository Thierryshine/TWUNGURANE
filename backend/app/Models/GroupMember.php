<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modèle GroupMember
 * TWUNGURANE - Table pivot pour les membres des groupes
 */
class GroupMember extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'group_id',
        'user_id',
        'role_dans_groupe',
        'date_adhesion',
        'statut',
        'total_contributions',
        'nombre_contributions',
        'notes',
    ];

    protected $casts = [
        'date_adhesion' => 'date',
        'total_contributions' => 'decimal:2',
        'nombre_contributions' => 'integer',
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
     * Vérifier si le membre est actif
     */
    public function isActive(): bool
    {
        return $this->statut === 'actif';
    }

    /**
     * Vérifier si le membre est administrateur du groupe
     */
    public function isGroupAdmin(): bool
    {
        return $this->role_dans_groupe === 'admin';
    }

    /**
     * Vérifier si le membre est trésorier du groupe
     */
    public function isGroupTreasurer(): bool
    {
        return $this->role_dans_groupe === 'tresorier';
    }
}
