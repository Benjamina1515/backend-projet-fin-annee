<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Etudiant;
use App\Models\Prof;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    /**
     * Inscription d'un nouvel utilisateur
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            'role' => ['required', Rule::in(['prof', 'etudiant'])],
            // Champs spécifiques pour étudiant
            'matricule' => 'required|string|max:255',
            'filiere' => 'required_if:role,etudiant|string|max:255',
            'niveau' => 'required_if:role,etudiant|string|max:255',
            // Champs spécifiques pour prof
            'specialite' => 'required_if:role,prof|string|max:255',
            'grade' => 'required_if:role,prof|string|max:255',
            'avatar' => 'nullable|image|max:15360',
        ]);

        // Créer l'utilisateur
        $userData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
        ];

        $user = User::create($userData);

        // Gérer l'avatar si présent et créer l'enregistrement spécifique selon le rôle
        if ($validated['role'] === 'etudiant') {
            $etudiantData = [
                'user_id' => $user->id,
                'matricule' => $validated['matricule'],
                'filiere' => $validated['filiere'],
                'niveau' => $validated['niveau'],
            ];

            // Si l'avatar est pour l'étudiant, le stocker dans la table etudiants
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

            // Si l'avatar est pour le prof, le stocker dans la table profs
            if ($request->hasFile('avatar')) {
                $file = $request->file('avatar');
                $originalName = $file->getClientOriginalName();
                $filePath = $file->storeAs('', $originalName, 'public');
                $profData['avatar'] = $originalName;
            }

            Prof::create($profData);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        // Charger les relations pour la réponse
        $user->load(['etudiant', 'prof']);

        return response()->json([
            'success' => true,
            'message' => 'Inscription réussie',
            'token' => $token,
            'user' => $this->formatUserResponse($user),
        ], 201);
    }

    /**
     * Connexion de l'utilisateur
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Les identifiants fournis sont incorrects.'],
            ]);
        }

        // Charger les relations selon le rôle
        if ($user->role === 'etudiant') {
            $user->load('etudiant');
        } elseif ($user->role === 'prof') {
            $user->load('prof');
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Connexion réussie',
            'token' => $token,
            'user' => $this->formatUserResponse($user),
        ]);
    }

    /**
     * Déconnexion de l'utilisateur
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Déconnexion réussie',
        ]);
    }

    /**
     * Récupérer le profil de l'utilisateur connecté
     */
    public function me(Request $request)
    {
        $user = $request->user();

        // Charger les relations selon le rôle
        if ($user->role === 'etudiant') {
            $user->load('etudiant');
        } elseif ($user->role === 'prof') {
            $user->load('prof');
        }

        return response()->json([
            'success' => true,
            'user' => $this->formatUserResponse($user),
        ]);
    }

    /**
     * Vérifier si le token est valide
     */
    public function verify(Request $request)
    {
        $user = $request->user();

        // Charger les relations selon le rôle
        if ($user->role === 'etudiant') {
            $user->load('etudiant');
        } elseif ($user->role === 'prof') {
            $user->load('prof');
        }

        return response()->json([
            'success' => true,
            'message' => 'Token valide',
            'user' => $this->formatUserResponse($user),
        ]);
    }

    /**
     * Mettre à jour le profil de l'utilisateur connecté
     */
    public function updateMe(Request $request)
    {
        $user = $request->user();

        // Validation commune
        $validated = $request->validate([
            'nom' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
            'email' => ['nullable', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'avatar' => 'nullable|image|max:15360', // 15MB

            // Étudiant
            'matricule' => 'nullable|string|max:255',
            'filiere' => 'nullable|string|max:255',
            'niveau' => 'nullable|string|max:255',

            // Prof
            'specialite' => 'nullable|string|max:255',
            'grade' => 'nullable|string|max:255',
        ]);

        // Mettre à jour l'utilisateur
        $user->name = $validated['nom'] ?? $validated['name'] ?? $user->name;
        $user->email = $validated['email'] ?? $user->email;
        $user->save();

        // Mise à jour selon le rôle pour les infos spécifiques + avatar
        if ($user->role === 'etudiant') {
            $etudiant = $user->etudiant ?: new Etudiant(['user_id' => $user->id]);
            $etudiant->matricule = $validated['matricule'] ?? $etudiant->matricule;
            $etudiant->filiere = $validated['filiere'] ?? $etudiant->filiere;
            $etudiant->niveau = $validated['niveau'] ?? $etudiant->niveau;

            if ($request->hasFile('avatar')) {
                $file = $request->file('avatar');
                $originalName = $file->getClientOriginalName();
                $file->storeAs('', $originalName, 'public');
                $etudiant->avatar = $originalName;
            }
            $etudiant->save();
        } elseif ($user->role === 'prof') {
            $prof = $user->prof ?: new Prof(['user_id' => $user->id]);
            $prof->matricule = $validated['matricule'] ?? $prof->matricule;
            $prof->specialite = $validated['specialite'] ?? $prof->specialite;
            $prof->grade = $validated['grade'] ?? $prof->grade;

            if ($request->hasFile('avatar')) {
                $file = $request->file('avatar');
                $originalName = $file->getClientOriginalName();
                $file->storeAs('', $originalName, 'public');
                $prof->avatar = $originalName;
            }
            $prof->save();
        }

        // Recharger les relations
        if ($user->role === 'etudiant') {
            $user->load('etudiant');
        } elseif ($user->role === 'prof') {
            $user->load('prof');
        }

        return response()->json([
            'success' => true,
            'message' => 'Profil mis à jour',
            'user' => $this->formatUserResponse($user),
        ]);
    }

    /**
     * Formater la réponse utilisateur avec les informations spécifiques
     */
    private function formatUserResponse(User $user): array
    {
        $response = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
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

