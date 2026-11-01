# CEA Hardware Eshop deployment

This directory is the shared deployment layer; the frontend and Laravel API remain separate repositories. It starts five containers on one host: Next.js, Nginx, Laravel FPM, a Laravel queue worker, and MySQL. Only Nginx publishes a port. MySQL, Laravel, and the queue worker are private to the Docker network.

## Local smoke test

Clone both repositories as siblings, then create the untracked environment file and generate an application key:

```sh
cd cea-hardware-eshop-backend/deploy
cp .env.example .env
docker compose run --rm api php artisan key:generate --show
# Put the generated value into APP_KEY in .env, then:
docker compose up --build
```

Open `http://localhost` and `http://localhost/health`. The latter confirms that Next.js can reach Laravel. Do not run `db:seed` against an existing database: the seed data is intended only for a new demo database.

For a fresh demonstration database, after the stack is up, run:

```sh
docker compose exec api php artisan db:seed
```

MySQL data and Laravel public storage use named volumes. `docker compose down` preserves them; `docker compose down --volumes` deletes them.

## AWS gate: verify before creating anything

The zero-out-of-pocket path is available only if the AWS console confirms that the account is eligible for the **Free account plan**. New accounts receive $100 in credits and can earn up to $100 more; the Free account plan closes after six months or when credits are exhausted, and does not bill you unless you upgrade. Existing accounts and paid-plan accounts need a separate cost decision first. Confirm the actual plan, credit balance, expiration date, and available EC2 options in the console before provisioning. AWS documents the [Free account plan](https://docs.aws.amazon.com/awsaccountbilling/latest/aboutv2/free-tier-plans.html) and [usage tracking](https://docs.aws.amazon.com/awsaccountbilling/latest/aboutv2/tracking-free-tier-usage.html).

For the first-week portfolio deployment, use exactly one x86 EC2 instance, one 30 GB gp3 root volume, and one public IPv4 address. Do **not** create an RDS database, NAT gateway, load balancer, Elastic IP, Route 53 hosted zone, or paid support plan. As a reference for `us-east-1`, a t3.micro is $0.0104/hour, 30 GB gp3 is about $2.40/month, and a public IPv4 address is normally $0.005/hour; prices, taxes, availability, and Free Tier coverage vary by account and region. AWS lists the current [EC2 T3 prices](https://aws.amazon.com/ec2/instance-types/t3/), [EBS prices](https://aws.amazon.com/ebs/pricing/), and [public IPv4 terms](https://aws.amazon.com/vpc/pricing/). This is a small portfolio environment, not production e-commerce infrastructure.

## EC2 bootstrap

1. Create the Free account plan only after the gate above is satisfied. Use an Amazon Linux 2023 x86 instance such as `t3.micro`, 30 GB gp3 storage, and the smallest region that the console confirms fits the plan. Restrict SSH (22) to your current IP; expose only HTTP (80) initially.
2. Install Docker and the Compose plugin on the instance. Clone both repositories into `/opt/cea-hardware-eshop` so their folder names remain `cea-hardware-eshop-next` and `cea-hardware-eshop-backend`.
3. Create `cea-hardware-eshop-backend/deploy/.env` from this directory's example, with strong distinct database passwords, a generated `APP_KEY`, real Stripe values only if payment is being tested, and `APP_URL`/`FRONTEND_URL` set to the public IP.
4. Run `docker compose up -d --build` from this directory, then check `docker compose ps`, `docker compose logs --tail=100`, and the `/health` page.
5. Do not put the database on the public network. Back up the named MySQL volume before experiments that can alter data.

HTTPS needs a domain you already control. Add DNS and a TLS configuration before using a real Stripe webhook; then change `APP_URL` and `FRONTEND_URL` to the HTTPS domain and configure Stripe to call `https://your-domain/api/stripe/webhook`.

## GitHub Actions deployment

Each repository verifies its own build. On a push to `main`, deployment is deliberately inactive until you set the repository variable `DEPLOY_ENABLED` to `true`. Before enabling it in both repositories, add these GitHub Actions secrets:

- `DEPLOY_HOST`: the EC2 public host or IP
- `DEPLOY_USER`: the EC2 login user
- `DEPLOY_SSH_KEY`: a dedicated private deployment key
- `DEPLOY_KNOWN_HOSTS`: the exact `ssh-keyscan -H` result captured from the trusted host during setup

Also add the repository variable `DEPLOY_ROOT` with `/opt/cea-hardware-eshop`. The EC2 host needs read-only GitHub access to the private frontend (a dedicated deploy key is the minimal option). The deployment workflows only pull the changed repository and rebuild its relevant service; secrets stay in `deploy/.env` on the host, never in GitHub source control.

## One-week checkpoint plan

1. Run the local stack and fix only container-specific problems.
2. Complete the AWS eligibility gate and create the single EC2 host.
3. Bootstrap the host and validate the public HTTP deployment.
4. Verify the application flow, queue, database persistence, and image storage.
5. Configure and test CI.
6. Add a domain/TLS only if you already own a domain; otherwise keep payment testing local.
7. Capture the architecture, deployment commands, and screenshots for the portfolio README.
