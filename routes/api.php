<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ProfController;
use App\Http\Controllers\EtudiantController;
use App\Http\Controllers\ProjetController;
use App\Http\Controllers\TacheController;
use Illuminate\Support\Facades\Route;

// Routes d'authentification
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::get('/auth/me', [AuthController::class, 'me'])->middleware('auth:sanctum');
Route::get('/auth/verify', [AuthController::class, 'verify'])->middleware('auth:sanctum');

// Routes de gestion des utilisateurs (Admin uniquement)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::post('/users/{id}', [UserController::class, 'update']); // Pour FormData avec _method=PUT
    Route::delete('/users/{id}', [UserController::class, 'destroy']);
    
    // Routes pour les professeurs (Admin uniquement)
    Route::post('/profs', [ProfController::class, 'store']);
    Route::put('/profs/{id}', [ProfController::class, 'update']);
    Route::post('/profs/{id}', [ProfController::class, 'update']); // Pour FormData avec _method=PUT
    Route::delete('/profs/{id}', [ProfController::class, 'destroy']);
    
    // Routes pour les étudiants (Admin uniquement)
    Route::post('/etudiants', [EtudiantController::class, 'store']);
    Route::put('/etudiants/{id}', [EtudiantController::class, 'update']);
    Route::post('/etudiants/{id}', [EtudiantController::class, 'update']); // Pour FormData avec _method=PUT
    Route::delete('/etudiants/{id}', [EtudiantController::class, 'destroy']);
    
    // Routes pour les projets (Prof uniquement)
    Route::get('/projets', [ProjetController::class, 'index']);
    // Route pour admin : voir tous les projets
    Route::get('/admin/projets', [ProjetController::class, 'indexAll']);
    Route::post('/projets', [ProjetController::class, 'store']);
    Route::get('/projets/{id}', [ProjetController::class, 'show']);
    Route::put('/projets/{id}', [ProjetController::class, 'update']);
    Route::delete('/projets/{id}', [ProjetController::class, 'destroy']);
    Route::post('/sujets', [ProjetController::class, 'storeSujet']);
    Route::put('/sujets/{id}', [ProjetController::class, 'updateSujet']);
    Route::delete('/sujets/{id}', [ProjetController::class, 'deleteSujet']);
    Route::post('/projets/{id}/repartition', [ProjetController::class, 'repartirEtudiants']);
    Route::post('/projets/{id}/reassigner-sujets', [ProjetController::class, 'reassignerSujetsAuxGroupes']);
    // Route pour étudiant : voir ses projets
    Route::get('/student/projets', [ProjetController::class, 'getStudentProjects']);
    
    // Routes pour les tâches (Étudiant uniquement)
    Route::get('/student/taches', [TacheController::class, 'index']);
    Route::get('/student/taches/stats', [TacheController::class, 'stats']);
    Route::get('/student/taches/{id}', [TacheController::class, 'show']);
    Route::post('/student/taches', [TacheController::class, 'store']);
    Route::put('/student/taches/{id}', [TacheController::class, 'update']);
    Route::patch('/student/taches/{id}/statut', [TacheController::class, 'updateStatus']);
    Route::delete('/student/taches/{id}', [TacheController::class, 'destroy']);
    
    // Routes pour les tâches des étudiants (Professeur uniquement)
    Route::get('/professor/taches', [TacheController::class, 'getProfessorStudentTasks']);
});

