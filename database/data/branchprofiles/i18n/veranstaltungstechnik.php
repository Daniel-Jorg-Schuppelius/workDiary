<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : veranstaltungstechnik.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Übersetzungen zum Branchenprofil „veranstaltungstechnik" (MVP-841): je Domäne und Code die
// Labels für die aktivierbaren Sprachen; der BranchProfileInstaller schreibt
// sie nach classifications.label_i18n. Deutsch ist das Quell-Label im Profil.
return [
    'entry_type' => [
        'angebot' => ['en' => 'Quote', 'es' => 'Presupuesto', 'fr' => 'Devis', 'it' => 'Preventivo'],
        'vorbereitung' => ['en' => 'Preparation', 'es' => 'Preparación', 'fr' => 'Préparation', 'it' => 'Preparazione'],
        'anlieferung' => ['en' => 'Delivery', 'es' => 'Entrega', 'fr' => 'Livraison', 'it' => 'Consegna'],
        'aufbau' => ['en' => 'Setup', 'es' => 'Montaje', 'fr' => 'Montage', 'it' => 'Allestimento'],
        'safetyCheck' => ['en' => 'Safety check', 'es' => 'Control de seguridad', 'fr' => 'Contrôle de sécurité', 'it' => 'Controllo di sicurezza'],
        'soundcheck' => ['en' => 'Soundcheck', 'es' => 'Prueba de sonido', 'fr' => 'Balance', 'it' => 'Prova audio'],
        'showbetreuung' => ['en' => 'Show support', 'es' => 'Asistencia durante el show', 'fr' => 'Régie de spectacle', 'it' => 'Assistenza durante lo show'],
        'abbau' => ['en' => 'Teardown', 'es' => 'Desmontaje', 'fr' => 'Démontage', 'it' => 'Smontaggio'],
        'ruecknahme' => ['en' => 'Return', 'es' => 'Devolución', 'fr' => 'Retour', 'it' => 'Restituzione'],
        'schaden' => ['en' => 'Damage', 'es' => 'Daño', 'fr' => 'Dommage', 'it' => 'Danno'],
    ],
    'activity' => [
        'planen' => ['en' => 'Plan', 'es' => 'Planificar', 'fr' => 'Planifier', 'it' => 'Pianificare'],
        'laden' => ['en' => 'Load', 'es' => 'Cargar', 'fr' => 'Charger', 'it' => 'Caricare'],
        'verkabeln' => ['en' => 'Cabling', 'es' => 'Cablear', 'fr' => 'Câbler', 'it' => 'Cablare'],
        'riggen' => ['en' => 'Rigging', 'es' => 'Colgar', 'fr' => 'Accrocher', 'it' => 'Appendere'],
        'messen' => ['en' => 'Measure', 'es' => 'Medir', 'fr' => 'Mesurer', 'it' => 'Misurare'],
        'programmieren' => ['en' => 'Program', 'es' => 'Programar', 'fr' => 'Programmer', 'it' => 'Programmare'],
        'testen' => ['en' => 'Test', 'es' => 'Probar', 'fr' => 'Tester', 'it' => 'Testare'],
        'betreuen' => ['en' => 'Look after', 'es' => 'Atender', 'fr' => 'Accompagner', 'it' => 'Assistere'],
        'abbauen' => ['en' => 'Dismantle', 'es' => 'Desmontar', 'fr' => 'Démonter', 'it' => 'Smontare'],
        'inventarisieren' => ['en' => 'Take inventory', 'es' => 'Inventariar', 'fr' => 'Inventorier', 'it' => 'Inventariare'],
    ],
    'defect_type' => [
        'equipmentFehlt' => ['en' => 'Equipment missing', 'es' => 'Falta equipamiento', 'fr' => 'Équipement manquant', 'it' => 'Attrezzatura mancante'],
        'kabelDefekt' => ['en' => 'Cable defective', 'es' => 'Cable defectuoso', 'fr' => 'Câble défectueux', 'it' => 'Cavo difettoso'],
        'stromproblem' => ['en' => 'Power problem', 'es' => 'Problema eléctrico', 'fr' => 'Problème d\'alimentation', 'it' => 'Problema elettrico'],
        'riggingMangel' => ['en' => 'Rigging defect', 'es' => 'Defecto de rigging', 'fr' => 'Défaut d\'accroche', 'it' => 'Difetto di rigging'],
        'transportschaden' => ['en' => 'Transport damage', 'es' => 'Daño en el transporte', 'fr' => 'Dommage de transport', 'it' => 'Danno da trasporto'],
        'kundenAenderung' => ['en' => 'Customer change request', 'es' => 'Cambio del cliente', 'fr' => 'Modification client', 'it' => 'Modifica del cliente'],
        'zeitverzug' => ['en' => 'Time delay', 'es' => 'Retraso', 'fr' => 'Retard', 'it' => 'Ritardo'],
    ],
    'root_cause' => [
        'planung' => ['en' => 'Planning', 'es' => 'Planificación', 'fr' => 'Planification', 'it' => 'Pianificazione'],
        'material' => ['en' => 'Material', 'es' => 'Material', 'fr' => 'Matériel', 'it' => 'Materiale'],
        'fremdgewerk' => ['en' => 'Other trade', 'es' => 'Otro gremio', 'fr' => 'Autre corps de métier', 'it' => 'Altra impresa'],
        'location' => ['en' => 'Venue', 'es' => 'Lugar', 'fr' => 'Lieu', 'it' => 'Location'],
        'wetter' => ['en' => 'Weather', 'es' => 'Clima', 'fr' => 'Météo', 'it' => 'Meteo'],
        'bedienfehler' => ['en' => 'Operating error', 'es' => 'Error de manejo', 'fr' => 'Erreur de manipulation', 'it' => 'Errore di utilizzo'],
        'verschleiss' => ['en' => 'Wear', 'es' => 'Desgaste', 'fr' => 'Usure', 'it' => 'Usura'],
    ],
    'result' => [
        'erledigt' => ['en' => 'Done', 'es' => 'Hecho', 'fr' => 'Fait', 'it' => 'Fatto'],
        'teilErledigt' => ['en' => 'Partially done', 'es' => 'Hecho en parte', 'fr' => 'Partiellement fait', 'it' => 'Fatto in parte'],
        'freigegeben' => ['en' => 'Approved', 'es' => 'Aprobado', 'fr' => 'Validé', 'it' => 'Approvato'],
        'nichtFreigegeben' => ['en' => 'Not approved', 'es' => 'No aprobado', 'fr' => 'Non validé', 'it' => 'Non approvato'],
        'nacharbeit' => ['en' => 'Rework', 'es' => 'Retrabajo', 'fr' => 'Retouche', 'it' => 'Rilavorazione'],
        'ersatzEquipment' => ['en' => 'Replacement equipment', 'es' => 'Equipo de sustitución', 'fr' => 'Équipement de remplacement', 'it' => 'Attrezzatura sostitutiva'],
        'eskaliert' => ['en' => 'Escalated', 'es' => 'Escalado', 'fr' => 'Escaladé', 'it' => 'Escalato'],
    ],
    'product_group' => [
        'ton' => ['en' => 'Sound', 'es' => 'Sonido', 'fr' => 'Son', 'it' => 'Audio'],
        'licht' => ['en' => 'Light', 'es' => 'Luz', 'fr' => 'Lumière', 'it' => 'Luce'],
        'video' => ['en' => 'Video', 'es' => 'Vídeo', 'fr' => 'Vidéo', 'it' => 'Video'],
        'buehne' => ['en' => 'Stage', 'es' => 'Escenario', 'fr' => 'Scène', 'it' => 'Palco'],
        'rigging' => ['en' => 'Rigging', 'es' => 'Rigging', 'fr' => 'Accroche', 'it' => 'Rigging'],
        'strom' => ['en' => 'Power', 'es' => 'Electricidad', 'fr' => 'Électricité', 'it' => 'Corrente'],
        'truss' => ['en' => 'Truss', 'es' => 'Truss', 'fr' => 'Structure truss', 'it' => 'Truss'],
        'mikrofon' => ['en' => 'Microphone', 'es' => 'Micrófono', 'fr' => 'Microphone', 'it' => 'Microfono'],
        'lautsprecher' => ['en' => 'Loudspeaker', 'es' => 'Altavoz', 'fr' => 'Haut-parleur', 'it' => 'Altoparlante'],
        'mischpult' => ['en' => 'Mixing desk', 'es' => 'Mesa de mezclas', 'fr' => 'Table de mixage', 'it' => 'Mixer'],
    ],
    'dienstmittel_type' => [
        'stativ' => ['en' => 'Stand', 'es' => 'Trípode', 'fr' => 'Pied', 'it' => 'Treppiede'],
        'stromverteiler' => ['en' => 'Power distributor', 'es' => 'Distribuidor eléctrico', 'fr' => 'Tableau de distribution', 'it' => 'Quadro di distribuzione'],
        'kabelcase' => ['en' => 'Cable case', 'es' => 'Caja de cables', 'fr' => 'Caisse à câbles', 'it' => 'Cassa cavi'],
        'videoprojektor' => ['en' => 'Video projector', 'es' => 'Proyector', 'fr' => 'Vidéoprojecteur', 'it' => 'Videoproiettore'],
    ],
];
