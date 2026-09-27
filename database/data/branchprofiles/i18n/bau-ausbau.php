<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : bau-ausbau.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Übersetzungen zum Branchenprofil „bau-ausbau" (MVP-841): je Domäne und Code die
// Labels für die aktivierbaren Sprachen; der BranchProfileInstaller schreibt
// sie nach classifications.label_i18n. Deutsch ist das Quell-Label im Profil.
return [
    'entry_type' => [
        'bautagesbericht' => ['en' => 'Daily site report', 'es' => 'Parte diario de obra', 'fr' => 'Rapport journalier de chantier', 'it' => 'Rapporto giornaliero di cantiere'],
        'aufmass' => ['en' => 'Measurement', 'es' => 'Medición', 'fr' => 'Métré', 'it' => 'Misurazione'],
        'montage' => ['en' => 'Assembly', 'es' => 'Montaje', 'fr' => 'Montage', 'it' => 'Montaggio'],
        'mangel' => ['en' => 'Defect', 'es' => 'Defecto', 'fr' => 'Défaut', 'it' => 'Difetto'],
        'nachtrag' => ['en' => 'Change order', 'es' => 'Adicional', 'fr' => 'Avenant', 'it' => 'Variante'],
        'teilabnahme' => ['en' => 'Partial acceptance', 'es' => 'Recepción parcial', 'fr' => 'Réception partielle', 'it' => 'Collaudo parziale'],
        'material' => ['en' => 'Material draw', 'es' => 'Retirada de material', 'fr' => 'Sortie de matériel', 'it' => 'Prelievo materiale'],
        'behinderung' => ['en' => 'Obstruction notice', 'es' => 'Aviso de impedimento', 'fr' => 'Avis d\'entrave', 'it' => 'Segnalazione di impedimento'],
        'restarbeit' => ['en' => 'Remaining work', 'es' => 'Trabajo pendiente', 'fr' => 'Travaux restants', 'it' => 'Lavori residui'],
    ],
    'activity' => [
        'messen' => ['en' => 'Measure', 'es' => 'Medir', 'fr' => 'Mesurer', 'it' => 'Misurare'],
        'montieren' => ['en' => 'Install', 'es' => 'Montar', 'fr' => 'Monter', 'it' => 'Montare'],
        'spachteln' => ['en' => 'Filling', 'es' => 'Enmasillar', 'fr' => 'Enduire', 'it' => 'Stuccare'],
        'schleifen' => ['en' => 'Sanding', 'es' => 'Lijar', 'fr' => 'Poncer', 'it' => 'Levigare'],
        'dokumentieren' => ['en' => 'Document', 'es' => 'Documentar', 'fr' => 'Documenter', 'it' => 'Documentare'],
        'pruefen' => ['en' => 'Check', 'es' => 'Comprobar', 'fr' => 'Vérifier', 'it' => 'Verificare'],
        'reinigen' => ['en' => 'Clean', 'es' => 'Limpiar', 'fr' => 'Nettoyer', 'it' => 'Pulire'],
        'koordinieren' => ['en' => 'Coordinate', 'es' => 'Coordinar', 'fr' => 'Coordonner', 'it' => 'Coordinare'],
        'nacharbeiten' => ['en' => 'Rework', 'es' => 'Retrabajar', 'fr' => 'Retoucher', 'it' => 'Rilavorare'],
    ],
    'defect_type' => [
        'massabweichung' => ['en' => 'Dimensional deviation', 'es' => 'Desviación dimensional', 'fr' => 'Écart dimensionnel', 'it' => 'Scostamento dimensionale'],
        'materialfehler' => ['en' => 'Material defect', 'es' => 'Defecto de material', 'fr' => 'Défaut de matériau', 'it' => 'Difetto del materiale'],
        'bauseitigerMangel' => ['en' => 'Defect on client\'s side', 'es' => 'Defecto imputable a la obra', 'fr' => 'Défaut imputable au maître d\'ouvrage', 'it' => 'Difetto a carico del committente'],
        'feuchtigkeit' => ['en' => 'Moisture', 'es' => 'Humedad', 'fr' => 'Humidité', 'it' => 'Umidità'],
        'beschaedigung' => ['en' => 'Damage', 'es' => 'Daño', 'fr' => 'Dommage', 'it' => 'Danneggiamento'],
        'planabweichung' => ['en' => 'Deviation from plan', 'es' => 'Desviación del plan', 'fr' => 'Écart par rapport au plan', 'it' => 'Scostamento dal piano'],
    ],
    'root_cause' => [
        'vorleistungFehlt' => ['en' => 'Preceding work missing', 'es' => 'Falta el trabajo previo', 'fr' => 'Travaux préalables manquants', 'it' => 'Lavori preliminari mancanti'],
        'wetter' => ['en' => 'Weather', 'es' => 'Clima', 'fr' => 'Météo', 'it' => 'Meteo'],
        'planung' => ['en' => 'Planning', 'es' => 'Planificación', 'fr' => 'Planification', 'it' => 'Pianificazione'],
        'material' => ['en' => 'Material', 'es' => 'Material', 'fr' => 'Matériel', 'it' => 'Materiale'],
        'fremdgewerk' => ['en' => 'Other trade', 'es' => 'Otro gremio', 'fr' => 'Autre corps de métier', 'it' => 'Altra impresa'],
        'bauherrAenderung' => ['en' => 'Client change request', 'es' => 'Cambio del promotor', 'fr' => 'Modification du maître d\'ouvrage', 'it' => 'Modifica del committente'],
        'lieferverzug' => ['en' => 'Delivery delay', 'es' => 'Retraso en la entrega', 'fr' => 'Retard de livraison', 'it' => 'Ritardo di consegna'],
    ],
    'result' => [
        'erledigt' => ['en' => 'Done', 'es' => 'Hecho', 'fr' => 'Fait', 'it' => 'Fatto'],
        'teilErledigt' => ['en' => 'Partially done', 'es' => 'Hecho en parte', 'fr' => 'Partiellement fait', 'it' => 'Fatto in parte'],
        'offen' => ['en' => 'Open', 'es' => 'Abierto', 'fr' => 'Ouvert', 'it' => 'Aperto'],
        'nachtragNoetig' => ['en' => 'Change order needed', 'es' => 'Se necesita adicional', 'fr' => 'Avenant nécessaire', 'it' => 'Serve variante'],
        'behindert' => ['en' => 'Obstructed', 'es' => 'Obstaculizado', 'fr' => 'Entravé', 'it' => 'Ostacolato'],
        'abgenommen' => ['en' => 'Accepted', 'es' => 'Aceptado', 'fr' => 'Réceptionné', 'it' => 'Collaudato'],
        'eskaliert' => ['en' => 'Escalated', 'es' => 'Escalado', 'fr' => 'Escaladé', 'it' => 'Escalato'],
    ],
    'product_group' => [
        'wand' => ['en' => 'Wall', 'es' => 'Pared', 'fr' => 'Mur', 'it' => 'Parete'],
        'decke' => ['en' => 'Ceiling', 'es' => 'Techo', 'fr' => 'Plafond', 'it' => 'Soffitto'],
        'boden' => ['en' => 'Floor', 'es' => 'Suelo', 'fr' => 'Sol', 'it' => 'Pavimento'],
        'tuer' => ['en' => 'Door', 'es' => 'Puerta', 'fr' => 'Porte', 'it' => 'Porta'],
        'fenster' => ['en' => 'Window', 'es' => 'Ventana', 'fr' => 'Fenêtre', 'it' => 'Finestra'],
        'trockenbau' => ['en' => 'Drywall construction', 'es' => 'Construcción en seco', 'fr' => 'Plâtrerie', 'it' => 'Cartongesso'],
        'daemmung' => ['en' => 'Insulation', 'es' => 'Aislamiento', 'fr' => 'Isolation', 'it' => 'Isolamento'],
        'malerarbeiten' => ['en' => 'Painting work', 'es' => 'Trabajos de pintura', 'fr' => 'Travaux de peinture', 'it' => 'Lavori di pittura'],
        'fliesen' => ['en' => 'Tiles', 'es' => 'Azulejos', 'fr' => 'Carrelage', 'it' => 'Piastrelle'],
        'fassade' => ['en' => 'Facade', 'es' => 'Fachada', 'fr' => 'Façade', 'it' => 'Facciata'],
    ],
    'trade' => [
        'rohbau' => ['en' => 'Shell construction', 'es' => 'Estructura', 'fr' => 'Gros œuvre', 'it' => 'Rustico'],
        'trockenbau' => ['en' => 'Drywall construction', 'es' => 'Construcción en seco', 'fr' => 'Plâtrerie', 'it' => 'Cartongesso'],
        'elektro' => ['en' => 'Electrical', 'es' => 'Electricidad', 'fr' => 'Électricité', 'it' => 'Elettrico'],
        'sanitaer' => ['en' => 'Plumbing / heating', 'es' => 'Fontanería / calefacción', 'fr' => 'Sanitaire / chauffage', 'it' => 'Idraulica / riscaldamento'],
        'maler' => ['en' => 'Painter / varnisher', 'es' => 'Pintor / barnizador', 'fr' => 'Peintre / laqueur', 'it' => 'Pittore / verniciatore'],
        'bodenleger' => ['en' => 'Floor layer', 'es' => 'Solador', 'fr' => 'Poseur de sols', 'it' => 'Posatore di pavimenti'],
        'fenster_tueren' => ['en' => 'Windows / doors', 'es' => 'Ventanas / puertas', 'fr' => 'Fenêtres / portes', 'it' => 'Finestre / porte'],
        'dachdecker' => ['en' => 'Roofer', 'es' => 'Tejador', 'fr' => 'Couvreur', 'it' => 'Copritetto'],
        'geruestbau' => ['en' => 'Scaffolding', 'es' => 'Montaje de andamios', 'fr' => 'Échafaudage', 'it' => 'Ponteggi'],
    ],
    'permit_type' => [
        'baugenehmigung' => ['en' => 'Building permit', 'es' => 'Licencia de obra', 'fr' => 'Permis de construire', 'it' => 'Permesso di costruire'],
        'abnahme_bauamt' => ['en' => 'Building authority acceptance', 'es' => 'Recepción por la autoridad urbanística', 'fr' => 'Réception par l\'autorité de construction', 'it' => 'Collaudo dell\'ufficio tecnico edilizio'],
        'statik_freigabe' => ['en' => 'Structural approval', 'es' => 'Aprobación estructural', 'fr' => 'Validation statique', 'it' => 'Approvazione statica'],
        'brandschutznachweis' => ['en' => 'Fire protection certificate', 'es' => 'Certificado de protección contra incendios', 'fr' => 'Attestation de protection incendie', 'it' => 'Certificato antincendio'],
        'geruestabnahme' => ['en' => 'Scaffold acceptance', 'es' => 'Recepción del andamio', 'fr' => 'Réception de l\'échafaudage', 'it' => 'Collaudo del ponteggio'],
        'entsorgungsnachweis' => ['en' => 'Disposal certificate', 'es' => 'Certificado de eliminación', 'fr' => 'Justificatif d\'élimination', 'it' => 'Certificato di smaltimento'],
        'aufgrabung' => ['en' => 'Excavation permit', 'es' => 'Permiso de excavación', 'fr' => 'Autorisation de fouille', 'it' => 'Permesso di scavo'],
    ],
];
