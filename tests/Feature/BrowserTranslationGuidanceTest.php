<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class BrowserTranslationGuidanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_layout_uses_native_language_links_without_external_translation_for_every_menu(): void
    {
        View::share('errors', new ViewErrorBag);

        foreach (['id', 'en', 'zh_CN'] as $locale) {
            app()->setLocale($locale);

            $html = View::make('layouts.app')->render();

            foreach (['id', 'en', 'zh_CN'] as $target) {
                $this->assertStringContainsString('href="'.route('lang.switch', $target).'"', $html);
            }

            $this->assertStringContainsString('aria-current="true"', $html);
            $this->assertStringContainsString('id="languageDropdown"', $html);
            $this->assertStringContainsString('aria-labelledby="languageDropdown"', $html);
            $this->assertStringContainsString('aria-label="'.e(__('erp.select_language')).'"', $html);
            $this->assertSame(1, substr_count($html, 'id="languageDropdown"'));

            foreach (['id' => 'Indonesia', 'en' => 'English', 'zh_CN' => '简体中文'] as $target => $name) {
                $this->assertStringContainsString('<span>'.$name.'</span>', $html);
                $this->assertSame(1, substr_count($html, 'href="'.route('lang.switch', $target).'"'));
                $this->assertStringContainsString(
                    'class="dropdown-item '.($locale === $target ? 'active' : '').'" lang="'.str_replace('_', '-', $target).'"',
                    $html
                );
            }

            $this->assertStringNotContainsString('translate.google.com/translate_a/element.js', $html);
            $this->assertStringNotContainsString('google-translate-language', $html);

            foreach (['general_journal', 'coa', 'helper_code', 'tax_master', 'payment_category',
                'budgeting', 'purchase_bill', 'payment_plan', 'fixed_asset', 'general_ledger',
                'profit_loss', 'balance_sheet', 'cash_flow'] as $key) {
                $label = __('erp.'.$key);
                $this->assertNotSame('erp.'.$key, $label);
                $this->assertStringContainsString(e($label), $html);
            }
        }
    }

    public function test_language_switch_persists_and_translates_the_journal_on_the_next_request(): void
    {
        $this->withoutMiddleware(\Illuminate\Auth\Middleware\Authenticate::class);

        foreach (['en', 'zh_CN', 'id'] as $locale) {
            $this->from(route('jurnal.index'))
                ->get(route('lang.switch', $locale))
                ->assertRedirect(route('jurnal.index'))
                ->assertSessionHas('locale', $locale);

            $response = $this->get(route('jurnal.index'))->assertOk();

            $this->assertSame($locale, app()->getLocale());
            $response->assertSee(__('erp.general_journal'));
            $response->assertSee(__('erp.journal_desc'));
            $response->assertDontSee("title: __('erp.alert_sync_started')", false);
        }
    }

    public function test_unsupported_locale_does_not_overwrite_the_selected_language(): void
    {
        $this->withoutMiddleware(\Illuminate\Auth\Middleware\Authenticate::class);

        $this->withSession(['locale' => 'en'])
            ->from(route('jurnal.index'))
            ->get(route('lang.switch', 'unsupported'))
            ->assertRedirect(route('jurnal.index'))
            ->assertSessionHas('locale', 'en');
    }
}
