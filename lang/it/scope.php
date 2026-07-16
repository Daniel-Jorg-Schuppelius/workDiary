<?php
/*
 * Created on   : Wed Jul 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : scope.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    // Aree di lavoro — viste con focus commutabili (Feature 082).
    'focus' => [
        'admin' => [
            'title' => 'Aree di lavoro',
            'subtitle' => 'Scegli quali aree di lavoro l’organizzazione offre nel selettore, rinominale e imposta una predefinita.',
            'hint' => 'Solo un suggerimento: l’area predefinita non è mai imposta — chiunque può cambiare in qualsiasi momento. Nascondere non modifica alcun permesso.',
            'list_heading' => 'Aree offerte',
            'configured_at' => 'Ultima modifica: :date',
            'mandatory' => 'sempre disponibile',
            'is_default' => 'Predefinita',
            'rename' => 'Nome visualizzato',
            'offered' => 'offerta',
            'set_default' => 'Predefinita',
            'saved' => 'Aree di lavoro salvate.',
        ],
        'switcher' => 'Cambia area di lavoro',
        'eyebrow' => 'Area di lavoro',
        'all' => 'Mostra tutto',
        'active' => 'Attiva',
        'reveal' => 'Mostra tutto',
        'reveal_off' => 'Mostra solo il focus',
        'dialog' => [
            'eyebrow' => 'Focalizza la vista',
            'title' => 'A cosa stai lavorando?',
            'subtitle' => 'Scegli un’area di lavoro — la navigazione mostrerà solo gli ambiti pertinenti. Nulla viene eliminato o bloccato; puoi cambiare in qualsiasi momento.',
            'footnote' => 'Gli ambiti nascosti restano raggiungibili tramite la ricerca globale e «Mostra tutto».',
        ],
        'flash' => [
            'unknown' => 'Area di lavoro sconosciuta.',
            'switched' => 'Area di lavoro «:name» attiva.',
        ],
    ],
    'title' => [
        'index' => 'Ambito funzionale',
    ],
    'nav' => [
        'customize' => 'Personalizza menu',
        'functions' => 'Tutte le funzioni',
    ],
    'page' => [
        'subtitle' => 'Definisci l\'ambito funzionale visibile dell\'organizzazione: preset per partire subito oppure moduli singoli.',
        'no_data_loss' => 'La disattivazione nasconde soltanto i moduli e blocca le loro pagine: nessun dato viene eliminato. Alla riattivazione torna tutto disponibile.',
    ],
    'presets' => [
        'heading' => 'Preset',
        'hint' => 'Un preset è una scorciatoia: imposta in un solo passaggio la lista dei moduli qui sotto. Dopo puoi rifinire singolarmente.',
        'apply' => 'Applica il preset «:preset»',
        'all_modules' => 'Tutti i moduli con licenza',
        'module_count' => '{1} :count modulo aggiuntivo|[2,*] :count moduli aggiuntivi',
    ],
    'recommendation' => [
        'heading' => 'Raccomandazione dal profilo di settore',
        'hint' => 'Il profilo di settore installato «:profile» consiglia i seguenti moduli.',
        'apply' => 'Applica la raccomandazione',
    ],
    'modules' => [
        'heading' => 'Imposta i moduli singolarmente',
        'configured_at' => 'Ultima configurazione: :date',
        'not_licensed_hint' => 'Non incluso nel piano attuale; ampliabile tramite la gestione licenze.',
    ],
    'flash' => [
        'saved' => 'Ambito funzionale salvato (:disabled disattivati, :enabled attivati). Nessun dato eliminato.',
        'no_recommendation' => 'Per questa organizzazione non esiste una raccomandazione del profilo di settore.',
    ],
    'customize' => [
        'subtitle' => 'Attiva ciò che deve comparire nel menu — disattiva ciò che non ti serve. Vale solo per te, su tutti i dispositivi.',
        'cosmetic_hint' => 'Nascondere non cambia i permessi: ricerca, segnalibri e link diretti continuano a funzionare. Con «Tutte le funzioni» recuperi tutto.',
        'sidebar_heading' => 'Navigazione laterale',
        'hide_section' => 'nascondi l\'intera sezione',
        'hide_group' => 'nascondi sottogruppo',
        'create_heading' => 'Creazione rapida («Nuovo …»)',
        'create_hint' => 'I gruppi nascosti non compaiono più nel menu «Nuovo …» della barra laterale.',
        'checkbox_hint' => 'Attivo = visibile nel menu.',
        'saved' => 'Personalizzazione del menu salvata.',
        'unhidden' => 'La voce è di nuovo visibile.',
    ],
    'functions' => [
        'focus_banner' => 'Area di lavoro attiva «:name». Gli ambiti nascosti sono indicati sotto — qui restano raggiungibili.',
        'in_focus_hidden' => 'Nascosto dall’area di lavoro',
        'show_all' => 'Mostra tutto',
        'subtitle' => 'Panoramica di tutte le aree con il loro stato, incluso ciò che è nascosto, disattivato o senza licenza.',
        'state' => [
            'hidden_section' => 'Sezione nascosta',
            'org_disabled' => 'Disattivato dall\'organizzazione',
            'hidden_by_me' => 'Nascosto da me',
        ],
        'action' => [
            'unhide' => 'Mostra',
            'enable_module' => 'Apri l\'ambito funzionale',
        ],
        'upsell_hint' => 'Questo modulo non è incluso nel piano attuale.',
    ],
];
