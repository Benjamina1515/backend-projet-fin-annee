<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Etudiant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class EtudiantController extends Controller
{
    /**
     * Créer un nouvel étudiant (Admin uniquement)
     */
    public function store(Request $request)
    {
        // Vérifier que l'utilisateur est admin
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé. Seuls les administrateurs peuvent créer des étudiants.',
            ], 403);
        }

        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'matricule' => 'required|string|max:255|unique:etudiants,matricule',
            'filiere' => 'required|string|max:255',
            'niveau' => 'required|string|max:255',
            'avatar' => 'nullable|image|max:15360',
        ]);

        // Créer l'utilisateur (mapper 'nom' vers 'name' pour la base de données)
        $user = User::create([
            'name' => $validated['nom'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'etudiant',
        ]);

        // Créer le profil étudiant
        $etudiantData = [
            'user_id' => $user->id,
            'matricule' => $validated['matricule'],
            'filiere' => $validated['filiere'],
            'niveau' => $validated['niveau'],
        ];

        // Gérer l'avatar si présent
        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            $originalName = $file->getClientOriginalName();
            $filePath = $file->storeAs('', $originalName, 'public');
            $etudiantData['avatar'] = $originalName;
        }

        $etudiant = Etudiant::create($etudiantData);

        // Charger les relations pour la réponse
        $user->load('etudiant');

        return response()->json([
            'success' => true,
            'message' => 'Étudiant créé avec succès.',
            'user' => [
                'id' => $user->id,
                'nom' => $user->name,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'etudiant' => [
                    'id' => $etudiant->id,
                    'matricule' => $etudiant->matricule,
                    'filiere' => $etudiant->filiere,
                    'niveau' => $etudiant->niveau,
                    'avatar' => $etudiant->avatar,
                    'avatar_url' => $etudiant->avatar ? asset('storage/' . $etudiant->avatar) : null,
                ],
            ],
        ], 201);
    }

    /**
     * Mettre à jour un étudiant (Admin uniquement)
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

        $user = User::with('etudiant')->where('role', 'etudiant')->find($id);

        if (!$user || !$user->etudiant) {
            return response()->json([
                'success' => false,
                'message' => 'Étudiant non trouvé.',
            ], 404);
        }

        $etudiant = $user->etudiant;

        $validated = $request->validate([
            'nom' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => 'sometimes|string|min:6',
            'matricule' => ['sometimes', 'string', 'max:255', Rule::unique('etudiants', 'matricule')->ignore($etudiant->id)],
            'filiere' => 'sometimes|string|max:255',
            'niveau' => 'sometimes|string|max:255',
            'avatar' => 'nullable|image|max:15360',
        ]);

        // Mettre à jour l'utilisateur (mapper 'nom' vers 'name' pour la base de données)
        if (isset($validated['nom'])) {
            $user->name = $validated['nom'];
        }
        if (isset($validated['email'])) {
            $user->email = $validated['email'];
        }
        if (isset($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }
        $user->save();

        // Mettre à jour le profil étudiant
        if (isset($validated['matricule'])) {
            $etudiant->matricule = $validated['matricule'];
        }
        if (isset($validated['filiere'])) {
            $etudiant->filiere = $validated['filiere'];
        }
        if (isset($validated['niveau'])) {
            $etudiant->niveau = $validated['niveau'];
        }

        // Gérer l'avatar si présent
        if ($request->hasFile('avatar')) {
            // Supprimer l'ancien avatar s'il existe
            if ($etudiant->avatar && Storage::disk('public')->exists($etudiant->avatar)) {
                Storage::disk('public')->delete($etudiant->avatar);
            }

            $file = $request->file('avatar');
            $originalName = $file->getClientOriginalName();
            $filePath = $file->storeAs('', $originalName, 'public');
            $etudiant->avatar = $originalName;
        }

        $etudiant->save();
        $user->load('etudiant');

        return response()->json([
            'success' => true,
            'message' => 'Étudiant mis à jour avec succès.',
            'user' => [
                'id' => $user->id,
                'nom' => $user->name,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'etudiant' => [
                    'id' => $etudiant->id,
                    'matricule' => $etudiant->matricule,
                    'filiere' => $etudiant->filiere,
                    'niveau' => $etudiant->niveau,
                    'avatar' => $etudiant->avatar,
                    'avatar_url' => $etudiant->avatar ? asset('storage/' . $etudiant->avatar) : null,
                ],
            ],
        ]);
    }

    /**
     * Supprimer un étudiant (Admin uniquement)
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

        $user = User::with('etudiant')->where('role', 'etudiant')->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Étudiant non trouvé.',
            ], 404);
        }

        // Empêcher la suppression de l'utilisateur lui-même
        if ($user->id === $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez pas supprimer votre propre compte.',
            ], 400);
        }

        // Supprimer l'avatar s'il existe
        if ($user->etudiant && $user->etudiant->avatar && Storage::disk('public')->exists($user->etudiant->avatar)) {
            Storage::disk('public')->delete($user->etudiant->avatar);
        }

        // La suppression en cascade gérera automatiquement l'étudiant
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Étudiant supprimé avec succès.',
        ]);
    }
}

