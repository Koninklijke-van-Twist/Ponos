# Ponos

Taaksysteem gekoppeld aan Business Central-projecten.

## Ontwikkeling

- Applicatie draait vanuit `web/`
- Tests: `php tests/run.php`
- Vereist `web/auth.php` (niet in git).
- OData-reads proberen Mímir als `$mimirApi` gezet is. Faalt die aanroep, dan valt Ponos terug op het bestaande Business Central-pad (`$baseUrl`, `$environment`, `$auth_list`, `$auth`) en de lokale odata-filecache. Zonder `$mimirApi` blijft alleen dat BC-pad actief.

### Mímir in productie

Alleen in `web/auth.php`, niet committen. De BC-credentials blijven naast `$mimirApi` staan; die zijn de automatische fallback als Mímir niet bereikbaar is (web, `nightly.php` en andere CLI-scripts zoals `ponos_data.php` en `ponos_api_key.php`):

```php
$mimirApi  = 'mimir_…';
$mimirBase = 'https://sleutels.kvt.nl/mimir/api'; // optioneel; dit is de default

$auth_list = [
    'Production' => ['mode' => 'basic', 'user' => 'USERNAME', 'pass' => 'PASSWORD'],
];
$environment = 'Production';
$auth = $auth_list[$environment];
$baseUrl = 'https://my-bc-domain.com:7148/';
```

`$mimirApi` is verplicht om Mímir te activeren. OData-reads en company-discovery gaan eerst naar Mímir. Bij een cURL-fout, timeout, non-2xx, ongeldige JSON of een Mímir-foutpayload haalt Ponos dezelfde gegevens direct bij Business Central op en slaat Mímir voor de rest van dat PHP-proces over. Ontbreken de BC-credentials, dan komt de oorspronkelijke Mímir-fout terug.

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
- Of JSON/form body field `api_key` (niet in de querystring)

De key hoort bij een gebruikers-e-mail. Groepstoegang en "Mijn Taken" volgen die gebruiker. Optioneel veld `actor_name` (op de request, of als default op de key) is alleen een weergavenaam op berichten/activiteit; rechten blijven bij de key-eigenaar. Zonder veld blijft de naam van de eigenaar. Hover toont “Integratie van &lt;eigenaar&gt;”. De web-UI blijft via de Office365-sessie werken (`logincheck.php` wordt overgeslagen als een geldige API-key aanwezig is).

Keys worden als SHA-256-hash in `web/data/ponos/ponos.sqlite` opgeslagen. De plaintext wordt één keer getoond.

### Key aanmaken voor Sec-Bot

**Primair:** inloggen in Ponos → tandwiel (Instellingen) → **API-sleutels**. Daar kun je een sleutel aanmaken (plaintext één keer zichtbaar), bestaande sleutels zien (id, naam, datum — nooit opnieuw de volledige key) en intrekken. Bewaar de getoonde `ponos_…` key in de Grok-keystore.

Optioneel admin-fallback op de server (CLI):

```
php web/ponos_api_key.php create tfalken@kvt.nl Sec-Bot
php web/ponos_api_key.php list tfalken@kvt.nl
php web/ponos_api_key.php revoke ID tfalken@kvt.nl
```

Daarna:

```
GET ponos_api.php?action=whoami
X-API-Key: ponos_…
```

De UI gebruikt dezelfde session-auth acties `create_api_key` / `list_api_keys` / `revoke_api_key` (POST, geen key in de querystring).

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
