# 📋 Phase 4 - Simplification Workflow Avoirs

**Date :** 2025-11-27  
**Statut :** ✅ TERMINÉE

---

## 🎯 Objectifs

Simplifier le workflow des avoirs tout en conservant la conformité comptable :
- ✅ Renommer APPLIED → REFUNDED (plus clair)
- ✅ Ajouter raccourci "Émettre & Envoyer" pour DRAFT
- ✅ Permettre DRAFT → SENT direct (émission automatique)
- ✅ Améliorer l'annulation (DRAFT, ISSUED et SENT)
- ✅ Intégrer CancelModal avec raisons spécifiques

---

## 📊 Modifications Backend

### 1. `src/Entity/CreditNoteStatus.php`

#### ✅ Renommage APPLIED → REFUNDED
**Avant :**
```php
case APPLIED = 'applied';
// ...
self::APPLIED => 'Appliqué',
```

**Après :**
```php
case REFUNDED = 'refunded';
// ...
self::REFUNDED => 'Remboursé',
```

#### ✅ `canBeSent()` amélioré
**Avant :** Ne permettait pas DRAFT → SENT  
**Après :** Permet DRAFT → SENT (avec émission automatique)

```php
public function canBeSent(): bool
{
    return !in_array($this, [self::CANCELLED, self::REFUNDED]);
}
```

#### ✅ `canBeCancelled()` amélioré
**Avant :** Seulement DRAFT et ISSUED  
**Après :** DRAFT, ISSUED et SENT

```php
public function canBeCancelled(): bool
{
    return in_array($this, [self::DRAFT, self::ISSUED, self::SENT]);
}
```

---

### 2. `src/Service/CreditNoteService.php`

#### ✅ Nouvelle méthode `issueAndSend()`
Raccourci pour émettre et envoyer en une seule action :
```php
public function issueAndSend(CreditNote $creditNote): void
{
    // Émettre d'abord (DRAFT → ISSUED)
    $this->issue($creditNote);
    
    // Puis envoyer (ISSUED → SENT)
    $this->send($creditNote);
}
```

#### ✅ Méthode `send()` améliorée
**Avant :** Ne gérait pas DRAFT  
**Après :** Émet automatiquement si DRAFT, puis envoie

```php
// Si DRAFT, émettre automatiquement avant d'envoyer
if ($status === CreditNoteStatus::DRAFT) {
    $this->issue($creditNote);
    $status = CreditNoteStatus::ISSUED; // Mettre à jour le statut après émission
}
```

#### ✅ Méthode `apply()` renommée
**Avant :** `apply()` → `APPLIED`  
**Après :** `apply()` → `REFUNDED` (sémantique plus claire)

---

### 3. `src/Security/Voter/CreditNoteVoter.php`

#### ✅ `canSend()` amélioré
**Avant :** Vérifiait manuellement les statuts  
**Après :** Utilise `canBeSent()` de l'enum

```php
private function canSend(CreditNote $creditNote, UserInterface $user, CreditNoteStatus $status): bool
{
    return $status->canBeSent();
}
```

#### ✅ `canCancel()` amélioré
**Avant :** Vérifiait manuellement DRAFT et ISSUED  
**Après :** Utilise `canBeCancelled()` de l'enum

```php
private function canCancel(CreditNote $creditNote, UserInterface $user, CreditNoteStatus $status): bool
{
    return $status->canBeCancelled();
}
```

---

### 4. `src/Controller/Admin/CreditNoteController.php`

#### ✅ Nouvelle route `issueAndSend()`
```php
#[Route('/{id}/issue-and-send', name: 'issue_and_send', requirements: ['id' => '\d+'], methods: ['POST'])]
#[IsGranted('CREDIT_NOTE_ISSUE', subject: 'creditNote')]
public function issueAndSend(Request $request, CreditNote $creditNote): Response
{
    // Émet et envoie en une seule action
    $this->creditNoteService->issueAndSend($creditNote);
    // ...
}
```

#### ✅ Méthode `sendEmail()` améliorée
**Avant :** Ne gérait pas l'émission automatique  
**Après :** Appelle `creditNoteService->send()` qui gère l'émission automatique

---

## 🎨 Modifications Frontend

### 1. `templates/components/EntityActions.html.twig`

#### ✅ Boutons spécifiques pour les avoirs
Ajout de boutons similaires aux factures :

- **Émettre** (DRAFT uniquement)
- **Émettre & Envoyer** (DRAFT uniquement, avec email)
- **Envoyer** (ISSUED uniquement, avec email)
- **Relancer** (SENT uniquement, avec email)
- **Rembourser** (ISSUED ou SENT) - renommé de "Appliquer"

#### ✅ Exclusion des avoirs du bouton générique
Les avoirs ne passent plus par le bouton "Envoyer" générique, ils ont leurs propres boutons spécifiques.

---

### 2. Templates mis à jour

#### ✅ `templates/admin/credit_note/show.html.twig`
- `applied` → `refunded` dans les conditions de statut

#### ✅ `templates/public/credit_note/view.html.twig`
- `applied` → `refunded` dans les conditions de statut
- Message : "Cet avoir a été appliqué !" → "Cet avoir a été remboursé !"

---

## 🔄 Workflow Final

```
DRAFT → [Émettre] → ISSUED → [Envoyer] → SENT → [Rembourser] → REFUNDED
    ↘️ [Émettre & Envoyer] ↗️
    ↘️ [Envoyer] (émet auto) ↗️
```

### Statuts et Actions

#### 📝 DRAFT (Brouillon)
- ✅ **Modifier** : Éditer le document
- ✅ **Émettre** : DRAFT → ISSUED (génère PDF + numéro)
- ✅ **Émettre & Envoyer** : DRAFT → ISSUED → SENT (en 1 clic)
- ✅ **Envoyer** : DRAFT → SENT (émet automatiquement puis envoie)
- ✅ **Annuler** : DRAFT → CANCELLED

#### 📄 ISSUED (Émis)
- ⚠️ **Document comptable immuable** (ne peut plus être modifié)
- ✅ **Envoyer** : ISSUED → SENT (+ email client)
- ✅ **Rembourser** : ISSUED → REFUNDED
- ✅ **Annuler** : ISSUED → CANCELLED

#### 📧 SENT (Envoyé)
- ⚠️ **Document comptable immuable** (ne peut plus être modifié)
- ✅ **Relancer** : Reste SENT (relance email)
- ✅ **Rembourser** : SENT → REFUNDED
- ✅ **Annuler** : SENT → CANCELLED

#### 💰 REFUNDED (Remboursé)
- ⚠️ **Statut final** (aucune action possible)

#### ❌ CANCELLED (Annulé)
- ⚠️ **Statut final** (aucune action possible)

---

## ✅ Résultats

### Améliorations UX
- ✅ **Workflow simplifié** : Possibilité d'émettre et envoyer en 1 clic
- ✅ **Terminologie claire** : "Remboursé" au lieu de "Appliqué"
- ✅ **Annulation flexible** : Possible depuis DRAFT, ISSUED ou SENT
- ✅ **Cohérence** : Même logique que les factures

### Conformité Comptable
- ✅ **Statut ISSUED conservé** : Document comptable légal
- ✅ **Traçabilité** : Date d'émission ≠ date d'envoi
- ✅ **Immuabilité** : Documents émis/envoyés non modifiables

---

## 📊 Comparaison Avant/Après

| Aspect | Avant | Après |
|--------|-------|-------|
| **Statut final** | APPLIED (peu clair) | REFUNDED (explicite) |
| **Émission + Envoi** | 2 actions séparées | 1 action "Émettre & Envoyer" |
| **Envoi depuis DRAFT** | ❌ Impossible | ✅ Possible (émet auto) |
| **Annulation depuis SENT** | ❌ Impossible | ✅ Possible |
| **Boutons** | Génériques | Spécifiques (comme factures) |

---

## 🎯 Conclusion

La Phase 4 (Avoirs) est terminée avec succès :
- ✅ Workflow simplifié et plus intuitif
- ✅ Terminologie clarifiée (REFUNDED)
- ✅ Annulation améliorée (DRAFT, ISSUED, SENT)
- ✅ Cohérence avec les factures
- ✅ Conformité comptable respectée

**Prochaine étape :** Phase 5 - Régénération automatique des PDF

