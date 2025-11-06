<?php

namespace App\Http\Controllers;

use App\Models\User;
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

        $users = User::all()->map(function ($user) {
            return [
                'id' => $user->id,
                'nom' => $user->name, // Le front-end attend "nom"
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'avatar' => $user->avatar,
                'avatar_url' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ];
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

        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non trouvé.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'nom' => $user->name,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'avatar' => $user->avatar,
                'avatar_url' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ],
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

        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role' => ['required', Rule::in(['admin', 'prof', 'etudiant'])],
            'avatar' => 'nullable|image|max:15360',
        ]);

        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            $originalName = $file->getClientOriginalName();
            // Stocker directement dans storage/app/public/ (pas dans images/)
            $filePath = $file->storeAs('', $originalName, 'public');
            // Stocker seulement le nom du fichier dans la base de données
            $validated['avatar'] = $originalName;
        }

        $user = User::create([
            'name' => $validated['nom'], // Le front-end envoie "nom"
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'avatar' => $validated['avatar'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur créé avec succès.',
            'user' => [
                'id' => $user->id,
                'nom' => $user->name,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'avatar' => $user->avatar,
                'avatar_url' => $user->avatar ? asset('storage/' . $user->avatar) : null,
            ],
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

        $user = User::find($id);

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
        ]);

        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            $originalName = $file->getClientOriginalName();
            
            // Supprimer l'ancien fichier s'il existe
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }
            
            // Stocker directement dans storage/app/public/ (pas dans images/)
            $filePath = $file->storeAs('', $originalName, 'public');
            // Stocker seulement le nom du fichier dans la base de données
            $validated['avatar'] = $originalName;
        }

        // Mettre à jour les champs
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

        // Mettre à jour l'avatar si un nouveau fichier a été uploadé
        if (isset($validated['avatar'])) {
            $user->avatar = $validated['avatar'];
        }

        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur mis à jour avec succès.',
            'user' => [
                'id' => $user->id,
                'nom' => $user->name,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'avatar' => $user->avatar,
                'avatar_url' => $user->avatar ? asset('storage/' . $user->avatar) : null,
            ],
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

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur supprimé avec succès.',
        ]);
    }
}

