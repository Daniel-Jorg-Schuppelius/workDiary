<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : notification.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'center' => 'Notifiche',
        'center_subtitle' => 'Le tue notifiche in-app — lette e non lette.',
        'empty' => 'Nessuna notifica.',
        'empty_message' => 'Non appena un evento ti riguarda, apparirà qui.',
        'rules' => 'Regole di notifica',
        'rules_subtitle' => 'Regole per tipo di evento: canali, destinatari ed escalation.',
        'rules_help' => 'Come funzionano le regole di notifica?',
        'rules_help_text' => 'Per ogni tipo di evento la regola definisce se e tramite quali canali vengono inviate le notifiche e chi le riceve (persona interessata, ruoli, persone fisse). Senza una regola salvata vale l’impostazione predefinita mostrata. Escalation: se un evento scaduto resta irrisolto oltre il tempo configurato, viene notificato in aggiunta il ruolo di escalation. I livelli di escalation aggiuntivi (2/3) notificano ulteriori gruppi di destinatari, ciascuno dopo la propria scadenza. Il canale «Calendario» inserisce gli eventi con data nei calendari collegati dell’organizzazione (CalDAV/Microsoft 365/Google).',
        'edit_rule' => 'Modifica regola di notifica',
        'preferences' => 'Notifiche',
    ],

    'field' => [
        'event' => 'Evento',
        'enabled' => 'Attivo',
        'rule_enabled' => 'Notifiche attive per questo evento',
        'channels' => 'Canali',
        'recipients' => 'Destinatari',
        'affected_user' => 'Persona interessata',
        'notify_affected_help' => 'Notifica la persona interessata (es. assegnataria o richiedente)',
        'recipient_roles' => 'Ruoli destinatari',
        'recipient_users' => 'Destinatari fissi aggiuntivi',
        'fixed_users' => 'destinatari fissi',
        'escalation' => 'Escalation',
        'escalation_enabled' => 'Escalation attiva',
        'escalation_help' => 'Se l’evento resta irrisolto per il tempo configurato dopo la prima notifica, viene notificato in aggiunta il ruolo di escalation.',
        'escalation_unsupported' => 'L’escalation è disponibile solo per gli eventi scaduti.',
        'escalate_after_hours' => 'Escalation dopo (ore)',
        'escalation_role' => 'Ruolo di escalation',
        'escalation_summary' => 'dopo :hours h a :role',
        'escalation_ladder_help' => 'Livello aggiuntivo opzionale: se l’evento resta irrisolto per il tempo configurato dopo l’invio del livello di escalation precedente, viene notificato in aggiunta questo gruppo di destinatari.',
        'escalation_level2' => 'Livello di escalation 2',
        'escalation_level3' => 'Livello di escalation 3',
        'escalation_level_after_hours' => 'Dopo ulteriori ore',
        'escalation_level_roles' => 'Ruoli destinatari del livello',
        'escalation_level_users' => 'Destinatari fissi del livello',
        'escalation_level_summary' => 'Livello :level: +:hours h',
        'default_rule' => 'Predefinito (non ancora personalizzato)',
        'unread' => 'nuovo',
        'yes' => 'Sì',
        'no' => 'No',
        'mail_enabled' => 'Ricevi notifiche via e-mail',
        'quiet_from' => 'Ore di silenzio da',
        'quiet_to' => 'Ore di silenzio fino a',
        'preferences_help' => 'Le notifiche in-app vengono sempre raccolte. E-mail (e push) possono essere disattivate; durante le ore di silenzio non vengono inviati e-mail/push.',
    ],

    'action' => [
        'mark_read' => 'Segna come letta',
        'mark_all_read' => 'Segna tutte come lette',
        'delete' => 'Elimina',
        'delete_read' => 'Elimina lette',
        'open' => 'Apri',
        'show_all' => 'Mostra tutte',
        'edit' => 'Modifica',
        'save' => 'Salva',
    ],

    'confirm' => [
        'delete_read' => 'Eliminare definitivamente tutte le notifiche lette?',
    ],

    'flash' => [
        'all_read' => 'Tutte le notifiche sono state segnate come lette.',
        'deleted' => 'La notifica è stata eliminata.',
        'read_deleted' => ':count notifica/notifiche lette eliminate.',
        'rule_saved' => 'La regola di notifica per «:event» è stata salvata.',
    ],

    'mail' => [
        'subject' => ':event: :title',
        'subject_escalation' => 'Escalation — :event: :title',
        'greeting' => 'Ciao :name,',
        'action' => 'Apri nel sistema',
    ],

    'message' => [
        'issue_assigned' => ':actor ti ha assegnato questo punto aperto.',
        'customer_query_raised' => 'Un cliente ha posto una domanda.',
        'due_soon' => 'In scadenza il :date.',
        'overdue' => 'Scaduto dal :date.',
        'followup_due_soon' => 'Azione di follow-up in scadenza il :date.',
        'followup_overdue' => 'Azione di follow-up scaduta dal :date.',
        'followup_fallback_title' => 'Follow-up di una nota di comunicazione',
        'expiring_soon' => 'Il documento scade il :date.',
        'expired' => 'Il documento è scaduto dal :date.',
        'correction_requested_title' => 'Richiesta di correzione orari di :user per il :date',
        'correction_decided_title' => 'Decisione sulla tua richiesta di correzione (:date)',
        'correction_approved' => 'La tua richiesta è stata approvata. :note',
        'correction_rejected' => 'La tua richiesta è stata respinta. :note',
        'month_submitted_title' => 'Chiusura mensile :period inviata da :user',
        'certificate_expiring' => 'Il certificato scade il :date — pianificare per tempo la ri-certificazione.',
        'corrective_action_overdue' => 'Azione correttiva in ritardo dal :date.',
        'risk_review_due' => 'Riesame della valutazione del rischio accettata in scadenza il :date.',
        'vulnerability_overdue' => 'Vulnerabilità in ritardo dal :date.',
        'supplier_review_overdue' => 'Riesame fornitore scaduto dal :date.',
        'sla_at_risk' => 'Scadenza di risoluzione SLA a rischio — entro il :date.',
        'sla_breached' => 'Scadenza di risoluzione SLA superata — era prevista il :date.',
        'sla_quota_warning' => 'Quota SLA al :percent% (:consumed di :included min) nel periodo :period.',
        'asset_return_overdue' => 'Restituzione dell\'asset in ritardo — prevista il :date.',
        'incident_critical' => 'Nuovo incidente di sicurezza critico segnalato.',
        'safety_critical_event' => 'Evento di sicurezza critico (:severity) segnalato a :location.',
        'qualification_expiring' => 'La qualifica/formazione scade il :date.',
        'maintenance_due_soon' => 'Il piano di manutenzione :label scade il :date.',
        'maintenance_overdue' => 'Il piano di manutenzione :label è scaduto dal :date.',
    ],
];
