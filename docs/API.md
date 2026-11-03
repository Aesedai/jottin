# Inbound API

Create a jott from another system, such as n8n, Make, Zapier or your own code. It runs the same hashtag and collection rules as the web app.

## Authentication

A workspace owner creates a key under **API keys**. The key is shown once, and only a hash of it is stored. Send it as a bearer token:

```
Authorization: Bearer jott_...
```

The key decides the workspace. Any tenant or account field in the request is ignored.

## `POST /v1/jotts`

### Request

| Field | Type | Required | Notes |
|---|---|---|---|
| `text` | string | Yes | Up to 10,000 characters, and not blank. Hashtags file the jott into collections. |
| `collections` | array of strings | No | Collection IDs to add the jott to manually. All must belong to this workspace. |

```bash
curl -X POST https://your-host/v1/jotts \
  -H "Authorization: Bearer $JOTTIN_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"text": "Follow up with Alex about #interview"}'
```

### Response: `201 Created`

```json
{
  "id": "4f1c2a9d8e7b6a5c4d3e2f10",
  "createdAt": "2026-11-03T18:35:00Z",
  "source": "api",
  "message": "Saved in interview.",
  "collections": [
    {"id": "9a8b7c6d5e4f3a2b1c0d9e8f", "name": "interview", "membership": "automatic"}
  ]
}
```

`message` is a short summary for tools that display the reply. `membership` is `automatic` (from a hashtag), `manual` (from `collections`) or `both`.

### Sending from a webhook tool

Any tool that can POST JSON and set a header works, and fields other than `text` and `collections` are ignored.

**Webhooker:** add a contact with `https://your-host/v1/jotts` as its URL and an `Authorization` header set to `Bearer jott_...`. Each message you send becomes a jott, and the reply bubble shows the `message` field.

### Errors

Errors are JSON: `{"error": {"code": "...", "message": "..."}}`. A failed request creates nothing.

| Status | `code` | Meaning |
|---|---|---|
| 400 | `invalid_json` | The body isn't a JSON object. |
| 401 | `unauthorized` | The key is missing, wrong or revoked. |
| 413 | `payload_too_large` | The body is over 64 KB. |
| 422 | `invalid_text` | `text` is missing, blank, too long or not a string. |
| 422 | `invalid_collections` | `collections` isn't an array. |
| 422 | `unknown_collection` | A collection ID doesn't exist in this workspace. |
| 429 | `rate_limited` | Over 60 requests a minute for this key. See `Retry-After`. |
