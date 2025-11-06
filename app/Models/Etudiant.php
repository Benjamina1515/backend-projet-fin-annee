<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}

