<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : holzbau-tischler.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Übersetzungen zum Branchenprofil „holzbau-tischler" (MVP-1062); Deutsch ist das Quell-Label im Profil.
return [
    'entry_type' => [
        'fenster' => ['en' => 'Windows', 'es' => 'Ventanas', 'fr' => 'Fenêtres', 'it' => 'Finestre'],
        'tueren' => ['en' => 'Doors', 'es' => 'Puertas', 'fr' => 'Portes', 'it' => 'Porte'],
        'treppe' => ['en' => 'Stairs', 'es' => 'Escalera', 'fr' => 'Escalier', 'it' => 'Scala'],
        'moebel' => ['en' => 'Furniture/interior fit-out', 'es' => 'Mobiliario/interiorismo', 'fr' => 'Mobilier/agencement', 'it' => 'Mobili/arredo interno'],
        'holzbau' => ['en' => 'Timber frame construction', 'es' => 'Construcción en entramado de madera', 'fr' => 'Ossature bois', 'it' => 'Costruzione a telaio in legno'],
        'reparatur' => ['en' => 'Repair', 'es' => 'Reparación', 'fr' => 'Réparation', 'it' => 'Riparazione'],
        'aufmass' => ['en' => 'Measurement', 'es' => 'Medición', 'fr' => 'Métré', 'it' => 'Misurazione'],
    ],
    'activity' => [
        'aufmessen' => ['en' => 'Measure up', 'es' => 'Medir', 'fr' => 'Prendre les mesures', 'it' => 'Rilevare le misure'],
        'fertigen' => ['en' => 'Fabricate in the workshop', 'es' => 'Fabricar en el taller', 'fr' => 'Fabriquer à l’atelier', 'it' => 'Fabbricare in officina'],
        'montieren' => ['en' => 'Install', 'es' => 'Montar', 'fr' => 'Monter', 'it' => 'Montare'],
        'einstellen' => ['en' => 'Adjust', 'es' => 'Ajustar', 'fr' => 'Régler', 'it' => 'Regolare'],
        'abdichten' => ['en' => 'Seal', 'es' => 'Sellar', 'fr' => 'Étancher', 'it' => 'Sigillare'],
        'reinigen' => ['en' => 'Clean construction site', 'es' => 'Limpiar la obra', 'fr' => 'Nettoyer le chantier', 'it' => 'Pulire il cantiere'],
    ],
    'defect_type' => [
        'verzug' => ['en' => 'Warping', 'es' => 'Alabeo', 'fr' => 'Déformation', 'it' => 'Deformazione'],
        'klemmt' => ['en' => 'Sticks/does not close', 'es' => 'Se atasca/no cierra', 'fr' => 'Coince/ne ferme pas', 'it' => 'Si blocca/non chiude'],
        'oberflaeche' => ['en' => 'Surface damage', 'es' => 'Daño superficial', 'fr' => 'Dégât de surface', 'it' => 'Danno superficiale'],
        'undicht' => ['en' => 'Leaking', 'es' => 'No estanco', 'fr' => 'Non étanche', 'it' => 'Non a tenuta'],
        'beschlag' => ['en' => 'Fitting faulty', 'es' => 'Herraje averiado', 'fr' => 'Ferrure défectueuse', 'it' => 'Ferramenta guasta'],
    ],
    'root_cause' => [
        'feuchte' => ['en' => 'Moisture', 'es' => 'Humedad', 'fr' => 'Humidité', 'it' => 'Umidità'],
        'montage' => ['en' => 'Installation', 'es' => 'Montaje', 'fr' => 'Pose', 'it' => 'Montaggio'],
        'material' => ['en' => 'Material', 'es' => 'Material', 'fr' => 'Matériau', 'it' => 'Materiale'],
        'nutzung' => ['en' => 'Use', 'es' => 'Uso', 'fr' => 'Utilisation', 'it' => 'Utilizzo'],
    ],
    'result' => [
        'fertig' => ['en' => 'Finished', 'es' => 'Terminado', 'fr' => 'Terminé', 'it' => 'Finito'],
        'nachstellen' => ['en' => 'Readjustment needed', 'es' => 'Requiere reajuste', 'fr' => 'Réglage nécessaire', 'it' => 'Necessaria regolazione'],
        'ersatzteil' => ['en' => 'Spare part ordered', 'es' => 'Repuesto pedido', 'fr' => 'Pièce commandée', 'it' => 'Ricambio ordinato'],
        'kundeInformiert' => ['en' => 'Customer informed', 'es' => 'Cliente informado', 'fr' => 'Client informé', 'it' => 'Cliente informato'],
    ],
];
