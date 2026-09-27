<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : shk.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Übersetzungen zum Branchenprofil „shk" (MVP-841): je Domäne und Code die
// Labels für die aktivierbaren Sprachen; der BranchProfileInstaller schreibt
// sie nach classifications.label_i18n. Deutsch ist das Quell-Label im Profil.
return [
    'entry_type' => [
        'wartung' => ['en' => 'Maintenance', 'es' => 'Mantenimiento', 'fr' => 'Maintenance', 'it' => 'Manutenzione'],
        'stoerung' => ['en' => 'Fault', 'es' => 'Avería', 'fr' => 'Panne', 'it' => 'Guasto'],
        'reparatur' => ['en' => 'Repair', 'es' => 'Reparación', 'fr' => 'Réparation', 'it' => 'Riparazione'],
        'installation' => ['en' => 'Installation', 'es' => 'Instalación', 'fr' => 'Installation', 'it' => 'Installazione'],
        'inbetriebnahme' => ['en' => 'Commissioning', 'es' => 'Puesta en marcha', 'fr' => 'Mise en service', 'it' => 'Messa in servizio'],
        'druckpruefung' => ['en' => 'Pressure test', 'es' => 'Prueba de presión', 'fr' => 'Essai de pression', 'it' => 'Prova di pressione'],
        'dichtheitspruefung' => ['en' => 'Leak test', 'es' => 'Prueba de estanqueidad', 'fr' => 'Essai d\'étanchéité', 'it' => 'Prova di tenuta'],
        'abnahme' => ['en' => 'Acceptance', 'es' => 'Recepción', 'fr' => 'Réception', 'it' => 'Collaudo'],
        'notdienst' => ['en' => 'Emergency service', 'es' => 'Servicio de urgencia', 'fr' => 'Service d\'urgence', 'it' => 'Servizio di emergenza'],
    ],
    'activity' => [
        'entlueften' => ['en' => 'Bleed', 'es' => 'Purgar', 'fr' => 'Purger', 'it' => 'Sfiatare'],
        'reinigen' => ['en' => 'Clean', 'es' => 'Limpiar', 'fr' => 'Nettoyer', 'it' => 'Pulire'],
        'tauschen' => ['en' => 'Replace', 'es' => 'Sustituir', 'fr' => 'Remplacer', 'it' => 'Sostituire'],
        'messen' => ['en' => 'Measure', 'es' => 'Medir', 'fr' => 'Mesurer', 'it' => 'Misurare'],
        'abdichten' => ['en' => 'Sealing', 'es' => 'Sellar', 'fr' => 'Étanchéifier', 'it' => 'Sigillare'],
        'einstellen' => ['en' => 'Adjust', 'es' => 'Ajustar', 'fr' => 'Régler', 'it' => 'Regolare'],
        'spuelen' => ['en' => 'Flush', 'es' => 'Enjuagar', 'fr' => 'Rincer', 'it' => 'Risciacquare'],
        'pruefen' => ['en' => 'Check', 'es' => 'Comprobar', 'fr' => 'Vérifier', 'it' => 'Verificare'],
        'dokumentieren' => ['en' => 'Document', 'es' => 'Documentar', 'fr' => 'Documenter', 'it' => 'Documentare'],
    ],
    'defect_type' => [
        'leckage' => ['en' => 'Leakage', 'es' => 'Fuga', 'fr' => 'Fuite', 'it' => 'Perdita'],
        'druckverlust' => ['en' => 'Pressure loss', 'es' => 'Pérdida de presión', 'fr' => 'Perte de pression', 'it' => 'Perdita di pressione'],
        'heizungsstoerung' => ['en' => 'Heating fault', 'es' => 'Avería de la calefacción', 'fr' => 'Panne de chauffage', 'it' => 'Guasto al riscaldamento'],
        'verstopfung' => ['en' => 'Blockage', 'es' => 'Atasco', 'fr' => 'Obstruction', 'it' => 'Ostruzione'],
        'korrosion' => ['en' => 'Corrosion', 'es' => 'Corrosión', 'fr' => 'Corrosion', 'it' => 'Corrosione'],
        'sensorDefekt' => ['en' => 'Sensor defect', 'es' => 'Sensor defectuoso', 'fr' => 'Capteur défectueux', 'it' => 'Sensore difettoso'],
        'brennerStoerung' => ['en' => 'Burner fault', 'es' => 'Avería del quemador', 'fr' => 'Panne de brûleur', 'it' => 'Guasto al bruciatore'],
        'geraeusch' => ['en' => 'Noise', 'es' => 'Ruido', 'fr' => 'Bruit', 'it' => 'Rumore'],
    ],
    'root_cause' => [
        'verschleiss' => ['en' => 'Wear', 'es' => 'Desgaste', 'fr' => 'Usure', 'it' => 'Usura'],
        'verkalkung' => ['en' => 'Limescale', 'es' => 'Calcificación', 'fr' => 'Entartrage', 'it' => 'Calcare'],
        'frost' => ['en' => 'Frost', 'es' => 'Helada', 'fr' => 'Gel', 'it' => 'Gelo'],
        'montagefehler' => ['en' => 'Assembly error', 'es' => 'Error de montaje', 'fr' => 'Erreur de montage', 'it' => 'Errore di montaggio'],
        'nutzung' => ['en' => 'Use', 'es' => 'Uso', 'fr' => 'Utilisation', 'it' => 'Utilizzo'],
        'materialfehler' => ['en' => 'Material defect', 'es' => 'Defecto de material', 'fr' => 'Défaut de matériau', 'it' => 'Difetto del materiale'],
        'fremdeinwirkung' => ['en' => 'Third-party interference', 'es' => 'Intervención de terceros', 'fr' => 'Intervention extérieure', 'it' => 'Intervento di terzi'],
    ],
    'result' => [
        'behoben' => ['en' => 'Fixed', 'es' => 'Solucionado', 'fr' => 'Corrigé', 'it' => 'Risolto'],
        'teilBehoben' => ['en' => 'Partially fixed', 'es' => 'Solucionado en parte', 'fr' => 'Partiellement corrigé', 'it' => 'Risolto in parte'],
        'dicht' => ['en' => 'Tight', 'es' => 'Estanco', 'fr' => 'Étanche', 'it' => 'A tenuta'],
        'nichtDicht' => ['en' => 'Not tight', 'es' => 'No estanco', 'fr' => 'Non étanche', 'it' => 'Non a tenuta'],
        'ersatzteilNoetig' => ['en' => 'Spare part needed', 'es' => 'Se necesita repuesto', 'fr' => 'Pièce de rechange nécessaire', 'it' => 'Serve ricambio'],
        'kundenentscheidung' => ['en' => 'Customer decision', 'es' => 'Decisión del cliente', 'fr' => 'Décision du client', 'it' => 'Decisione del cliente'],
        'eskaliert' => ['en' => 'Escalated', 'es' => 'Escalado', 'fr' => 'Escaladé', 'it' => 'Escalato'],
    ],
    'product_group' => [
        'heizung' => ['en' => 'Heating', 'es' => 'Calefacción', 'fr' => 'Chauffage', 'it' => 'Riscaldamento'],
        'therme' => ['en' => 'Boiler', 'es' => 'Caldera mural', 'fr' => 'Chaudière murale', 'it' => 'Caldaia murale'],
        'boiler' => ['en' => 'Boiler', 'es' => 'Calentador', 'fr' => 'Chauffe-eau', 'it' => 'Boiler'],
        'pumpe' => ['en' => 'Pump', 'es' => 'Bomba', 'fr' => 'Pompe', 'it' => 'Pompa'],
        'ventil' => ['en' => 'Valve', 'es' => 'Válvula', 'fr' => 'Vanne', 'it' => 'Valvola'],
        'rohrleitung' => ['en' => 'Pipe', 'es' => 'Tubería', 'fr' => 'Canalisation', 'it' => 'Tubazione'],
        'heizkoerper' => ['en' => 'Radiator', 'es' => 'Radiador', 'fr' => 'Radiateur', 'it' => 'Radiatore'],
        'sanitaerObjekt' => ['en' => 'Sanitary fixture', 'es' => 'Aparato sanitario', 'fr' => 'Appareil sanitaire', 'it' => 'Sanitario'],
        'klimaGeraet' => ['en' => 'Air conditioner', 'es' => 'Aire acondicionado', 'fr' => 'Climatiseur', 'it' => 'Condizionatore'],
        'lueftung' => ['en' => 'Ventilation', 'es' => 'Ventilación', 'fr' => 'Ventilation', 'it' => 'Ventilazione'],
    ],
    'permit_type' => [
        'schornsteinfeger' => ['en' => 'Chimney sweep acceptance', 'es' => 'Inspección del deshollinador', 'fr' => 'Réception par le ramoneur', 'it' => 'Collaudo dello spazzacamino'],
        'gasanmeldung' => ['en' => 'Gas registration (grid operator)', 'es' => 'Alta de gas (operador de red)', 'fr' => 'Déclaration gaz (gestionnaire de réseau)', 'it' => 'Registrazione gas (gestore di rete)'],
        'wasserrecht' => ['en' => 'Water rights permit', 'es' => 'Permiso de aguas', 'fr' => 'Autorisation au titre de la loi sur l\'eau', 'it' => 'Autorizzazione idrica'],
        'emissionsschutz' => ['en' => 'Emission control (BImSchV)', 'es' => 'Control de emisiones (BImSchV)', 'fr' => 'Protection contre les émissions (BImSchV)', 'it' => 'Controllo delle emissioni (BImSchV)'],
        'dichtheitsnachweis' => ['en' => 'Leak-tightness certificate', 'es' => 'Certificado de estanqueidad', 'fr' => 'Attestation d\'étanchéité', 'it' => 'Certificato di tenuta'],
    ],
];
