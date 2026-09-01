<?php
/*
 * Created on   : Tue Sep 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : help.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Centro assistenza (funzionalità 039, MVP-752): sezioni della pagina di panoramica.
return [
    // Artikelschema der Pilotartikel (MVP-756) — Reihenfolge wie
    // config('help-center.article_schema').
    'schema' => [
        'zweck' => 'Scopo e contesto',
        'voraussetzungen' => 'Prerequisiti',
        'ablauf' => 'Procedura consigliata',
        'beispiel' => 'Esempio pratico',
        'fehler' => 'Errori tipici',
        'naechste-schritte' => 'Effetti e prossimi passi',
    ],
    'sections' => [
        'erste-schritte' => [
            'title' => 'Primi passi',
            'description' => 'Accesso, dashboard, navigazione, impostazioni personali e i passaggi più importanti per iniziare.',
        ],
        'kunden-vertrieb' => [
            'title' => 'Clienti & vendite',
            'description' => 'Anagrafica clienti, fascicolo cliente, progetti, portale clienti, appuntamenti e temi commerciali.',
        ],
        'zeit-personal' => [
            'title' => 'Tempo & personale',
            'description' => 'Timbratura, registrazioni orarie, assenze, pianificazione dei turni, conti ore ed export paghe.',
        ],
        'auftraege-service' => [
            'title' => 'Commesse & assistenza',
            'description' => 'Registro commesse, verbali, procedure, moduli, helpdesk e temi di cantiere.',
        ],
        'material-lager' => [
            'title' => 'Articoli & magazzino',
            'description' => 'Anagrafica articoli, cataloghi, giacenze, approvvigionamento, prezzi e numeri di serie.',
        ],
        'geraete-fuhrpark' => [
            'title' => 'Attrezzature & parco mezzi',
            'description' => 'Fascicolo attrezzature, verifiche, veicoli, consegne chiavi, garanzie e software.',
        ],
        'faktura' => [
            'title' => 'Fatture & fatturazione',
            'description' => 'Preventivi, fatture, fatturazione elettronica, contratti, flusso documentale e provvigioni.',
        ],
        'buchhaltung' => [
            'title' => 'Contabilità & finanze',
            'description' => 'Giornale, piano dei conti, chiusura, conti bancari, export DATEV ed export dei tempi.',
        ],
        'auswertungen' => [
            'title' => 'Analisi',
            'description' => 'Report, approfondimenti, esportazioni e corretta lettura degli indicatori.',
        ],
        'sicherheit-compliance' => [
            'title' => 'Sicurezza & conformità',
            'description' => 'ISMS, protezione dei dati, whistleblowing, sicurezza sul lavoro, audit e archivio.',
        ],
        'administration' => [
            'title' => 'Amministrazione',
            'description' => 'Organizzazione, ruoli e permessi, importazione, backup, licenza e integrazioni.',
        ],
        'weitere' => [
            'title' => 'Altri argomenti',
            'description' => 'Tutto ciò che non rientra in una delle aree principali.',
        ],
    ],
];
