<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class LanguageController extends Controller
{
    public function switchLanguage($locale)
    {
        // Validasi agar hanya menerima kode bahasa 'id' (Indonesia) atau 'en' (Inggris)
        if (in_array($locale, ['id', 'en'])) {
            Session::put('locale', $locale);
        }
        
        return redirect()->back();
    }
}