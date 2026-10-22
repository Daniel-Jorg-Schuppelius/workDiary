<?php
/*
 * Created on   : Fri Jun 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DayCloseWorkflowException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\TimeApproval;

/**
 * Wirft jeder DayCloseService-Übergang, der die in ../WorkDiary-Architecture/konzepte/tagesabschluss.md
 * §3/§5 definierte Statusmaschine verletzt oder eine fachliche
 * Vorbedingung nicht erfüllt (⛔-Warnungen, Zukunftstag, gesperrter
 * Monat, zu kurze Begründung).
 */
class DayCloseWorkflowException extends TimeApprovalWorkflowException {}
