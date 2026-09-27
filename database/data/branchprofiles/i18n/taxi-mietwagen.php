<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : taxi-mietwagen.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Übersetzungen zum Branchenprofil „taxi-mietwagen" (MVP-841): je Domäne und Code die
// Labels für die aktivierbaren Sprachen; der BranchProfileInstaller schreibt
// sie nach classifications.label_i18n. Deutsch ist das Quell-Label im Profil.
return [
    'entry_type' => [
        'fahrtanfrage' => ['en' => 'Ride request', 'es' => 'Solicitud de viaje', 'fr' => 'Demande de course', 'it' => 'Richiesta di corsa'],
        'vorbestellung' => ['en' => 'Advance booking', 'es' => 'Reserva anticipada', 'fr' => 'Réservation à l\'avance', 'it' => 'Prenotazione anticipata'],
        'sofortfahrt' => ['en' => 'Immediate ride', 'es' => 'Viaje inmediato', 'fr' => 'Course immédiate', 'it' => 'Corsa immediata'],
        'serienfahrt' => ['en' => 'Recurring ride', 'es' => 'Viaje periódico', 'fr' => 'Course récurrente', 'it' => 'Corsa ricorrente'],
        'bereitstellung' => ['en' => 'Standby', 'es' => 'Puesta a disposición', 'fr' => 'Mise à disposition', 'it' => 'Messa a disposizione'],
        'personenfahrt' => ['en' => 'Passenger ride', 'es' => 'Viaje de pasajeros', 'fr' => 'Course passagers', 'it' => 'Corsa passeggeri'],
        'wartezeit' => ['en' => 'Waiting time', 'es' => 'Tiempo de espera', 'fr' => 'Temps d\'attente', 'it' => 'Tempo di attesa'],
        'leerfahrt' => ['en' => 'Empty run', 'es' => 'Viaje en vacío', 'fr' => 'Course à vide', 'it' => 'Corsa a vuoto'],
        'fahrzeugwechsel' => ['en' => 'Vehicle change', 'es' => 'Cambio de vehículo', 'fr' => 'Changement de véhicule', 'it' => 'Cambio veicolo'],
        'stoerung' => ['en' => 'Breakdown', 'es' => 'Avería', 'fr' => 'Panne', 'it' => 'Guasto'],
        'unfall' => ['en' => 'Accident', 'es' => 'Accidente', 'fr' => 'Accident', 'it' => 'Incidente'],
        'reklamation' => ['en' => 'Complaint', 'es' => 'Reclamación', 'fr' => 'Réclamation', 'it' => 'Reclamo'],
        'abrechnung' => ['en' => 'Settlement', 'es' => 'Liquidación', 'fr' => 'Décompte', 'it' => 'Rendiconto'],
    ],
    'activity' => [
        'annehmen' => ['en' => 'Receive', 'es' => 'Recibir', 'fr' => 'Réceptionner', 'it' => 'Ricevere'],
        'disponieren' => ['en' => 'Dispatch', 'es' => 'Planificar', 'fr' => 'Planifier', 'it' => 'Pianificare'],
        'anfahren' => ['en' => 'Travel', 'es' => 'Desplazamiento', 'fr' => 'Déplacement', 'it' => 'Trasferimento'],
        'warten' => ['en' => 'Maintain', 'es' => 'Mantener', 'fr' => 'Entretenir', 'it' => 'Manutenere'],
        'aufnehmen' => ['en' => 'Pick up passenger', 'es' => 'Recoger al pasajero', 'fr' => 'Prendre en charge le passager', 'it' => 'Far salire il passeggero'],
        'befoerdern' => ['en' => 'Transport', 'es' => 'Transportar', 'fr' => 'Transporter', 'it' => 'Trasportare'],
        'abrechnen' => ['en' => 'Billing', 'es' => 'Facturar', 'fr' => 'Facturer', 'it' => 'Fatturare'],
        'kassieren' => ['en' => 'Collect payment', 'es' => 'Cobrar', 'fr' => 'Encaisser', 'it' => 'Incassare'],
        'reinigen' => ['en' => 'Clean', 'es' => 'Limpiar', 'fr' => 'Nettoyer', 'it' => 'Pulire'],
        'dokumentieren' => ['en' => 'Document', 'es' => 'Documentar', 'fr' => 'Documenter', 'it' => 'Documentare'],
    ],
    'product_group' => [
        'taxifahrt' => ['en' => 'Taxi ride', 'es' => 'Viaje en taxi', 'fr' => 'Course de taxi', 'it' => 'Corsa in taxi'],
        'mietwagenfahrt' => ['en' => 'Private hire trip', 'es' => 'Viaje VTC', 'fr' => 'Course VTC', 'it' => 'Corsa NCC'],
        'gebuendelterBedarfsverkehr' => ['en' => 'Pooled on-demand transport', 'es' => 'Transporte a demanda agrupado', 'fr' => 'Transport à la demande groupé', 'it' => 'Trasporto a chiamata aggregato'],
        'krankenfahrt' => ['en' => 'Patient transport', 'es' => 'Transporte sanitario', 'fr' => 'Transport de malade', 'it' => 'Trasporto infermi'],
        'schuelerfahrt' => ['en' => 'School transport', 'es' => 'Transporte escolar', 'fr' => 'Transport scolaire', 'it' => 'Trasporto scolastico'],
        'rollstuhlfahrt' => ['en' => 'Wheelchair transport', 'es' => 'Traslado en silla de ruedas', 'fr' => 'Transport en fauteuil roulant', 'it' => 'Trasporto in sedia a rotelle'],
        'grossraumfahrt' => ['en' => 'Large-capacity trip', 'es' => 'Viaje de gran capacidad', 'fr' => 'Course grande capacité', 'it' => 'Corsa ad alta capienza'],
    ],
    'defect_type' => [
        'verspaetung' => ['en' => 'Delay', 'es' => 'Retraso', 'fr' => 'Retard', 'it' => 'Ritardo'],
        'fahrgastNichtErschienen' => ['en' => 'Passenger no-show', 'es' => 'Pasajero no presentado', 'fr' => 'Passager absent', 'it' => 'Passeggero non presentato'],
        'falscheAdresse' => ['en' => 'Wrong address', 'es' => 'Dirección incorrecta', 'fr' => 'Mauvaise adresse', 'it' => 'Indirizzo errato'],
        'fahrzeugDefekt' => ['en' => 'Vehicle defect', 'es' => 'Avería del vehículo', 'fr' => 'Panne du véhicule', 'it' => 'Guasto al veicolo'],
        'fahrerAusfall' => ['en' => 'Driver unavailable', 'es' => 'Ausencia del conductor', 'fr' => 'Absence du chauffeur', 'it' => 'Autista non disponibile'],
        'taxameterStoerung' => ['en' => 'Taximeter fault', 'es' => 'Avería del taxímetro', 'fr' => 'Panne du taximètre', 'it' => 'Guasto al tassametro'],
        'tseStoerung' => ['en' => 'TSE fault', 'es' => 'Avería de la TSE', 'fr' => 'Panne TSE', 'it' => 'Guasto TSE'],
        'zahlungFehlgeschlagen' => ['en' => 'Payment failed', 'es' => 'Pago fallido', 'fr' => 'Paiement échoué', 'it' => 'Pagamento non riuscito'],
        'unfall' => ['en' => 'Accident', 'es' => 'Accidente', 'fr' => 'Accident', 'it' => 'Incidente'],
        'barrierefreiheitNichtErfuellt' => ['en' => 'Accessibility not met', 'es' => 'Accesibilidad no cumplida', 'fr' => 'Accessibilité non respectée', 'it' => 'Accessibilità non soddisfatta'],
    ],
    'root_cause' => [
        'verkehr' => ['en' => 'Traffic', 'es' => 'Tráfico', 'fr' => 'Circulation', 'it' => 'Traffico'],
        'wetter' => ['en' => 'Weather', 'es' => 'Clima', 'fr' => 'Météo', 'it' => 'Meteo'],
        'disposition' => ['en' => 'Dispatch', 'es' => 'Planificación', 'fr' => 'Planification', 'it' => 'Pianificazione'],
        'kunde' => ['en' => 'Customer', 'es' => 'Cliente', 'fr' => 'Client', 'it' => 'Cliente'],
        'vermittler' => ['en' => 'Broker', 'es' => 'Intermediario', 'fr' => 'Intermédiaire', 'it' => 'Intermediario'],
        'fahrzeug' => ['en' => 'Vehicle', 'es' => 'Vehículo', 'fr' => 'Véhicule', 'it' => 'Veicolo'],
        'fahrer' => ['en' => 'Driver', 'es' => 'Conductor', 'fr' => 'Chauffeur', 'it' => 'Autista'],
        'geraet' => ['en' => 'Device', 'es' => 'Equipo', 'fr' => 'Appareil', 'it' => 'Apparecchio'],
        'zahlungsdienst' => ['en' => 'Payment service', 'es' => 'Servicio de pago', 'fr' => 'Service de paiement', 'it' => 'Servizio di pagamento'],
    ],
    'result' => [
        'angenommen' => ['en' => 'Accepted', 'es' => 'Aceptado', 'fr' => 'Accepté', 'it' => 'Accettato'],
        'disponiert' => ['en' => 'Scheduled', 'es' => 'Planificado', 'fr' => 'Planifié', 'it' => 'Pianificato'],
        'fahrgastAufgenommen' => ['en' => 'Passenger picked up', 'es' => 'Pasajero recogido', 'fr' => 'Passager pris en charge', 'it' => 'Passeggero a bordo'],
        'abgeschlossen' => ['en' => 'Completed', 'es' => 'Finalizado', 'fr' => 'Terminé', 'it' => 'Concluso'],
        'storniert' => ['en' => 'Cancelled', 'es' => 'Anulado', 'fr' => 'Annulé', 'it' => 'Stornato'],
        'noShow' => ['en' => 'No-show', 'es' => 'No presentado', 'fr' => 'Absence', 'it' => 'Mancata presentazione'],
        'abgebrochen' => ['en' => 'Aborted', 'es' => 'Interrumpido', 'fr' => 'Interrompu', 'it' => 'Interrotto'],
        'umdisponiert' => ['en' => 'Rescheduled', 'es' => 'Replanificado', 'fr' => 'Replanifié', 'it' => 'Ripianificato'],
        'abgerechnet' => ['en' => 'Billed', 'es' => 'Facturado', 'fr' => 'Facturé', 'it' => 'Fatturato'],
    ],
    'priority' => [
        'standard' => ['en' => 'Standard', 'es' => 'Estándar', 'fr' => 'Standard', 'it' => 'Standard'],
        'vorbestellt' => ['en' => 'Pre-ordered', 'es' => 'Reservado', 'fr' => 'Précommandé', 'it' => 'Preordinato'],
        'dringend' => ['en' => 'Urgent', 'es' => 'Urgente', 'fr' => 'Urgent', 'it' => 'Urgente'],
        'medizinisch' => ['en' => 'Medical', 'es' => 'Médico', 'fr' => 'Médical', 'it' => 'Medico'],
        'barrierefrei' => ['en' => 'Accessible', 'es' => 'Accesible', 'fr' => 'Accessible', 'it' => 'Accessibile'],
    ],
    'dienstmittel_type' => [
        'taxi' => ['en' => 'Taxi', 'es' => 'Taxi', 'fr' => 'Taxi', 'it' => 'Taxi'],
        'mietwagen' => ['en' => 'Private hire car', 'es' => 'Vehículo de alquiler con conductor', 'fr' => 'Voiture de location avec chauffeur', 'it' => 'Noleggio con conducente'],
        'grossraumtaxi' => ['en' => 'Large-capacity taxi', 'es' => 'Taxi de gran capacidad', 'fr' => 'Taxi grande capacité', 'it' => 'Taxi ad alta capienza'],
        'rollstuhltaxi' => ['en' => 'Wheelchair taxi', 'es' => 'Taxi adaptado', 'fr' => 'Taxi adapté fauteuil roulant', 'it' => 'Taxi per sedie a rotelle'],
        'taxameter' => ['en' => 'Taximeter', 'es' => 'Taxímetro', 'fr' => 'Taximètre', 'it' => 'Tassametro'],
        'wegstreckenzaehler' => ['en' => 'Odometer', 'es' => 'Cuentakilómetros', 'fr' => 'Compteur kilométrique', 'it' => 'Contachilometri'],
        'tse' => ['en' => 'TSE / security module', 'es' => 'TSE / módulo de seguridad', 'fr' => 'TSE / module de sécurité', 'it' => 'TSE / modulo di sicurezza'],
        'kartenleser' => ['en' => 'Card reader', 'es' => 'Lector de tarjetas', 'fr' => 'Lecteur de cartes', 'it' => 'Lettore di schede'],
        'kindersitz' => ['en' => 'Child seat', 'es' => 'Asiento infantil', 'fr' => 'Siège enfant', 'it' => 'Seggiolino'],
    ],
    'permit_type' => [
        'taxikonzession' => ['en' => 'Taxi licence (§ 47 PBefG)', 'es' => 'Licencia de taxi (§ 47 PBefG)', 'fr' => 'Licence de taxi (§ 47 PBefG)', 'it' => 'Licenza taxi (§ 47 PBefG)'],
        'mietwagengenehmigung' => ['en' => 'Private hire permit (§ 49 PBefG)', 'es' => 'Licencia VTC (§ 49 PBefG)', 'fr' => 'Autorisation VTC (§ 49 PBefG)', 'it' => 'Autorizzazione NCC (§ 49 PBefG)'],
        'bedarfsverkehrsgenehmigung' => ['en' => 'Permit for pooled on-demand transport (§ 50 PBefG)', 'es' => 'Permiso de transporte a demanda agrupado (§ 50 PBefG)', 'fr' => 'Autorisation de transport à la demande groupé (§ 50 PBefG)', 'it' => 'Autorizzazione al trasporto a chiamata aggregato (§ 50 PBefG)'],
        'fahrgastbefoerderung' => ['en' => 'Passenger transport licence (§ 48 FeV)', 'es' => 'Permiso de transporte de pasajeros (§ 48 FeV)', 'fr' => 'Permis de transport de passagers (§ 48 FeV)', 'it' => 'Patente per trasporto passeggeri (§ 48 FeV)'],
        'eichnachweis' => ['en' => 'Calibration certificate taximeter/odometer', 'es' => 'Certificado de verificación taxímetro/cuentakilómetros', 'fr' => 'Certificat d\'étalonnage taximètre/compteur', 'it' => 'Certificato di taratura tassametro/contachilometri'],
        'bokraftPruefung' => ['en' => 'BOKraft inspection', 'es' => 'Inspección BOKraft', 'fr' => 'Contrôle BOKraft', 'it' => 'Controllo BOKraft'],
    ],
];
