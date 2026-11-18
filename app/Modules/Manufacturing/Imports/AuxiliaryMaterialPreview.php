<?php

namespace App\Modules\Manufacturing\Imports;

use App\Modules\Manufacturing\Models\AuxiliaryMaterial;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;

/** Read-only inspection of the existing standard ten-column template, never a commit. */
final class AuxiliaryMaterialPreview
{
    private const HEADINGS = ['HS CODE', 'KODE BAHAN', 'DESCRIPTION', 'NAMA BAHAN', 'NAMA MATERIAL INGGRIS',
        'KATEGORI', 'WARNA', 'SPESIFIKASI/DESKRIPSI', 'SATUAN', 'METER PER GULUNG'];

    private const FIELDS = ['hs_code', 'material_code', 'description', 'material_name', 'english_name',
        'category', 'color', 'specification', 'unit', 'meters_per_roll'];

    public const MAX_FILE_SIZE = 5242880; // 5 MB
    public const MAX_ROWS = 500;
    public const MAX_COLUMNS = 20;
    public const MAX_VALUE_CHARACTERS = 4096;
    public const MAX_RESULT_BYTES = 262144;
    public const MAX_ARCHIVE_BYTES = 8388608;
    public const MAX_ARCHIVE_ENTRIES = 128;
    public const MAX_XML_ELEMENTS = 100000;

    public function inspect(string $csv): array
    {
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, $csv);
        rewind($stream);
        try {
            return $this->inspectCsvStream($stream, 'csv');
        } finally {
            fclose($stream);
        }
    }

    public function inspectFile(string|UploadedFile $file, bool $createOnly = false): array
    {
        $path = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        $name = $file instanceof UploadedFile ? $file->getClientOriginalName() : basename($file);
        $size = $file instanceof UploadedFile ? $file->getSize() : filesize($path);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if ($size > self::MAX_FILE_SIZE) {
            throw ValidationException::withMessages(['file' => 'Ukuran file melebihi batas 5 MB.']);
        }

        if (! in_array($ext, ['csv', 'xlsx'], true)) {
            throw ValidationException::withMessages(['file' => 'Format preview harus .xlsx atau .csv (UTF-8, titik koma).']);
        }

        if ($ext === 'csv') {
            $stream = fopen($path, 'r');
            try {
                return $this->inspectCsvStream($stream, 'file', $createOnly);
            } finally {
                fclose($stream);
            }
        }

        $this->preflightXlsx($path);
        $reader = new XlsxReader();
        $reader->setReadDataOnly(true);
        $spreadsheet = null;
        try {
            $spreadsheet = $reader->load($path);
            $worksheet = $spreadsheet->getActiveSheet();
            if ($worksheet->getHighestRow() > self::MAX_ROWS + 6) $this->overBudget('file', 'maksimum '.self::MAX_ROWS.' record data');
            if (\PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($worksheet->getHighestColumn()) > self::MAX_COLUMNS) $this->overBudget('file', 'maksimum '.self::MAX_COLUMNS.' kolom');
            $rawRows = $worksheet->toArray(null, false, false, false);
        } catch (SpreadsheetException $exception) {
            throw ValidationException::withMessages(['file' => 'File spreadsheet tidak dapat dibaca. Gunakan file template yang valid dan tidak rusak.']);
        } finally {
            $spreadsheet?->disconnectWorksheets();
            unset($spreadsheet);
        }

        return $this->inspectRawMatrix($rawRows, 'file', $createOnly);
    }

    private function inspectCsvStream($stream, string $errorKey, bool $createOnly = false): array
    {
        // Bound allocation before fgetcsv can create an enormous field/column array.
        // Conservative raw-byte envelope permits UTF-8 and doubled CSV quotes.
        $quoted = $escaped = false;
        $columns = 1;
        $fieldBytes = $records = 0;
        while (! feof($stream)) {
            $chunk = fread($stream, 8192);
            for ($i = 0, $length = strlen($chunk); $i < $length; $i++) {
                $char = $chunk[$i];
                if ($char === '"' && ! $escaped) $quoted = ! $quoted;
                if (! $quoted && $char === ';') {
                    if (++$columns > self::MAX_COLUMNS) $this->overBudget($errorKey, 'maksimum '.self::MAX_COLUMNS.' kolom');
                    $fieldBytes = 0;
                } elseif (! $quoted && $char === "\n") {
                    if (++$records > self::MAX_ROWS + 6) $this->overBudget($errorKey, 'maksimum '.self::MAX_ROWS.' record data');
                    $columns = 1;
                    $fieldBytes = 0;
                } elseif (++$fieldBytes > self::MAX_VALUE_CHARACTERS * 8 + 2) {
                    $this->overBudget($errorKey, 'maksimum '.self::MAX_VALUE_CHARACTERS.' karakter per nilai (batas byte CSV mentah)');
                }
                $escaped = $quoted && $char === '\\' && ! $escaped;
            }
        }
        if ($quoted) throw ValidationException::withMessages([$errorKey => 'Tanda kutip CSV tidak lengkap. Gunakan CSV UTF-8 template yang valid.']);
        rewind($stream);
        $header = null;
        for ($line = 1; $line <= 6; $line++) {
            $header = fgetcsv($stream, 0, ';');
            if (is_array($header)) $this->checkValues($header, $errorKey);
        }
        if (! is_array($header) || array_slice($header, 0, 10) !== self::HEADINGS) {
            throw ValidationException::withMessages([$errorKey => 'Gunakan template standar Bahan Penolong: 10 kolom, header baris 6, data mulai baris 7. Format lain belum dipetakan.']);
        }
        $rawRows = [];
        for ($i = 0; $i < 5; $i++) {
            $rawRows[] = [];
        }
        $rawRows[] = $header;
        while (($values = fgetcsv($stream, 0, ';')) !== false) {
            if (count($rawRows) >= self::MAX_ROWS + 6) $this->overBudget($errorKey, 'maksimum '.self::MAX_ROWS.' record data (termasuk record kosong)');
            $this->checkValues($values, $errorKey);
            $rawRows[] = $values;
        }
        return $this->processRows($rawRows, $errorKey, $createOnly);
    }

    private function inspectRawMatrix(array $rawRows, string $errorKey, bool $createOnly): array
    {
        if (count($rawRows) < 6) {
            throw ValidationException::withMessages([$errorKey => 'Gunakan template standar Bahan Penolong: 10 kolom, header baris 6, data mulai baris 7. Format lain belum dipetakan.']);
        }
        $headerRow = $rawRows[5] ?? [];
        $header = array_map(fn ($v) => trim((string) $v), array_slice($headerRow, 0, 10));
        if ($header !== self::HEADINGS) {
            throw ValidationException::withMessages([$errorKey => 'Gunakan template standar Bahan Penolong: 10 kolom, header baris 6, data mulai baris 7. Format lain belum dipetakan.']);
        }
        return $this->processRows($rawRows, $errorKey, $createOnly);
    }

    private function processRows(array $rawRows, string $errorKey, bool $createOnly = false): array
    {
        $rows = [];
        $resultBytes = 2; // JSON array brackets; full results, never silently truncated.
        $totalRows = count($rawRows);
        for ($index = 6; $index < $totalRows; $index++) {
            $line = $index + 1;
            $values = $rawRows[$index];
            $this->checkValues($values, $errorKey);
            if (count(array_filter($values, fn ($value) => trim((string) $value) !== '')) === 0) continue;
            $data = array_combine(self::FIELDS, array_map(fn ($value) => trim((string) $value), array_pad(array_slice($values, 0, 10), 10, '')));
            $validator = Validator::make($data, [
                'material_code' => 'required|string|max:50', 'hs_code' => 'nullable|string|max:50',
                'description' => 'nullable|string|max:255', 'material_name' => 'required|string|max:255',
                'english_name' => 'nullable|string|max:255', 'category' => 'nullable|string|max:100',
                'color' => 'nullable|string|max:100', 'specification' => 'nullable|string',
                'unit' => 'required|string|max:20', 'meters_per_roll' => 'nullable|numeric|min:0',
            ]);
            $errors = $validator->errors()->all();
            if ($createOnly) {
                if (($values[8] ?? null) !== AuxiliaryMaterialCreateImport::UNIT) {
                    $literal = json_encode(mb_strimwidth((string) ($values[8] ?? ''), 0, 80, '…'), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                    $errors[] = "Unit sumber {$literal} belum didukung; gunakan PCS persis.";
                }
                if (isset($values[1]) && ! is_string($values[1])) {
                    $errors[] = 'Kode pada XLSX harus berupa teks agar leading zero tetap utuh.';
                }
                $meters = $values[9] ?? '';
                if ($meters !== '' && (! is_string($meters) || ! AuxiliaryMaterialCreateImport::exactMeters($meters))) {
                    $errors[] = 'Meter per gulung harus berupa teks desimal nonnegatif yang tepat untuk DECIMAL(20,2); tidak dibulatkan.';
                }
            }
            if (count($values) !== 10) $errors[] = 'Jumlah kolom harus 10; baris ini belum dapat dipetakan lengkap.';
            $unmapped = [];
            foreach (array_slice($values, 10, null, true) as $colIdx => $value) $unmapped['column_'.($colIdx + 1)] = $value;
            $rows[] = ['row' => $line, 'data' => $data, 'errors' => $errors, 'unmapped' => $unmapped,
                'existing_duplicate' => false, 'file_duplicate' => false];
            $resultBytes += strlen(json_encode($rows[array_key_last($rows)], JSON_THROW_ON_ERROR)) + (count($rows) > 1 ? 1 : 0);
            if ($resultBytes > self::MAX_RESULT_BYTES) $this->overBudget($errorKey, 'hasil preview maksimum '.self::MAX_RESULT_BYTES.' byte JSON sebelum penandaan duplikat');
        }

        $counts = array_count_values(array_column(array_column($rows, 'data'), 'material_code'));
        // Bounded query batches avoid driver parameter limits; no updateOrCreate/importer invocation.
        $existing = [];
        foreach (array_chunk(array_keys($counts), 500) as $codes) {
            foreach (AuxiliaryMaterial::whereIn('material_code', $codes)->pluck('material_code') as $code) $existing[(string) $code] = true;
        }
        foreach ($rows as &$row) {
            $code = $row['data']['material_code'];
            $row['existing_duplicate'] = isset($existing[$code]);
            $row['file_duplicate'] = $counts[$code] > 1;
        }
        unset($row);
        return $rows;
    }

    private function overBudget(string $key, string $limit): never
    {
        throw ValidationException::withMessages([$key => 'Batas resource preview: '.$limit.'. Bagi file menjadi bagian lebih kecil atau kurangi kolom/teks. Seluruh file ditolak, tidak ada data disimpan.']);
    }

    private function checkValues(array $values, string $key): void
    {
        if (count($values) > self::MAX_COLUMNS) $this->overBudget($key, 'maksimum '.self::MAX_COLUMNS.' kolom');
        foreach ($values as $value) {
            $text = (string) $value;
            if (! mb_check_encoding($text, 'UTF-8')) throw ValidationException::withMessages([$key => 'Gunakan teks UTF-8 yang valid.']);
            if (mb_strlen($text, 'UTF-8') > self::MAX_VALUE_CHARACTERS) $this->overBudget($key, 'maksimum '.self::MAX_VALUE_CHARACTERS.' karakter per nilai');
        }
    }

    /** Inspect central directory and XML incrementally BEFORE PhpSpreadsheet creates cells. */
    private function preflightXlsx(string $path): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) throw ValidationException::withMessages(['file' => 'File XLSX tidak dapat dibaca. Gunakan workbook template yang valid.']);
        try {
            if ($zip->numFiles > self::MAX_ARCHIVE_ENTRIES) $this->overBudget('file', 'maksimum '.self::MAX_ARCHIVE_ENTRIES.' entri arsip XLSX');
            $bytes = 0;
            $names = [];
            $sheets = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $bytes += $stat['size'];
                if ($bytes > self::MAX_ARCHIVE_BYTES) $this->overBudget('file', 'isi arsip XLSX maksimum '.self::MAX_ARCHIVE_BYTES.' byte setelah ekstraksi');
                $name = $stat['name'];
                if (isset($names[$name])) throw ValidationException::withMessages(['file' => 'Entri XLSX ganda tidak didukung.']);
                $names[$name] = true;
                if (preg_match('~^xl/worksheets/[^/]+\.xml$~', $name)) $sheets[] = $name;
            }
            if (count($sheets) !== 1) throw ValidationException::withMessages(['file' => 'Gunakan XLSX template dengan tepat satu worksheet.']);
            // Inspect every XML part: reject DTD, oversized text and excessive structure.
            foreach (array_keys($names) as $name) {
                if (! str_ends_with($name, '.xml') && ! str_ends_with($name, '.rels')) continue;
                $xml = new \XMLReader();
                $previous = libxml_use_internal_errors(true);
                try {
                    if (! $xml->open('zip://'.$path.'#'.$name, null, LIBXML_NONET)) throw new \RuntimeException('Invalid XML');
                    $nodes = $cells = $records = 0;
                    $sheet = in_array($name, $sheets, true);
                    while ($xml->read()) {
                        if ($xml->nodeType === \XMLReader::DOC_TYPE) throw new \RuntimeException('DTD not allowed');
                        if ($xml->nodeType !== \XMLReader::ELEMENT) continue;
                        if (++$nodes > self::MAX_XML_ELEMENTS) $this->overBudget('file', 'maksimum '.self::MAX_XML_ELEMENTS.' elemen XML per bagian XLSX');
                        if (in_array($xml->localName, ['t', 'v', 'f'], true)) $this->checkValues([$xml->readString()], 'file');
                        if ($sheet && $xml->localName === 'row' && ++$records > self::MAX_ROWS + 6) $this->overBudget('file', 'maksimum '.self::MAX_ROWS.' record data');
                        if ($sheet && $xml->localName === 'c' && ++$cells > (self::MAX_ROWS + 6) * self::MAX_COLUMNS) $this->overBudget('file', 'jumlah sel XLSX maksimum '.((self::MAX_ROWS + 6) * self::MAX_COLUMNS));
                        if ($sheet && in_array($xml->localName, ['dimension', 'c', 'row'], true)) {
                            $reference = $xml->getAttribute($xml->localName === 'dimension' ? 'ref' : 'r');
                            if ($reference !== null) foreach (explode(':', str_replace('$', '', $reference)) as $coordinate) {
                                if (! preg_match('/^([A-Z]*)([1-9][0-9]*)$/', $coordinate, $match)) throw new \RuntimeException('Invalid coordinate');
                                $column = $match[1] === '' ? 1 : \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($match[1]);
                                if ((int) $match[2] > self::MAX_ROWS + 6) $this->overBudget('file', 'baris worksheet maksimum '.(self::MAX_ROWS + 6).' ('.self::MAX_ROWS.' data + 6 template)');
                                if ($column > self::MAX_COLUMNS) $this->overBudget('file', 'maksimum '.self::MAX_COLUMNS.' kolom');
                            }
                        }
                    }
                    if (libxml_get_errors()) throw new \RuntimeException('Invalid XML');
                } catch (\RuntimeException $e) {
                    throw ValidationException::withMessages(['file' => 'Struktur XLSX tidak valid atau tidak didukung. Gunakan template standar yang tidak rusak.']);
                } finally {
                    $xml->close();
                    libxml_clear_errors();
                    libxml_use_internal_errors($previous);
                }
            }
        } finally {
            $zip->close();
        }
    }
}
