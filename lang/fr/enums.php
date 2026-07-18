<?php

return [
    'ai' => [
        'family' => ['llm' => 'Modèle de langage (LLM)', 'translation' => 'Traduction'],
        'verb' => ['formulate' => 'Formuler', 'summarize' => 'Résumer', 'classify' => 'Classifier', 'explain' => 'Expliquer', 'find' => 'Rechercher', 'translate' => 'Traduire'],
        'provider' => ['anthropic' => 'Anthropic Claude', 'openai' => 'OpenAI', 'gemini' => 'Google Gemini', 'azure_openai' => 'Azure OpenAI', 'openai_compatible' => 'Compatible OpenAI (générique)', 'ollama' => 'Ollama (local)', 'deepl' => 'DeepL', 'azure_translator' => 'Azure Translator', 'google_translate' => 'Google Cloud Translation', 'libretranslate' => 'LibreTranslate (local)', 'fake' => 'Fournisseur de test'],
        'connection_status' => ['draft' => 'Brouillon', 'active' => 'Actif', 'blocked' => 'Bloqué'],
        'memory_type' => ['glossary' => 'Glossaire', 'style_rule' => 'Règle de style', 'example' => 'Paire d\'exemples'],
        'sensitivity' => ['low' => 'Faible', 'medium' => 'Moyenne', 'high' => 'Élevée'],
    ],
    'domain' => [
        'environment' => ['ote' => 'OT&E (test/pilote)', 'production' => 'Production'],
        'connection_status' => ['draft' => 'Brouillon', 'active' => 'Actif', 'blocked' => 'Bloqué'],
        'sync_status' => ['current' => 'À jour', 'stale' => 'Obsolète', 'pending' => 'En attente', 'conflict' => 'Conflit', 'unknown' => 'Incertain'],
        'renewal_mode' => ['autorenew' => 'Renouvellement auto', 'autoexpire' => 'Expiration auto', 'autodelete' => 'Suppression auto', 'renewonce' => 'Renouveler une fois'],
        'command_status' => ['draft' => 'Brouillon', 'approved' => 'Approuvé', 'pending' => 'En attente', 'confirmed' => 'Confirmé', 'failed' => 'Échoué', 'unknown' => 'Incertain', 'conflict' => 'Conflit'],
        'capability_area' => ['authentication' => 'Authentification', 'subuser' => 'Sous-utilisateur', 'domains' => 'Domaines', 'contacts' => 'Contacts', 'nameservers' => 'Serveurs de noms', 'dns' => 'Zones DNS', 'events' => 'Événements', 'renewal' => 'Renouvellement', 'transfer' => 'Transfert', 'accounting' => 'Comptabilité', 'invoices' => 'Factures'],
    ],
    'asset' => [
        'defect-severity' => [
            'low' => 'Faible',
            'medium' => 'Moyenne',
            'high' => 'Élevée',
            'critical' => 'Critique',
        ],
        'defect-status' => [
            'open' => 'Ouvert',
            'inRepair' => 'En réparation',
            'resolved' => 'Résolu',
            'writtenOff' => 'Mis au rebut',
        ],
    ],
    'room_requirement_kind' => [
        'hygieneLevel' => 'Niveau d\'hygiène',
        'specialCleaning' => 'Nettoyage spécial',
        'accessRestriction' => 'Restriction d\'accès',
        'itInventory' => 'Inventaire informatique',
        'technicalInspection' => 'Inspection technique',
        'operatorDuty' => 'Obligation d\'exploitant',
        'other' => 'Autre',
    ],
    'event' => [
        'type' => [
            'training' => 'Formation',
            'workshop' => 'Atelier',
            'conference' => 'Conférence',
            'meeting' => 'Réunion',
            'internal_briefing' => 'Briefing interne',
            'external_visit' => 'Visite externe',
        ],
        'status' => [
            'planned' => 'Planifié',
            'confirmed' => 'Confirmé',
            'in_progress' => 'En cours',
            'completed' => 'Terminé',
            'cancelled' => 'Annulé',
        ],
        'visibility' => [
            'internal' => 'Interne',
            'external' => 'Externe',
            'public' => 'Public',
        ],
        'participant' => [
            'role' => [
                'organizer' => 'Organisateur',
                'trainer' => 'Formateur',
                'attendee' => 'Participant',
                'optional' => 'Facultatif',
            ],
            'status' => [
                'invited' => 'Invité',
                'accepted' => 'Accepté',
                'declined' => 'Refusé',
                'attended' => 'Présent',
                'no_show' => 'Absent',
            ],
        ],
        'reminder' => [
            'channel' => [
                'mail' => 'E-mail',
                'webpush' => 'Push',
                'database' => 'Dans l\'app',
            ],
        ],
    ],
    'vehicle' => [
        'type' => [
            'car' => 'Voiture',
            'van' => 'Camionnette',
            'truck' => 'Camion',
            'bicycle' => 'Vélo',
            'other' => 'Autre',
        ],
        'propulsion' => [
            'diesel' => 'Diesel',
            'petrol' => 'Essence',
            'gas' => 'Gaz',
            'hybrid' => 'Hybride',
            'electric' => 'Électrique',
            'muscle' => 'Force musculaire',
            'other' => 'Autre',
        ],
        'ownership' => [
            'owned' => 'En propriété',
            'leased' => 'Leasing',
            'rental' => 'Location',
        ],
    ],
    'diary' => [
        'dispatch_status' => [
            'unplanned' => 'Non planifié',
            'planned' => 'Planifié',
            'confirmed' => 'Confirmé',
            'enRoute' => 'En route',
            'done' => 'Terminé',
        ],
    ],
    'sickness' => [
        'kind' => [
            'initial' => 'Certificat initial',
            'follow_up' => 'Certificat de prolongation',
        ],
    ],
    'tour' => [
        'status' => [
            'draft' => 'Brouillon',
            'planned' => 'Planifié',
            'in_progress' => 'En cours',
            'completed' => 'Terminé',
            'cancelled' => 'Annulé',
        ],
    ],
    'activity' => [
        'category_type' => [
            'admin' => 'Administration',
            'training' => 'Formation',
            'meeting' => 'Réunion',
            'internal' => 'Interne',
            'travel' => 'Déplacement',
            'break' => 'Pause',
            'absence' => 'Absence',
            'standby' => 'Astreinte',
            'other' => 'Autre',
        ],
    ],
    'vacation' => [
        'type' => [
            'vacation' => 'Congés',
            'sick' => 'Maladie',
            'special' => 'Congé spécial',
            'unpaid' => 'Non payé',
        ],
        'status' => [
            'pending' => 'En attente',
            'approved' => 'Approuvé',
            'rejected' => 'Refusé',
            'cancelled' => 'Annulé',
        ],
    ],
    'cloud_intake' => [
        'provider' => [
            'dropbox' => 'Dropbox',
            'microsoft' => 'Microsoft OneDrive/SharePoint',
            'google' => 'Google Drive',
            'nextcloud' => 'Nextcloud',
        ],
        'connection_status' => [
            'draft' => 'Brouillon',
            'active' => 'Active',
            'reauth_required' => 'Reconnexion requise',
            'blocked' => 'Bloquée',
            'disabled' => 'Désactivée',
        ],
        'route_target' => [
            'incoming_invoice' => 'Factures entrantes',
            'document' => 'Document (GED)',
        ],
        'item_status' => [
            'imported' => 'Importé',
            'inbox' => 'Boîte de réception',
            'rejected' => 'Rejeté',
            'duplicate' => 'Doublon',
            'source_gone' => 'Source supprimée',
        ],
    ],
    'product' => [
        'status' => [
            'active' => 'Actif',
            'phasing_out' => 'En fin de vie',
            'discontinued' => 'Abandonné',
        ],
    ],
    'project' => [
        'status' => [
            'active' => 'Actif',
            'paused' => 'En pause',
            'archived' => 'Archivé',
        ],
    ],
    'task' => [
        'status' => [
            'open' => 'Ouvert',
            'in_progress' => 'En cours',
            'done' => 'Terminé',
        ],
        'priority' => [
            'low' => 'Basse',
            'medium' => 'Moyenne',
            'high' => 'Haute',
            'urgent' => 'Urgente',
        ],
    ],
    'timesheet' => [
        'status' => [
            'draft' => 'Brouillon',
            'submitted' => 'Soumis',
            'signed' => 'Signé',
            'locked' => 'Verrouillé',
        ],
        'kind' => [
            'project' => 'Projet',
            'personal_day' => 'Journée personnelle',
        ],
    ],
    'time_entry' => [
        'kind' => [
            'work' => 'Travail',
            'travel' => 'Déplacement',
            'standby' => 'Astreinte',
        ],
    ],
    'expense' => [
        'status' => [
            'draft' => 'Brouillon',
            'pending' => 'Soumis',
            'approved' => 'Approuvé',
            'rejected' => 'Refusé',
            'cancelled' => 'Annulé',
            'reimbursed' => 'Remboursé',
            'invoiced' => 'Facturé',
        ],
        'payment_method' => [
            'private_paid' => 'Payé à titre privé',
            'company_card' => 'Carte d\'entreprise',
            'cash' => 'Caisse',
            'bank_transfer' => 'Virement bancaire',
        ],
    ],
    'per_diem' => [
        'day_kind' => [
            'departure_day' => 'Jour de départ',
            'full_day' => 'Journée complète de voyage',
            'return_day' => 'Jour de retour',
            'single_day' => 'Voyage d\'une journée',
        ],
        'trip_status' => [
            'draft' => 'Brouillon',
            'converted' => 'Converti en frais',
            'cancelled' => 'Annulé',
        ],
    ],
    'notification' => [
        'event' => [
            'crisis' => [
                'alert' => 'Alerte de crise',
            ],
            'claim' => [
                'escalation' => 'Réclamation en retard',
            ],
            'rental' => [
                'returnOverdue' => 'Retour de location en retard',
            ],
            'assetFinance' => [
                'deadline' => 'Échéance de leasing',
            ],
            'contract' => [
                'deadlineDue' => 'Échéance contractuelle',
            ],
            'invoice' => [
                'recurringDraft' => 'Brouillon de facture depuis un plan de facturation',
            ],
            'fleet' => [
                'licenseCheckDue' => 'Contrôle du permis de conduire à échéance',
            ],
            'assetCompliance' => [
                'inspectionDue' => 'Contrôle à échéance/en retard',
            ],
            'ticket' => [
                'assigned' => 'Ticket attribué',
                'customerReplied' => 'Le client a répondu',
                'waitingExpired' => 'Relance de ticket échue',
            ],
            'problem' => [
                'effectivenessDue' => 'Contrôle d\'efficacité d\'un problème à échéance',
            ],
            'openIssue' => [
                'assigned' => 'Point ouvert assigné',
                'dueSoon' => 'Point ouvert bientôt dû',
                'overdue' => 'Point ouvert en retard',
            ],
            'communication' => [
                'followupDueSoon' => 'Action de suivi bientôt due',
                'followupOverdue' => 'Action de suivi en retard',
            ],
            'document' => [
                'expiringSoon' => 'Document expirant bientôt',
                'expired' => 'Document expiré',
            ],
            'timeCorrection' => [
                'requested' => 'Demande de correction de temps soumise',
                'decided' => 'Demande de correction de temps décidée',
            ],
            'monthClosure' => [
                'submitted' => 'Clôture mensuelle soumise',
            ],
            'isms' => [
                'certificateExpiring' => 'Certificat ISMS arrive à expiration',
                'correctiveActionOverdue' => 'Action corrective SMSI en retard',
                'riskReviewDue' => 'Revue de risque SMSI à échéance',
                'vulnerabilityOverdue' => 'Vulnérabilité ISMS en retard',
                'incidentCritical' => 'Incident de sécurité ISMS critique',
                'supplierReviewOverdue' => 'Examen fournisseur ISMS en retard',
            ],
            'sla' => [
                'atRisk' => 'Échéance SLA menacée',
                'breached' => 'Échéance SLA dépassée',
                'quotaWarning' => 'Quota SLA bientôt épuisé',
            ],
            'asset' => [
                'returnOverdue' => 'Retour de ressource en retard',
            ],
            'safety' => [
                'criticalEvent' => 'Événement de sécurité critique',
            ],
            'qualification' => [
                'expiring' => 'Qualification bientôt expirée',
            ],
            'shiftExchange' => [
                'requested' => 'Échange de poste demandé',
                'decided' => 'Échange de poste décidé',
            ],
            'customer' => [
                'queryRaised' => 'Le client a posé une question',
            ],
            'ideaMap' => [
                'shared' => 'Carte d\'idées partagée avec vous',
            ],
            'shipment' => [
                'deliveryProblem' => 'Problème de livraison d\'une expédition',
            ],
            'cti' => [
                'incomingCall' => 'Appel entrant',
            ],
            'maintenance' => [
                'dueSoon' => 'Maintenance/contrôle bientôt dû',
                'overdue' => 'Maintenance/contrôle en retard',
            ],
            'operations' => [
                'backupOverdue' => 'Sauvegarde en retard',
                'backupFailed' => 'Échec de la sauvegarde',
                'restoreTestOverdue' => 'Test de restauration en retard',
                'updateAvailable' => 'Mise à jour disponible',
                'updateSecurity' => 'Mise à jour de sécurité disponible',
                'licenseExpiring' => 'Licence bientôt expirée',
                'credentialExpiring' => 'Identifiant/jeton bientôt expiré',
                'connectionFailing' => 'Connexion défaillante',
                'componentEol' => 'Composant en fin de vie (EOL)',
                'pluginDisabled' => 'Plugin désactivé automatiquement',
                'schedulerOverdue' => 'Tâche planifiée en retard',
                'maintenanceScheduled' => 'Fenêtre de maintenance annoncée',
                'problemReportReceived' => 'Nouveau signalement de problème reçu',
            ],
        ],
        'channel' => [
            'inApp' => 'Dans l’application',
            'mail' => 'E-mail',
            'push' => 'Push',
            'teams' => 'Microsoft Teams',
            'mattermost' => 'Mattermost',
            'calendar' => 'Calendrier',
        ],
    ],

    'customer-query' => [
        'status' => [
            'open' => 'Ouverte',
            'answered' => 'Répondu',
            'closed' => 'Fermée',
        ],
    ],

    'shift' => [
        'availability_kind' => [
            'available' => 'Disponible',
            'unavailable' => 'Indisponible',
            'preferred' => 'Préféré',
        ],
        'preference' => [
            'want' => 'Souhait',
            'avoid' => 'Aversion',
        ],
        'exchange_status' => [
            'requested' => 'Demandé',
            'accepted' => 'Accepté',
            'approved' => 'Approuvé',
            'rejected' => 'Refusé',
            'cancelled' => 'Retiré',
        ],
    ],

    'sla' => [
        'status' => [
            'none' => 'Aucun SLA',
            'met' => 'SLA respecté',
            'onTrack' => 'SLA dans les temps',
            'atRisk' => 'SLA menacé',
            'breached' => 'SLA dépassé',
        ],
        'violationKind' => [
            'responseTime' => 'Temps de réaction',
            'resolutionTime' => 'Temps de résolution',
        ],
    ],

    'safety' => [
        'kind' => [
            'accident' => 'Accident',
            'nearMiss' => 'Presqu’accident',
            'hazard' => 'Danger',
            'defect' => 'Défaut',
        ],
        'severity' => [
            'low' => 'Faible',
            'medium' => 'Moyen',
            'high' => 'Élevé',
            'critical' => 'Critique',
        ],
        'status' => [
            'reported' => 'Signalé',
            'investigating' => 'En investigation',
            'measuresDefined' => 'Mesures définies',
            'closed' => 'Clôturé',
        ],
    ],

    'open-issue' => [
        'status' => [
            'open' => 'Ouvert',
            'inProgress' => 'En cours',
            'blocked' => 'Bloqué',
            'done' => 'Terminé',
            'wontDo' => 'Ne sera pas fait',
            'reopened' => 'Rouvert',
        ],
        'severity' => [
            'low' => 'Basse',
            'medium' => 'Moyenne',
            'high' => 'Haute',
            'critical' => 'Critique',
        ],
        'source' => [
            'manual' => 'Manuel',
            'protocolDefect' => 'Depuis le protocole',
            'communicationFollowup' => 'Depuis la communication',
            'procedureDeviation' => 'Depuis un écart de procédure',
            'customerRejection' => 'Refus du client',
        ],
        'visibility' => [
            'internal' => 'Interne',
            'customer' => 'Visible par le client',
        ],
    ],
    'communication' => [
        'type' => [
            'call' => 'Appel téléphonique',
            'email' => 'E-mail',
            'meeting' => 'Entretien sur site',
            'videocall' => 'Visioconférence',
            'chat' => 'Chat / messagerie',
            'internal' => 'Concertation interne',
            'decision' => 'Décision',
            'letter' => 'Courrier / fax',
            'other' => 'Autre',
        ],
        'direction' => [
            'inbound' => 'Entrant',
            'outbound' => 'Sortant',
            'internal' => 'Interne',
        ],
        'visibility' => [
            'internal' => 'Interne',
            'customer' => 'Visible client',
        ],
        'party' => [
            'internal' => 'Interne',
            'customer' => 'Client',
            'thirdParty' => 'Tiers',
        ],
    ],
    'knowledge' => [
        'status' => [
            'draft' => 'Brouillon',
            'published' => 'Publié',
            'archived' => 'Archivé',
        ],
        'visibility' => [
            'internal' => 'Interne (toute l’organisation)',
            'team' => 'Limité à l’équipe',
        ],
    ],
    'form' => [
        'template_status' => [
            'draft' => 'Brouillon',
            'active' => 'Actif',
            'archived' => 'Archivé',
        ],
        'field_type' => [
            'text' => 'Texte',
            'textarea' => 'Texte multiligne',
            'number' => 'Nombre',
            'date' => 'Date',
            'select' => 'Sélection',
            'checkbox' => 'Case à cocher',
            'photo' => 'Photo',
            'file' => 'Fichier',
            'signature' => 'Signature',
        ],
    ],
    'document' => [
        'type' => [
            'contract' => 'Contrat',
            'testReport' => 'Rapport de contrôle',
            'certificate' => 'Certificat',
            'manual' => 'Manuel',
            'datasheet' => 'Fiche technique',
            'manufacturerDoc' => 'Document fabricant',
            'permit' => 'Autorisation',
            'insurance' => 'Assurance',
            'invoice' => 'Facture',
            'other' => 'Autre',
        ],
        'status' => [
            'draft' => 'Brouillon',
            'active' => 'Actif',
            'expired' => 'Expiré',
            'archived' => 'Archivé',
        ],
    ],
    'protocol' => [
        'status' => [
            'draft' => 'Brouillon',
            'in_review' => 'En revue',
            'signed' => 'Signé',
            'archived' => 'Archivé',
            'superseded' => 'Remplacé',
        ],
        'type' => [
            'acceptance' => 'Réception',
            'service' => 'Intervention de service',
            'maintenance' => 'Maintenance',
            'handover' => 'Remise',
            'defect' => 'Rapport de défaut',
            'inspection' => 'Inspection',
            'siteVisit' => 'Visite sur site',
            'other' => 'Autre',
        ],
        'visibility' => [
            'internal' => 'Interne',
            'customer' => 'Visible par le client',
        ],
        'item-result' => [
            'ok' => 'OK',
            'notok' => 'Pas OK',
            'n_a' => 'Non applicable',
            'open' => 'Ouvert',
        ],
        'signature-role' => [
            'customer' => 'Client',
            'contractor' => 'Prestataire',
            'witness' => 'Témoin',
        ],
        'signature-method' => [
            'onscreen' => 'Signature à l\'écran',
            'portal' => 'Portail client',
            'emailLink' => 'Lien e-mail',
            'paper' => 'Papier',
        ],
        'item-type' => [
            'group' => 'Section',
            'text' => 'Texte libre',
            'boolean' => 'Élément oui/non',
            'choice' => 'Choix unique',
            'multichoice' => 'Choix multiple',
            'number' => 'Mesure / nombre',
            'range' => 'Plage cible',
            'date' => 'Date',
            'datetime' => 'Date et heure',
            'signature' => 'Signature',
            'photo' => 'Photo obligatoire',
            'file' => 'Document obligatoire',
            'defect' => 'Défaut',
            'measurement.timestamped' => 'Série de mesures',
            'procedure_step' => 'Étape de procédure',
            'signoff_internal' => 'Approbation interne',
        ],
        'item-photo-phase' => [
            'before' => 'Avant',
            'after' => 'Après',
            'detail' => 'Détail',
            'defect' => 'Défaut',
            'reference' => 'Référence',
        ],
    ],
    'procedure' => [
        'risk-level' => [
            'low' => 'Bas',
            'normal' => 'Normal',
            'high' => 'Élevé',
            'critical' => 'Critique',
        ],
        'step-type' => [
            'confirm' => 'Confirmation',
            'text' => 'Texte',
            'number' => 'Nombre/mesure',
            'choice' => 'Choix',
            'photo' => 'Photo',
            'file' => 'Fichier',
            'backup' => 'Enregistrement de sauvegarde',
            'signature' => 'Signature',
            'material' => 'Saisie de matériel',
            'dienstmittel' => 'Équipement de service',
            'freigabe' => 'Approbation (double contrôle)',
            'messreihe' => 'Série de mesures',
            'link_protocol' => 'Lier le protocole',
            'link_test' => 'Lier le test',
            'wait' => 'Temps d’attente',
        ],
        'proof-type' => [
            'backup' => 'Sauvegarde',
            'file' => 'Fichier',
            'photo' => 'Photo',
            'measure' => 'Mesure',
            'signature' => 'Signature',
        ],
        'run-status' => [
            'open' => 'Ouvert',
            'inProgress' => 'En cours',
            'blocked' => 'Bloqué',
            'completed' => 'Terminé',
            'aborted' => 'Interrompu',
        ],
        'step-run-status' => [
            'pending' => 'En attente',
            'done' => 'Terminé',
            'n_a' => 'Non applicable',
            'failed' => 'Échoué',
            'deviated' => 'Écart',
            'blocked' => 'Bloqué',
        ],
        'backup-scope' => [
            'config' => 'Configuration',
            'database' => 'Base de données',
            'fullSystem' => 'Système complet',
            'customScript' => 'Script personnalisé',
        ],
        'backup-storage-target' => [
            'attachment' => 'Pièce jointe',
            'external' => 'Stockage externe',
        ],
        'backup-verify-method' => [
            'checksum' => 'Comparaison de somme de contrôle',
            'restoreCheck' => 'Test de restauration',
            'managerConfirmation' => 'Confirmation de la direction',
        ],
        'deviation-type' => [
            'not_applicable' => 'Non applicable',
            'not_possible' => 'Impossible',
            'partial' => 'Partiellement rempli',
            'alternative_method' => 'Méthode alternative',
            'failed_check' => 'Lecture hors tolérance',
            'material_substitute' => 'Matériel de substitution',
            'safety_block' => 'Interruption de sécurité',
            'customer_decline' => 'Refus du client',
        ],
        'deviation-severity' => [
            'low' => 'Basse',
            'medium' => 'Moyenne',
            'high' => 'Haute',
            'critical' => 'Critique',
        ],
        'deviation-proposed-action' => [
            'none' => 'Aucune action de suivi',
            'open_issue' => 'Point ouvert',
            'new_diary_entry' => 'Nouvelle mission',
            'requalify' => 'Réexécuter',
            'escalate' => 'Escalade',
        ],
    ],
    'duty_plan' => [
        'status' => [
            'draft' => 'Brouillon',
            'published' => 'Publié',
        ],
    ],
    'export' => [
        'entity' => [
            'customers' => 'Clients',
            'projects' => 'Projets',
            'users' => 'Utilisateurs',
            'materials' => 'Matériaux',
            'scheduled_shifts' => 'Services planifiés',
            'tours' => 'Tournées',
        ],
        'format' => [
            'csv' => 'CSV',
            'xlsx' => 'XLSX',
        ],
        'state' => [
            'preparing' => 'En préparation',
            'ready' => 'Prêt',
            'failed' => 'Échoué',
        ],
    ],
    'compliance' => [
        'finding-status' => [
            'open' => 'Ouvert',
            'acknowledged' => 'Acquitté',
            'resolved' => 'Résolu',
            'accepted' => 'Accepté',
        ],
    ],
    'isms' => [
        'security-incident-category' => [
            'malware' => 'Logiciel malveillant',
            'phishing' => 'Hameçonnage',
            'dataLoss' => 'Perte de données',
            'unauthorizedAccess' => 'Accès non autorisé',
            'serviceOutage' => 'Interruption de service',
            'misconfiguration' => 'Erreur de configuration',
            'physical' => 'Incident physique',
            'other' => 'Autre',
        ],
        'security-incident-status' => [
            'reported' => 'Signalé',
            'triage' => 'Triage',
            'contained' => 'Contenu',
            'eradicated' => 'Éradiqué',
            'recovered' => 'Rétabli',
            'closed' => 'Clôturé',
        ],
        'incident-severity' => [
            'low' => 'Faible',
            'medium' => 'Moyenne',
            'high' => 'Élevée',
            'critical' => 'Critique',
        ],
        'vulnerability-status' => [
            'open' => 'Ouverte',
            'underReview' => 'En cours d\'examen',
            'mitigating' => 'En cours d\'atténuation',
            'resolved' => 'Résolue',
            'accepted' => 'Acceptée',
            'notAffected' => 'Non affecté',
        ],
        'exploitability' => [
            'unknown' => 'Inconnue',
            'underInvestigation' => 'En cours d\'investigation',
            'exploitable' => 'Exploitable',
            'notExploitable' => 'Non exploitable',
        ],
        'vulnerability-source' => [
            'manual' => 'Manuelle',
            'advisoryImport' => 'Import d\'avis',
        ],
        'supplier-assessment-status' => [
            'draft' => 'Brouillon',
            'assessed' => 'Évalué',
            'approved' => 'Approuvé',
            'flagged' => 'Signalé',
        ],
        'advisory-format' => [
            'csaf' => 'CSAF',
            'vex' => 'VEX',
        ],
        'audit-package-status' => [
            'draft' => 'Brouillon',
            'finalized' => 'Finalisé',
        ],
        'audit-kind' => [
            'internal' => 'Interne',
            'external' => 'Externe',
            'supplier' => 'Fournisseur',
        ],
        'audit-status' => [
            'planned' => 'Planifié',
            'inPreparation' => 'En préparation',
            'inProgress' => 'En cours',
            'reportIssued' => 'Rapport émis',
            'closed' => 'Clôturé',
        ],
        'finding-kind' => [
            'nonconformityMajor' => 'Non-conformité majeure',
            'nonconformityMinor' => 'Non-conformité mineure',
            'observation' => 'Observation',
            'improvement' => 'Piste d’amélioration',
        ],
        'finding-status' => [
            'open' => 'Ouvert',
            'inCorrection' => 'En correction',
            'effectivenessCheck' => 'Contrôle d’efficacité',
            'closed' => 'Clôturé',
        ],
        'corrective-action-status' => [
            'open' => 'Ouverte',
            'inProgress' => 'En cours',
            'done' => 'Mise en œuvre',
            'effective' => 'Efficace',
            'ineffective' => 'Inefficace',
        ],
        'review-status' => [
            'draft' => 'Brouillon',
            'approved' => 'Approuvée',
        ],
        'assessment-kind' => [
            'gross' => 'Brut',
            'net' => 'Net',
            'target' => 'Cible',
        ],
        'assessment-status' => [
            'draft' => 'Brouillon',
            'approved' => 'Approuvée',
        ],
        'risk-category' => [
            'organizational' => 'Organisationnel',
            'technical' => 'Technique',
            'physical' => 'Physique',
            'personnel' => 'Personnel',
            'supplier' => 'Fournisseur',
        ],
        'risk-treatment' => [
            'avoid' => 'Éviter',
            'mitigate' => 'Réduire',
            'transfer' => 'Transférer',
            'accept' => 'Accepter',
        ],
        'risk-status' => [
            'identified' => 'Identifié',
            'analyzed' => 'Analysé',
            'treated' => 'Traité',
            'accepted' => 'Accepté',
            'closed' => 'Clôturé',
        ],
        'requirement-source' => [
            'catalog' => 'Catalogue de référence',
            'custom' => 'Exigence propre',
        ],
        'control-implementation-status' => [
            'open' => 'Ouvert',
            'partial' => 'Partiellement mis en œuvre',
            'implemented' => 'Mis en œuvre',
            'notApplicable' => 'Non applicable',
        ],
        'software-category' => [
            'os' => 'Système d\'exploitation',
            'application' => 'Application',
            'service' => 'Service',
            'library' => 'Bibliothèque',
            'other' => 'Autre',
        ],
        'support-status' => [
            'supported' => 'Pris en charge',
            'extendedSupport' => 'Support étendu',
            'endOfLife' => 'Fin de vie',
            'unknown' => 'Inconnu',
        ],
        'norm-conformity-status' => [
            'notAssessed' => 'Non évalué',
            'gapAnalysisDone' => 'Analyse des écarts effectuée',
            'inProgress' => 'En cours de mise en œuvre',
            'internallyAuditReady' => 'Prêt pour l’audit interne',
            'externalAuditPlanned' => 'Audit externe planifié',
            'certified' => 'Certifié',
            'certificateSuspended' => 'Certificat suspendu',
            'certificateExpired' => 'Certificat expiré',
        ],
    ],
    'surcharge' => [
        'kind' => [
            'night' => 'Nuit',
            'saturday' => 'Samedi',
            'sunday' => 'Dimanche',
            'holiday' => 'Jour férié',
            'custom' => 'Personnalisé',
        ],
    ],
    'finance' => [
        'billing-mode' => [
            'workdiary' => 'WorkDiary (local)',
            'lexoffice' => 'Lexoffice pilote',
            'datev' => 'DATEV pilote',
            'orgamax' => 'orgaMAX pilote',
            'sevdesk' => 'sevDesk pilote',
            'easybill' => 'easybill pilote',
        ],
        'transfer-channel' => [
            'time' => 'Prestations/temps',
            'material' => 'Produits/matériel',
        ],
        'transfer-target' => [
            'lexoffice' => 'Lexoffice',
            'datev' => 'DATEV',
            'orgamax' => 'orgaMAX (commande)',
            'sevdesk' => 'sevDesk (brouillon de facture)',
            'easybill' => 'easybill (brouillon de facture)',
            'file' => 'Export de fichier',
        ],
        'transfer-status' => [
            'draft' => 'Brouillon',
            'confirmed' => 'Confirmé',
            'transferred' => 'Transféré',
            'failed' => 'Échoué',
            'voided' => 'Annulé',
        ],
        'chart-of-accounts' => [
            'skr03' => 'SKR03',
            'skr04' => 'SKR04',
        ],
        'datev-batch-status' => [
            'draft' => 'Brouillon',
            'exported' => 'Exporté',
        ],
        // Rapprochement des paiements (Feature 045, priorité 3).
        'bank-statement-format' => [
            'camt053' => 'CAMT.053',
            'mt940' => 'MT940',
            'ofx' => 'OFX',
            'qif' => 'QIF',
            'qxf' => 'QXF',
            'pain001' => 'PAIN.001',
            'pain008' => 'PAIN.008',
        ],
        'transaction-direction' => [
            'credit' => 'Entrée d’argent',
            'debit' => 'Sortie d’argent',
        ],
        'balance-check' => [
            'ok' => 'Chaîne de soldes cohérente',
            'mismatch' => 'Écart de solde',
            'unknown' => 'Soldes incomplets',
        ],
        'match-status' => [
            'unmatched' => 'Ouvert',
            'suggested' => 'Suggestions',
            'matched' => 'Affecté',
            'ignored' => 'Mis de côté',
            'unassignable' => 'Non affectable',
            'duplicate' => 'Doublon',
        ],
        'allocation-kind' => [
            'payment' => 'Paiement',
            'partial' => 'Paiement partiel',
            'overpayment' => 'Trop-perçu',
            'reimbursement' => 'Remboursement',
            'chargeback' => 'Rejet de prélèvement',
        ],
    ],

    // Clôture journalière (MVP-015, docs/tagesabschluss.md §3/§5).
    'dayClosure' => [
        'status' => [
            'open' => 'Ouvert',
            'closed' => 'Clôturé',
            'correction' => 'En correction',
            'locked' => 'Verrouillé',
        ],
    ],
    'dayCorrection' => [
        'status' => [
            'pending' => 'En attente',
            'approved' => 'Approuvée',
            'rejected' => 'Rejetée',
        ],
    ],

    // Résultat du test de restauration (Feature 017).
    'backup' => [
        // Cibles de sauvegarde cloud (fonctionnalité 017, phase 32).
        'provider' => [
            'dropbox' => 'Dropbox',
            'microsoft' => 'Microsoft OneDrive/SharePoint',
            'google' => 'Google Drive',
            'nextcloud' => 'Nextcloud',
        ],
        'target_status' => [
            'draft' => 'Brouillon',
            'active' => 'Active',
            'reauth_required' => 'Reconnexion requise',
            'blocked' => 'Bloquée',
            'disabled' => 'Désactivée',
        ],
        'generation_status' => [
            'building' => 'En création',
            'uploading' => 'Envoi en cours',
            'committed' => 'Validée',
            'verified' => 'Vérifiée',
            'verify_failed' => 'Vérification échouée',
            'failed' => 'Échouée',
        ],
        'retention_class' => [
            'daily' => 'Quotidienne',
            'weekly' => 'Hebdomadaire',
            'monthly' => 'Mensuelle',
        ],
        'restore-test-result' => [
            'passed' => 'Réussi',
            'partial' => 'Avec réserves',
            'failed' => 'Échoué',
        ],
    ],

    // Action à l’échéance d’un plan de maintenance (Feature 010 → Rang 43).
    'maintenance' => [
        'due_action' => [
            'none' => 'Simple avis (aucune fiche)',
            'ticket' => 'Créer un ticket de service',
        ],
    ],
];
