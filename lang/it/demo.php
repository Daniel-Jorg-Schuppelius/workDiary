<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : demo.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Contenuti dei blocchi demo (MVP-1532/1533). Salvati nella lingua di chi avvia il seed.
return [
    'schedule' => [
        'shift_type' => [
            'early' => 'Turno del mattino',
            'late' => 'Turno serale',
            'office' => 'Turno d’ufficio',
        ],
        'plan_current' => 'Turni di questa settimana e della prossima (demo)',
        'plan_next' => 'Turni tra due settimane (demo)',
        'coverage_note' => 'Nel turno del mattino sono previste almeno due persone nei giorni feriali.',
        'exchange_reason' => 'Impegno privato nel pomeriggio, scambio concordato con il turno del mattino.',
    ],
    'forms' => [
        'report' => [
            'name' => 'Rapporto d’intervento (demo)',
            'description' => 'Breve rapporto dopo ogni intervento presso il cliente.',
            'work_done' => 'Lavori eseguiti',
            'work_done_value' => 'Manutenzione eseguita secondo la checklist, nessun difetto riscontrato.',
            'hours_on_site' => 'Tempo sul posto',
            'result' => 'Esito',
            'result_done' => 'Completato',
            'result_follow_up' => 'Necessario un intervento successivo',
            'result_material' => 'Materiale mancante',
            'briefed' => 'Cliente istruito',
            'satisfaction' => 'Soddisfazione del cliente',
        ],
        'safety' => [
            'name' => 'Valutazione dei rischi prima dell’inizio dei lavori (demo)',
            'description' => 'Breve controllo sul posto prima di iniziare il lavoro.',
            'ppe_complete' => 'Dispositivi di protezione completi',
            'area_secured' => 'Area di lavoro messa in sicurezza',
            'hazards' => 'Rischi particolari',
            'hazards_value' => 'Attività del cliente in corso, area di lavoro delimitata con nastro segnaletico.',
        ],
        'handover' => [
            'name' => 'Passaggio di consegne prima delle ferie (demo)',
            'description' => 'Bozza: attività aperte e referenti per la sostituzione.',
            'open_tasks' => 'Attività aperte',
            'contact' => 'Referente presso il cliente',
        ],
        // Langer Bogen (Feature 203): Abschnitte, Speichern je Feld, Entwurf.
        'intake' => [
            'name' => 'Prima accoglienza (demo)',
            'description' => 'Modulo lungo con sezioni: ogni risposta viene salvata quando si lascia il campo, l’avanzamento è indicato per sezione.',
            'section_contact' => 'Contatto',
            'person' => 'Persona di contatto',
            'channel' => 'Canale preferito',
            'channel_phone' => 'Telefono',
            'channel_mail' => 'E-mail',
            'channel_visit' => 'Sul posto',
            'section_request' => 'Richiesta',
            'request' => 'Di che cosa si tratta?',
            'urgent' => 'Urgente',
            'section_risk' => 'Valutazione',
            'risk' => 'Rischio',
            'risk_low' => 'Basso',
            'risk_medium' => 'Medio',
            'risk_high' => 'Alto',
            'documents_checked' => 'Documenti verificati',
            'section_close' => 'Chiusura',
            'next_step' => 'Passo successivo',
            'follow_up' => 'Ricontrollo il',
            'person_value' => 'Sig.ra Esempio',
            'request_value' => 'Verifica dei documenti prima dell’inizio del contratto.',
        ],
    ],
    'knowledge' => [
        'guide_title' => 'Guida: :subject',
        'guide_solution' => 'Procedura di collaudo, da esaminare punto per punto con il cliente:',
        'issue_solution' => 'Concordare un appuntamento con il cliente, eseguire la verifica e documentare l’esito nell’ordine. Poi chiudere il punto aperto.',
        'onboarding_title' => 'Prima settimana per i nuovi colleghi',
        'onboarding_problem' => 'Il nuovo personale non sa dove si trovano ordini, tempi e documenti.',
        'onboarding_solution' => 'Bozza: giro di ordini, registrazione dei tempi e base di conoscenza; un referente fisso per le prime due settimane.',
    ],
    'inventory' => [
        'warehouse_main' => 'Magazzino principale (demo)',
        'warehouse_main_note' => 'File di scaffali A–C al piano terra',
        'warehouse_vehicle' => 'Veicolo di servizio 1 (demo)',
    ],
    'travel' => [
        'vendor' => [
            'parking' => 'Parcheggio del centro',
            'lodging' => 'Hotel del Mercato',
            'supplies' => 'Ferramenta di esempio',
            'hospitality' => 'Ristorante di esempio',
        ],
        'expense' => [
            'parking' => 'Parcheggio per l’appuntamento con il cliente a :city',
            'hotel' => 'Pernottamento per l’intervento a :city',
            'material' => 'Materiale di fissaggio per l’intervento',
            'hospitality' => 'Pranzo di lavoro con il cliente',
        ],
        'from_address' => 'Sede aziendale',
        'trip_purpose' => 'Appuntamento con il cliente :customer',
    ],
    'location' => [
        'zone_label' => ':customer (luogo d’intervento demo)',
    ],
    'contracts' => [
        'maintenance_title' => 'Contratto di manutenzione :customer (demo)',
        'maintenance_notes' => 'Canone mensile forfettario, rinnovo di dodici mesi salvo disdetta.',
        'review_title' => 'Colloquio annuale sull’ambito dei servizi',
        'license_title' => 'Licenza software per l’ufficio (demo)',
        'license_partner' => 'Software Esempio S.r.l.',
        'payment_title' => 'Verificare la fattura annuale della licenza',
        'carry_forward_note' => 'Prima del rinnovo confrontare il numero di account utente (demo).',
        'rent_title' => 'Contratto di locazione per superficie di magazzino (demo)',
        'rent_partner' => 'Parco Commerciale Esempio S.r.l.',
    ],
    'facility' => [
        'site_name' => ':customer – sede principale',
        'street' => 'Via Esempio 1',
        'building' => 'Edificio principale',
        'floor' => [
            'ground' => 'Piano terra',
            'first' => 'Primo piano',
        ],
        'room' => [
            'reception' => 'Reception',
            'technical' => 'Locale tecnico',
            'storage' => 'Magazzino',
            'office' => 'Ufficio',
            'meeting' => 'Sala riunioni',
            'social' => 'Sala pausa',
        ],
        'access_note' => 'Accesso solo con la chiave della reception.',
    ],
    'bau' => [
        'boq_name' => 'Computo metrico :project (demo)',
        'section_supply' => 'Fornitura',
        'section_labour' => 'Montaggio e manodopera',
        'item_assembly' => 'Montaggio e messa in servizio',
        'item_assembly_long' => 'Montaggio di tutte le voci di fornitura, compresi collaudo funzionale e istruzione.',
        'item_hourly' => 'Lavori in economia su documentazione',
        'progress_note' => 'Prima misurazione dopo la consegna',
    ],
    'team' => [
        'description' => 'Lavoro sul progetto :project',
    ],
];
