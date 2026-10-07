<?php
/*
 * Created on   : Wed Oct 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerIntakeUploadChannels.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Customer\Intake;

use App\Enums\Notification\NotificationEvent;
use App\Models\Customer\{CustomerIntake, CustomerIntakeUploadLink};
use App\Models\Platform\{Organization, User};
use App\Modules\ModuleRegistry;
use App\Services\Attachments\FileAttacher;
use App\Services\Customer\Contracts\IntakeUploadChannel;
use App\Services\Customer\Dto\IntakeRemoteFile;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\FileSystem\File;
use CommonToolkit\ValueObjects\ByteSize;
use Illuminate\Support\Facades\{Cache, Log};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Upload-Kanäle des Kundeneingangs (MVP-1078): Link öffnen, hochgeladene
 * Dateien als feste Kopie übernehmen, Link widerrufen. Die Prüfung folgt dem
 * Upload-Zweck des Eingangs — Größe gegen die App-Grenze (Server-zu-Server,
 * keine PHP-Grenze), Format gegen Endung und erkannten Typ. Jede Fassung wird
 * genau einmal verarbeitet; abgelehnte stehen mit Grund im Journal.
 */
class CustomerIntakeUploadChannels {
    public function __construct(
        private readonly ModuleRegistry $modules,
        private readonly FileAttacher $attacher,
        private readonly CustomerIntakeService $intakes,
        private readonly CustomerIntakeNotifier $notifier,
    ) {}

    /** Erster verfügbare Kanal der Organisation, null ohne Plugin oder Einrichtung. */
    public function channelFor(int $organizationId): ?IntakeUploadChannel {
        $organization = Organization::query()->withoutGlobalScopes()->find($organizationId);
        if ($organization === null) {
            return null;
        }
        foreach ($this->modules->extensions(IntakeUploadChannel::class) as $class) {
            $channel = app($class);
            if ($channel->isAvailable($organization)) {
                return $channel;
            }
        }

        return null;
    }

    public function latestLink(CustomerIntake $intake): ?CustomerIntakeUploadLink {
        return CustomerIntakeUploadLink::query()->withoutGlobalScopes()
            ->where('customer_intake_id', $intake->id)
            ->latest('id')
            ->first();
    }

    public function activeLink(CustomerIntake $intake): ?CustomerIntakeUploadLink {
        $link = $this->latestLink($intake);

        return $link !== null && $link->isActive() ? $link : null;
    }

    /** Link für den Kunden öffnen — ein bestehender aktiver Link wird wiederverwendet. */
    public function open(CustomerIntake $intake, User $portalUser): CustomerIntakeUploadLink {
        if (! $intake->acceptsCustomerFiles()) {
            throw ValidationException::withMessages(['uploads' => (string) __('customer_intake.error.uploads_closed')]);
        }
        $active = $this->activeLink($intake);
        if ($active !== null) {
            return $active;
        }
        $channel = $this->channelFor((int) $intake->organization_id)
            ?? throw ValidationException::withMessages(['uploads' => (string) __('customer_intake.cloud.unavailable')]);

        $organization = Organization::query()->withoutGlobalScopes()->findOrFail($intake->organization_id);
        $password = Str::password(16, symbols: false);
        $expiresAt = CarbonImmutable::now()->addDays(max(1, $channel->linkLifetimeDays($organization)))->endOfDay();
        try {
            $data = $channel->createLink($intake, $password, $expiresAt);
        } catch (Throwable $e) {
            Log::warning('Kundeneingang: Upload-Link nicht angelegt.', ['customer_intake_id' => $intake->id, 'channel' => $channel->key(), 'error' => $e->getMessage()]);

            throw ValidationException::withMessages(['uploads' => (string) __('customer_intake.cloud.open_failed', ['channel' => $channel->label()])]);
        }

        $link = CustomerIntakeUploadLink::query()->create([
            'organization_id' => $intake->organization_id,
            'customer_intake_id' => $intake->id,
            'channel' => $channel->key(),
            'external_id' => $data->externalId,
            'folder' => $data->folder,
            'url' => $data->url,
            'password' => $password,
            'expires_at' => $expiresAt,
            'created_by' => $portalUser->id,
        ]);
        $intake->record('cloud_link_opened', ['channel' => $channel->key(), 'expires_at' => $expiresAt->toIso8601String()], $portalUser);

        return $link;
    }

    /**
     * Neue Dateien übernehmen. Nimmt der Eingang nichts mehr an oder ist der
     * Link abgelaufen, folgt nach der letzten Übernahme der Widerruf.
     *
     * @return array{imported: int, rejected: int, failed: bool}
     */
    public function sync(CustomerIntakeUploadLink $link, ?User $actor = null): array {
        // Scheduler und Knopf dürfen nicht parallel übernehmen (doppelte Anhänge).
        $lock = Cache::lock('customer-intake-upload-link:' . $link->id, 600);
        if (! $lock->get()) {
            return ['imported' => 0, 'rejected' => 0, 'failed' => false];
        }
        try {
            return $this->syncLocked($link->refresh(), $actor);
        } finally {
            $lock->release();
        }
    }

    /** @return array{imported: int, rejected: int, failed: bool} */
    private function syncLocked(CustomerIntakeUploadLink $link, ?User $actor): array {
        $result = ['imported' => 0, 'rejected' => 0, 'failed' => false];
        if ($link->revoked_at !== null) {
            return $result;
        }
        $intake = CustomerIntake::query()->withoutGlobalScopes()->findOrFail($link->customer_intake_id);
        $channel = $this->channelByKey($link->channel);
        if ($channel === null) {
            $this->fail($link, $intake, 'channel_unavailable');

            return ['failed' => true] + $result;
        }

        try {
            $files = $channel->listFiles($link);
        } catch (Throwable $e) {
            $this->fail($link, $intake, class_basename($e) . ': ' . Str::limit($e->getMessage(), 200));

            return ['failed' => true] + $result;
        }

        $purpose = $intake->kind->uploadPurpose();
        $processed = (array) $link->processed_keys;
        $imported = [];
        $rejected = [];
        foreach ($files as $file) {
            if (in_array($file->key, $processed, true)) {
                continue;
            }
            $reason = $this->rejectionReason($file, $purpose);
            if ($reason === null) {
                try {
                    $attachment = $this->attacher->storeStream($intake, $channel->download($link, $file), $file->name, $intake->submitted_by_user_id, [
                        'organization_id' => $intake->organization_id,
                        'customer_visible' => true,
                        'meta_type' => 'channel:' . $link->channel,
                    ], 'customer-intakes', $purpose);
                    $imported[] = $attachment->original_name;
                } catch (ValidationException $e) {
                    $reason = (string) collect($e->errors())->flatten()->first();
                } catch (Throwable $e) {
                    // Übertragungsfehler: nicht als verarbeitet merken, nächster Lauf versucht es erneut.
                    $this->fail($link, $intake, class_basename($e) . ': ' . Str::limit($e->getMessage(), 200));
                    $result['failed'] = true;

                    break;
                }
            }
            if ($reason !== null) {
                $rejected[] = ['name' => File::sanitizeDisplayName($file->name), 'reason' => $reason];
            }
            $processed[] = $file->key;
            $link->forceFill(['processed_keys' => $processed])->save();
        }

        $link->forceFill(['last_synced_at' => now()] + ($result['failed'] ? [] : ['last_error' => null, 'last_error_at' => null]))->save();
        if ($imported !== []) {
            $intake->record('cloud_files_imported', ['channel' => $link->channel, 'files' => $imported], $actor);
            $this->intakes->resumeAfterCustomer($intake);
            $this->notifier->notifyStaff($intake, NotificationEvent::CustomerIntakeActivity, 'files_message', ['count' => (string) count($imported)]);
        }
        if ($rejected !== []) {
            $intake->record('cloud_files_rejected', ['channel' => $link->channel, 'files' => $rejected], $actor);
        }

        if (! $result['failed'] && (! $intake->acceptsCustomerFiles() || ($link->expires_at !== null && $link->expires_at->isPast()))) {
            $this->revoke($link, $actor, $intake->acceptsCustomerFiles() ? 'expired' : 'closed');
        }

        return ['imported' => count($imported), 'rejected' => count($rejected)] + $result;
    }

    /** Freigabe beim Anbieter widerrufen; scheitert das, bleibt der Link offen und der Fehler sichtbar. */
    public function revoke(CustomerIntakeUploadLink $link, ?User $actor, string $reason): void {
        if ($link->revoked_at !== null) {
            return;
        }
        $intake = CustomerIntake::query()->withoutGlobalScopes()->findOrFail($link->customer_intake_id);
        $channel = $this->channelByKey($link->channel);
        try {
            $channel?->revokeLink($link);
        } catch (Throwable $e) {
            $this->fail($link, $intake, class_basename($e) . ': ' . Str::limit($e->getMessage(), 200));

            return;
        }
        $link->forceFill(['revoked_at' => now()])->save();
        $intake->record('cloud_link_revoked', ['channel' => $link->channel, 'reason' => $reason], $actor);
    }

    /** Alle offenen Links abholen (Scheduler). @return int Anzahl bearbeiteter Links */
    public function syncAll(?int $organizationId = null): int {
        $count = 0;
        CustomerIntakeUploadLink::query()->withoutGlobalScopes()
            ->whereNull('revoked_at')
            ->when($organizationId !== null, fn ($query) => $query->where('organization_id', $organizationId))
            ->orderBy('id')
            ->each(function (CustomerIntakeUploadLink $link) use (&$count): void {
                $this->sync($link);
                $count++;
            });

        return $count;
    }

    private function channelByKey(string $key): ?IntakeUploadChannel {
        foreach ($this->modules->extensions(IntakeUploadChannel::class) as $class) {
            $channel = app($class);
            if ($channel->key() === $key) {
                return $channel;
            }
        }

        return null;
    }

    private function rejectionReason(IntakeRemoteFile $file, \App\Enums\Attachments\UploadPurpose $purpose): ?string {
        $name = '„' . File::sanitizeDisplayName($file->name) . '“';
        if (! in_array(strtolower(File::extension($file->name)), $purpose->extensions(), true)) {
            return (string) __('uploads.error.type', ['name' => $name]);
        }
        $maxBytes = FileAttacher::maxKb($purpose) * 1024;
        if ($file->size > $maxBytes) {
            return (string) __('uploads.error.too_large', ['name' => $name, 'size' => ByteSize::ofBytes($maxBytes)->format(0)]);
        }

        return null;
    }

    /** Fehler am Link festhalten; ins Journal nur beim Wechsel von „ok" zu „Fehler". */
    private function fail(CustomerIntakeUploadLink $link, CustomerIntake $intake, string $error): void {
        Log::warning('Kundeneingang: Upload-Kanal fehlgeschlagen.', ['link_id' => $link->id, 'channel' => $link->channel, 'error' => $error]);
        $first = $link->last_error === null;
        $link->forceFill(['last_error' => Str::limit($error, 290), 'last_error_at' => now()])->save();
        if ($first) {
            $intake->record('cloud_sync_failed', ['channel' => $link->channel]);
        }
    }
}
