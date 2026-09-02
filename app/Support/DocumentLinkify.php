<?php

namespace App\Support;

class DocumentLinkify
{
    // Daftar lengkap prefix transaksi ERP (sesuai konfirmasi terakhir)
    protected static string $pattern = '/\b(GJ|INV|BIL|ADJ|SR|PR|REFF|RET|KSY|CP|SP|AR|AP|BR|CD|DP|PO|SO|POS)-[A-Za-z0-9.\-]+/i';

    /**
     * Ubah referensi dokumen (INV-xxx, BIL-xxx, dll) di dalam teks bebas
     * menjadi link yang mengarah ke DocumentTraceController::trace().
     * Teks di-escape dulu (aman XSS), baru substring yang match dibungkus <a>.
     */
    public static function render(?string $text): string
    {
        if (empty($text)) {
            return '';
        }

        $escaped = e($text);

        return preg_replace_callback(self::$pattern, function ($matches) {
            $ref = $matches[0];
            $url = route('trace.document', $ref);

            return '<a href="' . e($url) . '" '
                 . 'class="text-decoration-none fw-bold text-primary" '
                 . 'title="Klik untuk melihat detail dokumen ' . e($ref) . '">'
                 . '<i class="fa-solid fa-arrow-up-right-from-square fa-xs me-1"></i>'
                 . e($ref)
                 . '</a>';
        }, $escaped);
    }
}
