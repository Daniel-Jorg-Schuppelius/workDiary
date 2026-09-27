<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : veranstalter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Übersetzungen zum Branchenprofil „veranstalter" (MVP-841): je Domäne und Code die
// Labels für die aktivierbaren Sprachen; der BranchProfileInstaller schreibt
// sie nach classifications.label_i18n. Deutsch ist das Quell-Label im Profil.
return [
    'entry_type' => [
        'briefing' => ['en' => 'Client briefing', 'es' => 'Briefing del cliente', 'fr' => 'Briefing client', 'it' => 'Briefing del cliente'],
        'konzept' => ['en' => 'Concept', 'es' => 'Concepto', 'fr' => 'Concept', 'it' => 'Concetto'],
        'budgetierung' => ['en' => 'Budgeting', 'es' => 'Presupuestación', 'fr' => 'Budgétisation', 'it' => 'Budgeting'],
        'locationScouting' => ['en' => 'Location scouting', 'es' => 'Búsqueda de ubicación', 'fr' => 'Repérage de lieu', 'it' => 'Ricerca location'],
        'genehmigung' => ['en' => 'Permits', 'es' => 'Permisos', 'fr' => 'Autorisations', 'it' => 'Autorizzazioni'],
        'dienstleisterBuchung' => ['en' => 'Vendor booking', 'es' => 'Contratación de proveedores', 'fr' => 'Réservation de prestataires', 'it' => 'Prenotazione fornitori'],
        'ticketing' => ['en' => 'Ticketing', 'es' => 'Venta de entradas', 'fr' => 'Billetterie', 'it' => 'Biglietteria'],
        'aufbauKoordination' => ['en' => 'Setup coordination', 'es' => 'Coordinación del montaje', 'fr' => 'Coordination du montage', 'it' => 'Coordinamento allestimento'],
        'durchfuehrung' => ['en' => 'Execution', 'es' => 'Ejecución', 'fr' => 'Déroulement', 'it' => 'Svolgimento'],
        'abbauKoordination' => ['en' => 'Teardown coordination', 'es' => 'Coordinación del desmontaje', 'fr' => 'Coordination du démontage', 'it' => 'Coordinamento smontaggio'],
        'nachbereitung' => ['en' => 'Follow-up / settlement', 'es' => 'Cierre / liquidación', 'fr' => 'Suivi / décompte', 'it' => 'Chiusura / rendiconto'],
        'zwischenfall' => ['en' => 'Incident', 'es' => 'Incidente', 'fr' => 'Incident', 'it' => 'Incidente'],
    ],
    'activity' => [
        'konzipieren' => ['en' => 'Design concept', 'es' => 'Diseñar', 'fr' => 'Concevoir', 'it' => 'Progettare'],
        'kalkulieren' => ['en' => 'Calculate', 'es' => 'Calcular', 'fr' => 'Chiffrer', 'it' => 'Calcolare'],
        'scouten' => ['en' => 'Scouting', 'es' => 'Explorar', 'fr' => 'Repérer', 'it' => 'Sopralluogo'],
        'beantragen' => ['en' => 'Apply for', 'es' => 'Solicitar', 'fr' => 'Demander', 'it' => 'Richiedere'],
        'beauftragen' => ['en' => 'Commission', 'es' => 'Encargar', 'fr' => 'Mandater', 'it' => 'Incaricare'],
        'koordinieren' => ['en' => 'Coordinate', 'es' => 'Coordinar', 'fr' => 'Coordonner', 'it' => 'Coordinare'],
        'betreuen' => ['en' => 'Look after', 'es' => 'Atender', 'fr' => 'Accompagner', 'it' => 'Assistere'],
        'dokumentieren' => ['en' => 'Document', 'es' => 'Documentar', 'fr' => 'Documenter', 'it' => 'Documentare'],
        'abrechnen' => ['en' => 'Billing', 'es' => 'Facturar', 'fr' => 'Facturer', 'it' => 'Fatturare'],
    ],
    'defect_type' => [
        'genehmigungFehlt' => ['en' => 'Permit missing', 'es' => 'Falta el permiso', 'fr' => 'Autorisation manquante', 'it' => 'Autorizzazione mancante'],
        'dienstleisterAusfall' => ['en' => 'Service provider failure', 'es' => 'Fallo del proveedor', 'fr' => 'Défaillance du prestataire', 'it' => 'Mancanza del fornitore'],
        'ueberbelegung' => ['en' => 'Overbooking / capacity', 'es' => 'Sobreocupación / capacidad', 'fr' => 'Surréservation / capacité', 'it' => 'Sovraffollamento / capacità'],
        'technikAusfall' => ['en' => 'Technical failure', 'es' => 'Fallo técnico', 'fr' => 'Panne technique', 'it' => 'Guasto tecnico'],
        'sicherheitsvorfall' => ['en' => 'Security incident', 'es' => 'Incidente de seguridad', 'fr' => 'Incident de sécurité', 'it' => 'Incidente di sicurezza'],
        'wetterabbruch' => ['en' => 'Cancelled due to weather', 'es' => 'Cancelado por el tiempo', 'fr' => 'Annulation météo', 'it' => 'Annullato per maltempo'],
        'zeitverzug' => ['en' => 'Time delay', 'es' => 'Retraso', 'fr' => 'Retard', 'it' => 'Ritardo'],
        'budgetueberschreitung' => ['en' => 'Budget overrun', 'es' => 'Exceso de presupuesto', 'fr' => 'Dépassement de budget', 'it' => 'Sforamento del budget'],
    ],
    'root_cause' => [
        'planung' => ['en' => 'Planning', 'es' => 'Planificación', 'fr' => 'Planification', 'it' => 'Pianificazione'],
        'behoerde' => ['en' => 'Authority', 'es' => 'Autoridad', 'fr' => 'Autorité', 'it' => 'Autorità'],
        'dienstleister' => ['en' => 'Service provider', 'es' => 'Proveedor de servicios', 'fr' => 'Prestataire', 'it' => 'Fornitore di servizi'],
        'kommunikation' => ['en' => 'Communication', 'es' => 'Comunicación', 'fr' => 'Communication', 'it' => 'Comunicazione'],
        'wetter' => ['en' => 'Weather', 'es' => 'Clima', 'fr' => 'Météo', 'it' => 'Meteo'],
        'technik' => ['en' => 'Technology', 'es' => 'Técnica', 'fr' => 'Technique', 'it' => 'Tecnica'],
        'besucher' => ['en' => 'Visitors', 'es' => 'Visitantes', 'fr' => 'Visiteurs', 'it' => 'Visitatori'],
        'budget' => ['en' => 'Budget', 'es' => 'Presupuesto', 'fr' => 'Budget', 'it' => 'Budget'],
    ],
    'result' => [
        'erledigt' => ['en' => 'Done', 'es' => 'Hecho', 'fr' => 'Fait', 'it' => 'Fatto'],
        'teilErledigt' => ['en' => 'Partially done', 'es' => 'Hecho en parte', 'fr' => 'Partiellement fait', 'it' => 'Fatto in parte'],
        'verschoben' => ['en' => 'Postponed', 'es' => 'Aplazado', 'fr' => 'Reporté', 'it' => 'Rinviato'],
        'abgesagt' => ['en' => 'Cancelled', 'es' => 'Cancelado', 'fr' => 'Annulé', 'it' => 'Annullato'],
        'freigegeben' => ['en' => 'Approved', 'es' => 'Aprobado', 'fr' => 'Validé', 'it' => 'Approvato'],
        'kundeInformiert' => ['en' => 'Customer informed', 'es' => 'Cliente informado', 'fr' => 'Client informé', 'it' => 'Cliente informato'],
        'eskaliert' => ['en' => 'Escalated', 'es' => 'Escalado', 'fr' => 'Escaladé', 'it' => 'Escalato'],
    ],
    'priority' => [
        'niedrig' => ['en' => 'Low risk', 'es' => 'Riesgo bajo', 'fr' => 'Risque faible', 'it' => 'Rischio basso'],
        'mittel' => ['en' => 'Medium risk', 'es' => 'Riesgo medio', 'fr' => 'Risque moyen', 'it' => 'Rischio medio'],
        'hoch' => ['en' => 'High risk', 'es' => 'Riesgo alto', 'fr' => 'Risque élevé', 'it' => 'Rischio elevato'],
        'kritisch' => ['en' => 'Critical risk', 'es' => 'Riesgo crítico', 'fr' => 'Risque critique', 'it' => 'Rischio critico'],
    ],
    'product_group' => [
        'konzert' => ['en' => 'Concert', 'es' => 'Concierto', 'fr' => 'Concert', 'it' => 'Concerto'],
        'festival' => ['en' => 'Festival', 'es' => 'Festival', 'fr' => 'Festival', 'it' => 'Festival'],
        'messe' => ['en' => 'Trade fair', 'es' => 'Feria', 'fr' => 'Salon', 'it' => 'Fiera'],
        'firmenevent' => ['en' => 'Corporate event', 'es' => 'Evento corporativo', 'fr' => 'Événement d\'entreprise', 'it' => 'Evento aziendale'],
        'konferenz' => ['en' => 'Conference / meeting', 'es' => 'Conferencia / congreso', 'fr' => 'Conférence / congrès', 'it' => 'Conferenza / convegno'],
        'gala' => ['en' => 'Gala', 'es' => 'Gala', 'fr' => 'Gala', 'it' => 'Gala'],
        'sportevent' => ['en' => 'Sports event', 'es' => 'Evento deportivo', 'fr' => 'Événement sportif', 'it' => 'Evento sportivo'],
        'strassenfest' => ['en' => 'Street festival', 'es' => 'Fiesta callejera', 'fr' => 'Fête de rue', 'it' => 'Festa di strada'],
    ],
    'dienstmittel_type' => [
        'funkgeraet' => ['en' => 'Radio', 'es' => 'Radio', 'fr' => 'Radio', 'it' => 'Radio'],
        'absperrung' => ['en' => 'Barrier', 'es' => 'Vallado', 'fr' => 'Barrière', 'it' => 'Transennamento'],
        'beschilderung' => ['en' => 'Signage', 'es' => 'Señalización', 'fr' => 'Signalisation', 'it' => 'Segnaletica'],
        'kassensystem' => ['en' => 'POS / ticketing system', 'es' => 'Sistema de caja / venta de entradas', 'fr' => 'Système de caisse / billetterie', 'it' => 'Sistema di cassa / biglietteria'],
        'zeltpavillon' => ['en' => 'Tent / pavilion', 'es' => 'Carpa / pabellón', 'fr' => 'Tente / chapiteau', 'it' => 'Tenda / gazebo'],
    ],
    'trade' => [
        'catering' => ['en' => 'Catering', 'es' => 'Catering', 'fr' => 'Traiteur', 'it' => 'Catering'],
        'technik' => ['en' => 'Event technology', 'es' => 'Técnica de eventos', 'fr' => 'Technique événementielle', 'it' => 'Tecnica per eventi'],
        'security' => ['en' => 'Security', 'es' => 'Seguridad', 'fr' => 'Sécurité', 'it' => 'Sicurezza'],
        'buehne' => ['en' => 'Stage / rigging', 'es' => 'Escenario / rigging', 'fr' => 'Scène / accroche', 'it' => 'Palco / rigging'],
        'kuenstler' => ['en' => 'Artists / acts', 'es' => 'Artistas / actuaciones', 'fr' => 'Artistes / numéros', 'it' => 'Artisti / esibizioni'],
        'ticketing' => ['en' => 'Ticketing', 'es' => 'Venta de entradas', 'fr' => 'Billetterie', 'it' => 'Biglietteria'],
        'sanitaet' => ['en' => 'First aid service', 'es' => 'Servicio sanitario', 'fr' => 'Service de secours', 'it' => 'Servizio sanitario'],
        'reinigung' => ['en' => 'Cleaning', 'es' => 'Limpieza', 'fr' => 'Nettoyage', 'it' => 'Pulizia'],
        'transport' => ['en' => 'Transport / logistics', 'es' => 'Transporte / logística', 'fr' => 'Transport / logistique', 'it' => 'Trasporto / logistica'],
        'dekoration' => ['en' => 'Decoration', 'es' => 'Decoración', 'fr' => 'Décoration', 'it' => 'Decorazione'],
    ],
    'permit_type' => [
        'sondernutzung' => ['en' => 'Special use of public space', 'es' => 'Uso especial del espacio público', 'fr' => 'Occupation du domaine public', 'it' => 'Occupazione di suolo pubblico'],
        'sperrzeit' => ['en' => 'Reduced closing hours', 'es' => 'Ampliación de horario', 'fr' => 'Réduction de l\'heure de fermeture', 'it' => 'Riduzione dell\'orario di chiusura'],
        'gema' => ['en' => 'GEMA registration', 'es' => 'Registro en la GEMA', 'fr' => 'Déclaration GEMA', 'it' => 'Registrazione GEMA'],
        'schankerlaubnis' => ['en' => 'Licence to serve alcohol', 'es' => 'Licencia de bebidas', 'fr' => 'Licence de débit de boissons', 'it' => 'Licenza di somministrazione'],
        'sicherheitskonzept' => ['en' => 'Security concept', 'es' => 'Concepto de seguridad', 'fr' => 'Concept de sécurité', 'it' => 'Piano di sicurezza'],
        'brandschutz' => ['en' => 'Fire protection', 'es' => 'Protección contra incendios', 'fr' => 'Protection incendie', 'it' => 'Protezione antincendio'],
        'laermschutz' => ['en' => 'Noise protection / exemption', 'es' => 'Protección acústica / excepción', 'fr' => 'Protection contre le bruit / dérogation', 'it' => 'Protezione dal rumore / deroga'],
        'lebensmittel' => ['en' => 'Food / restaurant', 'es' => 'Alimentación / hostelería', 'fr' => 'Alimentation / restauration', 'it' => 'Alimenti / ristorazione'],
    ],
];
