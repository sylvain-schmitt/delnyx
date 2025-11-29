# 📋 Workflow Facture - Conformité Légale Française

**Date :** 2025-11-27  
**Contexte :** Correction du workflow pour respecter la législation française

---

## ⚖️ Conformité Légale

### 📜 Article L441-9 Code de Commerce
> "La facture est exigible dès l'émission"

**Conséquence :**
- Une facture **émise (ISSUED)** est un **document comptable légal** et **immuable**
- Une facture **envoyée (SENT)** a été transmise au client et est **immuable**
- **Seule une facture DRAFT peut être annulée directement**

---

## 🔄 Workflow Correct

```
DRAFT → [Émettre] → ISSUED → [Envoyer] → SENT → [Marquer Payée] → PAID
  ↓
CANCELLED (annulation directe uniquement depuis DRAFT)
```

### Statuts et Actions

#### 📝 DRAFT (Brouillon)
- ✅ **Modifier** : Éditer le document
- ✅ **Émettre** : DRAFT → ISSUED (génère PDF + numéro)
- ✅ **Émettre & Envoyer** : DRAFT → ISSUED → SENT (en 1 clic)
- ✅ **Envoyer** : DRAFT → SENT (émet automatiquement puis envoie)
- ✅ **Annuler** : DRAFT → CANCELLED (annulation directe)

#### 📄 ISSUED (Émise)
- ⚠️ **Document légal immuable** (ne peut plus être modifié)
- ✅ **Envoyer** : ISSUED → SENT (+ email client)
- ✅ **Marquer Payée** : ISSUED → PAID
- ✅ **Créer Avoir** : Génère CreditNote (pour annulation partielle/totale)
- ❌ **Annuler** : ❌ **IMPOSSIBLE** (doit passer par un avoir total)

#### 📧 SENT (Envoyée)
- ⚠️ **Document légal immuable** (ne peut plus être modifié)
- ✅ **Relancer** : Reste SENT (relance email)
- ✅ **Marquer Payée** : SENT → PAID
- ✅ **Créer Avoir** : Génère CreditNote (pour annulation partielle/totale)
- ❌ **Annuler** : ❌ **IMPOSSIBLE** (doit passer par un avoir total)

#### 💰 PAID (Payée)
- ⚠️ **Document légal immuable** (ne peut plus être modifié)
- ✅ **Créer Avoir** : Génère CreditNote (pour remboursement/annulation)
- ❌ **Annuler** : ❌ **IMPOSSIBLE** (doit passer par un avoir total)

#### ❌ CANCELLED (Annulée)
- ⚠️ **Statut final** (aucune action possible)

---

## 🚫 Pourquoi une facture émise/envoyée ne peut pas être annulée ?

### Raison Légale
1. **Document comptable** : Une facture émise est un document comptable légal
2. **Traçabilité** : Obligation de conserver les factures 10 ans
3. **Opposabilité** : Une facture émise est opposable en justice

### Solution Légale : Créer un Avoir Total

Pour annuler une facture émise/envoyée/payée :
1. **Créer un avoir total** (CreditNote à 100% du montant)
2. L'avoir annule la facture comptablement
3. La facture passe en statut CANCELLED (automatiquement ou manuellement)

---

## 🔧 Comment modifier une facture émise/envoyée ?

### Option 1 : Annulation + Nouvelle Facture
1. Créer un avoir total pour annuler la facture incorrecte
2. Créer une nouvelle facture corrigée

### Option 2 : Avoir Partiel + Correction
1. Créer un avoir partiel pour la différence
2. Créer une nouvelle facture pour le montant correct

---

## ✅ Corrections Appliquées

### 1. `InvoiceStatus::canBeCancelled()`
**Avant :**
```php
public function canBeCancelled(): bool
{
    return in_array($this, [self::DRAFT, self::ISSUED]); // ❌ INCORRECT
}
```

**Après :**
```php
public function canBeCancelled(): bool
{
    return $this === self::DRAFT; // ✅ CORRECT
}
```

### 2. Bouton "Annuler" dans `EntityActions.html.twig`
Le bouton "Annuler" n'apparaît maintenant que pour les factures en statut **DRAFT**.

### 3. Workflow d'Annulation
- **DRAFT** → Annulation directe possible ✅
- **ISSUED/SENT/PAID** → Annulation via avoir total uniquement ✅

---

## 📊 Résumé des Actions par Statut

| Statut | Modifier | Émettre | Envoyer | Annuler | Créer Avoir | Marquer Payée |
|--------|----------|---------|---------|---------|-------------|---------------|
| **DRAFT** | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| **ISSUED** | ❌ | ❌ | ✅ | ❌ | ✅ | ✅ |
| **SENT** | ❌ | ❌ | ✅ (relance) | ❌ | ✅ | ✅ |
| **PAID** | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ |
| **CANCELLED** | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |

---

## 🎯 Conclusion

Le workflow respecte maintenant la législation française :
- ✅ Seule une facture DRAFT peut être annulée directement
- ✅ Les factures émises/envoyées sont immuables
- ✅ L'annulation d'une facture émise/envoyée passe par un avoir total
- ✅ Traçabilité et conformité comptable respectées

