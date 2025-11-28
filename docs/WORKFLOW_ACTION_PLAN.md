# 🎯 Plan d'action : Correction des workflows

## Vue d'ensemble

**Objectif :** Corriger tous les bugs de workflow et garantir un système de facturation fonctionnel et fiable.

**Durée estimée :** 12-16 heures  
**Priorité :** 🔴 CRITIQUE

---

## 📊 Organisation des tâches

### Phase 1 : 🔍 AUDIT & DIAGNOSTIC (2-3h)

#### 1.1 Configuration environnement debug
- [ ] Activer logs Symfony détaillés
- [ ] Configurer Profiler pour tracer les transitions
- [ ] Préparer outils de test (Mailhog, etc.)

#### 1.2 Audit des statuts actuels
- [ ] Documenter workflow RÉEL de chaque entité
- [ ] Comparer avec workflow ATTENDU
- [ ] Identifier les écarts et blocages

#### 1.3 Identification des points de blocage
- [ ] Lister tous les Voters et leurs conditions
- [ ] Lister tous les EventSubscribers actifs
- [ ] Identifier les conflits entre Subscribers
- [ ] Tracer une transition complète avec logs

**Livrables Phase 1 :**
- Document `WORKFLOW_CURRENT_STATE.md` avec état réel
- Liste des Voters/Subscribers à corriger
- Logs de transactions bloquées

---

### Phase 2 : 🧾 CORRECTION DEVIS (3-4h)

#### 2.1 Correction envoi (DRAFT → SENT)
**Fichiers à modifier :**
- `src/Controller/Admin/QuoteController.php::sendEmail()`
- `src/Service/QuoteService.php::send()`

**Actions :**
```php
// Dans QuoteService::send()
public function send(Quote $quote): void
{
    // Vérifier que le devis peut être envoyé
    if (!$quote->getStatutEnum()->canBeSent()) {
        throw new \RuntimeException('Ce devis ne peut pas être envoyé');
    }
    
    // Changer le statut vers SENT
    $quote->setStatut(QuoteStatus::SENT);
    $quote->setDateEnvoi(new \DateTime());
    
    // Persister
    $this->entityManager->flush();
}
```

#### 2.2 Correction signature (SENT → SIGNED)
**Fichiers à modifier :**
- `src/Security/Voter/QuoteVoter.php::canSign()`
- `src/Entity/QuoteStatus.php::canBeSigned()`
- `src/Controller/Public/PublicDocumentController.php::signQuote()`

**Actions :**
```php
// Dans QuoteStatus.php
public function canBeSigned(): bool
{
    return in_array($this, [
        self::SENT,    // ✅ Peut signer depuis SENT
        self::SIGNED,  // ✅ Peut re-signer (signature modifiée)
    ]);
}

// Dans QuoteVoter.php
private function canSign(Quote $quote, UserInterface $user): bool
{
    $status = $quote->getStatutEnum();
    
    // Vérifier le statut
    if (!$status->canBeSigned()) {
        return false;
    }
    
    // Vérifier que le devis a un client avec email
    if (!$quote->getClient() || !$quote->getClient()->getEmail()) {
        return false;
    }
    
    return true;
}
```

#### 2.3 Tests
- [ ] Test envoi : DRAFT → SENT
- [ ] Test réception email + PDF
- [ ] Test magic link signature
- [ ] Test signature : SENT → SIGNED
- [ ] Test génération facture depuis SIGNED

**Livrables Phase 2 :**
- Workflow devis fonctionnel end-to-end
- Tests automatisés pour workflow devis
- Documentation à jour

---

### Phase 3 : 💰 VÉRIFICATION FACTURES (2h)

#### 3.1 Audit workflow factures
- [ ] Tester émission : DRAFT → ISSUED
- [ ] Tester envoi : ISSUED → SENT
- [ ] Tester paiement : SENT → PAID
- [ ] Tester annulation via avoir total

#### 3.2 Corrections si nécessaire
**Points de vigilance :**
- `InvoiceController::issue()`
- `InvoiceService::issue()`
- `InvoiceVoter::canIssue()`
- `LockOnIssueSubscriber` (peut bloquer)

#### 3.3 Tests
- [ ] Workflow complet DRAFT → ISSUED → SENT → PAID
- [ ] Annulation via avoir total
- [ ] PDF et emails

**Livrables Phase 3 :**
- Workflow factures validé
- Tests automatisés
- Documentation

---

### Phase 4 : 📝 CORRECTION AVENANTS (3-4h)

#### 4.1 Correction dropdown lignes source
**Problème :** Les lignes du devis parent n'apparaissent pas.

**Fichiers à modifier :**
- `src/Form/AmendmentLineType.php`
- `src/Form/EventSubscriber/AmendmentLineSourceLineSubscriber.php`
- `assets/controllers/amendment_form_controller.js`

**Diagnostic :**
1. Vérifier que le FormEvent est déclenché
2. Vérifier que les lignes sont bien chargées depuis le devis
3. Vérifier le JavaScript qui populate le dropdown

**Solution probable :**
```php
// Dans AmendmentLineSourceLineSubscriber
public function preSetData(FormEvent $event): void
{
    $line = $event->getData();
    $form = $event->getForm();
    
    // Récupérer l'avenant parent
    $amendment = $line?->getAmendment() ?? $form->getParent()?->getData();
    
    if (!$amendment || !$amendment->getQuote()) {
        return;
    }
    
    // Récupérer TOUTES les lignes du devis
    $quoteLines = $amendment->getQuote()->getLines();
    
    // Construire les choices pour le dropdown
    $choices = [];
    foreach ($quoteLines as $quoteLine) {
        $label = sprintf(
            '%s (%.2f € x %s)',
            $quoteLine->getDescription(),
            $quoteLine->getUnitPrice() / 100,
            $quoteLine->getQuantity()
        );
        $choices[$label] = $quoteLine->getId();
    }
    
    // Modifier le champ sourceLine
    $form->add('sourceLine', EntityType::class, [
        'class' => QuoteLine::class,
        'choices' => $quoteLines,
        'choice_label' => function(QuoteLine $line) {
            return sprintf(
                '%s (%.2f € x %s)',
                $line->getDescription(),
                $line->getUnitPrice() / 100,
                $line->getQuantity()
            );
        },
        'placeholder' => 'Sélectionner une ligne du devis',
        'required' => true,
        'attr' => ['class' => 'form-select'],
    ]);
}
```

#### 4.2 Vérification workflow
- [ ] Émission avenant
- [ ] Signature avenant
- [ ] Recalcul devis parent

#### 4.3 Tests
- [ ] Création avenant avec lignes
- [ ] Modifications correctement appliquées
- [ ] Devis parent recalculé

**Livrables Phase 4 :**
- Dropdown lignes fonctionnel
- Workflow avenants complet
- Tests automatisés

---

### Phase 5 : 💳 CORRECTION AVOIRS (3-4h)

#### 5.1 Correction dropdown lignes facture
**Même problème que les avenants.**

**Fichiers à modifier :**
- `src/Form/CreditNoteLineType.php`
- `src/Form/EventSubscriber/CreditNoteLineSourceLineSubscriber.php`
- `assets/controllers/credit_note_form_controller.js`

**Solution similaire à Phase 4.1**

#### 5.2 Vérification workflow
- [ ] Émission avoir
- [ ] Envoi avoir
- [ ] Annulation facture si avoir total

#### 5.3 Tests
- [ ] Création avoir ligne par ligne
- [ ] Avoir total annule facture
- [ ] PDF et emails

**Livrables Phase 5 :**
- Dropdown lignes fonctionnel
- Workflow avoirs complet
- Tests automatisés

---

### Phase 6 : 🧪 TESTS E2E (2-3h)

#### 6.1 Tests workflow devis
```php
// tests/Functional/QuoteWorkflowFullTest.php
public function testCompleteQuoteWorkflow(): void
{
    // 1. Créer devis DRAFT
    // 2. Envoyer → SENT
    // 3. Signer → SIGNED
    // 4. Générer facture
    // 5. Vérifier états
}
```

#### 6.2 Tests workflow facture
```php
// tests/Functional/InvoiceWorkflowFullTest.php
public function testCompleteInvoiceWorkflow(): void
{
    // 1. Créer facture DRAFT
    // 2. Émettre → ISSUED
    // 3. Envoyer → SENT
    // 4. Payer → PAID
    // 5. Vérifier états
}
```

#### 6.3 Tests workflow avenant
```php
// tests/Functional/AmendmentWorkflowTest.php
public function testAmendmentWithQuoteRecalculation(): void
{
    // 1. Devis signé
    // 2. Créer avenant
    // 3. Signer avenant
    // 4. Vérifier recalcul devis
}
```

#### 6.4 Tests workflow avoir
```php
// tests/Functional/CreditNoteWorkflowTest.php
public function testTotalCreditNoteCancelsInvoice(): void
{
    // 1. Facture PAID
    // 2. Créer avoir total
    // 3. Émettre avoir
    // 4. Vérifier facture CANCELLED
}
```

**Livrables Phase 6 :**
- Suite de tests E2E complète
- Coverage > 80% sur workflows
- CI/CD configuré

---

### Phase 7 : 📚 DOCUMENTATION (1-2h)

#### 7.1 Schémas de workflow
Créer des diagrammes pour chaque entité :
- Workflow devis (avec branches refus/expiration)
- Workflow facture (avec annulation)
- Workflow avenant (avec recalcul)
- Workflow avoir (avec annulation facture)

#### 7.2 Guide développeur
- Comment ajouter un nouveau statut
- Comment ajouter une transition
- Comment débugger un workflow
- Liste des Voters/Subscribers et leur rôle

#### 7.3 Guide utilisateur
- Processus complet de facturation
- Que faire en cas d'erreur
- FAQ

**Livrables Phase 7 :**
- `docs/workflows/` avec schémas
- `docs/DEVELOPER_GUIDE.md`
- `docs/USER_GUIDE.md`

---

## 🛠️ Outils et commandes utiles

### Debug workflows

```bash
# Activer les logs détaillés
# Dans .env.local
APP_ENV=dev
APP_DEBUG=true

# Vider le cache
php bin/console cache:clear

# Regarder les logs en temps réel
tail -f var/log/dev.log | grep -i "workflow\|status\|transition"
```

### Tester une transition manuellement

```php
// Dans Symfony console ou script
$quote = $quoteRepository->find(1);
$quote->setStatut(QuoteStatus::SENT);
$entityManager->flush();
```

### Lister tous les EventSubscribers actifs

```bash
php bin/console debug:event-dispatcher
```

### Profiler une requête

```bash
# Accéder à http://localhost:8000/_profiler/
# Chercher la requête problématique
# Onglet "Doctrine" pour voir les queries
# Onglet "Events" pour voir les listeners appelés
```

---

## ⚠️ Points d'attention

### 1. EventSubscribers en conflit
- Plusieurs subscribers peuvent écouter le même événement
- L'ordre d'exécution peut causer des bugs
- Utiliser les priorités si nécessaire

### 2. Voters trop restrictifs
- Vérifier que les conditions ne sont pas trop strictes
- Penser aux cas limites
- Logger les refus pour debug

### 3. Flush() au bon moment
- Ne pas flusher trop tôt (avant validation)
- Ne pas oublier de flusher après changement de statut
- Attention aux transactions imbriquées

### 4. Tests avec vraies données
- Ne pas tester qu'avec des mocks
- Utiliser des fixtures réalistes
- Tester les cas limites et erreurs

---

## ✅ Checklist finale

Avant de considérer le travail terminé :

### Fonctionnel
- [ ] Tous les workflows testés manuellement
- [ ] Tous les tests automatisés passent
- [ ] Pas de régression sur fonctionnalités existantes
- [ ] PDFs générés correctement
- [ ] Emails envoyés correctement

### Code
- [ ] Code review effectué
- [ ] Pas de code dupliqué
- [ ] Logs de debug retirés
- [ ] Variables d'environnement documentées
- [ ] Migrations testées

### Documentation
- [ ] Schémas de workflow à jour
- [ ] README.md mis à jour
- [ ] CHANGELOG.md mis à jour
- [ ] Guide développeur complet
- [ ] Guide utilisateur complet

### Déploiement
- [ ] Tests en environnement de staging
- [ ] Plan de rollback préparé
- [ ] Migration BDD testée
- [ ] Monitoring configuré
- [ ] Utilisateurs prévenus des changements

---

## 📅 Planning proposé

**Semaine 1 : Diagnostic & Devis**
- Lundi-Mardi : Phase 1 (Audit)
- Mercredi-Jeudi : Phase 2 (Devis)
- Vendredi : Phase 3 (Factures)

**Semaine 2 : Avenants & Avoirs**
- Lundi-Mardi : Phase 4 (Avenants)
- Mercredi-Jeudi : Phase 5 (Avoirs)
- Vendredi : Phase 6 (Tests E2E)

**Semaine 3 : Documentation & Déploiement**
- Lundi : Phase 7 (Documentation)
- Mardi : Revue finale et corrections
- Mercredi : Déploiement staging
- Jeudi : Tests utilisateurs
- Vendredi : Déploiement production

---

## 🎯 Success Criteria

Le projet est considéré comme réussi si :

1. ✅ **100% des workflows fonctionnent** end-to-end
2. ✅ **0 bug critique** restant
3. ✅ **Tests automatisés** couvrent tous les workflows
4. ✅ **Documentation complète** et à jour
5. ✅ **Déploiement production** sans incident
6. ✅ **Utilisateurs satisfaits** du système

---

**Créé le :** 2025-11-27  
**Prochaine action :** Phase 1 - Audit & Diagnostic  
**Responsable :** Équipe Dev  
**Deadline :** 3 semaines

