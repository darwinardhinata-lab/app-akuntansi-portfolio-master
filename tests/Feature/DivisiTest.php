<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;

class DivisiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * REGRESI: Divisi yang dibuat manual lewat form harus tersimpan
     * (dengan kode_divisi + status_aktif) dan muncul di halaman list.
     */
    public function test_manual_created_divisi_is_saved_and_appears_in_list()
    {
        // Bypass auth middleware for tests
        $this->withoutMiddleware();

        // Simulasi submit form "Buat Manual" (kode diisi, status aktif tercentang)
        $response = $this->post(route('divisi.store'), [
            'kode_divisi'  => 'PRD',
            'nama_divisi'  => 'Produksi',
            'status_aktif' => '1',
        ]);

        // Harus sukses dan redirect ke halaman list dengan pesan sukses
        $response->assertRedirect(route('divisi.index'));
        $response->assertSessionHas('success');

        // Data harus tersimpan aktif (status_aktif = 1) agar muncul di dropdown Payment Plan
        $this->assertDatabaseHas('master_divisi', [
            'kode_divisi'  => 'PRD',
            'nama_divisi'  => 'PRODUKSI',
            'status_aktif' => 1,
        ]);

        // Divisi baru harus tampil di halaman list
        $this->get(route('divisi.index'))
            ->assertOk()
            ->assertSee('PRODUKSI')
            ->assertSee('PRD');
    }

    /**
     * REGRESI: Submit tanpa kode_divisi harus DITOLAK dengan pesan error
     * yang terlihat (tidak lagi gagal secara diam-diam).
     */
    public function test_store_without_kode_divisi_is_rejected_with_visible_error()
    {
        $this->withoutMiddleware();

        $response = $this->from(route('divisi.create'))
            ->post(route('divisi.store'), [
                'nama_divisi' => 'TANPA KODE',
            ]);

        $response->assertRedirect(route('divisi.create'));
        $response->assertSessionHasErrors(['kode_divisi']);

        // Tidak boleh ada baris yang tersimpan
        $this->assertDatabaseMissing('master_divisi', [
            'nama_divisi' => 'TANPA KODE',
        ]);
    }

    /**
     * REGRESI: Edit divisi tanpa mengubah kode harus berhasil
     * (unique rule mengabaikan baris yang sedang diedit).
     */
    public function test_update_divisi_without_changing_kode_succeeds()
    {
        $this->withoutMiddleware();

        $id = DB::table('master_divisi')->insertGetId([
            'kode_divisi'  => 'HR',
            'nama_divisi'  => 'HR',
            'status_aktif' => 1,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $response = $this->put(route('divisi.update', $id), [
            'kode_divisi' => 'HR',
            'nama_divisi' => 'Human Resource',
        ]);

        $response->assertRedirect(route('divisi.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('master_divisi', [
            'id_divisi'   => $id,
            'kode_divisi' => 'HR',
            'nama_divisi' => 'HUMAN RESOURCE',
        ]);
    }

    /**
     * REGRESI: Update tidak boleh memakai kode_divisi milik divisi lain.
     */
    public function test_update_with_duplicate_kode_is_rejected()
    {
        $this->withoutMiddleware();

        DB::table('master_divisi')->insert([
            ['kode_divisi' => 'FIN', 'nama_divisi' => 'FINANCE', 'status_aktif' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['kode_divisi' => 'GDG', 'nama_divisi' => 'GUDANG', 'status_aktif' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $gdgId = DB::table('master_divisi')->where('kode_divisi', 'GDG')->value('id_divisi');

        $response = $this->from(route('divisi.edit', $gdgId))
            ->put(route('divisi.update', $gdgId), [
                'kode_divisi' => 'FIN', // sudah dipakai divisi lain
                'nama_divisi' => 'GUDANG UTAMA',
            ]);

        $response->assertRedirect(route('divisi.edit', $gdgId));
        $response->assertSessionHasErrors(['kode_divisi']);

        $this->assertDatabaseHas('master_divisi', [
            'id_divisi'   => $gdgId,
            'kode_divisi' => 'GDG',
        ]);
    }
}