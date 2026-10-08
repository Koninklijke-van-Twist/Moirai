<?php

/**
 * Constants
 */

const FLAG_SVGS = [
    'nl' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 3 2" preserveAspectRatio="none"><rect width="3" height="0.6667" fill="#AE1C28"/><rect width="3" height="0.6667" y="0.6667" fill="#fff"/><rect width="3" height="0.6666" y="1.3333" fill="#21468B"/></svg>',
    'en' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 60 40" preserveAspectRatio="none"><clipPath id="a"><path d="M0 0v40h60V0z"/></clipPath><clipPath id="b"><path d="M30 20h30v20zv20H0zH0V0zV0h30z"/></clipPath><g clip-path="url(#a)"><path d="M0 0v40h60V0z" fill="#012169"/><path d="M0 0l60 40m0-40L0 40" stroke="#fff" stroke-width="8"/><path d="M0 0l60 40m0-40L0 40" clip-path="url(#b)" stroke="#C8102E" stroke-width="5"/><path d="M30 0v40M0 20h60" stroke="#fff" stroke-width="13"/><path d="M30 0v40M0 20h60" stroke="#C8102E" stroke-width="8"/></g></svg>',
    'de' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 3 2" preserveAspectRatio="none"><rect width="3" height="0.6667" fill="#000"/><rect width="3" height="0.6667" y="0.6667" fill="#D00"/><rect width="3" height="0.6666" y="1.3333" fill="#FFCE00"/></svg>',
    'fr' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 3 2" preserveAspectRatio="none"><rect width="1" height="2" fill="#002395"/><rect width="1" height="2" x="1" fill="#fff"/><rect width="1" height="2" x="2" fill="#ED2939"/></svg>',
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
        'app.title' => 'Monitor Ontvangstrapport',
        'moirai.title' => 'Moirai',
        'moirai.hero.subtitle' => 'Apparatenoverzicht voor %s',
        'moirai.admin.badge' => 'admin',
        'moirai.btn.enroll' => 'Linux Enroll Script',
        'moirai.btn.add_device' => 'Nieuw apparaat',
        'moirai.tab.laptops' => 'Laptops',
        'moirai.tab.phones' => 'Telefoons',
        'moirai.tab.accessories' => 'Accessoires',
        'moirai.tab.simcards' => 'SIM-kaarten',
        'budget.tab' => 'Telefoonbudget',
        'budget.col.person' => 'Persoon',
        'budget.col.start' => 'Indiensttreding',
        'budget.col.budget' => 'Huidig budget',
        'budget.col.last' => 'Laatste aankoop',
        'budget.col.id' => 'ID',
        'budget.col.date' => 'Datum',
        'budget.col.price' => 'Prijs',
        'budget.col.own' => 'Eigen bijdrage',
        'budget.col.status' => 'Status',
        'budget.col.linked_phone' => 'Gekoppelde telefoon',
        'budget.field.phone' => 'Telefoon',
        'budget.field.note' => 'Notitie',
        'budget.field.price' => 'Prijs (€)',
        'budget.field.date' => 'Datum',
        'budget.no_start' => 'Geen indiensttreding',
        'budget.filter.all' => 'Iedereen',
        'budget.filter.with' => 'Met budget',
        'budget.filter.without' => 'Zonder indiensttreding',
        'budget.search.placeholder' => 'Naam of e-mail…',
        'budget.page' => 'Pagina %s van %s (%s personen)',
        'budget.prev' => 'Vorige',
        'budget.next' => 'Volgende',
        'budget.modal.person' => 'Telefoonbudget',
        'budget.modal.purchase_new' => 'Aankoop registreren',
        'budget.modal.purchase_edit' => 'Aankoop aanpassen',
        'budget.current' => 'Huidig budget',
        'budget.phone_value' => 'Huidige waarde telefoon',
        'budget.phone_value_hint' => 'Informatief: prijs laatste aankoop min %s per maand (%s maanden). Geen effect op het budget.',
        'budget.add' => 'Aankoop toevoegen',
        'budget.no_purchases' => 'Nog geen aankopen.',
        'budget.start_required' => 'Registreer eerst een indiensttreding; daarna kun je aankopen toevoegen.',
        'budget.save_start' => 'Indiensttreding opslaan',
        'budget.status.onbevestigd' => 'Onbevestigd',
        'budget.status.bevestigd' => 'Bevestigd',
        'budget.btn.confirm' => 'Bevestigen',
        'budget.btn.unconfirm' => 'Onbevestigen',
        'budget.btn.edit' => 'Aanpassen',
        'budget.btn.delete' => 'Verwijderen',
        'budget.btn.yes' => 'Ja, doorgaan',
        'budget.confirm.title' => 'Weet je het zeker?',
        'budget.confirm.confirm' => 'Aankoop #%s bevestigen?',
        'budget.confirm.unconfirm' => 'Aankoop #%s terugzetten naar onbevestigd?',
        'budget.confirm.delete' => 'Aankoop #%s definitief verwijderen? Het budget wordt opnieuw berekend.',
        'budget.preview.before' => 'Budget op die datum',
        'budget.preview.after' => 'Budget na aankoop',
        'budget.preview.own' => 'Eigen bijdrage',
        'budget.unconfirmed_hint' => 'Onbevestigde aankopen tellen mee in het budget.',
        'budget.imported' => 'geïmporteerd',
        'budget.not_in_directory' => 'niet in de gebruikerslijst',
        'budget.link.title' => 'Telefoonbudget-aankoop',
        'budget.link.none' => '— Niet gekoppeld —',
        'budget.link.no_user' => 'Geef de telefoon eerst uit aan een persoon om een aankoop te koppelen.',
        'budget.link.taken' => 'al gekoppeld',
        'budget.link.saved' => 'Koppeling opgeslagen.',
        'budget.settings' => 'Instellingen',
        'budget.settings.start' => 'Startbudget (€)',
        'budget.settings.monthly' => 'Opbouw per maand (€)',
        'budget.settings.max' => 'Maximum budget (€, 0 = geen)',
        'budget.settings.depreciation' => 'Afschrijving telefoon per maand (€)',
        'budget.settings.saved' => 'Instellingen opgeslagen; alle budgetten zijn herberekend.',
        'budget.import' => 'Excel importeren',
        'budget.import.file' => 'Bestand (.xls of .xlsx)',
        'budget.import.preview' => 'Voorbeeld bekijken',
        'budget.import.summary' => '%s regels: %s nieuw, %s al geïmporteerd, %s met fout.',
        'budget.import.persons' => 'Personen in het bestand',
        'budget.import.source' => 'Naam in Excel',
        'budget.import.match' => 'Koppelen aan',
        'budget.import.rows' => 'Regels (nieuw)',
        'budget.import.sure' => 'zeker',
        'budget.import.unsure' => 'controleer',
        'budget.import.unknown' => 'niet herkend',
        'budget.import.skip' => '— Overslaan —',
        'budget.import.errors' => 'Regels met fout (worden overgeslagen)',
        'budget.import.run' => 'Importeren',
        'budget.import.ask_start' => 'Indiensttreding nodig',
        'budget.import.ask_start_body' => '%s (%s) heeft nog geen indiensttreding. In de Excel staat deze persoon als "%s" met %s aankoop/aankopen; de eerste is van %s.',
        'budget.import.next' => 'Volgende',
        'budget.import.cancel' => 'Import afbreken',
        'budget.import.done' => 'Import klaar: %s aankopen toegevoegd, %s overgeslagen, %s personen.',
        'budget.import.need_choice' => 'Kies voor elke te controleren persoon een koppeling of "Overslaan".',
        'budget.import.error.columns' => 'Verplichte kolommen niet gevonden (%s).',
        'budget.import.error.upload' => 'Upload een .xls- of .xlsx-bestand.',
        'budget.import.error.expired' => 'De importsessie is verlopen; upload het bestand opnieuw.',
        'budget.import.error.missing_start' => 'Indiensttreding ontbreekt voor: %s',
        'budget.error.amount_invalid' => 'Ongeldig bedrag.',
        'budget.error.date_invalid' => 'Ongeldige datum.',
        'budget.error.person_invalid' => 'Onbekende persoon.',
        'budget.error.no_start' => 'Deze persoon heeft nog geen indiensttreding.',
        'budget.error.purchase_not_found' => 'Aankoop niet gevonden.',
        'budget.error.status_transition' => 'Deze statuswijziging is niet mogelijk.',
        'budget.error.link_wrong_person' => 'De aankoop hoort niet bij de persoon aan wie de telefoon is uitgegeven.',
        'budget.error.link_taken' => 'Deze aankoop is al aan een andere telefoon gekoppeld.',
        'budget.error.csrf' => 'Sessie verlopen of ongeldig verzoek; herlaad de pagina.',
        'budget.hire.suggestion' => 'Vooringevuld uit Microsoft 365 (employeeHireDate): %s. Controleer of pas aan; er wordt pas iets bewaard als je opslaat.',
        'budget.hire.differs' => 'Microsoft 365 (employeeHireDate) noemt %s.',
        'budget.hire.diagnose_title' => 'Indiensttreding uit Microsoft 365',
        'budget.hire.diagnose_hint' => 'Telt hoeveel gebruikers die Moirai toont employeeHireDate gevuld hebben. Er worden geen namen of datums getoond.',
        'budget.hire.diagnose' => 'Controleer employeeHireDate',
        'budget.hire.result' => '%s gebruikers, waarvan %s met employeeHireDate (jaartallen %s).',
        'budget.hire.result_none' => '%s gebruikers, geen enkele met employeeHireDate.',
        'budget.hire.leave_ok' => 'employeeLeaveDateTime is leesbaar: %s gevuld.',
        'budget.hire.leave_no' => 'employeeLeaveDateTime is niet leesbaar (%s); daarvoor is User-LifeCycleInfo.Read.All nodig.',
        'budget.hire.roles' => 'App-rechten: %s',
        'budget.hire.failed' => 'Microsoft Graph gaf een fout: %s',
        'budget.add_person' => 'Persoon toevoegen',
        'budget.field.email' => 'E-mailadres',
        'budget.field.name' => 'Naam',
        'budget.add_person.hint' => 'Ook voor collega\'s buiten de Moirai-gebruikerslijst (bijv. @hunter.be of zonder functie).',
        'budget.error.start_required' => 'Vul de indiensttreding in.',
        'budget.error.email_invalid' => 'Ongeldig e-mailadres.',
        'budget.import.match_title' => 'Persoon koppelen',
        'budget.import.match_body' => 'Wie is "%s" uit de Excel? %s regel(s), eerste aankoop %s. Kies een suggestie of vul een e-mailadres in.',
        'budget.import.suggestions' => 'Suggesties',
        'budget.import.link' => 'Koppelen',
        'budget.import.skip_person' => 'Overslaan',
        'budget.import.ask' => 'Vragen bij importeren…',
        'budget.import.new_person' => 'nieuw',
        'moirai.label.search' => 'Zoeken',
        'moirai.label.status' => 'Status',
        'moirai.placeholder.search' => 'Model, serienummer, IMEI, accessoire, SIM-code, telefoonnummer, gebruiker…',
        'moirai.status.all' => 'Alles',
        'moirai.status.assigned' => 'Uitgegeven',
        'moirai.status.reserve' => 'Reserve',
        'moirai.status.unavailable' => 'Onbeschikbaar',
        'moirai.filter.all' => '— Alles —',
        'moirai.filter.os' => 'OS',
        'moirai.filter.os_version' => 'OS versie',
        'moirai.filter.model' => 'Modelnaam',
        'moirai.filter.ram' => 'RAM',
        'moirai.filter.storage' => 'Opslag',
        'moirai.filter.keyboard' => 'Toetsenbord',
        'moirai.filter.screen' => 'Schermformaat',
        'moirai.filter.condition' => 'Fysieke staat',
        'moirai.filter.name' => 'Modelnaam',
        'moirai.filter.purchase_date' => 'Aanschafdatum',
        'moirai.loader.devices' => 'Apparaten laden…',
        'moirai.empty.devices' => 'Geen apparaten gevonden.',
        'moirai.badge.assigned' => 'Uitgegeven aan %s',
        'moirai.badge.reserve' => 'Reserve',
        'moirai.badge.unavailable' => 'Onbeschikbaar',
        'moirai.badge.condition' => 'Staat: %s',
        'moirai.modal.device' => 'Apparaat',
        'moirai.modal.edit' => 'Apparaat bewerken',
        'moirai.modal.new' => 'Nieuw apparaat',
        'moirai.modal.assign' => 'Toewijzen',
        'moirai.modal.history' => 'Uitgiftehistorie',
        'moirai.btn.edit' => 'Bewerken',
        'moirai.btn.assign' => 'Toewijzen',
        'moirai.btn.history' => 'Historie',
        'moirai.btn.notes' => 'Notities',
        'moirai.btn.print_label' => 'Print label',
        'moirai.notes.title' => 'Notities',
        'moirai.notes.messages' => 'Berichten',
        'moirai.notes.empty' => 'Nog geen notities. Schrijf het eerste bericht hieronder.',
        'moirai.notes.message_label' => 'Bericht',
        'moirai.notes.edit_label' => 'Bericht bewerken',
        'moirai.notes.close' => 'Sluiten',
        'moirai.notes.load_failed' => 'Notities konden niet worden geladen.',
        'moirai.notes.send_failed' => 'Bericht kon niet worden verstuurd.',
        'moirai.notes.save_failed' => 'Wijziging kon niet worden opgeslagen.',
        'moirai.notes.delete_failed' => 'Bericht kon niet worden verwijderd.',
        'moirai.notes.cancel_edit' => 'Bewerken annuleren',
        'moirai.notes.edited_suffix' => ' (bewerkt)',
        'moirai.notes.delete.confirm.title' => 'Notitie verwijderen',
        'moirai.notes.delete.confirm.body' => 'Dit bericht wordt permanent verwijderd.',
        'moirai.notes.btn.delete_confirm' => 'Ja, verwijderen',
        'moirai.print.ram' => 'RAM',
        'moirai.print.storage' => 'Opslaggrootte',
        'moirai.print.cpu' => 'CPU',
        'moirai.print.modelnumber' => 'Modelnummer',
        'moirai.print.purchased' => 'Gekocht',
        'moirai.print.os' => 'OS',
        'moirai.print.keyboard' => 'Toetsenbord',
        'moirai.print.screen' => 'Scherm',
        'moirai.btn.save' => 'Opslaan',
        'moirai.btn.cancel' => 'Annuleren',
        'moirai.btn.delete' => 'Verwijderen',
        'moirai.btn.close' => 'Sluiten',
        'moirai.btn.refresh_users' => 'Gebruikerslijst vernieuwen',
        'moirai.field.naam' => 'Modelnaam',
        'moirai.field.modelnummer' => 'Modelnummer',
        'moirai.field.model' => 'Modelnaam',
        'moirai.field.serial' => 'Serienummer',
        'moirai.field.imei' => 'IMEI',
        'moirai.field.sim_code' => 'SIM-code',
        'moirai.field.phone_number' => 'Telefoonnummer',
        'moirai.field.accessory_id' => 'Accessoire-ID',
        'moirai.field.description' => 'Beschrijving',
        'moirai.field.ram' => 'RAM hoeveelheid',
        'moirai.field.storage' => 'Opslaggrootte',
        'moirai.field.cpu' => 'CPU modelnummer',
        'moirai.field.purchase_date' => 'Aanschafdatum',
        'moirai.field.os' => 'OS',
        'moirai.field.os_version' => 'OS versie',
        'moirai.field.keyboard' => 'Toetsenbord',
        'moirai.field.screen' => 'Schermformaat (inch)',
        'moirai.field.condition' => 'Fysieke staat',
        'moirai.field.assigned_to' => 'Uitgegeven aan',
        'moirai.field.history' => 'Historie uitgegeven',
        'moirai.condition.uitstekend' => 'Uitstekend',
        'moirai.condition.netjes' => 'Netjes',
        'moirai.condition.lichte_slijtage' => 'Lichte slijtage',
        'moirai.condition.beschadigd' => 'Beschadigd',
        'moirai.condition.phrase.uitstekend' => 'In uitstekende staat',
        'moirai.condition.phrase.netjes' => 'In nette staat',
        'moirai.condition.phrase.lichte_slijtage' => 'Visuele slijtage door gebruik',
        'moirai.condition.phrase.beschadigd' => 'Ernstige schade, maar functioneel en bruikbaar.',
        'moirai.select.choose' => '— Kies —',
        'moirai.select.reserve' => '— Reserve —',
        'moirai.select.unavailable' => '— Onbeschikbaar —',
        'moirai.history.empty' => 'Geen uitgiftehistorie.',
        'moirai.history.current' => 'Huidige toewijzing',
        'moirai.history.entry' => '%s · %s t/m %s',
        'moirai.history.since' => 'Sinds %s',
        'moirai.confirm.delete' => 'Weet je zeker dat je dit apparaat wilt verwijderen?',
        'moirai.delete.confirm.title' => 'Apparaat verwijderen',
        'moirai.delete.confirm.body' => 'Dit apparaat wordt permanent verwijderd. Deze actie is onherroepelijk.',
        'moirai.btn.delete_confirm' => 'Ja, definitief verwijderen',
        'moirai.unnamed' => 'Naamloos',
        'moirai.missing.fields' => 'Ontbrekend',
        'moirai.outdated.label' => 'Apparaat verouderd',
        'moirai.outdated.warning' => 'Dit apparaat is verouderd en moet vervangen worden.',
        'moirai.unknown_user' => 'Onbekend',
        'moirai.error.generic' => 'Er ging iets mis. Probeer het later opnieuw.',
        'moirai.error.request_failed' => 'Verzoek mislukt.',
        'moirai.error.print_failed' => 'Label kon niet worden geprint. Is posprint geïnstalleerd?',
        'moirai.error.forbidden' => 'Geen rechten.',
        'moirai.error.unauthorized' => 'Ongeldige of ontbrekende API-key.',
        'moirai.error.api_key_missing' => 'API-key ontbreekt. Stuur X-API-Key, Authorization: Bearer, of api_key in de POST-body.',
        'moirai.error.api_key_query' => 'API-keys mogen niet in de querystring.',
        'moirai.error.id_required' => 'Apparaat-id is verplicht.',
        'moirai.error.type_required' => 'Apparaattype is verplicht.',
        'moirai.error.invalid_input' => 'Ongeldige invoer.',
        'moirai.error.unknown_action' => 'Onbekende actie.',
        'moirai.error.device_not_found' => 'Apparaat niet gevonden.',
        'moirai.error.unknown_type' => 'Onbekend apparaattype.',
        'moirai.error.name_required' => 'Modelnaam is verplicht.',
        'moirai.error.modelnummer_required' => 'Modelnummer is verplicht.',
        'moirai.error.model_required' => 'Modelnaam is verplicht.',
        'moirai.error.serial_required' => 'Serienummer is verplicht.',
        'moirai.error.imei_required' => 'IMEI is verplicht.',
        'moirai.error.serial_duplicate' => 'Dit serienummer bestaat al.',
        'moirai.error.imei_duplicate' => 'Deze IMEI bestaat al.',
        'moirai.error.assign_invalid_user' => 'Uitgegeven aan moet een geldige gebruiker uit de directory zijn.',
        'moirai.error.users_fetch' => 'Gebruikerslijst kon niet worden opgehaald.',
        'moirai.error.save_failed' => 'Apparaat kon niet worden opgeslagen.',
        'moirai.error.ram_invalid' => 'RAM moet een geldige hoeveelheid zijn (bijv. 16 GB of 8192 MB).',
        'moirai.error.opslag_invalid' => 'Opslaggrootte moet een geldige hoeveelheid zijn (bijv. 512 GB of 256 GB).',
        'moirai.error.date_invalid' => 'Aanschafdatum is ongeldig.',
        'moirai.error.date_future' => 'Aanschafdatum mag niet in de toekomst liggen.',
        'moirai.error.screen_invalid' => 'Schermformaat moet een geldige inch-waarde zijn (bijv. 6.1 of 6.1 inch).',
        'moirai.error.os_laptop_invalid' => 'OS moet Windows, OSX of Linux zijn.',
        'moirai.error.keyboard_invalid' => 'Kies een geldige toetsenbordindeling.',
        'moirai.error.os_phone_invalid' => 'OS moet Android of iOS zijn.',
        'moirai.error.condition_invalid' => 'Kies een geldige fysieke staat.',
        'moirai.error.note_empty' => 'Bericht mag niet leeg zijn.',
        'moirai.error.note_too_long' => 'Bericht is te lang.',
        'moirai.error.note_not_found' => 'Notitie niet gevonden.',
        'moirai.error.sim_code_required' => 'SIM-code is verplicht.',
        'moirai.error.phone_required' => 'Telefoonnummer is verplicht.',
        'moirai.error.phone_invalid' => 'Telefoonnummer is ongeldig. Gebruik bijv. 06 12345678, +31 6 12345678 of 0031 6 12345678.',
        'moirai.error.sim_code_duplicate' => 'Er bestaat al een SIM-kaart met code %s.',
        'moirai.error.phone_duplicate' => 'Telefoonnummer %s is al gekoppeld aan SIM-kaart %s.',
        'moirai.error.sim_duplicate' => 'Deze SIM-kaart (code of telefoonnummer) bestaat al.',
        'moirai.error.print_not_supported' => 'Voor SIM-kaarten kan geen label geprint worden.',
    ],

    'en' => [
        'lang.menu_aria' => 'Choose language',
        'lang.switch_to' => 'Switch to %s',
        'app.title' => 'Receipt Monitor',
        'moirai.title' => 'Moirai',
        'moirai.hero.subtitle' => 'Device overview for %s',
        'moirai.admin.badge' => 'admin',
        'moirai.btn.enroll' => 'Linux Enroll Script',
        'moirai.btn.add_device' => 'New device',
        'moirai.tab.laptops' => 'Laptops',
        'moirai.tab.phones' => 'Phones',
        'moirai.tab.accessories' => 'Accessories',
        'moirai.tab.simcards' => 'SIM cards',
        'budget.tab' => 'Phone budget',
        'budget.col.person' => 'Person',
        'budget.col.start' => 'Start date',
        'budget.col.budget' => 'Current budget',
        'budget.col.last' => 'Last purchase',
        'budget.col.id' => 'ID',
        'budget.col.date' => 'Date',
        'budget.col.price' => 'Price',
        'budget.col.own' => 'Own contribution',
        'budget.col.status' => 'Status',
        'budget.col.linked_phone' => 'Linked phone',
        'budget.field.phone' => 'Phone',
        'budget.field.note' => 'Note',
        'budget.field.price' => 'Price (€)',
        'budget.field.date' => 'Date',
        'budget.no_start' => 'No start date',
        'budget.filter.all' => 'Everyone',
        'budget.filter.with' => 'With budget',
        'budget.filter.without' => 'Without start date',
        'budget.search.placeholder' => 'Name or e-mail…',
        'budget.page' => 'Page %s of %s (%s people)',
        'budget.prev' => 'Previous',
        'budget.next' => 'Next',
        'budget.modal.person' => 'Phone budget',
        'budget.modal.purchase_new' => 'Register purchase',
        'budget.modal.purchase_edit' => 'Edit purchase',
        'budget.current' => 'Current budget',
        'budget.phone_value' => 'Current phone value',
        'budget.phone_value_hint' => 'Informational: last purchase price minus %s per month (%s months). Does not affect the budget.',
        'budget.add' => 'Add purchase',
        'budget.no_purchases' => 'No purchases yet.',
        'budget.start_required' => 'Register a start date first; then you can add purchases.',
        'budget.save_start' => 'Save start date',
        'budget.status.onbevestigd' => 'Unconfirmed',
        'budget.status.bevestigd' => 'Confirmed',
        'budget.btn.confirm' => 'Confirm',
        'budget.btn.unconfirm' => 'Unconfirm',
        'budget.btn.edit' => 'Edit',
        'budget.btn.delete' => 'Delete',
        'budget.btn.yes' => 'Yes, continue',
        'budget.confirm.title' => 'Are you sure?',
        'budget.confirm.confirm' => 'Confirm purchase #%s?',
        'budget.confirm.unconfirm' => 'Set purchase #%s back to unconfirmed?',
        'budget.confirm.delete' => 'Permanently delete purchase #%s? The budget will be recalculated.',
        'budget.preview.before' => 'Budget on that date',
        'budget.preview.after' => 'Budget after purchase',
        'budget.preview.own' => 'Own contribution',
        'budget.unconfirmed_hint' => 'Unconfirmed purchases count towards the budget.',
        'budget.imported' => 'imported',
        'budget.not_in_directory' => 'not in the user list',
        'budget.link.title' => 'Phone budget purchase',
        'budget.link.none' => '— Not linked —',
        'budget.link.no_user' => 'Assign the phone to a person first to link a purchase.',
        'budget.link.taken' => 'already linked',
        'budget.link.saved' => 'Link saved.',
        'budget.settings' => 'Settings',
        'budget.settings.start' => 'Starting budget (€)',
        'budget.settings.monthly' => 'Accrual per month (€)',
        'budget.settings.max' => 'Maximum budget (€, 0 = none)',
        'budget.settings.depreciation' => 'Phone depreciation per month (€)',
        'budget.settings.saved' => 'Settings saved; all budgets were recalculated.',
        'budget.import' => 'Import Excel',
        'budget.import.file' => 'File (.xls or .xlsx)',
        'budget.import.preview' => 'Show preview',
        'budget.import.summary' => '%s rows: %s new, %s already imported, %s with errors.',
        'budget.import.persons' => 'People in the file',
        'budget.import.source' => 'Name in Excel',
        'budget.import.match' => 'Link to',
        'budget.import.rows' => 'Rows (new)',
        'budget.import.sure' => 'certain',
        'budget.import.unsure' => 'check',
        'budget.import.unknown' => 'not recognised',
        'budget.import.skip' => '— Skip —',
        'budget.import.errors' => 'Rows with errors (skipped)',
        'budget.import.run' => 'Import',
        'budget.import.ask_start' => 'Start date needed',
        'budget.import.ask_start_body' => '%s (%s) has no start date yet. The Excel lists this person as "%s" with %s purchase(s); the first is from %s.',
        'budget.import.next' => 'Next',
        'budget.import.cancel' => 'Cancel import',
        'budget.import.done' => 'Import done: %s purchases added, %s skipped, %s people.',
        'budget.import.need_choice' => 'Choose a link or "Skip" for every person to check.',
        'budget.import.error.columns' => 'Required columns not found (%s).',
        'budget.import.error.upload' => 'Upload an .xls or .xlsx file.',
        'budget.import.error.expired' => 'The import session expired; upload the file again.',
        'budget.import.error.missing_start' => 'Start date missing for: %s',
        'budget.error.amount_invalid' => 'Invalid amount.',
        'budget.error.date_invalid' => 'Invalid date.',
        'budget.error.person_invalid' => 'Unknown person.',
        'budget.error.no_start' => 'This person has no start date yet.',
        'budget.error.purchase_not_found' => 'Purchase not found.',
        'budget.error.status_transition' => 'This status change is not allowed.',
        'budget.error.link_wrong_person' => 'The purchase does not belong to the person the phone is assigned to.',
        'budget.error.link_taken' => 'This purchase is already linked to another phone.',
        'budget.error.csrf' => 'Session expired or invalid request; reload the page.',
        'budget.hire.suggestion' => 'Prefilled from Microsoft 365 (employeeHireDate): %s. Check or change it; nothing is stored until you save.',
        'budget.hire.differs' => 'Microsoft 365 (employeeHireDate) says %s.',
        'budget.hire.diagnose_title' => 'Start date from Microsoft 365',
        'budget.hire.diagnose_hint' => 'Counts how many users shown by Moirai have employeeHireDate filled in. No names or dates are shown.',
        'budget.hire.diagnose' => 'Check employeeHireDate',
        'budget.hire.result' => '%s users, %s of them with employeeHireDate (years %s).',
        'budget.hire.result_none' => '%s users, none with employeeHireDate.',
        'budget.hire.leave_ok' => 'employeeLeaveDateTime is readable: %s filled in.',
        'budget.hire.leave_no' => 'employeeLeaveDateTime is not readable (%s); this requires User-LifeCycleInfo.Read.All.',
        'budget.hire.roles' => 'App permissions: %s',
        'budget.hire.failed' => 'Microsoft Graph returned an error: %s',
        'budget.add_person' => 'Add person',
        'budget.field.email' => 'Email address',
        'budget.field.name' => 'Name',
        'budget.add_person.hint' => 'Also for colleagues outside the Moirai user list (e.g. @hunter.be or without a job title).',
        'budget.error.start_required' => 'Enter the start date.',
        'budget.error.email_invalid' => 'Invalid email address.',
        'budget.import.match_title' => 'Link person',
        'budget.import.match_body' => 'Who is "%s" from the Excel file? %s row(s), first purchase %s. Pick a suggestion or enter an email address.',
        'budget.import.suggestions' => 'Suggestions',
        'budget.import.link' => 'Link',
        'budget.import.skip_person' => 'Skip',
        'budget.import.ask' => 'Ask during import…',
        'budget.import.new_person' => 'new',
        'moirai.label.search' => 'Search',
        'moirai.label.status' => 'Status',
        'moirai.placeholder.search' => 'Model, serial, IMEI, accessory, SIM code, phone number, user…',
        'moirai.status.all' => 'All',
        'moirai.status.assigned' => 'Issued',
        'moirai.status.reserve' => 'Reserve',
        'moirai.status.unavailable' => 'Unavailable',
        'moirai.filter.all' => '— All —',
        'moirai.filter.os' => 'OS',
        'moirai.filter.os_version' => 'OS version',
        'moirai.filter.model' => 'Model name',
        'moirai.filter.ram' => 'RAM',
        'moirai.filter.storage' => 'Storage',
        'moirai.filter.keyboard' => 'Keyboard',
        'moirai.filter.screen' => 'Screen size',
        'moirai.filter.condition' => 'Physical condition',
        'moirai.filter.name' => 'Model name',
        'moirai.filter.purchase_date' => 'Purchase date',
        'moirai.loader.devices' => 'Loading devices…',
        'moirai.empty.devices' => 'No devices found.',
        'moirai.badge.assigned' => 'Issued to %s',
        'moirai.badge.reserve' => 'Reserve',
        'moirai.badge.unavailable' => 'Unavailable',
        'moirai.badge.condition' => 'Condition: %s',
        'moirai.modal.device' => 'Device',
        'moirai.modal.edit' => 'Edit device',
        'moirai.modal.new' => 'New device',
        'moirai.modal.assign' => 'Assign',
        'moirai.modal.history' => 'Issue history',
        'moirai.btn.edit' => 'Edit',
        'moirai.btn.assign' => 'Assign',
        'moirai.btn.history' => 'History',
        'moirai.btn.notes' => 'Notes',
        'moirai.btn.print_label' => 'Print label',
        'moirai.notes.title' => 'Notes',
        'moirai.notes.messages' => 'Messages',
        'moirai.notes.empty' => 'No notes yet. Write the first message below.',
        'moirai.notes.message_label' => 'Message',
        'moirai.notes.edit_label' => 'Edit message',
        'moirai.notes.close' => 'Close',
        'moirai.notes.load_failed' => 'Notes could not be loaded.',
        'moirai.notes.send_failed' => 'Message could not be sent.',
        'moirai.notes.save_failed' => 'Change could not be saved.',
        'moirai.notes.delete_failed' => 'Message could not be deleted.',
        'moirai.notes.cancel_edit' => 'Cancel editing',
        'moirai.notes.edited_suffix' => ' (edited)',
        'moirai.notes.delete.confirm.title' => 'Delete note',
        'moirai.notes.delete.confirm.body' => 'This message will be permanently deleted.',
        'moirai.notes.btn.delete_confirm' => 'Yes, delete',
        'moirai.print.ram' => 'RAM',
        'moirai.print.storage' => 'Storage',
        'moirai.print.cpu' => 'CPU',
        'moirai.print.modelnumber' => 'Model number',
        'moirai.print.purchased' => 'Purchased',
        'moirai.print.os' => 'OS',
        'moirai.print.keyboard' => 'Keyboard',
        'moirai.print.screen' => 'Screen',
        'moirai.btn.save' => 'Save',
        'moirai.btn.cancel' => 'Cancel',
        'moirai.btn.delete' => 'Delete',
        'moirai.btn.close' => 'Close',
        'moirai.btn.refresh_users' => 'Refresh user list',
        'moirai.field.naam' => 'Model name',
        'moirai.field.modelnummer' => 'Model number',
        'moirai.field.model' => 'Model name',
        'moirai.field.serial' => 'Serial number',
        'moirai.field.imei' => 'IMEI',
        'moirai.field.sim_code' => 'SIM code',
        'moirai.field.phone_number' => 'Phone number',
        'moirai.field.accessory_id' => 'Accessory ID',
        'moirai.field.description' => 'Description',
        'moirai.field.ram' => 'RAM amount',
        'moirai.field.storage' => 'Storage capacity',
        'moirai.field.cpu' => 'CPU model number',
        'moirai.field.purchase_date' => 'Purchase date',
        'moirai.field.os' => 'OS',
        'moirai.field.os_version' => 'OS version',
        'moirai.field.keyboard' => 'Keyboard',
        'moirai.field.screen' => 'Screen size (inches)',
        'moirai.field.condition' => 'Physical condition',
        'moirai.field.assigned_to' => 'Issued to',
        'moirai.field.history' => 'Issue history',
        'moirai.condition.uitstekend' => 'Excellent',
        'moirai.condition.netjes' => 'Neat',
        'moirai.condition.lichte_slijtage' => 'Light wear',
        'moirai.condition.beschadigd' => 'Damaged',
        'moirai.condition.phrase.uitstekend' => 'In excellent condition',
        'moirai.condition.phrase.netjes' => 'In neat condition',
        'moirai.condition.phrase.lichte_slijtage' => 'Visual wear from use',
        'moirai.condition.phrase.beschadigd' => 'Serious damage, but functional and usable.',
        'moirai.select.choose' => '— Choose —',
        'moirai.select.reserve' => '— Reserve —',
        'moirai.select.unavailable' => '— Unavailable —',
        'moirai.history.empty' => 'No issue history.',
        'moirai.history.current' => 'Current assignment',
        'moirai.history.entry' => '%s · %s to %s',
        'moirai.history.since' => 'Since %s',
        'moirai.confirm.delete' => 'Are you sure you want to delete this device?',
        'moirai.delete.confirm.title' => 'Delete device',
        'moirai.delete.confirm.body' => 'This device will be permanently deleted. This action is irreversible.',
        'moirai.btn.delete_confirm' => 'Yes, delete permanently',
        'moirai.unnamed' => 'Unnamed',
        'moirai.missing.fields' => 'Missing',
        'moirai.outdated.label' => 'Device outdated',
        'moirai.outdated.warning' => 'This device is outdated and needs to be replaced.',
        'moirai.unknown_user' => 'Unknown',
        'moirai.error.generic' => 'Something went wrong. Please try again later.',
        'moirai.error.request_failed' => 'Request failed.',
        'moirai.error.print_failed' => 'Label could not be printed. Is posprint installed?',
        'moirai.error.forbidden' => 'Access denied.',
        'moirai.error.unauthorized' => 'Invalid or missing API key.',
        'moirai.error.api_key_missing' => 'API key is missing. Send X-API-Key, Authorization: Bearer, or api_key in the POST body.',
        'moirai.error.api_key_query' => 'API keys must not be sent in the query string.',
        'moirai.error.id_required' => 'Device id is required.',
        'moirai.error.type_required' => 'Device type is required.',
        'moirai.error.invalid_input' => 'Invalid input.',
        'moirai.error.unknown_action' => 'Unknown action.',
        'moirai.error.device_not_found' => 'Device not found.',
        'moirai.error.unknown_type' => 'Unknown device type.',
        'moirai.error.name_required' => 'Model name is required.',
        'moirai.error.modelnummer_required' => 'Model number is required.',
        'moirai.error.model_required' => 'Model name is required.',
        'moirai.error.serial_required' => 'Serial number is required.',
        'moirai.error.imei_required' => 'IMEI is required.',
        'moirai.error.serial_duplicate' => 'This serial number already exists.',
        'moirai.error.imei_duplicate' => 'This IMEI already exists.',
        'moirai.error.assign_invalid_user' => 'Issued to must be a valid directory user.',
        'moirai.error.users_fetch' => 'Could not fetch user list.',
        'moirai.error.save_failed' => 'Device could not be saved.',
        'moirai.error.ram_invalid' => 'RAM must be a valid amount (e.g. 16 GB or 8192 MB).',
        'moirai.error.opslag_invalid' => 'Storage capacity must be a valid amount (e.g. 512 GB or 256 GB).',
        'moirai.error.date_invalid' => 'Purchase date is invalid.',
        'moirai.error.date_future' => 'Purchase date cannot be in the future.',
        'moirai.error.screen_invalid' => 'Screen size must be a valid inch value (e.g. 6.1 or 6.1 inch).',
        'moirai.error.os_laptop_invalid' => 'OS must be Windows, OSX or Linux.',
        'moirai.error.keyboard_invalid' => 'Choose a valid keyboard layout.',
        'moirai.error.os_phone_invalid' => 'OS must be Android or iOS.',
        'moirai.error.condition_invalid' => 'Choose a valid physical condition.',
        'moirai.error.note_empty' => 'Message cannot be empty.',
        'moirai.error.note_too_long' => 'Message is too long.',
        'moirai.error.note_not_found' => 'Note not found.',
        'moirai.error.sim_code_required' => 'SIM code is required.',
        'moirai.error.phone_required' => 'Phone number is required.',
        'moirai.error.phone_invalid' => 'Phone number is invalid. Use e.g. 06 12345678, +31 6 12345678 or 0031 6 12345678.',
        'moirai.error.sim_code_duplicate' => 'A SIM card with code %s already exists.',
        'moirai.error.phone_duplicate' => 'Phone number %s is already linked to SIM card %s.',
        'moirai.error.sim_duplicate' => 'This SIM card (code or phone number) already exists.',
        'moirai.error.print_not_supported' => 'Labels cannot be printed for SIM cards.',
    ],

    'de' => [
        'lang.menu_aria' => 'Sprache wählen',
        'lang.switch_to' => 'Wechseln zu %s',
        'app.title' => 'Wareneingangsmonitor',
        'moirai.title' => 'Moirai',
        'moirai.hero.subtitle' => 'Geräteübersicht für %s',
        'moirai.admin.badge' => 'Admin',
        'moirai.btn.enroll' => 'Linux Enroll Script',
        'moirai.btn.add_device' => 'Neues Gerät',
        'moirai.tab.laptops' => 'Laptops',
        'moirai.tab.phones' => 'Telefone',
        'moirai.tab.accessories' => 'Zubehör',
        'moirai.tab.simcards' => 'SIM-Karten',
        'budget.tab' => 'Telefonbudget',
        'budget.col.person' => 'Person',
        'budget.col.start' => 'Eintrittsdatum',
        'budget.col.budget' => 'Aktuelles Budget',
        'budget.col.last' => 'Letzter Kauf',
        'budget.col.id' => 'ID',
        'budget.col.date' => 'Datum',
        'budget.col.price' => 'Preis',
        'budget.col.own' => 'Eigenanteil',
        'budget.col.status' => 'Status',
        'budget.col.linked_phone' => 'Verknüpftes Telefon',
        'budget.field.phone' => 'Telefon',
        'budget.field.note' => 'Notiz',
        'budget.field.price' => 'Preis (€)',
        'budget.field.date' => 'Datum',
        'budget.no_start' => 'Kein Eintrittsdatum',
        'budget.filter.all' => 'Alle',
        'budget.filter.with' => 'Mit Budget',
        'budget.filter.without' => 'Ohne Eintrittsdatum',
        'budget.search.placeholder' => 'Name oder E-Mail…',
        'budget.page' => 'Seite %s von %s (%s Personen)',
        'budget.prev' => 'Zurück',
        'budget.next' => 'Weiter',
        'budget.modal.person' => 'Telefonbudget',
        'budget.modal.purchase_new' => 'Kauf erfassen',
        'budget.modal.purchase_edit' => 'Kauf bearbeiten',
        'budget.current' => 'Aktuelles Budget',
        'budget.phone_value' => 'Aktueller Telefonwert',
        'budget.phone_value_hint' => 'Nur zur Info: Preis des letzten Kaufs minus %s pro Monat (%s Monate). Kein Einfluss auf das Budget.',
        'budget.add' => 'Kauf hinzufügen',
        'budget.no_purchases' => 'Noch keine Käufe.',
        'budget.start_required' => 'Erfassen Sie zuerst ein Eintrittsdatum; danach können Sie Käufe hinzufügen.',
        'budget.save_start' => 'Eintrittsdatum speichern',
        'budget.status.onbevestigd' => 'Unbestätigt',
        'budget.status.bevestigd' => 'Bestätigt',
        'budget.btn.confirm' => 'Bestätigen',
        'budget.btn.unconfirm' => 'Bestätigung aufheben',
        'budget.btn.edit' => 'Bearbeiten',
        'budget.btn.delete' => 'Löschen',
        'budget.btn.yes' => 'Ja, fortfahren',
        'budget.confirm.title' => 'Sind Sie sicher?',
        'budget.confirm.confirm' => 'Kauf #%s bestätigen?',
        'budget.confirm.unconfirm' => 'Kauf #%s wieder auf unbestätigt setzen?',
        'budget.confirm.delete' => 'Kauf #%s endgültig löschen? Das Budget wird neu berechnet.',
        'budget.preview.before' => 'Budget an diesem Datum',
        'budget.preview.after' => 'Budget nach dem Kauf',
        'budget.preview.own' => 'Eigenanteil',
        'budget.unconfirmed_hint' => 'Unbestätigte Käufe zählen zum Budget.',
        'budget.imported' => 'importiert',
        'budget.not_in_directory' => 'nicht in der Benutzerliste',
        'budget.link.title' => 'Telefonbudget-Kauf',
        'budget.link.none' => '— Nicht verknüpft —',
        'budget.link.no_user' => 'Geben Sie das Telefon zuerst einer Person aus, um einen Kauf zu verknüpfen.',
        'budget.link.taken' => 'bereits verknüpft',
        'budget.link.saved' => 'Verknüpfung gespeichert.',
        'budget.settings' => 'Einstellungen',
        'budget.settings.start' => 'Startbudget (€)',
        'budget.settings.monthly' => 'Aufbau pro Monat (€)',
        'budget.settings.max' => 'Maximales Budget (€, 0 = keins)',
        'budget.settings.depreciation' => 'Telefon-Abschreibung pro Monat (€)',
        'budget.settings.saved' => 'Einstellungen gespeichert; alle Budgets wurden neu berechnet.',
        'budget.import' => 'Excel importieren',
        'budget.import.file' => 'Datei (.xls oder .xlsx)',
        'budget.import.preview' => 'Vorschau anzeigen',
        'budget.import.summary' => '%s Zeilen: %s neu, %s bereits importiert, %s fehlerhaft.',
        'budget.import.persons' => 'Personen in der Datei',
        'budget.import.source' => 'Name in Excel',
        'budget.import.match' => 'Verknüpfen mit',
        'budget.import.rows' => 'Zeilen (neu)',
        'budget.import.sure' => 'sicher',
        'budget.import.unsure' => 'prüfen',
        'budget.import.unknown' => 'nicht erkannt',
        'budget.import.skip' => '— Überspringen —',
        'budget.import.errors' => 'Fehlerhafte Zeilen (werden übersprungen)',
        'budget.import.run' => 'Importieren',
        'budget.import.ask_start' => 'Eintrittsdatum erforderlich',
        'budget.import.ask_start_body' => '%s (%s) hat noch kein Eintrittsdatum. In der Excel steht diese Person als "%s" mit %s Kauf/Käufen; der erste ist vom %s.',
        'budget.import.next' => 'Weiter',
        'budget.import.cancel' => 'Import abbrechen',
        'budget.import.done' => 'Import fertig: %s Käufe hinzugefügt, %s übersprungen, %s Personen.',
        'budget.import.need_choice' => 'Wählen Sie für jede zu prüfende Person eine Verknüpfung oder „Überspringen“.',
        'budget.import.error.columns' => 'Pflichtspalten nicht gefunden (%s).',
        'budget.import.error.upload' => 'Laden Sie eine .xls- oder .xlsx-Datei hoch.',
        'budget.import.error.expired' => 'Die Importsitzung ist abgelaufen; laden Sie die Datei erneut hoch.',
        'budget.import.error.missing_start' => 'Eintrittsdatum fehlt für: %s',
        'budget.error.amount_invalid' => 'Ungültiger Betrag.',
        'budget.error.date_invalid' => 'Ungültiges Datum.',
        'budget.error.person_invalid' => 'Unbekannte Person.',
        'budget.error.no_start' => 'Diese Person hat noch kein Eintrittsdatum.',
        'budget.error.purchase_not_found' => 'Kauf nicht gefunden.',
        'budget.error.status_transition' => 'Diese Statusänderung ist nicht möglich.',
        'budget.error.link_wrong_person' => 'Der Kauf gehört nicht zu der Person, der das Telefon ausgegeben wurde.',
        'budget.error.link_taken' => 'Dieser Kauf ist bereits mit einem anderen Telefon verknüpft.',
        'budget.error.csrf' => 'Sitzung abgelaufen oder ungültige Anfrage; Seite neu laden.',
        'budget.hire.suggestion' => 'Vorausgefüllt aus Microsoft 365 (employeeHireDate): %s. Prüfen oder ändern; gespeichert wird erst beim Speichern.',
        'budget.hire.differs' => 'Microsoft 365 (employeeHireDate) nennt %s.',
        'budget.hire.diagnose_title' => 'Eintrittsdatum aus Microsoft 365',
        'budget.hire.diagnose_hint' => 'Zählt, wie viele der in Moirai angezeigten Benutzer employeeHireDate ausgefüllt haben. Es werden keine Namen oder Daten angezeigt.',
        'budget.hire.diagnose' => 'employeeHireDate prüfen',
        'budget.hire.result' => '%s Benutzer, davon %s mit employeeHireDate (Jahre %s).',
        'budget.hire.result_none' => '%s Benutzer, keiner mit employeeHireDate.',
        'budget.hire.leave_ok' => 'employeeLeaveDateTime ist lesbar: %s ausgefüllt.',
        'budget.hire.leave_no' => 'employeeLeaveDateTime ist nicht lesbar (%s); dafür ist User-LifeCycleInfo.Read.All nötig.',
        'budget.hire.roles' => 'App-Berechtigungen: %s',
        'budget.hire.failed' => 'Microsoft Graph meldete einen Fehler: %s',
        'budget.add_person' => 'Person hinzufügen',
        'budget.field.email' => 'E-Mail-Adresse',
        'budget.field.name' => 'Name',
        'budget.add_person.hint' => 'Auch für Kollegen außerhalb der Moirai-Benutzerliste (z. B. @hunter.be oder ohne Funktion).',
        'budget.error.start_required' => 'Geben Sie das Eintrittsdatum ein.',
        'budget.error.email_invalid' => 'Ungültige E-Mail-Adresse.',
        'budget.import.match_title' => 'Person zuordnen',
        'budget.import.match_body' => 'Wer ist „%s“ aus der Excel-Datei? %s Zeile(n), erster Kauf %s. Wählen Sie einen Vorschlag oder geben Sie eine E-Mail-Adresse ein.',
        'budget.import.suggestions' => 'Vorschläge',
        'budget.import.link' => 'Zuordnen',
        'budget.import.skip_person' => 'Überspringen',
        'budget.import.ask' => 'Beim Import fragen…',
        'budget.import.new_person' => 'neu',
        'moirai.label.search' => 'Suchen',
        'moirai.label.status' => 'Status',
        'moirai.placeholder.search' => 'Modell, Seriennummer, IMEI, Zubehör, SIM-Code, Telefonnummer, Benutzer…',
        'moirai.status.all' => 'Alle',
        'moirai.status.assigned' => 'Ausgegeben',
        'moirai.status.reserve' => 'Reserve',
        'moirai.status.unavailable' => 'Nicht verfügbar',
        'moirai.filter.all' => '— Alle —',
        'moirai.filter.os' => 'OS',
        'moirai.filter.os_version' => 'OS-Version',
        'moirai.filter.model' => 'Modellname',
        'moirai.filter.ram' => 'RAM',
        'moirai.filter.storage' => 'Speicher',
        'moirai.filter.keyboard' => 'Tastatur',
        'moirai.filter.screen' => 'Bildschirmgröße',
        'moirai.filter.condition' => 'Physischer Zustand',
        'moirai.filter.name' => 'Modellname',
        'moirai.filter.purchase_date' => 'Anschaffungsdatum',
        'moirai.loader.devices' => 'Geräte werden geladen…',
        'moirai.empty.devices' => 'Keine Geräte gefunden.',
        'moirai.badge.assigned' => 'Ausgegeben an %s',
        'moirai.badge.reserve' => 'Reserve',
        'moirai.badge.unavailable' => 'Nicht verfügbar',
        'moirai.badge.condition' => 'Zustand: %s',
        'moirai.modal.device' => 'Gerät',
        'moirai.modal.edit' => 'Gerät bearbeiten',
        'moirai.modal.new' => 'Neues Gerät',
        'moirai.modal.assign' => 'Zuweisen',
        'moirai.modal.history' => 'Ausgabeverlauf',
        'moirai.btn.edit' => 'Bearbeiten',
        'moirai.btn.assign' => 'Zuweisen',
        'moirai.btn.history' => 'Verlauf',
        'moirai.btn.notes' => 'Notizen',
        'moirai.btn.print_label' => 'Etikett drucken',
        'moirai.notes.title' => 'Notizen',
        'moirai.notes.messages' => 'Nachrichten',
        'moirai.notes.empty' => 'Noch keine Notizen. Schreiben Sie die erste Nachricht unten.',
        'moirai.notes.message_label' => 'Nachricht',
        'moirai.notes.edit_label' => 'Nachricht bearbeiten',
        'moirai.notes.close' => 'Schließen',
        'moirai.notes.load_failed' => 'Notizen konnten nicht geladen werden.',
        'moirai.notes.send_failed' => 'Nachricht konnte nicht gesendet werden.',
        'moirai.notes.save_failed' => 'Änderung konnte nicht gespeichert werden.',
        'moirai.notes.delete_failed' => 'Nachricht konnte nicht gelöscht werden.',
        'moirai.notes.cancel_edit' => 'Bearbeiten abbrechen',
        'moirai.notes.edited_suffix' => ' (bearbeitet)',
        'moirai.notes.delete.confirm.title' => 'Notiz löschen',
        'moirai.notes.delete.confirm.body' => 'Diese Nachricht wird dauerhaft gelöscht.',
        'moirai.notes.btn.delete_confirm' => 'Ja, löschen',
        'moirai.print.ram' => 'RAM',
        'moirai.print.storage' => 'Speicher',
        'moirai.print.cpu' => 'CPU',
        'moirai.print.modelnumber' => 'Modellnummer',
        'moirai.print.purchased' => 'Gekauft',
        'moirai.print.os' => 'OS',
        'moirai.print.keyboard' => 'Tastatur',
        'moirai.print.screen' => 'Display',
        'moirai.btn.save' => 'Speichern',
        'moirai.btn.cancel' => 'Abbrechen',
        'moirai.btn.delete' => 'Löschen',
        'moirai.btn.close' => 'Schließen',
        'moirai.btn.refresh_users' => 'Benutzerliste aktualisieren',
        'moirai.field.naam' => 'Modellname',
        'moirai.field.modelnummer' => 'Modellnummer',
        'moirai.field.model' => 'Modellname',
        'moirai.field.serial' => 'Seriennummer',
        'moirai.field.imei' => 'IMEI',
        'moirai.field.sim_code' => 'SIM-Code',
        'moirai.field.phone_number' => 'Telefonnummer',
        'moirai.field.accessory_id' => 'Zubehör-ID',
        'moirai.field.description' => 'Beschreibung',
        'moirai.field.ram' => 'RAM-Menge',
        'moirai.field.storage' => 'Speicherkapazität',
        'moirai.field.cpu' => 'CPU-Modellnummer',
        'moirai.field.purchase_date' => 'Anschaffungsdatum',
        'moirai.field.os' => 'OS',
        'moirai.field.os_version' => 'OS-Version',
        'moirai.field.keyboard' => 'Tastatur',
        'moirai.field.screen' => 'Bildschirmgröße (Zoll)',
        'moirai.field.condition' => 'Physischer Zustand',
        'moirai.field.assigned_to' => 'Ausgegeben an',
        'moirai.field.history' => 'Ausgabeverlauf',
        'moirai.condition.uitstekend' => 'Ausgezeichnet',
        'moirai.condition.netjes' => 'Ordentlich',
        'moirai.condition.lichte_slijtage' => 'Leichte Abnutzung',
        'moirai.condition.beschadigd' => 'Beschädigt',
        'moirai.condition.phrase.uitstekend' => 'In ausgezeichnetem Zustand',
        'moirai.condition.phrase.netjes' => 'In ordentlichem Zustand',
        'moirai.condition.phrase.lichte_slijtage' => 'Visuelle Abnutzung durch Gebrauch',
        'moirai.condition.phrase.beschadigd' => 'Schwere Schäden, aber funktionsfähig und nutzbar.',
        'moirai.select.choose' => '— Wählen —',
        'moirai.select.reserve' => '— Reserve —',
        'moirai.select.unavailable' => '— Nicht verfügbar —',
        'moirai.history.empty' => 'Kein Ausgabeverlauf.',
        'moirai.history.current' => 'Aktuelle Zuweisung',
        'moirai.history.entry' => '%s · %s bis %s',
        'moirai.history.since' => 'Seit %s',
        'moirai.confirm.delete' => 'Möchten Sie dieses Gerät wirklich löschen?',
        'moirai.delete.confirm.title' => 'Gerät löschen',
        'moirai.delete.confirm.body' => 'Dieses Gerät wird dauerhaft gelöscht. Diese Aktion ist unwiderruflich.',
        'moirai.btn.delete_confirm' => 'Ja, endgültig löschen',
        'moirai.unnamed' => 'Unbenannt',
        'moirai.missing.fields' => 'Fehlt',
        'moirai.outdated.label' => 'Gerät veraltet',
        'moirai.outdated.warning' => 'Dieses Gerät ist veraltet und muss ersetzt werden.',
        'moirai.unknown_user' => 'Unbekannt',
        'moirai.error.generic' => 'Etwas ist schiefgelaufen. Bitte später erneut versuchen.',
        'moirai.error.request_failed' => 'Anfrage fehlgeschlagen.',
        'moirai.error.print_failed' => 'Etikett konnte nicht gedruckt werden. Ist posprint installiert?',
        'moirai.error.forbidden' => 'Keine Berechtigung.',
        'moirai.error.unauthorized' => 'Ungültiger oder fehlender API-Schlüssel.',
        'moirai.error.api_key_missing' => 'API-Schlüssel fehlt. Senden Sie X-API-Key, Authorization: Bearer oder api_key im POST-Body.',
        'moirai.error.api_key_query' => 'API-Schlüssel dürfen nicht in der Querystring stehen.',
        'moirai.error.id_required' => 'Geräte-ID ist erforderlich.',
        'moirai.error.type_required' => 'Gerätetyp ist erforderlich.',
        'moirai.error.invalid_input' => 'Ungültige Eingabe.',
        'moirai.error.unknown_action' => 'Unbekannte Aktion.',
        'moirai.error.device_not_found' => 'Gerät nicht gefunden.',
        'moirai.error.unknown_type' => 'Unbekannter Gerätetyp.',
        'moirai.error.name_required' => 'Modellname ist erforderlich.',
        'moirai.error.modelnummer_required' => 'Modellnummer ist erforderlich.',
        'moirai.error.model_required' => 'Modellname ist erforderlich.',
        'moirai.error.serial_required' => 'Seriennummer ist erforderlich.',
        'moirai.error.imei_required' => 'IMEI ist erforderlich.',
        'moirai.error.serial_duplicate' => 'Diese Seriennummer existiert bereits.',
        'moirai.error.imei_duplicate' => 'Diese IMEI existiert bereits.',
        'moirai.error.assign_invalid_user' => 'Ausgegeben an muss ein gültiger Verzeichnisbenutzer sein.',
        'moirai.error.users_fetch' => 'Benutzerliste konnte nicht abgerufen werden.',
        'moirai.error.save_failed' => 'Gerät konnte nicht gespeichert werden.',
        'moirai.error.ram_invalid' => 'RAM muss eine gültige Menge sein (z. B. 16 GB oder 8192 MB).',
        'moirai.error.date_invalid' => 'Anschaffungsdatum ist ungültig.',
        'moirai.error.date_future' => 'Anschaffungsdatum darf nicht in der Zukunft liegen.',
        'moirai.error.screen_invalid' => 'Bildschirmgröße muss ein gültiger Zoll-Wert sein (z. B. 6.1 oder 6.1 inch).',
        'moirai.error.os_laptop_invalid' => 'OS muss Windows, OSX oder Linux sein.',
        'moirai.error.keyboard_invalid' => 'Wählen Sie ein gültiges Tastaturlayout.',
        'moirai.error.os_phone_invalid' => 'OS muss Android oder iOS sein.',
        'moirai.error.condition_invalid' => 'Wählen Sie einen gültigen physischen Zustand.',
        'moirai.error.note_empty' => 'Nachricht darf nicht leer sein.',
        'moirai.error.note_too_long' => 'Nachricht ist zu lang.',
        'moirai.error.note_not_found' => 'Notiz nicht gefunden.',
        'moirai.error.sim_code_required' => 'SIM-Code ist erforderlich.',
        'moirai.error.phone_required' => 'Telefonnummer ist erforderlich.',
        'moirai.error.phone_invalid' => 'Telefonnummer ist ungültig. Verwenden Sie z. B. 06 12345678, +31 6 12345678 oder 0031 6 12345678.',
        'moirai.error.sim_code_duplicate' => 'Eine SIM-Karte mit dem Code %s existiert bereits.',
        'moirai.error.phone_duplicate' => 'Telefonnummer %s ist bereits mit SIM-Karte %s verknüpft.',
        'moirai.error.sim_duplicate' => 'Diese SIM-Karte (Code oder Telefonnummer) existiert bereits.',
        'moirai.error.print_not_supported' => 'Für SIM-Karten können keine Etiketten gedruckt werden.',
    ],

    'fr' => [
        'lang.menu_aria' => 'Choisir la langue',
        'lang.switch_to' => 'Passer en %s',
        'app.title' => 'Moniteur de réception',
        'moirai.title' => 'Moirai',
        'moirai.hero.subtitle' => 'Aperçu des appareils pour %s',
        'moirai.admin.badge' => 'admin',
        'moirai.btn.enroll' => 'Script Linux Enroll',
        'moirai.btn.add_device' => 'Nouvel appareil',
        'moirai.tab.laptops' => 'Ordinateurs portables',
        'moirai.tab.phones' => 'Téléphones',
        'moirai.tab.accessories' => 'Accessoires',
        'moirai.tab.simcards' => 'Cartes SIM',
        'budget.tab' => 'Budget téléphone',
        'budget.col.person' => 'Personne',
        'budget.col.start' => 'Date d\'entrée',
        'budget.col.budget' => 'Budget actuel',
        'budget.col.last' => 'Dernier achat',
        'budget.col.id' => 'ID',
        'budget.col.date' => 'Date',
        'budget.col.price' => 'Prix',
        'budget.col.own' => 'Contribution personnelle',
        'budget.col.status' => 'Statut',
        'budget.col.linked_phone' => 'Téléphone lié',
        'budget.field.phone' => 'Téléphone',
        'budget.field.note' => 'Note',
        'budget.field.price' => 'Prix (€)',
        'budget.field.date' => 'Date',
        'budget.no_start' => 'Pas de date d\'entrée',
        'budget.filter.all' => 'Tout le monde',
        'budget.filter.with' => 'Avec budget',
        'budget.filter.without' => 'Sans date d\'entrée',
        'budget.search.placeholder' => 'Nom ou e-mail…',
        'budget.page' => 'Page %s sur %s (%s personnes)',
        'budget.prev' => 'Précédent',
        'budget.next' => 'Suivant',
        'budget.modal.person' => 'Budget téléphone',
        'budget.modal.purchase_new' => 'Enregistrer un achat',
        'budget.modal.purchase_edit' => 'Modifier l\'achat',
        'budget.current' => 'Budget actuel',
        'budget.phone_value' => 'Valeur actuelle du téléphone',
        'budget.phone_value_hint' => 'Indicatif : prix du dernier achat moins %s par mois (%s mois). Sans effet sur le budget.',
        'budget.add' => 'Ajouter un achat',
        'budget.no_purchases' => 'Pas encore d\'achats.',
        'budget.start_required' => 'Enregistrez d\'abord une date d\'entrée ; vous pourrez ensuite ajouter des achats.',
        'budget.save_start' => 'Enregistrer la date d\'entrée',
        'budget.status.onbevestigd' => 'Non confirmé',
        'budget.status.bevestigd' => 'Confirmé',
        'budget.btn.confirm' => 'Confirmer',
        'budget.btn.unconfirm' => 'Annuler la confirmation',
        'budget.btn.edit' => 'Modifier',
        'budget.btn.delete' => 'Supprimer',
        'budget.btn.yes' => 'Oui, continuer',
        'budget.confirm.title' => 'Êtes-vous sûr ?',
        'budget.confirm.confirm' => 'Confirmer l\'achat n° %s ?',
        'budget.confirm.unconfirm' => 'Remettre l\'achat n° %s en non confirmé ?',
        'budget.confirm.delete' => 'Supprimer définitivement l\'achat n° %s ? Le budget sera recalculé.',
        'budget.preview.before' => 'Budget à cette date',
        'budget.preview.after' => 'Budget après l\'achat',
        'budget.preview.own' => 'Contribution personnelle',
        'budget.unconfirmed_hint' => 'Les achats non confirmés sont pris en compte dans le budget.',
        'budget.imported' => 'importé',
        'budget.not_in_directory' => 'pas dans la liste des utilisateurs',
        'budget.link.title' => 'Achat budget téléphone',
        'budget.link.none' => '— Non lié —',
        'budget.link.no_user' => 'Attribuez d\'abord le téléphone à une personne pour lier un achat.',
        'budget.link.taken' => 'déjà lié',
        'budget.link.saved' => 'Lien enregistré.',
        'budget.settings' => 'Paramètres',
        'budget.settings.start' => 'Budget de départ (€)',
        'budget.settings.monthly' => 'Cumul par mois (€)',
        'budget.settings.max' => 'Budget maximum (€, 0 = aucun)',
        'budget.settings.depreciation' => 'Dépréciation du téléphone par mois (€)',
        'budget.settings.saved' => 'Paramètres enregistrés ; tous les budgets ont été recalculés.',
        'budget.import' => 'Importer Excel',
        'budget.import.file' => 'Fichier (.xls ou .xlsx)',
        'budget.import.preview' => 'Voir l\'aperçu',
        'budget.import.summary' => '%s lignes : %s nouvelles, %s déjà importées, %s en erreur.',
        'budget.import.persons' => 'Personnes dans le fichier',
        'budget.import.source' => 'Nom dans Excel',
        'budget.import.match' => 'Lier à',
        'budget.import.rows' => 'Lignes (nouvelles)',
        'budget.import.sure' => 'certain',
        'budget.import.unsure' => 'à vérifier',
        'budget.import.unknown' => 'non reconnu',
        'budget.import.skip' => '— Ignorer —',
        'budget.import.errors' => 'Lignes en erreur (ignorées)',
        'budget.import.run' => 'Importer',
        'budget.import.ask_start' => 'Date d\'entrée requise',
        'budget.import.ask_start_body' => '%s (%s) n\'a pas encore de date d\'entrée. L\'Excel indique cette personne comme « %s » avec %s achat(s) ; le premier date du %s.',
        'budget.import.next' => 'Suivant',
        'budget.import.cancel' => 'Annuler l\'import',
        'budget.import.done' => 'Import terminé : %s achats ajoutés, %s ignorés, %s personnes.',
        'budget.import.need_choice' => 'Choisissez un lien ou « Ignorer » pour chaque personne à vérifier.',
        'budget.import.error.columns' => 'Colonnes obligatoires introuvables (%s).',
        'budget.import.error.upload' => 'Téléversez un fichier .xls ou .xlsx.',
        'budget.import.error.expired' => 'La session d\'import a expiré ; téléversez à nouveau le fichier.',
        'budget.import.error.missing_start' => 'Date d\'entrée manquante pour : %s',
        'budget.error.amount_invalid' => 'Montant invalide.',
        'budget.error.date_invalid' => 'Date invalide.',
        'budget.error.person_invalid' => 'Personne inconnue.',
        'budget.error.no_start' => 'Cette personne n\'a pas encore de date d\'entrée.',
        'budget.error.purchase_not_found' => 'Achat introuvable.',
        'budget.error.status_transition' => 'Ce changement de statut n\'est pas possible.',
        'budget.error.link_wrong_person' => 'L\'achat n\'appartient pas à la personne à qui le téléphone est attribué.',
        'budget.error.link_taken' => 'Cet achat est déjà lié à un autre téléphone.',
        'budget.error.csrf' => 'Session expirée ou requête invalide ; rechargez la page.',
        'budget.hire.suggestion' => 'Prérempli depuis Microsoft 365 (employeeHireDate) : %s. Vérifiez ou modifiez ; rien n\'est enregistré avant l\'enregistrement.',
        'budget.hire.differs' => 'Microsoft 365 (employeeHireDate) indique %s.',
        'budget.hire.diagnose_title' => 'Date d\'entrée depuis Microsoft 365',
        'budget.hire.diagnose_hint' => 'Compte combien d\'utilisateurs affichés par Moirai ont employeeHireDate renseigné. Aucun nom ni aucune date n\'est affiché.',
        'budget.hire.diagnose' => 'Vérifier employeeHireDate',
        'budget.hire.result' => '%s utilisateurs, dont %s avec employeeHireDate (années %s).',
        'budget.hire.result_none' => '%s utilisateurs, aucun avec employeeHireDate.',
        'budget.hire.leave_ok' => 'employeeLeaveDateTime est lisible : %s renseigné(s).',
        'budget.hire.leave_no' => 'employeeLeaveDateTime n\'est pas lisible (%s) ; il faut User-LifeCycleInfo.Read.All.',
        'budget.hire.roles' => 'Autorisations de l\'app : %s',
        'budget.hire.failed' => 'Microsoft Graph a renvoyé une erreur : %s',
        'budget.add_person' => 'Ajouter une personne',
        'budget.field.email' => 'Adresse e-mail',
        'budget.field.name' => 'Nom',
        'budget.add_person.hint' => 'Également pour les collègues hors de la liste des utilisateurs Moirai (p. ex. @hunter.be ou sans fonction).',
        'budget.error.start_required' => 'Saisissez la date d\'entrée.',
        'budget.error.email_invalid' => 'Adresse e-mail invalide.',
        'budget.import.match_title' => 'Associer la personne',
        'budget.import.match_body' => 'Qui est « %s » dans le fichier Excel ? %s ligne(s), premier achat %s. Choisissez une suggestion ou saisissez une adresse e-mail.',
        'budget.import.suggestions' => 'Suggestions',
        'budget.import.link' => 'Associer',
        'budget.import.skip_person' => 'Ignorer',
        'budget.import.ask' => 'Demander lors de l\'import…',
        'budget.import.new_person' => 'nouveau',
        'moirai.label.search' => 'Rechercher',
        'moirai.label.status' => 'Statut',
        'moirai.placeholder.search' => 'Modèle, n° série, IMEI, accessoire, code SIM, numéro de téléphone, utilisateur…',
        'moirai.status.all' => 'Tous',
        'moirai.status.assigned' => 'Attribué',
        'moirai.status.reserve' => 'Réserve',
        'moirai.status.unavailable' => 'Indisponible',
        'moirai.filter.all' => '— Tous —',
        'moirai.filter.os' => 'OS',
        'moirai.filter.os_version' => 'Version OS',
        'moirai.filter.model' => 'Nom du modèle',
        'moirai.filter.ram' => 'RAM',
        'moirai.filter.storage' => 'Stockage',
        'moirai.filter.keyboard' => 'Clavier',
        'moirai.filter.screen' => 'Taille écran',
        'moirai.filter.condition' => 'État physique',
        'moirai.filter.name' => 'Nom du modèle',
        'moirai.filter.purchase_date' => 'Date d\'achat',
        'moirai.loader.devices' => 'Chargement des appareils…',
        'moirai.empty.devices' => 'Aucun appareil trouvé.',
        'moirai.badge.assigned' => 'Attribué à %s',
        'moirai.badge.reserve' => 'Réserve',
        'moirai.badge.unavailable' => 'Indisponible',
        'moirai.badge.condition' => 'État : %s',
        'moirai.modal.device' => 'Appareil',
        'moirai.modal.edit' => 'Modifier l\'appareil',
        'moirai.modal.new' => 'Nouvel appareil',
        'moirai.modal.assign' => 'Attribuer',
        'moirai.modal.history' => 'Historique des attributions',
        'moirai.btn.edit' => 'Modifier',
        'moirai.btn.assign' => 'Attribuer',
        'moirai.btn.history' => 'Historique',
        'moirai.btn.notes' => 'Notes',
        'moirai.btn.print_label' => 'Imprimer l\'étiquette',
        'moirai.notes.title' => 'Notes',
        'moirai.notes.messages' => 'Messages',
        'moirai.notes.empty' => 'Pas encore de notes. Écrivez le premier message ci-dessous.',
        'moirai.notes.message_label' => 'Message',
        'moirai.notes.edit_label' => 'Modifier le message',
        'moirai.notes.close' => 'Fermer',
        'moirai.notes.load_failed' => 'Impossible de charger les notes.',
        'moirai.notes.send_failed' => 'Impossible d\'envoyer le message.',
        'moirai.notes.save_failed' => 'Impossible d\'enregistrer la modification.',
        'moirai.notes.delete_failed' => 'Impossible de supprimer le message.',
        'moirai.notes.cancel_edit' => 'Annuler la modification',
        'moirai.notes.edited_suffix' => ' (modifié)',
        'moirai.notes.delete.confirm.title' => 'Supprimer la note',
        'moirai.notes.delete.confirm.body' => 'Ce message sera définitivement supprimé.',
        'moirai.notes.btn.delete_confirm' => 'Oui, supprimer',
        'moirai.print.ram' => 'RAM',
        'moirai.print.storage' => 'Stockage',
        'moirai.print.cpu' => 'CPU',
        'moirai.print.modelnumber' => 'Numéro de modèle',
        'moirai.print.purchased' => 'Acheté',
        'moirai.print.os' => 'OS',
        'moirai.print.keyboard' => 'Clavier',
        'moirai.print.screen' => 'Écran',
        'moirai.btn.save' => 'Enregistrer',
        'moirai.btn.cancel' => 'Annuler',
        'moirai.btn.delete' => 'Supprimer',
        'moirai.btn.close' => 'Fermer',
        'moirai.btn.refresh_users' => 'Actualiser la liste des utilisateurs',
        'moirai.field.naam' => 'Nom du modèle',
        'moirai.field.modelnummer' => 'Numéro de modèle',
        'moirai.field.model' => 'Nom du modèle',
        'moirai.field.serial' => 'Numéro de série',
        'moirai.field.imei' => 'IMEI',
        'moirai.field.sim_code' => 'Code SIM',
        'moirai.field.phone_number' => 'Numéro de téléphone',
        'moirai.field.accessory_id' => 'ID accessoire',
        'moirai.field.description' => 'Description',
        'moirai.field.ram' => 'Quantité RAM',
        'moirai.field.cpu' => 'Numéro modèle CPU',
        'moirai.field.purchase_date' => 'Date d\'achat',
        'moirai.field.os' => 'OS',
        'moirai.field.os_version' => 'Version OS',
        'moirai.field.keyboard' => 'Clavier',
        'moirai.field.screen' => 'Taille écran (pouces)',
        'moirai.field.condition' => 'État physique',
        'moirai.field.assigned_to' => 'Attribué à',
        'moirai.field.history' => 'Historique des attributions',
        'moirai.condition.uitstekend' => 'Excellent',
        'moirai.condition.netjes' => 'Propre',
        'moirai.condition.lichte_slijtage' => 'Usère usure',
        'moirai.condition.beschadigd' => 'Endommagé',
        'moirai.condition.phrase.uitstekend' => 'En excellent état',
        'moirai.condition.phrase.netjes' => 'En bon état',
        'moirai.condition.phrase.lichte_slijtage' => 'Usure visuelle due à l\'usage',
        'moirai.condition.phrase.beschadigd' => 'Dommages importants, mais fonctionnel et utilisable.',
        'moirai.select.choose' => '— Choisir —',
        'moirai.select.reserve' => '— Réserve —',
        'moirai.select.unavailable' => '— Indisponible —',
        'moirai.history.empty' => 'Aucun historique d\'attribution.',
        'moirai.history.current' => 'Attribution actuelle',
        'moirai.history.entry' => '%s · %s au %s',
        'moirai.history.since' => 'Depuis %s',
        'moirai.confirm.delete' => 'Voulez-vous vraiment supprimer cet appareil ?',
        'moirai.delete.confirm.title' => 'Supprimer l\'appareil',
        'moirai.delete.confirm.body' => 'Cet appareil sera supprimé définitivement. Cette action est irréversible.',
        'moirai.btn.delete_confirm' => 'Oui, supprimer définitivement',
        'moirai.unnamed' => 'Sans nom',
        'moirai.missing.fields' => 'Manquant',
        'moirai.outdated.label' => 'Appareil obsolète',
        'moirai.outdated.warning' => 'Cet appareil est obsolète et doit être remplacé.',
        'moirai.unknown_user' => 'Inconnu',
        'moirai.error.generic' => 'Une erreur s\'est produite. Réessayez plus tard.',
        'moirai.error.request_failed' => 'Échec de la requête.',
        'moirai.error.print_failed' => 'L\'étiquette n\'a pas pu être imprimée. posprint est-il installé ?',
        'moirai.error.forbidden' => 'Accès refusé.',
        'moirai.error.unauthorized' => 'Clé API invalide ou manquante.',
        'moirai.error.api_key_missing' => 'Clé API manquante. Envoyez X-API-Key, Authorization: Bearer, ou api_key dans le corps POST.',
        'moirai.error.api_key_query' => 'Les clés API ne doivent pas figurer dans la querystring.',
        'moirai.error.id_required' => 'L\'identifiant de l\'appareil est obligatoire.',
        'moirai.error.type_required' => 'Le type d\'appareil est obligatoire.',
        'moirai.error.invalid_input' => 'Entrée invalide.',
        'moirai.error.unknown_action' => 'Action inconnue.',
        'moirai.error.device_not_found' => 'Appareil introuvable.',
        'moirai.error.unknown_type' => 'Type d\'appareil inconnu.',
        'moirai.error.name_required' => 'Le nom du modèle est obligatoire.',
        'moirai.error.modelnummer_required' => 'Le numéro de modèle est obligatoire.',
        'moirai.error.model_required' => 'Le nom du modèle est obligatoire.',
        'moirai.error.serial_required' => 'Le numéro de série est obligatoire.',
        'moirai.error.imei_required' => 'L\'IMEI est obligatoire.',
        'moirai.error.serial_duplicate' => 'Ce numéro de série existe déjà.',
        'moirai.error.imei_duplicate' => 'Cet IMEI existe déjà.',
        'moirai.error.assign_invalid_user' => 'Attribué à doit être un utilisateur valide de l\'annuaire.',
        'moirai.error.users_fetch' => 'Impossible de récupérer la liste des utilisateurs.',
        'moirai.error.save_failed' => 'L\'appareil n\'a pas pu être enregistré.',
        'moirai.error.ram_invalid' => 'La RAM doit être une quantité valide (ex. 16 GB ou 8192 MB).',
        'moirai.error.opslag_invalid' => 'La capacité de stockage doit être une quantité valide (ex. 512 GB ou 256 GB).',
        'moirai.error.date_invalid' => 'La date d\'achat est invalide.',
        'moirai.error.date_future' => 'La date d\'achat ne peut pas être dans le futur.',
        'moirai.error.screen_invalid' => 'La taille d\'écran doit être une valeur en pouces valide (p. ex. 6.1 ou 6.1 inch).',
        'moirai.error.os_laptop_invalid' => 'L\'OS doit être Windows, OSX ou Linux.',
        'moirai.error.keyboard_invalid' => 'Choisissez une disposition de clavier valide.',
        'moirai.error.os_phone_invalid' => 'L\'OS doit être Android ou iOS.',
        'moirai.error.condition_invalid' => 'Choisissez un état physique valide.',
        'moirai.error.note_empty' => 'Le message ne peut pas être vide.',
        'moirai.error.note_too_long' => 'Le message est trop long.',
        'moirai.error.note_not_found' => 'Note introuvable.',
        'moirai.error.sim_code_required' => 'Le code SIM est obligatoire.',
        'moirai.error.phone_required' => 'Le numéro de téléphone est obligatoire.',
        'moirai.error.phone_invalid' => 'Numéro de téléphone invalide. Utilisez par ex. 06 12345678, +31 6 12345678 ou 0031 6 12345678.',
        'moirai.error.sim_code_duplicate' => 'Une carte SIM avec le code %s existe déjà.',
        'moirai.error.phone_duplicate' => 'Le numéro %s est déjà lié à la carte SIM %s.',
        'moirai.error.sim_duplicate' => 'Cette carte SIM (code ou numéro) existe déjà.',
        'moirai.error.print_not_supported' => 'Impossible d\'imprimer une étiquette pour une carte SIM.',
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

function renderMoiraiLanguageRail(): void
{
    $current = getCurrentLanguage();
    $menuAria = htmlspecialchars(LOC('lang.menu_aria'), ENT_QUOTES);

    echo '<div class="lang-rail" data-lang-rail>';
    echo '<div class="lang-rail-choices" aria-hidden="true">';
    foreach (SUPPORTED_LANGUAGES as $code => $meta) {
        if ($code === $current) {
            continue;
        }
        $label = (string) ($meta['label'] ?? $code);
        $href = htmlspecialchars(localizationUrlWithLang($code), ENT_QUOTES);
        $title = htmlspecialchars(LOC('lang.switch_to', $label), ENT_QUOTES);
        echo '<a class="lang-rail-flag" href="' . $href . '" title="' . $title . '" aria-label="' . $title . '">';
        echo localizationFlagSvg($code);
        echo '</a>';
    }
    echo '</div>';
    echo '<button type="button" class="lang-rail-flag lang-rail-current" aria-haspopup="true" aria-expanded="false" aria-label="' . $menuAria . '">';
    echo localizationFlagSvg($current);
    echo '</button>';
    echo '</div>';
}

function renderMoiraiLanguageRailStyles(): void
{
    echo <<<'CSS'
<style>
.lang-rail {
    position: fixed;
    top: 12px;
    right: 12px;
    z-index: 5000;
    display: flex;
    align-items: center;
    justify-content: flex-end;
}
.lang-rail-choices {
    display: flex;
    align-items: center;
    gap: 6px;
    max-width: 0;
    opacity: 0;
    overflow: hidden;
    transition: max-width 0.25s ease, opacity 0.2s ease, margin 0.25s ease;
    margin-right: 0;
}
.lang-rail.is-open .lang-rail-choices {
    max-width: 220px;
    opacity: 1;
    margin-right: 8px;
}
.lang-rail-flag {
    display: block;
    width: 45px;
    height: 30px;
    padding: 0;
    border: 1px solid rgba(0, 82, 155, 0.25);
    border-radius: 4px;
    background: transparent;
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.12);
    cursor: pointer;
    overflow: hidden;
    flex-shrink: 0;
    line-height: 0;
}
button.lang-rail-flag {
    appearance: none;
}
.lang-rail-flag svg {
    width: 100%;
    height: 100%;
    display: block;
    vertical-align: top;
}
a.lang-rail-flag {
    text-decoration: none;
}
.lang-rail-flag:hover {
    box-shadow: 0 4px 18px rgba(15, 23, 42, 0.18);
}
@media print {
    .lang-rail { display: none !important; }
}
</style>
CSS;
}

function renderMoiraiLanguageRailScript(): void
{
    echo <<<'JS'
<script>
(function () {
    document.querySelectorAll('[data-lang-rail]').forEach(function (root) {
        var toggle = root.querySelector('.lang-rail-current');
        var choices = root.querySelector('.lang-rail-choices');
        if (!toggle || !choices) {
            return;
        }

        toggle.addEventListener('click', function (event) {
            event.stopPropagation();
            var isOpen = root.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            choices.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
        });

        document.addEventListener('click', function () {
            root.classList.remove('is-open');
            toggle.setAttribute('aria-expanded', 'false');
            choices.setAttribute('aria-hidden', 'true');
        });

        root.addEventListener('click', function (event) {
            event.stopPropagation();
        });
    });
})();
</script>
JS;
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
    width: 45px;
    height: 30px;
    padding: 0;
    border: 1px solid rgba(0, 82, 155, 0.25);
    border-radius: 4px;
    background: transparent;
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.12);
    cursor: pointer;
    line-height: 0;
    overflow: hidden;
}
.lang-switcher-toggle:hover {
    box-shadow: 0 4px 18px rgba(15, 23, 42, 0.18);
}
.lang-switcher-toggle svg {
    width: 100%;
    height: 100%;
    display: block;
    vertical-align: top;
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
    width: 45px;
    height: 30px;
    flex-shrink: 0;
    border-radius: 4px;
    overflow: hidden;
    display: block;
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
