<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : facility.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Übersetzungen zum Branchenprofil „facility" (MVP-841): je Domäne und Code die
// Labels für die aktivierbaren Sprachen; der BranchProfileInstaller schreibt
// sie nach classifications.label_i18n. Deutsch ist das Quell-Label im Profil.
return [
    'entry_type' => [
        'objektkontrolle' => ['en' => 'Site inspection', 'es' => 'Control del inmueble', 'fr' => 'Contrôle du site', 'it' => 'Controllo dell\'immobile'],
        'maengelmeldung' => ['en' => 'Defect report', 'es' => 'Aviso de defecto', 'fr' => 'Signalement de défaut', 'it' => 'Segnalazione difetto'],
        'kleinreparatur' => ['en' => 'Minor repair', 'es' => 'Reparación menor', 'fr' => 'Petite réparation', 'it' => 'Piccola riparazione'],
        'wartungsrunde' => ['en' => 'Maintenance round', 'es' => 'Ronda de mantenimiento', 'fr' => 'Tournée de maintenance', 'it' => 'Giro di manutenzione'],
        'winterdienst' => ['en' => 'Winter service', 'es' => 'Servicio invernal', 'fr' => 'Service hivernal', 'it' => 'Servizio invernale'],
        'zaehlerstand' => ['en' => 'Meter reading', 'es' => 'Lectura de contador', 'fr' => 'Relevé de compteur', 'it' => 'Lettura contatore'],
        'schluessel' => ['en' => 'Key handover/return', 'es' => 'Entrega/devolución de llaves', 'fr' => 'Remise/retour de clés', 'it' => 'Consegna/restituzione chiavi'],
        'notfall' => ['en' => 'Emergency', 'es' => 'Emergencia', 'fr' => 'Urgence', 'it' => 'Emergenza'],
    ],
    'waste_code' => [
        'avv_150110_h' => ['en' => '15 01 10* — Packaging containing residues of hazardous substances', 'es' => '15 01 10* — Envases con restos de sustancias peligrosas', 'fr' => '15 01 10* — Emballages contenant des résidus de substances dangereuses', 'it' => '15 01 10* — Imballaggi contenenti residui di sostanze pericolose'],
        'avv_200133_h' => ['en' => '20 01 33* — Mixed batteries (including hazardous)', 'es' => '20 01 33* — Pilas mezcladas (con peligrosas)', 'fr' => '20 01 33* — Piles mélangées (dont dangereuses)', 'it' => '20 01 33* — Batterie miste (incluse pericolose)'],
        'avv_200134' => ['en' => '20 01 34 — Batteries (non-hazardous)', 'es' => '20 01 34 — Pilas (no peligrosas)', 'fr' => '20 01 34 — Piles (non dangereuses)', 'it' => '20 01 34 — Batterie (non pericolose)'],
    ],
    'activity' => [
        'kontrollieren' => ['en' => 'Check', 'es' => 'Controlar', 'fr' => 'Contrôler', 'it' => 'Controllare'],
        'reinigen' => ['en' => 'Clean', 'es' => 'Limpiar', 'fr' => 'Nettoyer', 'it' => 'Pulire'],
        'reparieren' => ['en' => 'Repair', 'es' => 'Reparar', 'fr' => 'Réparer', 'it' => 'Riparare'],
        'dokumentieren' => ['en' => 'Document', 'es' => 'Documentar', 'fr' => 'Documenter', 'it' => 'Documentare'],
        'abschliessen' => ['en' => 'Complete', 'es' => 'Finalizar', 'fr' => 'Clôturer', 'it' => 'Concludere'],
        'streuen' => ['en' => 'Gritting', 'es' => 'Esparcir sal', 'fr' => 'Saler', 'it' => 'Spargere sale'],
        'ablesen' => ['en' => 'Reading', 'es' => 'Lectura', 'fr' => 'Relever', 'it' => 'Lettura'],
        'beauftragen' => ['en' => 'Commission', 'es' => 'Encargar', 'fr' => 'Mandater', 'it' => 'Incaricare'],
        'nachhalten' => ['en' => 'Follow up', 'es' => 'Hacer seguimiento', 'fr' => 'Relancer', 'it' => 'Sollecitare'],
    ],
    'defect_type' => [
        'defekteBeleuchtung' => ['en' => 'Defective lighting', 'es' => 'Iluminación defectuosa', 'fr' => 'Éclairage défectueux', 'it' => 'Illuminazione difettosa'],
        'wasserschaden' => ['en' => 'Water damage', 'es' => 'Daño por agua', 'fr' => 'Dégât des eaux', 'it' => 'Danno da acqua'],
        'vandalismus' => ['en' => 'Vandalism', 'es' => 'Vandalismo', 'fr' => 'Vandalisme', 'it' => 'Vandalismo'],
        'schliessproblem' => ['en' => 'Locking problem', 'es' => 'Problema de cierre', 'fr' => 'Problème de fermeture', 'it' => 'Problema di chiusura'],
        'brandschutzmangel' => ['en' => 'Fire protection deficiency', 'es' => 'Deficiencia de protección contra incendios', 'fr' => 'Défaut de protection incendie', 'it' => 'Carenza antincendio'],
        'stolperstelle' => ['en' => 'Trip hazard', 'es' => 'Riesgo de tropiezo', 'fr' => 'Risque de trébuchement', 'it' => 'Pericolo di inciampo'],
        'verunreinigung' => ['en' => 'Contamination', 'es' => 'Contaminación', 'fr' => 'Contamination', 'it' => 'Contaminazione'],
    ],
    'root_cause' => [
        'verschleiss' => ['en' => 'Wear', 'es' => 'Desgaste', 'fr' => 'Usure', 'it' => 'Usura'],
        'nutzung' => ['en' => 'Use', 'es' => 'Uso', 'fr' => 'Utilisation', 'it' => 'Utilizzo'],
        'wetter' => ['en' => 'Weather', 'es' => 'Clima', 'fr' => 'Météo', 'it' => 'Meteo'],
        'fremdeinwirkung' => ['en' => 'Third-party interference', 'es' => 'Intervención de terceros', 'fr' => 'Intervention extérieure', 'it' => 'Intervento di terzi'],
        'mangelndeWartung' => ['en' => 'Insufficient maintenance', 'es' => 'Mantenimiento insuficiente', 'fr' => 'Entretien insuffisant', 'it' => 'Manutenzione insufficiente'],
        'unbekannt' => ['en' => 'Unknown', 'es' => 'Desconocido', 'fr' => 'Inconnu', 'it' => 'Sconosciuto'],
    ],
    'result' => [
        'erledigt' => ['en' => 'Done', 'es' => 'Hecho', 'fr' => 'Fait', 'it' => 'Fatto'],
        'offen' => ['en' => 'Open', 'es' => 'Abierto', 'fr' => 'Ouvert', 'it' => 'Aperto'],
        'weitergeleitet' => ['en' => 'Forwarded', 'es' => 'Remitido', 'fr' => 'Transmis', 'it' => 'Inoltrato'],
        'nacharbeit' => ['en' => 'Rework', 'es' => 'Retrabajo', 'fr' => 'Retouche', 'it' => 'Rilavorazione'],
        'materialFehlt' => ['en' => 'Material missing', 'es' => 'Falta material', 'fr' => 'Matériel manquant', 'it' => 'Materiale mancante'],
        'kundeInformiert' => ['en' => 'Customer informed', 'es' => 'Cliente informado', 'fr' => 'Client informé', 'it' => 'Cliente informato'],
        'eskaliert' => ['en' => 'Escalated', 'es' => 'Escalado', 'fr' => 'Escaladé', 'it' => 'Escalato'],
    ],
    'product_group' => [
        'gebaeude' => ['en' => 'Building', 'es' => 'Edificio', 'fr' => 'Bâtiment', 'it' => 'Edificio'],
        'tuer' => ['en' => 'Door', 'es' => 'Puerta', 'fr' => 'Porte', 'it' => 'Porta'],
        'tor' => ['en' => 'Gate', 'es' => 'Portón', 'fr' => 'Portail', 'it' => 'Cancello'],
        'beleuchtung' => ['en' => 'Lighting', 'es' => 'Iluminación', 'fr' => 'Éclairage', 'it' => 'Illuminazione'],
        'heizung' => ['en' => 'Heating', 'es' => 'Calefacción', 'fr' => 'Chauffage', 'it' => 'Riscaldamento'],
        'aufzug' => ['en' => 'Elevator', 'es' => 'Ascensor', 'fr' => 'Ascenseur', 'it' => 'Ascensore'],
        'aussenanlage' => ['en' => 'Outdoor facilities', 'es' => 'Instalaciones exteriores', 'fr' => 'Aménagements extérieurs', 'it' => 'Area esterna'],
        'brandschutz' => ['en' => 'Fire protection', 'es' => 'Protección contra incendios', 'fr' => 'Protection incendie', 'it' => 'Protezione antincendio'],
        'schluessel' => ['en' => 'Key', 'es' => 'Llave', 'fr' => 'Clé', 'it' => 'Chiave'],
        'zaehler' => ['en' => 'Meter', 'es' => 'Contador', 'fr' => 'Compteur', 'it' => 'Contatore'],
    ],
    'trade' => [
        'reinigung' => ['en' => 'Cleaning', 'es' => 'Limpieza', 'fr' => 'Nettoyage', 'it' => 'Pulizia'],
        'sicherheitsdienst' => ['en' => 'Security service', 'es' => 'Servicio de seguridad', 'fr' => 'Service de sécurité', 'it' => 'Servizio di sicurezza'],
        'haustechnik' => ['en' => 'Building services / HVAC', 'es' => 'Instalaciones / climatización', 'fr' => 'Équipements techniques / CVC', 'it' => 'Impianti tecnici / HVAC'],
        'aufzug' => ['en' => 'Elevator maintenance', 'es' => 'Mantenimiento de ascensores', 'fr' => 'Maintenance d\'ascenseur', 'it' => 'Manutenzione ascensori'],
        'gruenpflege' => ['en' => 'Green space maintenance', 'es' => 'Mantenimiento de zonas verdes', 'fr' => 'Entretien des espaces verts', 'it' => 'Manutenzione del verde'],
        'brandschutz' => ['en' => 'Fire protection', 'es' => 'Protección contra incendios', 'fr' => 'Protection incendie', 'it' => 'Protezione antincendio'],
        'schaedlingsbekaempfung' => ['en' => 'Pest control', 'es' => 'Control de plagas', 'fr' => 'Lutte contre les nuisibles', 'it' => 'Disinfestazione'],
        'entsorgung' => ['en' => 'Disposal', 'es' => 'Eliminación', 'fr' => 'Élimination', 'it' => 'Smaltimento'],
    ],
];
