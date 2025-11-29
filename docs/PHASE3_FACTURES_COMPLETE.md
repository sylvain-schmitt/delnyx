# 📋 Phase 3 - Simplification Workflow Factures

**Date :** 2025-11-27  
**Statut :** ✅ TERMINÉE

---

## 🎯 Objectifs

Simplifier le workflow des factures tout en conservant la conformité légale française :
- ✅ Garder le statut ISSUED (document comptable obligatoire)
- ✅ Ajouter raccourci "Émettre & Envoyer" pour DRAFT
- ✅ Permettre DRAFT → SENT direct (émission automatique)
- ✅ Améliorer l'annulation (DRAFT et ISSUED)
- ✅ Intégrer CancelModal avec raisons spécifiques

---

## 📊 Modifications Backend

### 1. `src/Entity/InvoiceStatus.php`

#### ✅ `canBeSent()` amélioré
**Avant :** Ne permettait pas DRAFT → SENT  
**Après :** Permet DRAFT → SENT (avec émission automatique)

```php
public function canBeSent(): bool
{
    return !in_array($this, [self::CANCELLED]);
}
```

#### ✅ `canBeCancelled()` amélioré
**Avant :** Seulement DRAFT  
**Après :** DRAFT et ISSUED

```php
public function canBeCancelled(): bool
{
    return in_array($this, [self::DRAFT, self::ISSUED]);
}
```

---

### 2. `src/Service/InvoiceService.php`

#### ✅ Nouvelle méthode `issueAndSend()`
Raccourci pour émettre et envoyer en une seule action :
```php
public function issueAndSend(Invoice $invoice, ?string $channel = 'email'): void
{
    // Émettre d'abord (DRAFT → ISSUED)
    $this->issue($invoice);
    
    // Puis envoyer (ISSUED → SENT)
    $this->send($invoice, $channel);
}
```

#### ✅ Méthode `send()` améliorée
**Avant :** Ne gérait pas DRAFT  
**Après :** Émet automatiquement si DRAFT, puis envoie

```php
// Si DRAFT, émettre automatiquement avant d'envoyer
if ($statutEnum === InvoiceStatus::DRAFT) {
    $this->issue($invoice);
}
```

#### ✅ Nouvelle méthode `cancel()`
Gère l'annulation avec raison :
```php
public function cancel(Invoice $invoice, ?string $reason = null): void
{
    // Transition DRAFT/ISSUED → CANCELLED
    // Enregistre la raison dans les notes
    // Audit et traçabilité
}
```

---

### 3. `src/Security/Voter/InvoiceVoter.php`

#### ✅ Nouvelle constante `CANCEL`
```php
public const CANCEL = 'INVOICE_CANCEL';
```

#### ✅ Nouvelle méthode `canCancel()`
```php
private function canCancel(Invoice $invoice, UserInterface $user, InvoiceStatus $status): bool
{
    return $status->canBeCancelled();
}
```

#### ✅ `canSend()` mis à jour
Permet maintenant DRAFT → SENT

---

### 4. `src/Controller/Admin/InvoiceController.php`

#### ✅ Nouvelle route `issueAndSend()`
```php
#[Route('/{id}/issue-and-send', name: 'issue_and_send', ...)]
#[IsGranted('INVOICE_ISSUE', subject: 'invoice')]
public function issueAndSend(Request $request, Invoice $invoice): Response
```

#### ✅ Route `send()` améliorée
Utilise maintenant le service qui gère DRAFT → SENT automatiquement

#### ✅ Route `sendEmail()` améliorée
Utilise le service `send()` qui génère le PDF automatiquement

#### ✅ Route `cancel()` refactorisée
Utilise maintenant `InvoiceService::cancel()` avec raisons

---

## 🎨 Modifications Frontend

### 1. `templates/components/EntityActions.html.twig`

#### ✅ Boutons spécifiques Invoice ajoutés

**DRAFT :**
- ✅ Bouton "Émettre" (formulaire POST)
- ✅ Bouton "Émettre & Envoyer" (modal email)

**ISSUED :**
- ✅ Bouton "Envoyer la facture" (modal email)

**SENT :**
- ✅ Bouton "Relancer le client" (modal email)

**DRAFT/ISSUED :**
- ✅ Bouton "Annuler" (CancelModal)

---

### 2. `templates/admin/invoice/show.html.twig`

#### ✅ Alerte annulation ajoutée
```twig
{% if invoice.statutEnum.value == 'cancelled' %}
    <div class="bg-red-500/20 ...">
        <p>Facture annulée</p>
        <p>Voir la section "Conditions et notes" pour la raison.</p>
    </div>
{% endif %}
```

#### ✅ Section "Conditions et notes" améliorée
- ✅ S'affiche toujours si facture annulée
- ✅ Affiche la raison d'annulation dans les notes
- ✅ `whitespace-pre-line` pour respecter les retours à la ligne

#### ✅ CancelModal intégré
```twig
{{ component('CancelModal') }}
```

---

### 3. `templates/admin/invoice/index.html.twig`

#### ✅ CancelModal ajouté
```twig
{{ component('CancelModal') }}
```

---

## 🔄 Workflow Final

### DRAFT
```
[Émettre] [Émettre & Envoyer] [Modifier] [Annuler]
```

### ISSUED
```
[Envoyer la facture] [Marquer payée] [Créer avoir] [Annuler]
```

### SENT
```
[Relancer le client] [Marquer payée] [Créer avoir]
```

### PAID
```
[Relancer le client] [Créer avoir]
```

### CANCELLED
```
Aucune action (final)
```

---

## 📝 Raisons d'Annulation (Factures)

1. Erreur de facturation
2. Facture en doublon
3. Prestation non réalisée
4. Remplacée par un avoir
5. Autre raison (préciser)

---

## ✅ Tests à Effectuer

1. **DRAFT → Émettre & Envoyer**
   - [ ] Créer une facture DRAFT
   - [ ] Cliquer "Émettre & Envoyer"
   - [ ] Vérifier statut = SENT
   - [ ] Vérifier PDF généré
   - [ ] Vérifier email envoyé

2. **DRAFT → Envoyer (direct)**
   - [ ] Créer une facture DRAFT
   - [ ] Cliquer "Envoyer" (via modal email)
   - [ ] Vérifier statut = SENT
   - [ ] Vérifier PDF généré

3. **ISSUED → Envoyer**
   - [ ] Prendre une facture ISSUED
   - [ ] Cliquer "Envoyer la facture"
   - [ ] Vérifier statut = SENT

4. **SENT → Relancer**
   - [ ] Prendre une facture SENT
   - [ ] Cliquer "Relancer le client"
   - [ ] Vérifier email envoyé

5. **Annulation DRAFT**
   - [ ] Prendre une facture DRAFT
   - [ ] Cliquer "Annuler"
   - [ ] Sélectionner une raison
   - [ ] Vérifier statut = CANCELLED
   - [ ] Vérifier raison dans notes

6. **Annulation ISSUED**
   - [ ] Prendre une facture ISSUED
   - [ ] Cliquer "Annuler"
   - [ ] Sélectionner une raison
   - [ ] Vérifier statut = CANCELLED
   - [ ] Vérifier raison dans notes

---

## 📁 Fichiers Modifiés

### Backend (4 fichiers)
- ✅ `src/Entity/InvoiceStatus.php`
- ✅ `src/Service/InvoiceService.php`
- ✅ `src/Security/Voter/InvoiceVoter.php`
- ✅ `src/Controller/Admin/InvoiceController.php`

### Frontend (3 fichiers)
- ✅ `templates/components/EntityActions.html.twig`
- ✅ `templates/admin/invoice/show.html.twig`
- ✅ `templates/admin/invoice/index.html.twig`

---

## 🎉 Résultat

**Workflow simplifié et fluide :**
- ✅ Raccourci "Émettre & Envoyer" pour cas simples
- ✅ Possibilité d'émettre puis envoyer séparément pour cas complexes
- ✅ Envoi direct depuis DRAFT (émission automatique)
- ✅ Annulation améliorée avec raisons spécifiques
- ✅ Interface cohérente avec les autres entités

**Conformité légale :**
- ✅ Statut ISSUED conservé (document comptable)
- ✅ Traçabilité complète (audit, notes)
- ✅ Numérotation préservée même si annulée

---

**Phase 3 : ✅ TERMINÉE**

