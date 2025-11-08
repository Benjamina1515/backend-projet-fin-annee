<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Projet extends Model
{
    use HasFactory;

    protected $fillable = [
        'prof_id',
        'titre',
        'description',
        'nb_par_groupe',
        'date_creation',
        'niveaux',
        'date_debut',
        'date_fin',
    ];

    protected $casts = [
        'date_creation' => 'datetime',
        'date_debut' => 'date',
        'date_fin' => 'date',
        'niveaux' => 'array',
    ];

    /**
     * Relation avec Prof
     */
    public function prof(): BelongsTo
    {
        return $this->belongsTo(Prof::class);
    }

    /**
     * Relation avec Sujets
     */
    public function sujets(): HasMany
    {
        return $this->hasMany(Sujet::class);
    }

    /**
     * Relation avec Groupes
     */
    public function groupes(): HasMany
    {
        return $this->hasMany(Groupe::class);
    }

    /**
     * Accessor pour le nombre de groupes
     */
    public function getNbGroupesAttribute(): int
    {
        return $this->groupes()->count();
    }

    /**
     * Relation avec Taches
     */
    public function taches(): HasMany
    {
        return $this->hasMany(Tache::class);
    }
}
