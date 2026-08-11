<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : costcenter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'rules' => "Regole dei centri di costo",
        'rules_subtitle' => "Centri di costo per l'export orario verificato: per utente, per team o come valore predefinito dell'organizzazione.",
        'rules_help' => "Come funzionano le regole dei centri di costo?",
        'rules_help_text' => "Durante l'export orario ogni riga riceve il centro di costo del collaboratore: prima vince una regola utente, poi la regola del team con la priorità più alta, infine il valore predefinito dell'organizzazione. Nell'interfaccia di verifica è possibile sovrascrivere il centro di costo per riga.",
        'create_rule' => "Crea regola centro di costo",
        'edit_rule' => "Modifica regola centro di costo",
        'empty' => "Nessuna regola dei centri di costo",
    ],

    'field' => [
        'basics' => "Regola",
        'source' => "Origine",
        'source_help' => "Le regole utente prevalgono sulle regole del team; senza corrispondenza vale il valore predefinito dell'organizzazione.",
        'source_default' => "Predefinito dell'organizzazione",
        'source_user' => "Utente",
        'source_team' => "Team",
        'user' => "Utente",
        'team' => "Team",
        'choose' => "– selezionare –",
        'cost_center' => "Centro di costo",
        'cost_center_master' => "Centro di costo dall'anagrafica",
        'cost_center_master_free' => "– inserimento libero –",
        'cost_center_master_help' => "La selezione riprende il codice dell'anagrafica; senza selezione vale il codice inserito liberamente.",
        'priority' => "Priorità",
        'priority_help' => "Spareggio tra più regole di team: vince la priorità più alta.",
    ],

    'action' => [
        'create' => "Crea",
        'edit' => "Modifica",
        'save' => "Salva",
        'delete' => "Elimina",
        'delete_confirm' => "Eliminare davvero questa regola del centro di costo? Gli export esistenti restano invariati.",
    ],

    'flash' => [
        'created' => "Regola del centro di costo creata.",
        'updated' => "Regola del centro di costo aggiornata.",
        'deleted' => "Regola del centro di costo eliminata.",
    ],
];
