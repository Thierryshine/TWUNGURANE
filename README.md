# TWUNGURANE - Application de Gestion des Groupes d'Épargne Communautaire

Application web fintech pour la gestion digitale des groupes d'épargne communautaire (tontines, VSLA, groupes solidaires) au Burundi.

## 🎯 Description

TWUNGURANE est une application web moderne qui permet de digitaliser la gestion des groupes d'épargne communautaire, améliorant ainsi la transparence financière et renforçant la confiance entre les membres.

## ✨ Fonctionnalités

### 🔐 Authentification
- Inscription via téléphone et email
- Connexion sécurisée
- Vérification OTP simulée (SMS)
- Gestion des rôles (Administrateur, Trésorier, Membre)

### 👥 Gestion des Cercles d'Épargne
- Création de cercles (Tontine, VSLA, Groupe Solidaire)
- Configuration des paramètres :
  - Nom, type, localisation (Province/Commune)
  - Montant de contribution
  - Fréquence (hebdomadaire/mensuelle)
  - Durée du cycle
  - Limite de 20 membres par cercle
- Invitation de membres par téléphone

### 👤 Gestion des Membres
- Ajout, modification et suppression de membres
- Informations complètes : nom, téléphone, rôle, statut
- Historique individuel des contributions
- Statuts : Actif / Suspendu

### 💰 Transactions et Contributions
- Enregistrement des contributions (épargne, pénalité, prêt, remboursement)
- Simulation des moyens de paiement :
  - Lumicash
  - EcoCash
  - Espèces
- Historique avec filtres avancés
- Calculs automatiques (totaux par membre et par cercle)

### 💵 Prêts Internes (VSLA)
- Soumission de demande de prêt
- Validation par l'administrateur
- Plan de remboursement
- Suivi du solde restant

### 📊 Tableau de Bord
- Indicateurs clés :
  - Solde total du groupe
  - Contributions mensuelles
  - Nombre de membres actifs
  - Nombre de cercles
- Graphiques dynamiques (Chart.js) :
  - Évolution des contributions
  - Répartition par cercle
- Notifications (retards, fin de cycle)

### 📈 Rapports
- Rapports par membre, groupe et période
- Filtres avancés
- Export CSV
- Export PDF (simulation)

### 📞 Contact et Support
- Formulaire de contact avec validation
- Informations de contact :
  - Email : support@twungurane.bi
  - WhatsApp Business
  - Adresse : Bujumbura, Burundi

## 🛠️ Technologies Utilisées

- **Frontend** :
  - HTML5
  - CSS3 (Flexbox, Grid)
  - JavaScript ES6+
  - Chart.js pour les graphiques
  - Font Awesome pour les icônes
  - Google Fonts (Inter, Roboto)

- **Stockage** :
  - LocalStorage pour la persistance des données

## 📁 Structure du Projet

```
TWUNGURANE/
├── index.html          # Page principale
├── css/
│   └── style.css      # Styles fintech (vert, or, blanc)
├── js/
│   ├── app.js          # Logique principale de l'application
│   └── data.js         # Gestion des données (LocalStorage)
├── assets/             # Ressources (images, etc.)
└── README.md           # Documentation
```

## 🚀 Installation et Utilisation

1. **Cloner ou télécharger le projet**

2. **Ouvrir l'application** :
   - Ouvrir `index.html` dans un navigateur web moderne
   - Ou utiliser un serveur local (recommandé) :
     ```bash
     # Avec Python
     python -m http.server 8000
     
     # Avec Node.js (http-server)
     npx http-server
     ```

3. **Première utilisation** :
   - Créer un compte (inscription)
   - Le code OTP sera affiché dans une alerte (simulation)
   - Se connecter avec vos identifiants
   - Créer votre premier cercle d'épargne

## 🎨 Design

- **Palette de couleurs** :
  - Vert primaire : #00A859
  - Or : #FFD700
  - Blanc : #FFFFFF
- **Typographie** : Inter / Roboto
- **Design** : Mobile-first, responsive
- **Style** : Moderne, inspiré des fintech africaines

## 📱 Compatibilité

- Navigateurs modernes (Chrome, Firefox, Safari, Edge)
- Responsive design (desktop, tablette, mobile)
- Optimisé pour connexions internet limitées

## 🔒 Sécurité (MVP)

- Validation stricte des formulaires
- Masquage des données sensibles
- Journalisation des actions
- Préparation pour KYC (maquette upload ID)

## 📝 Notes Importantes

- **Simulation** : Cette version est un MVP sans backend réel
- **Données** : Toutes les données sont stockées localement (LocalStorage)
- **OTP** : Le code OTP est simulé (affiché dans une alerte)
- **Mobile Money** : Les paiements sont simulés (pas d'intégration réelle)

## 🔮 Évolutions Futures

- Intégration réelle Mobile Money (Lumicash, EcoCash)
- Application mobile Flutter
- Backend sécurisé (Firebase, Supabase, Node.js)
- Multilingue (Kirundi, Anglais)
- Conformité réglementaire et KYC complet
- PWA (Progressive Web App)

## 👥 Public Cible

- Adultes de 18 à 45 ans
- Groupes communautaires, coopératives, associations
- Jeunes entrepreneurs et travailleurs informels
- Milieu urbain et semi-rural burundais

## 📄 Licence

Ce projet est développé dans le cadre d'un hackathon.

## 🤝 Contribution

Pour toute question ou suggestion, utilisez le formulaire de contact dans l'application.

---

**TWUNGURANE** - Ensemble, épargnons mieux ! 💚

