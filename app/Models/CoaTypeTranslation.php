<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoaTypeTranslation extends Model
{
    protected $fillable = [
        'coa_type',
        'locale',
        'label',
        'is_auto_translated',
        'translated_at',
    ];

    protected $casts = [
        'is_auto_translated' => 'boolean',
        'translated_at' => 'datetime',
    ];

    /**
     * Cache in-memory sederhana per-request supaya listing akun (bisa ratusan baris)
     * tidak melakukan query lookup berulang untuk coa_type yang sama.
     */
    protected static array $cache = [];

    public static function labelFor(string $coaType, string $locale): ?string
    {
        $key = $coaType . '|' . $locale;

        if (!array_key_exists($key, self::$cache)) {
            self::$cache[$key] = self::query()
                ->where('coa_type', $coaType)
                ->where('locale', $locale)
                ->value('label');
        }

        return self::$cache[$key];
    }
}
