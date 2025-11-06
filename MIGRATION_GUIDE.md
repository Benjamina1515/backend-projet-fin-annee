# Migration vers Tables Séparées pour Étudiants et Profs

## Vue d'ensemble

Ce projet a été migré pour séparer les informations spécifiques des étudiants et des professeurs dans des tables dédiées (`etudiants` et `profs`), tout en conservant la table `users` pour l'authentification.

## Structure de la Base de Données

### Table `users`
- `id` (PK)
- `name`
- `email` (unique)
- `password`
- `role` (admin, prof, etudiant)
- `avatar` (optionnel)
- `created_at`, `updated_at`

### Table `etudiants`
- `id` (PK)
- `user_id` (FK vers users, cascade delete)
- `matricule` (unique)
- `filiere`
- `niveau`
- `avatar` (optionnel)
- `created_at`, `updated_at`

### Table `profs`
- `id` (PK)
- `user_id` (FK vers users, cascade delete)
- `matricule` (unique)
- `specialite`
- `grade`
- `avatar` (optionnel)
- `created_at`, `updated_at`

## Relations Eloquent

### User Model
```php
// User a un Etudiant (one-to-one)
public function etudiant(): HasOne

// User a un Prof (one-to-one)
public function prof(): HasOne
```

### Etudiant Model
```php
// Etudiant appartient à un User (belongs-to)
public function user(): BelongsTo
```

### Prof Model
```php
// Prof appartient à un User (belongs-to)
public function user(): BelongsTo
```

## Routes API

### Authentification
- `POST /api/auth/register` - Inscription (prof ou etudiant uniquement)
- `POST /api/auth/login` - Connexion
- `POST /api/auth/logout` - Déconnexion (auth:sanctum)
- `GET /api/auth/me` - Profil utilisateur (auth:sanctum)
- `GET /api/auth/verify` - Vérifier token (auth:sanctum)

### Gestion des Utilisateurs (Admin uniquement)
- `GET /api/users` - Liste tous les utilisateurs
- `GET /api/users/{id}` - Détails d'un utilisateur
- `POST /api/users` - Créer un utilisateur
- `PUT /api/users/{id}` - Mettre à jour un utilisateur
- `POST /api/users/{id}` - Mettre à jour (avec FormData)
- `DELETE /api/users/{id}` - Supprimer un utilisateur

## Exemples d'Utilisation

### Inscription d'un Étudiant
```http
POST /api/auth/register
Content-Type: application/json

{
  "name": "Jean Dupont",
  "email": "jean@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "role": "etudiant",
  "matricule": "ETU2024001",
  "filiere": "Informatique",
  "niveau": "L3"
}
```

### Inscription d'un Professeur
```http
POST /api/auth/register
Content-Type: application/json

{
  "name": "Marie Martin",
  "email": "marie@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "role": "prof",
  "matricule": "PROF2024001",
  "specialite": "Mathématiques",
  "grade": "Maître de Conférences"
}
```

### Réponse de Login/Register
```json
{
  "success": true,
  "message": "Connexion réussie",
  "token": "1|...",
  "user": {
    "id": 1,
    "name": "Jean Dupont",
    "email": "jean@example.com",
    "role": "etudiant",
    "avatar": null,
    "avatar_url": null,
    "etudiant": {
      "id": 1,
      "matricule": "ETU2024001",
      "filiere": "Informatique",
      "niveau": "L3",
      "avatar": null,
      "avatar_url": null
    }
  }
}
```

### Création d'un Utilisateur par Admin
```http
POST /api/users
Authorization: Bearer {admin_token}
Content-Type: multipart/form-data

nom: Jean Dupont
email: jean@example.com
password: password123
role: etudiant
matricule: ETU2024001
filiere: Informatique
niveau: L3
avatar: [fichier image]
```

## Migration

Pour appliquer les migrations :

```bash
php artisan migrate
```

## Notes Importantes

1. **Cascade Delete** : La suppression d'un utilisateur supprime automatiquement l'enregistrement associé dans `etudiants` ou `profs` grâce à la contrainte de clé étrangère.

2. **Avatar** : L'avatar peut être stocké soit dans la table `users` (pour les admins) soit dans les tables spécifiques (`etudiants` ou `profs`).

3. **Matricule** : Le matricule est unique dans chaque table (`etudiants` et `profs`), mais peut être identique entre les deux tables.

4. **Rôle Admin** : Les administrateurs n'ont pas d'enregistrement dans `etudiants` ou `profs`, ils utilisent uniquement la table `users`.

5. **Chargement des Relations** : Les relations sont chargées automatiquement dans les réponses API selon le rôle de l'utilisateur.

## Compatibilité

- Laravel Sanctum pour l'authentification
- API RESTful
- Support FormData pour les uploads de fichiers
- Validation complète des données

