# GAPS TO CONFIRM — CEISA H2H Fase 2 (Signing & Client Sungguhan)

> Dibuat otomatis oleh Cline pada Fase 2. **Semua item di bawah WAJIB dikonfirmasi ke
> dokumentasi resmi DJBC / Postman collection CEISA 4.0 sebelum modul ini dipakai
> untuk request sungguhan (sandbox maupun produksi).** Implementasi saat ini
> sengaja TIDAK menebak di luar yang tercantum di sini.

| # | Gap | Lokasi di kode | Asumsi sementara yang dipakai | Yang perlu dikonfirmasi |
|---|-----|----------------|-------------------------------|-------------------------|
| 1 | **Format timestamp** pada string-to-sign | `CeisaSignatureService::signHmac()` | Epoch detik (`now()->timestamp`), mis. `1726646400` | Epoch detik, milidetik, atau ISO 8601? Zona waktu WIB atau UTC? |
| 2 | **Nama header selain `beacukai-signature`** | `config/customs.php` → `signing.*_header` | `client-id`, `timestamp`, `Authorization: Bearer {token}` | Nama header resmi untuk client id, timestamp, dan access token. Apakah `Authorization` dipakai, atau ada header khusus (mis. `access-token`)? |
| 3 | **Alur mendapatkan Access Token** | `config/customs.php` → `signing.access_token` (diisi manual via `.env`) | Token diisi manual, TIDAK di-refresh otomatis | Endpoint & grant type resmi (client credentials?), format request/response, masa berlaku token, dan apakah perlu job refresh otomatis (di luar scope Fase 2). |
| 4 | **Modul mana yang butuh RSA/X.509** | `CeisaSignatureService::signAsymmetric()` — sengaja `throw new RuntimeException` | Tidak ada — stub gagal cepat | Modul/endpoint mana (PIB? PEB? keduanya?) yang wajib tanda tangan asimetris, format sertifikat, dan cara menghitung string-to-sign untuk RSA. |
| 5 | **Path endpoint PIB/PEB** | `config/customs.php` → `endpoints.paths` | `/ceisa/v1/pib`, `/ceisa/v1/peb` (dari contoh spesifikasi user) | Path resmi per-layanan di sandbox (POST submit). |
| 6 | **Path GET cek status** | `CeisaH2HClient::checkStatus()` | `{path}/{nomor_aju}` — asumsi pola REST umum | Format resmi path GET untuk cek status + parameter query bila ada. |
| 7 | **Mapping status CEISA → status lokal** | `SubmitCustomsDocumentJob::handle()` | 2xx → `SUBMIT_SUCCESS`, selain itu `SUBMIT_FAILED`; `nomor_aju`/`nomor_pendaftaran` dibaca dari body response bila ada | Struktur body response submit/cek status resmi (nama field status, nomor AJU, nomor pendaftaran/SPPB), daftar nilai status yang mungkin, dan pemetaannya ke status lokal (`SUBMITTED`, `UNDER_REVIEW`, `SPPB_ISSUED`, `REJECTED`, dst.). |
| 8 | **Base URL produksi** | `config/customs.php` → `endpoints.production` | Kosong (wajib diisi via `.env` bila perlu) | Host produksi resmi (sandbox terisi: `https://apis-sandbox.beacukai.go.id`). |
| 9 | **Perilaku retry** | `config/customs.php` → `retry` | 3x dengan backoff 10/60/300 detik | Apakah CEISA menyetujui retry otomatis pada 5xx/timeout, atau semua kegagalan harus dikonfirmasi manual (hindari double-submission PIB/PEB)? |
| 10 | **`.env` lokal `CEISA_SANDBOX_BASE_URL=https://sandbox-ceisa.customs.go.id`** | `.env` baris ~53 (TIDAK diubah oleh Fase 2) | Domain tebakan lama sebelum spesifikasi resmi; TIDAK resolve (diverifikasi via cURL error 6) | Ganti manual ke `https://apis-sandbox.beacukai.go.id` (atau hapus barisnya agar pakai default config). Sengaja tidak diubah otomatis karena `.env` adalah file lokal user. |

## Catatan infrastruktur test (Fase 2)
- `phpunit.xml` kini men-set `CEISA_ENABLED=true` **hanya untuk lingkungan test**:
  registrasi route modul (`routes/customs.php`) dievaluasi saat boot dari env ini,
  jadi feature test butuh route terdaftar. Perilaku runtime tiap test tetap
  dikontrol eksplisit lewat `config(['customs.enabled' => ...])`.
  Default di `config/customs.php`, `.env`, dan `.env.example` **tetap `false`**.
- ⚠️ **Temuan kebersihan repo (di luar scope, perlu keputusan manusia):** file `.env`
  ternyata **ter-track di git** (muncul sebagai `M .env` di `git status`), padahal
  konvensi seharusnya di-gitignore. Selama kredensial asli CEISA belum diisi ini
  tidak fatal, tapi **sebelum uji sandbox sungguhan dengan kredensial asli, `.env`
  sebaiknya dikeluarkan dari tracking** (`git rm --cached .env` + tambah ke
  `.gitignore`) supaya secret tidak pernah ter-commit.
- Minor: `.env.example` punya `CEISA_RETRY_TIMES`, tapi `config/customs.php` masih
  hardcode `retry.times = 3` (belum membaca env) — cleanup kecil untuk Fase berikutnya.

## Catatan keamanan
- `config('customs.enabled')` default **false** di semua file; kredensial asli hanya boleh
  di `.env` (gitignored). Tidak ada kredensial yang di-commit.
- Uji coba sungguhan ke sandbox dilakukan **manual oleh manusia**, bukan oleh agent.

## Status implementasi terkait gap
- Semua test memakai `Http::fake()` — tidak ada satu pun test yang mengirim request nyata.
- `test_asymmetric_signing_throws_not_implemented` mengunci perilaku stub RSA agar gagal
  cepat sampai gap #4 diselesaikan.
