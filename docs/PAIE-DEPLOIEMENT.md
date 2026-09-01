# Déploiement du module Paie (Admin) — migration & email

Je n'ai pas pu exécuter la migration ni vérifier `MAILER_DSN` moi-même : mon
environnement de travail n'a ni PHP, ni serveur de base de données, ni accès
réseau — je ne peux qu'éditer le code, pas lancer ton projet. Voici donc
exactement quoi faire de ton côté.

## 1. Aucun `.env.local` fourni

Le zip que tu m'as donné ne contient **aucun fichier `.env` ni `.env.local`**
(normal, ils sont généralement ignorés par git / exclus des exports). Je ne
peux donc pas savoir si `MAILER_DSN` est déjà configuré chez toi. Utilise le
fichier `.env.local.example` ajouté à la racine du projet comme modèle : copie
son contenu dans ton `.env.local` réel et adapte les valeurs.

## 2. Vérifier / configurer `MAILER_DSN`

Ouvre (ou crée) `.env.local` à la racine et vérifie qu'une ligne `MAILER_DSN`
y figure :

**En local avec le docker-compose fourni (Mailpit, inclus dans
`compose.override.yaml`)** — aucun vrai email n'est envoyé, tout est capturé
par Mailpit :
```
MAILER_DSN=smtp://mailer:1025
```
Interface Mailpit pour voir les fiches de paie « envoyées » : http://localhost:8025

**En production**, utilise un vrai transport (exemples) :
```
# Gmail / Google Workspace
MAILER_DSN=gmail+smtp://VOTRE_EMAIL:MOT_DE_PASSE_APPLICATION@default

# SMTP générique (OVH, Infomaniak, etc.)
MAILER_DSN=smtp://USER:PASSWORD@smtp.votre-hebergeur.com:587

# Un service transactionnel (recommandé en prod) : Brevo, Mailgun, Postmark...
MAILER_DSN=brevo+api://VOTRE_CLE_API@default
```
Sans `MAILER_DSN` valide, le calcul et le paiement fonctionneront quand même,
mais l'envoi automatique de la fiche de paie échouera silencieusement pour
l'employé (message flash "warning" affiché à l'admin — voir
`PaieController::valider()` qui capture l'exception sans bloquer le
paiement).

## 3. Appliquer la migration

```bash
# Avec Symfony CLI / PHP installé localement
php bin/console doctrine:migrations:status
php bin/console doctrine:migrations:migrate

# Ou via le docker-compose fourni
docker compose exec php bin/console doctrine:migrations:migrate
```
Cela crée les colonnes `paid_at` et `payslip_sent_at` sur la table `paie`
(fichier `migrations/Version20260820074438.php`).

### ⚠️ Point d'attention détecté : MySQL vs PostgreSQL

`compose.yaml` démarre une base **PostgreSQL**, et `config/packages/doctrine.yaml`
référence explicitement `PostgreSQLPlatform`. Mais **toutes les migrations du
projet, y compris celles déjà présentes avant mon intervention**
(ex. `Version20260819111656.php`) utilisent une syntaxe **MySQL/MariaDB**
(`CHANGE`, `DATETIME`), qui n'est pas valide sur PostgreSQL (`ALTER COLUMN`,
`TIMESTAMP`). J'ai suivi le style déjà en place dans le projet par cohérence,
mais si ta base réelle est PostgreSQL, ma migration comme les précédentes
échoueront. Si tu utilises MySQL/MariaDB (XAMPP/WAMP en local par exemple),
tout est déjà compatible et il n'y a rien à changer. Dis-moi laquelle des deux
bases tu utilises réellement si tu veux que je corrige ce point.

## 4. Après la migration

Vide le cache pour être sûr que les nouvelles entités/routes sont bien prises
en compte :
```bash
php bin/console cache:clear
```
