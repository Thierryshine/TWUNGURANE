<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Modèle User
 * TWUNGURANE - Gestion des utilisateurs
 * 
 * @property int $id
 * @property string $nom
 * @property string $prenom
 * @property string $telephone
 * @property string|null $email
 * @property string $password
 * @property string $role
 * @property string $statut
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * Les attributs qui sont assignables en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nom',
        'prenom',
        'telephone',
        'email',
        'password',
        'role',
        'statut',
    ];

    /**
     * Les attributs qui doivent être cachés pour la sérialisation.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Les attributs qui doivent être convertis.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Relation: Groupes où l'utilisateur est membre
     */
    public function groups()
    {
        return $this->belongsToMany(Group::class, 'group_members')
            ->withPivot('role_dans_groupe', 'date_adhesion', 'statut', 'total_contributions')
            ->withTimestamps();
    }

    /**
     * Relation: Membres des groupes (via group_members)
     */
    public function groupMemberships()
    {
        return $this->hasMany(GroupMember::class);
    }

    /**
     * Relation: Contributions de l'utilisateur
     */
    public function contributions()
    {
        return $this->hasMany(Contribution::class);
    }

    /**
     * Relation: Prêts de l'utilisateur
     */
    public function loans()
    {
        return $this->hasMany(Loan::class);
    }

    /**
     * Relation: Transactions de l'utilisateur
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Relation: Logs d'audit créés par l'utilisateur
     */
    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }

    /**
     * Groupes créés par l'utilisateur
     */
    public function createdGroups()
    {
        return $this->hasMany(Group::class, 'created_by');
    }

    /**
     * Vérifier si l'utilisateur est administrateur
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Vérifier si l'utilisateur est trésorier
     */
    public function isTreasurer(): bool
    {
        return $this->role === 'tresorier';
    }

    /**
     * Vérifier si l'utilisateur est membre
     */
    public function isMember(): bool
    {
        return $this->role === 'membre';
    }

    /**
     * Vérifier si l'utilisateur est actif
     */
    public function isActive(): bool
    {
        return $this->statut === 'actif';
    }

    /**
     * Obtenir le nom complet
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->prenom} {$this->nom}";
    }
}
