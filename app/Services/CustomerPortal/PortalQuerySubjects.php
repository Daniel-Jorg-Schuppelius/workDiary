<?php
/*
 * Created on   : Mon Aug 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PortalQuerySubjects.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\CustomerPortal;

use App\Enums\CustomerPortal\PortalCapability;
use App\Models\{Customer, DiaryEntry, Document, TimeEntry, User};
use App\Support\Sqid;
use Illuminate\Database\Eloquent\Model;

/**
 * Zentrale Subject-Allowlist der Portal-Rückfragen (MVP-512): ein Subject ist
 * nur zulässig, wenn es zum Kunden gehört UND nach MVP-511 aktuell im Portal
 * sichtbar ist. Die Rückfrage-Capability erweitert freigegebene Bereiche —
 * sie kann keine Inhalte selbst sichtbar machen.
 */
class PortalQuerySubjects {
    /** Formular-Schlüssel → Modellklasse (zentrale Allowlist). */
    public const TYPES = [
        'diary' => DiaryEntry::class,
        'time_entry' => TimeEntry::class,
        'document' => Document::class,
    ];

    public function __construct(private readonly PortalVisibility $visibility) {}

    /**
     * Löst Formular-Typ + Sqid zu einem für DIESEN Portalbenutzer sichtbaren
     * Subject auf — sonst null (Controller antwortet kundensicher 404).
     */
    public function resolve(User $portalUser, string $type, string $sqid): ?Model {
        $class = self::TYPES[$type] ?? null;
        $customer = $portalUser->customer;
        if ($class === null || $customer === null) {
            return null;
        }

        $id = Sqid::decode($class, $sqid);
        if ($id === null) {
            return null;
        }

        /** @var Model|null $subject */
        $subject = $class::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $customer->organization_id)
            ->whereKey($id)
            ->first();
        if ($subject === null || ! $this->visibleTo($customer, $subject)) {
            return null;
        }

        return $subject;
    }

    /** Doppeltes Gate: Kundenbezug + Bereichsfreigabe + Objektsichtbarkeit. */
    public function visibleTo(Customer $customer, Model $subject): bool {
        if ($subject instanceof DiaryEntry) {
            return (int) $subject->customer_id === (int) $customer->id
                && $this->visibility->allows($customer, PortalCapability::Diary);
        }

        if ($subject instanceof TimeEntry) {
            if (! $this->visibility->timeDetail($customer)->showsEntries()) {
                return false;
            }
            $published = $subject->customer_visible_at !== null
                || $this->visibility->timeScope($customer) === PortalVisibility::TIME_SCOPE_ALL;

            return $published
                && (int) ($subject->project->customer_id ?? 0) === (int) $customer->id;
        }

        if ($subject instanceof Document) {
            // Objekt-Gate wie der Portal-DocumentController: derselbe Scope,
            // damit Bereichsfreigabe nie die Dokumentfreigabe umgeht.
            return $this->visibility->allows($customer, PortalCapability::Documents)
                && Document::query()
                    ->visibleToCustomer((int) $customer->organization_id, (int) $customer->id)
                    ->whereKey($subject->getKey())
                    ->exists();
        }

        return false;
    }

    /** Kurzer Anzeigetext des Subjects für Listen/Benachrichtigungen. */
    public function label(Model $subject): string {
        return match (true) {
            $subject instanceof DiaryEntry => (string) __('Auftrag :title', ['title' => (string) ($subject->getAttribute('title') ?? '#' . $subject->getKey())]),
            $subject instanceof TimeEntry => (string) __('Zeiteintrag vom :date', ['date' => $subject->date?->format(\App\Support\Formats::date()) ?? '—']),
            $subject instanceof Document => (string) __('Dokument :name', ['name' => (string) ($subject->getAttribute('title') ?? $subject->getAttribute('name') ?? '#' . $subject->getKey())]),
            default => '#' . $subject->getKey(),
        };
    }

    /** Formular-Schlüssel eines Subjects (Umkehrung der Allowlist). */
    public function typeKey(Model $subject): ?string {
        foreach (self::TYPES as $key => $class) {
            if ($subject instanceof $class) {
                return $key;
            }
        }

        return null;
    }
}
