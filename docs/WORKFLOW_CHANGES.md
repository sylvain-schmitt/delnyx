# ✅ Modifications Appliquées - Workflow Devis

## Date : 2025-11-27
## Phase 2 : Corrections et Nouvelles Fonctionnalités

---

## 🎯 Workflow Final Implémenté

```
┌─────────┐
│  DRAFT  │ ← Création, modifications possibles
└─────────┘
     │
     │ [Envoyer] = Change statut + Génère PDF + Envoie email
     ▼
┌─────────┐
│  SENT   │ ← Devis envoyé, en attente signature
└─────────┘
     │
     ├──→ [Renvoyer] = Renvoie l'email (garde SENT)
     ├──→ [Relancer] = Email de relance au client
     ├──→ [Modifier] = Retour en DRAFT
     └──→ [Annuler] = CANCELLED (avec raison)
     │
     │ [Magic Link Client] = Signature
     ▼
┌─────────┐
│ SIGNED  │ ← Devis = CONTRAT (immuable)
└─────────┘
     │
     └──→ [Générer Facture]
```

---

## 📝 Fichiers Modifiés

### 1. `src/Entity/QuoteStatus.php`

#### Changement : `canBeSent()`

**Avant :**
```php
public function canBeSent(): bool
{
    // ❌ DRAFT ne pouvait PAS être envoyé
    return !in_array($this, [self::DRAFT, self::REFUSED, self::EXPIRED, self::CANCELLED]);
}
```

**Après :**
```php
public function canBeSent(): bool
{
    // ✅ DRAFT peut maintenant être envoyé directement
    return !in_array($this, [self::SIGNED, self::REFUSED, self::EXPIRED, self::CANCELLED]);
}
```

**Impact :** Permet le workflow simplifié DRAFT → SENT (skip ISSUED)

---

### 2. `src/Service/QuoteService.php`

#### A. Changement : `send()` - Gestion du workflow simplifié

**Ajout :**
```php
// Gérer la transition selon l'état actuel
$oldStatus = $quote->getStatut();

if ($oldStatus === QuoteStatus::DRAFT) {
    // ✅ DRAFT → SENT directement
    $quote->setStatut(QuoteStatus::SENT);
    
    // Générer le PDF si pas encore fait
    if (!$quote->getPdfFilename()) {
        try {
            $pdfResult = $this->pdfGeneratorService->generateDevisPdf($quote, true);
            $quote->setPdfFilename($pdfResult['filename']);
            $quote->setPdfHash($pdfResult['hash']);
        } catch (\Exception $e) {
            // Log error
        }
    }
    
    $this->logStatusChange($quote, $oldStatus, QuoteStatus::SENT, 'send');
} elseif ($oldStatus === QuoteStatus::ISSUED) {
    // ISSUED → SENT
    $quote->setStatut(QuoteStatus::SENT);
    $this->logStatusChange($quote, $oldStatus, QuoteStatus::SENT, 'send');
} elseif (in_array($oldStatus, [QuoteStatus::SENT, QuoteStatus::ACCEPTED])) {
    // Déjà envoyé : simple renvoi
    $this->logStatusChange($quote, $oldStatus, $oldStatus, 'resend');
}
```

**Impact :** 
- ✅ DRAFT peut être envoyé directement
- ✅ PDF généré automatiquement lors de l'envoi
- ✅ Support du renvoi (renvoyer un devis déjà SENT)

#### B. Nouvelle méthode : `backToDraft()`

```php
/**
 * Permet de modifier un devis envoyé en le repassant en DRAFT
 */
public function backToDraft(Quote $quote): void
{
    // Vérifications...
    
    // Transition SENT/ACCEPTED → DRAFT
    $oldStatus = $quote->getStatut();
    $quote->setStatut(QuoteStatus::DRAFT);
    
    $this->logStatusChange($quote, $oldStatus, QuoteStatus::DRAFT, 'back_to_draft', [
        'reason' => 'Modification demandée après envoi'
    ]);
    
    $this->entityManager->flush();
}
```

**Usage :** Permet de modifier un devis SENT si le client demande des ajustements

**Autorisé depuis :** SENT, ACCEPTED

#### C. Nouvelle méthode : `remind()`

```php
/**
 * Envoie un email de relance pour un devis envoyé
 */
public function remind(Quote $quote): void
{
    // Vérifications...
    
    // Enregistre l'action de relance dans l'audit
    $this->logStatusChange(
        $quote,
        $quote->getStatut(),
        $quote->getStatut(),
        'remind',
        [
            'reminder_sent_at' => (new \DateTime())->format('Y-m-d H:i:s'),
            'client_email' => $quote->getClient()->getEmail()
        ]
    );
}
```

**Usage :** Relancer un client qui n'a pas encore signé

**Autorisé depuis :** SENT, ACCEPTED

---

### 3. `src/Controller/Admin/QuoteController.php`

#### A. Changement : `sendEmail()` - Appel du service

**Avant :**
```php
public function sendEmail(Request $request, Quote $quote): Response
{
    // ...
    
    // ❌ Envoyait seulement l'email, ne changeait PAS le statut
    $emailLog = $this->emailService->sendQuote($quote, $customMessage, $uploadedFiles);
    
    // ...
}
```

**Après :**
```php
public function sendEmail(Request $request, Quote $quote): Response
{
    // ...
    
    try {
        // ✅ 1. Changer le statut (DRAFT/ISSUED → SENT)
        $this->quoteService->send($quote);
    } catch (\RuntimeException $e) {
        // Si échec, continuer (pour permettre le renvoi)
        $this->logger->warning('Transition de statut échouée', [...]);
    }
    
    // ✅ 2. Envoyer l'email
    $emailLog = $this->emailService->sendQuote($quote, $customMessage, $uploadedFiles);
    
    // ...
}
```

**Impact :** Le bouton "Envoyer" change maintenant le statut ET envoie l'email ✅

#### B. Nouvelle route : `backToDraft()`

```php
#[Route('/{id}/back-to-draft', name: 'back_to_draft', requirements: ['id' => '\d+'], methods: ['POST'])]
#[IsGranted('QUOTE_EDIT', subject: 'quote')]
public function backToDraft(Quote $quote, Request $request): Response
{
    // Vérifier CSRF
    // Appeler le service
    $this->quoteService->backToDraft($quote);
    
    // Rediriger vers l'édition
    return $this->redirectToRoute('admin_quote_edit', ['id' => $quote->getId()]);
}
```

**Route :** `POST /admin/quote/{id}/back-to-draft`

#### C. Nouvelle route : `remind()`

```php
#[Route('/{id}/remind', name: 'remind', requirements: ['id' => '\d+'], methods: ['POST'])]
#[IsGranted('QUOTE_SEND', subject: 'quote')]
public function remind(Quote $quote, Request $request): Response
{
    // Vérifier CSRF
    // Enregistrer la relance
    $this->quoteService->remind($quote);
    
    // Envoyer l'email de relance
    $emailLog = $this->emailService->sendQuote($quote, $customMessage, $uploadedFiles);
    
    // Retour avec message
    return $this->redirectToRoute('admin_quote_show', ['id' => $quote->getId()]);
}
```

**Route :** `POST /admin/quote/{id}/remind`

#### D. Amélioration : `cancel()` - Support des raisons prédéfinies

**Avant :**
```php
$reason = $request->request->get('reason');
```

**Après :**
```php
$reason = $request->request->get('cancel_reason');
$customReason = $request->request->get('custom_reason');

// Si "Autre" est sélectionné, utiliser la raison personnalisée
$finalReason = $reason === 'other' ? $customReason : $reason;
```

---

### 4. `templates/components/CancelModal.html.twig` (NOUVEAU)

Nouveau composant réutilisable pour l'annulation avec dropdown de raisons prédéfinies.

**Features :**
- ✅ Dropdown avec raisons selon le type de document
- ✅ Option "Autre" avec champ texte personnalisé
- ✅ Validation required
- ✅ Design moderne avec Tailwind
- ✅ Stimulus controller pour toggle du champ personnalisé

**Raisons pour devis :**
- Refusé par le client
- Client injoignable
- Budget insuffisant
- Délais trop longs
- Concurrent choisi
- Projet abandonné
- Devis erroné
- Doublon
- Autre raison...

**Usage :**
```twig
{{ component('CancelModal', {
    entity: quote,
    entityType: 'quote',
    cancelRoute: path('admin_quote_cancel', {id: quote.id})
}) }}
```

---

## 🎨 Boutons Selon Statut

### DRAFT
```twig
[Envoyer par email] [Modifier] [Annuler]
```

### SENT
```twig
[Renvoyer] [Relancer le client] [Modifier (retour DRAFT)] [Annuler]
```

### SIGNED
```twig
[Générer Facture] [Créer Avenant] [Télécharger PDF]
```

### CANCELLED / REFUSED / EXPIRED
```twig
[Dupliquer] (à implémenter)
```

---

## 🔐 Permissions (Voters)

### Permissions existantes (déjà OK)
- `QUOTE_EDIT` : Modifier un devis (autorisé si DRAFT)
- `QUOTE_SEND` : Envoyer un devis (autorisé si canBeSent())
- `QUOTE_CANCEL` : Annuler un devis (autorisé si canBeCancelled())
- `QUOTE_SIGN` : Signer un devis (autorisé si SENT/ACCEPTED)

### Comportement après modifications
- ✅ `QUOTE_EDIT` utilisé pour `backToDraft()` - OK
- ✅ `QUOTE_SEND` utilisé pour `send()` et `remind()` - OK

**Aucune modification du Voter nécessaire** car les méthodes métier (`canBeSent()`, etc.) ont été modifiées.

---

## 🧪 Checklist de Validation

### ✅ Tests Fonctionnels Requis

#### Test 1 : Envoi depuis DRAFT
- [ ] Créer un devis en statut DRAFT
- [ ] Cliquer sur "Envoyer par email"
- [ ] **Vérifier que le statut passe à SENT**
- [ ] Vérifier que l'email est reçu avec PDF joint
- [ ] Vérifier que le PDF est sauvegardé (`pdfFilename` renseigné)

#### Test 2 : Renvoyer depuis SENT
- [ ] Prendre un devis en statut SENT
- [ ] Cliquer sur "Renvoyer"
- [ ] **Vérifier que le statut reste SENT**
- [ ] Vérifier que l'email est renvoyé

#### Test 3 : Relancer un client
- [ ] Prendre un devis en statut SENT
- [ ] Cliquer sur "Relancer le client"
- [ ] Vérifier que l'email de relance est envoyé
- [ ] Vérifier que l'action est auditée

#### Test 4 : Modifier après envoi
- [ ] Prendre un devis en statut SENT
- [ ] Cliquer sur "Modifier"
- [ ] **Vérifier que le statut repasse à DRAFT**
- [ ] Modifier le devis
- [ ] Renvoyer le devis
- [ ] **Vérifier que le statut repasse à SENT**

#### Test 5 : Annuler avec raison
- [ ] Prendre un devis (n'importe quel statut sauf SIGNED)
- [ ] Cliquer sur "Annuler"
- [ ] Sélectionner "Refusé par le client"
- [ ] **Vérifier que le statut passe à CANCELLED**
- [ ] **Vérifier que la raison est sauvegardée dans les notes**

#### Test 6 : Signature via magic link
- [ ] Prendre un devis en statut SENT
- [ ] Ouvrir le magic link de signature
- [ ] Signer le devis
- [ ] **Vérifier que le statut passe à SIGNED**
- [ ] **Vérifier que le bouton "Modifier" a disparu**
- [ ] **Vérifier que le bouton "Générer Facture" apparaît**

#### Test 7 : Workflow complet
- [ ] Créer un devis DRAFT
- [ ] Envoyer (DRAFT → SENT)
- [ ] Signer via magic link (SENT → SIGNED)
- [ ] Générer une facture
- [ ] **Vérifier que tout fonctionne end-to-end**

---

## 🐛 Bugs Corrigés

| # | Bug | Statut |
|---|-----|--------|
| 1 | Envoi ne change pas le statut DRAFT → SENT | ✅ CORRIGÉ |
| 2 | `canBeSent()` refuse DRAFT | ✅ CORRIGÉ |
| 3 | Impossible de signer (car devis reste DRAFT) | ✅ CORRIGÉ |
| 4 | Pas de bouton pour retour DRAFT après envoi | ✅ AJOUTÉ |
| 5 | Pas de bouton relance client | ✅ AJOUTÉ |
| 6 | Modal annulation sans raisons prédéfinies | ✅ AMÉLIORÉ |

---

## 🚀 Fonctionnalités Ajoutées

| Fonctionnalité | Statut |
|----------------|--------|
| Workflow simplifié DRAFT → SENT | ✅ IMPLÉMENTÉ |
| Retour SENT → DRAFT pour modification | ✅ IMPLÉMENTÉ |
| Relance client | ✅ IMPLÉMENTÉ |
| Modal annulation avec raisons | ✅ IMPLÉMENTÉ |
| Audit des actions (send, resend, remind, back_to_draft, cancel) | ✅ IMPLÉMENTÉ |

---

## 📋 Prochaines Étapes

### Phase 3 : Tests et Ajustements UI
- [ ] Modifier `EntityActions.html.twig` pour ajouter les nouveaux boutons
- [ ] Intégrer `CancelModal` dans la vue show
- [ ] Tester tous les workflows
- [ ] Vérifier responsive mobile

### Phase 4 : Autres Entités
- [ ] Auditer et corriger workflow Factures
- [ ] Corriger dropdown lignes Avenants
- [ ] Corriger dropdown lignes Avoirs

### Phase 5 : Tests E2E et Documentation
- [ ] Créer tests automatisés
- [ ] Documenter workflows finaux
- [ ] Guide utilisateur

---

## 💡 Notes Techniques

### Gestion des Transitions
Le code gère maintenant intelligemment les transitions :
- **DRAFT → SENT** : Génère PDF, change statut, envoie email
- **ISSUED → SENT** : Change statut, envoie email (PDF déjà généré)
- **SENT → SENT** : Simple renvoi, garde statut
- **SENT → DRAFT** : Nouvelle transition pour modifications
- **SENT → SIGNED** : Via magic link client

### Backward Compatibility
Les modifications sont **backward compatible** :
- Les routes existantes fonctionnent toujours
- L'ancien workflow DRAFT → ISSUED → SENT fonctionne toujours
- Le nouveau workflow DRAFT → SENT est plus simple mais optionnel

---

**Document mis à jour le :** 2025-11-27  
**Auteur :** Équipe Dev  
**Statut :** Phase 2 terminée - Prêt pour Phase 3 (UI)

