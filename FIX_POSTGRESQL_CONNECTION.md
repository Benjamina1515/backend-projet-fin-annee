# Guide pour résoudre le problème de connexion PostgreSQL

## ✅ PROBLÈME RÉSOLU

Le problème a été résolu avec succès ! Les extensions PostgreSQL ont été activées et toutes les migrations ont été exécutées.

## 🔍 Problème identifié (résolu)

L'erreur **"could not find driver"** indiquait que l'extension PHP pour PostgreSQL (`pdo_pgsql`) n'était pas activée dans votre installation XAMPP.

## ✅ Vérifications effectuées

- ✅ PostgreSQL est installé et fonctionne (service `postgresql-x64-14` en cours d'exécution)
- ✅ Les fichiers DLL nécessaires existent dans `C:\xampp\php\ext\`
- ✅ La configuration `.env` est correcte (`DB_CONNECTION=pgsql`)
- ❌ Les extensions PostgreSQL sont commentées dans `php.ini`

## 🔧 Solution : Activer les extensions PostgreSQL

### Étape 1 : Ouvrir le fichier php.ini

1. Ouvrez le fichier `C:\xampp\php\php.ini` avec un éditeur de texte (Notepad++ ou VS Code)
   - **Important** : Ouvrez en tant qu'administrateur si nécessaire

### Étape 2 : Décommenter les extensions

Recherchez ces lignes (vers la ligne 947-949) :

```ini
;extension=pdo_pgsql
;extension=pgsql
```

Et remplacez-les par :

```ini
extension=pdo_pgsql
extension=pgsql
```

(Supprimez le point-virgule `;` au début de chaque ligne)

### Étape 3 : Redémarrer Apache

1. Ouvrez le **Panneau de contrôle XAMPP**
2. Arrêtez Apache (bouton "Stop")
3. Redémarrez Apache (bouton "Start")

### Étape 4 : Vérifier l'activation

Exécutez cette commande dans PowerShell :

```powershell
php -m | Select-String -Pattern "pgsql"
```

Vous devriez voir :
```
pdo_pgsql
pgsql
```

### Étape 5 : Vider le cache Laravel

```powershell
cd "backend-projet-fin-annee"
php artisan config:clear
php artisan cache:clear
```

### Étape 6 : Tester la connexion

```powershell
php artisan migrate:status
```

Si cela fonctionne, vous devriez voir la liste des migrations sans erreur.

## 🔍 Vérification de la base de données

Assurez-vous que la base de données `suivi_academique` existe :

```sql
-- Connectez-vous à PostgreSQL avec psql ou pgAdmin
CREATE DATABASE suivi_academique;
```

Ou vérifiez qu'elle existe :

```sql
\l
```

## 📝 Configuration actuelle (.env)

Votre configuration actuelle est :
```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=suivi_academique
DB_USERNAME=postgres
DB_PASSWORD=sarangheo
```

## ⚠️ Si le problème persiste

1. Vérifiez que PostgreSQL écoute sur le port 5432 :
   ```powershell
   netstat -an | Select-String "5432"
   ```

2. Vérifiez les logs PostgreSQL dans :
   - `C:\Program Files\PostgreSQL\14\data\log\`

3. Testez la connexion manuellement :
   ```powershell
   psql -U postgres -h 127.0.0.1 -p 5432 -d suivi_academique
   ```

4. Vérifiez le fichier `pg_hba.conf` de PostgreSQL pour s'assurer que les connexions locales sont autorisées.

