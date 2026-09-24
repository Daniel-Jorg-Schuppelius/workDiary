<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResalePurchaseImportRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Http\UploadedFile;

/**
 * Anbieterrechnungen als PDF hochladen (Feature 152, MVP-762): bis zu 50
 * Dateien je 10 MB.
 */
class ResalePurchaseImportRequest extends BaseFormRequest {
    public const MAX_FILES = 50;

    public const MAX_FILE_KB = 10240;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array {
        return [
            'files' => ['required', 'array', 'min:1', 'max:' . self::MAX_FILES],
            'files.*' => ['file', 'max:' . self::MAX_FILE_KB, 'extensions:pdf', 'mimes:pdf'],
        ];
    }

    /**
     * @return list<UploadedFile>
     */
    public function uploads(): array {
        $files = $this->file('files');
        $list = [];
        foreach (is_array($files) ? $files : [$files] as $file) {
            if ($file instanceof UploadedFile) {
                $list[] = $file;
            }
        }

        return $list;
    }
}
