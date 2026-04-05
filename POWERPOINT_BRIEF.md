# Brief PowerPoint — SIGNAL v2
## Document de génération pour Claude / outil de présentation

---

## Instructions pour Claude

Génère une présentation PowerPoint professionnelle de **12 slides** pour le projet SIGNAL v2, présenté lors du Hackathon RATP (Decode School · Master No-code / Low-code · 2026).

**Ton et style :**
- Professionnel, sobre, percutant
- Couleurs : bleu RATP `#003189`, blanc `#FFFFFF`, rouge urgence `#DC3545`, gris `#6C757D`
- Police titre : bold, grande taille
- Police corps : regular, taille lisible
- Chaque slide = 1 message clé, pas de pavé de texte
- Privilégier les icônes, chiffres en grand, listes courtes (3-5 points max)
- Ratio 16:9

**Outil recommandé :** python-pptx, ou exporte vers Google Slides / PowerPoint.

---

## Structure des 12 slides

---

### SLIDE 1 — Couverture

**Titre principal :** SIGNAL
**Sous-titre :** De la plainte à la décision — en heures, pas 48.
**Mention :** Hackathon RATP · Decode School · 2026
**Visuel suggéré :** Logo RATP + fond bleu `#003189` + texte blanc
**Note orateur :** Accroche : on résout un problème réel, urgent, chiffré.

---

### SLIDE 2 — Le problème (chiffres)

**Titre :** Un système qui perd du temps et de l'argent

**4 blocs visuels (cards) :**
- 🕐 **48h** — délai moyen de remontée d'un signalement
- 📹 **24h** — durée de conservation des bandes vidéo
- ⚠️ **60 000 €** — amende IDFM par rapport client mystère négatif
- 💸 **~10 M€/an** — pertes annuelles RATP estimées liées aux amendes IDFM

**Message clé :** Quand le dossier arrive, les preuves ont disparu.
**Note orateur :** Le problème n'est pas le manque de volonté — c'est le délai structurel.

---

### SLIDE 3 — Les 4 problèmes critiques

**Titre :** Ce qui ne fonctionne pas aujourd'hui

**Liste visuelle :**
1. **Délai de remontée** — 48h vs 24h de conservation vidéo → preuves perdues
2. **Erreurs d'attribution** — identification manuelle de l'agent = erreur humaine
3. **Amendes IDFM** — clients mystères, aucune preuve pour contester
4. **Sources dispersées** — mail, réseaux, téléphone, formulaire de 10 min → abandon

**Note orateur :** Ces 4 problèmes se combinent. SIGNAL résout les 4 en même temps.

---

### SLIDE 4 — La solution : vue d'ensemble

**Titre :** SIGNAL — 6 étapes, une plateforme

**Schéma horizontal en 6 blocs enchaînés :**

```
[1. ENTRÉE MULTI-CANAL]
       ↓
[2. ANALYSE IA]
       ↓
[3. HISTORIQUE AGENT]
       ↓
[4. COURRIER AUTO]
       ↓
[5. VALIDATION RH]
       ↓
[6. ENVOI & SUIVI]
```

**Sous-chaque bloc :** 1 ligne descriptive
1. Web · QR terrain · DM · Réseaux sociaux · Note vocale
2. Qualification · Score gravité · Attribution agent
3. Profil enrichi · Patterns · Score surveillance
4. Brouillon IA · Modifiable · 3 modèles
5. Validation humaine obligatoire · Audit complet
6. Maileva API · Suivi temps réel · Dashboard

**Note orateur :** L'IA prépare, l'humain décide. Toujours.

---

### SLIDE 5 — Expérience usager (B2C)

**Titre :** Pour l'usager : 30 secondes, zéro friction

**Deux colonnes :**

**Avant SIGNAL**
- Formulaire de 10 minutes
- Abandon massif
- Aucun accusé de réception
- Barrière linguistique

**Avec SIGNAL**
- 2 cards visuelles → 1 clic pour choisir le type
- Formulaire adaptatif pré-rempli (QR/NFC)
- Note vocale + détection langue automatique
- Accusé de réception immédiat
- Coordonnées optionnelles — aucun compte requis

**Visuel suggéré :** Mockup mobile du formulaire avec les 2 cards incident/positif

**Note orateur :** L'usager n'a pas à savoir ce qu'est un "type de signalement". Il choisit entre "signaler un problème" et "partager un avis positif".

---

### SLIDE 6 — Canaux de collecte

**Titre :** Chaque canal identifié automatiquement

**Tableau 4 colonnes :**

| Canal | Accès | Canal détecté |
|-------|-------|---------------|
| 🌐 Formulaire web | ratp.ddev.site/public/signalement | Formulaire web |
| 📱 QR Terrain | Scan NFC/QR dans le bus | Terrain (QR) |
| 💬 Lien DM | Lien partagé par message privé | Message direct |
| 📲 Réseaux sociaux | Instagram, X, etc. | Canal social |

**Message clé :** Aucune saisie manuelle de la source. Le canal est tracé côté serveur.

---

### SLIDE 7 — Ce que voit le manager

**Titre :** Le manager : tout le dossier en un écran

**Liste visuelle (6 éléments avec icônes) :**
- 📋 Description des faits telle que rapportée par le client
- 🤖 Analyse IA automatique : résumé, score 1-4, justification de la gravité
- ⚡ Niveau d'urgence + actions recommandées
- 👤 Fiche agent : score de surveillance, historique complet
- 📹 Alerte conservation vidéo 24h si applicable
- 📬 Envoi en validation RH en 1 clic

**Note orateur :** Dès la soumission, sans intervention humaine, le dossier est déjà qualifié.

---

### SLIDE 8 — L'IA en détail

**Titre :** Ce que l'IA génère automatiquement

**6 blocs visuels (grid 2×3) :**

| | |
|---|---|
| **Résumé des faits** en langage naturel | **Score décisionnel** 1 à 4 avec code couleur |
| **Gravité détectée** (faible / moyen / grave) | **Facteurs de risque** identifiés |
| **Actions recommandées** au manager | **Brouillon de courrier** prêt à valider |

**Résilience :** Failover automatique entre 3 providers (OpenRouter · Gemini · Ollama)
Si un provider tombe → bascule automatique, zéro interruption.

**Note orateur :** L'IA ne décide pas. Elle prépare et justifie. Le RH valide et signe.

---

### SLIDE 9 — Fiche agent & export PDF

**Titre :** Le dossier disciplinaire en 1 clic

**Deux blocs :**

**Fiche agent (écran)**
- Score de surveillance Niveau 1-4
- Badge couleur : Normal / Attention / Critique
- Historique complet des signalements avec gravité et statut
- Recherche instantanée sans rechargement

**Export PDF**
- Identité complète de l'agent
- Score et niveau d'attention
- Synthèse IA
- Historique détaillé de tous les incidents

**Accessible à :** Manager + RH

**Note orateur :** Le RH peut imprimer ou envoyer le dossier au juriste en 1 clic. Zéro copier-coller.

---

### SLIDE 10 — Analyse de tendances IA

**Titre :** L'intelligence qui voit ce que l'œil humain rate

**Schéma comparatif 30 jours vs 30 jours précédents :**

Ce que l'IA détecte en 1 clic :
- Niveau d'alerte global : Normal / Attention / Critique
- Résumé exécutif réseau (30 jours)
- Hausse des incidents graves, ligne problématique
- Agents récidivistes, axes à risque
- Actions recommandées à la direction

**Citation différenciante :**
> "Mes concurrents ont une carte. Nous avons une intelligence qui lit les patterns et recommande des décisions."

---

### SLIDE 11 — Piste d'audit & conformité RGPD

**Titre :** Traçabilité complète — Immuable — Auditable

**3 colonnes :**

**Qui a fait quoi**
- Chaque action horodatée
- Acteur identifié
- Rôle tracé

**Chaque analyse IA**
- Fournisseur utilisé
- Score attribué
- Bascule failover notée

**Chaque décision RH**
- Validation ou refus
- Commentaire obligatoire
- Courrier signé

**En bas :** `L'IA aide · Le RH décide · Tout est tracé`

---

### SLIDE 12 — Stack technique & roadmap

**Titre :** Construit pour durer

**Colonne gauche — Stack actuel :**
- Symfony 8 · PHP 8.4
- PostgreSQL 16
- Bootstrap 5 · Stimulus / Turbo
- DDEV (dev) → Docker (prod)
- OpenRouter · Gemini · Ollama (failover IA)
- Maileva API (courrier postal)

**Colonne droite — Roadmap :**
- NFC physique par véhicule (mascotte RATP)
- Notification agent (droits + timer vidéo)
- Connecteur réseaux sociaux (Instagram, X)
- Module IDFM clients mystères
- Application mobile légère
- Enrichissement juridique automatisé

**Note orateur :** Le MVP est fonctionnel. La roadmap est priorisée par impact métier.

---

## Données clés à mettre en valeur (grands chiffres)

| Chiffre | Contexte |
|---------|----------|
| **48h → quelques heures** | Délai de traitement réduit |
| **24h** | Fenêtre critique conservation vidéo |
| **60 000 €** | Amende unitaire IDFM |
| **~10 M€/an** | Pertes RATP évitables |
| **4 canaux** | Web, QR terrain, DM, réseaux sociaux |
| **3 providers IA** | Résilience failover automatique |
| **Score 1-4** | Gravité agent normalisée |
| **6 étapes** | Du signalement à l'envoi du courrier |

---

## Palette couleurs à utiliser

```
Bleu RATP principal :  #003189
Blanc :                #FFFFFF
Rouge urgence :        #DC3545
Vert positif :         #198754
Gris texte secondaire: #6C757D
Fond carte neutre :    #F8F9FA
Bleu clair accent :    #0D6EFD
```

---

## Prompt à donner à Claude pour générer le fichier .pptx

```
Tu vas générer un fichier PowerPoint (.pptx) en Python avec python-pptx.

Utilise le brief ci-dessus (POWERPOINT_BRIEF.md) pour créer une présentation de 12 slides.

Contraintes techniques :
- Format 16:9 (largeur 33.87 cm, hauteur 19.05 cm)
- Chaque slide doit avoir un titre et un contenu structuré
- Utilise les couleurs définies dans la palette
- Les slides à grand chiffre (slide 2) doivent avoir le chiffre en très grande police (60pt+)
- Les listes ne dépassent pas 5 points
- Génère le fichier sous le nom : SIGNAL_v2_presentation.pptx

Génère le script Python complet et autonome.
```
