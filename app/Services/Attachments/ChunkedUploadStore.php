<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ChunkedUploadStore.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Attachments;

use CommonToolkit\Helper\Data\JsonHelper;
use CommonToolkit\Helper\FileSystem\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\{Carbon, Str};
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Wiederaufnehmbarer Upload in Teilen (MVP-1265). Große Medien kommen in
 * Stücken unterhalb der PHP-Grenzen an; bricht die Verbindung ab, lädt der
 * Browser nur die fehlenden Teile nach. Erst das Zusammensetzen erzeugt eine
 * Datei, die danach den gewöhnlichen Weg über den {@see FileAttacher} nimmt —
 * Endungen, MIME-Prüfung und Quote gelten unverändert.
 *
 * Ein Upload gehört genau einer Person und einem Ziel (`scope`); Teile
 * unvollständiger Uploads räumt `uploads:prune-chunks` nach einem Tag ab.
 */
class ChunkedUploadStore {
    public const DIRECTORY = 'chunked-uploads';

    private const CHUNK_BYTES = 4 * 1024 * 1024;

    private const MAX_AGE_HOURS = 24;

    /**
     * @return array{token: string, chunk_size: int, chunks: int, received: list<int>}
     */
    public function start(int $userId, string $scope, string $name, int $size, ?string $mime): array {
        $maxBytes = FileAttacher::maxKb() * 1024;
        if ($size < 1 || $size > $maxBytes) {
            throw ValidationException::withMessages(['media' => (string) __('validation.max.file', ['attribute' => 'media', 'max' => FileAttacher::maxKb()])]);
        }
        $token = Str::random(40);
        $chunkSize = $this->chunkSize();
        $meta = [
            'user_id' => $userId,
            'scope' => $scope,
            'name' => File::sanitizeFilename($name, keepExtension: true) ?: 'upload',
            'size' => $size,
            'mime' => $mime,
            'chunk_size' => $chunkSize,
            'chunks' => (int) ceil($size / $chunkSize),
            'created_at' => Carbon::now()->toIso8601String(),
        ];
        Storage::disk('local')->put($this->path($token, 'meta.json'), JsonHelper::encode($meta));

        return ['token' => $token, 'chunk_size' => $chunkSize, 'chunks' => $meta['chunks'], 'received' => []];
    }

    /** @return array{chunk_size: int, chunks: int} */
    public function describe(int $userId, string $scope, string $token): array {
        $meta = $this->meta($userId, $scope, $token);

        return ['chunk_size' => $meta['chunk_size'], 'chunks' => $meta['chunks']];
    }

    /** @return list<int> */
    public function received(int $userId, string $scope, string $token): array {
        $this->meta($userId, $scope, $token);
        $indices = [];
        foreach (Storage::disk('local')->files(self::DIRECTORY . '/' . $token) as $file) {
            if (preg_match('/\/(\d+)\.part$/', $file, $m) === 1) {
                $indices[] = (int) $m[1];
            }
        }
        sort($indices);

        return $indices;
    }

    public function put(int $userId, string $scope, string $token, int $index, UploadedFile $chunk): void {
        $meta = $this->meta($userId, $scope, $token);
        $expected = $index === $meta['chunks'] - 1 ? $meta['size'] - $index * $meta['chunk_size'] : $meta['chunk_size'];
        if ($index < 0 || $index >= $meta['chunks'] || (int) $chunk->getSize() !== $expected) {
            throw ValidationException::withMessages(['chunk' => (string) __('uploads.chunked.invalid_chunk')]);
        }
        $chunk->storeAs(self::DIRECTORY . '/' . $token, $index . '.part', 'local');
    }

    /**
     * Teile zusammensetzen. Die Datei liegt bereits auf dem Server; das
     * Testkennzeichen von UploadedFile überspringt nur `is_uploaded_file`.
     */
    public function assemble(int $userId, string $scope, string $token): UploadedFile {
        $meta = $this->meta($userId, $scope, $token);
        if (count($this->received($userId, $scope, $token)) !== $meta['chunks']) {
            throw ValidationException::withMessages(['media' => (string) __('uploads.chunked.incomplete')]);
        }
        $disk = Storage::disk('local');
        $target = $disk->path($this->path($token, 'assembled'));
        $parts = (static function () use ($disk, $token, $meta): \Generator {
            for ($i = 0; $i < $meta['chunks']; $i++) {
                yield from File::readChunks($disk->path(self::DIRECTORY . '/' . $token . '/' . $i . '.part'), 1024 * 1024, strict: true);
            }
        })();
        if (File::writeStream($target, $parts) !== $meta['size']) {
            throw ValidationException::withMessages(['media' => (string) __('uploads.chunked.incomplete')]);
        }

        return new UploadedFile($target, $meta['name'], $meta['mime'], null, true);
    }

    public function discard(string $token): void {
        if (preg_match('/^[A-Za-z0-9]{40}$/', $token) === 1) {
            Storage::disk('local')->deleteDirectory(self::DIRECTORY . '/' . $token);
        }
    }

    /** Liegengebliebene Uploads entfernen; Rückgabe = Anzahl. */
    public function prune(?Carbon $now = null): int {
        $limit = ($now ?? Carbon::now())->copy()->subHours(self::MAX_AGE_HOURS)->getTimestamp();
        $disk = Storage::disk('local');
        $removed = 0;
        foreach ($disk->directories(self::DIRECTORY) as $directory) {
            $meta = $directory . '/meta.json';
            if (! $disk->exists($meta) || $disk->lastModified($meta) < $limit) {
                $disk->deleteDirectory($directory);
                $removed++;
            }
        }

        return $removed;
    }

    /** Teilgröße unterhalb der PHP-Grenzen, höchstens 4 MB. */
    private function chunkSize(): int {
        $php = min(FileAttacher::effectiveMaxKb(), FileAttacher::effectiveTotalKb()) * 1024;

        return max(256 * 1024, min(self::CHUNK_BYTES, $php - 64 * 1024));
    }

    /**
     * @return array{user_id: int, scope: string, name: string, size: int, mime: ?string, chunk_size: int, chunks: int}
     */
    private function meta(int $userId, string $scope, string $token): array {
        $raw = preg_match('/^[A-Za-z0-9]{40}$/', $token) === 1 ? Storage::disk('local')->get($this->path($token, 'meta.json')) : null;
        $meta = $raw !== null ? JsonHelper::decode($raw) : null;
        if (! is_array($meta) || (int) ($meta['user_id'] ?? 0) !== $userId || ($meta['scope'] ?? null) !== $scope) {
            throw ValidationException::withMessages(['media' => (string) __('uploads.chunked.unknown')]);
        }

        return [
            'user_id' => (int) $meta['user_id'],
            'scope' => (string) $meta['scope'],
            'name' => (string) ($meta['name'] ?? 'upload'),
            'size' => (int) ($meta['size'] ?? 0),
            'mime' => isset($meta['mime']) ? (string) $meta['mime'] : null,
            'chunk_size' => max(1, (int) ($meta['chunk_size'] ?? self::CHUNK_BYTES)),
            'chunks' => (int) ($meta['chunks'] ?? 0),
        ];
    }

    private function path(string $token, string $file): string {
        return self::DIRECTORY . '/' . $token . '/' . $file;
    }
}
