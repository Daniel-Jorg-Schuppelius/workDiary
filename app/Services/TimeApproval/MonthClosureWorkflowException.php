<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MonthClosureWorkflowException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\TimeApproval;

/**
 * Wirft jeder MonthClosureService-Übergang, der die in
 * ../WorkDiary-Architecture/konzepte/monatsfreigabe.md §4 definierte Statusmaschine verletzt
 * oder eine fachliche Vorbedingung nicht erfüllt
 * (z. B. offene Tage / ⛔ Warnungen vor Submit).
 */
class MonthClosureWorkflowException extends TimeApprovalWorkflowException {}
