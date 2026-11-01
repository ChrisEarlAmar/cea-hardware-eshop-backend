# CEA Hardware Eshop API

The Laravel API, database migrations, queue worker, and shared deployment layer for CEA Hardware Eshop. The Next.js storefront stays in its own repository; keep both repositories as sibling folders when using Docker Compose.

## Local development

Use PHP 8.2 or later, Composer, Node.js 22, and a MySQL database. Configure `.env` from `.env.example`, then install and run the application:

```bash
composer install
npm ci
php artisan key:generate
php artisan migrate
composer run dev
```

`composer run dev` starts Laravel, the queue listener, log viewer, and Vite. Point the frontend's `LARAVEL_API_URL` at this API when developing outside Docker.

## Verify and build

```bash
composer test
npm run build
docker build -t cea-hardware-eshop-api:test .
```

## Deployment

The [deployment runbook](deploy/README.md) is the source of truth for the two-repository Docker Compose stack, Nginx routing, AWS Free plan eligibility check, EC2 bootstrap, and the disabled-by-default GitHub Actions deployment step. Do not commit `deploy/.env` or other real credentials.
