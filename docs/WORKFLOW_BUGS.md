# 🐛 Bugs identifiés dans les workflows

## Date de création : 2025-11-27

---

## 📊 Priorité : CRITIQUE

Ces bugs empêchent l'utilisation normale du système de facturation.

---

## 🧾 1. DEVIS (Quote)

### ❌ Bug 1.1 : Envoi ne change pas le statut
**Statut actuel :** `DRAFT`  
**Action :** Clic sur "Envoyer par email"  
**Statut attendu :** `SENT`  
**Statut obtenu :** `DRAFT` (ne change pas)

**Impact :** 
- Le client ne peut pas signer un devis qui n'est pas marqué comme envoyé
- Le suivi des devis envoyés est impossible
- La progression du workflow est bloquée

**Localisation probable :**
- `src/Controller/Admin/QuoteController.php::sendEmail()`
- `src/Service/QuoteService.php::send()`
- EventSubscribers qui gèrent les transitions

---

### ❌ Bug 1.2 : Impossible de signer depuis SENT
**Statut actuel :** `SENT` (si on arrive à l'avoir)  
**Action :** Signature via magic link  
**Erreur :** Impossible de signer / Transition refusée

**Impact :**
- Workflow complètement bloqué
- Le client ne peut pas valider le devis
- Impossible de générer une facture depuis un devis signé

**Localisation probable :**
- `src/Security/Voter/QuoteVoter.php::canSign()`
- `src/Entity/QuoteStatus.php::canBeSigned()`
- `src/EventSubscriber/LockOnSignatureSubscriber.php`

---

### 📋 Workflow attendu pour DEVIS

```
┌─────────┐   Création    ┌─────────┐
│  DRAFT  │ ────────────> │  DRAFT  │
└─────────┘               └─────────┘
                               │
                               │ Envoi email
                               ▼
                          ┌─────────┐
                          │  SENT   │
                          └─────────┘
                               │
                               │ Signature client
                               ▼
                          ┌─────────┐
                          │ SIGNED  │ ───> Génération Facture
                          └─────────┘
                               │
                               │ Refus/Expiration
                               ▼
                          ┌──────────┐
                          │ REFUSED/ │
                          │ EXPIRED  │
                          └──────────┘
```

---

## 💰 2. FACTURES (Invoice)

### ⚠️ À vérifier : Transitions de statut
**Workflow attendu :**
```
DRAFT → ISSUED → SENT → PAID
         │
         └──> CANCELLED (via avoir total)
```

**Points à tester :**
- [ ] Émission : `DRAFT → ISSUED`
- [ ] Envoi : `ISSUED → SENT`
- [ ] Paiement : `SENT → PAID`
- [ ] Annulation via avoir : `ISSUED/PAID → CANCELLED`

---

## 📝 3. AVENANTS (Amendment)

### ❌ Bug 3.1 : Lignes source manquantes dans dropdown
**Contexte :** Création/édition d'un avenant  
**Problème :** Les lignes du devis parent n'apparaissent pas dans la liste déroulante pour sélectionner la ligne à modifier

**Impact :**
- Impossible de créer un avenant fonctionnel
- Workflow d'ajustement de devis bloqué

**Localisation probable :**
- `src/Form/AmendmentLineType.php::buildForm()`
- `src/Form/EventSubscriber/AmendmentLineSourceLineSubscriber.php`
- JavaScript : `assets/controllers/amendment_form_controller.js`

---

### 📋 Workflow attendu pour AVENANTS

```
Devis SIGNED
     │
     │ Création avenant
     ▼
┌─────────┐   Modification    ┌─────────┐
│  DRAFT  │ ────────────────> │  DRAFT  │
└─────────┘                   └─────────┘
                                   │
                                   │ Émission
                                   ▼
                              ┌─────────┐
                              │  SENT   │
                              └─────────┘
                                   │
                                   │ Signature client
                                   ▼
                              ┌─────────┐
                              │ SIGNED  │ ──> Recalcul devis parent
                              └─────────┘
```

---

## 💳 4. AVOIRS (CreditNote)

### ❌ Bug 4.1 : Lignes facture manquantes dans dropdown
**Contexte :** Création d'un avoir  
**Problème :** Les lignes de la facture n'apparaissent pas dans la liste déroulante

**Impact :**
- Impossible de créer un avoir ligne par ligne
- Seuls les avoirs totaux sont possibles (workaround)

**Localisation probable :**
- `src/Form/CreditNoteLineType.php::buildForm()`
- `src/Form/EventSubscriber/CreditNoteLineSourceLineSubscriber.php`
- JavaScript : `assets/controllers/credit_note_form_controller.js`

---

### 📋 Workflow attendu pour AVOIRS

```
Facture ISSUED/PAID
     │
     │ Création avoir
     ▼
┌─────────┐   Ajout lignes   ┌─────────┐
│  DRAFT  │ ───────────────> │  DRAFT  │
└─────────┘                  └─────────┘
                                  │
                                  │ Émission
                                  ▼
                             ┌─────────┐
                             │ ISSUED  │
                             └─────────┘
                                  │
                                  │ Envoi
                                  ▼
                             ┌─────────┐
                             │  SENT   │
                             └─────────┘
                                  │
                                  │ Si avoir total
                                  ▼
                          Facture → CANCELLED
```

---

## 🔍 Zones à auditer en priorité

### 1. Controllers - Méthodes d'action
- `QuoteController::sendEmail()` - Ne change pas le statut
- `InvoiceController::issue()` - À vérifier
- `AmendmentController::issue()` - À vérifier
- `CreditNoteController::issue()` - À vérifier

### 2. Services - Logique métier
- `QuoteService::send()` - Transition DRAFT → SENT
- `InvoiceService::issue()` - Transition DRAFT → ISSUED
- `AmendmentService::sign()` - Recalcul devis parent
- `CreditNoteService::issue()` - Annulation facture si total

### 3. Voters - Autorisations
- `QuoteVoter::canSign()` - Vérifier conditions
- `QuoteVoter::canSend()` - Vérifier conditions
- `InvoiceVoter::canIssue()` - Vérifier conditions
- `CreditNoteVoter::canIssue()` - Vérifier conditions

### 4. EventSubscribers - Effets de bord
- `LockOnSignatureSubscriber` - Peut bloquer transitions
- `LockOnIssueSubscriber` - Peut bloquer transitions
- `RecalculateParentTotalsSubscriber` - Avenants
- `CancelInvoiceOnTotalCreditNoteSubscriber` - Avoirs

### 5. Formulaires - Dropdowns dynamiques
- `AmendmentLineType` + `AmendmentLineSourceLineSubscriber`
- `CreditNoteLineType` + `CreditNoteLineSourceLineSubscriber`
- JavaScript associé (Stimulus controllers)

---

## 📝 Notes pour la correction

### Stratégie proposée

1. **Phase 1 : Diagnostic** (1-2h)
   - Activer les logs Symfony (`APP_ENV=dev` temporairement)
   - Tracer chaque transition de statut
   - Identifier les Voters/Subscribers qui bloquent
   - Documenter les transitions actuelles vs attendues

2. **Phase 2 : Devis** (2-3h)
   - Corriger `QuoteController::sendEmail()`
   - Corriger `QuoteVoter::canSign()`
   - Tester workflow complet DRAFT → SENT → SIGNED
   - Tests automatisés

3. **Phase 3 : Factures** (1-2h)
   - Vérifier toutes les transitions
   - Corriger si nécessaire
   - Tests automatisés

4. **Phase 4 : Avenants** (3-4h)
   - Corriger dropdown lignes source
   - Vérifier recalcul devis parent
   - Tests automatisés

5. **Phase 5 : Avoirs** (3-4h)
   - Corriger dropdown lignes facture
   - Vérifier annulation facture
   - Tests automatisés

6. **Phase 6 : Tests E2E** (2-3h)
   - Workflow complet pour chaque type de document
   - Cas limites et erreurs
   - Documentation

---

## ✅ Checklist de validation

Une fois les corrections effectuées, valider :

### Devis
- [ ] Envoi change le statut vers SENT
- [ ] Email envoyé avec PDF
- [ ] Magic link fonctionnel
- [ ] Signature possible depuis SENT
- [ ] Signature change le statut vers SIGNED
- [ ] Refus change le statut vers REFUSED
- [ ] Génération facture possible depuis SIGNED

### Factures
- [ ] Émission change le statut vers ISSUED
- [ ] Envoi change le statut vers SENT
- [ ] Paiement change le statut vers PAID
- [ ] Avoir total annule la facture (CANCELLED)
- [ ] PDF généré et envoyé correctement

### Avenants
- [ ] Liste déroulante affiche toutes les lignes du devis
- [ ] Émission fonctionne
- [ ] Signature fonctionne
- [ ] Recalcul du devis parent correct
- [ ] PDF généré correctement

### Avoirs
- [ ] Liste déroulante affiche toutes les lignes de la facture
- [ ] Émission fonctionne
- [ ] Envoi fonctionne
- [ ] Avoir total annule la facture
- [ ] PDF généré correctement

---

## 🎯 Objectif final

**Avoir un système de facturation complètement fonctionnel avec :**
- ✅ Workflows clairs et prévisibles
- ✅ Transitions de statut fiables
- ✅ Dropdowns dynamiques fonctionnels
- ✅ Tests automatisés pour éviter les régressions
- ✅ Documentation à jour des workflows

---

**Dernière mise à jour :** 2025-11-27  
**Prochaine action :** Audit complet avec logs activés

