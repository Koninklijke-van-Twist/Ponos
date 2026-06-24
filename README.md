# Ponos

Taaksysteem gekoppeld aan Business Central-projecten.

## Ontwikkeling

- Applicatie draait vanuit `web/`
- Tests: `php tests/run.php`
- Vereist `web/auth.php` (niet in git) en BC OData-toegang

## URL-structuur

`index.php?company=...&dept=...&project=...&task=...`

Deze link opent direct de juiste afdeling, project en taak.
