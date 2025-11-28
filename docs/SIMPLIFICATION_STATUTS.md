# 🔧 Simplification des Statuts - Workflow Minimaliste

## Statuts AVANT vs APRÈS

### ❌ AVANT (8 statuts - trop complexe)
```
DRAFT → ISSUED → SENT → ACCEPTED → SIGNED
         ↓        ↓         ↓         ↓
     CANCELLED  REFUSED  EXPIRED  (états finaux)
```

### ✅ APRÈS (6 statuts - simplifié)
```
DRAFT → SENT → SIGNED
  ↓       ↓       ↓
CANCELLED REFUSED EXPIRED
```

## Statuts SUPPRIMÉS

### 1. ISSUED (Émis) ❌
**Raison :** Redondant dans le workflow simplifié
- **Avant :** DRAFT → ISSUED → SENT
- **Après :** DRAFT → SENT (direct)
- **Légalement :** Pas obligatoire

### 2. ACCEPTED (Accepté) ❌
**Raison :** Doublon avec SIGNED
- En France, un devis "accepté" = "signé"
- Acceptation orale n'a pas de valeur juridique
- Seule la signature compte
- **Légalement :** Acceptation = Signature

## Statuts CONSERVÉS (Légalement Obligatoires)

### 1. DRAFT (Brouillon) ✅
- État initial
- Modifiable
- Pas de valeur juridique

### 2. SENT (Envoyé) ✅
- Devis transmis au client
- Date d'envoi enregistrée
- Traçabilité obligatoire

### 3. SIGNED (Signé) ✅
- **CONTRAT** légalement opposable
- Immuable (sauf avenant)
- Archivage 10 ans obligatoire
- **BASE LÉGALE :** Art. L441-3 Code de Commerce

### 4. REFUSED (Refusé) ✅
- Client a refusé le devis
- Traçabilité obligatoire
- Date de refus enregistrée

### 5. EXPIRED (Expiré) ✅
- Date de validité dépassée
- **BASE LÉGALE :** Un devis a une durée de validité (30 jours par défaut)
- Devient caduc automatiquement

### 6. CANCELLED (Annulé) ✅
- Annulation admin
- Raison d'annulation obligatoire
- Archivage 10 ans obligatoire

## Workflow Final

```
┌─────────┐
│  DRAFT  │ (modifiable)
└─────────┘
     │
     │ Envoyer
     ▼
┌─────────┐
│  SENT   │ (en attente)
└─────────┘
     │
     ├──→ Signer → SIGNED (CONTRAT)
     ├──→ Refuser → REFUSED
     ├──→ Annuler → CANCELLED
     └──→ Auto → EXPIRED (si date dépassée)
```

## Impacts Techniques

### Suppression de ISSUED
- ❌ `QuoteStatus::ISSUED`
- ❌ `canBeIssued()`
- ❌ Bouton "Émettre"
- ❌ Route `admin_quote_issue`
- ✅ La génération PDF se fait lors de l'envoi

### Suppression de ACCEPTED
- ❌ `QuoteStatus::ACCEPTED`
- ❌ `canBeAccepted()`
- ❌ `QuoteService::accept()`
- ❌ Bouton "Accepter"
- ❌ Route `admin_quote_accept`
- ✅ On passe directement de SENT à SIGNED

## Base Légale Française

### Ce qui est OBLIGATOIRE 📜
1. **Devis Signé = Contrat** (Art. L441-3 Code de Commerce)
2. **Archivage 10 ans** (Art. L123-22 Code de Commerce)
3. **Date de validité** (Usage commercial)
4. **Traçabilité** (envoi, refus, annulation)

### Ce qui est FACULTATIF
- ✅ Statut "Émis" (pas dans la loi)
- ✅ Statut "Accepté" (seule la signature compte)
- ✅ Étapes intermédiaires multiples

## Avantages de la Simplification

### Pour l'Utilisateur 👤
- ✅ Workflow plus rapide : 2 clics au lieu de 4
- ✅ Moins de confusion
- ✅ Interface plus claire

### Légalement 📜
- ✅ Conforme à la législation française
- ✅ Traçabilité complète
- ✅ Archivage obligatoire respecté

### Techniquement 💻
- ✅ Moins de code à maintenir
- ✅ Moins de routes/controllers
- ✅ Moins de bugs potentiels

---

**Conclusion :** Le workflow simplifié est **légalement valide** et **plus efficace**.

