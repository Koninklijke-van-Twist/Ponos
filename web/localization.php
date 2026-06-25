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
        'ponos.hero.subtitle' => 'Taken per groep.',
        'ponos.label.company' => 'Bedrijf',
        'ponos.label.group' => 'Groep',
        'ponos.label.category' => 'Categorie',
        'ponos.sidebar.groups' => 'Groepen',
        'ponos.group.my_tasks' => 'Mijn Taken',
        'ponos.group.my_tasks_hint' => 'Alle taken die aan jou zijn toegewezen',
        'ponos.empty.select_group' => 'Kies een groep in de sidebar om taken te bekijken.',
        'ponos.empty.no_groups' => 'Nog geen groepen. Maak er een aan als admin.',
        'ponos.empty.no_tasks' => 'Nog geen taken in deze groep.',
        'ponos.btn.new_task' => 'Nieuwe taak',
        'ponos.btn.new_group' => 'Nieuwe groep',
        'ponos.btn.new_category' => 'Nieuwe categorie',
        'ponos.btn.delete' => 'Verwijderen',
        'ponos.btn.save' => 'Opslaan',
        'ponos.btn.cancel' => 'Annuleren',
        'ponos.btn.edit' => 'Bewerken',
        'ponos.btn.send' => 'Versturen',
        'ponos.btn.archive' => 'Archief',
        'ponos.btn.unarchive' => 'Uit archief halen',
        'ponos.btn.prev_page' => 'Vorige',
        'ponos.btn.next_page' => 'Volgende',
        'ponos.field.title' => 'Titel',
        'ponos.field.description' => 'Beschrijving',
        'ponos.field.group' => 'Groep',
        'ponos.field.assignee' => 'Toegewezen aan',
        'ponos.field.due_date' => 'Deadline',
        'ponos.field.checklist' => 'Checklist',
        'ponos.field.checklist_add' => 'Subtaak toevoegen',
        'ponos.field.attachments' => 'Bijlagen',
        'ponos.field.message' => 'Bericht',
        'ponos.field.category' => 'Categorie',
        'ponos.category.uncategorized' => 'Ongecategoriseerd',
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
        'ponos.error.admin_required' => 'Alleen admins mogen groepen beheren.',
        'ponos.error.group_not_found' => 'Groep niet gevonden.',
        'ponos.error.cannot_create_in_my_tasks' => 'Taken kunnen niet worden aangemaakt in Mijn Taken.',
        'ponos.prompt.new_group' => 'Naam van de nieuwe groep:',
        'ponos.prompt.rename_group' => 'Nieuwe naam voor de groep:',
        'ponos.group.new_title' => 'Nieuwe groep',
        'ponos.group.rename_title' => 'Groep hernoemen',
        'ponos.category.admin_title' => 'Categorieën beheren',
        'ponos.category.new_title' => 'Nieuwe categorie',
        'ponos.category.rename_title' => 'Categorie hernoemen',
        'ponos.group.admin_title' => 'Groep beheren',
        'ponos.group.tab.categories' => 'Categorieën',
        'ponos.group.tab.access' => 'Toegang',
        'ponos.access.everyone' => 'Iedereen heeft toegang',
        'ponos.access.members' => 'Leden',
        'ponos.access.add_member' => 'Gebruiker toevoegen',
        'ponos.access.remove_member' => 'Verwijderen',
        'ponos.empty.no_members' => 'Nog geen leden toegevoegd.',
        'ponos.empty.no_categories' => 'Nog geen categorieën in deze groep.',
        'ponos.empty.no_archived_tasks' => 'Geen gearchiveerde taken.',
        'ponos.archive.title' => 'Archief',
        'ponos.error.cannot_remove_own_access' => 'Je kunt je eigen toegang tot deze groep niet verwijderen.',
        'ponos.error.category_not_found' => 'Categorie niet gevonden.',
        'ponos.system.changed_category' => 'Categorie gewijzigd van %s naar %s',
        'ponos.group.delete_confirm_title' => 'Groep verwijderen?',
        'ponos.group.delete_confirm_message' => 'Er staan nog taken in dit project die permanent verwijderd worden als je deze groep verwijdert. Weet je het zeker?',
        'ponos.group.delete_confirm_yes' => 'Ja, verwijder alles',
        'ponos.group.delete_confirm_no' => 'Annuleren',
        'ponos.system.task_created' => 'Taak aangemaakt: %s',
        'ponos.system.task_updated' => 'Taak bewerkt.',
        'ponos.system.changed_title' => 'Titel gewijzigd van "%s" naar "%s"',
        'ponos.system.changed_description' => 'Beschrijving gewijzigd',
        'ponos.system.moved_to_group' => 'Taak verplaatst naar groep %s',
        'ponos.system.cleared_assignee_on_move' => 'Toewijzing verwijderd: geen toegang tot nieuwe groep',
        'ponos.system.changed_assignee' => 'Toewijzing gewijzigd van %s naar %s',
        'ponos.system.changed_due_date' => 'Deadline gewijzigd van %s naar %s',
        'ponos.system.changed_checklist' => 'Checklist gewijzigd',
        'ponos.system.changed_status' => 'Status gewijzigd van %s naar %s',
        'ponos.system.reminder_sent' => 'E-mailherinnering verstuurd naar %s',
        'ponos.system.unarchived' => 'Taak uit archief gehaald',
        'ponos.reminder.confirm' => 'Wil je %s per e-mail herinneren aan deze taak?',
        'ponos.reminder.yes' => 'Ja',
        'ponos.reminder.yes_always' => 'Ja (niet meer vragen)',
        'ponos.reminder.no' => 'Nee',
        'ponos.reminder.sent' => 'E-mailherinnering verstuurd.',
        'ponos.error.reminder_rate_limited' => 'Deze taak kan pas over een uur opnieuw worden herinnerd.',
        'ponos.error.reminder_send_failed' => 'E-mailherinnering versturen mislukt. Probeer het later opnieuw.',
        'ponos.settings.title' => 'E-mailvoorkeuren',
        'ponos.settings.assigned' => 'Toegewezen aan taak',
        'ponos.settings.status_changed' => 'Taakstatus veranderd',
        'ponos.settings.message' => 'Bericht op mijn taak geplaatst',
        'ponos.settings.checklist' => 'Checklist op een aan mij toegewezen taak veranderd',
        'ponos.settings.daily_reminder' => 'Dagelijkse herinnering bij taken met deadline vandaag',
        'ponos.settings.hint' => 'Je ontvangt nooit e-mails over acties die je zelf hebt uitgevoerd.',
        'ponos.pin.pin' => 'Groep vastpinnen',
        'ponos.pin.unpin' => 'Groep losmaken',
        'ponos.unread.badge' => '%d ongelezen berichten',
        'ponos.email.subject.assigned' => 'Ponos: taak toegewezen — %s',
        'ponos.email.body.assigned' => "Je bent toegewezen aan de taak \"%s\" in groep %s.\n\nOpen de taak: %s",
        'ponos.email.subject.status' => 'Ponos: status gewijzigd — %s',
        'ponos.email.body.status' => "De status van \"%s\" is gewijzigd van %s naar %s (groep %s).\n\nOpen de taak: %s",
        'ponos.email.subject.message' => 'Ponos: nieuw bericht — %s',
        'ponos.email.body.message' => "Nieuw bericht op \"%s\" van %s:\n%s\n\nGroep: %s\nOpen de taak: %s",
        'ponos.email.subject.checklist' => 'Ponos: checklist gewijzigd — %s',
        'ponos.email.body.checklist' => "De checklist van \"%s\" is gewijzigd (groep %s).\n\nOpen de taak: %s",
        'ponos.email.subject.daily_reminder' => 'Ponos: taken met deadline vandaag',
        'ponos.email.body.daily_reminder' => "De volgende taken hebben vandaag een deadline:\n\n%s",
        'ponos.email.intro.assigned' => 'Je bent toegewezen aan de taak "%s" in groep %s.',
        'ponos.email.intro.status' => 'De status van "%s" is gewijzigd van %s naar %s (groep %s).',
        'ponos.email.intro.message' => 'Nieuw bericht op "%s" van %s: %s (groep %s).',
        'ponos.email.intro.checklist' => 'De checklist van "%s" is gewijzigd (groep %s).',
        'ponos.email.intro.daily_reminder' => 'Je hebt %d taak/taken met een deadline vandaag:',
        'ponos.email.subject.reminder' => 'Ponos: herinnering — %s',
        'ponos.email.intro.reminder' => '%s vraagt je om de taak "%s" op te pakken (groep %s).',
        'ponos.email.footer' => 'Klik op een taakkaart om de taak in Ponos te openen.',
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
        'ponos.hero.subtitle' => 'Tasks per group.',
        'ponos.label.company' => 'Company',
        'ponos.label.group' => 'Group',
        'ponos.label.category' => 'Category',
        'ponos.sidebar.groups' => 'Groups',
        'ponos.group.my_tasks' => 'My Tasks',
        'ponos.group.my_tasks_hint' => 'All tasks assigned to you',
        'ponos.empty.select_group' => 'Select a group in the sidebar to view tasks.',
        'ponos.empty.no_groups' => 'No groups yet. Create one as an admin.',
        'ponos.empty.no_tasks' => 'No tasks in this group yet.',
        'ponos.btn.new_task' => 'New task',
        'ponos.btn.new_group' => 'New group',
        'ponos.btn.new_category' => 'New category',
        'ponos.btn.delete' => 'Delete',
        'ponos.btn.save' => 'Save',
        'ponos.btn.cancel' => 'Cancel',
        'ponos.btn.edit' => 'Edit',
        'ponos.btn.send' => 'Send',
        'ponos.btn.archive' => 'Archive',
        'ponos.btn.unarchive' => 'Restore from archive',
        'ponos.btn.prev_page' => 'Previous',
        'ponos.btn.next_page' => 'Next',
        'ponos.field.title' => 'Title',
        'ponos.field.description' => 'Description',
        'ponos.field.group' => 'Group',
        'ponos.field.assignee' => 'Assigned to',
        'ponos.field.due_date' => 'Due date',
        'ponos.field.checklist' => 'Checklist',
        'ponos.field.checklist_add' => 'Add subtask',
        'ponos.field.attachments' => 'Attachments',
        'ponos.field.message' => 'Message',
        'ponos.field.category' => 'Category',
        'ponos.category.uncategorized' => 'Uncategorized',
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
        'ponos.error.admin_required' => 'Only admins can manage groups.',
        'ponos.error.group_not_found' => 'Group not found.',
        'ponos.error.cannot_create_in_my_tasks' => 'Tasks cannot be created in My Tasks.',
        'ponos.prompt.new_group' => 'Name of the new group:',
        'ponos.prompt.rename_group' => 'New name for the group:',
        'ponos.group.new_title' => 'New group',
        'ponos.group.rename_title' => 'Rename group',
        'ponos.category.admin_title' => 'Manage categories',
        'ponos.category.new_title' => 'New category',
        'ponos.category.rename_title' => 'Rename category',
        'ponos.group.admin_title' => 'Manage group',
        'ponos.group.tab.categories' => 'Categories',
        'ponos.group.tab.access' => 'Access',
        'ponos.access.everyone' => 'Everyone has access',
        'ponos.access.members' => 'Members',
        'ponos.access.add_member' => 'Add user',
        'ponos.access.remove_member' => 'Remove',
        'ponos.empty.no_members' => 'No members added yet.',
        'ponos.empty.no_categories' => 'No categories in this group yet.',
        'ponos.empty.no_archived_tasks' => 'No archived tasks.',
        'ponos.archive.title' => 'Archive',
        'ponos.error.cannot_remove_own_access' => 'You cannot remove your own access to this group.',
        'ponos.error.category_not_found' => 'Category not found.',
        'ponos.system.changed_category' => 'Category changed from %s to %s',
        'ponos.group.delete_confirm_title' => 'Delete group?',
        'ponos.group.delete_confirm_message' => 'There are still tasks in this project that will be permanently deleted if you delete this group. Are you sure?',
        'ponos.group.delete_confirm_yes' => 'Yes, delete everything',
        'ponos.group.delete_confirm_no' => 'Cancel',
        'ponos.system.task_created' => 'Task created: %s',
        'ponos.system.task_updated' => 'Task updated.',
        'ponos.system.changed_title' => 'Title changed from "%s" to "%s"',
        'ponos.system.changed_description' => 'Description changed',
        'ponos.system.moved_to_group' => 'Task moved to group %s',
        'ponos.system.cleared_assignee_on_move' => 'Assignee cleared: no access to target group',
        'ponos.system.changed_assignee' => 'Assignee changed from %s to %s',
        'ponos.system.changed_due_date' => 'Due date changed from %s to %s',
        'ponos.system.changed_checklist' => 'Checklist changed',
        'ponos.system.changed_status' => 'Status changed from %s to %s',
        'ponos.system.reminder_sent' => 'Email reminder sent to %s',
        'ponos.system.unarchived' => 'Task restored from archive',
        'ponos.reminder.confirm' => 'Do you want to remind %s about this task by email?',
        'ponos.reminder.yes' => 'Yes',
        'ponos.reminder.yes_always' => 'Yes (don\'t ask again)',
        'ponos.reminder.no' => 'No',
        'ponos.reminder.sent' => 'Email reminder sent.',
        'ponos.error.reminder_rate_limited' => 'This task can only be reminded again after one hour.',
        'ponos.error.reminder_send_failed' => 'Failed to send email reminder. Please try again later.',
        'ponos.settings.title' => 'Email preferences',
        'ponos.settings.assigned' => 'Assigned to a task',
        'ponos.settings.status_changed' => 'Task status changed',
        'ponos.settings.message' => 'Message posted on my task',
        'ponos.settings.checklist' => 'Checklist changed on a task assigned to me',
        'ponos.settings.daily_reminder' => 'Daily reminder for tasks due today',
        'ponos.settings.hint' => 'You never receive emails about actions you performed yourself.',
        'ponos.pin.pin' => 'Pin group',
        'ponos.pin.unpin' => 'Unpin group',
        'ponos.unread.badge' => '%d unread messages',
        'ponos.email.subject.assigned' => 'Ponos: task assigned — %s',
        'ponos.email.body.assigned' => "You have been assigned to task \"%s\" in group %s.\n\nOpen task: %s",
        'ponos.email.subject.status' => 'Ponos: status changed — %s',
        'ponos.email.body.status' => "Status of \"%s\" changed from %s to %s (group %s).\n\nOpen task: %s",
        'ponos.email.subject.message' => 'Ponos: new message — %s',
        'ponos.email.body.message' => "New message on \"%s\" from %s:\n%s\n\nGroup: %s\nOpen task: %s",
        'ponos.email.subject.checklist' => 'Ponos: checklist changed — %s',
        'ponos.email.body.checklist' => "The checklist of \"%s\" was changed (group %s).\n\nOpen task: %s",
        'ponos.email.subject.daily_reminder' => 'Ponos: tasks due today',
        'ponos.email.body.daily_reminder' => "The following tasks are due today:\n\n%s",
        'ponos.email.intro.assigned' => 'You have been assigned to task "%s" in group %s.',
        'ponos.email.intro.status' => 'Status of "%s" changed from %s to %s (group %s).',
        'ponos.email.intro.message' => 'New message on "%s" from %s: %s (group %s).',
        'ponos.email.intro.checklist' => 'The checklist of "%s" was changed (group %s).',
        'ponos.email.intro.daily_reminder' => 'You have %d task(s) due today:',
        'ponos.email.subject.reminder' => 'Ponos: reminder — %s',
        'ponos.email.intro.reminder' => '%s asks you to pick up task "%s" (group %s).',
        'ponos.email.footer' => 'Click a task card to open the task in Ponos.',
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
        'ponos.hero.subtitle' => 'Aufgaben pro Gruppe.',
        'ponos.label.company' => 'Unternehmen',
        'ponos.label.group' => 'Gruppe',
        'ponos.label.category' => 'Kategorie',
        'ponos.sidebar.groups' => 'Gruppen',
        'ponos.group.my_tasks' => 'Meine Aufgaben',
        'ponos.group.my_tasks_hint' => 'Alle dir zugewiesenen Aufgaben',
        'ponos.empty.select_group' => 'Wählen Sie eine Gruppe in der Seitenleiste.',
        'ponos.empty.no_groups' => 'Noch keine Gruppen. Als Admin eine anlegen.',
        'ponos.empty.no_tasks' => 'Noch keine Aufgaben in dieser Gruppe.',
        'ponos.btn.new_task' => 'Neue Aufgabe',
        'ponos.btn.new_group' => 'Neue Gruppe',
        'ponos.btn.new_category' => 'Neue Kategorie',
        'ponos.btn.delete' => 'Löschen',
        'ponos.btn.save' => 'Speichern',
        'ponos.btn.cancel' => 'Abbrechen',
        'ponos.btn.edit' => 'Bearbeiten',
        'ponos.btn.send' => 'Senden',
        'ponos.btn.archive' => 'Archiv',
        'ponos.btn.unarchive' => 'Aus Archiv holen',
        'ponos.btn.prev_page' => 'Zurück',
        'ponos.btn.next_page' => 'Weiter',
        'ponos.field.title' => 'Titel',
        'ponos.field.description' => 'Beschreibung',
        'ponos.field.group' => 'Gruppe',
        'ponos.field.assignee' => 'Zugewiesen an',
        'ponos.field.due_date' => 'Fälligkeitsdatum',
        'ponos.field.checklist' => 'Checkliste',
        'ponos.field.checklist_add' => 'Unteraufgabe hinzufügen',
        'ponos.field.attachments' => 'Anhänge',
        'ponos.field.message' => 'Nachricht',
        'ponos.field.category' => 'Kategorie',
        'ponos.category.uncategorized' => 'Unkategorisiert',
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
        'ponos.error.admin_required' => 'Nur Admins dürfen Gruppen verwalten.',
        'ponos.error.group_not_found' => 'Gruppe nicht gefunden.',
        'ponos.error.cannot_create_in_my_tasks' => 'Aufgaben können nicht in Meine Aufgaben erstellt werden.',
        'ponos.prompt.new_group' => 'Name der neuen Gruppe:',
        'ponos.prompt.rename_group' => 'Neuer Name für die Gruppe:',
        'ponos.group.new_title' => 'Neue Gruppe',
        'ponos.group.rename_title' => 'Gruppe umbenennen',
        'ponos.category.admin_title' => 'Kategorien verwalten',
        'ponos.category.new_title' => 'Neue Kategorie',
        'ponos.category.rename_title' => 'Kategorie umbenennen',
        'ponos.group.admin_title' => 'Gruppe verwalten',
        'ponos.group.tab.categories' => 'Kategorien',
        'ponos.group.tab.access' => 'Zugriff',
        'ponos.access.everyone' => 'Jeder hat Zugriff',
        'ponos.access.members' => 'Mitglieder',
        'ponos.access.add_member' => 'Benutzer hinzufügen',
        'ponos.access.remove_member' => 'Entfernen',
        'ponos.empty.no_members' => 'Noch keine Mitglieder hinzugefügt.',
        'ponos.empty.no_categories' => 'Noch keine Kategorien in dieser Gruppe.',
        'ponos.empty.no_archived_tasks' => 'Keine archivierten Aufgaben.',
        'ponos.archive.title' => 'Archiv',
        'ponos.error.cannot_remove_own_access' => 'Sie können Ihren eigenen Zugriff auf diese Gruppe nicht entfernen.',
        'ponos.error.category_not_found' => 'Kategorie nicht gefunden.',
        'ponos.system.changed_category' => 'Kategorie geändert von %s zu %s',
        'ponos.group.delete_confirm_title' => 'Gruppe löschen?',
        'ponos.group.delete_confirm_message' => 'In diesem Projekt befinden sich noch Aufgaben, die dauerhaft gelöscht werden, wenn Sie diese Gruppe löschen. Sind Sie sicher?',
        'ponos.group.delete_confirm_yes' => 'Ja, alles löschen',
        'ponos.group.delete_confirm_no' => 'Abbrechen',
        'ponos.system.task_created' => 'Aufgabe erstellt: %s',
        'ponos.system.task_updated' => 'Aufgabe bearbeitet.',
        'ponos.system.changed_title' => 'Titel geändert von "%s" zu "%s"',
        'ponos.system.changed_description' => 'Beschreibung geändert',
        'ponos.system.moved_to_group' => 'Aufgabe verschoben nach Gruppe %s',
        'ponos.system.cleared_assignee_on_move' => 'Zuweisung entfernt: kein Zugriff auf Zielgruppe',
        'ponos.system.changed_assignee' => 'Zuweisung geändert von %s zu %s',
        'ponos.system.changed_due_date' => 'Fälligkeitsdatum geändert von %s zu %s',
        'ponos.system.changed_checklist' => 'Checkliste geändert',
        'ponos.system.changed_status' => 'Status geändert von %s zu %s',
        'ponos.system.reminder_sent' => 'E-Mail-Erinnerung gesendet an %s',
        'ponos.system.unarchived' => 'Aufgabe aus Archiv geholt',
        'ponos.reminder.confirm' => '%s per E-Mail an diese Aufgabe erinnern?',
        'ponos.reminder.yes' => 'Ja',
        'ponos.reminder.yes_always' => 'Ja (nicht mehr fragen)',
        'ponos.reminder.no' => 'Nein',
        'ponos.reminder.sent' => 'E-Mail-Erinnerung gesendet.',
        'ponos.error.reminder_rate_limited' => 'Diese Aufgabe kann erst in einer Stunde erneut erinnert werden.',
        'ponos.error.reminder_send_failed' => 'E-Mail-Erinnerung konnte nicht gesendet werden. Bitte später erneut versuchen.',
        'ponos.settings.title' => 'E-Mail-Einstellungen',
        'ponos.settings.assigned' => 'Einer Aufgabe zugewiesen',
        'ponos.settings.status_changed' => 'Aufgabenstatus geändert',
        'ponos.settings.message' => 'Nachricht auf meiner Aufgabe',
        'ponos.settings.checklist' => 'Checkliste auf mir zugewiesener Aufgabe geändert',
        'ponos.settings.daily_reminder' => 'Tägliche Erinnerung bei Aufgaben mit Fälligkeit heute',
        'ponos.settings.hint' => 'Sie erhalten nie E-Mails über Aktionen, die Sie selbst ausgeführt haben.',
        'ponos.pin.pin' => 'Gruppe anheften',
        'ponos.pin.unpin' => 'Gruppe lösen',
        'ponos.unread.badge' => '%d ungelesene Nachrichten',
        'ponos.email.subject.assigned' => 'Ponos: Aufgabe zugewiesen — %s',
        'ponos.email.body.assigned' => "Ihnen wurde die Aufgabe \"%s\" in Gruppe %s zugewiesen.\n\nAufgabe öffnen: %s",
        'ponos.email.subject.status' => 'Ponos: Status geändert — %s',
        'ponos.email.body.status' => "Status von \"%s\" geändert von %s zu %s (Gruppe %s).\n\nAufgabe öffnen: %s",
        'ponos.email.subject.message' => 'Ponos: neue Nachricht — %s',
        'ponos.email.body.message' => "Neue Nachricht zu \"%s\" von %s:\n%s\n\nGruppe: %s\nAufgabe öffnen: %s",
        'ponos.email.subject.checklist' => 'Ponos: Checkliste geändert — %s',
        'ponos.email.body.checklist' => "Die Checkliste von \"%s\" wurde geändert (Gruppe %s).\n\nAufgabe öffnen: %s",
        'ponos.email.subject.daily_reminder' => 'Ponos: Aufgaben mit Fälligkeit heute',
        'ponos.email.body.daily_reminder' => "Folgende Aufgaben sind heute fällig:\n\n%s",
        'ponos.email.intro.assigned' => 'Ihnen wurde die Aufgabe "%s" in Gruppe %s zugewiesen.',
        'ponos.email.intro.status' => 'Status von "%s" geändert von %s zu %s (Gruppe %s).',
        'ponos.email.intro.message' => 'Neue Nachricht zu "%s" von %s: %s (Gruppe %s).',
        'ponos.email.intro.checklist' => 'Die Checkliste von "%s" wurde geändert (Gruppe %s).',
        'ponos.email.intro.daily_reminder' => 'Sie haben %d Aufgabe(n) mit Fälligkeit heute:',
        'ponos.email.subject.reminder' => 'Ponos: Erinnerung — %s',
        'ponos.email.intro.reminder' => '%s erinnert Sie an die Aufgabe "%s" (Gruppe %s).',
        'ponos.email.footer' => 'Klicken Sie auf eine Aufgabenkarte, um die Aufgabe in Ponos zu öffnen.',
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
        'ponos.hero.subtitle' => 'Tâches par groupe.',
        'ponos.label.company' => 'Société',
        'ponos.label.group' => 'Groupe',
        'ponos.label.category' => 'Catégorie',
        'ponos.sidebar.groups' => 'Groupes',
        'ponos.group.my_tasks' => 'Mes tâches',
        'ponos.group.my_tasks_hint' => 'Toutes les tâches qui vous sont assignées',
        'ponos.empty.select_group' => 'Choisissez un groupe dans la barre latérale.',
        'ponos.empty.no_groups' => 'Aucun groupe pour l\'instant. Créez-en un en tant qu\'admin.',
        'ponos.empty.no_tasks' => 'Aucune tâche dans ce groupe.',
        'ponos.btn.new_task' => 'Nouvelle tâche',
        'ponos.btn.new_group' => 'Nouveau groupe',
        'ponos.btn.new_category' => 'Nouvelle catégorie',
        'ponos.btn.delete' => 'Supprimer',
        'ponos.btn.save' => 'Enregistrer',
        'ponos.btn.cancel' => 'Annuler',
        'ponos.btn.edit' => 'Modifier',
        'ponos.btn.send' => 'Envoyer',
        'ponos.btn.archive' => 'Archives',
        'ponos.btn.unarchive' => 'Sortir des archives',
        'ponos.btn.prev_page' => 'Précédent',
        'ponos.btn.next_page' => 'Suivant',
        'ponos.field.title' => 'Titre',
        'ponos.field.description' => 'Description',
        'ponos.field.group' => 'Groupe',
        'ponos.field.assignee' => 'Assigné à',
        'ponos.field.due_date' => 'Échéance',
        'ponos.field.checklist' => 'Liste de contrôle',
        'ponos.field.checklist_add' => 'Ajouter une sous-tâche',
        'ponos.field.attachments' => 'Pièces jointes',
        'ponos.field.message' => 'Message',
        'ponos.field.category' => 'Catégorie',
        'ponos.category.uncategorized' => 'Non catégorisé',
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
        'ponos.error.admin_required' => 'Seuls les admins peuvent gérer les groupes.',
        'ponos.error.group_not_found' => 'Groupe introuvable.',
        'ponos.error.cannot_create_in_my_tasks' => 'Les tâches ne peuvent pas être créées dans Mes tâches.',
        'ponos.prompt.new_group' => 'Nom du nouveau groupe :',
        'ponos.prompt.rename_group' => 'Nouveau nom du groupe :',
        'ponos.group.new_title' => 'Nouveau groupe',
        'ponos.group.rename_title' => 'Renommer le groupe',
        'ponos.category.admin_title' => 'Gérer les catégories',
        'ponos.category.new_title' => 'Nouvelle catégorie',
        'ponos.category.rename_title' => 'Renommer la catégorie',
        'ponos.group.admin_title' => 'Gérer le groupe',
        'ponos.group.tab.categories' => 'Catégories',
        'ponos.group.tab.access' => 'Accès',
        'ponos.access.everyone' => 'Tout le monde a accès',
        'ponos.access.members' => 'Membres',
        'ponos.access.add_member' => 'Ajouter un utilisateur',
        'ponos.access.remove_member' => 'Supprimer',
        'ponos.empty.no_members' => 'Aucun membre ajouté.',
        'ponos.empty.no_categories' => 'Aucune catégorie dans ce groupe.',
        'ponos.empty.no_archived_tasks' => 'Aucune tâche archivée.',
        'ponos.archive.title' => 'Archives',
        'ponos.error.cannot_remove_own_access' => 'Vous ne pouvez pas supprimer votre propre accès à ce groupe.',
        'ponos.error.category_not_found' => 'Catégorie introuvable.',
        'ponos.system.changed_category' => 'Catégorie modifiée de %s à %s',
        'ponos.group.delete_confirm_title' => 'Supprimer le groupe ?',
        'ponos.group.delete_confirm_message' => 'Il reste des tâches dans ce projet qui seront définitivement supprimées si vous supprimez ce groupe. Êtes-vous sûr ?',
        'ponos.group.delete_confirm_yes' => 'Oui, tout supprimer',
        'ponos.group.delete_confirm_no' => 'Annuler',
        'ponos.system.task_created' => 'Tâche créée : %s',
        'ponos.system.task_updated' => 'Tâche modifiée.',
        'ponos.system.changed_title' => 'Titre modifié de "%s" à "%s"',
        'ponos.system.changed_description' => 'Description modifiée',
        'ponos.system.moved_to_group' => 'Tâche déplacée vers le groupe %s',
        'ponos.system.cleared_assignee_on_move' => 'Assignation supprimée : pas d\'accès au groupe cible',
        'ponos.system.changed_assignee' => 'Assignation modifiée de %s à %s',
        'ponos.system.changed_due_date' => 'Échéance modifiée de %s à %s',
        'ponos.system.changed_checklist' => 'Liste de contrôle modifiée',
        'ponos.system.changed_status' => 'Statut modifié de %s à %s',
        'ponos.system.reminder_sent' => 'Rappel par e-mail envoyé à %s',
        'ponos.system.unarchived' => 'Tâche sortie des archives',
        'ponos.reminder.confirm' => 'Voulez-vous rappeler à %s cette tâche par e-mail ?',
        'ponos.reminder.yes' => 'Oui',
        'ponos.reminder.yes_always' => 'Oui (ne plus demander)',
        'ponos.reminder.no' => 'Non',
        'ponos.reminder.sent' => 'Rappel par e-mail envoyé.',
        'ponos.error.reminder_rate_limited' => 'Cette tâche ne peut être rappelée qu\'après une heure.',
        'ponos.error.reminder_send_failed' => 'Échec de l\'envoi du rappel par e-mail. Réessayez plus tard.',
        'ponos.settings.title' => 'Préférences e-mail',
        'ponos.settings.assigned' => 'Assigné à une tâche',
        'ponos.settings.status_changed' => 'Statut de tâche modifié',
        'ponos.settings.message' => 'Message publié sur ma tâche',
        'ponos.settings.checklist' => 'Liste de contrôle modifiée sur une tâche qui m\'est assignée',
        'ponos.settings.daily_reminder' => 'Rappel quotidien pour les tâches dues aujourd\'hui',
        'ponos.settings.hint' => 'Vous ne recevez jamais d\'e-mails pour vos propres actions.',
        'ponos.pin.pin' => 'Épingler le groupe',
        'ponos.pin.unpin' => 'Désépingler le groupe',
        'ponos.unread.badge' => '%d messages non lus',
        'ponos.email.subject.assigned' => 'Ponos : tâche assignée — %s',
        'ponos.email.body.assigned' => "Vous avez été assigné à la tâche \"%s\" dans le groupe %s.\n\nOuvrir la tâche : %s",
        'ponos.email.subject.status' => 'Ponos : statut modifié — %s',
        'ponos.email.body.status' => "Le statut de \"%s\" est passé de %s à %s (groupe %s).\n\nOuvrir la tâche : %s",
        'ponos.email.subject.message' => 'Ponos : nouveau message — %s',
        'ponos.email.body.message' => "Nouveau message sur \"%s\" de %s :\n%s\n\nGroupe : %s\nOuvrir la tâche : %s",
        'ponos.email.subject.checklist' => 'Ponos : liste modifiée — %s',
        'ponos.email.body.checklist' => "La liste de contrôle de \"%s\" a été modifiée (groupe %s).\n\nOuvrir la tâche : %s",
        'ponos.email.subject.daily_reminder' => 'Ponos : tâches dues aujourd\'hui',
        'ponos.email.body.daily_reminder' => "Les tâches suivantes sont dues aujourd'hui :\n\n%s",
        'ponos.email.intro.assigned' => 'La tâche « %s » vous a été assignée dans le groupe %s.',
        'ponos.email.intro.status' => 'Le statut de « %s » est passé de %s à %s (groupe %s).',
        'ponos.email.intro.message' => 'Nouveau message sur « %s » de %s : %s (groupe %s).',
        'ponos.email.intro.checklist' => 'La liste de contrôle de « %s » a été modifiée (groupe %s).',
        'ponos.email.intro.daily_reminder' => 'Vous avez %d tâche(s) dues aujourd\'hui :',
        'ponos.email.subject.reminder' => 'Ponos : rappel — %s',
        'ponos.email.intro.reminder' => '%s vous demande de traiter la tâche « %s » (groupe %s).',
        'ponos.email.footer' => 'Cliquez sur une carte de tâche pour l\'ouvrir dans Ponos.',
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

function ponos_format_display_date(string $dateStr): string
{
    $dateStr = trim($dateStr);
    if ($dateStr === '') {
        return '';
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
        return $dateStr;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $dateStr);
    if ($date === false) {
        return $dateStr;
    }

    if (class_exists(IntlDateFormatter::class)) {
        $formatter = new IntlDateFormatter(
            getDateLocale(),
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            null,
            null,
            'd MMM y'
        );
        $formatted = $formatter->format($date);
        if (is_string($formatted) && $formatted !== '') {
            return preg_replace('/\b([a-z]{3,4})\./iu', '$1', $formatted) ?? $formatted;
        }
    }

    return $date->format('j M Y');
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
