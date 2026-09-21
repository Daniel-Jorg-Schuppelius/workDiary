<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : onboarding.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

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
                'description' => 'Inserisca nome, fuso orario e impostazioni di base locali dell\'organizzazione.',
                'link' => 'Apri organizzazione',
            ],
            'branch_profile' => [
                'title' => 'Scegli il profilo di settore',
                'description' => 'Selezioni un profilo di settore per avere valori predefiniti adatti alle classificazioni.',
                'link' => 'Apri profili di settore',
            ],
            'scope' => [
                'title' => 'Scegliere l\'ambito funzionale',
                'description' => 'Scelga un preset di ambito funzionale o adatti i moduli attivi: ciò che non serve resta nascosto senza perdere dati.',
                'link' => 'Apri l\'ambito funzionale',
            ],
            'workspaces' => [
                'title' => 'Configura le aree di lavoro',
                'description' => 'Scelga quali aree compaiono nel selettore e quale è predefinita — chiunque può cambiare in qualsiasi momento.',
                'link' => 'Apri le aree di lavoro',
            ],
        ],
        'users' => [
            'invite' => [
                'title' => 'Invita i primi utenti',
                'description' => 'Inviti almeno un\'altra persona attiva nella sua organizzazione.',
                'link' => 'Apri membri',
            ],
        ],
        'roles' => [
            'check' => [
                'title' => 'Verifica i ruoli',
                'description' => 'Si assicuri che siano assegnati almeno un amministratore dell\'organizzazione e un operatore.',
                'link' => 'Apri gestione accessi',
            ],
        ],
        'classification' => [
            'check' => [
                'title' => 'Verifica le classificazioni',
                'description' => 'Confermi o sostituisca almeno un dominio di classificazione per l\'organizzazione.',
                'link' => 'Apri classificazioni',
            ],
        ],
        'customer' => [
            'first' => [
                'title' => 'Crea il primo cliente',
                'description' => 'Aggiunga il primo cliente manualmente o tramite importazione CSV.',
                'link' => 'Apri clienti',
            ],
        ],
        'work' => [
            'first' => [
                'title' => 'Primo progetto o commessa',
                'description' => 'Crei un primo progetto o avvii la prima registrazione nel registro.',
                'link' => 'Apri progetti',
            ],
        ],
        'time' => [
            'first' => [
                'title' => 'Prima registrazione di tempo',
                'description' => 'Registri almeno una voce di tempo per attivare il monitoraggio del tempo.',
                'link' => 'Apri monitoraggio del tempo',
            ],
        ],
        'protocol' => [
            'first_signed' => [
                'title' => 'Firma il primo protocollo',
                'description' => 'Crei un protocollo e completi la firma.',
                'link' => 'Apri registro',
            ],
        ],
        'backup' => [
            'heartbeat' => [
                'title' => 'Heartbeat di backup',
                'description' => 'Configuri l\'esecuzione del backup in modo che vengano scritti regolarmente heartbeat riusciti.',
                'link' => 'Apri registro di audit',
            ],
        ],
    ],
];
