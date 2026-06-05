<?php

return [
    'eligibility' => [
        'title' => 'Éligibilité flex pour :name',
        'nav_title' => 'Éligibilité flex',
        'subtitle' => 'Périodes pendant lesquelles :name participe au suivi du temps flex.',
        'current' => [
            'active' => 'Actuellement éligible au flex',
            'inactive' => 'Actuellement non éligible au flex',
        ],
        'table' => [
            'valid_from' => 'Valable à partir du',
            'valid_to' => 'Valable jusqu\'au',
            'open' => 'sans fin',
            'note' => 'Note',
            'actions' => 'Actions',
        ],
        'form' => [
            'add_title' => 'Ajouter une nouvelle période',
            'valid_from' => 'Valable à partir du',
            'valid_to' => 'Valable jusqu\'au (vide = sans fin)',
            'note' => 'Note (facultatif)',
            'submit' => 'Créer la période',
            'end_today' => 'Terminer aujourd\'hui',
            'end_submit' => 'Terminer',
        ],
        'flash' => [
            'saved' => 'Période flex enregistrée.',
            'deleted' => 'Période flex supprimée.',
        ],
        'empty' => ':name n\'a aucune période flex enregistrée — ne participe pas au temps flex.',
        'confirm_delete' => 'Vraiment supprimer cette période ? Les calculs de solde seront relancés.',
    ],
];
