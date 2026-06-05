<?php

return [
    'page' => [
        'title' => 'Onboarding',
        'heading' => 'Lista di controllo onboarding',
        'progress_label' => 'Avanzamento',
        'progress_summary' => 'Passaggi obbligatori: :done di :total (:percent %)',
        'badge_required' => 'Obbligatorio',
        'badge_recommended' => 'Consigliato',
        'badge_done' => 'Completato',
        'badge_open' => 'Aperto',
        'badge_skipped' => 'Saltato',
    ],
    'widget' => [
        'title' => 'Configura l\'onboarding',
        'subtitle' => ':done di :total passaggi obbligatori completati',
        'open_link' => 'Apri onboarding',
        'dismiss' => 'Nascondi widget',
        'dismissed_at' => 'Widget nascosto: :date',
        'complete_headline' => 'Tutti i passaggi obbligatori completati',
        'complete_subtitle' => 'L\'organizzazione è pronta.',
        'open_steps' => '{0} Nessun passaggio aperto|{1} :count passaggio aperto|[2,*] :count passaggi aperti',
    ],
    'action' => [
        'skip' => 'Salta',
        'skip_placeholder' => 'Motivo dell\'omissione',
        'flash_skipped' => 'Il passaggio di onboarding è stato saltato.',
        'flash_dismissed' => 'Il widget di onboarding è stato nascosto.',
        'error_step_not_skippable' => 'Questo passaggio di onboarding non può essere saltato.',
    ],
    'step' => [
        'org' => [
            'profile' => [
                'title' => 'Completa i dati dell\'organizzazione',
                'description' => 'Inserisci nome, fuso orario e impostazioni di base locali dell\'organizzazione.',
                'link' => 'Apri organizzazione',
            ],
            'branch_profile' => [
                'title' => 'Scegli il profilo di settore',
                'description' => 'Seleziona un profilo di settore per avere valori predefiniti adatti alle classificazioni.',
                'link' => 'Apri profili di settore',
            ],
        ],
        'users' => [
            'invite' => [
                'title' => 'Invita i primi utenti',
                'description' => 'Invita almeno un\'altra persona attiva nella tua organizzazione.',
                'link' => 'Apri membri',
            ],
        ],
        'roles' => [
            'check' => [
                'title' => 'Verifica i ruoli',
                'description' => 'Assicurati che siano assegnati almeno un amministratore dell\'organizzazione e un operatore.',
                'link' => 'Apri gestione accessi',
            ],
        ],
        'classification' => [
            'check' => [
                'title' => 'Verifica le classificazioni',
                'description' => 'Conferma o sostituisci almeno un dominio di classificazione per l\'organizzazione.',
                'link' => 'Apri classificazioni',
            ],
        ],
        'customer' => [
            'first' => [
                'title' => 'Crea il primo cliente',
                'description' => 'Aggiungi il primo cliente manualmente o tramite importazione CSV.',
                'link' => 'Apri clienti',
            ],
        ],
        'work' => [
            'first' => [
                'title' => 'Primo progetto o commessa',
                'description' => 'Crea un primo progetto o avvia la prima registrazione nel registro.',
                'link' => 'Apri progetti',
            ],
        ],
        'time' => [
            'first' => [
                'title' => 'Prima registrazione di tempo',
                'description' => 'Registra almeno una voce di tempo per attivare il monitoraggio del tempo.',
                'link' => 'Apri monitoraggio del tempo',
            ],
        ],
        'protocol' => [
            'first_signed' => [
                'title' => 'Firma il primo protocollo',
                'description' => 'Crea un protocollo e completa la firma.',
                'link' => 'Apri registro',
            ],
        ],
        'backup' => [
            'heartbeat' => [
                'title' => 'Heartbeat di backup',
                'description' => 'Configura l\'esecuzione del backup in modo che vengano scritti regolarmente heartbeat riusciti.',
                'link' => 'Apri registro di audit',
            ],
        ],
    ],
];
