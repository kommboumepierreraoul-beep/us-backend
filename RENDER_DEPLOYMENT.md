# Deploiement Render gratuit

## Type de service

- Backend Laravel : Web Service Docker
- Root directory : `us_backend`
- Dockerfile : `Dockerfile`
- Health check path : `/api/v1/health`

Le conteneur Apache ecoute automatiquement le port fourni par Render via `PORT`.

## Variables d'environnement importantes

Copier les variables depuis `.env.production` dans Render Dashboard > Environment.

Valeurs recommandees pour Render gratuit :

```env
APP_NAME=Us
APP_ENV=production
APP_DEBUG=false
APP_URL=https://us-backend-92uk.onrender.com

DB_CONNECTION=pgsql
DB_SSLMODE=require

CACHE_STORE=database
SESSION_DRIVER=database
QUEUE_CONNECTION=sync

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
MAIL_MAILER=brevo_api
BREVO_SENDER_EMAIL=kommboumepierreraoul@gmail.com
BREVO_SENDER_NAME=Us
VAPID_SUBJECT="${APP_URL}"
LOG_LEVEL=info
DEV_MAIL_TEST_TOKEN=une-valeur-longue-aleatoire
```

## Notes

- Ne pas commiter les secrets Render, Brevo, Google, Cloudinary ou VAPID.
- La base PostgreSQL gratuite Render peut expirer selon les conditions du plan gratuit.
- Le service gratuit dort apres inactivite, donc la premiere requete peut etre lente.
- Si vous ajoutez un worker payant plus tard, repasser `QUEUE_CONNECTION=database` et lancer un service worker avec `php artisan queue:work`.
- La route de test Brevo en production demande `DEV_MAIL_TEST_TOKEN` :
  `GET /api/v1/dev/brevo/test-email?token=...`
- Diagnostic sans secret :
  `GET /api/v1/dev/brevo/status`
