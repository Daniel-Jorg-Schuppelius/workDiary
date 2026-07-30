<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : integrity.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Signaux secondaires d'intégrité et verrouillage (fonctionnalité 097, MVP-447/448).
return [
    'anchor' => [
        'unavailable' => 'Ancre d\'intégrité externe illisible (cible de sauvegarde joignable ?) — signal secondaire ignoré.',
        'root_mismatch' => 'L\'ancre externe diffère : racine de l\'ancre :remote, local :local.',
        'history_mismatch' => 'L\'historique des contrôles diffère de l\'ancre externe — l\'historique local a peut-être été remplacé.',
    ],
    'env' => [
        'missing' => '.env absent ou illisible (la référence contient une empreinte).',
        'values_changed' => '.env modifié (même jeu de clés, valeurs différentes).',
        'keys_changed' => '.env modifié (jeu de clés différent : :before → :after clés).',
    ],
    'git' => [
        'head_mismatch' => 'Le HEAD Git :head ne correspond pas au build de référence :expected (AVERT.).',
        'dirty' => 'Arbre de travail Git non propre dans le périmètre : :count chemin(s) — :paths (AVERT.).',
    ],
    'lockdown' => [
        'crisis_title' => 'Verrouillage d\'intégrité : code source altéré',
        'crisis_description' => 'Une référence signée montre des écarts sur plusieurs contrôles consécutifs (:modified modifiés, :added ajoutés, :deleted supprimés). L\'installation est en mode maintenance.',
    ],
];
