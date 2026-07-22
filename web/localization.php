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
        'moirai.label.search' => 'Zoeken',
        'moirai.label.status' => 'Status',
        'moirai.placeholder.search' => 'Model, serienummer, IMEI, gebruiker…',
        'moirai.status.all' => 'Alles',
        'moirai.status.assigned' => 'Uitgegeven',
        'moirai.status.reserve' => 'Reserve',
        'moirai.filter.all' => '— Alles —',
        'moirai.filter.os' => 'OS',
        'moirai.filter.os_version' => 'OS versie',
        'moirai.filter.model' => 'Modelnaam',
        'moirai.filter.ram' => 'RAM',
        'moirai.filter.storage' => 'Opslag',
        'moirai.filter.keyboard' => 'Toetsenbord',
        'moirai.filter.screen' => 'Schermformaat',
        'moirai.loader.devices' => 'Apparaten laden…',
        'moirai.empty.devices' => 'Geen apparaten gevonden.',
        'moirai.badge.assigned' => 'Uitgegeven aan %s',
        'moirai.badge.reserve' => 'Reserve',
        'moirai.modal.device' => 'Apparaat',
        'moirai.modal.edit' => 'Apparaat bewerken',
        'moirai.modal.new' => 'Nieuw apparaat',
        'moirai.modal.assign' => 'Toewijzen',
        'moirai.modal.history' => 'Uitgiftehistorie',
        'moirai.btn.edit' => 'Bewerken',
        'moirai.btn.assign' => 'Toewijzen',
        'moirai.btn.history' => 'Historie',
        'moirai.btn.print_label' => 'Print label',
        'moirai.print.ram' => 'RAM',
        'moirai.print.storage' => 'Opslaggrootte',
        'moirai.print.cpu' => 'CPU',
        'moirai.print.purchased' => 'Gekocht',
        'moirai.print.os' => 'OS',
        'moirai.print.keyboard' => 'Toetsenbord',
        'moirai.print.screen' => 'Scherm',
        'moirai.btn.save' => 'Opslaan',
        'moirai.btn.cancel' => 'Annuleren',
        'moirai.btn.delete' => 'Verwijderen',
        'moirai.btn.close' => 'Sluiten',
        'moirai.field.naam' => 'Apparaatnaam',
        'moirai.field.model' => 'Modelnaam',
        'moirai.field.serial' => 'Serienummer',
        'moirai.field.imei' => 'IMEI',
        'moirai.field.ram' => 'RAM hoeveelheid',
        'moirai.field.storage' => 'Opslaggrootte',
        'moirai.field.cpu' => 'CPU modelnummer',
        'moirai.field.purchase_date' => 'Aanschafdatum',
        'moirai.field.os' => 'OS',
        'moirai.field.os_version' => 'OS versie',
        'moirai.field.keyboard' => 'Toetsenbord',
        'moirai.field.screen' => 'Schermformaat (inch)',
        'moirai.field.assigned_to' => 'Uitgegeven aan',
        'moirai.field.history' => 'Historie uitgegeven',
        'moirai.select.choose' => '— Kies —',
        'moirai.select.reserve' => '— Reserve —',
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
        'moirai.unknown_user' => 'Onbekend',
        'moirai.error.generic' => 'Er ging iets mis. Probeer het later opnieuw.',
        'moirai.error.request_failed' => 'Verzoek mislukt.',
        'moirai.error.print_failed' => 'Label kon niet worden geprint. Is posprint geïnstalleerd?',
        'moirai.error.forbidden' => 'Geen rechten.',
        'moirai.error.invalid_input' => 'Ongeldige invoer.',
        'moirai.error.unknown_action' => 'Onbekende actie.',
        'moirai.error.device_not_found' => 'Apparaat niet gevonden.',
        'moirai.error.unknown_type' => 'Onbekend apparaattype.',
        'moirai.error.name_required' => 'Apparaatnaam is verplicht.',
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
        'moirai.label.search' => 'Search',
        'moirai.label.status' => 'Status',
        'moirai.placeholder.search' => 'Model, serial, IMEI, user…',
        'moirai.status.all' => 'All',
        'moirai.status.assigned' => 'Issued',
        'moirai.status.reserve' => 'Reserve',
        'moirai.filter.all' => '— All —',
        'moirai.filter.os' => 'OS',
        'moirai.filter.os_version' => 'OS version',
        'moirai.filter.model' => 'Model name',
        'moirai.filter.ram' => 'RAM',
        'moirai.filter.storage' => 'Storage',
        'moirai.filter.keyboard' => 'Keyboard',
        'moirai.filter.screen' => 'Screen size',
        'moirai.loader.devices' => 'Loading devices…',
        'moirai.empty.devices' => 'No devices found.',
        'moirai.badge.assigned' => 'Issued to %s',
        'moirai.badge.reserve' => 'Reserve',
        'moirai.modal.device' => 'Device',
        'moirai.modal.edit' => 'Edit device',
        'moirai.modal.new' => 'New device',
        'moirai.modal.assign' => 'Assign',
        'moirai.modal.history' => 'Issue history',
        'moirai.btn.edit' => 'Edit',
        'moirai.btn.assign' => 'Assign',
        'moirai.btn.history' => 'History',
        'moirai.btn.print_label' => 'Print label',
        'moirai.print.ram' => 'RAM',
        'moirai.print.storage' => 'Storage',
        'moirai.print.cpu' => 'CPU',
        'moirai.print.purchased' => 'Purchased',
        'moirai.print.os' => 'OS',
        'moirai.print.keyboard' => 'Keyboard',
        'moirai.print.screen' => 'Screen',
        'moirai.btn.save' => 'Save',
        'moirai.btn.cancel' => 'Cancel',
        'moirai.btn.delete' => 'Delete',
        'moirai.btn.close' => 'Close',
        'moirai.field.naam' => 'Device name',
        'moirai.field.model' => 'Model name',
        'moirai.field.serial' => 'Serial number',
        'moirai.field.imei' => 'IMEI',
        'moirai.field.ram' => 'RAM amount',
        'moirai.field.storage' => 'Storage capacity',
        'moirai.field.cpu' => 'CPU model number',
        'moirai.field.purchase_date' => 'Purchase date',
        'moirai.field.os' => 'OS',
        'moirai.field.os_version' => 'OS version',
        'moirai.field.keyboard' => 'Keyboard',
        'moirai.field.screen' => 'Screen size (inches)',
        'moirai.field.assigned_to' => 'Issued to',
        'moirai.field.history' => 'Issue history',
        'moirai.select.choose' => '— Choose —',
        'moirai.select.reserve' => '— Reserve —',
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
        'moirai.unknown_user' => 'Unknown',
        'moirai.error.generic' => 'Something went wrong. Please try again later.',
        'moirai.error.request_failed' => 'Request failed.',
        'moirai.error.print_failed' => 'Label could not be printed. Is posprint installed?',
        'moirai.error.forbidden' => 'Access denied.',
        'moirai.error.invalid_input' => 'Invalid input.',
        'moirai.error.unknown_action' => 'Unknown action.',
        'moirai.error.device_not_found' => 'Device not found.',
        'moirai.error.unknown_type' => 'Unknown device type.',
        'moirai.error.name_required' => 'Device name is required.',
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
        'moirai.label.search' => 'Suchen',
        'moirai.label.status' => 'Status',
        'moirai.placeholder.search' => 'Modell, Seriennummer, IMEI, Benutzer…',
        'moirai.status.all' => 'Alle',
        'moirai.status.assigned' => 'Ausgegeben',
        'moirai.status.reserve' => 'Reserve',
        'moirai.filter.all' => '— Alle —',
        'moirai.filter.os' => 'OS',
        'moirai.filter.os_version' => 'OS-Version',
        'moirai.filter.model' => 'Modellname',
        'moirai.filter.ram' => 'RAM',
        'moirai.filter.storage' => 'Speicher',
        'moirai.filter.keyboard' => 'Tastatur',
        'moirai.filter.screen' => 'Bildschirmgröße',
        'moirai.loader.devices' => 'Geräte werden geladen…',
        'moirai.empty.devices' => 'Keine Geräte gefunden.',
        'moirai.badge.assigned' => 'Ausgegeben an %s',
        'moirai.badge.reserve' => 'Reserve',
        'moirai.modal.device' => 'Gerät',
        'moirai.modal.edit' => 'Gerät bearbeiten',
        'moirai.modal.new' => 'Neues Gerät',
        'moirai.modal.assign' => 'Zuweisen',
        'moirai.modal.history' => 'Ausgabeverlauf',
        'moirai.btn.edit' => 'Bearbeiten',
        'moirai.btn.assign' => 'Zuweisen',
        'moirai.btn.history' => 'Verlauf',
        'moirai.btn.print_label' => 'Etikett drucken',
        'moirai.print.ram' => 'RAM',
        'moirai.print.storage' => 'Speicher',
        'moirai.print.cpu' => 'CPU',
        'moirai.print.purchased' => 'Gekauft',
        'moirai.print.os' => 'OS',
        'moirai.print.keyboard' => 'Tastatur',
        'moirai.print.screen' => 'Display',
        'moirai.btn.save' => 'Speichern',
        'moirai.btn.cancel' => 'Abbrechen',
        'moirai.btn.delete' => 'Löschen',
        'moirai.btn.close' => 'Schließen',
        'moirai.field.naam' => 'Gerätename',
        'moirai.field.model' => 'Modellname',
        'moirai.field.serial' => 'Seriennummer',
        'moirai.field.imei' => 'IMEI',
        'moirai.field.ram' => 'RAM-Menge',
        'moirai.field.storage' => 'Speicherkapazität',
        'moirai.field.cpu' => 'CPU-Modellnummer',
        'moirai.field.purchase_date' => 'Anschaffungsdatum',
        'moirai.field.os' => 'OS',
        'moirai.field.os_version' => 'OS-Version',
        'moirai.field.keyboard' => 'Tastatur',
        'moirai.field.screen' => 'Bildschirmgröße (Zoll)',
        'moirai.field.assigned_to' => 'Ausgegeben an',
        'moirai.field.history' => 'Ausgabeverlauf',
        'moirai.select.choose' => '— Wählen —',
        'moirai.select.reserve' => '— Reserve —',
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
        'moirai.unknown_user' => 'Unbekannt',
        'moirai.error.generic' => 'Etwas ist schiefgelaufen. Bitte später erneut versuchen.',
        'moirai.error.request_failed' => 'Anfrage fehlgeschlagen.',
        'moirai.error.print_failed' => 'Etikett konnte nicht gedruckt werden. Ist posprint installiert?',
        'moirai.error.forbidden' => 'Keine Berechtigung.',
        'moirai.error.invalid_input' => 'Ungültige Eingabe.',
        'moirai.error.unknown_action' => 'Unbekannte Aktion.',
        'moirai.error.device_not_found' => 'Gerät nicht gefunden.',
        'moirai.error.unknown_type' => 'Unbekannter Gerätetyp.',
        'moirai.error.name_required' => 'Gerätename ist erforderlich.',
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
        'moirai.label.search' => 'Rechercher',
        'moirai.label.status' => 'Statut',
        'moirai.placeholder.search' => 'Modèle, n° série, IMEI, utilisateur…',
        'moirai.status.all' => 'Tous',
        'moirai.status.assigned' => 'Attribué',
        'moirai.status.reserve' => 'Réserve',
        'moirai.filter.all' => '— Tous —',
        'moirai.filter.os' => 'OS',
        'moirai.filter.os_version' => 'Version OS',
        'moirai.filter.model' => 'Nom du modèle',
        'moirai.filter.ram' => 'RAM',
        'moirai.filter.storage' => 'Stockage',
        'moirai.filter.keyboard' => 'Clavier',
        'moirai.filter.screen' => 'Taille écran',
        'moirai.loader.devices' => 'Chargement des appareils…',
        'moirai.empty.devices' => 'Aucun appareil trouvé.',
        'moirai.badge.assigned' => 'Attribué à %s',
        'moirai.badge.reserve' => 'Réserve',
        'moirai.modal.device' => 'Appareil',
        'moirai.modal.edit' => 'Modifier l\'appareil',
        'moirai.modal.new' => 'Nouvel appareil',
        'moirai.modal.assign' => 'Attribuer',
        'moirai.modal.history' => 'Historique des attributions',
        'moirai.btn.edit' => 'Modifier',
        'moirai.btn.assign' => 'Attribuer',
        'moirai.btn.history' => 'Historique',
        'moirai.btn.print_label' => 'Imprimer l\'étiquette',
        'moirai.print.ram' => 'RAM',
        'moirai.print.storage' => 'Stockage',
        'moirai.print.cpu' => 'CPU',
        'moirai.print.purchased' => 'Acheté',
        'moirai.print.os' => 'OS',
        'moirai.print.keyboard' => 'Clavier',
        'moirai.print.screen' => 'Écran',
        'moirai.btn.save' => 'Enregistrer',
        'moirai.btn.cancel' => 'Annuler',
        'moirai.btn.delete' => 'Supprimer',
        'moirai.btn.close' => 'Fermer',
        'moirai.field.naam' => 'Nom de l\'appareil',
        'moirai.field.model' => 'Nom du modèle',
        'moirai.field.serial' => 'Numéro de série',
        'moirai.field.imei' => 'IMEI',
        'moirai.field.ram' => 'Quantité RAM',
        'moirai.field.cpu' => 'Numéro modèle CPU',
        'moirai.field.purchase_date' => 'Date d\'achat',
        'moirai.field.os' => 'OS',
        'moirai.field.os_version' => 'Version OS',
        'moirai.field.keyboard' => 'Clavier',
        'moirai.field.screen' => 'Taille écran (pouces)',
        'moirai.field.assigned_to' => 'Attribué à',
        'moirai.field.history' => 'Historique des attributions',
        'moirai.select.choose' => '— Choisir —',
        'moirai.select.reserve' => '— Réserve —',
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
        'moirai.unknown_user' => 'Inconnu',
        'moirai.error.generic' => 'Une erreur s\'est produite. Réessayez plus tard.',
        'moirai.error.request_failed' => 'Échec de la requête.',
        'moirai.error.print_failed' => 'L\'étiquette n\'a pas pu être imprimée. posprint est-il installé ?',
        'moirai.error.forbidden' => 'Accès refusé.',
        'moirai.error.invalid_input' => 'Entrée invalide.',
        'moirai.error.unknown_action' => 'Action inconnue.',
        'moirai.error.device_not_found' => 'Appareil introuvable.',
        'moirai.error.unknown_type' => 'Type d\'appareil inconnu.',
        'moirai.error.name_required' => 'Le nom de l\'appareil est obligatoire.',
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
