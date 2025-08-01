# Delnyx

Delnyx est une plateforme vitrine pour mon activité freelance, développée avec Symfony 7, conteneurisée via Docker, et auto-hébergée sur une VM locale (Freebox Delta).

## 🔧 Stack technique

- PHP 8.3 (FPM)
- Symfony 7.3
- Nginx (reverse proxy)
- PostgreSQL 15
- Docker / Docker Compose
- Certbot (Let's Encrypt)
- GitHub Actions (CI/CD – à venir)

## 🚀 Objectifs

- Vitrine de mes projets freelance (ex: Aqualize, Fish Tracker…)
- Gestion simplifiée des clients, devis, factures
- Admin minimal et design sobre

## 📦 Lancer le projet

```bash
docker-compose up -d --build
```

Le projet Symfony sera accessible sur :  
👉 http://localhost

## 📁 Arborescence

```
delnyx/
├── app/             # Code Symfony
├── nginx/           # Configuration Nginx
├── certbot/         # Pour Let's Encrypt
├── Dockerfile       # Image PHP personnalisée
├── docker-compose.yml
└── README.md
```

## 🗺️ Roadmap (prochaine étape)

- [ ] Créer un contrôleur `HomeController`
- [ ] Ajouter Twig et afficher un template statique
- [ ] Intégrer un premier design Lovable pour la page d’accueil
- [ ] Ajouter API Platform
- [ ] Mettre en place la CI/CD (GitHub Actions)
