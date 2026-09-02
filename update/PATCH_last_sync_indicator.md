# Patch: Indikator "Data Terakhir Disinkronkan" di Laporan Laba Rugi

Perubahan kecil di 2 file yang SUDAH ADA di project Anda. Cukup tambahkan
potongan berikut, tidak perlu ganti file secara keseluruhan.

---

## 1. `app/Http/Controllers/ProfitLossController.php`

Tambahkan `use App\Models\JournalHeader;` di bagian atas (kalau belum ada),
lalu tambahkan satu baris **sebelum** `$company = \App\Models\CompanyProfile::first();`
di method `index()`:

```php
use App\Models\JournalHeader; // tambahkan di bagian use statement paling atas file

// ... di dalam method index(), sebelum baris "$company = ..." ...

$lastSync = JournalHeader::max('created_at');

$company = \App\Models\CompanyProfile::first();
```

Lalu tambahkan `'lastSync'` ke KEDUA pemanggilan `compact()` di method yang sama:

```php
// Cabang 'bulanan'
$view = view('report.profit-loss', compact('tab', 'report', 'year', 'interval', 'month', 'periods', 'isExport', 'company', 'lastSync'));

// Cabang 'periode'
$view = view('report.profit-loss', compact('tab', 'report', 'startDate', 'endDate', 'isExport', 'company', 'lastSync'));
```

---

## 2. `resources/views/report/profit-loss.blade.php`

Tambahkan badge ini tepat **di bawah** baris periode, di KEDUA blok header
(blok `@if($tab == 'bulanan')` dan blok `@elseif($tab == 'periode')`) —
cari baris:

```blade
<p style="margin: 0; color: #64748b; font-size: 0.85rem; font-weight: 500;">Periode: ...</p>
```

Tepat setelah tag `</p>` itu (masih di dalam div yang sama), tambahkan:

```blade
@if(isset($lastSync) && $lastSync)
    @php
        $syncTime = \Carbon\Carbon::parse($lastSync);
        $hoursAgo = $syncTime->diffInHours(now());
        $syncColor = $hoursAgo <= 24 ? '#16a34a' : ($hoursAgo <= 72 ? '#d97706' : '#dc2626');
        $syncBg    = $hoursAgo <= 24 ? '#f0fdf4' : ($hoursAgo <= 72 ? '#fffbeb' : '#fef2f2');
    @endphp
    <p style="margin: 6px 0 0 0; font-size: 0.75rem;">
        <span style="background: {{ $syncBg }}; color: {{ $syncColor }}; padding: 2px 10px; border-radius: 12px; font-weight: 600;">
            ● Data terakhir disinkronkan: {{ $syncTime->translatedFormat('d M Y H:i') }}
            ({{ $syncTime->diffForHumans() }})
        </span>
    </p>
@endif
```

**Kenapa ini penting:** ini persis kasus kemarin — laporan dicetak jam 16:28,
padahal sync data terbaru baru jalan jam 03:18 dini hari berikutnya. Dengan
badge ini, begitu laporan dibuka/dicetak, langsung kelihatan apakah datanya
masih dalam rentang wajar (hijau, ≤24 jam), mulai perlu dicek (kuning, 1-3
hari), atau jelas basi (merah, >3 hari) — sebelum laporan itu dikirim ke
siapa pun.

---

## Catatan

- `JournalHeader::max('created_at')` mengambil waktu insert baris jurnal
  TERBARU di seluruh ledger (bukan per akun) — cukup untuk indikator umum
  "kapan terakhir ada aktivitas sync". Kalau nanti Anda ingin granularitas
  per sumber (mis. terakhir sync dari webhook vs dari CSV manual), itu
  perlu kolom tambahan (`sync_source`) di `journal_headers` — bisa kita
  bahas terpisah kalau dibutuhkan.
- Threshold 24/72 jam di atas cuma default masuk akal untuk siklus sync
  harian — sesuaikan angkanya kalau ritme sync Anda beda (mis. real-time
  webhook seharusnya threshold-nya jauh lebih ketat, dalam hitungan jam
  bukan hari).
