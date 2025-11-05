# Installation de Laravel Sanctum

## Problème
Le trait `HasApiTokens` n'est pas trouvé car Sanctum n'est pas encore installé.

## Solution

### 1. Installer Sanctum
Exécutez cette commande dans le terminal :

```bash
cd backend-projet-fin-annee
composer install
```

Ou si vous voulez seulement installer Sanctum :

```bash
cd backend-projet-fin-annee
composer require laravel/sanctum
```

### 2. Réactiver Sanctum dans le modèle User

Après l'installation, modifiez `app/Models/User.php` :

```php
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;
    // ...
}
```

### 3. Exécuter les migrations

```bash
php artisan migrate
php artisan db:seed
```

## Alternative : Installation manuelle

Si les commandes PowerShell posent problème, vous pouvez :

1. Ouvrir un terminal dans le dossier `backend-projet-fin-annee`
2. Exécuter : `composer install`
3. Modifier le fichier `app/Models/User.php` pour ajouter `HasApiTokens` au trait use

