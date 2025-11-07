<?php

namespace App\Http\Controllers;

use App\Models\Projet;
use App\Models\Sujet;
use App\Models\Groupe;
use App\Models\Etudiant;
use App\Models\Prof;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProjetController extends Controller
{
    /**
     * Liste de tous les projets (pour admin)
     */
    public function indexAll(Request $request)
    {
        // Vérifier que l'utilisateur est un admin
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé. Seuls les administrateurs peuvent accéder à cette ressource.',
            ], 403);
        }

        $projets = Projet::with(['prof.user'])
            ->withCount('groupes')
            ->withCount('sujets')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'projets' => $projets->map(function ($projet) {
                return [
                    'id' => $projet->id,
                    'titre' => $projet->titre ?? '',
                    'description' => $projet->description ?? null,
                    'nb_par_groupe' => (int) ($projet->nb_par_groupe ?? 0),
                    'niveaux' => is_array($projet->niveaux) ? $projet->niveaux : [],
                    'date_debut' => $projet->date_debut ? $projet->date_debut->format('Y-m-d') : null,
                    'date_fin' => $projet->date_fin ? $projet->date_fin->format('Y-m-d') : null,
                    'date_creation' => $projet->date_creation ? $projet->date_creation->format('Y-m-d H:i:s') : null,
                    'nb_groupes' => $projet->groupes_count ?? 0,
                    'nb_sujets' => $projet->sujets_count ?? 0,
                    'prof' => $projet->prof && $projet->prof->user ? [
                        'id' => $projet->prof->id,
                        'nom' => $projet->prof->user->name ?? null,
                        'email' => $projet->prof->user->email ?? null,
                    ] : null,
                ];
            }),
        ]);
    }

    /**
     * Liste des projets du professeur connecté
     */
    public function index(Request $request)
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

        $projets = Projet::where('prof_id', $prof->id)
            ->withCount('groupes')
            ->with(['sujets', 'groupes' => function ($query) {
                $query->with('sujet', 'etudiants.user');
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'projets' => $projets->map(function ($projet) {
                // S'assurer que les relations sont des Collections
                $sujetsCollection = $projet->sujets ?? collect();
                $groupesCollection = $projet->groupes ?? collect();
                
                return [
                    'id' => $projet->id,
                    'titre' => $projet->titre ?? '',
                    'description' => $projet->description ?? null,
                    'nb_par_groupe' => (int) ($projet->nb_par_groupe ?? 0),
                    'niveaux' => is_array($projet->niveaux) ? $projet->niveaux : [],
                    'date_debut' => $projet->date_debut ? $projet->date_debut->format('Y-m-d') : null,
                    'date_fin' => $projet->date_fin ? $projet->date_fin->format('Y-m-d') : null,
                    'date_creation' => $projet->date_creation ? $projet->date_creation->format('Y-m-d H:i:s') : null,
                    'nb_groupes' => (int) ($projet->groupes_count ?? 0),
                    'nb_sujets' => $sujetsCollection->count(),
                    'sujets' => $sujetsCollection->map(function ($sujet) {
                        return [
                            'id' => $sujet->id,
                            'titre_sujet' => $sujet->titre_sujet ?? '',
                            'description' => $sujet->description ?? null,
                        ];
                    })->values(),
                    'groupes' => $groupesCollection->map(function ($groupe) {
                        // S'assurer que etudiants est une Collection Eloquent
                        $etudiantsCollection = $groupe->etudiants ?? collect();
                        
                        // Déterminer le niveau du groupe (niveau le plus fréquent parmi les étudiants)
                        $niveaux = $etudiantsCollection->pluck('niveau');
                        // S'assurer que pluck() retourne une Collection
                        $niveauxCollection = collect($niveaux)->filter();
                        $niveauGroupe = null;
                        
                        if ($niveauxCollection->isNotEmpty()) {
                            try {
                                $modeResult = $niveauxCollection->mode();
                                // mode() retourne une Collection, prendre le premier élément
                                $niveauGroupe = is_array($modeResult) 
                                    ? ($modeResult[0] ?? $niveauxCollection->first()) 
                                    : ($modeResult->first() ?? $niveauxCollection->first());
                            } catch (\Exception $e) {
                                // En cas d'erreur, prendre simplement le premier
                                $niveauGroupe = $niveauxCollection->first();
                            }
                        }
                        
                        return [
                            'id' => $groupe->id,
                            'numero_groupe' => (int) ($groupe->numero_groupe ?? 0),
                            'niveau' => $niveauGroupe,
                            'sujet' => $groupe->sujet ? [
                                'id' => $groupe->sujet->id,
                                'titre_sujet' => $groupe->sujet->titre_sujet ?? '',
                                'description' => $groupe->sujet->description ?? null,
                            ] : null,
                            'etudiants' => $etudiantsCollection->map(function ($etudiant) {
                                return [
                                    'id' => $etudiant->id,
                                    'matricule' => $etudiant->matricule ?? '',
                                    'nom' => ($etudiant->user->name ?? null),
                                    'email' => ($etudiant->user->email ?? null),
                                    'filiere' => $etudiant->filiere ?? null,
                                    'niveau' => $etudiant->niveau ?? null,
                                ];
                            })->values(),
                            'nb_etudiants' => $etudiantsCollection->count(),
                        ];
                    })->values(),
                ];
            })->values(),
        ]);
    }

    /**
     * Créer un nouveau projet
     */
    public function store(Request $request)
    {
        // Vérifier que l'utilisateur est un professeur
        if ($request->user()->role !== 'prof') {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé.',
            ], 403);
        }

        $validated = $request->validate([
            'titre' => 'required|string|max:255',
            'description' => 'nullable|string',
            'nb_par_groupe' => 'required|integer|min:1',
            'niveaux' => 'required|array|min:1',
            'niveaux.*' => 'required|string|in:L1,L2,L3,M1,M2',
            'date_debut' => 'required|date',
            'date_fin' => 'required|date|after_or_equal:date_debut',
        ]);

        $user = $request->user();
        $prof = $user->prof;

        if (!$prof) {
            return response()->json([
                'success' => false,
                'message' => 'Profil professeur non trouvé.',
            ], 404);
        }

        $projet = Projet::create([
            'prof_id' => $prof->id,
            'titre' => $validated['titre'],
            'description' => $validated['description'] ?? null,
            'nb_par_groupe' => $validated['nb_par_groupe'],
            'niveaux' => $validated['niveaux'],
            'date_debut' => $validated['date_debut'],
            'date_fin' => $validated['date_fin'],
            'date_creation' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Projet créé avec succès.',
            'projet' => [
                'id' => $projet->id,
                'titre' => $projet->titre ?? '',
                'description' => $projet->description ?? null,
                'nb_par_groupe' => (int) ($projet->nb_par_groupe ?? 0),
                'niveaux' => is_array($projet->niveaux) ? $projet->niveaux : [],
                'date_debut' => $projet->date_debut ? $projet->date_debut->format('Y-m-d') : null,
                'date_fin' => $projet->date_fin ? $projet->date_fin->format('Y-m-d') : null,
                'date_creation' => $projet->date_creation ? $projet->date_creation->format('Y-m-d H:i:s') : null,
            ],
        ], 201);
    }

    /**
     * Détails d'un projet avec ses sujets et groupes
     */
    public function show(Request $request, $id)
    {
        // Vérifier que l'utilisateur est un professeur
        if ($request->user()->role !== 'prof') {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé.',
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

        $projet = Projet::where('prof_id', $prof->id)
            ->with(['sujets', 'groupes.sujet', 'groupes.etudiants.user'])
            ->find($id);

        if (!$projet) {
            return response()->json([
                'success' => false,
                'message' => 'Projet non trouvé.',
            ], 404);
        }

        // S'assurer que les relations sont des Collections
        $sujetsCollection = $projet->sujets ?? collect();
        $groupesCollection = $projet->groupes ?? collect();
        
        return response()->json([
            'success' => true,
            'projet' => [
                'id' => $projet->id,
                'titre' => $projet->titre ?? '',
                'description' => $projet->description ?? null,
                'nb_par_groupe' => (int) ($projet->nb_par_groupe ?? 0),
                'niveaux' => is_array($projet->niveaux) ? $projet->niveaux : [],
                'date_debut' => $projet->date_debut ? $projet->date_debut->format('Y-m-d') : null,
                'date_fin' => $projet->date_fin ? $projet->date_fin->format('Y-m-d') : null,
                'date_creation' => $projet->date_creation ? $projet->date_creation->format('Y-m-d H:i:s') : null,
                'sujets' => $sujetsCollection->map(function ($sujet) {
                    return [
                        'id' => $sujet->id,
                        'titre_sujet' => $sujet->titre_sujet ?? '',
                        'description' => $sujet->description ?? null,
                    ];
                })->values(),
                'groupes' => $groupesCollection->map(function ($groupe) {
                    // S'assurer que etudiants est une Collection Eloquent
                    $etudiantsCollection = $groupe->etudiants ?? collect();
                    
                    // Déterminer le niveau du groupe (niveau le plus fréquent parmi les étudiants)
                    $niveaux = $etudiantsCollection->pluck('niveau');
                    // S'assurer que pluck() retourne une Collection
                    $niveauxCollection = collect($niveaux)->filter();
                    $niveauGroupe = null;
                    
                    if ($niveauxCollection->isNotEmpty()) {
                        try {
                            $modeResult = $niveauxCollection->mode();
                            // mode() retourne une Collection, prendre le premier élément
                            $niveauGroupe = is_array($modeResult) 
                                ? ($modeResult[0] ?? $niveauxCollection->first()) 
                                : ($modeResult->first() ?? $niveauxCollection->first());
                        } catch (\Exception $e) {
                            // En cas d'erreur, prendre simplement le premier
                            $niveauGroupe = $niveauxCollection->first();
                        }
                    }
                    
                    return [
                        'id' => $groupe->id,
                        'numero_groupe' => (int) ($groupe->numero_groupe ?? 0),
                        'niveau' => $niveauGroupe,
                        'sujet' => $groupe->sujet ? [
                            'id' => $groupe->sujet->id,
                            'titre_sujet' => $groupe->sujet->titre_sujet ?? '',
                            'description' => $groupe->sujet->description ?? null,
                        ] : null,
                        'etudiants' => $etudiantsCollection->map(function ($etudiant) {
                            return [
                                'id' => $etudiant->id,
                                'matricule' => $etudiant->matricule ?? '',
                                'nom' => ($etudiant->user->name ?? null),
                                'email' => ($etudiant->user->email ?? null),
                                'filiere' => $etudiant->filiere ?? null,
                                'niveau' => $etudiant->niveau ?? null,
                            ];
                        })->values(),
                        'nb_etudiants' => $etudiantsCollection->count(),
                    ];
                })->values(),
            ],
        ]);
    }

    /**
     * Mettre à jour un projet
     */
    public function update(Request $request, $id)
    {
        // Vérifier que l'utilisateur est un professeur
        if ($request->user()->role !== 'prof') {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé.',
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

        // Vérifier que le projet appartient au professeur
        $projet = Projet::where('prof_id', $prof->id)->find($id);

        if (!$projet) {
            return response()->json([
                'success' => false,
                'message' => 'Projet non trouvé ou accès non autorisé.',
            ], 404);
        }

        $validated = $request->validate([
            'titre' => 'required|string|max:255',
            'description' => 'nullable|string',
            'nb_par_groupe' => 'required|integer|min:1',
            'niveaux' => 'required|array|min:1',
            'niveaux.*' => 'required|string|in:L1,L2,L3,M1,M2',
            'date_debut' => 'required|date',
            'date_fin' => 'required|date|after_or_equal:date_debut',
        ]);

        // Vérifier si nb_par_groupe ou niveaux ont changé
        $nbParGroupeChanged = $projet->nb_par_groupe != $validated['nb_par_groupe'];
        $niveauxChanged = json_encode($projet->niveaux ?? []) !== json_encode($validated['niveaux']);
        $shouldRecreateGroups = ($nbParGroupeChanged || $niveauxChanged) && $projet->groupes()->count() > 0;

        DB::beginTransaction();

        try {
            // Si nb_par_groupe ou niveaux ont changé et qu'il y a des groupes, les supprimer
            if ($shouldRecreateGroups) {
                $projet->groupes()->delete();
            }

            $projet->update([
                'titre' => $validated['titre'],
                'description' => $validated['description'] ?? null,
                'nb_par_groupe' => $validated['nb_par_groupe'],
                'niveaux' => $validated['niveaux'],
                'date_debut' => $validated['date_debut'],
                'date_fin' => $validated['date_fin'],
            ]);

            // Si on a supprimé les groupes et qu'il y a des sujets, recréer la répartition
            if ($shouldRecreateGroups) {
                $projet->refresh();
                $projet->load('sujets');
                
                if ($projet->sujets->isNotEmpty()) {
                    // Récupérer les étudiants selon les nouveaux niveaux
                    $etudiants = Etudiant::with('user')
                        ->whereIn('niveau', $validated['niveaux'])
                        ->get();

                    if ($etudiants->isNotEmpty()) {
                        // Grouper les étudiants par niveau et mélanger
                        $etudiantsParNiveau = $etudiants->groupBy('niveau')->map(function ($groupeEtudiants) {
                            return collect($groupeEtudiants)->shuffle();
                        });

                        $sujets = $projet->sujets->shuffle();
                        $numeroGroupe = 1;
                        $nbParGroupe = $validated['nb_par_groupe'];
                        $sujetIndex = 0;

                        foreach ($etudiantsParNiveau as $niveau => $etudiantsNiveau) {
                            $nbEtudiantsNiveau = $etudiantsNiveau->count();
                            
                            for ($i = 0; $i < $nbEtudiantsNiveau; $i += $nbParGroupe) {
                                $groupeEtudiants = $etudiantsNiveau->slice($i, $nbParGroupe);
                                $sujet = $sujets[$sujetIndex % $sujets->count()];
                                $sujetIndex++;

                                $groupe = Groupe::create([
                                    'projet_id' => $projet->id,
                                    'sujet_id' => $sujet->id,
                                    'numero_groupe' => $numeroGroupe,
                                ]);

                                foreach ($groupeEtudiants as $etudiant) {
                                    $groupe->etudiants()->attach($etudiant->id);
                                }

                                $numeroGroupe++;
                            }
                        }
                    }
                }
            }

            DB::commit();

            // Recharger le projet avec toutes les relations
            $projet->refresh();
            $projet->load(['sujets', 'groupes.sujet', 'groupes.etudiants.user']);

            // Préparer la réponse
            $sujetsCollection = $projet->sujets ?? collect();
            $groupesCollection = $projet->groupes ?? collect();

            return response()->json([
                'success' => true,
                'message' => $shouldRecreateGroups 
                    ? 'Projet mis à jour avec succès. Les groupes ont été recréés selon les nouveaux paramètres.'
                    : 'Projet mis à jour avec succès.',
                'projet' => [
                    'id' => $projet->id,
                    'titre' => $projet->titre ?? '',
                    'description' => $projet->description ?? null,
                    'nb_par_groupe' => (int) ($projet->nb_par_groupe ?? 0),
                'niveaux' => is_array($projet->niveaux) ? $projet->niveaux : [],
                'date_debut' => $projet->date_debut ? $projet->date_debut->format('Y-m-d') : null,
                'date_fin' => $projet->date_fin ? $projet->date_fin->format('Y-m-d') : null,
                'date_creation' => $projet->date_creation ? $projet->date_creation->format('Y-m-d H:i:s') : null,
                'nb_groupes' => $groupesCollection->count(),
                'sujets' => $sujetsCollection->map(function ($sujet) {
                    return [
                        'id' => $sujet->id,
                        'titre_sujet' => $sujet->titre_sujet ?? '',
                        'description' => $sujet->description ?? null,
                    ];
                })->values(),
                'groupes' => $groupesCollection->map(function ($groupe) {
                    $etudiants = $groupe->etudiants ?? collect();
                    $niveauGroupe = null;
                    if ($etudiants->isNotEmpty()) {
                        $niveauGroupe = $etudiants->first()->niveau ?? null;
                    }
                    $etudiantsCollection = $etudiants ?? collect();
                    
                    return [
                        'id' => $groupe->id,
                        'numero_groupe' => (int) ($groupe->numero_groupe ?? 0),
                        'niveau' => $niveauGroupe,
                        'sujet' => $groupe->sujet ? [
                            'id' => $groupe->sujet->id,
                            'titre_sujet' => $groupe->sujet->titre_sujet ?? '',
                            'description' => $groupe->sujet->description ?? null,
                        ] : null,
                        'etudiants' => $etudiantsCollection->map(function ($etudiant) {
                            return [
                                'id' => $etudiant->id,
                                'matricule' => $etudiant->matricule ?? '',
                                'nom' => ($etudiant->user->name ?? null),
                                'email' => ($etudiant->user->email ?? null),
                                'filiere' => $etudiant->filiere ?? null,
                                'niveau' => $etudiant->niveau ?? null,
                            ];
                        })->values(),
                        'nb_etudiants' => $etudiantsCollection->count(),
                    ];
                })->values(),
            ],
        ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur lors de la mise à jour du projet: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du projet: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Supprimer un projet
     */
    public function destroy(Request $request, $id)
    {
        // Vérifier que l'utilisateur est un professeur
        if ($request->user()->role !== 'prof') {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé.',
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

        // Vérifier que le projet appartient au professeur
        $projet = Projet::where('prof_id', $prof->id)->find($id);

        if (!$projet) {
            return response()->json([
                'success' => false,
                'message' => 'Projet non trouvé ou accès non autorisé.',
            ], 404);
        }

        // Supprimer le projet (les sujets et groupes seront supprimés en cascade grâce aux contraintes de clé étrangère)
        $projet->delete();

        return response()->json([
            'success' => true,
            'message' => 'Projet supprimé avec succès.',
        ]);
    }

    /**
     * Ajouter un sujet (sous-projet) à un projet
     */
    public function storeSujet(Request $request)
    {
        // Vérifier que l'utilisateur est un professeur
        if ($request->user()->role !== 'prof') {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé.',
            ], 403);
        }

        $validated = $request->validate([
            'projet_id' => 'required|exists:projets,id',
            'titre_sujet' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $user = $request->user();
        $prof = $user->prof;

        if (!$prof) {
            return response()->json([
                'success' => false,
                'message' => 'Profil professeur non trouvé.',
            ], 404);
        }

        // Vérifier que le projet appartient au professeur
        $projet = Projet::where('prof_id', $prof->id)->find($validated['projet_id']);

        if (!$projet) {
            return response()->json([
                'success' => false,
                'message' => 'Projet non trouvé ou accès non autorisé.',
            ], 404);
        }

        $sujet = Sujet::create([
            'projet_id' => $projet->id,
            'titre_sujet' => $validated['titre_sujet'],
            'description' => $validated['description'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Sujet ajouté avec succès.',
            'sujet' => [
                'id' => $sujet->id,
                'projet_id' => $sujet->projet_id,
                'titre_sujet' => $sujet->titre_sujet,
                'description' => $sujet->description,
            ],
        ], 201);
    }

    /**
     * Mettre à jour un sujet (sous-projet)
     */
    public function updateSujet(Request $request, $id)
    {
        // Vérifier que l'utilisateur est un professeur
        if ($request->user()->role !== 'prof') {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé.',
            ], 403);
        }

        $validated = $request->validate([
            'titre_sujet' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $user = $request->user();
        $prof = $user->prof;

        if (!$prof) {
            return response()->json([
                'success' => false,
                'message' => 'Profil professeur non trouvé.',
            ], 404);
        }

        // Récupérer le sujet avec son projet
        $sujet = Sujet::with('projet')->find($id);

        if (!$sujet) {
            return response()->json([
                'success' => false,
                'message' => 'Sujet non trouvé.',
            ], 404);
        }

        // Vérifier que le projet appartient au professeur
        if ($sujet->projet->prof_id !== $prof->id) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé.',
            ], 403);
        }

        $sujet->update([
            'titre_sujet' => $validated['titre_sujet'],
            'description' => $validated['description'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Sujet mis à jour avec succès.',
            'sujet' => [
                'id' => $sujet->id,
                'projet_id' => $sujet->projet_id,
                'titre_sujet' => $sujet->titre_sujet,
                'description' => $sujet->description,
            ],
        ]);
    }

    /**
     * Supprimer un sujet (sous-projet)
     */
    public function deleteSujet(Request $request, $id)
    {
        // Vérifier que l'utilisateur est un professeur
        if ($request->user()->role !== 'prof') {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé.',
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

        // Récupérer le sujet avec son projet
        $sujet = Sujet::with('projet')->find($id);

        if (!$sujet) {
            return response()->json([
                'success' => false,
                'message' => 'Sujet non trouvé.',
            ], 404);
        }

        // Vérifier que le projet appartient au professeur
        if ($sujet->projet->prof_id !== $prof->id) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé.',
            ], 403);
        }

        $projet = $sujet->projet;
        $projetId = $projet->id;

        DB::beginTransaction();

        try {
            // Récupérer tous les groupes qui ont ce sujet assigné
            $groupesAvecSujet = Groupe::where('projet_id', $projetId)
                ->where('sujet_id', $sujet->id)
                ->get();

            // Supprimer le sujet
            $sujet->delete();

            // Si des groupes avaient ce sujet, réassigner un autre sujet
            if ($groupesAvecSujet->isNotEmpty()) {
                // Recharger le projet pour avoir les sujets restants
                $projet->refresh();
                $projet->load('sujets');
                $sujetsRestants = $projet->sujets->shuffle(); // Mélanger pour une distribution aléatoire

                if ($sujetsRestants->isNotEmpty()) {
                    // Réassigner les sujets restants aux groupes de manière cyclique
                    $sujetIndex = 0;
                    foreach ($groupesAvecSujet as $groupe) {
                        $nouveauSujet = $sujetsRestants[$sujetIndex % $sujetsRestants->count()];
                        $sujetIndex++;
                        
                        $groupe->sujet_id = $nouveauSujet->id;
                        $groupe->save();
                    }
                } else {
                    // S'il n'y a plus de sujets, mettre à null
                    foreach ($groupesAvecSujet as $groupe) {
                        $groupe->sujet_id = null;
                        $groupe->save();
                    }
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $groupesAvecSujet->isNotEmpty() 
                    ? 'Sujet supprimé avec succès. Les groupes ont été automatiquement réassignés à d\'autres sujets.'
                    : 'Sujet supprimé avec succès.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur lors de la suppression du sujet: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression du sujet: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Répartition automatique des étudiants par groupe
     */
    public function repartirEtudiants(Request $request, $id)
    {
        // Vérifier que l'utilisateur est un professeur
        if ($request->user()->role !== 'prof') {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé.',
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

        $projet = Projet::where('prof_id', $prof->id)
            ->with(['sujets' => function($query) {
                $query->orderBy('id');
            }])
            ->find($id);

        if (!$projet) {
            return response()->json([
                'success' => false,
                'message' => 'Projet non trouvé ou accès non autorisé.',
            ], 404);
        }

        // Vérifier qu'il y a des sujets
        if ($projet->sujets->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Veuillez d\'abord ajouter au moins un sujet au projet.',
            ], 400);
        }

        // Récupérer les étudiants selon les niveaux du projet
        $niveauxProjet = $projet->niveaux ?? [];
        
        if (empty($niveauxProjet)) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun niveau défini pour ce projet. Veuillez d\'abord mettre à jour le projet avec des niveaux.',
            ], 400);
        }
        
        $etudiants = Etudiant::with('user')
            ->whereIn('niveau', $niveauxProjet)
            ->get();

        if ($etudiants->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun étudiant disponible pour la répartition.',
            ], 400);
        }

        // Grouper les étudiants par niveau et mélanger chaque groupe
        // S'assurer que groupBy() retourne une Collection
        $etudiantsParNiveau = $etudiants->groupBy('niveau')->map(function ($groupeEtudiants) {
            // S'assurer que $groupeEtudiants est une Collection avant shuffle()
            return collect($groupeEtudiants)->shuffle();
        });

        if ($etudiantsParNiveau->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun étudiant avec niveau défini disponible pour la répartition.',
            ], 400);
        }

        // Calculer le nombre total d'étudiants
        $nbEtudiants = $etudiants->count();
        $nbParGroupe = $projet->nb_par_groupe;

        DB::beginTransaction();

        try {
            // Supprimer les groupes existants (et leurs relations)
            $projet->groupes()->delete();

            // Recharger les sujets pour s'assurer d'avoir tous les sujets (anciens et nouveaux)
            $projet->refresh();
            $sujets = $projet->sujets->shuffle(); // Mélanger les sujets pour une distribution aléatoire
            
            if ($sujets->isEmpty()) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun sujet disponible pour la répartition.',
                ], 400);
            }

            // Créer les groupes et assigner les étudiants par niveau
            $numeroGroupe = 1;
            $nbParGroupe = $projet->nb_par_groupe;
            $sujetIndex = 0; // Index pour parcourir les sujets de manière cyclique

            // Parcourir chaque niveau
            foreach ($etudiantsParNiveau as $niveau => $etudiantsNiveau) {
                $nbEtudiantsNiveau = $etudiantsNiveau->count();
                
                // Créer les groupes pour ce niveau
                for ($i = 0; $i < $nbEtudiantsNiveau; $i += $nbParGroupe) {
                    $groupeEtudiants = $etudiantsNiveau->slice($i, $nbParGroupe);
                    
                    // Sélectionner un sujet de manière cyclique pour distribuer équitablement tous les sujets
                    // Cela garantit que tous les sujets (anciens et nouveaux) sont utilisés
                    $sujet = $sujets[$sujetIndex % $sujets->count()];
                    $sujetIndex++;

                    // Créer le groupe avec le niveau dans le numéro de groupe
                    $groupe = Groupe::create([
                        'projet_id' => $projet->id,
                        'sujet_id' => $sujet->id,
                        'numero_groupe' => $numeroGroupe,
                    ]);

                    // Assigner les étudiants au groupe
                    foreach ($groupeEtudiants as $etudiant) {
                        $groupe->etudiants()->attach($etudiant->id);
                    }

                    $numeroGroupe++;
                }
            }

            DB::commit();

            // Recharger le projet avec les nouvelles données
            $projet->refresh();
            $projet->load(['groupes.sujet', 'groupes.etudiants.user']);

            // S'assurer que les relations sont des Collections après le refresh
            $sujetsCollection = $projet->sujets ?? collect();
            $groupesCollection = $projet->groupes ?? collect();
            
            return response()->json([
                'success' => true,
                'message' => 'Répartition effectuée avec succès. ' . ($numeroGroupe - 1) . ' groupe(s) créé(s) répartis par niveau.',
                'projet' => [
                    'id' => $projet->id,
                    'titre' => $projet->titre ?? '',
                    'description' => $projet->description ?? null,
                    'nb_par_groupe' => (int) ($projet->nb_par_groupe ?? 0),
                    'niveaux' => is_array($projet->niveaux) ? $projet->niveaux : [],
                    'date_debut' => $projet->date_debut ? $projet->date_debut->format('Y-m-d') : null,
                    'date_fin' => $projet->date_fin ? $projet->date_fin->format('Y-m-d') : null,
                    'date_creation' => $projet->date_creation ? $projet->date_creation->format('Y-m-d H:i:s') : null,
                    'nb_groupes' => $groupesCollection->count(),
                    'sujets' => $sujetsCollection->map(function ($sujet) {
                        return [
                            'id' => $sujet->id,
                            'titre_sujet' => $sujet->titre_sujet ?? '',
                            'description' => $sujet->description ?? null,
                        ];
                    })->values(),
                    'groupes' => $groupesCollection->map(function ($groupe) {
                        // S'assurer que etudiants est une Collection Eloquent
                        $etudiantsCollection = $groupe->etudiants ?? collect();
                        
                        // Déterminer le niveau du groupe (niveau le plus fréquent parmi les étudiants)
                        $niveaux = $etudiantsCollection->pluck('niveau');
                        // S'assurer que pluck() retourne une Collection
                        $niveauxCollection = collect($niveaux)->filter();
                        $niveauGroupe = null;
                        
                        if ($niveauxCollection->isNotEmpty()) {
                            try {
                                $modeResult = $niveauxCollection->mode();
                                // mode() retourne une Collection, prendre le premier élément
                                $niveauGroupe = is_array($modeResult) 
                                    ? ($modeResult[0] ?? $niveauxCollection->first()) 
                                    : ($modeResult->first() ?? $niveauxCollection->first());
                            } catch (\Exception $e) {
                                // En cas d'erreur, prendre simplement le premier
                                $niveauGroupe = $niveauxCollection->first();
                            }
                        }
                        
                        return [
                            'id' => $groupe->id,
                            'numero_groupe' => (int) ($groupe->numero_groupe ?? 0),
                            'niveau' => $niveauGroupe,
                            'sujet' => $groupe->sujet ? [
                                'id' => $groupe->sujet->id,
                                'titre_sujet' => $groupe->sujet->titre_sujet ?? '',
                                'description' => $groupe->sujet->description ?? null,
                            ] : null,
                            'etudiants' => $etudiantsCollection->map(function ($etudiant) {
                                return [
                                    'id' => $etudiant->id,
                                    'matricule' => $etudiant->matricule ?? '',
                                    'nom' => ($etudiant->user->name ?? null),
                                    'email' => ($etudiant->user->email ?? null),
                                    'filiere' => $etudiant->filiere ?? null,
                                    'niveau' => $etudiant->niveau ?? null,
                                ];
                            })->values(),
                            'nb_etudiants' => $etudiantsCollection->count(),
                        ];
                    })->values(),
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur lors de la répartition des étudiants: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la répartition: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Réassigner les sujets aux groupes existants (sans recréer les groupes)
     */
    public function reassignerSujetsAuxGroupes(Request $request, $id)
    {
        // Vérifier que l'utilisateur est un professeur
        if ($request->user()->role !== 'prof') {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé.',
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

        $projet = Projet::where('prof_id', $prof->id)
            ->with(['sujets', 'groupes'])
            ->find($id);

        if (!$projet) {
            return response()->json([
                'success' => false,
                'message' => 'Projet non trouvé ou accès non autorisé.',
            ], 404);
        }

        // Vérifier qu'il y a des sujets
        if ($projet->sujets->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Veuillez d\'abord ajouter au moins un sujet au projet.',
            ], 400);
        }

        // Vérifier qu'il y a des groupes existants
        if ($projet->groupes->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun groupe existant. Veuillez d\'abord créer des groupes avec la répartition initiale.',
            ], 400);
        }

        DB::beginTransaction();

        try {
            // Recharger les sujets pour s'assurer d'avoir tous les sujets (anciens et nouveaux)
            $projet->refresh();
            $sujets = $projet->sujets->shuffle(); // Mélanger les sujets pour une distribution aléatoire
            
            if ($sujets->isEmpty()) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun sujet disponible pour la répartition.',
                ], 400);
            }

            // Recharger les groupes existants
            $projet->load('groupes');
            $groupes = $projet->groupes;
            
            if ($groupes->isEmpty()) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun groupe existant.',
                ], 400);
            }

            // Réassigner les sujets aux groupes existants de manière cyclique
            $sujetIndex = 0;
            foreach ($groupes as $groupe) {
                // Sélectionner un sujet de manière cyclique pour distribuer équitablement tous les sujets
                $sujet = $sujets[$sujetIndex % $sujets->count()];
                $sujetIndex++;
                
                // Mettre à jour le sujet du groupe
                $groupe->sujet_id = $sujet->id;
                $groupe->save();
            }

            DB::commit();

            // Recharger le projet avec les nouvelles données
            $projet->refresh();
            $projet->load(['groupes.sujet', 'groupes.etudiants.user']);

            // S'assurer que les relations sont des Collections après le refresh
            $sujetsCollection = $projet->sujets ?? collect();
            $groupesCollection = $projet->groupes ?? collect();
            
            return response()->json([
                'success' => true,
                'message' => 'Sujets réassignés avec succès aux groupes existants.',
                'projet' => [
                    'id' => $projet->id,
                    'titre' => $projet->titre ?? '',
                    'description' => $projet->description ?? null,
                    'nb_par_groupe' => (int) ($projet->nb_par_groupe ?? 0),
                    'niveaux' => is_array($projet->niveaux) ? $projet->niveaux : [],
                    'date_debut' => $projet->date_debut ? $projet->date_debut->format('Y-m-d') : null,
                    'date_fin' => $projet->date_fin ? $projet->date_fin->format('Y-m-d') : null,
                    'date_creation' => $projet->date_creation ? $projet->date_creation->format('Y-m-d H:i:s') : null,
                    'nb_groupes' => $groupesCollection->count(),
                    'sujets' => $sujetsCollection->map(function ($sujet) {
                        return [
                            'id' => $sujet->id,
                            'titre_sujet' => $sujet->titre_sujet ?? '',
                            'description' => $sujet->description ?? null,
                        ];
                    })->values(),
                    'groupes' => $groupesCollection->map(function ($groupe) {
                        $etudiants = $groupe->etudiants ?? collect();
                        $niveauGroupe = null;
                        if ($etudiants->isNotEmpty()) {
                            $niveauGroupe = $etudiants->first()->niveau ?? null;
                        }
                        $etudiantsCollection = $etudiants ?? collect();
                        
                        return [
                            'id' => $groupe->id,
                            'numero_groupe' => (int) ($groupe->numero_groupe ?? 0),
                            'niveau' => $niveauGroupe,
                            'sujet' => $groupe->sujet ? [
                                'id' => $groupe->sujet->id,
                                'titre_sujet' => $groupe->sujet->titre_sujet ?? '',
                                'description' => $groupe->sujet->description ?? null,
                            ] : null,
                            'etudiants' => $etudiantsCollection->map(function ($etudiant) {
                                return [
                                    'id' => $etudiant->id,
                                    'matricule' => $etudiant->matricule ?? '',
                                    'nom' => ($etudiant->user->name ?? null),
                                    'email' => ($etudiant->user->email ?? null),
                                    'filiere' => $etudiant->filiere ?? null,
                                    'niveau' => $etudiant->niveau ?? null,
                                ];
                            })->values(),
                            'nb_etudiants' => $etudiantsCollection->count(),
                        ];
                    })->values(),
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur lors de la réassignation des sujets: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la réassignation des sujets: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Récupérer les projets de l'étudiant connecté avec ses groupes et coéquipiers
     */
    public function getStudentProjects(Request $request)
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

        // Récupérer les groupes de l'étudiant avec leurs projets, sujets et autres étudiants
        $groupes = $etudiant->groupes()
            ->with([
                'projet' => function ($query) {
                    $query->with(['prof.user' => function ($q) {
                        $q->select('id', 'name', 'email');
                    }]);
                },
                'sujet',
                'etudiants.user' => function ($query) {
                    $query->select('id', 'name', 'email');
                }
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        // Grouper par projet et structurer les données
        $projetsData = [];
        foreach ($groupes as $groupe) {
            if (!$groupe->projet) continue;

            $projetId = $groupe->projet->id;
            
            // Récupérer les autres étudiants du groupe (exclure l'étudiant actuel)
            $coequipiers = $groupe->etudiants
                ->filter(function ($et) use ($etudiant) {
                    return $et->id !== $etudiant->id;
                })
                ->map(function ($et) {
                    return [
                        'id' => $et->id,
                        'nom' => $et->user->name ?? null,
                        'matricule' => $et->matricule ?? null,
                        'email' => $et->user->email ?? null,
                    ];
                })
                ->values();

            // Si le projet n'existe pas encore dans le tableau, l'ajouter
            if (!isset($projetsData[$projetId])) {
                // S'assurer que le projet est rechargé avec la relation prof
                $groupe->projet->loadMissing('prof.user');
                
                $profNom = null;
                $profEmail = null;
                
                if ($groupe->projet->prof && $groupe->projet->prof->user) {
                    $profNom = $groupe->projet->prof->user->name;
                    $profEmail = $groupe->projet->prof->user->email;
                }
                
                $projetsData[$projetId] = [
                    'id' => $groupe->projet->id,
                    'titre' => $groupe->projet->titre ?? '',
                    'description' => $groupe->projet->description ?? null,
                    'date_debut' => $groupe->projet->date_debut ? $groupe->projet->date_debut->format('Y-m-d') : null,
                    'date_fin' => $groupe->projet->date_fin ? $groupe->projet->date_fin->format('Y-m-d') : null,
                    'prof' => $profNom ? [
                        'id' => $groupe->projet->prof->id,
                        'nom' => $profNom,
                        'email' => $profEmail,
                    ] : null,
                    'groupe' => [
                        'id' => $groupe->id,
                        'numero_groupe' => $groupe->numero_groupe,
                        'sujet' => $groupe->sujet ? [
                            'id' => $groupe->sujet->id,
                            'titre_sujet' => $groupe->sujet->titre_sujet ?? '',
                            'description' => $groupe->sujet->description ?? null,
                        ] : null,
                        'coequipiers' => $coequipiers->toArray(),
                    ],
                ];
            }
        }

        return response()->json([
            'success' => true,
            'projets' => array_values($projetsData),
        ]);
    }
}
