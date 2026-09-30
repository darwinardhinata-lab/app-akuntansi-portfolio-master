<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class BrowserTranslationGuidanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_layout_includes_google_website_translator_controls_for_each_supported_locale(): void
    {
        View::share('errors', new ViewErrorBag);

        foreach (['id', 'en', 'zh_CN'] as $locale) {
            app()->setLocale($locale);

            $html = View::make('layouts.app')->render();

            $this->assertStringContainsString('google_translate_element', $html);
            $this->assertStringContainsString('google-translate-language', $html);
            $this->assertStringContainsString('data-google-language="en"', $html);
            $this->assertStringContainsString('data-google-language="zh-CN"', $html);
            $this->assertStringContainsString('translate.google.com/translate_a/element.js', $html);
            $this->assertStringContainsString("document.cookie = 'googtrans='", $html);
        }
    }
}
