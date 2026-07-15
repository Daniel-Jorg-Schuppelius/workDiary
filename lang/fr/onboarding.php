<?php

return [
    'page' => [
        'title' => 'Onboarding',
        'heading' => 'Liste de contrôle d\'onboarding',
        'progress_label' => 'Progression',
        'progress_summary' => 'Étapes requises : :done sur :total (:percent %)',
        'badge_required' => 'Requis',
        'badge_recommended' => 'Recommandé',
        'badge_done' => 'Terminé',
        'badge_open' => 'Ouvert',
        'badge_skipped' => 'Ignoré',
    ],
    'widget' => [
        'title' => 'Configurer l\'onboarding',
        'subtitle' => ':done sur :total étapes requises terminées',
        'open_link' => 'Ouvrir l\'onboarding',
        'dismiss' => 'Masquer le widget',
        'dismissed_at' => 'Widget masqué : :date',
        'complete_headline' => 'Toutes les étapes requises sont terminées',
        'complete_subtitle' => 'L\'organisation est prête.',
        'open_steps' => '{0} Aucune étape ouverte|{1} :count étape ouverte|[2,*] :count étapes ouvertes',
    ],
    'action' => [
        'skip' => 'Ignorer',
        'skip_placeholder' => 'Raison de l\'omission',
        'flash_skipped' => 'L\'étape d\'onboarding a été ignorée.',
        'flash_dismissed' => 'Le widget d\'onboarding a été masqué.',
        'error_step_not_skippable' => 'Cette étape d\'onboarding ne peut pas être ignorée.',
    ],
    'step' => [
        'org' => [
            'profile' => [
                'title' => 'Compléter les détails de l\'organisation',
                'description' => 'Renseignez le nom, le fuseau horaire et les paramètres de base locaux de l\'organisation.',
                'link' => 'Ouvrir l\'organisation',
            ],
            'branch_profile' => [
                'title' => 'Choisir un profil de secteur',
                'description' => 'Sélectionnez un profil de secteur pour disposer de valeurs par défaut adaptées aux classifications.',
                'link' => 'Ouvrir les profils de secteur',
            ],
            'scope' => [
                'title' => 'Choisir le périmètre fonctionnel',
                'description' => 'Choisissez un préréglage de périmètre fonctionnel ou ajustez les modules actifs — ce dont vous n\'avez pas besoin reste masqué, sans perte de données.',
                'link' => 'Ouvrir le périmètre fonctionnel',
            ],
        ],
        'users' => [
            'invite' => [
                'title' => 'Inviter les premiers utilisateurs',
                'description' => 'Invitez au moins une autre personne active dans votre organisation.',
                'link' => 'Ouvrir les membres',
            ],
        ],
        'roles' => [
            'check' => [
                'title' => 'Vérifier les rôles',
                'description' => 'Assurez-vous qu\'au moins un administrateur d\'organisation et un opérateur sont attribués.',
                'link' => 'Ouvrir la gestion des accès',
            ],
        ],
        'classification' => [
            'check' => [
                'title' => 'Vérifier les classifications',
                'description' => 'Confirmez ou remplacez au moins un domaine de classification pour l\'organisation.',
                'link' => 'Ouvrir les classifications',
            ],
        ],
        'customer' => [
            'first' => [
                'title' => 'Créer le premier client',
                'description' => 'Ajoutez le premier client manuellement ou via un import CSV.',
                'link' => 'Ouvrir les clients',
            ],
        ],
        'work' => [
            'first' => [
                'title' => 'Premier projet ou mission',
                'description' => 'Créez un premier projet ou démarrez la première saisie de journal.',
                'link' => 'Ouvrir les projets',
            ],
        ],
        'time' => [
            'first' => [
                'title' => 'Première saisie de temps',
                'description' => 'Saisissez au moins une entrée de temps pour activer le suivi du temps.',
                'link' => 'Ouvrir le suivi du temps',
            ],
        ],
        'protocol' => [
            'first_signed' => [
                'title' => 'Signer le premier protocole',
                'description' => 'Créez un protocole et finalisez la signature.',
                'link' => 'Ouvrir le journal',
            ],
        ],
        'backup' => [
            'heartbeat' => [
                'title' => 'Heartbeat de sauvegarde',
                'description' => 'Configurez l\'exécution de la sauvegarde afin que des heartbeats réussis soient écrits régulièrement.',
                'link' => 'Ouvrir le journal d\'audit',
            ],
        ],
    ],
];
