# Deploying to Google Cloud

**This design hasn't been deployed or tested on Google Cloud.** The live site runs on AWS Lightsail (see `docs/DEPLOY-AWS.md`), and this describes how the same production image would map to Google Cloud.

## Design

| Component | Service | Notes |
|---|---|---|
| **Web app** | Cloud Run, from the `prod` image target | Containers are stateless, because sessions are in MySQL |
| **Database** | Cloud SQL for MySQL 8 | Needs the `log_bin_trust_function_creators` flag for the audit triggers |
| **Worker** | Cloud Run Job on Cloud Scheduler | Runs `php spark deliveries:run`, with no `--loop` |
| **Migrations** | Cloud Run Job, run during deployment | Runs `php spark migrate --all` |
| **Secrets** | Secret Manager | Database password and `ENCRYPTION_KEY` |

## Environment

```
CI_ENVIRONMENT=production
app_baseURL=https://jottin.app/
app_forceGlobalSecureRequests=true
database_default_hostname=...
database_default_database=...
database_default_username=...
database_default_password=...   (from Secret Manager)
encryption_key=...              (from Secret Manager)
```

Never set `JOTTIN_ALLOW_PRIVATE_WEBHOOKS`. It's ignored outside development anyway.

## What would change for real production

- **Rate limits:** the throttler uses the local file cache, so each instance counts separately. A shared cache such as Memorystore would be needed.
- **Client IPs:** behind Google's load balancer, `JOTTIN_TRUSTED_PROXY` must name its address range, or every client looks like one address and rate limits misfire.
- **Logs:** CodeIgniter logs to `writable/logs`, which disappears with the container, so they should go to stdout for Cloud Logging.
- **Scheduling:** Cloud Scheduler's one-minute minimum makes automatic delivery slower than the local 2-second loop.
- **Webhook egress:** requests should leave through a restricted network path, as a second layer behind the SSRF checks.
- **Backups:** Cloud SQL automated backups and a tested restore procedure.

## Regulated data

If this held health data, I'd also look at a BAA with the cloud provider, customer-managed keys, private IP for Cloud SQL, shipping the audit log somewhere the app can't alter, and retention rules for notes and history.
