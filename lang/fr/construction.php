<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : construction.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Courriers VOB/B',
    'subtitle' => 'Avis d’entrave et réserves techniques avec preuve de réception.',
    'empty' => 'Aucun courrier enregistré.',
    'dialog_hint' => 'Les faits sont le cœur du courrier : concis, vérifiables et datés. Les références juridiques sont du texte — WorkDiary ne fournit pas de conseil juridique.',
    'disclaimer' => 'Les références juridiques sont des blocs de texte et non un conseil juridique. Il appartient aux parties de décider si un délai court ou si la durée des travaux est prolongée.',

    'kind' => [
        'obstruction' => 'Avis d’entrave',
        'concern' => 'Réserve technique',
    ],

    'legal' => [
        'obstruction' => '§ 6 al. 1 VOB/B',
        'concern' => '§ 4 al. 3 VOB/B',
    ],

    'status' => [
        'draft' => 'Brouillon',
        'sent' => 'Envoyé',
        'acknowledged' => 'Réception confirmée',
    ],

    'column' => [
        'number' => 'Numéro',
        'kind' => 'Type',
        'subject' => 'Objet',
        'project' => 'Chantier',
        'occurred_on' => 'Date',
        'status' => 'Statut',
    ],

    'filter' => [
        'kind' => 'Type',
        'status' => 'Statut',
    ],

    'field' => [
        'site' => 'Lieu d’intervention',
        'customer' => 'Maître d’ouvrage',
        'diary_entry' => 'Origine (entrée de journal)',
        'recipient_name' => 'Destinataire',
        'recipient_email' => 'E-mail du destinataire',
        'facts' => 'Faits',
        'facts_hint' => 'Qu’est-ce qui entrave précisément les travaux ou fonde la réserve ? Cause, prestation concernée, moment.',
        'impact_schedule' => 'Incidence sur la durée des travaux',
        'impact_cost' => 'Incidence sur les coûts',
        'claims_time_extension' => 'Prolongation de délai demandée',
        'claims_time_extension_hint' => 'Simple mention sur le courrier — WorkDiary ne décale aucun délai pour autant.',
        'legal_reference' => 'Référence juridique',
        'legal_reference_hint' => 'Apparaît sous forme de texte dans le courrier.',
        'acknowledged_note' => 'Mention relative à la réception',
    ],

    'section' => [
        'context' => 'Rattachement',
        'weather' => 'Météo du jour concerné',
        'delivery' => 'Preuve de réception',
        'acknowledge' => 'Accusé de réception',
    ],

    'action' => [
        'edit' => 'Modifier',
        'pdf' => 'PDF',
        'send' => 'Envoyer',
        'acknowledge' => 'Confirmer la réception',
    ],

    'badge' => [
        'time_extension' => 'Prolongation de délai demandée',
    ],

    'note' => [
        'time_extension' => 'Mention : une prolongation de délai a été demandée. Les délais dans WorkDiary restent inchangés — une prolongation ne prend effet que si elle est convenue entre les parties et saisie ici.',
        'time_extension_short' => 'Une prolongation demandée est une mention ; WorkDiary ne décale pas les délais automatiquement.',
    ],

    'delivery' => [
        'none' => 'Aucune preuve de réception enregistrée.',
        'method' => 'Mode de remise',
        'method_registered_mail' => 'Lettre recommandée',
        'method_courier' => 'Porteur',
        'method_handover' => 'Remise en main propre',
        'method_fax' => 'Télécopie',
        'method_portal' => 'Portail d’appels d’offres/de chantier',
        'delivered_at' => 'Remis le',
        'recipient' => 'Destinataire',
        'reference' => 'Numéro de reçu/de suivi',
        'record' => 'Enregistrer la remise',
    ],

    'mail' => [
        'title' => 'Envoyer :label :nr par e-mail',
    ],

    'pdf' => [
        'number' => 'Numéro',
        'subject' => 'Objet',
        'occurred_on' => 'Date',
        'project' => 'Chantier',
        'site' => 'Lieu d’intervention',
        'legal_reference' => 'Référence juridique',
        'facts' => 'Faits',
        'impact_schedule' => 'Incidence sur la durée des travaux',
        'impact_cost' => 'Incidence sur les coûts',
        'weather' => 'Météo du jour concerné',
        'weather_values' => 'Mesures',
        'weather_source' => 'Source',
        'time_extension' => 'Prolongation de délai demandée',
        'time_extension_text' => 'Nous demandons une prolongation du délai d’exécution correspondant à la durée de l’entrave.',
        'disclaimer' => 'Ce courrier cite les dispositions applicables sous forme de bloc de texte. Il ne remplace pas un examen juridique.',
    ],

    'error' => [
        'frozen' => 'Un courrier envoyé est figé et ne peut plus être modifié.',
    ],

    'created' => 'Courrier créé.',
    'updated' => 'Courrier enregistré.',
    'deleted' => 'Brouillon supprimé.',
    'delivery_recorded' => 'Preuve de réception enregistrée.',
    'acknowledged' => 'Réception confirmée.',
];
