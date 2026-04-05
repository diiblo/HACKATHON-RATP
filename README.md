# RATP Signalements — Plateforme de gestion intelligente

Plateforme de gestion des signalements agents RATP avec pré-triage IA, workflow RH, export PDF et analyse de tendances.

**Stack** : Symfony 8 · PHP 8.4 · PostgreSQL 16 · DDEV · Bootstrap 5 · Stimulus/Turbo

---

## Prérequis

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (ou Docker Engine sous Linux)
- [DDEV](https://ddev.readthedocs.io/en/stable/#installation) v1.23+

Vérifier l'installation :

```bash
docker --version
ddev --version
```

---

## Installation

### 1. Cloner le dépôt

```bash
git clone <url-du-repo> ratp
cd ratp
```

### 2. Démarrer l'environnement DDEV

```bash
ddev start
```

DDEV crée automatiquement les conteneurs PHP 8.4, PostgreSQL 16 et Nginx. Le projet est accessible sur **https://ratp.ddev.site**.

### 3. Installer les dépendances PHP

```bash
ddev composer install
```

### 4. Configurer les variables d'environnement

```bash
cp .env .env.local
```

Éditer `.env.local` et renseigner les clés IA (optionnelles, le projet fonctionne sans) :

```dotenv
# Clé API OpenRouter (fournisseur IA principal)
OPENROUTER_API_KEY=sk-or-...

# Clé API Google Gemini (fournisseur IA secondaire)
GEMINI_API_KEY=AIza...
```

### 5. Créer la base de données et appliquer les migrations

```bash
ddev exec php bin/console doctrine:database:create --if-not-exists
ddev exec php bin/console doctrine:migrations:migrate --no-interaction
```

### 6. Charger les données de démonstration

```bash
ddev exec php bin/console app:load-fixtures --reset
```

### 7. Synchroniser les fournisseurs IA (optionnel)

```bash
ddev exec php bin/console app:ai:sync-real-providers
```

### 8. Compiler les assets front-end

```bash
ddev exec php bin/console asset-map:compile
```

---

## Accès

| URL | Description |
|-----|-------------|
| https://ratp.ddev.site | Tableau de bord (connexion requise) |
| https://ratp.ddev.site/public/signalement | Formulaire public — web |
| https://ratp.ddev.site/public/signalement/terrain | Formulaire public — terrain (QR) |
| https://ratp.ddev.site/public/signalement/message-direct | Formulaire public — lien DM |
| https://ratp.ddev.site/public/demo | Page de démonstration guidée |

### Comptes de démonstration

| Email | Mot de passe | Rôle |
|-------|-------------|------|
| manager1@ratp.fr | Manager1! | Manager — accès complet signalements + IA |
| rh1@ratp.fr | Rh12345! | RH — validation courriers, export PDF agent |
| devadmin@ratp.fr | DevAdmin1! | Dev Admin — configuration IA, journal d'audit |
| admin@ratp.fr | Admin1234! | Admin métier |

---

## Commandes utiles

### Environnement DDEV

```bash
# Démarrer
ddev start

# Arrêter
ddev stop

# Redémarrer (après modif config .ddev/)
ddev restart

# Ouvrir le projet dans le navigateur
ddev launch

# Accéder au shell du conteneur web
ddev ssh

# Exécuter une commande dans le conteneur
ddev exec <commande>
```

### Symfony

```bash
# Vider le cache
ddev exec php bin/console cache:clear

# Lister toutes les routes
ddev exec php bin/console debug:router

# Lister les services
ddev exec php bin/console debug:container

# Vérifier la configuration de sécurité
ddev exec php bin/console debug:firewall
```

### Base de données

```bash
# Appliquer les migrations
ddev exec php bin/console doctrine:migrations:migrate --no-interaction

# Générer une nouvelle migration après modif d'entité
ddev exec php bin/console make:migration

# Vérifier le statut des migrations
ddev exec php bin/console doctrine:migrations:status

# Réinitialiser complètement la base (DESTRUCTIF)
ddev exec php bin/console doctrine:database:drop --force
ddev exec php bin/console doctrine:database:create
ddev exec php bin/console doctrine:migrations:migrate --no-interaction
ddev exec php bin/console app:load-fixtures --reset
```

### Fixtures & données

```bash
# Recharger les données de démo fraîches
ddev exec php bin/console app:load-fixtures --reset
```

### IA

```bash
# Synchroniser les fournisseurs IA configurés en base
ddev exec php bin/console app:ai:sync-real-providers

# Tester un fournisseur IA
ddev exec php bin/console app:ai:test-provider

# Tester le mécanisme de bascule (failover)
ddev exec php bin/console app:ai:test-provider --failover

# Lancer une analyse IA sur un signalement (par ID)
ddev exec php bin/console app:ai:analyze-signalement <id>
```

### Assets front-end

```bash
# Compiler les assets (production)
ddev exec php bin/console asset-map:compile

# Linter Twig
ddev exec php bin/console lint:twig templates/
```

### Logs

```bash
# Suivre les logs Symfony en temps réel
ddev exec tail -f var/log/dev.log

# Logs DDEV (Nginx, PHP-FPM)
ddev logs
ddev logs -f   # mode live
```

---

## Réinitialisation rapide avant démo

```bash
ddev exec php bin/console app:load-fixtures --reset
ddev exec php bin/console app:ai:sync-real-providers
ddev exec php bin/console cache:clear
```

---

## Structure du projet

```
src/
├── Command/          # Commandes console (fixtures, IA, tests)
├── Controller/       # Contrôleurs Symfony (public, signalement, agent, RH...)
├── Entity/           # Entités Doctrine (Signalement, Agent, User, Courrier...)
├── Form/             # Types de formulaires
├── Repository/       # Requêtes Doctrine
└── Service/          # Services métier (IA, PDF, Maileva, score...)

templates/
├── public/           # Formulaires publics (web, terrain, DM, réseaux sociaux)
├── signalement/      # Vue et liste des signalements
├── agent/            # Fiche et liste des agents
├── courrier/         # Workflow RH courriers
└── base.html.twig    # Layout principal avec sidebar

migrations/           # Migrations Doctrine (versionnées)
```

---

## Fonctionnalités principales

- **Formulaires multi-canaux** : web, terrain QR, lien DM, réseaux sociaux — chaque canal identifié automatiquement
- **Pré-triage IA** : qualification automatique dès la soumission (gravité, priorité, synthèse)
- **Analyse IA complète** : résumé, facteurs de risque, actions recommandées, brouillon de courrier
- **Analyse de tendances** : lecture des patterns sur 30 jours vs 30 jours précédents
- **Workflow RH** : courriers pré-rédigés par l'IA, validation, envoi postal simulé (Maileva)
- **Fiche agent** : score de surveillance 1–4, historique complet, export PDF en un clic
- **Tableau de bord live** : polling automatique toutes les 30s, alertes agents critiques
- **Piste d'audit** : chaque action tracée avec acteur, horodatage et fournisseur IA utilisé
- **Résilience IA** : bascule automatique entre 3 fournisseurs (OpenRouter, Gemini, Ollama)
