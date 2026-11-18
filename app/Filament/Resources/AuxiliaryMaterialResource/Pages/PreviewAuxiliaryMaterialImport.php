<?php

namespace App\Filament\Resources\AuxiliaryMaterialResource\Pages;

use App\Filament\Resources\AuxiliaryMaterialResource;
use App\Modules\Manufacturing\Imports\AuxiliaryMaterialCreateImport;
use App\Modules\Manufacturing\Imports\AuxiliaryMaterialPreview;
use App\Modules\Platform\Support\CompanyContext;
use App\Support\ErpPanelAccess;
use Filament\Resources\Pages\Page;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

final class PreviewAuxiliaryMaterialImport extends Page
{
    use WithFileUploads { _startUpload as private startTemporaryUpload; }

    protected static string $resource = AuxiliaryMaterialResource::class;
    protected string $view = 'filament.resources.auxiliary-materials.preview-import';

    public string $csv = '';
    public $file = null;
    #[Locked] public array $rows = [];
    #[Locked] public bool $inspected = false;
    #[Locked] public string $sourceType = '';
    #[Locked] public ?string $previewBinding = null;
    #[Locked] public int $saved = 0;

    public function getTitle(): string { return 'Import Bahan Penolong'; }

    public function mount(): void { abort_unless(AuxiliaryMaterialResource::canViewAny(), 403); }
    public function hydrate(): void { abort_unless(AuxiliaryMaterialResource::canViewAny(), 403); }

    public function updatedFile(): void
    {
        $this->rows = [];
        $this->inspected = false;
        $this->sourceType = '';
        $this->previewBinding = null;
        $this->saved = 0;
        $this->resetValidation();
    }

    public function _startUpload($name, $fileInfo, $isMultiple)
    {
        abort_unless(AuxiliaryMaterialResource::canViewAny(), 403);
        $this->updatedFile();
        $this->file = null;
        return $this->startTemporaryUpload($name, $fileInfo, $isMultiple);
    }

    public function preview(): void
    {
        abort_unless(AuxiliaryMaterialResource::canViewAny(), 403);
        $this->updatedFile();

        if ($this->file) {
            $this->validateFile();
            $binding = $this->currentBinding();
            $this->rows = app(AuxiliaryMaterialCreateImport::class)->inspect($this->file);
            $this->previewBinding = $binding;
            $this->sourceType = 'file';
            $this->inspected = true;
            return;
        }

        // Text input fallback
        $this->validate(['csv' => 'required|string|max:1048576']);
        $this->rows = app(AuxiliaryMaterialPreview::class)->inspect($this->csv);
        $this->sourceType = 'csv';
        $this->inspected = true;
    }

    public function canSave(): bool
    {
        return $this->previewBinding !== null && AuxiliaryMaterialCreateImport::ready($this->rows)
            && app(ErpPanelAccess::class)->allows(request(), auth()->user(), 'material.create');
    }

    public function save(): void
    {
        abort_unless(AuxiliaryMaterialResource::canViewAny()
            && app(ErpPanelAccess::class)->allows(request(), auth()->user(), 'material.create'), 403,
            'Izin material.create diperlukan untuk menyimpan import.');
        $this->resetValidation();
        if ($this->previewBinding === null) {
            throw ValidationException::withMessages(['save' => 'Unggah dan preview file sebelum menyimpan.']);
        }
        $this->validateFile();
        if (! hash_equals($this->previewBinding, $this->currentBinding())) {
            throw ValidationException::withMessages(['save' => 'File atau scope berubah. Unggah dan preview ulang.']);
        }
        try {
            $count = app(AuxiliaryMaterialCreateImport::class)->save($this->file);
        } catch (QueryException $e) {
            report($e);
            throw ValidationException::withMessages(['save' => 'Penyimpanan gagal. Seluruh batch dibatalkan; preview ulang atau hubungi administrator.']);
        }
        $this->updatedFile();
        $this->file = null;
        $this->saved = $count;
    }

    private function validateFile(): void
    {
        if (! $this->file instanceof TemporaryUploadedFile || ! $this->file->exists()) {
            throw ValidationException::withMessages(['file' => 'File unggahan tidak tersedia. Unggah ulang.']);
        }
        $this->validate(['file' => 'required|file|max:5120|mimes:xlsx,csv,txt|extensions:xlsx,csv']);
    }

    private function currentBinding(): string
    {
        $company = app(CompanyContext::class)->requireCompany(request());
        return hash('sha256', json_encode([auth()->id(), $company->getKey(), request()->session()->getId(),
            $this->file->getFilename(), $this->file->getClientOriginalName(), hash_file('sha256', $this->file->getRealPath())], JSON_THROW_ON_ERROR));
    }
}
