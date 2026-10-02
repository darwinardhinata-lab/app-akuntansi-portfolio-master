<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class PurchaseFinanceNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function renderNavigation(string $routeName, array $query = []): \DOMXPath
    {
        $request = Request::create(route($routeName, $query));
        $route = clone Route::getRoutes()->getByName($routeName);
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);
        $this->app->instance('request', $request);
        View::share('errors', new ViewErrorBag);

        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8">'.View::make('layouts.app')->render());
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return new \DOMXPath($document);
    }

    public function test_existing_links_are_mapped_to_purchase_and_finance_in_all_locales(): void
    {
        config(['platform.order_company_scope_enabled' => true]);
        foreach (['id', 'en', 'zh_CN'] as $locale) {
            app()->setLocale($locale);
            $xpath = $this->renderNavigation('dashboard');

            foreach (['po.index', 'grn.index', 'purchase-returns.index'] as $name) {
                $this->assertTrue(Route::has($name));
                $this->assertSame(1, $xpath->query('//*[@id="sectionOutflow"]//a[@href="'.route($name).'"]')->length);
            }

            foreach (['purchase-bills.index', 'payment.index', 'reports.ap_dp', 'reports.ap_subledger', 'jurnal.index', 'buku-besar.index', 'aset.index', 'aset.list'] as $name) {
                $this->assertTrue(Route::has($name));
                $this->assertSame(1, $xpath->query('//*[@id="sectionAkuntansi"]//a[@href="'.route($name).'"]')->length);
                $this->assertSame(0, $xpath->query('//*[@id="sectionOutflow"]//a[@href="'.route($name).'"]')->length);
            }

            foreach (['purchase_navigation', 'purchase_receipt_navigation', 'finance_navigation', 'journal_navigation', 'ap_navigation'] as $key) {
                $this->assertTrue(app('translator')->hasForLocale('erp.'.$key, $locale));
            }
            $this->assertStringContainsString(__('erp.purchase_navigation'), $xpath->query('//a[@href="#sectionOutflow"]')->item(0)->textContent);
            $this->assertStringContainsString(__('erp.finance_navigation'), $xpath->query('//a[@href="#sectionAkuntansi"]')->item(0)->textContent);
            $this->assertSame(1, $xpath->query('//*[@id="menuApHutang"]')->length);
        }
    }

    public function test_each_route_opens_only_its_own_purchase_or_finance_section(): void
    {
        config(['platform.order_company_scope_enabled' => true]);
        foreach (['po.index', 'grn.index', 'purchase-returns.index', 'purchase-bills.index', 'payment.index', 'reports.ap_dp', 'reports.ap_subledger', 'jurnal.index', 'buku-besar.index'] as $name) {
            $xpath = $this->renderNavigation($name);
            $purchase = in_array($name, ['po.index', 'grn.index', 'purchase-returns.index'], true);
            foreach (['sectionOutflow' => $purchase, 'sectionAkuntansi' => ! $purchase] as $id => $active) {
                $this->assertSame($active ? 'true' : 'false', $xpath->query('//a[@href="#'.$id.'"]')->item(0)->getAttribute('aria-expanded'), $name);
                $classes = explode(' ', $xpath->query('//*[@id="'.$id.'"]')->item(0)->getAttribute('class'));
                $this->assertSame($active, in_array('show', $classes, true), $name);
            }
            $this->assertSame(1, $xpath->query('//aside//a[@href="'.route($name).'" and contains(concat(" ", normalize-space(@class), " "), " active ")]')->length, $name);
        }
    }

    public function test_receipt_history_link_requires_company_ownership_but_not_new_receiving_flag(): void
    {
        foreach ([false, true] as $ownership) {
            foreach ([false, true] as $receiving) {
                config([
                    'platform.order_company_scope_enabled' => $ownership,
                    'platform.grn_enabled' => $receiving,
                ]);
                $xpath = $this->renderNavigation('dashboard');
                $this->assertSame($ownership ? 1 : 0, $xpath->query('//*[@id="sectionOutflow"]//a[@href="'.route('grn.index').'"]')->length);
                $this->assertSame(1, $xpath->query('//*[@id="sectionOutflow"]//a[@href="'.route('po.index').'"]')->length);
            }
        }
    }

    public function test_indirect_cash_flow_is_not_active_on_an_unrelated_route(): void
    {
        foreach (['dashboard' => false, 'arus-kas.index' => true] as $name => $expected) {
            $xpath = $this->renderNavigation($name, ['tab' => 'indirect']);
            $link = $xpath->query('//*[@id="menuArusKas"]//a[@href="'.route('arus-kas.index', ['tab' => 'indirect']).'"]')->item(0);
            $this->assertSame($expected, in_array('active', explode(' ', $link->getAttribute('class')), true));
        }
    }
}
