<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : verein.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Übersetzungen zum Branchenprofil „verein" (MVP-848): je Domäne und Code die
// Labels der aktivierbaren Sprachen; Deutsch ist das Quell-Label im Profil.
return [
    'entry_type' => [
        'training' => ['en' => 'Training operations', 'es' => 'Entrenamiento', 'fr' => 'Entraînement', 'it' => 'Allenamento'],
        'veranstaltung' => ['en' => 'Event / sports festival', 'es' => 'Evento / fiesta deportiva', 'fr' => 'Événement / fête sportive', 'it' => 'Evento / festa sportiva'],
        'wettkampfbetreuung' => ['en' => 'Competition support', 'es' => 'Acompañamiento en competición', 'fr' => 'Encadrement en compétition', 'it' => 'Assistenza in gara'],
        'geschaeftsstelle' => ['en' => 'Office / administration', 'es' => 'Oficina / administración', 'fr' => 'Secrétariat / administration', 'it' => 'Segreteria / amministrazione'],
        'sportstaette' => ['en' => 'Facility / pitch maintenance', 'es' => 'Instalación / mantenimiento', 'fr' => 'Installation / entretien', 'it' => 'Impianto / manutenzione'],
        'gremium' => ['en' => 'Board / committee', 'es' => 'Junta / comité', 'fr' => 'Bureau / commission', 'it' => 'Consiglio / commissione'],
        'zwischenfall' => ['en' => 'Incident / accident', 'es' => 'Incidente / accidente', 'fr' => 'Incident / accident', 'it' => 'Incidente / infortunio'],
    ],
    'activity' => [
        'trainieren' => ['en' => 'Train / instruct', 'es' => 'Entrenar / instruir', 'fr' => 'Entraîner / encadrer', 'it' => 'Allenare / istruire'],
        'betreuen' => ['en' => 'Look after', 'es' => 'Atender', 'fr' => 'Accompagner', 'it' => 'Assistere'],
        'organisieren' => ['en' => 'Organise', 'es' => 'Organizar', 'fr' => 'Organiser', 'it' => 'Organizzare'],
        'verwalten' => ['en' => 'Manage / bill', 'es' => 'Gestionar / facturar', 'fr' => 'Gérer / facturer', 'it' => 'Gestire / fatturare'],
        'pflegen' => ['en' => 'Maintain / service', 'es' => 'Mantener / conservar', 'fr' => 'Entretenir / maintenir', 'it' => 'Curare / manutenere'],
        'dokumentieren' => ['en' => 'Document', 'es' => 'Documentar', 'fr' => 'Documenter', 'it' => 'Documentare'],
    ],
    'defect_type' => [
        'hallenausfall' => ['en' => 'Hall / pitch unusable', 'es' => 'Pabellón / campo no utilizable', 'fr' => 'Salle / terrain inutilisable', 'it' => 'Palestra / campo non utilizzabile'],
        'geraetedefekt' => ['en' => 'Device defect', 'es' => 'Avería del equipo', 'fr' => 'Appareil défectueux', 'it' => 'Guasto all\'apparecchio'],
        'trainerausfall' => ['en' => 'Instructor unavailable', 'es' => 'Monitor ausente', 'fr' => 'Entraîneur absent', 'it' => 'Istruttore assente'],
        'verletzung' => ['en' => 'Injury', 'es' => 'Lesión', 'fr' => 'Blessure', 'it' => 'Lesione'],
        'beitragsrueckstand' => ['en' => 'Arrears of fees', 'es' => 'Cuotas atrasadas', 'fr' => 'Arriérés de cotisation', 'it' => 'Quote arretrate'],
    ],
    'root_cause' => [
        'witterung' => ['en' => 'Weather conditions', 'es' => 'Condiciones meteorológicas', 'fr' => 'Intempéries', 'it' => 'Condizioni meteo'],
        'wartung' => ['en' => 'Maintenance / wear', 'es' => 'Mantenimiento / desgaste', 'fr' => 'Maintenance / usure', 'it' => 'Manutenzione / usura'],
        'personal' => ['en' => 'Staff / volunteers', 'es' => 'Personal / voluntariado', 'fr' => 'Personnel / bénévoles', 'it' => 'Personale / volontari'],
        'kommunikation' => ['en' => 'Communication', 'es' => 'Comunicación', 'fr' => 'Communication', 'it' => 'Comunicazione'],
        'organisation' => ['en' => 'Organisation', 'es' => 'Organización', 'fr' => 'Organisation', 'it' => 'Organizzazione'],
    ],
    'result' => [
        'erledigt' => ['en' => 'Done', 'es' => 'Hecho', 'fr' => 'Fait', 'it' => 'Fatto'],
        'verschoben' => ['en' => 'Postponed', 'es' => 'Aplazado', 'fr' => 'Reporté', 'it' => 'Rinviato'],
        'abgesagt' => ['en' => 'Cancelled', 'es' => 'Cancelado', 'fr' => 'Annulé', 'it' => 'Annullato'],
        'ersatz' => ['en' => 'Replacement arranged', 'es' => 'Sustitución organizada', 'fr' => 'Remplacement organisé', 'it' => 'Sostituzione organizzata'],
        'gemeldet' => ['en' => 'Reported / forwarded', 'es' => 'Notificado / remitido', 'fr' => 'Signalé / transmis', 'it' => 'Segnalato / inoltrato'],
    ],
    'priority' => [
        'niedrig' => ['en' => 'Low', 'es' => 'Bajo', 'fr' => 'Faible', 'it' => 'Basso'],
        'mittel' => ['en' => 'Medium', 'es' => 'Medio', 'fr' => 'Moyen', 'it' => 'Medio'],
        'hoch' => ['en' => 'High', 'es' => 'Alto', 'fr' => 'Élevé', 'it' => 'Alto'],
        'sicherheitsrelevant' => ['en' => 'Safety-relevant', 'es' => 'Relevante para la seguridad', 'fr' => 'Lié à la sécurité', 'it' => 'Rilevante per la sicurezza'],
    ],
    'product_group' => [
        'mitgliedschaft' => ['en' => 'Membership', 'es' => 'Afiliación', 'fr' => 'Adhésion', 'it' => 'Iscrizione'],
        'kurs' => ['en' => 'Course / training', 'es' => 'Curso / formación', 'fr' => 'Cours / formation', 'it' => 'Corso / formazione'],
        'veranstaltung' => ['en' => 'Event', 'es' => 'Evento', 'fr' => 'Événement', 'it' => 'Evento'],
        'ausruestung' => ['en' => 'Equipment / loan devices', 'es' => 'Equipamiento / equipos en préstamo', 'fr' => 'Équipement / appareils prêtés', 'it' => 'Attrezzatura / apparecchi in prestito'],
    ],
    'dienstmittel_type' => [
        'sportgeraet' => ['en' => 'Sports equipment', 'es' => 'Material deportivo', 'fr' => 'Équipement sportif', 'it' => 'Attrezzo sportivo'],
        'erstehilfe' => ['en' => 'First aid equipment', 'es' => 'Equipo de primeros auxilios', 'fr' => 'Matériel de premiers secours', 'it' => 'Dotazione di primo soccorso'],
        'fahrzeug' => ['en' => 'Club vehicle', 'es' => 'Vehículo del club', 'fr' => 'Véhicule du club', 'it' => 'Veicolo dell\'associazione'],
        'schluessel' => ['en' => 'Key / access', 'es' => 'Llave / acceso', 'fr' => 'Clé / accès', 'it' => 'Chiave / accesso'],
    ],
];
