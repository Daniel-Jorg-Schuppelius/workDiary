<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : dachdecker.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Übersetzungen zum Branchenprofil „dachdecker" (MVP-1062); Deutsch ist das Quell-Label im Profil.
return [
    'entry_type' => [
        'steildach' => ['en' => 'Pitched roof', 'es' => 'Cubierta inclinada', 'fr' => 'Toiture en pente', 'it' => 'Tetto a falde'],
        'flachdach' => ['en' => 'Flat roof', 'es' => 'Cubierta plana', 'fr' => 'Toiture plate', 'it' => 'Tetto piano'],
        'rinne' => ['en' => 'Gutter/sheet metal', 'es' => 'Canalón/hojalatería', 'fr' => 'Gouttière/zinguerie', 'it' => 'Grondaia/lattoneria'],
        'dachfenster' => ['en' => 'Roof window', 'es' => 'Ventana de tejado', 'fr' => 'Fenêtre de toit', 'it' => 'Finestra da tetto'],
        'wartung' => ['en' => 'Roof maintenance', 'es' => 'Mantenimiento de cubierta', 'fr' => 'Entretien de toiture', 'it' => 'Manutenzione del tetto'],
        'sturmschaden' => ['en' => 'Storm damage', 'es' => 'Daños por tormenta', 'fr' => 'Dégâts de tempête', 'it' => 'Danni da tempesta'],
        'aufmass' => ['en' => 'Measurement', 'es' => 'Medición', 'fr' => 'Métré', 'it' => 'Misurazione'],
    ],
    'activity' => [
        'eindecken' => ['en' => 'Lay roofing', 'es' => 'Cubrir', 'fr' => 'Couvrir', 'it' => 'Posare la copertura'],
        'abdichten' => ['en' => 'Seal', 'es' => 'Sellar', 'fr' => 'Étancher', 'it' => 'Sigillare'],
        'daemmen' => ['en' => 'Insulate', 'es' => 'Aislar', 'fr' => 'Isoler', 'it' => 'Isolare'],
        'reparieren' => ['en' => 'Repair', 'es' => 'Reparar', 'fr' => 'Réparer', 'it' => 'Riparare'],
        'sichern' => ['en' => 'Set up fall protection', 'es' => 'Instalar protección anticaídas', 'fr' => 'Installer la protection antichute', 'it' => 'Predisporre la protezione anticaduta'],
        'dokumentieren' => ['en' => 'Document', 'es' => 'Documentar', 'fr' => 'Documenter', 'it' => 'Documentare'],
    ],
    'defect_type' => [
        'undicht' => ['en' => 'Leak', 'es' => 'Filtración', 'fr' => 'Fuite', 'it' => 'Infiltrazione'],
        'ziegelbruch' => ['en' => 'Broken tiles', 'es' => 'Tejas rotas', 'fr' => 'Tuiles cassées', 'it' => 'Tegole rotte'],
        'rinneDefekt' => ['en' => 'Gutter faulty', 'es' => 'Canalón defectuoso', 'fr' => 'Gouttière défectueuse', 'it' => 'Grondaia difettosa'],
        'anschluss' => ['en' => 'Faulty junction', 'es' => 'Remate defectuoso', 'fr' => 'Raccord défectueux', 'it' => 'Raccordo difettoso'],
        'feuchte' => ['en' => 'Damp in the roof space', 'es' => 'Humedad en el desván', 'fr' => 'Humidité dans les combles', 'it' => 'Umidità nel sottotetto'],
    ],
    'root_cause' => [
        'sturm' => ['en' => 'Storm/severe weather', 'es' => 'Tormenta/temporal', 'fr' => 'Tempête/intempéries', 'it' => 'Tempesta/maltempo'],
        'alterung' => ['en' => 'Ageing', 'es' => 'Envejecimiento', 'fr' => 'Vieillissement', 'it' => 'Invecchiamento'],
        'ausfuehrung' => ['en' => 'Workmanship', 'es' => 'Ejecución', 'fr' => 'Exécution', 'it' => 'Esecuzione'],
        'fremdeinwirkung' => ['en' => 'External impact', 'es' => 'Acción de terceros', 'fr' => 'Intervention extérieure', 'it' => 'Azione esterna'],
    ],
    'result' => [
        'dicht' => ['en' => 'Watertight', 'es' => 'Estanco', 'fr' => 'Étanche', 'it' => 'A tenuta'],
        'provisorisch' => ['en' => 'Temporarily secured', 'es' => 'Asegurado provisionalmente', 'fr' => 'Sécurisé provisoirement', 'it' => 'Messo in sicurezza provvisoriamente'],
        'folgeauftrag' => ['en' => 'Follow-up order needed', 'es' => 'Se necesita pedido posterior', 'fr' => 'Commande complémentaire nécessaire', 'it' => 'Necessario ordine successivo'],
        'kundeInformiert' => ['en' => 'Customer informed', 'es' => 'Cliente informado', 'fr' => 'Client informé', 'it' => 'Cliente informato'],
    ],
];
