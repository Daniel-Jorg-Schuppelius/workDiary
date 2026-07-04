<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : integration.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'webhook' => [
        'title' => [
            'index' => 'Webhooks',
            'subtitle' => 'Notifications d’événements sortantes vers des systèmes externes.',
            'help' => 'Comment fonctionnent les webhooks ?',
            'help_text' => 'Un webhook envoie une charge utile JSON signée par POST HTTPS vers votre URL lors des événements abonnés. La signature (HMAC-SHA256 sur l’horodatage et le corps) figure dans l’en-tête X-WorkDiary-Signature ; vérifiez-la avec la clé de signature. Après plusieurs échecs, le point de terminaison est désactivé automatiquement.',
            'create' => 'Créer un webhook',
            'edit' => 'Modifier le webhook',
            'empty' => 'Aucun webhook créé pour le moment.',
        ],
        'field' => [
            'basics' => 'Informations de base',
            'label' => 'Libellé',
            'label_placeholder' => 'p. ex. intégration ERP',
            'url' => 'URL cible',
            'url_help' => 'Point de terminaison HTTPS qui reçoit la requête POST.',
            'events' => 'Événements abonnés',
            'events_help' => 'Seuls les événements sélectionnés déclenchent un envoi.',
            'security' => 'Sécurité et statut',
            'signing_secret' => 'Clé de signature',
            'endpoint_active' => 'Point de terminaison actif',
            'status' => 'Statut',
            'active' => 'Actif',
            'inactive' => 'Inactif',
            'auto_disabled' => 'Désactivé automatiquement',
            'auto_disabled_help' => 'Désactivé automatiquement après trop d’échecs. L’enregistrer comme actif le réactive.',
            'last_deliveries' => 'Dernières livraisons',
            'no_deliveries' => 'Aucune livraison pour le moment.',
        ],
        'action' => [
            'create' => 'Créer',
            'edit' => 'Modifier',
            'save' => 'Enregistrer',
            'delete' => 'Supprimer',
            'delete_confirm' => 'Supprimer vraiment ce webhook ? Les journaux de livraison existants sont conservés.',
            'rotate_secret' => 'Renouveler la clé de signature',
            'test' => 'Envoyer un événement de test',
        ],
        'secret' => [
            'shown_once' => 'Clé de signature – visible uniquement maintenant',
            'shown_once_help' => 'Copiez la clé maintenant. Pour des raisons de sécurité, elle n’est plus jamais affichée en clair.',
            'rotate_help' => 'La clé en clair n’est affichée qu’une seule fois lors de la création/du renouvellement.',
            'rotate_confirm' => 'Générer une nouvelle clé de signature ? L’ancienne clé devient immédiatement invalide.',
        ],
        'flash' => [
            'created' => 'Webhook créé.',
            'updated' => 'Webhook mis à jour.',
            'deleted' => 'Webhook supprimé.',
            'secret_rotated' => 'Clé de signature renouvelée.',
            'test_sent' => 'Événement de test mis en file d’attente.',
        ],
        'event' => [
            'openIssue.assigned' => 'Point ouvert assigné',
            'openIssue.overdue' => 'Point ouvert en retard',
            'safetyEvent.reported' => 'Événement de sécurité signalé',
            'isms.incidentCritical' => 'Incident de sécurité ISMS critique',
            'timeCorrection.requested' => 'Correction du temps de travail demandée',
            'monthClosure.submitted' => 'Clôture mensuelle soumise',
            'sla.breached' => 'Délai SLA dépassé',
            'document.expired' => 'Document expiré',
        ],
        'delivery_status' => [
            'pending' => 'En attente',
            'success' => 'Réussi',
            'failed' => 'Échoué',
        ],
    ],
    'external_type' => [
        'client' => 'Client',
        'client_id' => 'ID client',
        'contact' => 'Contact',
        'delivery_note' => 'Bon de livraison',
        'dunning' => 'Relance',
        'entry' => 'Saisie',
        'foreign_client' => 'Client externe',
        'invoice' => 'Facture',
        'order_confirmation' => 'Confirmation de commande',
        'project' => 'Projet',
        'project_id' => 'ID projet',
        'pushed_entry' => 'Saisie transférée',
        'quotation' => 'Devis',
        'session' => 'Session',
        'user' => 'Utilisateur',
        'voucher' => 'Justificatif',
        'work_package' => 'Lot de travaux',
        'anydesk_id' => 'ID AnyDesk',
        'teamviewer_id' => 'ID TeamViewer',
    ],
    'outbox' => [
        'status' => [
            'pending' => 'En attente',
            'processing' => 'En cours de transfert',
            'confirmed' => 'Confirmé',
            'failed' => 'Échoué',
            'compensation_required' => 'Compensation requise',
        ],
    ],
];
