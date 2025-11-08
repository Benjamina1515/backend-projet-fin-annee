<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Etudiant extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'matricule',
        'filiere',
        'niveau',
        'avatar',
    ];

    /**
     * Relation avec User
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relation avec Groupes (many-to-many)
     */
    public function groupes(): BelongsToMany
    {
        return $this->belongsToMany(Groupe::class, 'etudiant_groupe')
            ->withTimestamps();
    }

    /**
     * Relation avec Taches (one-to-many)
     */
    public function taches(): HasMany
    {
        return $this->hasMany(Tache::class);
    }
}

