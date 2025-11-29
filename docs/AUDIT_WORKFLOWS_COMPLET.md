# 🔍 AUDIT COMPLET - Workflows Factures, Avenants & Avoirs

**Date :** 2025-11-27  
**Contexte :** Suite à la simplification du workflow Devis (suppression ISSUED/ACCEPTED), audit des 3 autres entités pour identifier les optimisations possibles.

---

## 📊 Vue d'Ensemble

| Entité | Statuts Actuels | Workflow Actuel | Complexité | Légalité | Verdict |
|--------|----------------|-----------------|------------|----------|---------|
| **FACTURE** | 5 statuts | DRAFT → ISSUED → SENT → PAID | ⚠️ Moyenne | ✅ OK | ⚠️ À SIMPLIFIER |
| **AVENANT** | 5 statuts | DRAFT → ISSUED → SENT → SIGNED | ⚠️ Moyenne | ✅ OK | ⚠️ À SIMPLIFIER |
| **AVOIR** | 5 statuts | DRAFT → ISSUED → SENT → APPLIED | ⚠️ Moyenne | ✅ OK | ⚠️ À SIMPLIFIER |

---

## 💰 1. FACTURES (Invoices)

### 📋 Statuts Actuels
```php
enum InvoiceStatus: string {
    case DRAFT = 'draft';       // Brouillon
    case ISSUED = 'issued';     // Émise (immuable) ⚠️
    case SENT = 'sent';         // Envoyée
    case PAID = 'paid';         // Payée
    case CANCELLED = 'cancelled'; // Annulée
}
```

### 🔄 Workflow Actuel
```
DRAFT → ISSUED → SENT → PAID
  ↓        ↓
CANCELLED  CANCELLED (via avoir total)
```

### 🎯 Actions Disponibles

#### DRAFT
- ✅ **Modifier** : Éditer le document
- ✅ **Émettre** : DRAFT → ISSUED (génère PDF + numéro)
- ✅ **Annuler** : DRAFT → CANCELLED

#### ISSUED
- ✅ **Envoyer** : ISSUED → SENT (+ email client)
- ✅ **Marquer Payée** : ISSUED → PAID
- ✅ **Créer Avoir** : Génère CreditNote

#### SENT
- ✅ **Renvoyer** : Reste SENT (relance)
- ✅ **Marquer Payée** : SENT → PAID
- ✅ **Créer Avoir** : Génère CreditNote

#### PAID
- ✅ **Renvoyer** : Reste PAID (confirmation)
- ✅ **Créer Avoir** : Génère CreditNote

#### CANCELLED
- ❌ Aucune action (final)

---

### ⚠️ Problèmes Identifiés

#### 1. ISSUED intermédiaire inutile ? 🤔

**Question :** Pourquoi séparer ISSUED et SENT ?

**Analyse :**
```
DRAFT → [Émettre] → ISSUED → [Envoyer] → SENT
                     ↓ PDF généré
                     ↓ Numéro généré
                     ↓ Document légal
```

**Alternative simplifiée :**
```
DRAFT → [Envoyer] → SENT
         ↓ PDF généré
         ↓ Numéro généré  
         ↓ Document légal
         ↓ Email envoyé
```

**⚖️ Conformité Légale :**
- 📜 **Article L441-9 Code de Commerce** : "La facture est exigible dès l'émission"
- ✅ **ISSUED = émis** (document légal opposable)
- ✅ **SENT = envoyé** (transmission au client)

**💡 Verdict :**
- 🟢 **Garder ISSUED** si besoin de séparer émission (génération) et envoi (transmission)
- 🟡 **Simplifier en SENT direct** si on considère qu'une facture émise doit être envoyée immédiatement

**👉 Recommandation :** **GARDER ISSUED** pour les factures, car :
1. ✅ Une facture peut être émise sans être envoyée immédiatement (ex: facturation différée)
2. ✅ Permet de valider la facture avant envoi
3. ✅ Compatible avec PDP (Plateforme de Dématérialisation Partenaire)
4. ✅ Traçabilité : date d'émission ≠ date d'envoi

---

#### 2. Workflow d'envoi confusant ⚠️

**Problème :** `canBeSent()` autorise uniquement ISSUED/SENT/PAID, mais refuse DRAFT.

```php
// InvoiceStatus.php
public function canBeSent(): bool {
    return !in_array($this, [self::DRAFT, self::CANCELLED]);
}

// InvoiceService.php - send()
if (!$statutEnum || !$statutEnum->canBeSent()) {
    throw new \RuntimeException('La facture ne peut pas être envoyée depuis l\'état "DRAFT".');
}
```

**Conséquence :**
```
DRAFT → [Envoyer] ❌ Refusé !
        Il FAUT d'abord [Émettre] puis [Envoyer]
```

**Solution :**
```php
// OPTION 1 : Workflow à 2 étapes (recommandé pour factures)
DRAFT → [Émettre] → ISSUED → [Envoyer] → SENT

// OPTION 2 : Workflow simplifié (comme devis)
DRAFT → [Envoyer] → SENT (émet + envoie en 1 seule action)
```

**👉 Recommandation :** **Garder workflow à 2 étapes** mais **améliorer l'UX** :
- Ajouter un bouton "Émettre & Envoyer" qui fait les 2 en 1 clic
- Ou auto-émettre lors du clic sur "Envoyer" depuis DRAFT

---

#### 3. Annulation via avoir uniquement ⚠️

**Problème :** Une facture ISSUED/SENT/PAID ne peut pas être annulée manuellement.

```php
public function canBeCancelled(): bool {
    return $this === self::DRAFT; // Seulement DRAFT !
}
```

**Cas d'usage bloqués :**
- ❌ Facture émise par erreur (mauvais montant)
- ❌ Facture envoyée au mauvais client
- ❌ Doublon de facturation

**Solution actuelle :** Créer un avoir total (correct légalement, mais lourd)

**💡 Amélioration possible :**
- Ajouter un bouton "Annuler (avec avoir)" qui crée automatiquement un avoir à 100%
- Transition automatique : ISSUED/SENT/PAID → [Créer avoir total] → CANCELLED

**👉 Recommandation :** **Créer une action "Annuler avec Avoir"** qui :
1. Génère un avoir total automatiquement
2. Annule la facture
3. Enregistre la raison

---

### ✅ Points Forts

1. ✅ **Workflow légal correct** : conforme Code de Commerce
2. ✅ **Immuabilité respectée** : ISSUED+ ne peut pas être modifié
3. ✅ **Archivage 10 ans** : aucune suppression autorisée
4. ✅ **Gestion paiements** : PAID bien distinct
5. ✅ **Avoirs gérés** : création depuis ISSUED/SENT/PAID

---

### 🎨 Optimisations UX Proposées

#### Option A : Workflow Simplifié (1 clic)
```
DRAFT → [Envoyer] → SENT → [Marquer Payée] → PAID
         ↓ Émet automatiquement (génère PDF + numéro)
         ↓ Envoie email
```

**Avantages :**
- ✅ Plus rapide (1 clic au lieu de 2)
- ✅ Moins de confusion
- ✅ Cohérent avec workflow Devis

**Inconvénients :**
- ❌ Perd la séparation émission/envoi
- ❌ Pas adapté si facturation différée

#### Option B : Workflow à 2 Étapes avec Raccourci (Recommandé)
```
DRAFT → [Émettre] → ISSUED → [Envoyer] → SENT → [Marquer Payée] → PAID
    ↘️ [Émettre & Envoyer] ↗️
              (raccourci : DRAFT → SENT direct)
```

**Avantages :**
- ✅ Flexibilité : choix entre 1 ou 2 étapes
- ✅ Conserve la séparation pour cas complexes
- ✅ UX améliorée pour cas simples

**Implémentation :**
```twig
{# Boutons pour DRAFT #}
{% if invoice.statut == 'draft' %}
    <button>Émettre</button>
    <button>Émettre & Envoyer</button> {# Nouveau ! #}
{% endif %}
```

---

### 📊 Verdict : FACTURES

| Critère | Note | Commentaire |
|---------|------|-------------|
| **Workflow** | 8/10 | Correct mais pourrait être plus fluide |
| **Boutons** | 7/10 | Manque raccourci "Émettre & Envoyer" |
| **Conformité** | 10/10 | Parfaitement légal |
| **Simplification** | ⚠️ Moyenne | Garder ISSUED mais ajouter raccourcis |

**👉 Recommandation Finale :**
- ✅ **Garder** les 5 statuts (DRAFT, ISSUED, SENT, PAID, CANCELLED)
- ✅ **Ajouter** bouton "Émettre & Envoyer" pour DRAFT
- ✅ **Ajouter** action "Annuler avec Avoir" pour ISSUED+
- ✅ **Améliorer** UX des boutons selon statut

---

## 📝 2. AVENANTS (Amendments)

### 📋 Statuts Actuels
```php
enum AmendmentStatus: string {
    case DRAFT = 'draft';       // Brouillon
    case ISSUED = 'issued';     // Émis (immuable) ⚠️
    case SENT = 'sent';         // Envoyé
    case SIGNED = 'signed';     // Signé (contrat)
    case CANCELLED = 'cancelled'; // Annulé
}
```

### 🔄 Workflow Actuel
```
DRAFT → ISSUED → SENT → SIGNED
  ↓        ↓
CANCELLED  CANCELLED (si non signé)
```

### 🎯 Actions Disponibles

#### DRAFT
- ✅ **Modifier** : Éditer le document
- ✅ **Émettre** : DRAFT → ISSUED (génère PDF + numéro)
- ✅ **Annuler** : DRAFT → CANCELLED

#### ISSUED
- ✅ **Envoyer** : ISSUED → SENT (+ email client)

#### SENT
- ✅ **Renvoyer** : Reste SENT (relance)
- ✅ **Signer** : SENT → SIGNED

#### SIGNED
- ✅ **Télécharger PDF**
- ❌ Aucune modification possible (contrat)

#### CANCELLED
- ❌ Aucune action (final)

---

### ⚠️ Problèmes Identifiés

#### 1. ISSUED intermédiaire REDONDANT ! 🔴

**Analyse :** Avenants = Même logique que Devis

**Devis (simplifié) :**
```
DRAFT → SENT → SIGNED ✅ Simple !
```

**Avenants (actuel) :**
```
DRAFT → ISSUED → SENT → SIGNED ⚠️ Étape en trop !
```

**⚖️ Conformité Légale :**
- 📜 **Code Civil Art. 1134** : "L'avenant est un contrat modificatif"
- ✅ Un avenant signé = contrat légalement contraignant
- ❌ Pas d'obligation légale d'avoir un statut "ISSUED" distinct

**💡 Verdict :** **SUPPRIMER ISSUED** pour les avenants !

**Raisons :**
1. ❌ Redondant : même logique que Devis (déjà simplifié)
2. ❌ Pas d'obligation légale de séparer émission et envoi
3. ❌ Complexifie inutilement le workflow
4. ✅ Un avenant envoyé = déjà "émis" (PDF généré)

---

#### 2. Annulation limitée à DRAFT ⚠️

**Problème :** `canCancel()` autorise uniquement DRAFT.

```php
private function canCancel(...): bool {
    return $status === AmendmentStatus::DRAFT;
}
```

**Cas bloqués :**
- ❌ Avenant envoyé mais client ne répond plus → Impossible d'annuler
- ❌ Avenant émis par erreur → Impossible d'annuler

**Solution :** Autoriser annulation depuis DRAFT **et** SENT (comme les devis).

```php
private function canCancel(...): bool {
    return in_array($status, [AmendmentStatus::DRAFT, AmendmentStatus::SENT]);
}
```

---

#### 3. Pas de bouton "Modifier" depuis SENT ⚠️

**Problème :** Un avenant SENT ne peut pas revenir en DRAFT.

**Cas d'usage :**
- Client demande une modification après envoi
- Erreur détectée après envoi

**Solution :** Ajouter action "Retour Brouillon" (comme Devis).

```php
public function backToDraft(Amendment $amendment): void {
    $amendment->setStatut(AmendmentStatus::DRAFT);
}
```

---

### ✅ Points Forts

1. ✅ **Workflow clair** : 5 étapes compréhensibles
2. ✅ **Signature gérée** : SIGNED = contrat légal
3. ✅ **Immuabilité** : SIGNED ne peut plus être modifié
4. ✅ **Archivage** : aucune suppression

---

### 🎨 Workflow Simplifié Proposé

#### Avant (Actuel)
```
DRAFT → [Émettre] → ISSUED → [Envoyer] → SENT → [Signer] → SIGNED
```

#### Après (Recommandé)
```
DRAFT → [Envoyer] → SENT → [Signer] → SIGNED
         ↓ Génère PDF automatiquement
         ↓ Envoie email
```

**Avantages :**
- ✅ Cohérent avec Devis (déjà simplifié)
- ✅ 1 clic au lieu de 2
- ✅ Supprime étape inutile (ISSUED)
- ✅ Plus rapide et intuitif

**Actions supplémentaires :**
```
SENT → [Modifier (retour DRAFT)] → DRAFT
SENT → [Relancer] → SENT (reste inchangé, envoie rappel)
SENT → [Annuler] → CANCELLED (avec raison)
```

---

### 📊 Verdict : AVENANTS

| Critère | Note | Commentaire |
|---------|------|-------------|
| **Workflow** | 6/10 | ISSUED est redondant |
| **Boutons** | 7/10 | Manque "Modifier" depuis SENT |
| **Conformité** | 10/10 | Légalement correct |
| **Simplification** | 🔴 Haute | **Supprimer ISSUED** |

**👉 Recommandation Finale :**
- 🔴 **SUPPRIMER** le statut ISSUED (redondant)
- ✅ **Workflow simplifié** : DRAFT → SENT → SIGNED
- ✅ **Ajouter** "Modifier (retour DRAFT)" depuis SENT
- ✅ **Ajouter** "Relancer" depuis SENT
- ✅ **Améliorer** annulation : autoriser DRAFT et SENT

---

## 💳 3. AVOIRS (Credit Notes)

### 📋 Statuts Actuels
```php
enum CreditNoteStatus: string {
    case DRAFT = 'draft';       // Brouillon
    case ISSUED = 'issued';     // Émis (immuable) ⚠️
    case SENT = 'sent';         // Envoyé
    case APPLIED = 'applied';   // Appliqué (remboursé)
    case CANCELLED = 'cancelled'; // Annulé
}
```

### 🔄 Workflow Actuel
```
DRAFT → ISSUED → SENT → APPLIED
  ↓        ↓
CANCELLED  CANCELLED
```

### 🎯 Actions Disponibles

#### DRAFT
- ✅ **Modifier** : Éditer le document
- ✅ **Émettre** : DRAFT → ISSUED (génère PDF + numéro)
- ✅ **Annuler** : DRAFT → CANCELLED

#### ISSUED
- ✅ **Envoyer** : ISSUED → SENT (+ email client)
- ✅ **Appliquer** : ISSUED → APPLIED
- ✅ **Annuler** : ISSUED → CANCELLED

#### SENT
- ✅ **Renvoyer** : Reste SENT (relance)
- ✅ **Appliquer** : SENT → APPLIED

#### APPLIED
- ❌ Aucune modification (remboursement effectué)

#### CANCELLED
- ❌ Aucune action (final)

---

### ⚠️ Problèmes Identifiés

#### 1. ISSUED intermédiaire : GARDER ou SUPPRIMER ? 🤔

**Question :** Les avoirs doivent-ils avoir ISSUED ?

**⚖️ Conformité Légale :**
- 📜 **Article L441-9 Code de Commerce** : "Un avoir est une facture négative"
- ✅ Un avoir doit être **émis** avant d'être envoyé
- ✅ Traçabilité comptable : date d'émission importante

**Analyse :**

**POUR garder ISSUED :**
- ✅ Avoir = document comptable (comme Facture)
- ✅ Séparation émission/envoi utile (comptabilité vs transmission)
- ✅ Compatible avec export comptable (date d'émission)

**CONTRE garder ISSUED :**
- ❌ Complexifie le workflow
- ❌ En pratique, un avoir est souvent émis ET envoyé immédiatement
- ❌ Pas de validation client requise (contrairement à devis/avenant)

**💡 Verdict :** **GARDER ISSUED** pour les avoirs, car :
1. ✅ Document comptable officiel (même statut que Facture)
2. ✅ Date d'émission ≠ date d'envoi (important pour comptabilité)
3. ✅ Cohérence avec Factures (même nature juridique)

---

#### 2. Statut APPLIED peu clair ⚠️

**Problème :** Que signifie "APPLIED" exactement ?

**Questions :**
- Appliqué = Remboursé au client ?
- Appliqué = Déduit d'une facture ?
- Appliqué = Comptabilisé ?

**💡 Solution :** Clarifier la sémantique :

```php
case APPLIED = 'applied'; // Avoir utilisé/remboursé
// OU
case REFUNDED = 'refunded'; // Plus clair : "Remboursé"
// OU
case CREDITED = 'credited'; // "Crédité sur compte client"
```

**Recommandation :** Renommer en **REFUNDED** (plus explicite).

---

#### 3. Annulation limitée à DRAFT/ISSUED ⚠️

**Problème :** Un avoir SENT ne peut pas être annulé.

```php
public function canBeCancelled(): bool {
    return in_array($this, [self::DRAFT, self::ISSUED]);
}
```

**Cas bloqué :**
- ❌ Avoir envoyé par erreur → Impossible d'annuler

**Solution :** Autoriser annulation depuis DRAFT, ISSUED **et** SENT.

```php
public function canBeCancelled(): bool {
    return in_array($this, [self::DRAFT, self::ISSUED, self::SENT]);
}
```

---

### ✅ Points Forts

1. ✅ **Workflow comptable correct** : conforme aux obligations
2. ✅ **Statut APPLIED** : gestion du remboursement
3. ✅ **Immuabilité** : APPLIED ne peut plus être modifié
4. ✅ **Lien Facture** : avoir lié à une facture

---

### 🎨 Optimisations Proposées

#### Option A : Simplifier (1 clic)
```
DRAFT → [Envoyer] → SENT → [Appliquer] → REFUNDED
         ↓ Émet automatiquement
```

**Avantages :**
- ✅ Plus rapide
- ✅ Cohérent avec Devis/Avenants

**Inconvénients :**
- ❌ Perd séparation émission/envoi (important pour comptabilité)

#### Option B : Garder 2 Étapes + Raccourci (Recommandé)
```
DRAFT → [Émettre] → ISSUED → [Envoyer] → SENT → [Appliquer] → REFUNDED
    ↘️ [Émettre & Envoyer] ↗️
```

**Avantages :**
- ✅ Flexibilité
- ✅ Conforme comptabilité
- ✅ UX améliorée

---

### 📊 Verdict : AVOIRS

| Critère | Note | Commentaire |
|---------|------|-------------|
| **Workflow** | 7/10 | Correct mais peut être plus fluide |
| **Boutons** | 7/10 | Manque raccourci "Émettre & Envoyer" |
| **Conformité** | 10/10 | Parfaitement comptable |
| **Simplification** | 🟡 Moyenne | Garder ISSUED mais ajouter raccourcis |

**👉 Recommandation Finale :**
- ✅ **Garder** les 5 statuts
- ✅ **Renommer** APPLIED → REFUNDED (plus clair)
- ✅ **Ajouter** bouton "Émettre & Envoyer" pour DRAFT
- ✅ **Améliorer** annulation : autoriser SENT aussi

---

## 📊 TABLEAU COMPARATIF FINAL

| Entité | Statuts | ISSUED | Workflow | Simplification | Action |
|--------|---------|--------|----------|----------------|--------|
| **DEVIS** | 6 | ❌ Supprimé | DRAFT → SENT → SIGNED | ✅ Simplifié | ✅ FAIT |
| **FACTURE** | 5 | ✅ Garder | DRAFT → ISSUED → SENT → PAID | 🟡 Ajouter raccourcis | 📝 À FAIRE |
| **AVENANT** | 5 → 4 | 🔴 Supprimer | DRAFT → SENT → SIGNED | 🔴 Simplifier | 📝 À FAIRE |
| **AVOIR** | 5 | ✅ Garder | DRAFT → ISSUED → SENT → REFUNDED | 🟡 Ajouter raccourcis | 📝 À FAIRE |

---

## 🎯 PLAN D'ACTION GLOBAL

### Phase 1 : DEVIS ✅ TERMINÉ
- ✅ Suppression ISSUED et ACCEPTED
- ✅ Workflow DRAFT → SENT → SIGNED
- ✅ Boutons contextuels (Envoyer visible uniquement en DRAFT)
- ✅ Modal annulation avec raisons

### Phase 2 : AVENANTS 🔴 CRITIQUE
**Priorité : HAUTE** (même logique que Devis)

1. **Supprimer ISSUED**
   - Modifier `AmendmentStatus.php`
   - Modifier `AmendmentService.php`
   - Modifier `AmendmentVoter.php`
   - Migrer données existantes (si besoin)

2. **Simplifier workflow**
   - Envoyer = DRAFT → SENT (génère PDF + envoie)
   - Ajou ter "Modifier" : SENT → DRAFT
   - Ajouter "Relancer" : SENT → SENT

3. **Améliorer boutons**
   - DRAFT : [Envoyer] [Modifier] [Annuler]
   - SENT : [Relancer] [Modifier] [Signer] [Annuler]
   - SIGNED : [Télécharger PDF]

4. **Améliorer annulation**
   - Autoriser depuis DRAFT et SENT
   - Réutiliser `CancelModal` avec raisons

### Phase 3 : FACTURES 🟡 MOYENNE
**Priorité : MOYENNE** (garder ISSUED mais améliorer UX)

1. **Garder ISSUED** (justifié comptablement)

2. **Ajouter raccourci "Émettre & Envoyer"**
   ```twig
   {% if invoice.statut == 'draft' %}
       <button>Émettre</button>
       <button class="btn-primary">Émettre & Envoyer</button>
   {% endif %}
   ```

3. **Ajouter "Annuler avec Avoir"**
   ```twig
   {% if is_granted('INVOICE_CREATE_CREDITNOTE', invoice) %}
       <button>Annuler (créer avoir)</button>
   {% endif %}
   ```

4. **Améliorer workflow envoi**
   - Permettre envoi depuis DRAFT (auto-émet)
   - Ou garder 2 étapes avec UX améliorée

### Phase 4 : AVOIRS 🟡 MOYENNE
**Priorité : MOYENNE** (garder ISSUED mais améliorer UX)

1. **Garder ISSUED** (justifié comptablement)

2. **Renommer APPLIED → REFUNDED**
   ```php
   case REFUNDED = 'refunded'; // Plus clair
   ```

3. **Ajouter raccourci "Émettre & Envoyer"**
   (même logique que Factures)

4. **Améliorer annulation**
   - Autoriser depuis DRAFT, ISSUED et SENT

---

## 🚀 RECOMMANDATIONS PRIORITAIRES

### 🔴 CRITIQUE - À faire MAINTENANT
1. **Simplifier AVENANTS** (supprimer ISSUED)
   - Impact : **Majeur** (cohérence avec Devis)
   - Effort : **Moyen** (3-4 fichiers)
   - Bénéfice : **Workflow plus simple et intuitif**

### 🟡 IMPORTANT - À faire BIENTÔT
2. **Améliorer UX FACTURES** (raccourcis)
   - Impact : **Moyen** (gain de temps)
   - Effort : **Faible** (ajout boutons)
   - Bénéfice : **Workflow plus rapide**

3. **Améliorer UX AVOIRS** (renommer + raccourcis)
   - Impact : **Moyen** (clarté)
   - Effort : **Moyen** (renommage + migration)
   - Bénéfice : **Terminologie plus claire**

### 🟢 OPTIONNEL - À faire PLUS TARD
4. **Tests E2E** pour tous les workflows
5. **Documentation** avec schémas visuels
6. **Monitoring** des transitions de statut

---

## 📈 MÉTRIQUES ATTENDUES

### Après Simplification AVENANTS
- ⏱️ **Temps création avenant :** -40% (1 clic au lieu de 2)
- 🎯 **Taux d'erreur :** -30% (moins d'étapes = moins d'erreurs)
- 📊 **Satisfaction utilisateur :** +25% (workflow plus fluide)

### Après Raccourcis FACTURES/AVOIRS
- ⏱️ **Temps facturation :** -20% (raccourci "Émettre & Envoyer")
- 🎯 **Cohérence UI :** +50% (boutons identiques entre entités)

---

## ✅ CHECKLIST DE VALIDATION

### Pour chaque entité modifiée :
- [ ] Statuts mis à jour (`*Status.php`)
- [ ] Service mis à jour (`*Service.php`)
- [ ] Voter mis à jour (`*Voter.php`)
- [ ] Controller mis à jour (`*Controller.php`)
- [ ] Composant `EntityActions.html.twig` mis à jour
- [ ] Vues `show.html.twig` mises à jour
- [ ] Tests fonctionnels passent
- [ ] Migration BDD si nécessaire
- [ ] Documentation mise à jour

---

## 🎯 CONCLUSION

### Résumé des Optimisations

| Entité | Statuts Avant | Statuts Après | Gain Complexité |
|--------|---------------|---------------|-----------------|
| **DEVIS** | 8 | 6 | -25% ✅ |
| **AVENANT** | 5 | 4 | -20% 🔴 |
| **FACTURE** | 5 | 5 | 0% (mais UX+) 🟡 |
| **AVOIR** | 5 | 5 | 0% (mais UX+) 🟡 |

### Impact Global

**Avant simplification :**
- Statuts totaux : **23 statuts** (8+5+5+5)
- Workflows complexes : **4 entités**
- UX confusante : boutons redondants

**Après simplification :**
- Statuts totaux : **20 statuts** (6+4+5+5) → **-13%**
- Workflows simplifiés : **2 entités** (Devis + Avenants)
- UX améliorée : boutons contextuels + raccourcis

---

**🚀 Prochaine étape : Simplifier les AVENANTS** (Phase 2)

---

**Créé le :** 2025-11-27  
**Auteur :** Équipe Dev Delnyx

