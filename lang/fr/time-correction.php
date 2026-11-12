<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : time-correction.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    // Auftrag einer Zeitbuchung per Korrekturantrag ändern (MVP-1552).
    'order' => [
        'label' => 'Changer de commande',
        'unchanged' => '— inchangé —',
        'none' => '— sans commande —',
        'current' => 'actuel',
    ],
    // Eigene Stempelung korrigieren, auch offline (MVP-1595).
    'attendance' => [
        'title' => 'Corriger le pointage',
        'action' => 'Corriger le pointage',
        'submit' => 'Envoyer la correction',
        'intro' => 'La correction suit le circuit de validation sous forme de demande ; si votre organisation autorise l’autocorrection, elle s’applique immédiatement. Sans connexion, elle est enregistrée sur l’appareil et transmise au prochain contact.',
        'span' => 'Début et fin',
        'reason' => 'Motif',
        'reason_placeholder' => 'Au moins :min caractères, p. ex. « oubli du pointage de départ »',
        'changed' => 'Le pointage a été modifié entre-temps — veuillez rouvrir la boîte de dialogue.',
        'applied' => 'Pointage corrigé.',
        'submitted' => 'Correction soumise à validation.',
    ],
];
