# Ponos

Taaksysteem gekoppeld aan Business Central-projecten.

## Ontwikkeling

- Applicatie draait vanuit `web/`
- Tests: `php tests/run.php`
- Vereist `web/auth.php` (niet in git) en BC OData-toegang

## URL-structuur

`index.php?company=...&dept=...&project=...&task=...`

Deze link opent direct de juiste afdeling, project en taak.

## Machine API (Sec-Bot / integraties)

Endpoint: `ponos_api.php`

Ontdekking (geen auth):

```
GET ponos_api.php
GET ponos_api.php?action=help
GET ponos_api.php?action=spec
```

Geeft een machine-readable JSON-spec (Forum Magnum-stijl) van alle task-actions.

### Auth

Bots gebruiken een **vaste API-key**, niet de roterende dagelijkse login-key van analytics.

- Header: `X-API-Key: ponos_…`
- Of: `Authorization: Bearer ponos_…`
- Of query/body: `api_key`

De key hoort bij een gebruikers-e-mail. Groepstoegang en "Mijn Taken" volgen die gebruiker. De web-UI blijft via de Office365-sessie werken (`logincheck.php` wordt overgeslagen als een geldige API-key aanwezig is).

Keys worden als SHA-256-hash in `web/data/ponos/ponos.sqlite` opgeslagen. De plaintext wordt één keer getoond.

### Key aanmaken voor Sec-Bot

Op de server:

```
php web/ponos_api_key.php create tfalken@kvt.nl Sec-Bot
```

Bewaar de getoonde `ponos_…` key in de Grok-keystore. Daarna:

```
GET ponos_api.php?action=whoami
X-API-Key: ponos_…
```

Lijst/intrekken:

```
php web/ponos_api_key.php list tfalken@kvt.nl
php web/ponos_api_key.php revoke ID tfalken@kvt.nl
```

Ingelogd via de UI kan hetzelfde met `create_api_key` / `list_api_keys` / `revoke_api_key`.

### Taken organiseren

Bestaande Ponos-velden (geen parallel taskmodel):

| Sec-Bot | Ponos veld |
|---------|------------|
| titel | `title` |
| notities | `description` (+ `add_message` voor thread) |
| status | `status`: `todo`, `in_progress`, `done` |
| deadline | `due_date` (`YYYY-MM-DD`) |
| assignee | `assignee_email` |
| prioriteit | bestaat niet; gebruik `category_id` / `due_date` |

Afronden: `action=complete_task` of `update_status` met `status=done`. Archiveren gebeurt automatisch na de cutoff; ophalen via `list_archived_tasks`, terugzetten via `unarchive_task`.
