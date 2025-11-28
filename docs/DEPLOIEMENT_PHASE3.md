# 🚀 Guide de Déploiement - Phase 3 (UI)

## Date : 2025-11-27
## Version : 2.0 - Workflow Devis Simplifié

---

## ✅ Modifications Prêtes à Déployer

### 📝 Fichiers Modifiés

#### Backend (PHP/Symfony)
1. ✅ `src/Entity/QuoteStatus.php` - Autorise envoi depuis DRAFT
2. ✅ `src/Service/QuoteService.php` - Workflow simplifié + nouvelles méthodes
3. ✅ `src/Controller/Admin/QuoteController.php` - Nouvelles routes (backToDraft, remind)

#### Frontend (Twig/Templates)
4. ✅ `templates/components/EntityActions.html.twig` - Nouveaux boutons
5. ✅ `templates/components/CancelModal.html.twig` - Modal d'annulation (NOUVEAU)
6. ✅ `templates/admin/quote/show.html.twig` - Intégration CancelModal

#### JavaScript (Stimulus)
7. ✅ `assets/controllers/modal_controller.js` - Controller pour modals (NOUVEAU)

#### Documentation
8. ✅ `docs/WORKFLOW_BUGS.md` - Audit des bugs
9. ✅ `docs/WORKFLOW_ACTION_PLAN.md` - Plan d'action complet
10. ✅ `docs/WORKFLOW_CURRENT_STATE.md` - État actuel détaillé
11. ✅ `docs/WORKFLOW_CHANGES.md` - Toutes les modifications
12. ✅ `docs/GUIDE_UTILISATEUR_DEVIS.md` - Guide utilisateur final
13. ✅ `docs/DEPLOIEMENT_PHASE3.md` - Ce document

---

## 🔍 Tests Recommandés AVANT Déploiement

### Test 1 : Envoi depuis DRAFT
```bash
1. Créer un devis DRAFT
2. Cliquer "Envoyer"
3. ✅ Vérifier statut = SENT
4. ✅ Vérifier email reçu avec PDF
```

### Test 2 : Retour en DRAFT
```bash
1. Prendre un devis SENT
2. Cliquer "Modifier"
3. ✅ Vérifier statut = DRAFT
4. Modifier une ligne
5. Cliquer "Envoyer"
6. ✅ Vérifier statut = SENT
```

### Test 3 : Relancer Client
```bash
1. Prendre un devis SENT
2. Cliquer "Relancer le client"
3. ✅ Vérifier email de relance envoyé
```

### Test 4 : Annulation avec Raison
```bash
1. Prendre un devis DRAFT ou SENT
2. Cliquer "Annuler"
3. ✅ Vérifier modal s'ouvre
4. Sélectionner "Refusé par le client"
5. Confirmer
6. ✅ Vérifier statut = CANCELLED
7. ✅ Vérifier raison dans notes
```

### Test 5 : Signature
```bash
1. Prendre un devis SENT
2. Ouvrir magic link signature
3. Signer
4. ✅ Vérifier statut = SIGNED
5. ✅ Vérifier bouton "Générer Facture" visible
```

---

## 📦 Commandes de Déploiement

### Option A : Déploiement Local pour Tests

```bash
# 1. Pull des modifications
cd /home/ogan/projets/symfony/delnyx
git pull origin feature/workflow-simplification

# 2. Installer les dépendances JavaScript
npm install

# 3. Compiler les assets
npm run build

# 4. Clear cache Symfony
php bin/console cache:clear

# 5. Lancer le serveur de dev
symfony server:start

# 6. Tester dans le navigateur
# http://localhost:8000/admin/quote
```

### Option B : Déploiement Production

```bash
# 1. Committer les modifications
git add .
git commit -m "feat: Workflow devis simplifié avec nouvelles fonctionnalités

- Envoi DRAFT → SENT direct (skip ISSUED)
- Bouton 'Modifier' pour retour DRAFT depuis SENT
- Bouton 'Relancer client' pour rappels
- Modal annulation avec raisons prédéfinies
- Génération PDF automatique lors envoi
- Documentation utilisateur complète"

# 2. Push vers GitHub
git push origin feature/workflow-simplification

# 3. Merger dans main (après validation tests)
git checkout main
git merge feature/workflow-simplification
git push origin main

# 4. Le déploiement automatique se déclenchera sur le serveur
# Vérifier les logs de déploiement
```

---

## 🔧 Configuration Requise

### Aucune Migration BDD Requise ✅
Toutes les modifications sont dans le code applicatif, aucune modification de schéma BDD.

### Variables d'Environnement
Aucune nouvelle variable requise.

### Permissions Fichiers
Vérifier que `var/` est writable pour la génération des PDFs :
```bash
chmod -R 777 var/
```

---

## 🎯 Fonctionnalités Ajoutées

### 1. Workflow Simplifié
- ✅ DRAFT peut être envoyé directement (skip ISSUED)
- ✅ Génération PDF automatique lors de l'envoi
- ✅ Workflow plus intuitif : DRAFT → SENT → SIGNED

### 2. Bouton "Modifier" (SENT → DRAFT)
- ✅ Permet de modifier un devis envoyé
- ✅ Repasse automatiquement en DRAFT
- ✅ Confirmation requise

### 3. Bouton "Relancer le Client"
- ✅ Envoie un email de rappel
- ✅ Personnalisable
- ✅ Audit de l'action

### 4. Modal Annulation Amélioré
- ✅ Dropdown avec 8 raisons prédéfinies
- ✅ Option "Autre" avec champ personnalisé
- ✅ Raison sauvegardée dans les notes
- ✅ Design moderne et responsive

### 5. Documentation Complète
- ✅ Guide utilisateur détaillé
- ✅ Cas d'usage pratiques
- ✅ Troubleshooting

---

## ⚠️ Points de Vigilance

### 1. Devis Existants
Les devis existants en base ne seront **pas impactés** :
- ✅ Les devis DRAFT restent DRAFT
- ✅ Les devis SENT restent SENT
- ✅ Les devis SIGNED restent SIGNED
- ✅ Backward compatible

### 2. Magic Links
Les magic links existants continuent de fonctionner normalement.

### 3. PDF Existants
Les PDFs déjà générés ne sont **pas régénérés** :
- ✅ Les PDFs existants restent valides
- ✅ Seuls les nouveaux devis auront le PDF auto-généré à l'envoi

---

## 🐛 Bugs Corrigés

| Bug | Description | Statut |
|-----|-------------|--------|
| #1 | Envoi ne change pas le statut | ✅ CORRIGÉ |
| #2 | Impossible de signer (car reste DRAFT) | ✅ CORRIGÉ |
| #3 | Workflow incohérent | ✅ CORRIGÉ |

---

## 📊 Métriques de Succès

Après déploiement, surveiller :

### KPIs Opérationnels
- ⏱️ **Temps moyen d'envoi d'un devis** : Devrait diminuer (workflow simplifié)
- 📧 **Taux d'ouverture des relances** : Nouvelle fonctionnalité
- ✍️ **Taux de signature** : Devrait augmenter (meilleure UX)

### KPIs Techniques
- 🐛 **Erreurs PHP** : Surveiller dans logs Symfony
- 🚀 **Temps de génération PDF** : Surveiller performances
- 💾 **Espace disque PDFs** : Vérifier croissance normale

---

## 🆘 Rollback Procedure (En cas de problème)

### Si Bug Critique en Production

```bash
# 1. Revenir à la version précédente
git checkout main
git revert HEAD
git push origin main

# 2. Le déploiement automatique restaurera l'ancienne version

# 3. Clear cache si nécessaire
ssh user@server
cd /path/to/app
php bin/console cache:clear --env=prod
```

### Sauvegardes
- ✅ Base de données sauvegardée automatiquement (quotidien)
- ✅ Code versionné sur GitHub
- ✅ PDFs existants non impactés

---

## 📞 Support Post-Déploiement

### Contacts
- **Dev Lead :** [Votre nom]
- **Email :** support@delnyx.com
- **Urgence :** [Téléphone]

### Monitoring
- 📊 **Logs Symfony :** `var/log/prod.log`
- 🐛 **Sentry :** Alertes automatiques sur erreurs
- 📧 **Emails :** Vérifier file d'attente Mailer

---

## ✅ Checklist de Déploiement

### Pré-Déploiement
- [ ] Tests locaux effectués
- [ ] Documentation relue
- [ ] Commit créé avec message clair
- [ ] Branch pushed sur GitHub

### Déploiement
- [ ] Merge dans main
- [ ] Déploiement automatique réussi
- [ ] Cache cleared
- [ ] Assets compilés

### Post-Déploiement
- [ ] Tests en production effectués
- [ ] Aucune erreur dans les logs
- [ ] Email de test envoyé et reçu
- [ ] PDF généré correctement
- [ ] Modal d'annulation fonctionne
- [ ] Boutons visibles et fonctionnels

### Communication
- [ ] Équipe informée des nouveautés
- [ ] Guide utilisateur partagé
- [ ] Formation rapide si nécessaire

---

## 🎉 Prochaines Phases

### Phase 4 : Factures, Avenants, Avoirs
- Corriger workflow factures (même logique)
- Corriger dropdowns lignes (avenants/avoirs)
- Tests E2E

### Phase 5 : Fonctionnalités Avancées
- Dupliquer devis
- Prolonger date de validité
- Régénération PDF si obsolète

### Phase 6 : Tests Automatisés
- Tests PHPUnit pour workflows
- Tests Behat pour scénarios utilisateur
- CI/CD avec tests automatiques

---

## 📚 Documentation Associée

1. **WORKFLOW_BUGS.md** - Liste des bugs identifiés
2. **WORKFLOW_ACTION_PLAN.md** - Plan d'action sur 3 semaines
3. **WORKFLOW_CURRENT_STATE.md** - Audit technique détaillé
4. **WORKFLOW_CHANGES.md** - Détail de toutes les modifications
5. **GUIDE_UTILISATEUR_DEVIS.md** - Guide utilisateur final

---

**🚀 Bon déploiement !**

**Date de création :** 2025-11-27  
**Auteur :** Équipe Dev Delnyx  
**Version :** 1.0

