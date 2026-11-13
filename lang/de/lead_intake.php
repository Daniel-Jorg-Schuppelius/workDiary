<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : lead_intake.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Aufnahmeakte mit Prüfpaketen (Feature 200, MVP-1641–1644, MVP-1675).
return [
    'title' => 'Aufnahmeakte',
    'title_for' => 'Aufnahmeakte: :name',
    'index_title' => 'Aufnahmen',
    'index_subtitle' => 'Aufnahmeakten der Leads von der Anfrage bis zur Einrichtung des Kunden.',
    'back_to_lead' => 'Zum Lead',
    'revision' => 'Fassung :revision',
    'superseded' => 'Abgelöst',
    'stages' => 'Stufen',
    'current_stage' => 'Aktuelle Stufe',

    'action' => [
        'open' => 'Aufnahme öffnen',
        'decide' => 'Entscheiden',
        'refresh' => 'Aktualisieren',
        'pdf' => 'PDF',
        'archive' => 'Als Dokument ablegen',
        'move_to' => 'Weiter: :stage',
        'save' => 'Speichern',
        'confirm_second_review' => 'Annahme bestätigen',
        'return_second_review' => 'Zurückgeben',
    ],
    'confirm' => [
        'refresh' => 'Neue Fassung der Akte anlegen? Die Angaben werden übernommen und lassen sich prüfen und ergänzen.',
        'archive' => 'PDF ohne Ausweis- und Gesundheitsangaben als Dokument ablegen?',
        'second_review' => 'Annahme als zweite Person bestätigen?',
    ],
    'hint' => [
        'restricted' => ':count Angaben dieser Akte brauchen ein eigenes Recht und sind ausgeblendet.',
        'restricted_answered' => ':kind: :count ausgefüllt.',
        'no_packages' => 'Keine Prüfpakete eingeschaltet. Akten enthalten dann nur den Gesprächsbogen; Leads lassen sich wie bisher direkt umwandeln.',
    ],

    'form' => [
        'section_base' => 'Anfrage und Gespräch',
        'request' => 'Anliegen',
        'services' => 'Gewünschte Leistungen',
        'services_help' => 'Wird bei der Umwandlung Leistungsumfang des neuen Kunden.',
        'start_on' => 'Gewünschter Beginn',
        'interview_notes' => 'Gesprächsnotizen',
        'section_traits' => 'Fristmerkmale: :rule_set',
        'traits_help' => 'Werden bei der Umwandlung Fristmerkmale des neuen Kunden.',
        'section_documents' => 'Unterlagen: :package',
        'documents_help' => 'Haken setzen, sobald die Unterlage vorliegt; Dateien unten bei den Unterlagen ablegen.',
    ],

    'field' => [
        'stage' => 'Stufe',
        'decision' => 'Entscheidung',
        'decision_reason' => 'Begründung',
        'decided_at' => 'Entschieden am',
        'decided_by' => 'Entschieden von',
        'second_review' => 'Zweiter Blick',
        'protocol' => 'Vermerk',
        'packages' => 'Prüfpakete',
        'refresh_due_on' => 'Aktualisierung fällig',
        'retention_until' => 'Aufbewahrung bis',
        'customer' => 'Kunde',
        'relationship_ended_on' => 'Ende der Geschäftsbeziehung',
    ],

    'documents' => [
        'title' => 'Unterlagen',
        'upload' => 'Ablegen',
        'upload_for' => 'Datei zu „:document“',
    ],

    'decision' => [
        'title' => 'Entscheidung',
        'legend' => 'Aufnahme annehmen oder ablehnen',
        'open' => 'Noch nicht entschieden.',
        'missing' => 'Vor der Annahme fehlt noch: :count',
        'blocks_conversion' => 'Ein Prüfpaket mit Entscheidungspflicht ist aktiv: Der Lead wird erst nach der Annahme Kunde.',
        'reason_hint' => 'Die Begründung steht in der Akte und im PDF.',
        'second_review_pending' => 'Angenommen — der zweite Blick einer weiteren Person steht aus.',
        'return_reason' => 'Begründung für die Rückgabe',
    ],

    'packages' => [
        'title' => 'Prüfpakete und Fristen',
        'none' => 'Keine',
    ],
    'versions' => [
        'title' => 'Fassungen',
    ],
    'retention' => [
        'open' => 'läuft ab Ende der Geschäftsbeziehung',
    ],

    'settings' => [
        'title' => 'Einstellungen der Aufnahmen',
        'submit' => 'Speichern',
        'packages' => 'Prüfpakete',
        'none_available' => 'Kein Prüfpaket verfügbar.',
        'packages_hint' => 'Eingeschaltete Pakete gelten für neu geöffnete Akten. Ein Paket mit Entscheidungspflicht sperrt die Umwandlung ohne Annahme.',
        'refresh' => 'Aktualisierung je GwG-Risikostufe (Monate)',
        'refresh_for' => 'Risikostufe :level',
        'refresh_hint' => 'Vorgabe: jährlich. Die Frist beginnt mit der Annahme.',
        'second_review' => 'Zweiter Blick',
        'second_review_label' => 'Annahme durch eine zweite Person bestätigen lassen',
        'second_review_hint' => 'Vorgabe: aus. Die zweite Person braucht das Entscheidungsrecht und darf nicht selbst angenommen haben.',
    ],

    'relationship' => [
        'title' => 'Ende der Geschäftsbeziehung',
        'submit' => 'Speichern',
        'legend' => 'Geschäftsbeziehung',
        'hint' => 'Ab dem Schluss dieses Kalenderjahres läuft die Aufbewahrung der Aufnahmeakten (GwG: fünf Jahre, § 8 Abs. 4 GwG). Leer lassen, solange sie besteht.',
        'ongoing' => 'besteht',
    ],

    'cockpit' => [
        'none' => 'Noch keine Aufnahmeakte. Aktive Prüfpakete: :packages',
        'refresh_due' => 'Aktualisierung :date',
        'retention' => 'aufbewahren bis :date',
    ],

    'filter' => [
        'status' => 'Auswahl',
        'status_open' => 'Offene Aufnahmen',
        'status_refresh' => 'Aktualisierung fällig',
        'status_all' => 'Alle',
        'stage' => 'Stufe',
        'all_stages' => 'Alle Stufen',
    ],
    'empty' => [
        'title' => 'Keine Aufnahmen',
        'message' => 'Aufnahmeakten werden am Lead geöffnet.',
    ],
    'column' => [
        'lead' => 'Lead',
        'stage' => 'Stufe',
        'packages' => 'Prüfpakete',
        'decision' => 'Entscheidung',
        'refresh_due_on' => 'Aktualisierung',
        'updated_at' => 'Geändert',
    ],

    'widget' => [
        'title' => 'Aufnahmen',
        'description' => 'Offene Aufnahmeakten je Stufe, von der Anfrage bis zur Einrichtung.',
        'open_list' => 'Alle Aufnahmen',
        'empty' => 'Gerade ist keine Aufnahme offen.',
    ],

    'flash' => [
        'opened' => 'Aufnahmeakte geöffnet.',
        'saved' => 'Bogen gespeichert.',
        'stage' => 'Stufe: :stage',
        'decided' => 'Entschieden: :decision',
        'second_review_confirmed' => 'Annahme bestätigt.',
        'second_review_returned' => 'Annahme zurückgegeben — der Bogen ist wieder offen.',
        'document_added' => 'Unterlage abgelegt.',
        'refreshed' => 'Fassung :revision angelegt.',
        'archived' => 'Als Dokument abgelegt: :file',
        'settings_saved' => 'Einstellungen der Aufnahmen gespeichert.',
        'relationship_saved' => 'Gespeichert; Aufbewahrung von :count Aufnahmeakten neu berechnet.',
    ],

    'error' => [
        'anonymized' => 'Der Lead ist anonymisiert.',
        'stage_by_decision' => 'Einrichtung, „eingerichtet“ und „abgelehnt“ folgen aus der Entscheidung bzw. der Umwandlung.',
        'closed' => 'Die Akte ist entschieden oder durch eine neuere Fassung abgelöst.',
        'reason_required' => 'Bitte die Entscheidung begründen.',
        'no_second_review' => 'Diese Annahme wartet nicht auf einen zweiten Blick.',
        'same_person' => 'Den zweiten Blick gibt eine andere Person als die, die angenommen hat.',
        'refresh_needs_acceptance' => 'Aktualisiert wird die neueste angenommene Fassung.',
        'unknown_document' => 'Diese Unterlage gehört nicht zur Akte.',
        'restricted' => 'Für diese Unterlage fehlt das eigene Recht.',
        'no_carrier' => 'Weder Kunde noch Lead für die Ablage gefunden.',
        'restricted_missing' => 'Angabe mit eigenem Recht (:right) fehlt.',
    ],

    'block' => [
        'no_intake' => 'Ein Prüfpaket mit Entscheidungspflicht ist aktiv: Bitte zuerst die Aufnahmeakte öffnen und die Aufnahme annehmen.',
        'not_accepted' => 'Die Aufnahme ist noch nicht angenommen — erst danach wird der Lead Kunde.',
        'second_review' => 'Die Annahme wartet auf den zweiten Blick.',
        'rejected' => 'Die Aufnahme wurde abgelehnt; der Lead wird nicht umgewandelt.',
    ],

    'transfer' => [
        'service_note' => 'Aus der Aufnahmeakte übernommen.',
        'traits_incomplete' => 'Fristmerkmale „:rule_set“ aus der Aufnahmeakte sind unvollständig und wurden nicht übernommen — bitte am Kunden ergänzen.',
    ],

    'notification' => [
        'refresh' => [
            'title' => ':count Aufnahmeakte(n) zu aktualisieren',
            'message' => 'Fällig ab :date: :names',
        ],
    ],

    'work' => [
        'decide' => 'Aufnahme entscheiden: :name',
        'second_review' => 'Zweiter Blick: :name',
        'refresh' => 'Aufnahme aktualisieren: :name',
    ],

    'pdf' => [
        'file_prefix' => 'aufnahme',
        'customer' => 'Kunde',
        'restricted' => 'eingeschränkt (:kind)',
    ],

    'vat_id' => [
        'invalid' => 'Die USt-IdNr. ist formal ungültig (Aufbau oder Prüfziffer).',
    ],

    'dsar' => [
        'title' => 'Aufnahmeakten',
        'list_title' => 'Aufnahmeakte, Fassung :revision',
        'column' => [
            'topic' => 'Angabe',
            'value' => 'Inhalt',
        ],
        'legal_basis' => 'Rechtsgrundlage der Aufbewahrung',
        'legal_basis_value' => 'Art. 6 Abs. 1 lit. c DSGVO i. V. m. :basis',
        'restricted' => 'vorhanden — Ausgabe nur mit dem Recht „:right“',
    ],

    'demo' => [
        'request' => 'Laufende Finanzbuchhaltung, Lohn für drei Beschäftigte und Jahresabschluss ab dem kommenden Monat.',
        'notes' => 'Erstgespräch in der Kanzlei; Unterlagen des Vorberaters folgen per Upload.',
        'purpose' => 'Steuerberatung: Finanzbuchhaltung, Lohn und Jahresabschluss für die Partnerschaft.',
    ],

    'gwg' => [
        'label' => 'Geldwäschegesetz (GwG)',
        'protocol_title' => 'Identifizierung und Risikovermerk: :name',
        'protocol_description' => 'Aus der Aufnahmeakte (Fassung :revision). Risikostufe: :risk. Begründung: :reason',
        'document' => [
            'id_copy' => 'Kopie des Ausweisdokuments bzw. Registerauszugs (§ 8 Abs. 2 GwG)',
        ],
        'section' => [
            'identification' => 'GwG: Identifizierung des Vertragspartners (§ 11 GwG)',
            'verification' => 'GwG: Überprüfung der Identität (§ 12 GwG)',
            'beneficial_owners' => 'GwG: Wirtschaftlich Berechtigte (§ 3 GwG)',
            'pep' => 'GwG: Politisch exponierte Person (§ 15 Abs. 3 Nr. 1 GwG)',
            'purpose' => 'GwG: Zweck und Art der Geschäftsbeziehung (§ 10 Abs. 1 Nr. 3 GwG)',
            'risk' => 'GwG: Risikobewertung (§§ 14, 15 GwG)',
        ],
        'field' => [
            'party_kind' => 'Vertragspartner ist',
            'first_name' => 'Vorname',
            'last_name' => 'Nachname',
            'birth_place' => 'Geburtsort',
            'birth_date' => 'Geburtsdatum',
            'nationality' => 'Staatsangehörigkeit',
            'home_address' => 'Wohnanschrift',
            'company_name' => 'Firma, Name oder Bezeichnung',
            'legal_form' => 'Rechtsform',
            'register_number' => 'Registernummer',
            'seat_address' => 'Anschrift des Sitzes bzw. der Hauptniederlassung',
            'representatives' => 'Mitglieder des Vertretungsorgans bzw. gesetzliche Vertreter',
            'verification_method' => 'Überprüft anhand',
            'id_document_kind' => 'Art des Dokuments',
            'id_document_number' => 'Nummer des Dokuments',
            'id_issuer' => 'Ausstellende Stelle',
            'verified_on' => 'Überprüft am',
            'beneficial_owners' => 'Wirtschaftlich Berechtigte',
            'transparency_register_checked' => 'Einsicht ins Transparenzregister genommen',
            'transparency_register_on' => 'Transparenzregister: abgerufen am',
            'transparency_register_reference' => 'Transparenzregister: Auszug bzw. Aktenzeichen',
            'pep_status' => 'Politisch exponierte Person',
            'pep_details' => 'Angaben zur Stellung bzw. Beziehung',
            'purpose' => 'Zweck und angestrebte Art der Geschäftsbeziehung',
            'suspicion_checked' => 'Anhaltspunkte für Geldwäsche oder Terrorismusfinanzierung geprüft',
            'lower_risk_factors' => 'Faktoren für ein geringeres Risiko (Anlage 1 GwG)',
            'higher_risk_factors' => 'Faktoren für ein höheres Risiko (Anlage 2 GwG)',
            'risk_level' => 'Risikostufe',
            'risk_reason' => 'Begründung der Risikostufe',
        ],
        'help' => [
            'party_kind' => 'Natürliche Person: § 11 Abs. 4 Nr. 1 GwG; juristische Person oder Personengesellschaft: Nr. 2.',
            'verification_method' => 'Bei Ausweis oder Registerauszug: Art, Nummer und ausstellende Stelle festhalten und eine Kopie ablegen (§ 8 Abs. 2 GwG).',
            'id_document_number' => 'Wird verschlüsselt gespeichert und ist nur mit dem Recht für Ausweisangaben sichtbar.',
            'beneficial_owners' => 'Name, Art und Umfang der Beteiligung bzw. Kontrolle; bei einer natürlichen Person, die für sich handelt, sie selbst.',
            'transparency_register_checked' => 'Nachweis der Registrierung bzw. Auszug aus dem Transparenzregister (§ 11 Abs. 5, § 12 Abs. 3 GwG).',
            'pep_status' => 'Bei einer politisch exponierten Person gelten verstärkte Sorgfaltspflichten — die Risikostufe ist dann hoch.',
            'suspicion_checked' => 'Die Akte hält nur die Prüfung fest; eine Meldung an die FIU erfolgt nicht aus der App.',
            'lower_risk_factors' => 'Nicht abschließende Faktoren nach Anlage 1 GwG.',
            'higher_risk_factors' => 'Nicht abschließende Faktoren nach Anlage 2 GwG.',
            'risk_level' => 'Gering: vereinfachte Sorgfaltspflichten (§ 14), normal: allgemeine (§ 10), hoch: verstärkte (§ 15). Bestimmt die Aktualisierungsfrist.',
            'risk_reason' => 'Bei gering und hoch Pflicht (§ 8 Abs. 1 Satz 1 Nr. 2 GwG).',
        ],
        'option' => [
            'party_kind' => [
                'natural' => 'Natürliche Person',
                'legal' => 'Juristische Person oder Personengesellschaft',
            ],
            'verification_method' => [
                'id_document' => 'Amtlicher Lichtbildausweis (Personalausweis, Reisepass)',
                'eid' => 'Elektronischer Identitätsnachweis (eID)',
                'qes' => 'Qualifizierte elektronische Signatur',
                'register_extract' => 'Auszug aus dem Handels- oder Genossenschaftsregister',
                'formation_documents' => 'Gründungsdokumente',
                'own_register_check' => 'Eigene dokumentierte Einsichtnahme in das Register',
            ],
            'pep_status' => [
                'no' => 'Nein',
                'pep' => 'Ja, selbst politisch exponiert',
                'family' => 'Familienmitglied einer politisch exponierten Person',
                'associate' => 'Bekanntermaßen nahestehende Person',
            ],
            'lower_risk_factors' => [
                'listed_company' => 'Börsennotiertes Unternehmen mit Offenlegungspflichten',
                'public_body' => 'Öffentliche Verwaltung oder öffentliches Unternehmen',
                'low_risk_area' => 'Sitz bzw. Wohnsitz in einem Gebiet mit geringerem Risiko',
                'low_risk_product' => 'Leistung mit geringem Risiko',
            ],
            'higher_risk_factors' => [
                'unusual_circumstances' => 'Außergewöhnliche Umstände der Geschäftsbeziehung',
                'high_risk_area' => 'Sitz bzw. Wohnsitz in einem Gebiet mit hohem Risiko',
                'asset_holding_vehicle' => 'Gesellschaft zur privaten Vermögensverwaltung',
                'nominee_shareholders' => 'Nominee-Anteilseigner oder Inhaberaktien',
                'cash_intensive' => 'Bargeldintensives Unternehmen',
                'complex_structure' => 'Ungewöhnlich komplexe Eigentumsstruktur',
                'no_personal_contact' => 'Kein persönlicher Kontakt ohne Sicherungsmaßnahmen',
                'unknown_third_party_payments' => 'Zahlungen unbekannter oder nicht verbundener Dritter',
                'high_risk_goods' => 'Handel mit Öl, Waffen, Edelmetallen, Tabak, Kulturgütern oder geschützten Arten',
            ],
        ],
        'error' => [
            'pep_requires_high' => 'Bei einer politisch exponierten Person gelten verstärkte Sorgfaltspflichten: Risikostufe „hoch“ wählen (§ 15 Abs. 3 Nr. 1 GwG).',
            'copy_missing' => 'Die Kopie des Dokuments fehlt (§ 8 Abs. 2 GwG).',
        ],
    ],
];
