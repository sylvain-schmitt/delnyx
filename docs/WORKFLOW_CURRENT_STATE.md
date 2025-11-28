# 🔍 État Actuel des Workflows - Rapport d'Audit

## Date : 2025-11-27
## Status : CRITIQUE - Plusieurs workflows non fonctionnels

---

## 🎯 Résumé Exécutif

**Problème principal identifié :** Le bouton "Envoyer" dans l'interface n'appelle pas la bonne méthode. Il envoie uniquement l'email sans changer le statut du document.

**Impact :** 
- Les devis restent en statut DRAFT même après envoi
- Impossible de signer un devis car il n'est jamais en statut SENT
- Le workflow est complètement bloqué

---

## 📊 ANALYSE DÉTAILLÉE : DEVIS (Quote)

### 1. 🐛 BUG CRITIQUE : Envoi ne change pas le statut

#### Code incriminé

**EntityActions.html.twig (ligne 92) :**
```twig
<button type="button" 
    data-email-trigger-url-value="{{ path('admin_quote_send_email', {id: entity.id}) }}"
    ...>
    Envoyer le devis
</button>
```

**QuoteController::sendEmail() (ligne 684-719) :**
```php
public function sendEmail(Request $request, Quote $quote): Response
{
    // ...
    
    try {
        $customMessage = $request->request->get('custom_message');
        $uploadedFiles = $request->files->get('attachments', []);
        
        // ❌ BUG : Envoie seulement l'email, ne change PAS le statut !
        $emailLog = $this->emailService->sendQuote($quote, $customMessage, $uploadedFiles);
        
        if ($emailLog->getStatus() === 'sent') {
            $this->addFlash('success', sprintf('Devis envoyé avec succès à %s', $client->getEmail()));
        }
    } catch (\Exception $e) {
        // ...
    }
    
    return $this->redirectToRoute('admin_quote_show', ['id' => $quote->getId()]);
}
```

#### ✅ Solution

Il existe une méthode `QuoteController::send()` (ligne 511-528) qui fait la bonne chose :

```php
public function send(Quote $quote, Request $request): Response
{
    // ...
    
    try {
        // ✅ Appelle le service qui change le statut
        $this->quoteService->send($quote);
        $this->addFlash('success', 'Devis envoyé avec succès.');
    } catch (\Exception $e) {
        // ...
    }
    
    return $this->redirectToRoute('admin_quote_show', ['id' => $quote->getId()]);
}
```

**MAIS** cette route n'est jamais appelée par l'interface !

#### 🔧 Correctif proposé

**Option 1 : Fusionner les deux méthodes (RECOMMANDÉ)**

Modifier `sendEmail()` pour qu'il appelle aussi le service de transition :

```php
public function sendEmail(Request $request, Quote $quote): Response
{
    // 1. Changer le statut DRAFT → SENT (si applicable)
    try {
        $this->quoteService->send($quote);  // ✅ Ajouter cette ligne
    } catch (\RuntimeException $e) {
        // Si la transition échoue (ex: devis en DRAFT), continuer quand même
        // pour permettre le renvoi
    }
    
    // 2. Envoyer l'email
    $emailLog = $this->emailService->sendQuote($quote, $customMessage, $uploadedFiles);
    
    // ...
}
```

**Option 2 : Séparer "Émettre" et "Envoyer"**

Modifier le workflow UX pour avoir deux boutons distincts :
- Bouton "Émettre" : DRAFT → ISSUED (appelle `issue()`)
- Bouton "Envoyer par email" : Envoie l'email (appelle `sendEmail()`)

---

### 2. 🐛 BUG CRITIQUE : Workflow de statut incohérent

#### QuoteStatus::canBeSent() (ligne 139-142)

```php
public function canBeSent(): bool
{
    // ❌ Un devis DRAFT ne peut PAS être envoyé !
    return !in_array($this, [self::DRAFT, self::REFUSED, self::EXPIRED, self::CANCELLED]);
}
```

**Problème :** Cette logique force le workflow suivant :

```
DRAFT --[issue()]--> ISSUED --[send()]--> SENT
```

**MAIS** l'interface ne propose QUE le bouton "Envoyer" sans bouton "Émettre" !

#### QuoteService::send() (ligne 111-159)

```php
public function send(Quote $quote): void
{
    // Vérifie que la transition est possible
    if (!$quote->getStatut()?->canBeSent()) {
        throw new \RuntimeException(
            sprintf(
                'Le devis ne peut pas être envoyé depuis l\'état "%s".',
                $quote->getStatut()?->getLabel() ?? 'inconnu'
            )
        );
    }
    
    // Ne change le statut que si ISSUED → SENT
    $oldStatus = $quote->getStatut();
    if ($oldStatus === QuoteStatus::ISSUED) {
        $quote->setStatut(QuoteStatus::SENT);
    }
    
    // ...
}
```

**Problème :** Si le devis est en DRAFT, `canBeSent()` retourne `false` et une exception est levée !

#### 🔧 Correctif proposé

**Option A : Permettre l'envoi depuis DRAFT (RECOMMANDÉ pour simplifier UX)**

```php
// Dans QuoteStatus::canBeSent()
public function canBeSent(): bool
{
    // ✅ Autoriser DRAFT, ISSUED, et SENT (pour renvoyer)
    return !in_array($this, [self::SIGNED, self::REFUSED, self::EXPIRED, self::CANCELLED]);
}

// Dans QuoteService::send()
public function send(Quote $quote): void
{
    // ...
    
    $oldStatus = $quote->getStatut();
    
    // Transition automatique selon l'état actuel
    if ($oldStatus === QuoteStatus::DRAFT) {
        // ✅ DRAFT → SENT directement (skip ISSUED)
        $quote->setStatut(QuoteStatus::SENT);
    } elseif ($oldStatus === QuoteStatus::ISSUED) {
        // ISSUED → SENT
        $quote->setStatut(QuoteStatus::SENT);
    } elseif ($oldStatus === QuoteStatus::SENT) {
        // Déjà SENT, on garde le statut (simple renvoi)
    }
    
    // ...
}
```

**Option B : Forcer le workflow DRAFT → ISSUED → SENT**

Ajouter un bouton "Émettre" dans l'interface et garder la logique actuelle.

---

### 3. 🐛 BUG : Impossible de signer depuis SENT

#### QuoteVoter::canSign() (ligne 163-177)

```php
private function canSign(Quote $quote, UserInterface $user, QuoteStatus $status): bool
{
    // ✅ Logique correcte : peut signer depuis ISSUED, SENT, ACCEPTED
    if (!in_array($status, [QuoteStatus::ISSUED, QuoteStatus::SENT, QuoteStatus::ACCEPTED])) {
        return false;
    }
    
    // Vérifier que le devis peut être signé
    try {
        $quote->validateCanBeSigned();
        return true;
    } catch (\RuntimeException $e) {
        return false;
    }
}
```

**Ce code est correct !** Le problème vient du fait que les devis ne passent jamais en statut SENT (bug #1).

#### QuoteStatus::canBeSigned() (ligne 157-160)

```php
public function canBeSigned(): bool
{
    // ✅ Logique correcte
    return in_array($this, [self::SENT, self::ACCEPTED]);
}
```

**Ce code est correct !**

#### 🔧 Correctif

Aucune modification nécessaire ici. Une fois le bug #1 (envoi) corrigé, la signature fonctionnera.

---

## 📋 WORKFLOW ACTUEL vs WORKFLOW ATTENDU

### Workflow Actuel (CASSÉ)

```
┌─────────┐
│  DRAFT  │ ← Création
└─────────┘
     │
     │ Click "Envoyer" (sendEmail())
     ▼
┌─────────┐
│  DRAFT  │ ← ❌ Statut ne change PAS !
└─────────┘
     │
     │ Email envoyé mais statut = DRAFT
     │
     ❌ Impossible de signer (canBeSigned() = false pour DRAFT)
```

### Workflow Attendu (Option A - Simplifié)

```
┌─────────┐
│  DRAFT  │ ← Création
└─────────┘
     │
     │ Click "Envoyer" (sendEmail() modifié)
     ▼
┌─────────┐
│  SENT   │ ← ✅ Transition automatique DRAFT → SENT
└─────────┘
     │
     │ Click "Signer" (magic link)
     ▼
┌─────────┐
│ SIGNED  │ ← ✅ Devis = Contrat
└─────────┘
     │
     │ Click "Générer Facture"
     ▼
  FACTURE
```

### Workflow Attendu (Option B - Complet)

```
┌─────────┐
│  DRAFT  │ ← Création
└─────────┘
     │
     │ Click "Émettre" (issue())
     ▼
┌─────────┐
│ ISSUED  │ ← PDF généré et sauvegardé
└─────────┘
     │
     │ Click "Envoyer" (send())
     ▼
┌─────────┐
│  SENT   │ ← Email envoyé + date enregistrée
└─────────┘
     │
     │ Click "Signer" (magic link)
     ▼
┌─────────┐
│ SIGNED  │ ← Devis = Contrat
└─────────┘
     │
     │ Click "Générer Facture"
     ▼
  FACTURE
```

---

## 🔍 ANALYSE DES EVENTSUBSCRIBERS

### EventSubscribers actifs :

1. **AutoExpireQuoteSubscriber** : Expire automatiquement les devis passés
2. **AutoNumberingSubscriber** : Génère les numéros de document
3. **LockOnSignatureSubscriber** : Verrouille les devis signés
4. **LockOnIssueSubscriber** : Verrouille après émission
5. **PreventQuoteLineModificationSubscriber** : Empêche modification lignes signées
6. **RecalculateTotalsSubscriber** : Recalcule les totaux
7. **RecalculateQuoteFromAmendmentSubscriber** : Recalcule devis après avenant

### ⚠️ Subscribers qui peuvent bloquer les transitions :

#### LockOnSignatureSubscriber (ligne 24-70)

```php
public function preUpdate(LifecycleEventArgs $args): void
{
    $entity = $args->getObject();

    if ($entity instanceof Quote) {
        $this->handleQuoteSignature($entity, $args);
    }
}

private function handleQuoteSignature(Quote $quote, LifecycleEventArgs $args): void
{
    // ...
    
    // Vérifier si le devis est signé et empêcher les modifications
    if ($quote->getStatut() === QuoteStatus::SIGNED) {
        $this->preventModifications($quote, $changeset);
    }
}
```

**Verdict :** ✅ **Correct.** Empêche uniquement les modifications après signature, pas les transitions de statut.

#### LockOnIssueSubscriber

À vérifier : Peut bloquer les modifications après émission.

---

## 🗂️ RÉSUMÉ DES BUGS IDENTIFIÉS

| # | Entité | Bug | Gravité | Impact |
|---|--------|-----|---------|--------|
| 1 | Quote | `sendEmail()` ne change pas le statut | 🔴 CRITIQUE | Workflow bloqué |
| 2 | Quote | `canBeSent()` refuse DRAFT | 🔴 CRITIQUE | Exception levée |
| 3 | Quote | Bouton "Émettre" absent de l'UI | 🟡 MAJEUR | Workflow confus |
| 4 | Quote | Impossible de signer (conséquence de #1) | 🔴 CRITIQUE | Workflow bloqué |

---

## 🚀 PLAN DE CORRECTION

### Phase 1 : Correction immédiate du bug d'envoi

**Fichier :** `src/Controller/Admin/QuoteController.php`

**Ligne 684-719 : Modifier `sendEmail()`**

```php
#[Route('/{id}/send-email', name: 'send_email', requirements: ['id' => '\d+'], methods: ['POST'])]
public function sendEmail(Request $request, Quote $quote): Response
{
    // Vérifier le token CSRF
    if (!$this->isCsrfTokenValid('quote_send_email_' . $quote->getId(), $request->request->get('_token'))) {
        $this->addFlash('error', 'Token CSRF invalide.');
        return $this->redirectToRoute('admin_quote_show', ['id' => $quote->getId()]);
    }

    // Vérifier que le devis a un client avec un email
    $client = $quote->getClient();
    
    if (!$client || !$client->getEmail()) {
        $this->addFlash('error', 'Impossible d\'envoyer le devis : aucun email client configuré.');
        return $this->redirectToRoute('admin_quote_show', ['id' => $quote->getId()]);
    }

    try {
        // ✅ NOUVEAU : Changer le statut avant d'envoyer l'email
        // Si DRAFT → passer directement à SENT
        // Si ISSUED → passer à SENT
        // Si déjà SENT → juste renvoyer
        if ($quote->getStatut() === QuoteStatus::DRAFT || $quote->getStatut() === QuoteStatus::ISSUED) {
            $this->quoteService->send($quote);
        }
        
        // Envoyer l'email
        $customMessage = $request->request->get('custom_message');
        $uploadedFiles = $request->files->get('attachments', []);
        
        $emailLog = $this->emailService->sendQuote($quote, $customMessage, $uploadedFiles);
        
        if ($emailLog->getStatus() === 'sent') {
            $this->addFlash('success', sprintf('Devis envoyé avec succès à %s', $client->getEmail()));
        } else {
            $this->addFlash('error', sprintf('Erreur lors de l\'envoi : %s', $emailLog->getErrorMessage()));
        }
    } catch (\Exception $e) {
        $this->addFlash('error', 'Erreur lors de l\'envoi de l\'email : ' . $e->getMessage());
    }

    return $this->redirectToRoute('admin_quote_show', ['id' => $quote->getId()]);
}
```

### Phase 2 : Assouplir les règles de transition

**Fichier :** `src/Entity/QuoteStatus.php`

**Ligne 139-142 : Modifier `canBeSent()`**

```php
/**
 * Vérifie si le devis peut être envoyé
 * Peut être envoyé depuis DRAFT, ISSUED, SENT (renvoyer), ACCEPTED
 */
public function canBeSent(): bool
{
    // ✅ Autoriser DRAFT et tous les états "vivants"
    return !in_array($this, [self::SIGNED, self::REFUSED, self::EXPIRED, self::CANCELLED]);
}
```

**Fichier :** `src/Service/QuoteService.php`

**Ligne 111-159 : Modifier `send()`**

```php
public function send(Quote $quote): void
{
    // Vérifier les permissions
    if (!$this->authorizationChecker->isGranted('QUOTE_SEND', $quote)) {
        throw new AccessDeniedException('Vous n\'avez pas la permission d\'envoyer ce devis.');
    }

    // Vérifier que la transition est possible
    if (!$quote->getStatut()?->canBeSent()) {
        throw new \RuntimeException(
            sprintf(
                'Le devis ne peut pas être envoyé depuis l\'état "%s".',
                $quote->getStatut()?->getLabel() ?? 'inconnu'
            )
        );
    }

    // Valider que le devis est prêt à être envoyé
    $this->validateBeforeSend($quote);

    // ✅ NOUVEAU : Gérer la transition selon l'état actuel
    $oldStatus = $quote->getStatut();
    
    if ($oldStatus === QuoteStatus::DRAFT) {
        // DRAFT → SENT directement (simplifie le workflow)
        $quote->setStatut(QuoteStatus::SENT);
        
        // Générer le PDF si pas encore fait
        if (!$quote->getPdfFilename()) {
            try {
                $pdfResult = $this->pdfGeneratorService->generateDevisPdf($quote, true);
                $quote->setPdfFilename($pdfResult['filename']);
                $quote->setPdfHash($pdfResult['hash']);
            } catch (\Exception $e) {
                $this->logger->error('Erreur génération PDF', [
                    'quote_id' => $quote->getId(),
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        $this->logStatusChange($quote, $oldStatus, QuoteStatus::SENT, 'send');
    } elseif ($oldStatus === QuoteStatus::ISSUED) {
        // ISSUED → SENT
        $quote->setStatut(QuoteStatus::SENT);
        $this->logStatusChange($quote, $oldStatus, QuoteStatus::SENT, 'send');
    } elseif (in_array($oldStatus, [QuoteStatus::SENT, QuoteStatus::ACCEPTED])) {
        // Déjà envoyé ou accepté : juste un renvoi, pas de changement de statut
        $this->logStatusChange($quote, $oldStatus, $oldStatus, 'resend');
    }
    
    // Toujours enregistrer la date d'envoi et incrémenter le compteur
    $quote->setDateEnvoi(new \DateTime());
    $quote->incrementSentCount();
    
    if (!$quote->getDeliveryChannel()) {
        $quote->setDeliveryChannel('email');
    }

    // Persister
    $this->entityManager->flush();

    $this->logger->info('Devis envoyé', [
        'quote_id' => $quote->getId(),
        'quote_number' => $quote->getNumero(),
        'old_status' => $oldStatus?->value,
        'new_status' => $quote->getStatut()->value,
    ]);
}
```

---

## ✅ CHECKLIST DE VALIDATION

Une fois les corrections effectuées, valider :

- [ ] Créer un devis en statut DRAFT
- [ ] Cliquer sur "Envoyer par email"
- [ ] **Vérifier que le statut passe à SENT**
- [ ] Vérifier que l'email est bien envoyé avec PDF
- [ ] Ouvrir le magic link de signature
- [ ] Signer le devis
- [ ] **Vérifier que le statut passe à SIGNED**
- [ ] Générer une facture depuis le devis signé
- [ ] **Vérifier que la facture est créée**

---

## 📝 NOTES POUR LES AUTRES ENTITÉS

### Factures (Invoice)

À vérifier :
- Workflow DRAFT → ISSUED → SENT → PAID
- Même problème potentiel si `sendEmail()` ne change pas le statut

### Avenants (Amendment)

Bugs reportés par l'utilisateur :
- ❌ Lignes source du devis ne s'affichent pas dans le dropdown

À investiguer :
- `AmendmentLineType` + `AmendmentLineSourceLineSubscriber`

### Avoirs (CreditNote)

Bugs reportés par l'utilisateur :
- ❌ Lignes de la facture ne s'affichent pas dans le dropdown

À investiguer :
- `CreditNoteLineType` + `CreditNoteLineSourceLineSubscriber`

---

## 🎯 PROCHAINES ÉTAPES

1. ✅ **Audit Phase 1 TERMINÉ** - Bugs identifiés et documentés
2. ⏳ **Phase 2 :** Documenter workflow actuel vs attendu (ce document)
3. ⏳ **Phase 3 :** Lister tous les bugs (fait dans ce document)
4. ⏳ **Phase 4 :** Corriger envoi devis (code fourni ci-dessus)
5. ⏳ **Phase 5 :** Tester workflow complet
6. ⏳ **Phase 6 :** Auditer factures, avenants, avoirs

---

**Rapport généré le :** 2025-11-27  
**Auteur :** Audit automatisé  
**Statut :** COMPLET - Prêt pour corrections

