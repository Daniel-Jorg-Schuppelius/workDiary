<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DelegableWork.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Delegation\Contracts;

use App\Models\Platform\User;

/**
 * Erweiterungspunkt der Vertretung (Feature 202, MVP-1647): ein Bereich
 * zugewiesener Arbeit, den eine Vertretung übernimmt — Aufträge, Aufgaben,
 * Tickets. Ein Modul meldet ihn über `Manifest::extensions()`, gelesen wird über
 * `ModuleExtensions::activeFor()` (nur aktive Module).
 *
 * Delegierbar ist nur, was einer Person **zugewiesen** ist. Persönliches —
 * Zeiten, Abwesenheiten, Personalakte, private Notizen — und alles, was ein
 * Recht statt einer Zuweisung freigibt (Genehmigungen, Prüfrechte), ist es
 * nie: die Vertretung verleiht keine Rechte.
 *
 * Ist die Klasse zugleich eine Quelle des Arbeitsvorrats
 * ({@see \App\Services\WorkQueue\Contracts\WorkItemSource}), zeigt der
 * Arbeitsvorrat der Vertretung die persönlich zugewiesenen Vorgänge der
 * vertretenen Person dieser Quelle, gekennzeichnet „in Vertretung für …“.
 * Policies und „Meine“-Sichten schließen über
 * {@see \App\Services\Delegation\ActsForResolver::principalIds()} an (MVP-1648).
 */
interface DelegableWork {
    /** Stabiler Schlüssel des Bereichs (bei Arbeitsvorrats-Quellen derselbe wie dort). */
    public function key(): string;

    /** Bezeichnung im Vertretungsdialog, z. B. „Zugewiesene Aufträge“. */
    public function label(): string;

    /** Offene, der Person zugewiesene Vorgänge des Bereichs — nur die Anzahl, nie Inhalte. */
    public function count(User $user): int;
}
