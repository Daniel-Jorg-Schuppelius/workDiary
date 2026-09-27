<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : sicherheitsdienst.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Übersetzungen zum Branchenprofil „sicherheitsdienst" (MVP-841): je Domäne und Code die
// Labels für die aktivierbaren Sprachen; der BranchProfileInstaller schreibt
// sie nach classifications.label_i18n. Deutsch ist das Quell-Label im Profil.
return [
    'entry_type' => [
        'wachbuch' => ['en' => 'Logbook entry', 'es' => 'Entrada en el libro de guardia', 'fr' => 'Entrée du registre de garde', 'it' => 'Registrazione nel registro di guardia'],
        'revierfahrt' => ['en' => 'Patrol drive', 'es' => 'Ronda en vehículo', 'fr' => 'Ronde motorisée', 'it' => 'Ronda con veicolo'],
        'kontrollgang' => ['en' => 'Patrol round', 'es' => 'Ronda de control', 'fr' => 'Ronde de contrôle', 'it' => 'Giro di controllo'],
        'alarm' => ['en' => 'Alarm response', 'es' => 'Verificación de alarma', 'fr' => 'Levée de doute', 'it' => 'Intervento su allarme'],
        'zutritt' => ['en' => 'Access control', 'es' => 'Control de acceso', 'fr' => 'Contrôle d\'accès', 'it' => 'Controllo accessi'],
        'schluessel' => ['en' => 'Key handover/return', 'es' => 'Entrega/devolución de llaves', 'fr' => 'Remise/retour de clés', 'it' => 'Consegna/restituzione chiavi'],
        'vorfall' => ['en' => 'Incident report', 'es' => 'Informe de incidente', 'fr' => 'Rapport d\'incident', 'it' => 'Segnalazione incidente'],
        'uebergabe' => ['en' => 'Shift handover', 'es' => 'Relevo de turno', 'fr' => 'Passation de service', 'it' => 'Passaggio di turno'],
        'sonderdienst' => ['en' => 'Special assignment', 'es' => 'Servicio especial', 'fr' => 'Mission spéciale', 'it' => 'Servizio speciale'],
    ],
    'activity' => [
        'kontrollieren' => ['en' => 'Check', 'es' => 'Controlar', 'fr' => 'Contrôler', 'it' => 'Controllare'],
        'anmelden' => ['en' => 'Register', 'es' => 'Registrar', 'fr' => 'Déclarer', 'it' => 'Registrare'],
        'absichern' => ['en' => 'Secure', 'es' => 'Asegurar', 'fr' => 'Sécuriser', 'it' => 'Mettere in sicurezza'],
        'melden' => ['en' => 'Report', 'es' => 'Notificar', 'fr' => 'Signaler', 'it' => 'Segnalare'],
        'eskortieren' => ['en' => 'Escort', 'es' => 'Escoltar', 'fr' => 'Escorter', 'it' => 'Scortare'],
        'sperren' => ['en' => 'Block', 'es' => 'Bloquear', 'fr' => 'Bloquer', 'it' => 'Bloccare'],
        'aufschliessen' => ['en' => 'Unlock', 'es' => 'Abrir', 'fr' => 'Ouvrir', 'it' => 'Aprire'],
        'dokumentieren' => ['en' => 'Document', 'es' => 'Documentar', 'fr' => 'Documenter', 'it' => 'Documentare'],
        'eskalieren' => ['en' => 'Escalate', 'es' => 'Escalar', 'fr' => 'Escalader', 'it' => 'Escalare'],
    ],
    'defect_type' => [
        'einbruchVerdacht' => ['en' => 'Suspected burglary', 'es' => 'Sospecha de robo', 'fr' => 'Soupçon d\'effraction', 'it' => 'Sospetto di effrazione'],
        'vandalismus' => ['en' => 'Vandalism', 'es' => 'Vandalismo', 'fr' => 'Vandalisme', 'it' => 'Vandalismo'],
        'tueroffen' => ['en' => 'Door/window open', 'es' => 'Puerta/ventana abierta', 'fr' => 'Porte/fenêtre ouverte', 'it' => 'Porta/finestra aperta'],
        'alarmAusgeloest' => ['en' => 'Alarm triggered', 'es' => 'Alarma activada', 'fr' => 'Alarme déclenchée', 'it' => 'Allarme scattato'],
        'schluesselFehlt' => ['en' => 'Key missing', 'es' => 'Falta la llave', 'fr' => 'Clé manquante', 'it' => 'Chiave mancante'],
        'personenkonflikt' => ['en' => 'Interpersonal conflict', 'es' => 'Conflicto entre personas', 'fr' => 'Conflit entre personnes', 'it' => 'Conflitto tra persone'],
        'brandschutz' => ['en' => 'Fire protection deficiency', 'es' => 'Deficiencia de protección contra incendios', 'fr' => 'Défaut de protection incendie', 'it' => 'Carenza antincendio'],
    ],
    'root_cause' => [
        'unbekannt' => ['en' => 'Unknown', 'es' => 'Desconocido', 'fr' => 'Inconnu', 'it' => 'Sconosciuto'],
        'technischerFehler' => ['en' => 'Technical error', 'es' => 'Error técnico', 'fr' => 'Erreur technique', 'it' => 'Errore tecnico'],
        'fremdeinwirkung' => ['en' => 'Third-party interference', 'es' => 'Intervención de terceros', 'fr' => 'Intervention extérieure', 'it' => 'Intervento di terzi'],
        'bedienfehler' => ['en' => 'Operating error', 'es' => 'Error de manejo', 'fr' => 'Erreur de manipulation', 'it' => 'Errore di utilizzo'],
        'wetter' => ['en' => 'Weather', 'es' => 'Clima', 'fr' => 'Météo', 'it' => 'Meteo'],
        'organisatorisch' => ['en' => 'Organisational', 'es' => 'Organizativo', 'fr' => 'Organisationnel', 'it' => 'Organizzativo'],
    ],
    'result' => [
        'erledigt' => ['en' => 'Done', 'es' => 'Hecho', 'fr' => 'Fait', 'it' => 'Fatto'],
        'lageGeklaert' => ['en' => 'Situation resolved', 'es' => 'Situación aclarada', 'fr' => 'Situation clarifiée', 'it' => 'Situazione chiarita'],
        'polizeiInformiert' => ['en' => 'Police informed', 'es' => 'Policía informada', 'fr' => 'Police informée', 'it' => 'Polizia informata'],
        'kundeInformiert' => ['en' => 'Customer informed', 'es' => 'Cliente informado', 'fr' => 'Client informé', 'it' => 'Cliente informato'],
        'offen' => ['en' => 'Open', 'es' => 'Abierto', 'fr' => 'Ouvert', 'it' => 'Aperto'],
        'eskaliert' => ['en' => 'Escalated', 'es' => 'Escalado', 'fr' => 'Escaladé', 'it' => 'Escalato'],
        'fehlalarm' => ['en' => 'False alarm', 'es' => 'Falsa alarma', 'fr' => 'Fausse alarme', 'it' => 'Falso allarme'],
    ],
    'product_group' => [
        'objekt' => ['en' => 'Property', 'es' => 'Inmueble', 'fr' => 'Site', 'it' => 'Oggetto'],
        'kontrollpunkt' => ['en' => 'Checkpoint', 'es' => 'Punto de control', 'fr' => 'Point de contrôle', 'it' => 'Punto di controllo'],
        'tor' => ['en' => 'Gate', 'es' => 'Portón', 'fr' => 'Portail', 'it' => 'Cancello'],
        'tuer' => ['en' => 'Door', 'es' => 'Puerta', 'fr' => 'Porte', 'it' => 'Porta'],
        'alarmanlage' => ['en' => 'Alarm system', 'es' => 'Sistema de alarma', 'fr' => 'Système d\'alarme', 'it' => 'Impianto d\'allarme'],
        'kamera' => ['en' => 'Camera', 'es' => 'Cámara', 'fr' => 'Caméra', 'it' => 'Telecamera'],
        'schluessel' => ['en' => 'Key', 'es' => 'Llave', 'fr' => 'Clé', 'it' => 'Chiave'],
        'ausweis' => ['en' => 'ID card', 'es' => 'Identificación', 'fr' => 'Pièce d\'identité', 'it' => 'Documento d\'identità'],
        'fahrzeug' => ['en' => 'Vehicle', 'es' => 'Vehículo', 'fr' => 'Véhicule', 'it' => 'Veicolo'],
    ],
];
