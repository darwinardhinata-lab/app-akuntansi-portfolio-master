<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class LanguageController extends Controller
{
    public function switchLanguage($locale)
    {
        // Validasi agar hanya menerima kode bahasa 'id' (Indonesia), 'en' (Inggris), atau 'zh_CN' (Mandarin)
        if (in_array($locale, ['id', 'en', 'zh_CN'])) {
            Session::put('locale', $locale);
        }
        
        return redirect()->back();
    }
}