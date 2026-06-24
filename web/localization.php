<?php

/**
 * Constants
 */

const FLAG_SVGS = [
    'nl' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 900 600"><rect width="900" height="600" fill="#AE1C28"/><rect width="900" height="400" fill="#fff"/><rect width="900" height="200" fill="#fff"/><rect width="900" height="200" y="0" fill="#AE1C28"/><rect width="900" height="200" y="200" fill="#fff"/><rect width="900" height="200" y="400" fill="#21468B"/></svg>',
    'en' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 60 40"><clipPath id="a"><path d="M0 0v40h60V0z"/></clipPath><clipPath id="b"><path d="M30 20h30v20zv20H0zH0V0zV0h30z"/></clipPath><g clip-path="url(#a)"><path d="M0 0v40h60V0z" fill="#012169"/><path d="M0 0l60 40m0-40L0 40" stroke="#fff" stroke-width="8"/><path d="M0 0l60 40m0-40L0 40" clip-path="url(#b)" stroke="#C8102E" stroke-width="5"/><path d="M30 0v40M0 20h60" stroke="#fff" stroke-width="13"/><path d="M30 0v40M0 20h60" stroke="#C8102E" stroke-width="8"/></g></svg>',
    'de' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 5 3"><rect width="5" height="3" y="0" fill="#000"/><rect width="5" height="2" y="1" fill="#D00"/><rect width="5" height="1" y="2" fill="#FFCE00"/></svg>',
    'fr' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 900 600"><rect width="900" height="600" fill="#ED2939"/><rect width="600" height="600" fill="#fff"/><rect width="300" height="600" fill="#002395"/></svg>',
];

const SUPPORTED_LANGUAGES = [
    'nl' => ['flag' => '🇳🇱', 'label' => 'Nederlands'],
    'en' => ['flag' => '🇬🇧', 'label' => 'English'],
    'de' => ['flag' => '🇩🇪', 'label' => 'Deutsch'],
    'fr' => ['flag' => '🇫🇷', 'label' => 'Français'],
];

const LOCALE_BY_LANG = [
    'nl' => 'nl-NL',
    'en' => 'en-GB',
    'de' => 'de-DE',
    'fr' => 'fr-FR',
];

const TRANSLATIONS = [
    'nl' => [
        'lang.menu_aria' => 'Taal kiezen',
        'lang.switch_to' => 'Schakel naar %s',
        'app.title' => 'Ponos',
        'ponos.hero.title' => 'Ponos',
        'ponos.hero.subtitle' => 'Taken per project uit Business Central.',
        'ponos.label.company' => 'Bedrijf',
        'ponos.label.department' => 'Afdeling',
        'ponos.label.project' => 'Project',
        'ponos.sidebar.departments' => 'Afdelingen',
        'ponos.sidebar.no_department' => 'Zonder afdeling',
        'ponos.empty.select_project' => 'Kies een project in de sidebar om taken te bekijken.',
        'ponos.empty.no_tasks' => 'Nog geen taken in dit project.',
        'ponos.btn.new_task' => 'Nieuwe taak',
        'ponos.btn.save' => 'Opslaan',
        'ponos.btn.cancel' => 'Annuleren',
        'ponos.btn.edit' => 'Bewerken',
        'ponos.btn.send' => 'Versturen',
        'ponos.field.title' => 'Titel',
        'ponos.field.description' => 'Beschrijving',
        'ponos.field.category' => 'Categorie',
        'ponos.field.assignee' => 'Toegewezen aan',
        'ponos.field.due_date' => 'Deadline',
        'ponos.field.checklist' => 'Checklist',
        'ponos.field.checklist_add' => 'Subtaak toevoegen',
        'ponos.field.attachments' => 'Bijlagen',
        'ponos.field.message' => 'Bericht',
        'ponos.status.todo' => 'Nog te doen',
        'ponos.status.in_progress' => 'Onderhanden',
        'ponos.status.done' => 'Afgerond',
        'ponos.task.messages' => 'Berichten',
        'ponos.task.copy_link' => 'Link kopiëren',
        'ponos.task.link_copied' => 'Link gekopieerd',
        'ponos.error.load_failed' => 'Gegevens ophalen mislukt. Probeer het later opnieuw.',
        'ponos.error.save_failed' => 'Opslaan mislukt.',
        'ponos.error.task_not_found' => 'Taak niet gevonden.',
        'ponos.error.attachment_not_found' => 'Bijlage niet gevonden.',
        'ponos.error.missing_param' => 'Ontbrekende parameter: %s',
        'ponos.error.unknown_action' => 'Onbekende actie.',
        'ponos.system.task_created' => 'Taak aangemaakt: %s',
        'ponos.system.task_updated' => 'Taak bewerkt.',
        'ponos.system.changed_title' => 'Titel gewijzigd van "%s" naar "%s"',
        'ponos.system.changed_description' => 'Beschrijving gewijzigd',
        'ponos.system.changed_category' => 'Categorie gewijzigd van "%s" naar "%s"',
        'ponos.system.changed_assignee' => 'Toewijzing gewijzigd van %s naar %s',
        'ponos.system.changed_due_date' => 'Deadline gewijzigd van %s naar %s',
        'ponos.system.changed_checklist' => 'Checklist gewijzigd',
        'ponos.system.changed_status' => 'Status gewijzigd van %s naar %s',
        'elpis.hero.title' => 'Monitor Ontvangstrapport',
        'elpis.hero.subtitle' => 'Bekijk inkoopplanningsregels per project en projectmanager.',
        'elpis.label.company' => 'Bedrijf',
        'elpis.label.manager' => 'Projectmanager',
        'elpis.manager.all' => 'Iedereen',
        'elpis.section.projects' => 'Projecten',
        'elpis.label.search' => 'Zoeken',
        'elpis.placeholder.search' => 'Projectnr., werkorder, item of omschrijving',
        'elpis.label.line_search' => 'Zoeken in regels',
        'elpis.placeholder.line_search' => 'Werkorder, item of omschrijving',
        'elpis.empty.search' => 'Geen projecten gevonden voor deze zoekopdracht.',
        'elpis.meta.manager' => 'Projectmanager',
        'elpis.col.workorder' => 'Werkorder',
        'elpis.col.item' => 'Item',
        'elpis.col.description' => 'Description',
        'elpis.col.to_order' => 'Te bestellen',
        'elpis.col.ordered' => 'Besteld',
        'elpis.col.outstanding' => 'Openstaand',
        'elpis.col.received' => 'Ontvangen',
        'elpis.col.material_status' => 'Materiaalstatus',
        'elpis.col.expected_receipt' => 'Verwachte ontvangst',
        'elpis.material_status.O' => 'Onbekend',
        'elpis.material_status.N' => 'Niet nodig',
        'elpis.material_status.X' => 'Niet op tijd',
        'elpis.material_status.T' => 'Te laat',
        'elpis.material_status.I' => 'Inkooporder aanwezig',
        'elpis.material_status.V' => 'Voorraad',
        'elpis.material_status.G' => 'Gepicked',
        'elpis.material_status.B' => 'Uitgegeven',
        'elpis.material_status.A' => 'Aangenomen',
        'elpis.material_status.C' => 'Gecontroleerd',
        'elpis.empty.managers' => 'Geen projectmanagers gevonden',
        'elpis.empty.projects' => 'Geen projecten gevonden voor deze projectmanager.',
        'elpis.empty.lines' => 'Geen inkoopplanningsregels voor dit project.',
        'elpis.error.load_failed' => 'Gegevens ophalen mislukt. Probeer het later opnieuw.',
        'elpis.loader.wait' => 'Even geduld...',
        'elpis.loader.loading' => 'Gegevens ophalen uit Business Central',
    ],

    'en' => [
        'lang.menu_aria' => 'Choose language',
        'lang.switch_to' => 'Switch to %s',
        'app.title' => 'Ponos',
        'ponos.hero.title' => 'Ponos',
        'ponos.hero.subtitle' => 'Tasks per project from Business Central.',
        'ponos.label.company' => 'Company',
        'ponos.label.department' => 'Department',
        'ponos.label.project' => 'Project',
        'ponos.sidebar.departments' => 'Departments',
        'ponos.sidebar.no_department' => 'No department',
        'ponos.empty.select_project' => 'Select a project in the sidebar to view tasks.',
        'ponos.empty.no_tasks' => 'No tasks in this project yet.',
        'ponos.btn.new_task' => 'New task',
        'ponos.btn.save' => 'Save',
        'ponos.btn.cancel' => 'Cancel',
        'ponos.btn.edit' => 'Edit',
        'ponos.btn.send' => 'Send',
        'ponos.field.title' => 'Title',
        'ponos.field.description' => 'Description',
        'ponos.field.category' => 'Category',
        'ponos.field.assignee' => 'Assigned to',
        'ponos.field.due_date' => 'Due date',
        'ponos.field.checklist' => 'Checklist',
        'ponos.field.checklist_add' => 'Add subtask',
        'ponos.field.attachments' => 'Attachments',
        'ponos.field.message' => 'Message',
        'ponos.status.todo' => 'To do',
        'ponos.status.in_progress' => 'In progress',
        'ponos.status.done' => 'Done',
        'ponos.task.messages' => 'Messages',
        'ponos.task.copy_link' => 'Copy link',
        'ponos.task.link_copied' => 'Link copied',
        'ponos.error.load_failed' => 'Failed to load data. Please try again later.',
        'ponos.error.save_failed' => 'Save failed.',
        'ponos.error.task_not_found' => 'Task not found.',
        'ponos.error.attachment_not_found' => 'Attachment not found.',
        'ponos.error.missing_param' => 'Missing parameter: %s',
        'ponos.error.unknown_action' => 'Unknown action.',
        'ponos.system.task_created' => 'Task created: %s',
        'ponos.system.task_updated' => 'Task updated.',
        'ponos.system.changed_title' => 'Title changed from "%s" to "%s"',
        'ponos.system.changed_description' => 'Description changed',
        'ponos.system.changed_category' => 'Category changed from "%s" to "%s"',
        'ponos.system.changed_assignee' => 'Assignee changed from %s to %s',
        'ponos.system.changed_due_date' => 'Due date changed from %s to %s',
        'ponos.system.changed_checklist' => 'Checklist changed',
        'ponos.system.changed_status' => 'Status changed from %s to %s',
        'elpis.hero.title' => 'Receipt Monitor',
        'elpis.hero.subtitle' => 'View purchase planning lines per project and project manager.',
        'elpis.label.company' => 'Company',
        'elpis.label.manager' => 'Project manager',
        'elpis.manager.all' => 'Everyone',
        'elpis.section.projects' => 'Projects',
        'elpis.label.search' => 'Search',
        'elpis.placeholder.search' => 'Project no., work order, item or description',
        'elpis.label.line_search' => 'Search lines',
        'elpis.placeholder.line_search' => 'Work order, item or description',
        'elpis.empty.search' => 'No projects found for this search.',
        'elpis.meta.manager' => 'Project manager',
        'elpis.col.workorder' => 'Work order',
        'elpis.col.item' => 'Item',
        'elpis.col.description' => 'Description',
        'elpis.col.to_order' => 'To order',
        'elpis.col.ordered' => 'Ordered',
        'elpis.col.outstanding' => 'Outstanding',
        'elpis.col.received' => 'Received',
        'elpis.col.material_status' => 'Material status',
        'elpis.col.expected_receipt' => 'Expected receipt',
        'elpis.material_status.O' => 'Unknown',
        'elpis.material_status.N' => 'Not required',
        'elpis.material_status.X' => 'Not on time',
        'elpis.material_status.T' => 'Late',
        'elpis.material_status.I' => 'Purchase order present',
        'elpis.material_status.V' => 'Stock',
        'elpis.material_status.G' => 'Picked',
        'elpis.material_status.B' => 'Issued',
        'elpis.material_status.A' => 'Accepted',
        'elpis.material_status.C' => 'Checked',
        'elpis.empty.managers' => 'No project managers found',
        'elpis.empty.projects' => 'No projects found for this project manager.',
        'elpis.empty.lines' => 'No purchase planning lines for this project.',
        'elpis.error.load_failed' => 'Failed to load data. Please try again later.',
        'elpis.loader.wait' => 'Please wait...',
        'elpis.loader.loading' => 'Fetching data from Business Central',
    ],

    'de' => [
        'lang.menu_aria' => 'Sprache wählen',
        'lang.switch_to' => 'Wechseln zu %s',
        'app.title' => 'Ponos',
        'ponos.hero.title' => 'Ponos',
        'ponos.hero.subtitle' => 'Aufgaben pro Projekt aus Business Central.',
        'ponos.label.company' => 'Unternehmen',
        'ponos.label.department' => 'Abteilung',
        'ponos.label.project' => 'Projekt',
        'ponos.sidebar.departments' => 'Abteilungen',
        'ponos.sidebar.no_department' => 'Ohne Abteilung',
        'ponos.empty.select_project' => 'Wählen Sie ein Projekt in der Seitenleiste.',
        'ponos.empty.no_tasks' => 'Noch keine Aufgaben in diesem Projekt.',
        'ponos.btn.new_task' => 'Neue Aufgabe',
        'ponos.btn.save' => 'Speichern',
        'ponos.btn.cancel' => 'Abbrechen',
        'ponos.btn.edit' => 'Bearbeiten',
        'ponos.btn.send' => 'Senden',
        'ponos.field.title' => 'Titel',
        'ponos.field.description' => 'Beschreibung',
        'ponos.field.category' => 'Kategorie',
        'ponos.field.assignee' => 'Zugewiesen an',
        'ponos.field.due_date' => 'Fälligkeitsdatum',
        'ponos.field.checklist' => 'Checkliste',
        'ponos.field.checklist_add' => 'Unteraufgabe hinzufügen',
        'ponos.field.attachments' => 'Anhänge',
        'ponos.field.message' => 'Nachricht',
        'ponos.status.todo' => 'Offen',
        'ponos.status.in_progress' => 'In Bearbeitung',
        'ponos.status.done' => 'Erledigt',
        'ponos.task.messages' => 'Nachrichten',
        'ponos.task.copy_link' => 'Link kopieren',
        'ponos.task.link_copied' => 'Link kopiert',
        'ponos.error.load_failed' => 'Daten konnten nicht geladen werden.',
        'ponos.error.save_failed' => 'Speichern fehlgeschlagen.',
        'ponos.error.task_not_found' => 'Aufgabe nicht gefunden.',
        'ponos.error.attachment_not_found' => 'Anhang nicht gefunden.',
        'ponos.error.missing_param' => 'Fehlender Parameter: %s',
        'ponos.error.unknown_action' => 'Unbekannte Aktion.',
        'ponos.system.task_created' => 'Aufgabe erstellt: %s',
        'ponos.system.task_updated' => 'Aufgabe bearbeitet.',
        'ponos.system.changed_title' => 'Titel geändert von "%s" zu "%s"',
        'ponos.system.changed_description' => 'Beschreibung geändert',
        'ponos.system.changed_category' => 'Kategorie geändert von "%s" zu "%s"',
        'ponos.system.changed_assignee' => 'Zuweisung geändert von %s zu %s',
        'ponos.system.changed_due_date' => 'Fälligkeitsdatum geändert von %s zu %s',
        'ponos.system.changed_checklist' => 'Checkliste geändert',
        'ponos.system.changed_status' => 'Status geändert von %s zu %s',
        'elpis.hero.title' => 'Wareneingangsmonitor',
        'elpis.hero.subtitle' => 'Einkaufsplanungszeilen pro Projekt und Projektmanager anzeigen.',
        'elpis.label.company' => 'Unternehmen',
        'elpis.label.manager' => 'Projektmanager',
        'elpis.manager.all' => 'Alle',
        'elpis.section.projects' => 'Projekte',
        'elpis.label.search' => 'Suchen',
        'elpis.placeholder.search' => 'Projektnr., Arbeitsauftrag, Artikel oder Beschreibung',
        'elpis.label.line_search' => 'Zeilen suchen',
        'elpis.placeholder.line_search' => 'Arbeitsauftrag, Artikel oder Beschreibung',
        'elpis.empty.search' => 'Keine Projekte für diese Suche gefunden.',
        'elpis.meta.manager' => 'Projektmanager',
        'elpis.col.workorder' => 'Arbeitsauftrag',
        'elpis.col.item' => 'Artikel',
        'elpis.col.description' => 'Beschreibung',
        'elpis.col.to_order' => 'Zu bestellen',
        'elpis.col.ordered' => 'Bestellt',
        'elpis.col.outstanding' => 'Offen',
        'elpis.col.received' => 'Erhalten',
        'elpis.col.material_status' => 'Materialstatus',
        'elpis.col.expected_receipt' => 'Erwarteter Eingang',
        'elpis.material_status.O' => 'Unbekannt',
        'elpis.material_status.N' => 'Nicht erforderlich',
        'elpis.material_status.X' => 'Nicht rechtzeitig',
        'elpis.material_status.T' => 'Verspätet',
        'elpis.material_status.I' => 'Bestellung vorhanden',
        'elpis.material_status.V' => 'Lager',
        'elpis.material_status.G' => 'Kommissioniert',
        'elpis.material_status.B' => 'Ausgegeben',
        'elpis.material_status.A' => 'Angenommen',
        'elpis.material_status.C' => 'Geprüft',
        'elpis.empty.managers' => 'Keine Projektmanager gefunden',
        'elpis.empty.projects' => 'Keine Projekte für diesen Projektmanager gefunden.',
        'elpis.empty.lines' => 'Keine Einkaufsplanungszeilen für dieses Projekt.',
        'elpis.error.load_failed' => 'Daten konnten nicht geladen werden. Bitte später erneut versuchen.',
        'elpis.loader.wait' => 'Bitte warten...',
        'elpis.loader.loading' => 'Daten werden aus Business Central geladen',
    ],

    'fr' => [
        'lang.menu_aria' => 'Choisir la langue',
        'lang.switch_to' => 'Passer en %s',
        'app.title' => 'Ponos',
        'ponos.hero.title' => 'Ponos',
        'ponos.hero.subtitle' => 'Tâches par projet depuis Business Central.',
        'ponos.label.company' => 'Société',
        'ponos.label.department' => 'Département',
        'ponos.label.project' => 'Projet',
        'ponos.sidebar.departments' => 'Départements',
        'ponos.sidebar.no_department' => 'Sans département',
        'ponos.empty.select_project' => 'Choisissez un projet dans la barre latérale.',
        'ponos.empty.no_tasks' => 'Aucune tâche dans ce projet.',
        'ponos.btn.new_task' => 'Nouvelle tâche',
        'ponos.btn.save' => 'Enregistrer',
        'ponos.btn.cancel' => 'Annuler',
        'ponos.btn.edit' => 'Modifier',
        'ponos.btn.send' => 'Envoyer',
        'ponos.field.title' => 'Titre',
        'ponos.field.description' => 'Description',
        'ponos.field.category' => 'Catégorie',
        'ponos.field.assignee' => 'Assigné à',
        'ponos.field.due_date' => 'Échéance',
        'ponos.field.checklist' => 'Liste de contrôle',
        'ponos.field.checklist_add' => 'Ajouter une sous-tâche',
        'ponos.field.attachments' => 'Pièces jointes',
        'ponos.field.message' => 'Message',
        'ponos.status.todo' => 'À faire',
        'ponos.status.in_progress' => 'En cours',
        'ponos.status.done' => 'Terminé',
        'ponos.task.messages' => 'Messages',
        'ponos.task.copy_link' => 'Copier le lien',
        'ponos.task.link_copied' => 'Lien copié',
        'ponos.error.load_failed' => 'Échec du chargement des données.',
        'ponos.error.save_failed' => 'Échec de l\'enregistrement.',
        'ponos.error.task_not_found' => 'Tâche introuvable.',
        'ponos.error.attachment_not_found' => 'Pièce jointe introuvable.',
        'ponos.error.missing_param' => 'Paramètre manquant : %s',
        'ponos.error.unknown_action' => 'Action inconnue.',
        'ponos.system.task_created' => 'Tâche créée : %s',
        'ponos.system.task_updated' => 'Tâche modifiée.',
        'ponos.system.changed_title' => 'Titre modifié de "%s" à "%s"',
        'ponos.system.changed_description' => 'Description modifiée',
        'ponos.system.changed_category' => 'Catégorie modifiée de "%s" à "%s"',
        'ponos.system.changed_assignee' => 'Assignation modifiée de %s à %s',
        'ponos.system.changed_due_date' => 'Échéance modifiée de %s à %s',
        'ponos.system.changed_checklist' => 'Liste de contrôle modifiée',
        'ponos.system.changed_status' => 'Statut modifié de %s à %s',
        'elpis.hero.title' => 'Moniteur de réception',
        'elpis.hero.subtitle' => 'Consultez les lignes de planification d\'achat par projet et chef de projet.',
        'elpis.label.company' => 'Société',
        'elpis.label.manager' => 'Chef de projet',
        'elpis.manager.all' => 'Tous',
        'elpis.section.projects' => 'Projets',
        'elpis.label.search' => 'Rechercher',
        'elpis.placeholder.search' => 'N° projet, ordre de travail, article ou description',
        'elpis.label.line_search' => 'Rechercher dans les lignes',
        'elpis.placeholder.line_search' => 'Ordre de travail, article ou description',
        'elpis.empty.search' => 'Aucun projet trouvé pour cette recherche.',
        'elpis.meta.manager' => 'Chef de projet',
        'elpis.col.workorder' => 'Ordre de travail',
        'elpis.col.item' => 'Article',
        'elpis.col.description' => 'Description',
        'elpis.col.to_order' => 'À commander',
        'elpis.col.ordered' => 'Commandé',
        'elpis.col.outstanding' => 'En cours',
        'elpis.col.received' => 'Reçu',
        'elpis.col.material_status' => 'Statut matériel',
        'elpis.col.expected_receipt' => 'Réception prévue',
        'elpis.material_status.O' => 'Inconnu',
        'elpis.material_status.N' => 'Non requis',
        'elpis.material_status.X' => 'Pas à temps',
        'elpis.material_status.T' => 'En retard',
        'elpis.material_status.I' => 'Commande présente',
        'elpis.material_status.V' => 'Stock',
        'elpis.material_status.G' => 'Prélevé',
        'elpis.material_status.B' => 'Distribué',
        'elpis.material_status.A' => 'Accepté',
        'elpis.material_status.C' => 'Contrôlé',
        'elpis.empty.managers' => 'Aucun chef de projet trouvé',
        'elpis.empty.projects' => 'Aucun projet trouvé pour ce chef de projet.',
        'elpis.empty.lines' => 'Aucune ligne de planification d\'achat pour ce projet.',
        'elpis.error.load_failed' => 'Échec du chargement des données. Réessayez plus tard.',
        'elpis.loader.wait' => 'Veuillez patienter...',
        'elpis.loader.loading' => 'Récupération des données depuis Business Central',
    ],
];

/**
 * Functies
 */

function getUserPrefsPath(string $email): ?string
{
    $email = strtolower(trim($email));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return null;
    }
    $dir = __DIR__ . '/data/user_prefs';
    $filename = preg_replace('/[^a-z0-9._\-]/', '_', $email) . '.json';
    return $dir . '/' . $filename;
}

function loadUserPrefs(string $email): array
{
    $path = getUserPrefsPath($email);
    if ($path === null || !is_file($path)) {
        return [];
    }
    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function saveUserPref(string $email, string $key, mixed $value): void
{
    $path = getUserPrefsPath($email);
    if ($path === null) {
        return;
    }
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    $prefs = loadUserPrefs($email);
    $prefs[$key] = $value;
    file_put_contents($path, json_encode($prefs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function getCurrentLanguage(): string
{
    $lang = (string) ($_SESSION['lang'] ?? 'nl');
    return array_key_exists($lang, SUPPORTED_LANGUAGES) ? $lang : 'nl';
}

function getHtmlLang(): string
{
    return getCurrentLanguage();
}

function getDateLocale(): string
{
    $lang = getCurrentLanguage();
    return LOCALE_BY_LANG[$lang] ?? 'nl-NL';
}

/**
 * Geeft de vertaling voor $key in de actieve taal.
 * Extra $args worden via sprintf ingevoegd (voor %d, %s, etc.).
 */
function LOC(string $key, mixed ...$args): string
{
    $lang = getCurrentLanguage();
    $translations = TRANSLATIONS[$lang] ?? TRANSLATIONS['nl'];
    $string = $translations[$key] ?? (TRANSLATIONS['nl'][$key] ?? $key);

    return $args !== [] ? sprintf($string, ...$args) : $string;
}

function elpis_material_status_label(string $code): string
{
    $code = strtoupper(trim($code));
    if ($code === '') {
        return '';
    }

    $key = 'elpis.material_status.' . $code;
    $lang = getCurrentLanguage();

    if (isset(TRANSLATIONS[$lang][$key])) {
        return TRANSLATIONS[$lang][$key];
    }

    if (isset(TRANSLATIONS['nl'][$key])) {
        return TRANSLATIONS['nl'][$key];
    }

    return TRANSLATIONS['nl']['elpis.material_status.O'] ?? 'Onbekend';
}

function localizationFlagSvg(string $lang): string
{
    $svg = FLAG_SVGS[$lang] ?? '';
    if ($svg === '') {
        return '';
    }

    $safeLang = preg_replace('/[^a-z0-9]/', '', $lang) ?? $lang;
    return str_replace(
        ['id="a"', 'url(#a)', 'id="b"', 'url(#b)'],
        ['id="flag-' . $safeLang . '-a"', 'url(#flag-' . $safeLang . '-a)', 'id="flag-' . $safeLang . '-b"', 'url(#flag-' . $safeLang . '-b)'],
        $svg
    );
}

function localizationUrlWithLang(string $lang): string
{
    $params = $_GET;
    unset($params['lang']);
    $params['lang'] = $lang;
    $path = strtok((string) ($_SERVER['REQUEST_URI'] ?? ''), '?') ?: '';
    $query = http_build_query($params);
    return $path . ($query !== '' ? '?' . $query : '');
}

function localizationJsTranslations(array $keys): string
{
    $payload = [];
    foreach ($keys as $key) {
        $payload[$key] = LOC($key);
    }

    return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function renderLanguageSwitcherStyles(): void
{
    echo <<<'CSS'
<style>
.lang-switcher {
    position: fixed;
    top: 12px;
    right: 12px;
    z-index: 5000;
    font-family: inherit;
}
.lang-switcher-toggle {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 42px;
    height: 30px;
    padding: 0;
    border: 1px solid rgba(0, 82, 155, 0.25);
    border-radius: 6px;
    background: #ffffff;
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.12);
    cursor: pointer;
}
.lang-switcher-toggle:hover {
    background: #f2f9ff;
}
.lang-switcher-toggle svg {
    width: 28px;
    height: auto;
    display: block;
    border-radius: 2px;
    overflow: hidden;
}
.lang-switcher-menu {
    position: absolute;
    top: calc(100% + 6px);
    right: 0;
    min-width: 160px;
    margin: 0;
    padding: 6px;
    list-style: none;
    background: #ffffff;
    border: 1px solid #c9d7eb;
    border-radius: 10px;
    box-shadow: 0 12px 28px rgba(15, 23, 42, 0.18);
    display: none;
}
.lang-switcher.is-open .lang-switcher-menu {
    display: block;
}
.lang-switcher-item a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 10px;
    border-radius: 8px;
    color: var(--kvt-text, #1f2937);
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
}
.lang-switcher-item a:hover {
    background: #edf7ff;
}
.lang-switcher-item.is-active a {
    background: #e6f4ff;
}
.lang-switcher-item svg {
    width: 24px;
    height: auto;
    flex-shrink: 0;
    border-radius: 2px;
    overflow: hidden;
}
@media print {
    .lang-switcher {
        display: none !important;
    }
}
</style>
CSS;
}

function renderLanguageSwitcher(): void
{
    $current = getCurrentLanguage();
    $menuAria = htmlspecialchars(LOC('lang.menu_aria'), ENT_QUOTES);

    echo '<div class="lang-switcher" data-lang-switcher>';
    echo '<button type="button" class="lang-switcher-toggle" aria-haspopup="true" aria-expanded="false" aria-label="' . $menuAria . '">';
    echo localizationFlagSvg($current);
    echo '</button>';
    echo '<ul class="lang-switcher-menu" role="menu">';

    foreach (SUPPORTED_LANGUAGES as $code => $meta) {
        if ($code === $current) {
            continue;
        }

        $label = (string) ($meta['label'] ?? $code);
        $href = htmlspecialchars(localizationUrlWithLang($code), ENT_QUOTES);
        $title = htmlspecialchars(LOC('lang.switch_to', $label), ENT_QUOTES);

        echo '<li class="lang-switcher-item" role="none">';
        echo '<a role="menuitem" href="' . $href . '" title="' . $title . '">';
        echo localizationFlagSvg($code);
        echo '<span>' . htmlspecialchars($label) . '</span>';
        echo '</a>';
        echo '</li>';
    }

    echo '</ul>';
    echo '</div>';
}

function renderLanguageSwitcherScript(): void
{
    echo <<<'JS'
<script>
(function () {
    document.querySelectorAll('[data-lang-switcher]').forEach(function (root) {
        var toggle = root.querySelector('.lang-switcher-toggle');
        if (!toggle) {
            return;
        }

        toggle.addEventListener('click', function (event) {
            event.stopPropagation();
            var isOpen = root.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });

        document.addEventListener('click', function () {
            root.classList.remove('is-open');
            toggle.setAttribute('aria-expanded', 'false');
        });

        root.addEventListener('click', function (event) {
            event.stopPropagation();
        });
    });
})();
</script>
JS;
}

/**
 * Page load
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

if (!isset($_SESSION['lang'])) {
    $prefEmail = strtolower(trim((string) ($_SESSION['user']['email'] ?? '')));
    if ($prefEmail !== '') {
        $savedPrefs = loadUserPrefs($prefEmail);
        if (isset($savedPrefs['lang']) && array_key_exists($savedPrefs['lang'], SUPPORTED_LANGUAGES)) {
            $_SESSION['lang'] = $savedPrefs['lang'];
        }
    }
}

if (!isset($_SESSION['lang']) || !array_key_exists((string) $_SESSION['lang'], SUPPORTED_LANGUAGES)) {
    $_SESSION['lang'] = 'nl';
}

if (isset($_GET['lang']) && array_key_exists($_GET['lang'], SUPPORTED_LANGUAGES)) {
    $requestedLang = (string) $_GET['lang'];
    $langChanged = $requestedLang !== getCurrentLanguage();
    $_SESSION['lang'] = $requestedLang;
    $prefEmail = strtolower(trim((string) ($_SESSION['user']['email'] ?? '')));
    if ($prefEmail !== '' && $langChanged) {
        saveUserPref($prefEmail, 'lang', $requestedLang);
    }

    $isApiAction = isset($_GET['action']) && trim((string) $_GET['action']) !== '';
    if (!$isApiAction && strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'GET') {
        $params = $_GET;
        unset($params['lang']);
        $path = strtok((string) ($_SERVER['REQUEST_URI'] ?? ''), '?') ?: '';
        $query = http_build_query($params);
        header('Location: ' . $path . ($query !== '' ? '?' . $query : ''));
        exit;
    }
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
