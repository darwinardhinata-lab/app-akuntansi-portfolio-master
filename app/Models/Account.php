<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\AccountTranslation;
use App\Models\CoaTypeTranslation;

class Account extends Model
{
    // Pengaturan Primary Key
    protected $primaryKey = 'account_code';
    public $incrementing = false;
    protected $keyType = 'string';

    // Sesuaikan dengan kolom aktual di database
    protected $fillable = [
        'account_code',
        'account_name',
        'coa_type',       // SEBELUMNYA: type
        'normal_balance', 
        'report_pos'      // SEBELUMNYA: report_type
    ];

    /**
     * Relasi ke detail jurnal.
     */
    public function journalDetails(): HasMany
    {
        return $this->hasMany(JournalDetail::class, 'account_code', 'account_code');
    }

    /**
     * Relasi ke override translation untuk account_name (locale non-default).
     */
    public function translations(): HasMany
    {
        return $this->hasMany(AccountTranslation::class, 'account_code', 'account_code');
    }

    /**
     * Nama akun sesuai locale aktif (atau locale yang diminta).
     * Locale 'id' (default) selalu ambil dari kolom account_name asli,
     * TIDAK query ke account_translations -- supaya tidak ada perubahan
     * perilaku sama sekali untuk locale default.
     */
    public function translatedName(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();

        if ($locale === 'id') {
            return $this->account_name;
        }

        $override = $this->relationLoaded('translations')
            ? $this->translations->firstWhere('locale', $locale)
            : $this->translations()->where('locale', $locale)->first();

        return $override?->name ?? $this->account_name;
    }

    /**
     * Label coa_type sesuai locale aktif, dari kamus coa_type_translations.
     * Kolom coa_type ASLI tidak pernah diubah -- WHERE coa_type = ... di
     * AccountController/AccountExport tetap aman, ini murni untuk tampilan.
     */
    public function translatedCoaType(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();

        if ($locale === 'id' || empty($this->coa_type)) {
            return $this->coa_type;
        }

        return CoaTypeTranslation::labelFor($this->coa_type, $locale) ?? $this->coa_type;
    }

    /**
     * Label Pos Saldo (DEBET/KREDIT) sesuai locale aktif.
     * Pakai lang key yang SUDAH ADA di lang/{locale}/erp.php (debit_caps,
     * credit_caps) -- tidak perlu tabel/migration baru untuk kolom ini.
     */
    public function translatedNormalBalance(): string
    {
        return $this->normal_balance === 'KREDIT'
            ? __('erp.credit_caps')
            : __('erp.debit_caps');
    }

    /**
     * Label Pos Laporan (NERACA/LABA RUGI) sesuai locale aktif.
     * Sama seperti di atas, reuse lang key existing (balance_sheet_caps,
     * profit_loss_caps).
     */
    public function translatedReportPos(): string
    {
        return $this->report_pos === 'LABA RUGI'
            ? __('erp.profit_loss_caps')
            : __('erp.balance_sheet_caps');
    }
}