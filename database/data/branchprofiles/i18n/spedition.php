<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : spedition.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Übersetzungen zum Branchenprofil „spedition" (MVP-841): je Domäne und Code die
// Labels für die aktivierbaren Sprachen; der BranchProfileInstaller schreibt
// sie nach classifications.label_i18n. Deutsch ist das Quell-Label im Profil.
return [
    'entry_type' => [
        'transportauftrag' => ['en' => 'Transport order', 'es' => 'Orden de transporte', 'fr' => 'Ordre de transport', 'it' => 'Ordine di trasporto'],
        'disposition' => ['en' => 'Dispatching', 'es' => 'Planificación', 'fr' => 'Dispatching', 'it' => 'Pianificazione'],
        'abholung' => ['en' => 'Pickup', 'es' => 'Recogida', 'fr' => 'Enlèvement', 'it' => 'Ritiro'],
        'beladung' => ['en' => 'Loading', 'es' => 'Carga', 'fr' => 'Chargement', 'it' => 'Carico'],
        'umschlag' => ['en' => 'Transshipment', 'es' => 'Transbordo', 'fr' => 'Transbordement', 'it' => 'Trasbordo'],
        'transport' => ['en' => 'Transport', 'es' => 'Transporte', 'fr' => 'Transport', 'it' => 'Trasporto'],
        'zustellung' => ['en' => 'Delivery', 'es' => 'Entrega', 'fr' => 'Livraison', 'it' => 'Consegna'],
        'rueckladung' => ['en' => 'Return load', 'es' => 'Carga de retorno', 'fr' => 'Fret de retour', 'it' => 'Carico di ritorno'],
        'wartezeit' => ['en' => 'Waiting time', 'es' => 'Tiempo de espera', 'fr' => 'Temps d\'attente', 'it' => 'Tempo di attesa'],
        'schaden' => ['en' => 'Damage', 'es' => 'Daño', 'fr' => 'Avarie', 'it' => 'Danno'],
        'reklamation' => ['en' => 'Complaint', 'es' => 'Reclamación', 'fr' => 'Réclamation', 'it' => 'Reclamo'],
        'nachkalkulation' => ['en' => 'Post-calculation', 'es' => 'Cálculo posterior', 'fr' => 'Calcul a posteriori', 'it' => 'Consuntivo'],
    ],
    'activity' => [
        'planen' => ['en' => 'Plan', 'es' => 'Planificar', 'fr' => 'Planifier', 'it' => 'Pianificare'],
        'avisieren' => ['en' => 'Notify', 'es' => 'Avisar', 'fr' => 'Aviser', 'it' => 'Preavvisare'],
        'laden' => ['en' => 'Load', 'es' => 'Cargar', 'fr' => 'Charger', 'it' => 'Caricare'],
        'sichern' => ['en' => 'Back up', 'es' => 'Hacer copia', 'fr' => 'Sauvegarder', 'it' => 'Eseguire backup'],
        'fahren' => ['en' => 'Driving', 'es' => 'Conducir', 'fr' => 'Conduire', 'it' => 'Guidare'],
        'umladen' => ['en' => 'Transship', 'es' => 'Transbordar', 'fr' => 'Transborder', 'it' => 'Trasbordare'],
        'entladen' => ['en' => 'Unload', 'es' => 'Descargar', 'fr' => 'Décharger', 'it' => 'Scaricare'],
        'scannen' => ['en' => 'Scan', 'es' => 'Escanear', 'fr' => 'Numériser', 'it' => 'Scansionare'],
        'dokumentieren' => ['en' => 'Document', 'es' => 'Documentar', 'fr' => 'Documenter', 'it' => 'Documentare'],
        'palettenTauschen' => ['en' => 'Pallet exchange', 'es' => 'Intercambio de palés', 'fr' => 'Échange de palettes', 'it' => 'Scambio pallet'],
    ],
    'defect_type' => [
        'lieferverzug' => ['en' => 'Delivery delay', 'es' => 'Retraso en la entrega', 'fr' => 'Retard de livraison', 'it' => 'Ritardo di consegna'],
        'transportschaden' => ['en' => 'Transport damage', 'es' => 'Daño en el transporte', 'fr' => 'Dommage de transport', 'it' => 'Danno da trasporto'],
        'fehlmenge' => ['en' => 'Shortfall', 'es' => 'Cantidad faltante', 'fr' => 'Quantité manquante', 'it' => 'Quantità mancante'],
        'falscheWare' => ['en' => 'Wrong goods', 'es' => 'Mercancía incorrecta', 'fr' => 'Mauvaise marchandise', 'it' => 'Merce errata'],
        'temperaturAbweichend' => ['en' => 'Temperature deviation', 'es' => 'Desviación de temperatura', 'fr' => 'Écart de température', 'it' => 'Temperatura fuori norma'],
        'annahmeVerweigert' => ['en' => 'Acceptance refused', 'es' => 'Recepción rechazada', 'fr' => 'Réception refusée', 'it' => 'Accettazione rifiutata'],
        'dokumentFehlt' => ['en' => 'Document missing', 'es' => 'Falta el documento', 'fr' => 'Document manquant', 'it' => 'Documento mancante'],
        'lademittelDifferenz' => ['en' => 'Load carrier discrepancy', 'es' => 'Diferencia de soportes de carga', 'fr' => 'Écart de supports de charge', 'it' => 'Differenza supporti di carico'],
    ],
    'root_cause' => [
        'verkehr' => ['en' => 'Traffic', 'es' => 'Tráfico', 'fr' => 'Circulation', 'it' => 'Traffico'],
        'verspaeteteBereitstellung' => ['en' => 'Late provision', 'es' => 'Puesta a disposición tardía', 'fr' => 'Mise à disposition tardive', 'it' => 'Messa a disposizione tardiva'],
        'rampenstau' => ['en' => 'Loading dock congestion', 'es' => 'Congestión en el muelle', 'fr' => 'Engorgement du quai', 'it' => 'Coda alla rampa'],
        'falscheAdresse' => ['en' => 'Wrong address', 'es' => 'Dirección incorrecta', 'fr' => 'Mauvaise adresse', 'it' => 'Indirizzo errato'],
        'fahrzeugDefekt' => ['en' => 'Vehicle defect', 'es' => 'Avería del vehículo', 'fr' => 'Panne du véhicule', 'it' => 'Guasto al veicolo'],
        'personalEngpass' => ['en' => 'Staff shortage', 'es' => 'Falta de personal', 'fr' => 'Manque de personnel', 'it' => 'Carenza di personale'],
        'wetter' => ['en' => 'Weather', 'es' => 'Clima', 'fr' => 'Météo', 'it' => 'Meteo'],
        'kundenfehler' => ['en' => 'Customer error', 'es' => 'Error del cliente', 'fr' => 'Erreur du client', 'it' => 'Errore del cliente'],
        'subunternehmer' => ['en' => 'Subcontractor', 'es' => 'Subcontratista', 'fr' => 'Sous-traitant', 'it' => 'Subappaltatore'],
    ],
    'result' => [
        'zugestellt' => ['en' => 'Delivered', 'es' => 'Entregado', 'fr' => 'Livré', 'it' => 'Consegnato'],
        'teilZugestellt' => ['en' => 'Partially delivered', 'es' => 'Entregado en parte', 'fr' => 'Partiellement livré', 'it' => 'Consegnato in parte'],
        'nichtZugestellt' => ['en' => 'Not delivered', 'es' => 'No entregado', 'fr' => 'Non livré', 'it' => 'Non consegnato'],
        'retourniert' => ['en' => 'Returned', 'es' => 'Devuelto', 'fr' => 'Retourné', 'it' => 'Restituito'],
        'umdisponiert' => ['en' => 'Rescheduled', 'es' => 'Replanificado', 'fr' => 'Replanifié', 'it' => 'Ripianificato'],
        'eskaliert' => ['en' => 'Escalated', 'es' => 'Escalado', 'fr' => 'Escaladé', 'it' => 'Escalato'],
        'schadenGemeldet' => ['en' => 'Damage reported', 'es' => 'Daño notificado', 'fr' => 'Dommage signalé', 'it' => 'Danno segnalato'],
        'abgerechnet' => ['en' => 'Billed', 'es' => 'Facturado', 'fr' => 'Facturé', 'it' => 'Fatturato'],
    ],
    'product_group' => [
        'stueckgut' => ['en' => 'General cargo', 'es' => 'Carga general', 'fr' => 'Colis de détail', 'it' => 'Merce a colli'],
        'teilpartie' => ['en' => 'Part load', 'es' => 'Carga parcial', 'fr' => 'Chargement partiel', 'it' => 'Carico parziale'],
        'komplettladung' => ['en' => 'Full truckload', 'es' => 'Carga completa', 'fr' => 'Chargement complet', 'it' => 'Carico completo'],
        'express' => ['en' => 'Express', 'es' => 'Exprés', 'fr' => 'Express', 'it' => 'Espresso'],
        'kuehlgut' => ['en' => 'Chilled goods', 'es' => 'Mercancía refrigerada', 'fr' => 'Marchandises réfrigérées', 'it' => 'Merce refrigerata'],
        'gefahrgut' => ['en' => 'Dangerous goods', 'es' => 'Mercancías peligrosas', 'fr' => 'Marchandises dangereuses', 'it' => 'Merci pericolose'],
        'sperrgut' => ['en' => 'Bulky goods', 'es' => 'Mercancía voluminosa', 'fr' => 'Marchandise encombrante', 'it' => 'Merce ingombrante'],
        'palettenware' => ['en' => 'Palletised goods', 'es' => 'Mercancía paletizada', 'fr' => 'Marchandise palettisée', 'it' => 'Merce pallettizzata'],
        'retouren' => ['en' => 'Returns', 'es' => 'Devoluciones', 'fr' => 'Retours', 'it' => 'Resi'],
        'lagerware' => ['en' => 'Stock goods', 'es' => 'Mercancía en stock', 'fr' => 'Marchandise en stock', 'it' => 'Merce a magazzino'],
    ],
    'dienstmittel_type' => [
        'sattelzug' => ['en' => 'Articulated lorry', 'es' => 'Camión articulado', 'fr' => 'Semi-remorque', 'it' => 'Autoarticolato'],
        'wechselbruecke' => ['en' => 'Swap body', 'es' => 'Caja móvil', 'fr' => 'Caisse mobile', 'it' => 'Cassa mobile'],
        'sprinter' => ['en' => 'Van', 'es' => 'Furgoneta', 'fr' => 'Fourgon', 'it' => 'Furgone'],
        'kuehlfahrzeug' => ['en' => 'Refrigerated vehicle', 'es' => 'Vehículo frigorífico', 'fr' => 'Véhicule frigorifique', 'it' => 'Veicolo refrigerato'],
        'anhaenger' => ['en' => 'Trailer', 'es' => 'Remolque', 'fr' => 'Remorque', 'it' => 'Rimorchio'],
        'auflieger' => ['en' => 'Semi-trailer', 'es' => 'Semirremolque', 'fr' => 'Semi-remorque', 'it' => 'Semirimorchio'],
        'stapler' => ['en' => 'Forklift', 'es' => 'Carretilla elevadora', 'fr' => 'Chariot élévateur', 'it' => 'Carrello elevatore'],
        'hubwagen' => ['en' => 'Pallet truck', 'es' => 'Transpaleta', 'fr' => 'Transpalette', 'it' => 'Transpallet'],
        'scanner' => ['en' => 'Scanner', 'es' => 'Escáner', 'fr' => 'Scanner', 'it' => 'Scanner'],
        'spanngurt' => ['en' => 'Ratchet strap', 'es' => 'Cincha', 'fr' => 'Sangle d\'arrimage', 'it' => 'Cinghia di ancoraggio'],
    ],
    'permit_type' => [
        'grossraumtransport' => ['en' => 'Oversize transport', 'es' => 'Transporte especial', 'fr' => 'Transport exceptionnel', 'it' => 'Trasporto eccezionale'],
        'schwertransport' => ['en' => 'Heavy transport', 'es' => 'Transporte pesado', 'fr' => 'Transport lourd', 'it' => 'Trasporto pesante'],
        'gefahrgut_adr' => ['en' => 'Dangerous goods / ADR', 'es' => 'Mercancías peligrosas / ADR', 'fr' => 'Marchandises dangereuses / ADR', 'it' => 'Merci pericolose / ADR'],
        'kabotage' => ['en' => 'Cabotage', 'es' => 'Cabotaje', 'fr' => 'Cabotage', 'it' => 'Cabotaggio'],
        'gewichtsausnahme' => ['en' => 'Weight/dimension exemption', 'es' => 'Excepción de peso/dimensiones', 'fr' => 'Dérogation poids/dimensions', 'it' => 'Deroga peso/dimensioni'],
        'sondernutzung' => ['en' => 'Special road use', 'es' => 'Uso especial de la vía', 'fr' => 'Occupation du domaine routier', 'it' => 'Occupazione suolo stradale'],
    ],
];
