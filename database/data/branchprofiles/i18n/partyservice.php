<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : partyservice.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Übersetzungen zum Branchenprofil „partyservice" (MVP-841): je Domäne und Code die
// Labels für die aktivierbaren Sprachen; der BranchProfileInstaller schreibt
// sie nach classifications.label_i18n. Deutsch ist das Quell-Label im Profil.
return [
    'entry_type' => [
        'anfrage' => ['en' => 'Enquiry', 'es' => 'Consulta', 'fr' => 'Demande', 'it' => 'Richiesta'],
        'angebot' => ['en' => 'Quote', 'es' => 'Presupuesto', 'fr' => 'Devis', 'it' => 'Preventivo'],
        'menueplanung' => ['en' => 'Menu planning', 'es' => 'Planificación del menú', 'fr' => 'Planification du menu', 'it' => 'Pianificazione del menu'],
        'einkauf' => ['en' => 'Purchasing', 'es' => 'Compras', 'fr' => 'Achats', 'it' => 'Acquisti'],
        'vorbereitung' => ['en' => 'Preparation / mise en place', 'es' => 'Preparación / mise en place', 'fr' => 'Préparation / mise en place', 'it' => 'Preparazione / mise en place'],
        'anlieferung' => ['en' => 'Delivery', 'es' => 'Entrega', 'fr' => 'Livraison', 'it' => 'Consegna'],
        'aufbau' => ['en' => 'Buffet setup', 'es' => 'Montaje del bufé', 'fr' => 'Installation du buffet', 'it' => 'Allestimento buffet'],
        'service' => ['en' => 'On-site service', 'es' => 'Servicio en el lugar', 'fr' => 'Service sur place', 'it' => 'Servizio in loco'],
        'abbau' => ['en' => 'Dismantling', 'es' => 'Desmontaje', 'fr' => 'Démontage', 'it' => 'Smontaggio'],
        'ruecknahme' => ['en' => 'Return / cleaning', 'es' => 'Recogida / limpieza', 'fr' => 'Reprise / nettoyage', 'it' => 'Ritiro / pulizia'],
        'reklamation' => ['en' => 'Complaint', 'es' => 'Reclamación', 'fr' => 'Réclamation', 'it' => 'Reclamo'],
    ],
    'activity' => [
        'planen' => ['en' => 'Plan', 'es' => 'Planificar', 'fr' => 'Planifier', 'it' => 'Pianificare'],
        'einkaufen' => ['en' => 'Shopping', 'es' => 'Hacer la compra', 'fr' => 'Faire les courses', 'it' => 'Fare la spesa'],
        'vorbereiten' => ['en' => 'Prepare', 'es' => 'Preparar', 'fr' => 'Préparer', 'it' => 'Preparare'],
        'kochen' => ['en' => 'Cooking', 'es' => 'Cocinar', 'fr' => 'Cuisiner', 'it' => 'Cucinare'],
        'anrichten' => ['en' => 'Plating', 'es' => 'Emplatar', 'fr' => 'Dresser', 'it' => 'Impiattare'],
        'transportieren' => ['en' => 'Transport', 'es' => 'Transportar', 'fr' => 'Transporter', 'it' => 'Trasportare'],
        'aufbauen' => ['en' => 'Set up', 'es' => 'Montar', 'fr' => 'Monter', 'it' => 'Allestire'],
        'servieren' => ['en' => 'Serving', 'es' => 'Servir', 'fr' => 'Servir', 'it' => 'Servire'],
        'abbauen' => ['en' => 'Dismantle', 'es' => 'Desmontar', 'fr' => 'Démonter', 'it' => 'Smontare'],
        'reinigen' => ['en' => 'Clean', 'es' => 'Limpiar', 'fr' => 'Nettoyer', 'it' => 'Pulire'],
        'dokumentieren' => ['en' => 'Document', 'es' => 'Documentar', 'fr' => 'Documenter', 'it' => 'Documentare'],
    ],
    'defect_type' => [
        'kuehlketteUnterbrochen' => ['en' => 'Cold chain interrupted', 'es' => 'Cadena de frío interrumpida', 'fr' => 'Chaîne du froid interrompue', 'it' => 'Catena del freddo interrotta'],
        'mengeFalsch' => ['en' => 'Wrong quantity', 'es' => 'Cantidad incorrecta', 'fr' => 'Quantité erronée', 'it' => 'Quantità errata'],
        'allergenInfoFehlt' => ['en' => 'Allergen information missing', 'es' => 'Falta información de alérgenos', 'fr' => 'Information sur les allergènes manquante', 'it' => 'Informazioni sugli allergeni mancanti'],
        'qualitaetsmangel' => ['en' => 'Quality defect', 'es' => 'Defecto de calidad', 'fr' => 'Défaut de qualité', 'it' => 'Difetto di qualità'],
        'personalEngpass' => ['en' => 'Staff shortage', 'es' => 'Falta de personal', 'fr' => 'Manque de personnel', 'it' => 'Carenza di personale'],
        'equipmentFehlt' => ['en' => 'Equipment missing', 'es' => 'Falta equipamiento', 'fr' => 'Équipement manquant', 'it' => 'Attrezzatura mancante'],
        'transportschaden' => ['en' => 'Transport damage', 'es' => 'Daño en el transporte', 'fr' => 'Dommage de transport', 'it' => 'Danno da trasporto'],
        'kundenAenderung' => ['en' => 'Customer change request', 'es' => 'Cambio del cliente', 'fr' => 'Modification client', 'it' => 'Modifica del cliente'],
        'hygienemangel' => ['en' => 'Hygiene deficiency', 'es' => 'Deficiencia de higiene', 'fr' => 'Défaut d\'hygiène', 'it' => 'Carenza igienica'],
    ],
    'root_cause' => [
        'planung' => ['en' => 'Planning', 'es' => 'Planificación', 'fr' => 'Planification', 'it' => 'Pianificazione'],
        'einkauf' => ['en' => 'Purchasing', 'es' => 'Compras', 'fr' => 'Achats', 'it' => 'Acquisti'],
        'kuehlung' => ['en' => 'Cooling', 'es' => 'Refrigeración', 'fr' => 'Refroidissement', 'it' => 'Raffreddamento'],
        'personal' => ['en' => 'Staff', 'es' => 'Personal', 'fr' => 'Personnel', 'it' => 'Personale'],
        'lieferant' => ['en' => 'Supplier', 'es' => 'Proveedor', 'fr' => 'Fournisseur', 'it' => 'Fornitore'],
        'transport' => ['en' => 'Transport', 'es' => 'Transporte', 'fr' => 'Transport', 'it' => 'Trasporto'],
        'kommunikation' => ['en' => 'Communication', 'es' => 'Comunicación', 'fr' => 'Communication', 'it' => 'Comunicazione'],
        'wetter' => ['en' => 'Weather', 'es' => 'Clima', 'fr' => 'Météo', 'it' => 'Meteo'],
    ],
    'result' => [
        'erledigt' => ['en' => 'Done', 'es' => 'Hecho', 'fr' => 'Fait', 'it' => 'Fatto'],
        'teilErledigt' => ['en' => 'Partially done', 'es' => 'Hecho en parte', 'fr' => 'Partiellement fait', 'it' => 'Fatto in parte'],
        'nachbessern' => ['en' => 'Rectify', 'es' => 'Subsanar', 'fr' => 'Corriger', 'it' => 'Correggere'],
        'ersatzGeliefert' => ['en' => 'Replacement delivered', 'es' => 'Sustitución entregada', 'fr' => 'Remplacement livré', 'it' => 'Sostituzione consegnata'],
        'kundeInformiert' => ['en' => 'Customer informed', 'es' => 'Cliente informado', 'fr' => 'Client informé', 'it' => 'Cliente informato'],
        'freigegeben' => ['en' => 'Approved', 'es' => 'Aprobado', 'fr' => 'Validé', 'it' => 'Approvato'],
        'eskaliert' => ['en' => 'Escalated', 'es' => 'Escalado', 'fr' => 'Escaladé', 'it' => 'Escalato'],
    ],
    'priority' => [
        'standard' => ['en' => 'Standard', 'es' => 'Estándar', 'fr' => 'Standard', 'it' => 'Standard'],
        'express' => ['en' => 'Express / short notice', 'es' => 'Exprés / urgente', 'fr' => 'Express / court délai', 'it' => 'Espresso / a breve termine'],
        'grossveranstaltung' => ['en' => 'Major event', 'es' => 'Gran evento', 'fr' => 'Grand événement', 'it' => 'Grande evento'],
    ],
    'product_group' => [
        'vorspeise' => ['en' => 'Starter', 'es' => 'Entrante', 'fr' => 'Entrée', 'it' => 'Antipasto'],
        'hauptgang' => ['en' => 'Main course', 'es' => 'Plato principal', 'fr' => 'Plat principal', 'it' => 'Portata principale'],
        'dessert' => ['en' => 'Dessert', 'es' => 'Postre', 'fr' => 'Dessert', 'it' => 'Dessert'],
        'fingerfood' => ['en' => 'Finger food', 'es' => 'Bocaditos', 'fr' => 'Bouchées', 'it' => 'Finger food'],
        'buffet' => ['en' => 'Buffet', 'es' => 'Bufé', 'fr' => 'Buffet', 'it' => 'Buffet'],
        'getraenke' => ['en' => 'Beverages', 'es' => 'Bebidas', 'fr' => 'Boissons', 'it' => 'Bevande'],
        'kaffeebar' => ['en' => 'Coffee bar', 'es' => 'Barra de café', 'fr' => 'Bar à café', 'it' => 'Caffetteria'],
        'sonderkost' => ['en' => 'Special diet (vegan/halal/gluten-free)', 'es' => 'Dieta especial (vegana/halal/sin gluten)', 'fr' => 'Régime spécial (végan/halal/sans gluten)', 'it' => 'Dieta speciale (vegana/halal/senza glutine)'],
    ],
    'goodwill_reason' => [
        'kulanz' => ['en' => 'Goodwill', 'es' => 'Gesto comercial', 'fr' => 'Geste commercial', 'it' => 'Gesto commerciale'],
        'verspaetung' => ['en' => 'Delay', 'es' => 'Retraso', 'fr' => 'Retard', 'it' => 'Ritardo'],
        'qualitaet' => ['en' => 'Quality defect', 'es' => 'Defecto de calidad', 'fr' => 'Défaut de qualité', 'it' => 'Difetto di qualità'],
        'stammkunde' => ['en' => 'Regular customer', 'es' => 'Cliente habitual', 'fr' => 'Client régulier', 'it' => 'Cliente abituale'],
    ],
    'dienstmittel_type' => [
        'chafingDish' => ['en' => 'Chafing dish', 'es' => 'Chafing dish', 'fr' => 'Chafing dish', 'it' => 'Scaldavivande'],
        'thermoport' => ['en' => 'Insulated food carrier', 'es' => 'Contenedor isotérmico', 'fr' => 'Conteneur isotherme', 'it' => 'Contenitore termico'],
        'kuehlbox' => ['en' => 'Cool box', 'es' => 'Nevera portátil', 'fr' => 'Glacière', 'it' => 'Borsa frigo'],
        'geschirr' => ['en' => 'Tableware', 'es' => 'Vajilla', 'fr' => 'Vaisselle', 'it' => 'Stoviglie'],
        'glaeser' => ['en' => 'Glasses', 'es' => 'Vasos', 'fr' => 'Verres', 'it' => 'Bicchieri'],
        'besteck' => ['en' => 'Cutlery', 'es' => 'Cubiertos', 'fr' => 'Couverts', 'it' => 'Posate'],
        'mobiliar' => ['en' => 'Furniture (tables/chairs)', 'es' => 'Mobiliario (mesas/sillas)', 'fr' => 'Mobilier (tables/chaises)', 'it' => 'Arredi (tavoli/sedie)'],
    ],
    'allergen' => [
        'keine' => ['en' => 'No allergens', 'es' => 'Sin alérgenos', 'fr' => 'Aucun allergène', 'it' => 'Nessun allergene'],
        'gluten' => ['en' => 'Cereals containing gluten', 'es' => 'Cereales con gluten', 'fr' => 'Céréales contenant du gluten', 'it' => 'Cereali contenenti glutine'],
        'krebstiere' => ['en' => 'Crustaceans', 'es' => 'Crustáceos', 'fr' => 'Crustacés', 'it' => 'Crostacei'],
        'ei' => ['en' => 'Eggs', 'es' => 'Huevos', 'fr' => 'Œufs', 'it' => 'Uova'],
        'fisch' => ['en' => 'Fish', 'es' => 'Pescado', 'fr' => 'Poissons', 'it' => 'Pesce'],
        'erdnuss' => ['en' => 'Peanuts', 'es' => 'Cacahuetes', 'fr' => 'Arachides', 'it' => 'Arachidi'],
        'soja' => ['en' => 'Soy', 'es' => 'Soja', 'fr' => 'Soja', 'it' => 'Soia'],
        'milch' => ['en' => 'Milk / lactose', 'es' => 'Leche / lactosa', 'fr' => 'Lait / lactose', 'it' => 'Latte / lattosio'],
        'schalenfruechte' => ['en' => 'Tree nuts', 'es' => 'Frutos de cáscara', 'fr' => 'Fruits à coque', 'it' => 'Frutta a guscio'],
        'sellerie' => ['en' => 'Celery', 'es' => 'Apio', 'fr' => 'Céleri', 'it' => 'Sedano'],
        'senf' => ['en' => 'Mustard', 'es' => 'Mostaza', 'fr' => 'Moutarde', 'it' => 'Senape'],
        'sesam' => ['en' => 'Sesame', 'es' => 'Sésamo', 'fr' => 'Sésame', 'it' => 'Sesamo'],
        'sulfite' => ['en' => 'Sulphur dioxide / sulphites', 'es' => 'Dióxido de azufre / sulfitos', 'fr' => 'Anhydride sulfureux / sulfites', 'it' => 'Anidride solforosa / solfiti'],
        'lupine' => ['en' => 'Lupin', 'es' => 'Altramuces', 'fr' => 'Lupin', 'it' => 'Lupini'],
        'weichtiere' => ['en' => 'Molluscs', 'es' => 'Moluscos', 'fr' => 'Mollusques', 'it' => 'Molluschi'],
    ],
];
