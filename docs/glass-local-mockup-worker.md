# Glass Local Mockup Worker

Glass custom mockups are rendered by the local workstation, while the website only creates and displays a shared database job. Each job records `job_uuid`, `product_id`, and `product_slug`; the Glass worker only claims `product_slug = glass` jobs and verifies the item and PSD owner before rendering.

## Local setup

1. Use a local checkout with the same `storage/app/public` files synchronized from the VPS, including PSD templates, Glass master images, and `generated/glass/mockups` outputs.
2. Configure that local checkout to connect to the same central database as the website. Do not expose the database publicly; use an existing private network, VPN, or an SSH tunnel.
3. Run the migration on the central database after deploying the code:

```bash
php artisan migrate --force
```

4. On the local workstation, start the worker:

```bash
php artisan glass:local-mockup-worker
```

The worker claims one `waiting` job at a time, renders the PSD locally, writes output image links to the central database, and marks the job completed. Use `--once` to process a single job for testing.
