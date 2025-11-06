<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sujet extends Model
{
    use HasFactory;

    protected $fillable = [
        'projet_id',
        'titre_sujet',
        'description',
    ];

    /**
     * Relation avec Projet
     */
    public function projet(): BelongsTo
    {
        return $this->belongsTo(Projet::class);
    }

    /**
     * Relation avec Groupes
     */
    public function groupes(): HasMany
    {
        return $this->hasMany(Groupe::class);
    }
}
