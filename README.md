# Delnyx

Delnyx est une plateforme freelance développée avec Symfony et Docker. Elle servira de vitrine professionnelle, de portfolio, et à terme de back-office pour la gestion client et de devis/factures.

---

## ✅ Stack actuelle

- PHP 8.3 / Symfony 7.3
- Twig (moteur de templates)
- PostgreSQL (via Docker)
- Docker + Docker Compose
- Tailwind CSS (via `symfonycasts/tailwind-bundle` + AssetMapper)

---

## 🚀 Installation & Lancement

### 1. Cloner le projet

```bash
git clone https://github.com/ton-utilisateur/delnyx.git
cd delnyx
```

### 2. Lancer les conteneurs

```bash
docker-compose up -d --build
```

### 3. Installer les dépendances PHP

```bash
docker exec -it delnyx-app composer install
```

### 4. Installer Tailwind CSS (via le bundle)

```bash
php bin/console tailwind:install
```

### 5. Lancer le watcher Tailwind (dans un autre terminal)

```bash
php bin/console tailwind:build --watch
```

---

## 🧪 Environnement

- Site accessible via : `http://localhost`
- Fichier CSS généré dans : `public/build/tailwind.css`
- Les vues Twig sont dans `templates/`

---

## 🛠️ À faire

- [ ] Créer le layout global avec Tailwind
- [ ] Intégrer la page statique générée avec Lovable
- [ ] Ajouter une CI/CD via GitHub Actions
- [ ] Ajouter une interface d’administration (back-office Symfony)

---

## 📦 Commandes utiles

```bash
# Lancer les conteneurs
docker-compose up -d

# Arrêter les conteneurs
docker-compose down

# Recompiler les assets Tailwind à la volée
php bin/console tailwind:build --watch
```
