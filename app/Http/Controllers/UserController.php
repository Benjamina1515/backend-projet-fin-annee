<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Etudiant;
use App\Models\Prof;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    /**
     * Récupérer tous les utilisateurs (Admin uniquement)
     */
    public function index(Request $request)
    {
        // Vérifier que l'utilisateur est admin
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé. Seuls les administrateurs peuvent accéder à cette ressource.',
            ], 403);
        }

        $users = User::with(['etudiant', 'prof'])->get()->map(function ($user) {
            return $this->formatUserResponse($user);
        });

        return response()->json([
            'success' => true,
            'users' => $users,
        ]);
    }

    /**
     * Récupérer un utilisateur par ID
     */
    public function show(Request $request, $id)
    {
        // Vérifier que l'utilisateur est admin
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé.',
            ], 403);
        }

        $user = User::with(['etudiant', 'prof'])->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non trouvé.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'user' => $this->formatUserResponse($user),
        ]);
    }

    /**
     * Créer un nouvel utilisateur (Admin uniquement)
     */
    public function store(Request $request)
    {
        // Vérifier que l'utilisateur est admin
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé.',
            ], 403);
        }

        $rules = [
            'nom' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role' => ['required', Rule::in(['admin', 'prof', 'etudiant'])],
            'avatar' => 'nullable|image|max:15360',
        ];

        // Ajouter les règles selon le rôle
        if ($request->role === 'etudiant') {
            $rules['matricule'] = 'required|string|max:255|unique:etudiants,matricule';
            $rules['filiere'] = 'required|string|max:255';
            $rules['niveau'] = 'required|string|max:255';
        } elseif ($request->role === 'prof') {
            $rules['matricule'] = 'required|string|max:255|unique:profs,matricule';
            $rules['specialite'] = 'required|string|max:255';
            $rules['grade'] = 'required|string|max:255';
        }

        $validated = $request->validate($rules);

        $userData = [
            'name' => $validated['nom'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
        ];

        $user = User::create($userData);

        // Créer l'enregistrement spécifique selon le rôle
        if ($validated['role'] === 'etudiant') {
            $etudiantData = [
                'user_id' => $user->id,
                'matricule' => $validated['matricule'],
                'filiere' => $validated['filiere'],
                'niveau' => $validated['niveau'],
            ];
            if ($request->hasFile('avatar')) {
                $file = $request->file('avatar');
                $originalName = $file->getClientOriginalName();
                $filePath = $file->storeAs('', $originalName, 'public');
                $etudiantData['avatar'] = $originalName;
            }
            Etudiant::create($etudiantData);
        } elseif ($validated['role'] === 'prof') {
            $profData = [
                'user_id' => $user->id,
                'matricule' => $validated['matricule'],
                'specialite' => $validated['specialite'],
                'grade' => $validated['grade'],
            ];
            if ($request->hasFile('avatar')) {
                $file = $request->file('avatar');
                $originalName = $file->getClientOriginalName();
                $filePath = $file->storeAs('', $originalName, 'public');
                $profData['avatar'] = $originalName;
            }
            Prof::create($profData);
        }

        $user->load(['etudiant', 'prof']);

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur créé avec succès.',
            'user' => $this->formatUserResponse($user),
        ], 201);
    }

    /**
     * Mettre à jour un utilisateur (Admin uniquement)
     */
    public function update(Request $request, $id)
    {
        // Vérifier que l'utilisateur est admin
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé.',
            ], 403);
        }

        $user = User::with(['etudiant', 'prof'])->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non trouvé.',
            ], 404);
        }

        $validated = $request->validate([
            'nom' => 'sometimes|string|max:255',
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($id)],
            'password' => 'sometimes|string|min:6',
            'role' => ['sometimes', Rule::in(['admin', 'prof', 'etudiant'])],
            'avatar' => 'nullable|image|max:15360',
            // Champs spécifiques pour étudiant
            'matricule' => 'sometimes|string|max:255|unique:etudiants,matricule,' . ($user->etudiant ? $user->etudiant->id : 'NULL') . ',id',
            'filiere' => 'sometimes|string|max:255',
            'niveau' => 'sometimes|string|max:255',
            // Champs spécifiques pour prof
            'specialite' => 'sometimes|string|max:255',
            'grade' => 'sometimes|string|max:255',
        ]);

        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            $originalName = $file->getClientOriginalName();
            
            // Supprimer l'ancien fichier selon le rôle
            if ($user->role === 'etudiant' && $user->etudiant && $user->etudiant->avatar) {
                if (Storage::disk('public')->exists($user->etudiant->avatar)) {
                    Storage::disk('public')->delete($user->etudiant->avatar);
                }
            } elseif ($user->role === 'prof' && $user->prof && $user->prof->avatar) {
                if (Storage::disk('public')->exists($user->prof->avatar)) {
                    Storage::disk('public')->delete($user->prof->avatar);
                }
            }
            
            $filePath = $file->storeAs('', $originalName, 'public');
            $validated['avatar'] = $originalName;
        }

        // Mettre à jour les champs de base
        if ($request->has('nom')) {
            $user->name = $validated['nom'];
        } elseif ($request->has('name')) {
            $user->name = $validated['name'];
        }

        if ($request->has('email')) {
            $user->email = $validated['email'];
        }

        if ($request->has('password')) {
            $user->password = Hash::make($validated['password']);
        }

        if ($request->has('role')) {
            $user->role = $validated['role'];
        }

        $user->save();

        // Mettre à jour les informations spécifiques selon le rôle
        if ($user->role === 'etudiant' && $user->etudiant) {
            if ($request->has('matricule')) {
                $user->etudiant->matricule = $validated['matricule'];
            }
            if ($request->has('filiere')) {
                $user->etudiant->filiere = $validated['filiere'];
            }
            if ($request->has('niveau')) {
                $user->etudiant->niveau = $validated['niveau'];
            }
            if (isset($validated['avatar'])) {
                $user->etudiant->avatar = $validated['avatar'];
            }
            $user->etudiant->save();
        } elseif ($user->role === 'prof' && $user->prof) {
            if ($request->has('matricule')) {
                $user->prof->matricule = $validated['matricule'];
            }
            if ($request->has('specialite')) {
                $user->prof->specialite = $validated['specialite'];
            }
            if ($request->has('grade')) {
                $user->prof->grade = $validated['grade'];
            }
            if (isset($validated['avatar'])) {
                $user->prof->avatar = $validated['avatar'];
            }
            $user->prof->save();
        }

        $user->load(['etudiant', 'prof']);

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur mis à jour avec succès.',
            'user' => $this->formatUserResponse($user),
        ]);
    }

    /**
     * Supprimer un utilisateur (Admin uniquement)
     */
    public function destroy(Request $request, $id)
    {
        // Vérifier que l'utilisateur est admin
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé.',
            ], 403);
        }

        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non trouvé.',
            ], 404);
        }

        // Empêcher la suppression de l'utilisateur lui-même
        if ($user->id === $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez pas supprimer votre propre compte.',
            ], 400);
        }

        // La suppression en cascade gérera automatiquement etudiant/prof
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur supprimé avec succès.',
        ]);
    }

    /**
     * Formater la réponse utilisateur avec les informations spécifiques
     */
    private function formatUserResponse(User $user): array
    {
        $response = [
            'id' => $user->id,
            'nom' => $user->name,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];

        // Ajouter les informations spécifiques selon le rôle
        if ($user->role === 'etudiant' && $user->etudiant) {
            $response['avatar'] = $user->etudiant->avatar;
            $response['avatar_url'] = $user->etudiant->avatar ? asset('storage/' . $user->etudiant->avatar) : null;
            $response['etudiant'] = [
                'id' => $user->etudiant->id,
                'matricule' => $user->etudiant->matricule,
                'filiere' => $user->etudiant->filiere,
                'niveau' => $user->etudiant->niveau,
                'avatar' => $user->etudiant->avatar,
                'avatar_url' => $user->etudiant->avatar ? asset('storage/' . $user->etudiant->avatar) : null,
            ];
        } elseif ($user->role === 'prof' && $user->prof) {
            $response['avatar'] = $user->prof->avatar;
            $response['avatar_url'] = $user->prof->avatar ? asset('storage/' . $user->prof->avatar) : null;
            $response['prof'] = [
                'id' => $user->prof->id,
                'matricule' => $user->prof->matricule,
                'specialite' => $user->prof->specialite,
                'grade' => $user->prof->grade,
                'avatar' => $user->prof->avatar,
                'avatar_url' => $user->prof->avatar ? asset('storage/' . $user->prof->avatar) : null,
            ];
        } else {
            // Pour les admins ou utilisateurs sans relation
            $response['avatar'] = null;
            $response['avatar_url'] = null;
        }

        return $response;
    }
}
