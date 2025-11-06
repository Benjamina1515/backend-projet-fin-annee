<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Groupe extends Model
{
    use HasFactory;

    protected $fillable = [
        'projet_id',
        'sujet_id',
        'numero_groupe',
    ];

    /**
     * Relation avec Projet
     */
    public function projet(): BelongsTo
    {
        return $this->belongsTo(Projet::class);
    }

    /**
     * Relation avec Sujet
     */
    public function sujet(): BelongsTo
    {
        return $this->belongsTo(Sujet::class);
    }

    /**
     * Relation avec Etudiants (many-to-many)
     */
    public function etudiants(): BelongsToMany
    {
        return $this->belongsToMany(Etudiant::class, 'etudiant_groupe')
            ->withTimestamps();
    }

    /**
     * Accessor pour le nombre d'étudiants
     */
    public function getNbEtudiantsAttribute(): int
    {
        return $this->etudiants()->count();
    }
}
