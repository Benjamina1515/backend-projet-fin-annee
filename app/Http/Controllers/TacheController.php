<?php

namespace App\Http\Controllers;

use App\Models\Tache;
use App\Models\Etudiant;
use App\Models\Projet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TacheController extends Controller
{
    /**
     * Récupérer toutes les tâches de l'étudiant connecté
     */
    public function index(Request $request)
    {
        // Vérifier que l'utilisateur est un étudiant
        if ($request->user()->role !== 'etudiant') {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé. Seuls les étudiants peuvent accéder à cette ressource.',
            ], 403);
        }

        $user = $request->user();
        $etudiant = $user->etudiant;

        if (!$etudiant) {
            return response()->json([
                'success' => false,
                'message' => 'Profil étudiant non trouvé.',
            ], 404);
        }

        $taches = Tache::where('etudiant_id', $etudiant->id)
            ->with(['projet' => function ($query) {
                $query->select('id', 'titre');
            }, 'etudiant' => function ($query) {
                $query->select('id', 'matricule');
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'taches' => $taches,
        ]);
    }

    /**
     * Récupérer une tâche spécifique
     */
    public function show(Request $request, $id)
    {
        // Vérifier que l'utilisateur est un étudiant
        if ($request->user()->role !== 'etudiant') {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé.',
            ], 403);
        }

        $user = $request->user();
        $etudiant = $user->etudiant;

        if (!$etudiant) {
            return response()->json([
                'success' => false,
                'message' => 'Profil étudiant non trouvé.',
            ], 404);
        }

        $tache = Tache::where('id', $id)
            ->where('etudiant_id', $etudiant->id)
            ->with(['projet' => function ($query) {
                $query->select('id', 'titre');
            }, 'etudiant' => function ($query) {
                $query->select('id', 'matricule');
            }])
            ->first();

        if (!$tache) {
            return response()->json([
                'success' => false,
                'message' => 'Tâche non trouvée.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'tache' => $tache,
        ]);
    }

    /**
     * Créer une nouvelle tâche
     */
    public function store(Request $request)
    {
        // Vérifier que l'utilisateur est un étudiant
        if ($request->user()->role !== 'etudiant') {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé. Seuls les étudiants peuvent créer des tâches.',
            ], 403);
        }

        $user = $request->user();
        $etudiant = $user->etudiant;

        if (!$etudiant) {
            return response()->json([
                'success' => false,
                'message' => 'Profil étudiant non trouvé.',
            ], 404);
        }

        // Valider les données
        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'priorite' => 'required|in:high,mid,low',
            'projet_id' => 'required|exists:projets,id',
            'date_debut' => 'nullable|date',
            'date_fin' => 'nullable|date',
        ]);

        // Vérifier que le projet est assigné à l'étudiant
        $projet = Projet::find($validated['projet_id']);
        if (!$projet) {
            return response()->json([
                'success' => false,
                'message' => 'Projet non trouvé.',
            ], 404);
        }

        // Vérifier que l'étudiant est dans un groupe qui a ce projet
        $groupe = $etudiant->groupes()
            ->where('projet_id', $validated['projet_id'])
            ->first();

        if (!$groupe) {
            return response()->json([
                'success' => false,
                'message' => 'Ce projet ne vous est pas assigné.',
            ], 403);
        }

        // Créer la tâche (statut par défaut: todo)
        $tache = Tache::create([
            'etudiant_id' => $etudiant->id,
            'projet_id' => $validated['projet_id'],
            'nom' => $validated['nom'],
            'statut' => 'todo', // Statut par défaut
            'priorite' => $validated['priorite'],
            'date_debut' => $validated['date_debut'] ?? null,
            'date_fin' => $validated['date_fin'] ?? null,
        ]);

        $tache->load(['projet' => function ($query) {
            $query->select('id', 'titre');
        }, 'etudiant' => function ($query) {
            $query->select('id', 'matricule');
        }]);

        return response()->json([
            'success' => true,
            'message' => 'Tâche créée avec succès.',
            'tache' => $tache,
        ], 201);
    }

    /**
     * Mettre à jour une tâche
     */
    public function update(Request $request, $id)
    {
        // Vérifier que l'utilisateur est un étudiant
        if ($request->user()->role !== 'etudiant') {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé.',
            ], 403);
        }

        $user = $request->user();
        $etudiant = $user->etudiant;

        if (!$etudiant) {
            return response()->json([
                'success' => false,
                'message' => 'Profil étudiant non trouvé.',
            ], 404);
        }

        $tache = Tache::where('id', $id)
            ->where('etudiant_id', $etudiant->id)
            ->first();

        if (!$tache) {
            return response()->json([
                'success' => false,
                'message' => 'Tâche non trouvée.',
            ], 404);
        }

        // Valider les données
        $validated = $request->validate([
            'nom' => 'sometimes|required|string|max:255',
            'priorite' => 'sometimes|required|in:high,mid,low',
            'projet_id' => 'sometimes|required|exists:projets,id',
            'date_debut' => 'nullable|date',
            'date_fin' => 'nullable|date',
        ]);

        // Si le projet est modifié, vérifier qu'il est assigné à l'étudiant
        if (isset($validated['projet_id']) && $validated['projet_id'] !== $tache->projet_id) {
            $groupe = $etudiant->groupes()
                ->where('projet_id', $validated['projet_id'])
                ->first();

            if (!$groupe) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce projet ne vous est pas assigné.',
                ], 403);
            }
        }

        $tache->update($validated);
        $tache->load(['projet' => function ($query) {
            $query->select('id', 'titre');
        }, 'etudiant' => function ($query) {
            $query->select('id', 'matricule');
        }]);

        return response()->json([
            'success' => true,
            'message' => 'Tâche mise à jour avec succès.',
            'tache' => $tache,
        ]);
    }

    /**
     * Supprimer une tâche
     */
    public function destroy(Request $request, $id)
    {
        // Vérifier que l'utilisateur est un étudiant
        if ($request->user()->role !== 'etudiant') {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé.',
            ], 403);
        }

        $user = $request->user();
        $etudiant = $user->etudiant;

        if (!$etudiant) {
            return response()->json([
                'success' => false,
                'message' => 'Profil étudiant non trouvé.',
            ], 404);
        }

        $tache = Tache::where('id', $id)
            ->where('etudiant_id', $etudiant->id)
            ->first();

        if (!$tache) {
            return response()->json([
                'success' => false,
                'message' => 'Tâche non trouvée.',
            ], 404);
        }

        $tache->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tâche supprimée avec succès.',
        ]);
    }

    /**
     * Changer le statut d'une tâche
     */
    public function updateStatus(Request $request, $id)
    {
        // Vérifier que l'utilisateur est un étudiant
        if ($request->user()->role !== 'etudiant') {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé.',
            ], 403);
        }

        $user = $request->user();
        $etudiant = $user->etudiant;

        if (!$etudiant) {
            return response()->json([
                'success' => false,
                'message' => 'Profil étudiant non trouvé.',
            ], 404);
        }

        $tache = Tache::where('id', $id)
            ->where('etudiant_id', $etudiant->id)
            ->first();

        if (!$tache) {
            return response()->json([
                'success' => false,
                'message' => 'Tâche non trouvée.',
            ], 404);
        }

        // Valider le statut
        $validated = $request->validate([
            'statut' => 'required|in:todo,in_progress,overdue,done',
        ]);

        $tache->update(['statut' => $validated['statut']]);
        $tache->load(['projet' => function ($query) {
            $query->select('id', 'titre');
        }, 'etudiant' => function ($query) {
            $query->select('id', 'matricule');
        }]);

        return response()->json([
            'success' => true,
            'message' => 'Statut de la tâche mis à jour avec succès.',
            'tache' => $tache,
        ]);
    }

    /**
     * Récupérer les statistiques des tâches de l'étudiant
     */
    public function stats(Request $request)
    {
        // Vérifier que l'utilisateur est un étudiant
        if ($request->user()->role !== 'etudiant') {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé.',
            ], 403);
        }

        $user = $request->user();
        $etudiant = $user->etudiant;

        if (!$etudiant) {
            return response()->json([
                'success' => false,
                'message' => 'Profil étudiant non trouvé.',
            ], 404);
        }

        $stats = [
            'total' => Tache::where('etudiant_id', $etudiant->id)->count(),
            'todo' => Tache::where('etudiant_id', $etudiant->id)->where('statut', 'todo')->count(),
            'in_progress' => Tache::where('etudiant_id', $etudiant->id)->where('statut', 'in_progress')->count(),
            'overdue' => Tache::where('etudiant_id', $etudiant->id)->where('statut', 'overdue')->count(),
            'done' => Tache::where('etudiant_id', $etudiant->id)->where('statut', 'done')->count(),
        ];

        return response()->json([
            'success' => true,
            'stats' => $stats,
        ]);
    }

    /**
     * Récupérer toutes les tâches des étudiants encadrés par le professeur connecté
     */
    public function getProfessorStudentTasks(Request $request)
    {
        // Vérifier que l'utilisateur est un professeur
        if ($request->user()->role !== 'prof') {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé. Seuls les professeurs peuvent accéder à cette ressource.',
            ], 403);
        }

        $user = $request->user();
        $prof = $user->prof;

        if (!$prof) {
            return response()->json([
                'success' => false,
                'message' => 'Profil professeur non trouvé.',
            ], 404);
        }

        // Récupérer les IDs des projets du professeur
        $projetIds = Projet::where('prof_id', $prof->id)->pluck('id');

        if ($projetIds->isEmpty()) {
            return response()->json([
                'success' => true,
                'taches' => [],
            ]);
        }

        // Récupérer les tâches des étudiants qui sont dans les groupes de ces projets
        $taches = Tache::whereIn('projet_id', $projetIds)
            ->with([
                'projet' => function ($query) {
                    $query->select('id', 'titre');
                },
                'etudiant.user' => function ($query) {
                    $query->select('id', 'name', 'email');
                },
                'etudiant.groupes' => function ($query) use ($projetIds) {
                    $query->whereIn('projet_id', $projetIds)
                        ->select('groupes.id', 'groupes.projet_id', 'groupes.numero_groupe');
                },
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        // Enrichir les tâches avec les informations du groupe
        $taches = $taches->map(function ($tache) {
            // Trouver le groupe de l'étudiant pour ce projet spécifique
            $groupe = null;
            if ($tache->etudiant && $tache->etudiant->groupes) {
                $groupe = $tache->etudiant->groupes
                    ->where('projet_id', $tache->projet_id)
                    ->first();
            }

            return [
                'id' => $tache->id,
                'nom' => $tache->nom,
                'statut' => $tache->statut,
                'priorite' => $tache->priorite,
                'date_debut' => $tache->date_debut ? $tache->date_debut->format('Y-m-d') : null,
                'date_fin' => $tache->date_fin ? $tache->date_fin->format('Y-m-d') : null,
                'created_at' => $tache->created_at,
                'updated_at' => $tache->updated_at,
                'projet' => $tache->projet ? [
                    'id' => $tache->projet->id,
                    'titre' => $tache->projet->titre,
                ] : null,
                'etudiant' => $tache->etudiant ? [
                    'id' => $tache->etudiant->id,
                    'matricule' => $tache->etudiant->matricule ?? null,
                    'nom' => ($tache->etudiant->user && $tache->etudiant->user->name) ? $tache->etudiant->user->name : null,
                    'email' => ($tache->etudiant->user && $tache->etudiant->user->email) ? $tache->etudiant->user->email : null,
                    'niveau' => $tache->etudiant->niveau ?? null,
                    'filiere' => $tache->etudiant->filiere ?? null,
                ] : null,
                'groupe' => $groupe ? [
                    'id' => $groupe->id,
                    'numero_groupe' => $groupe->numero_groupe,
                ] : null,
            ];
        });

        return response()->json([
            'success' => true,
            'taches' => $taches,
        ]);
    }
}

