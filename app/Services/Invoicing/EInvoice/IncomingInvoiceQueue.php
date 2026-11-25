<?php
/*
 * Created on   : Wed Nov 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingInvoiceQueue.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\EInvoice;

use App\Models\Invoicing\IncomingEInvoice;
use App\Models\Platform\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Durcharbeiten des Rechnungseingangs (MVP-1806): die Reiter „Zuzuordnen“ und
 * „Zu prüfen“ als Schlange in Listenreihenfolge (neuester Eingang zuerst).
 * Nach einer Aktion bleibt der Eingang stehen, solange er noch in der Schlange
 * ist; sonst folgt der nächste.
 */
final class IncomingInvoiceQueue {
    public const array QUEUES = ['assign', 'review'];

    public static function from(mixed $value): ?string {
        return is_string($value) && in_array($value, self::QUEUES, true) ? $value : null;
    }

    /** @return Builder<IncomingEInvoice> */
    public function query(string $queue, User $user): Builder {
        $query = IncomingEInvoice::query()->visibleTo($user);

        return $queue === 'review' ? $query->awaitingReview() : $query->awaitingAssignment();
    }

    public function first(string $queue, User $user): ?IncomingEInvoice {
        return $this->query($queue, $user)->orderByDesc('received_at')->orderByDesc('id')->first();
    }

    public function contains(string $queue, User $user, IncomingEInvoice $incoming): bool {
        return $incoming->exists && $this->query($queue, $user)->whereKey($incoming->getKey())->exists();
    }

    /** Der Eingang hinter `$anchor`; `$wrap` beginnt am Ende wieder vorn (übersprungene Eingänge). */
    public function next(string $queue, User $user, IncomingEInvoice $anchor, bool $wrap = false): ?IncomingEInvoice {
        $next = $this->beyond($this->query($queue, $user), $anchor, '<')->orderByDesc('received_at')->orderByDesc('id')->first();
        if ($next !== null || ! $wrap) {
            return $next;
        }

        return $this->query($queue, $user)->whereKeyNot($anchor->getKey())->orderByDesc('received_at')->orderByDesc('id')->first();
    }

    public function previous(string $queue, User $user, IncomingEInvoice $anchor): ?IncomingEInvoice {
        return $this->beyond($this->query($queue, $user), $anchor, '>')->orderBy('received_at')->orderBy('id')->first();
    }

    /** @return array{position: int, total: int} */
    public function position(string $queue, User $user, IncomingEInvoice $incoming): array {
        return [
            'position' => $this->beyond($this->query($queue, $user), $incoming, '>')->count() + 1,
            'total' => $this->query($queue, $user)->count(),
        ];
    }

    /**
     * Ziel nach einer Aktion: derselbe Eingang, solange er in der Schlange
     * steht, sonst der nächste. null = Schlange leer oder kein Durcharbeiten —
     * der Aufrufer kehrt zur Liste zurück.
     */
    public function continueUrl(?string $queue, User $user, IncomingEInvoice $incoming): ?string {
        if ($queue === null) {
            return null;
        }
        $target = $this->contains($queue, $user, $incoming) ? $incoming : $this->next($queue, $user, $incoming, wrap: true);
        $document = $target?->document;

        return $document === null ? null : route('finance.incoming-invoices.show', ['document' => $document, 'queue' => $queue]);
    }

    /**
     * @param  Builder<IncomingEInvoice>  $query
     * @param  '<'|'>'  $operator
     * @return Builder<IncomingEInvoice>
     */
    private function beyond(Builder $query, IncomingEInvoice $anchor, string $operator): Builder {
        $at = $anchor->received_at;

        return $query->where(static fn (Builder $beyond) => $beyond
            ->where('received_at', $operator, $at)
            ->orWhere(static fn (Builder $same) => $same->where('received_at', $at)->where('id', $operator, $anchor->getKey())));
    }
}
