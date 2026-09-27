<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : elektro.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Übersetzungen zum Branchenprofil „elektro" (MVP-841): je Domäne und Code die
// Labels für die aktivierbaren Sprachen; der BranchProfileInstaller schreibt
// sie nach classifications.label_i18n. Deutsch ist das Quell-Label im Profil.
return [
    'entry_type' => [
        'installation' => ['en' => 'Installation', 'es' => 'Instalación', 'fr' => 'Installation', 'it' => 'Installazione'],
        'wartung' => ['en' => 'Maintenance', 'es' => 'Mantenimiento', 'fr' => 'Maintenance', 'it' => 'Manutenzione'],
        'stoerung' => ['en' => 'Fault', 'es' => 'Avería', 'fr' => 'Panne', 'it' => 'Guasto'],
        'pruefung' => ['en' => 'Test', 'es' => 'Comprobación', 'fr' => 'Contrôle', 'it' => 'Verifica'],
        'messung' => ['en' => 'Measurement', 'es' => 'Medición', 'fr' => 'Mesure', 'it' => 'Misurazione'],
        'verteilerarbeit' => ['en' => 'Distribution board work', 'es' => 'Trabajo en cuadro eléctrico', 'fr' => 'Travaux sur tableau', 'it' => 'Lavoro sul quadro elettrico'],
        'eCheck' => ['en' => 'E-check', 'es' => 'Revisión eléctrica', 'fr' => 'Contrôle électrique', 'it' => 'Controllo elettrico'],
        'wallbox' => ['en' => 'Wallbox', 'es' => 'Wallbox', 'fr' => 'Wallbox', 'it' => 'Wallbox'],
        'pvAnschluss' => ['en' => 'PV connection', 'es' => 'Conexión fotovoltaica', 'fr' => 'Raccordement PV', 'it' => 'Collegamento fotovoltaico'],
        'abnahme' => ['en' => 'Acceptance', 'es' => 'Recepción', 'fr' => 'Réception', 'it' => 'Collaudo'],
        'nacharbeit' => ['en' => 'Rework', 'es' => 'Retrabajo', 'fr' => 'Reprise', 'it' => 'Rilavorazione'],
    ],
    'waste_code' => [
        'avv_170410_h' => ['en' => '17 04 10* — Cables containing oil, coal tar or other hazardous substances', 'es' => '17 04 10* — Cables con aceite, alquitrán u otras sustancias peligrosas', 'fr' => '17 04 10* — Câbles contenant des hydrocarbures, du goudron ou d\'autres substances dangereuses', 'it' => '17 04 10* — Cavi contenenti olio, catrame o altre sostanze pericolose'],
        'avv_170411' => ['en' => '17 04 11 — Cables (non-hazardous)', 'es' => '17 04 11 — Cables (no peligrosos)', 'fr' => '17 04 11 — Câbles (non dangereux)', 'it' => '17 04 11 — Cavi (non pericolosi)'],
        'avv_200133_h' => ['en' => '20 01 33* — Mixed batteries (including hazardous)', 'es' => '20 01 33* — Pilas mezcladas (con peligrosas)', 'fr' => '20 01 33* — Piles mélangées (dont dangereuses)', 'it' => '20 01 33* — Batterie miste (incluse pericolose)'],
        'avv_200134' => ['en' => '20 01 34 — Batteries (non-hazardous)', 'es' => '20 01 34 — Pilas (no peligrosas)', 'fr' => '20 01 34 — Piles (non dangereuses)', 'it' => '20 01 34 — Batterie (non pericolose)'],
    ],
    'activity' => [
        'freischalten' => ['en' => 'Activate', 'es' => 'Activar', 'fr' => 'Activer', 'it' => 'Attivare'],
        'messen' => ['en' => 'Measure', 'es' => 'Medir', 'fr' => 'Mesurer', 'it' => 'Misurare'],
        'anschliessen' => ['en' => 'Connect', 'es' => 'Conectar', 'fr' => 'Raccorder', 'it' => 'Collegare'],
        'verdrahten' => ['en' => 'Wire', 'es' => 'Cablear', 'fr' => 'Câbler', 'it' => 'Cablare'],
        'beschriften' => ['en' => 'Labelling', 'es' => 'Rotular', 'fr' => 'Étiqueter', 'it' => 'Etichettare'],
        'pruefen' => ['en' => 'Check', 'es' => 'Comprobar', 'fr' => 'Vérifier', 'it' => 'Verificare'],
        'dokumentieren' => ['en' => 'Document', 'es' => 'Documentar', 'fr' => 'Documenter', 'it' => 'Documentare'],
        'fehlersuche' => ['en' => 'Troubleshooting', 'es' => 'Localización de averías', 'fr' => 'Recherche de panne', 'it' => 'Ricerca guasti'],
        'inbetriebnehmen' => ['en' => 'Commission', 'es' => 'Poner en marcha', 'fr' => 'Mettre en service', 'it' => 'Mettere in funzione'],
    ],
    'defect_type' => [
        'kurzschluss' => ['en' => 'Short circuit', 'es' => 'Cortocircuito', 'fr' => 'Court-circuit', 'it' => 'Cortocircuito'],
        'erdschluss' => ['en' => 'Earth fault', 'es' => 'Falta a tierra', 'fr' => 'Défaut à la terre', 'it' => 'Guasto a terra'],
        'ueberlast' => ['en' => 'Overload', 'es' => 'Sobrecarga', 'fr' => 'Surcharge', 'it' => 'Sovraccarico'],
        'defekteSicherung' => ['en' => 'Defective fuse', 'es' => 'Fusible defectuoso', 'fr' => 'Fusible défectueux', 'it' => 'Fusibile difettoso'],
        'loseKlemme' => ['en' => 'Loose terminal', 'es' => 'Borne suelto', 'fr' => 'Borne desserrée', 'it' => 'Morsetto allentato'],
        'isolationsfehler' => ['en' => 'Insulation fault', 'es' => 'Fallo de aislamiento', 'fr' => 'Défaut d\'isolement', 'it' => 'Guasto di isolamento'],
        'falscheBeschriftung' => ['en' => 'Wrong labelling', 'es' => 'Rotulación incorrecta', 'fr' => 'Étiquetage erroné', 'it' => 'Etichettatura errata'],
        'messwertAbweichung' => ['en' => 'Measurement deviation', 'es' => 'Desviación de medición', 'fr' => 'Écart de mesure', 'it' => 'Scostamento del valore misurato'],
    ],
    'root_cause' => [
        'verschleiss' => ['en' => 'Wear', 'es' => 'Desgaste', 'fr' => 'Usure', 'it' => 'Usura'],
        'feuchtigkeit' => ['en' => 'Moisture', 'es' => 'Humedad', 'fr' => 'Humidité', 'it' => 'Umidità'],
        'installation' => ['en' => 'Installation error', 'es' => 'Error de instalación', 'fr' => 'Erreur d\'installation', 'it' => 'Errore di installazione'],
        'bedienfehler' => ['en' => 'Operating error', 'es' => 'Error de manejo', 'fr' => 'Erreur de manipulation', 'it' => 'Errore di utilizzo'],
        'fremdgewerk' => ['en' => 'Other trade', 'es' => 'Otro gremio', 'fr' => 'Autre corps de métier', 'it' => 'Altra impresa'],
        'materialfehler' => ['en' => 'Material defect', 'es' => 'Defecto de material', 'fr' => 'Défaut de matériau', 'it' => 'Difetto del materiale'],
        'planungsfehler' => ['en' => 'Planning error', 'es' => 'Error de planificación', 'fr' => 'Erreur de planification', 'it' => 'Errore di pianificazione'],
    ],
    'result' => [
        'behoben' => ['en' => 'Fixed', 'es' => 'Solucionado', 'fr' => 'Corrigé', 'it' => 'Risolto'],
        'teilBehoben' => ['en' => 'Partially fixed', 'es' => 'Solucionado en parte', 'fr' => 'Partiellement corrigé', 'it' => 'Risolto in parte'],
        'freigegeben' => ['en' => 'Approved', 'es' => 'Aprobado', 'fr' => 'Validé', 'it' => 'Approvato'],
        'nichtFreigegeben' => ['en' => 'Not approved', 'es' => 'No aprobado', 'fr' => 'Non validé', 'it' => 'Non approvato'],
        'nacharbeitNoetig' => ['en' => 'Rework needed', 'es' => 'Se necesita retrabajo', 'fr' => 'Retouche nécessaire', 'it' => 'Serve rilavorazione'],
        'materialFehlt' => ['en' => 'Material missing', 'es' => 'Falta material', 'fr' => 'Matériel manquant', 'it' => 'Materiale mancante'],
        'eskaliert' => ['en' => 'Escalated', 'es' => 'Escalado', 'fr' => 'Escaladé', 'it' => 'Escalato'],
    ],
    'product_group' => [
        'verteiler' => ['en' => 'Distribution board', 'es' => 'Cuadro de distribución', 'fr' => 'Tableau électrique', 'it' => 'Quadro di distribuzione'],
        'sicherung' => ['en' => 'Fuse', 'es' => 'Fusible', 'fr' => 'Fusible', 'it' => 'Fusibile'],
        'leitung' => ['en' => 'Line', 'es' => 'Tubería', 'fr' => 'Conduite', 'it' => 'Condotta'],
        'steckdose' => ['en' => 'Socket', 'es' => 'Enchufe', 'fr' => 'Prise', 'it' => 'Presa'],
        'beleuchtung' => ['en' => 'Lighting', 'es' => 'Iluminación', 'fr' => 'Éclairage', 'it' => 'Illuminazione'],
        'wallbox' => ['en' => 'Wall box', 'es' => 'Wallbox', 'fr' => 'Borne de recharge', 'it' => 'Wallbox'],
        'pvWechselrichter' => ['en' => 'PV inverter', 'es' => 'Inversor FV', 'fr' => 'Onduleur PV', 'it' => 'Inverter FV'],
        'zaehlerplatz' => ['en' => 'Meter panel', 'es' => 'Centralización de contadores', 'fr' => 'Emplacement du compteur', 'it' => 'Quadro contatori'],
        'netzwerk' => ['en' => 'Network', 'es' => 'Red', 'fr' => 'Réseau', 'it' => 'Rete'],
        'smartHome' => ['en' => 'Smart home', 'es' => 'Hogar inteligente', 'fr' => 'Maison connectée', 'it' => 'Smart home'],
    ],
    'permit_type' => [
        'netzanmeldung' => ['en' => 'Grid registration (DSO)', 'es' => 'Alta en la red (distribuidora)', 'fr' => 'Déclaration au réseau (GRD)', 'it' => 'Registrazione alla rete (DSO)'],
        'zaehlersetzung' => ['en' => 'Meter installation', 'es' => 'Instalación del contador', 'fr' => 'Pose de compteur', 'it' => 'Installazione del contatore'],
        'anlagenanmeldung_pv' => ['en' => 'PV system registration', 'es' => 'Registro de instalación FV', 'fr' => 'Déclaration d\'installation PV', 'it' => 'Registrazione impianto FV'],
        'e_anmeldung' => ['en' => 'Grid registration / TAB', 'es' => 'Alta en la red / TAB', 'fr' => 'Déclaration au réseau / TAB', 'it' => 'Registrazione alla rete / TAB'],
        'abnahme_vnb' => ['en' => 'Acceptance by grid operator', 'es' => 'Aceptación por el operador de red', 'fr' => 'Réception par le gestionnaire de réseau', 'it' => 'Collaudo del gestore di rete'],
    ],
];
