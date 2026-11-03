# Outbound webhooks

Each collection can send its jotts to one URL. The workspace owner sets this under the collection's **Settings & history**.

## Modes

| Mode | What happens |
|---|---|
| **Disabled** | Nothing is sent. |
| **Manual** | A person selects jotts and confirms. A sent jott can't be sent again until someone re-arms it, whether the attempt succeeded or failed. |
| **Automatic** | Each new jott entering the collection is queued, and a worker sends it. Editing a jott or assigning it manually later doesn't send it again. |

## The request

A `POST` with a JSON body:

```json
{
  "event": "jott.delivery",
  "deliveryId": "4f1c2a9d8e7b6a5c4d3e2f10",
  "occurredAt": "2026-11-03T18:35:02Z",
  "collection": {"id": "9a8b7c6d5e4f3a2b1c0d9e8f", "name": "interview"},
  "jott": {"id": "1b2c3d4e5f6a7b8c9d0e1f2a", "text": "Prep examples for #interview", "createdAt": "2026-11-03T18:35:00Z"}
}
```

A `2xx` response counts as delivered. That means the receiver accepted the request, not that its own work finished. Delivery isn't guaranteed to be exactly once, so a receiver can use `deliveryId` to spot repeats.

**Limits:** 3 seconds to connect, 5 seconds in total. Redirects aren't followed, and the response body is discarded. Failed attempts are recorded and not retried.

## Verifying the signature

The signing secret is shown once, when the webhook is first saved. Each request includes:

| Header | Contents |
|---|---|
| `X-Jottin-Timestamp` | Unix time when it was signed |
| `X-Jottin-Signature` | `sha256=` followed by the HMAC-SHA256 of `{timestamp}.{raw body}` |
| `X-Jottin-Delivery` | The same value as `deliveryId` |

```php
$expected = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);

if (! hash_equals($expected, $signature) || abs(time() - (int) $timestamp) > 300) {
    http_response_code(401);
    exit;
}
```

Checking the timestamp stops an old captured request being replayed later.

## Protection against SSRF

A webhook could otherwise be aimed at internal systems, so:

- Only `http` and `https` are allowed, and URLs can't include credentials.
- A host is refused if any address it resolves to is private, loopback, link-local or reserved, which includes cloud metadata at `169.254.169.254`.
- Disguised forms of an IP address, such as `2130706433` and `0x7f.0.0.1`, are refused.
- The check runs again at send time, and the connection is pinned to the address that was checked, which defeats DNS rebinding.
- Redirects aren't followed, because one could point anywhere.

`JOTTIN_ALLOW_PRIVATE_WEBHOOKS=true` lets the local demo receiver be a target. It's only honored when `CI_ENVIRONMENT` is `development`.

## What's stored

URLs and signing secrets are encrypted at rest, because URLs often contain tokens. Only the host is kept readable, for the UI and history. History records the result, HTTP status and a short error summary, and never the note's text, the full URL or the response.

## Try it locally

The Compose stack includes a test receiver. Use one of these as the endpoint URL:

| URL | Behavior |
|---|---|
| `http://receiver/ok` | Returns 200 |
| `http://receiver/fail` | Returns 500 |
| `http://receiver/slow` | Takes 10 seconds, so the request times out |
| `http://receiver/redirect` | Returns a 302, which isn't followed |
