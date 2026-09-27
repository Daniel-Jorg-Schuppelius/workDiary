<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : kfz-fuhrparkservice.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Übersetzungen zum Branchenprofil „kfz-fuhrparkservice" (MVP-841): je Domäne und Code die
// Labels für die aktivierbaren Sprachen; der BranchProfileInstaller schreibt
// sie nach classifications.label_i18n. Deutsch ist das Quell-Label im Profil.
return [
    'entry_type' => [
        'annahme' => ['en' => 'Vehicle check-in', 'es' => 'Recepción del vehículo', 'fr' => 'Prise en charge du véhicule', 'it' => 'Accettazione veicolo'],
        'wartung' => ['en' => 'Maintenance/service', 'es' => 'Mantenimiento/servicio', 'fr' => 'Entretien/révision', 'it' => 'Manutenzione/tagliando'],
        'reparatur' => ['en' => 'Repair', 'es' => 'Reparación', 'fr' => 'Réparation', 'it' => 'Riparazione'],
        'diagnose' => ['en' => 'Diagnosis', 'es' => 'Diagnóstico', 'fr' => 'Diagnostic', 'it' => 'Diagnosi'],
        'reifenwechsel' => ['en' => 'Tyre change', 'es' => 'Cambio de neumáticos', 'fr' => 'Changement de pneus', 'it' => 'Cambio pneumatici'],
        'schaden' => ['en' => 'Damage', 'es' => 'Daño', 'fr' => 'Sinistre', 'it' => 'Danno'],
        'huAu' => ['en' => 'Roadworthiness test prep', 'es' => 'Preparación ITV', 'fr' => 'Préparation contrôle technique', 'it' => 'Preparazione revisione'],
        'uebergabe' => ['en' => 'Vehicle handover', 'es' => 'Entrega del vehículo', 'fr' => 'Remise du véhicule', 'it' => 'Consegna veicolo'],
        'rueckgabe' => ['en' => 'Vehicle return', 'es' => 'Devolución del vehículo', 'fr' => 'Restitution du véhicule', 'it' => 'Restituzione veicolo'],
        'nachkalkulation' => ['en' => 'Post-calculation', 'es' => 'Cálculo posterior', 'fr' => 'Calcul a posteriori', 'it' => 'Consuntivo'],
    ],
    'activity' => [
        'pruefen' => ['en' => 'Check', 'es' => 'Comprobar', 'fr' => 'Vérifier', 'it' => 'Verificare'],
        'messen' => ['en' => 'Measure', 'es' => 'Medir', 'fr' => 'Mesurer', 'it' => 'Misurare'],
        'tauschen' => ['en' => 'Replace', 'es' => 'Sustituir', 'fr' => 'Remplacer', 'it' => 'Sostituire'],
        'reinigen' => ['en' => 'Clean', 'es' => 'Limpiar', 'fr' => 'Nettoyer', 'it' => 'Pulire'],
        'kalibrieren' => ['en' => 'Calibrate', 'es' => 'Calibrar', 'fr' => 'Étalonner', 'it' => 'Calibrare'],
        'probefahrt' => ['en' => 'Test drive', 'es' => 'Prueba de conducción', 'fr' => 'Essai routier', 'it' => 'Prova su strada'],
        'dokumentieren' => ['en' => 'Document', 'es' => 'Documentar', 'fr' => 'Documenter', 'it' => 'Documentare'],
        'bestellen' => ['en' => 'Order', 'es' => 'Pedir', 'fr' => 'Commander', 'it' => 'Ordinare'],
        'uebergeben' => ['en' => 'Hand over', 'es' => 'Entregar', 'fr' => 'Remettre', 'it' => 'Consegnare'],
    ],
    'defect_type' => [
        'motorschaden' => ['en' => 'Engine damage', 'es' => 'Avería del motor', 'fr' => 'Dommage moteur', 'it' => 'Danno al motore'],
        'bremsen' => ['en' => 'Brakes', 'es' => 'Frenos', 'fr' => 'Freins', 'it' => 'Freni'],
        'elektrik' => ['en' => 'Electrics', 'es' => 'Electricidad', 'fr' => 'Électricité', 'it' => 'Impianto elettrico'],
        'karosserie' => ['en' => 'Bodywork', 'es' => 'Carrocería', 'fr' => 'Carrosserie', 'it' => 'Carrozzeria'],
        'reifen' => ['en' => 'Tyres', 'es' => 'Neumáticos', 'fr' => 'Pneus', 'it' => 'Pneumatici'],
        'scheibe' => ['en' => 'Windscreen', 'es' => 'Luna', 'fr' => 'Vitre', 'it' => 'Parabrezza'],
        'lack' => ['en' => 'Paint', 'es' => 'Pintura', 'fr' => 'Peinture', 'it' => 'Vernice'],
        'fahrwerk' => ['en' => 'Chassis', 'es' => 'Tren de rodaje', 'fr' => 'Train roulant', 'it' => 'Telaio'],
        'verschleiss' => ['en' => 'Wear', 'es' => 'Desgaste', 'fr' => 'Usure', 'it' => 'Usura'],
    ],
    'root_cause' => [
        'verschleiss' => ['en' => 'Wear', 'es' => 'Desgaste', 'fr' => 'Usure', 'it' => 'Usura'],
        'unfall' => ['en' => 'Accident', 'es' => 'Accidente', 'fr' => 'Accident', 'it' => 'Incidente'],
        'bedienfehler' => ['en' => 'Operating error', 'es' => 'Error de manejo', 'fr' => 'Erreur de manipulation', 'it' => 'Errore di utilizzo'],
        'wartungUeberfaellig' => ['en' => 'Maintenance overdue', 'es' => 'Mantenimiento vencido', 'fr' => 'Maintenance en retard', 'it' => 'Manutenzione scaduta'],
        'material' => ['en' => 'Material defect', 'es' => 'Defecto de material', 'fr' => 'Défaut de matériau', 'it' => 'Difetto del materiale'],
        'fremdeinwirkung' => ['en' => 'Third-party interference', 'es' => 'Intervención de terceros', 'fr' => 'Intervention extérieure', 'it' => 'Intervento di terzi'],
    ],
    'result' => [
        'erledigt' => ['en' => 'Done', 'es' => 'Hecho', 'fr' => 'Fait', 'it' => 'Fatto'],
        'teilErledigt' => ['en' => 'Partially done', 'es' => 'Hecho en parte', 'fr' => 'Partiellement fait', 'it' => 'Fatto in parte'],
        'fahrbereit' => ['en' => 'Roadworthy', 'es' => 'Listo para circular', 'fr' => 'En état de rouler', 'it' => 'Pronto per la marcia'],
        'nichtFahrbereit' => ['en' => 'Not roadworthy', 'es' => 'No apto para circular', 'fr' => 'Hors d\'état de rouler', 'it' => 'Non pronto per la marcia'],
        'teileNoetig' => ['en' => 'Parts needed', 'es' => 'Se necesitan piezas', 'fr' => 'Pièces nécessaires', 'it' => 'Servono ricambi'],
        'kundeInformiert' => ['en' => 'Customer informed', 'es' => 'Cliente informado', 'fr' => 'Client informé', 'it' => 'Cliente informato'],
        'eskaliert' => ['en' => 'Escalated', 'es' => 'Escalado', 'fr' => 'Escaladé', 'it' => 'Escalato'],
    ],
    'product_group' => [
        'pkw' => ['en' => 'Car', 'es' => 'Turismo', 'fr' => 'Voiture', 'it' => 'Autovettura'],
        'transporter' => ['en' => 'Van', 'es' => 'Furgoneta', 'fr' => 'Fourgon', 'it' => 'Furgone'],
        'lkw' => ['en' => 'Truck', 'es' => 'Camión', 'fr' => 'Camion', 'it' => 'Camion'],
        'anhaenger' => ['en' => 'Trailer', 'es' => 'Remolque', 'fr' => 'Remorque', 'it' => 'Rimorchio'],
        'reifen' => ['en' => 'Tyres', 'es' => 'Neumáticos', 'fr' => 'Pneus', 'it' => 'Pneumatici'],
        'bremse' => ['en' => 'Brake', 'es' => 'Freno', 'fr' => 'Frein', 'it' => 'Freno'],
        'motor' => ['en' => 'Engine', 'es' => 'Motor', 'fr' => 'Moteur', 'it' => 'Motore'],
        'batterie' => ['en' => 'Battery', 'es' => 'Batería', 'fr' => 'Batterie', 'it' => 'Batteria'],
        'karosserie' => ['en' => 'Bodywork', 'es' => 'Carrocería', 'fr' => 'Carrosserie', 'it' => 'Carrozzeria'],
        'innenraum' => ['en' => 'Interior', 'es' => 'Interior', 'fr' => 'Intérieur', 'it' => 'Interno'],
    ],
];
