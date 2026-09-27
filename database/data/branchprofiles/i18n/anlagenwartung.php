<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : anlagenwartung.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Übersetzungen zum Branchenprofil „anlagenwartung" (MVP-841): je Domäne und Code die
// Labels für die aktivierbaren Sprachen; der BranchProfileInstaller schreibt
// sie nach classifications.label_i18n. Deutsch ist das Quell-Label im Profil.
return [
    'entry_type' => [
        'wartung' => ['en' => 'Maintenance', 'es' => 'Mantenimiento', 'fr' => 'Maintenance', 'it' => 'Manutenzione'],
        'stoerung' => ['en' => 'Fault', 'es' => 'Avería', 'fr' => 'Panne', 'it' => 'Guasto'],
        'inspektion' => ['en' => 'Inspection', 'es' => 'Inspección', 'fr' => 'Inspection', 'it' => 'Ispezione'],
        'reparatur' => ['en' => 'Repair', 'es' => 'Reparación', 'fr' => 'Réparation', 'it' => 'Riparazione'],
        'inbetriebnahme' => ['en' => 'Commissioning', 'es' => 'Puesta en marcha', 'fr' => 'Mise en service', 'it' => 'Messa in servizio'],
        'kalibrierung' => ['en' => 'Calibration', 'es' => 'Calibración', 'fr' => 'Étalonnage', 'it' => 'Calibrazione'],
        'stillstand' => ['en' => 'Downtime', 'es' => 'Parada', 'fr' => 'Arrêt', 'it' => 'Fermo macchina'],
        'ersatzteil' => ['en' => 'Spare part', 'es' => 'Repuesto', 'fr' => 'Pièce de rechange', 'it' => 'Ricambio'],
        'abnahme' => ['en' => 'Acceptance', 'es' => 'Recepción', 'fr' => 'Réception', 'it' => 'Collaudo'],
    ],
    'activity' => [
        'pruefen' => ['en' => 'Check', 'es' => 'Comprobar', 'fr' => 'Vérifier', 'it' => 'Verificare'],
        'messen' => ['en' => 'Measure', 'es' => 'Medir', 'fr' => 'Mesurer', 'it' => 'Misurare'],
        'schmieren' => ['en' => 'Lubricate', 'es' => 'Lubricar', 'fr' => 'Lubrifier', 'it' => 'Lubrificare'],
        'tauschen' => ['en' => 'Replace', 'es' => 'Sustituir', 'fr' => 'Remplacer', 'it' => 'Sostituire'],
        'justieren' => ['en' => 'Adjust', 'es' => 'Ajustar', 'fr' => 'Ajuster', 'it' => 'Regolare'],
        'reinigen' => ['en' => 'Clean', 'es' => 'Limpiar', 'fr' => 'Nettoyer', 'it' => 'Pulire'],
        'kalibrieren' => ['en' => 'Calibrate', 'es' => 'Calibrar', 'fr' => 'Étalonner', 'it' => 'Calibrare'],
        'testen' => ['en' => 'Test', 'es' => 'Probar', 'fr' => 'Tester', 'it' => 'Testare'],
        'dokumentieren' => ['en' => 'Document', 'es' => 'Documentar', 'fr' => 'Documenter', 'it' => 'Documentare'],
    ],
    'defect_type' => [
        'lagerschaden' => ['en' => 'Storage damage', 'es' => 'Daño en almacén', 'fr' => 'Dommage en stockage', 'it' => 'Danno in magazzino'],
        'leckage' => ['en' => 'Leakage', 'es' => 'Fuga', 'fr' => 'Fuite', 'it' => 'Perdita'],
        'sensorfehler' => ['en' => 'Sensor error', 'es' => 'Error del sensor', 'fr' => 'Erreur de capteur', 'it' => 'Errore del sensore'],
        'ueberhitzung' => ['en' => 'Overheating', 'es' => 'Sobrecalentamiento', 'fr' => 'Surchauffe', 'it' => 'Surriscaldamento'],
        'verschleiss' => ['en' => 'Wear', 'es' => 'Desgaste', 'fr' => 'Usure', 'it' => 'Usura'],
        'vibrationsproblem' => ['en' => 'Vibration problem', 'es' => 'Problema de vibraciones', 'fr' => 'Problème de vibrations', 'it' => 'Problema di vibrazioni'],
        'steuerungsfehler' => ['en' => 'Control fault', 'es' => 'Fallo de control', 'fr' => 'Défaut de commande', 'it' => 'Guasto del comando'],
    ],
    'root_cause' => [
        'verschleiss' => ['en' => 'Wear', 'es' => 'Desgaste', 'fr' => 'Usure', 'it' => 'Usura'],
        'bedienfehler' => ['en' => 'Operating error', 'es' => 'Error de manejo', 'fr' => 'Erreur de manipulation', 'it' => 'Errore di utilizzo'],
        'material' => ['en' => 'Material defect', 'es' => 'Defecto de material', 'fr' => 'Défaut de matériau', 'it' => 'Difetto del materiale'],
        'wartungUeberfaellig' => ['en' => 'Maintenance overdue', 'es' => 'Mantenimiento vencido', 'fr' => 'Maintenance en retard', 'it' => 'Manutenzione scaduta'],
        'fremdteil' => ['en' => 'Third-party part', 'es' => 'Pieza de terceros', 'fr' => 'Pièce étrangère', 'it' => 'Componente di terzi'],
        'prozessabweichung' => ['en' => 'Process deviation', 'es' => 'Desviación del proceso', 'fr' => 'Écart de processus', 'it' => 'Deviazione di processo'],
    ],
    'result' => [
        'behoben' => ['en' => 'Fixed', 'es' => 'Solucionado', 'fr' => 'Corrigé', 'it' => 'Risolto'],
        'teilBehoben' => ['en' => 'Partially fixed', 'es' => 'Solucionado en parte', 'fr' => 'Partiellement corrigé', 'it' => 'Risolto in parte'],
        'produktiv' => ['en' => 'System live', 'es' => 'Instalación en producción', 'fr' => 'Installation en production', 'it' => 'Impianto in produzione'],
        'stillstand' => ['en' => 'Downtime', 'es' => 'Parada', 'fr' => 'Arrêt', 'it' => 'Fermo'],
        'ersatzteilNoetig' => ['en' => 'Spare part needed', 'es' => 'Se necesita repuesto', 'fr' => 'Pièce de rechange nécessaire', 'it' => 'Serve ricambio'],
        'beobachtung' => ['en' => 'Observation', 'es' => 'Observación', 'fr' => 'Observation', 'it' => 'Osservazione'],
        'eskaliert' => ['en' => 'Escalated', 'es' => 'Escalado', 'fr' => 'Escaladé', 'it' => 'Escalato'],
    ],
    'product_group' => [
        'maschine' => ['en' => 'Machine', 'es' => 'Máquina', 'fr' => 'Machine', 'it' => 'Macchina'],
        'anlage' => ['en' => 'System', 'es' => 'Instalación', 'fr' => 'Installation', 'it' => 'Impianto'],
        'pumpe' => ['en' => 'Pump', 'es' => 'Bomba', 'fr' => 'Pompe', 'it' => 'Pompa'],
        'motor' => ['en' => 'Engine', 'es' => 'Motor', 'fr' => 'Moteur', 'it' => 'Motore'],
        'sensor' => ['en' => 'Sensor', 'es' => 'Sensor', 'fr' => 'Capteur', 'it' => 'Sensore'],
        'steuerung' => ['en' => 'Control', 'es' => 'Control', 'fr' => 'Commande', 'it' => 'Comando'],
        'hydraulik' => ['en' => 'Hydraulics', 'es' => 'Hidráulica', 'fr' => 'Hydraulique', 'it' => 'Idraulica'],
        'pneumatik' => ['en' => 'Pneumatics', 'es' => 'Neumática', 'fr' => 'Pneumatique', 'it' => 'Pneumatica'],
        'foerdertechnik' => ['en' => 'Conveyor technology', 'es' => 'Técnica de transporte', 'fr' => 'Technique de convoyage', 'it' => 'Tecnica di movimentazione'],
        'roboter' => ['en' => 'Robot', 'es' => 'Robot', 'fr' => 'Robot', 'it' => 'Robot'],
    ],
];
