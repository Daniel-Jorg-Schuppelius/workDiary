<?php
/*
 * Created on   : Tue Oct 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ApprovalInboxSubject.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Approval\Contracts;

use App\Models\Approval\Approval;
use App\Models\Platform\User;
use App\Services\Approval\Dto\ApprovalInboxEntry;
use Illuminate\Database\Eloquent\Model;

/**
 * Erweiterungspunkt des Genehmigungs-Eingangs: ein Modul meldet einen
 * Gegenstand mit Freigabekette an (Registrierung über `Manifest::extensions()`).
 * Der Eingang zeigt nur angemeldete Gegenstände und entscheidet über
 * {@see self::decide()} — denselben Weg wie die Akte des Gegenstands.
 */
interface ApprovalInboxSubject {
    /** @return class-string<Model> */
    public function approvableClass(): string;

    /** @return list<string> Beziehungen, die der Eingang für {@see self::present()} vorlädt */
    public function eagerLoad(): array;

    /** Erwartet der Gegenstand noch Entscheidungen? Sonst verschwinden seine offenen Stufen aus dem Eingang. */
    public function awaitsDecision(Model $approvable): bool;

    /** Titel, Verweis auf die Akte (null ohne Leserecht) und Zusatzzeile. */
    public function present(Model $approvable, User $viewer): ApprovalInboxEntry;

    /**
     * Entscheidet die Stufe samt Folgen am Gegenstand (Status, Audit).
     *
     * @throws \RuntimeException|\InvalidArgumentException fachliche Ablehnung (Selbstfreigabe, entschieden, abgelöst …)
     */
    public function decide(Approval $approval, User $actor, string $decision, ?string $reason, ?int $delegateUserId): void;
}
