<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : vermieter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Übersetzungen zum Branchenprofil „vermieter"; Deutsch ist das Quell-Label im Profil.
return [
    'entry_type' => [
        'wohnungsuebergabe' => ['en' => 'Apartment handover', 'es' => 'Entrega de vivienda', 'fr' => 'Remise du logement', 'it' => 'Consegna appartamento'],
        'wohnungsabnahme' => ['en' => 'Move-out inspection', 'es' => 'Inspección de salida', 'fr' => 'État des lieux de sortie', 'it' => 'Verifica di riconsegna'],
        'gaestewechsel' => ['en' => 'Guest changeover', 'es' => 'Cambio de huéspedes', 'fr' => 'Changement de locataires', 'it' => 'Cambio ospiti'],
        'endreinigung' => ['en' => 'Final cleaning', 'es' => 'Limpieza final', 'fr' => 'Nettoyage final', 'it' => 'Pulizia finale'],
        'besichtigung' => ['en' => 'Viewing', 'es' => 'Visita', 'fr' => 'Visite', 'it' => 'Visita'],
        'schadensaufnahme' => ['en' => 'Damage report', 'es' => 'Registro de daños', 'fr' => 'Constat de dommages', 'it' => 'Rilevazione danni'],
        'zaehlerablesung' => ['en' => 'Meter reading', 'es' => 'Lectura de contadores', 'fr' => 'Relevé de compteurs', 'it' => 'Lettura contatori'],
        'wohnungsinstandhaltung' => ['en' => 'Apartment maintenance', 'es' => 'Mantenimiento de vivienda', 'fr' => 'Entretien du logement', 'it' => 'Manutenzione appartamento'],
    ],
    'activity' => [
        'uebergeben' => ['en' => 'Hand over', 'es' => 'Entregar', 'fr' => 'Remettre', 'it' => 'Consegnare'],
        'abnehmen' => ['en' => 'Inspect on move-out', 'es' => 'Inspeccionar a la salida', 'fr' => 'Faire l’état des lieux', 'it' => 'Verificare alla riconsegna'],
        'reinigen' => ['en' => 'Clean', 'es' => 'Limpiar', 'fr' => 'Nettoyer', 'it' => 'Pulire'],
        'ablesen' => ['en' => 'Read meters', 'es' => 'Leer contadores', 'fr' => 'Relever', 'it' => 'Leggere'],
        'besichtigen' => ['en' => 'Show around', 'es' => 'Enseñar la vivienda', 'fr' => 'Faire visiter', 'it' => 'Mostrare l’alloggio'],
        'reparieren' => ['en' => 'Repair', 'es' => 'Reparar', 'fr' => 'Réparer', 'it' => 'Riparare'],
        'dokumentieren' => ['en' => 'Document', 'es' => 'Documentar', 'fr' => 'Documenter', 'it' => 'Documentare'],
    ],
    'defect_type' => [
        'wasserschaden' => ['en' => 'Water damage', 'es' => 'Daños por agua', 'fr' => 'Dégât des eaux', 'it' => 'Danno da acqua'],
        'schimmel' => ['en' => 'Damp / mould', 'es' => 'Humedad / moho', 'fr' => 'Humidité / moisissure', 'it' => 'Umidità / muffa'],
        'heizung' => ['en' => 'Heating / hot water', 'es' => 'Calefacción / agua caliente', 'fr' => 'Chauffage / eau chaude', 'it' => 'Riscaldamento / acqua calda'],
        'sanitaer' => ['en' => 'Plumbing', 'es' => 'Fontanería', 'fr' => 'Sanitaire', 'it' => 'Sanitari'],
        'elektrik' => ['en' => 'Electrics', 'es' => 'Electricidad', 'fr' => 'Électricité', 'it' => 'Impianto elettrico'],
        'schloss' => ['en' => 'Lock / key', 'es' => 'Cerradura / llave', 'fr' => 'Serrure / clé', 'it' => 'Serratura / chiave'],
        'inventar' => ['en' => 'Furnishings damaged or missing', 'es' => 'Mobiliario dañado o ausente', 'fr' => 'Mobilier endommagé ou manquant', 'it' => 'Arredi danneggiati o mancanti'],
    ],
    'root_cause' => [
        'verschleiss' => ['en' => 'Wear and tear', 'es' => 'Desgaste', 'fr' => 'Usure', 'it' => 'Usura'],
        'nutzung' => ['en' => 'Improper use', 'es' => 'Uso indebido', 'fr' => 'Usage inapproprié', 'it' => 'Uso improprio'],
        'witterung' => ['en' => 'Weather', 'es' => 'Condiciones meteorológicas', 'fr' => 'Intempéries', 'it' => 'Agenti atmosferici'],
        'bausubstanz' => ['en' => 'Building fabric', 'es' => 'Estructura del edificio', 'fr' => 'Structure du bâtiment', 'it' => 'Struttura dell’edificio'],
    ],
    'result' => [
        'erledigt' => ['en' => 'Done', 'es' => 'Hecho', 'fr' => 'Terminé', 'it' => 'Fatto'],
        'handwerker' => ['en' => 'Tradesperson commissioned', 'es' => 'Técnico encargado', 'fr' => 'Artisan mandaté', 'it' => 'Artigiano incaricato'],
        'mieterInformiert' => ['en' => 'Tenant informed', 'es' => 'Inquilino informado', 'fr' => 'Locataire informé', 'it' => 'Inquilino informato'],
        'kaution' => ['en' => 'To be settled against the deposit', 'es' => 'A liquidar con la fianza', 'fr' => 'À imputer sur le dépôt de garantie', 'it' => 'Da compensare con il deposito'],
        'offen' => ['en' => 'Open', 'es' => 'Abierto', 'fr' => 'Ouvert', 'it' => 'Aperto'],
    ],
];
