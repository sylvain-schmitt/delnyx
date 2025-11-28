# 📘 Guide Utilisateur - Gestion des Devis

## Date : 2025-11-27
## Version : 2.0 (Workflow Simplifié)

---

## 🎯 Vue d'Ensemble

Le nouveau système de gestion des devis offre un workflow **simplifié et intuitif** pour gérer vos devis de A à Z.

### Workflow Principal

```
BROUILLON → ENVOYÉ → SIGNÉ → FACTURE
```

---

## 📋 Statuts des Devis

| Statut | Description | Modifiable | Actions Disponibles |
|--------|-------------|------------|---------------------|
| **BROUILLON** (DRAFT) | Devis en cours de rédaction | ✅ Oui | Modifier, Envoyer, Annuler |
| **ENVOYÉ** (SENT) | Devis envoyé au client, en attente de signature | ❌ Non* | Renvoyer, Relancer, Modifier*, Annuler |
| **SIGNÉ** (SIGNED) | Devis signé = Contrat | ❌ Non | Générer Facture, Créer Avenant |
| **ANNULÉ** (CANCELLED) | Devis annulé pour diverses raisons | ❌ Non | Dupliquer* |
| **REFUSÉ** (REFUSED) | Client a refusé le devis | ❌ Non | Dupliquer* |
| **EXPIRÉ** (EXPIRED) | Date de validité dépassée | ❌ Non | Prolonger*, Dupliquer* |

\* *Fonctionnalités à venir*

---

## 🚀 Guide Étape par Étape

### 1. Créer un Devis (BROUILLON)

1. Accéder à **Devis > Nouveau**
2. Sélectionner un client
3. Ajouter des lignes de prestation
4. Vérifier les montants
5. **Enregistrer en brouillon**

**💡 Conseil :** Vous pouvez revenir modifier le devis autant de fois que nécessaire tant qu'il n'est pas envoyé.

---

### 2. Envoyer le Devis au Client

#### Méthode Rapide (Recommandée)

1. Ouvrir le devis en statut **BROUILLON**
2. Cliquer sur **"Envoyer par email"**
3. Personnaliser le message (optionnel)
4. Ajouter des pièces jointes (optionnel)
5. **Envoyer**

**✨ Le système va automatiquement :**
- ✅ Générer le PDF du devis
- ✅ Passer le statut à **ENVOYÉ**
- ✅ Envoyer l'email au client avec PDF joint
- ✅ Créer un magic link pour la signature

---

### 3. Gérer un Devis Envoyé

Une fois le devis envoyé, vous avez plusieurs options :

#### 📧 Renvoyer le Devis
Si le client n'a pas reçu l'email :
- Cliquer sur **"Renvoyer"**
- Le même email sera renvoyé (statut reste ENVOYÉ)

#### 🔔 Relancer le Client
Si le client tarde à répondre :
- Cliquer sur **"Relancer le client"**
- Personnaliser le message de relance
- L'email de rappel sera envoyé

#### ✏️ Modifier le Devis
Si le client demande des modifications :
1. Cliquer sur **"Modifier"**
2. Le devis repasse en statut **BROUILLON**
3. Apporter les modifications nécessaires
4. Cliquer sur **"Envoyer"** à nouveau

**⚠️ Note :** Le client recevra un nouvel email avec le devis modifié.

#### ❌ Annuler le Devis
Si le projet tombe à l'eau :
1. Cliquer sur **"Annuler"**
2. Sélectionner une raison dans le menu déroulant :
   - Refusé par le client
   - Client injoignable
   - Budget insuffisant
   - Délais trop longs
   - Concurrent choisi
   - Projet abandonné
   - Devis erroné
   - Doublon
   - Autre raison (champ personnalisé)
3. Confirmer l'annulation

**💡 Astuce :** La raison d'annulation sera enregistrée dans les notes du devis pour votre historique.

---

### 4. Signature par le Client

#### Option A : Signature via Magic Link (Recommandé)

Le client reçoit un email avec un lien sécurisé :
1. Le client clique sur **"Signer le devis"**
2. Il est redirigé vers une page de signature
3. Il signe électroniquement
4. Le statut passe automatiquement à **SIGNÉ**

#### Option B : Signature Manuelle (Admin)

Si le client a signé sur papier ou à l'oral :
1. Ouvrir le devis en statut **ENVOYÉ**
2. Cliquer sur **"Signer"**
3. Confirmer la signature
4. Le statut passe à **SIGNÉ**

**🎉 Le devis signé devient un CONTRAT légalement opposable !**

---

### 5. Générer la Facture

Une fois le devis signé :
1. Ouvrir le devis en statut **SIGNÉ**
2. Cliquer sur **"Générer une facture"**
3. La facture est créée automatiquement avec :
   - Toutes les lignes du devis
   - Les montants identiques
   - Un numéro de facture auto-généré
4. La facture est créée en statut **BROUILLON**
5. Vous pouvez ensuite l'émettre et l'envoyer au client

---

## 💡 Cas d'Usage Fréquents

### Cas 1 : Devis Accepté Oralement

**Situation :** Le client dit "OK" par téléphone mais n'a pas signé le magic link.

**Solution :**
1. Aller dans le devis (statut ENVOYÉ)
2. Cliquer sur **"Signer"** (signature manuelle admin)
3. Générer la facture

---

### Cas 2 : Client Demande une Modification

**Situation :** Le client veut changer une prestation après avoir reçu le devis.

**Solution :**
1. Cliquer sur **"Modifier"** (retour en BROUILLON)
2. Modifier les lignes du devis
3. Cliquer sur **"Envoyer"** à nouveau
4. Le client reçoit le nouveau devis

**⚠️ Alternative :** Si le devis est déjà SIGNÉ, créer un **AVENANT** au lieu de modifier.

---

### Cas 3 : Client ne Répond Pas

**Situation :** Aucune réponse du client après 1 semaine.

**Solution :**
1. Cliquer sur **"Relancer le client"**
2. Personnaliser le message : *"Bonjour, je me permets de vous relancer concernant le devis DEV-2025-001. Êtes-vous toujours intéressé ?"*
3. Envoyer

---

### Cas 4 : Projet Annulé par le Client

**Situation :** Le client vous informe qu'il ne donne pas suite.

**Solution :**
1. Cliquer sur **"Annuler"**
2. Sélectionner **"Refusé par le client"**
3. Confirmer

Le devis passe en statut ANNULÉ et reste dans vos archives.

---

### Cas 5 : Erreur dans le Devis Envoyé

**Situation :** Vous vous rendez compte d'une erreur dans un devis déjà envoyé.

**Solution :**
1. Cliquer sur **"Modifier"** (retour en BROUILLON)
2. Corriger l'erreur
3. Cliquer sur **"Envoyer"** à nouveau

**💡 Alternative (si mineur) :** Cliquer sur "Annuler" avec raison "Devis erroné", puis créer un nouveau devis corrigé.

---

## 🎨 Interface Utilisateur

### Boutons Disponibles par Statut

#### BROUILLON
```
[Envoyer par email] [Modifier] [Annuler]
```

#### ENVOYÉ
```
[Renvoyer] [Relancer le client] [Modifier] [Annuler]
```

#### SIGNÉ
```
[Générer Facture] [Créer Avenant] [Télécharger PDF]
```

#### ANNULÉ / REFUSÉ / EXPIRÉ
```
[Dupliquer] (à venir)
```

---

## 📊 Magic Links - Liens Sécurisés pour le Client

Chaque devis envoyé génère automatiquement des **magic links** (liens magiques) :

### 🔗 Lien de Visualisation
- Permet au client de voir le devis en ligne
- Pas de connexion requise
- Affichage professionnel et responsive

### ✍️ Lien de Signature
- Permet au client de signer électroniquement
- Signature sécurisée et horodatée
- Change automatiquement le statut à SIGNÉ

### ❌ Lien de Refus
- Permet au client de refuser officiellement
- Change le statut à REFUSÉ
- Enregistre la date de refus

**🔒 Sécurité :** Les magic links sont uniques, cryptés et ne nécessitent pas de compte client.

---

## ⚙️ Paramètres Importants

### Date de Validité
- Par défaut : **30 jours** (durée légale en France)
- Modifiable lors de la création du devis
- Après expiration : le devis passe en statut EXPIRÉ

### Numérotation Automatique
- Format : **DEV-YYYY-XXX**
- Exemple : DEV-2025-001, DEV-2025-002, etc.
- Incrémente automatiquement chaque année

### Génération PDF
- Généré automatiquement lors de l'envoi
- Sauvegardé avec empreinte SHA256 (conformité légale)
- Joint automatiquement aux emails

---

## ⚠️ Points d'Attention

### ⛔ Impossible de Modifier un Devis SIGNÉ
Une fois signé, le devis est un **contrat légalement opposable** et devient **immuable**.

**Solution :** Créer un **AVENANT** pour apporter des modifications au contrat existant.

### 📧 Email Client Obligatoire
Pour envoyer un devis, le client doit avoir une adresse email configurée.

**Vérification :** Un message "Email non configuré" apparaît si l'email manque.

### 🗑️ Impossible de Supprimer un Devis
Pour des raisons légales (archivage 10 ans), les devis ne peuvent pas être supprimés.

**Solution :** Utiliser le statut **ANNULÉ** pour marquer un devis comme non valide.

---

## 🆘 Problèmes Courants

### Le Client n'a pas Reçu l'Email

**Vérifications :**
1. L'adresse email du client est-elle correcte ?
2. Vérifier les spams du client
3. Utiliser le bouton **"Renvoyer"**

---

### Le Magic Link ne Fonctionne Pas

**Solutions :**
1. Régénérer le lien en renvoyant le devis
2. Utiliser la signature manuelle admin en dernier recours

---

### Je ne Vois pas le Bouton "Envoyer"

**Causes possibles :**
- Le client n'a pas d'email → Ajouter l'email dans la fiche client
- Le devis est déjà SIGNÉ → Créer un avenant au lieu de modifier
- Le devis est ANNULÉ → Dupliquer pour créer un nouveau devis

---

## 📞 Support

Pour toute question ou problème :
- 📧 Email : support@delnyx.com
- 📱 Téléphone : XX XX XX XX XX

---

## 🔄 Mises à Jour

### Version 2.0 (2025-11-27)
- ✅ Workflow simplifié BROUILLON → ENVOYÉ → SIGNÉ
- ✅ Bouton "Relancer le client"
- ✅ Bouton "Modifier" pour retour en BROUILLON
- ✅ Modal d'annulation avec raisons prédéfinies
- ✅ Génération PDF automatique lors de l'envoi

### Version 1.0 (2025-08)
- Création du système de devis
- Gestion des statuts
- Magic links pour signature

---

**📚 Ce guide est mis à jour régulièrement. Consultez la version en ligne pour les dernières nouveautés.**

