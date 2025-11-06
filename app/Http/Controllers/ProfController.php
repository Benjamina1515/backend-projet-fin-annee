<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Prof;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfController extends Controller
{
    /**
     * Créer un nouveau professeur (Admin uniquement)
     */
    public function store(Request $request)
    {
        // Vérifier que l'utilisateur est admin
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé. Seuls les administrateurs peuvent créer des professeurs.',
            ], 403);
        }

        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'matricule' => 'required|string|max:255|unique:profs,matricule',
            'specialite' => 'required|string|max:255',
            'grade' => 'required|string|max:255',
            'avatar' => 'nullable|image|max:15360',
        ]);

        // Créer l'utilisateur (mapper 'nom' vers 'name' pour la base de données)
        $user = User::create([
            'name' => $validated['nom'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'prof',
        ]);

        // Créer le profil professeur
        $profData = [
            'user_id' => $user->id,
            'matricule' => $validated['matricule'],
            'specialite' => $validated['specialite'],
            'grade' => $validated['grade'],
        ];

        // Gérer l'avatar si présent
        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            $originalName = $file->getClientOriginalName();
            $filePath = $file->storeAs('', $originalName, 'public');
            $profData['avatar'] = $originalName;
        }

        $prof = Prof::create($profData);

        // Charger les relations pour la réponse
        $user->load('prof');

        return response()->json([
            'success' => true,
            'message' => 'Professeur créé avec succès.',
            'user' => [
                'id' => $user->id,
                'nom' => $user->name,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'prof' => [
                    'id' => $prof->id,
                    'matricule' => $prof->matricule,
                    'specialite' => $prof->specialite,
                    'grade' => $prof->grade,
                    'avatar' => $prof->avatar,
                    'avatar_url' => $prof->avatar ? asset('storage/' . $prof->avatar) : null,
                ],
            ],
        ], 201);
    }

    /**
     * Mettre à jour un professeur (Admin uniquement)
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

        $user = User::with('prof')->where('role', 'prof')->find($id);

        if (!$user || !$user->prof) {
            return response()->json([
                'success' => false,
                'message' => 'Professeur non trouvé.',
            ], 404);
        }

        $prof = $user->prof;

        $validated = $request->validate([
            'nom' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => 'sometimes|string|min:6',
            'matricule' => ['sometimes', 'string', 'max:255', Rule::unique('profs', 'matricule')->ignore($prof->id)],
            'specialite' => 'sometimes|string|max:255',
            'grade' => 'sometimes|string|max:255',
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

        // Mettre à jour le profil professeur
        if (isset($validated['matricule'])) {
            $prof->matricule = $validated['matricule'];
        }
        if (isset($validated['specialite'])) {
            $prof->specialite = $validated['specialite'];
        }
        if (isset($validated['grade'])) {
            $prof->grade = $validated['grade'];
        }

        // Gérer l'avatar si présent
        if ($request->hasFile('avatar')) {
            // Supprimer l'ancien avatar s'il existe
            if ($prof->avatar && Storage::disk('public')->exists($prof->avatar)) {
                Storage::disk('public')->delete($prof->avatar);
            }

            $file = $request->file('avatar');
            $originalName = $file->getClientOriginalName();
            $filePath = $file->storeAs('', $originalName, 'public');
            $prof->avatar = $originalName;
        }

        $prof->save();
        $user->load('prof');

        return response()->json([
            'success' => true,
            'message' => 'Professeur mis à jour avec succès.',
            'user' => [
                'id' => $user->id,
                'nom' => $user->name,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'prof' => [
                    'id' => $prof->id,
                    'matricule' => $prof->matricule,
                    'specialite' => $prof->specialite,
                    'grade' => $prof->grade,
                    'avatar' => $prof->avatar,
                    'avatar_url' => $prof->avatar ? asset('storage/' . $prof->avatar) : null,
                ],
            ],
        ]);
    }

    /**
     * Supprimer un professeur (Admin uniquement)
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

        $user = User::with('prof')->where('role', 'prof')->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Professeur non trouvé.',
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
        if ($user->prof && $user->prof->avatar && Storage::disk('public')->exists($user->prof->avatar)) {
            Storage::disk('public')->delete($user->prof->avatar);
        }

        // La suppression en cascade gérera automatiquement le prof
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Professeur supprimé avec succès.',
        ]);
    }
}

