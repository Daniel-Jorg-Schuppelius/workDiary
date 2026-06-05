<?php

return [
    'title' => [
        'index' => 'Protocoles',
        'show' => 'Protocole #:id',
        'create' => 'Créer un protocole',
        'edit' => 'Modifier le protocole',
    ],
    'field' => [
        'type' => 'Type',
        'title' => 'Titre',
        'description' => 'Description',
        'state_initial' => 'État avant',
        'stateInitial' => 'État avant',
        'state_final' => 'État après',
        'stateFinal' => 'État après',
        'occurred_at' => 'Date / heure',
        'occurredAt' => 'Date / heure',
        'createdBy' => 'Créé par',
        'visibility' => 'Visibilité',
        'status' => 'Statut',
        'revision' => 'Révision',
        'subject' => 'Référence',
    ],
    'action' => [
        'create' => 'Créer',
        'update' => 'Enregistrer',
        'requestReview' => 'Soumettre pour revue',
        'returnToDraft' => 'Remettre en brouillon',
        'sign' => 'Finaliser / signer',
        'archive' => 'Archiver',
        'supersede' => 'Créer une révision de correction',
        'addItem' => 'Ajouter un élément',
        'fillItem' => 'Renseigner l\'élément',
        'removeItem' => 'Supprimer l\'élément',
        'delete' => 'Supprimer',
    ],
    'flash' => [
        'created' => 'Protocole créé.',
        'updated' => 'Protocole mis à jour.',
        'deleted' => 'Protocole supprimé.',
        'transition' => [
            'requestReview' => 'Protocole soumis pour revue.',
            'returnToDraft' => 'Protocole remis en brouillon.',
            'sign' => 'Protocole signé et finalisé.',
            'archive' => 'Protocole archivé.',
            'supersede' => 'Révision de correction créée.',
        ],
        'item' => [
            'added' => 'Élément ajouté.',
            'filled' => 'Élément renseigné.',
            'removed' => 'Élément supprimé.',
        ],
        'photo' => [
            'uploaded' => 'Photo ajoutée.',
            'removed' => 'Photo supprimée.',
            'captionUpdated' => 'Légende mise à jour.',
        ],
    ],
    'validation' => [
        'required' => 'L\'élément « :label » est obligatoire.',
        'criticalDefectMissingOpenIssue' => 'Le défaut critique « :label » nécessite un point ouvert.',
        'text' => [
            'minLength' => 'Texte trop court (min. :min caractères).',
            'maxLength' => 'Texte trop long (max. :max caractères).',
        ],
        'boolean' => [
            'invalid' => 'Une valeur booléenne est requise.',
        ],
        'choice' => [
            'invalid' => 'Une sélection est requise.',
            'notInOptions' => 'La sélection ne figure pas dans la liste des options.',
        ],
        'multichoice' => [
            'invalid' => 'Au moins une sélection est requise.',
            'notInOptions' => 'La sélection ne figure pas dans la liste des options.',
        ],
        'number' => [
            'invalid' => 'Valeur numérique requise.',
            'min' => 'La valeur est inférieure au minimum (:bound).',
            'max' => 'La valeur dépasse le maximum (:bound).',
        ],
        'date' => [
            'invalid' => 'Date invalide.',
        ],
        'attachments' => [
            'required' => 'Au moins une pièce jointe est requise.',
            'min' => 'Au moins :min pièces jointes sont requises.',
            'max' => 'Au plus :max pièces jointes sont autorisées.',
        ],
        'defect' => [
            'severity' => 'La gravité doit être low/medium/high/critical.',
            'description' => 'La description du défaut est requise.',
        ],
        'measurement' => [
            'empty' => 'Au moins une mesure est requise.',
            'invalidSample' => 'Chaque mesure nécessite « at » et « value ».',
        ],
        'signature' => [
            'missing' => 'La signature n\'est pas encore apposée.',
        ],
        'photo' => [
            'missingPhase' => 'Élément photo « :label » : la phase « :phase » nécessite au moins :need photo(s) (présentes : :have).',
        ],
    ],
    'pdf' => [
        'title' => 'Protocole – :title',
        'state' => 'État',
        'items' => 'Éléments du protocole',
        'signatures' => 'Signatures',
        'col' => [
            'label' => 'Élément',
            'type' => 'Type',
            'value' => 'Valeur',
            'result' => 'Résultat',
            'note' => 'Note',
        ],
        'footer' => [
            'hash' => 'Somme de contrôle',
            'generated' => 'Généré le :at',
        ],
    ],
    'signature' => [
        'tokenIssued' => 'Le lien de signature a été créé.',
        'tokenExpired' => 'Le lien de signature a expiré ou a déjà été utilisé.',
        'tokenUnknown' => 'Lien de signature inconnu.',
        'redeemed' => 'La signature a été enregistrée.',
    ],
];
