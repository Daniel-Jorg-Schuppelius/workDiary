<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupportReportPackager.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Support;

use CommonToolkit\Helper\Data\JsonHelper;
use CommonToolkit\Helper\FileSystem\File as ToolkitFile;
use CommonToolkit\Helper\FileSystem\Folder as ToolkitFolder;
use RuntimeException;
use ZipArchive;

/**
 * Verpackt das vom {@see SupportReportBuilder} erzeugte Array als ZIP-Datei
 * (optional mit Passwort) und berechnet den SHA-256-Hash zur Verifikation.
 */
class SupportReportPackager {
    /**
     * @param  array<string, mixed>  $bundle
     * @return array{path:string, sha256:string, bytes:int, password_set:bool, json_bytes:int}
     */
    public function package(array $bundle, ?string $password = null, ?string $targetPath = null): array {
        $json = JsonHelper::encode($bundle, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        $targetPath ??= storage_path('app/support/' . $this->defaultFilename($bundle));
        $dir = dirname($targetPath);
        if (! ToolkitFolder::exists($dir)) {
            ToolkitFolder::create($dir, 0755, true);
        }

        $zip = new ZipArchive();
        $opened = $zip->open($targetPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($opened !== true) {
            throw new RuntimeException('Konnte ZIP-Datei nicht öffnen (Code ' . (int) $opened . ').');
        }

        $entryName = 'support-report.json';
        $zip->addFromString($entryName, $json);

        $passwordSet = false;
        if ($password !== null && $password !== '') {
            $zip->setPassword($password);
            // AES-256, falls verfügbar; fällt sonst auf ZipCrypto zurück.
            $encryption = defined('ZipArchive::EM_AES_256') ? ZipArchive::EM_AES_256 : 0;
            if ($encryption !== 0) {
                $zip->setEncryptionName($entryName, $encryption);
                $passwordSet = true;
            }
        }

        $zip->close();

        $bytes = ToolkitFile::size($targetPath);
        $sha256 = ToolkitFile::hash($targetPath);

        return [
            'path' => $targetPath,
            'sha256' => $sha256,
            'bytes' => $bytes,
            'password_set' => $passwordSet,
            'json_bytes' => strlen($json),
        ];
    }

    /**
     * Erzeugt eine kurze Inhalts-Übersicht für das Vorab-Review (Spec §5).
     *
     * @param  array<string, mixed>  $bundle
     * @return array{total_estimated_kb:int, top_sections: list<array{key:string, kb:int}>}
     */
    public function preview(array $bundle): array {
        $sections = [];
        foreach ($bundle as $key => $value) {
            $serialized = JsonHelper::encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $sections[] = [
                'key' => (string) $key,
                'kb' => (int) ceil(strlen($serialized) / 1024),
            ];
        }

        usort($sections, static fn(array $a, array $b): int => $b['kb'] <=> $a['kb']);

        return [
            'total_estimated_kb' => array_sum(array_column($sections, 'kb')),
            'top_sections' => array_slice($sections, 0, 6),
        ];
    }

    /** @param  array<string, mixed>  $bundle */
    private function defaultFilename(array $bundle): string {
        $stamp = preg_replace('/[^0-9TZ-]/', '', (string) ($bundle['generated_at'] ?? ''));
        if (! is_string($stamp) || $stamp === '') {
            $stamp = (string) time();
        }

        return 'support-report-' . $stamp . '-' . bin2hex(random_bytes(3)) . '.zip';
    }
}
