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

            foreach (['purchase-bills.index', 'payment.index', 'reports.ap_dp', 'reports.ap_subledger', 'reports.ar_dp', 'reports.ar_subledger', 'jurnal.index', 'buku-besar.index', 'aset.index', 'aset.list'] as $name) {
                $this->assertTrue(Route::has($name));
                $this->assertSame(1, $xpath->query('//*[@id="sectionAkuntansi"]//a[@href="'.route($name).'"]')->length);
                $this->assertSame(0, $xpath->query('//*[@id="sectionOutflow"]//a[@href="'.route($name).'"]')->length);
            }

            foreach (['purchase_navigation', 'purchase_receipt_navigation', 'finance_navigation', 'journal_navigation', 'ap_navigation', 'ar_navigation', 'ar_list_navigation'] as $key) {
                $this->assertTrue(app('translator')->hasForLocale('erp.'.$key, $locale));
            }
            $this->assertStringContainsString(__('erp.purchase_navigation'), $xpath->query('//a[@href="#sectionOutflow"]')->item(0)->textContent);
            $this->assertStringContainsString(__('erp.finance_navigation'), $xpath->query('//a[@href="#sectionAkuntansi"]')->item(0)->textContent);
            $this->assertSame(1, $xpath->query('//*[@id="menuApHutang"]')->length);
            $this->assertSame(1, $xpath->query('//*[@id="sectionAkuntansi"]//*[@id="menuArPiutang"]')->length);
            foreach (['reports.ar_dp', 'reports.ar_subledger'] as $name) {
                $this->assertSame(1, $xpath->query('//aside//a[@href="'.route($name).'"]')->length);
                $this->assertSame(0, $xpath->query('//*[@id="sectionInflow"]//a[@href="'.route($name).'"]')->length);
            }
            $this->assertStringContainsString(__('erp.ar_list_navigation'), $xpath->query('//*[@id="menuArPiutang"]//a[@href="'.route('reports.ar_subledger').'"]')->item(0)->textContent);
        }
    }

    public function test_each_route_opens_only_its_own_purchase_or_finance_section(): void
    {
        config(['platform.order_company_scope_enabled' => true]);
        foreach (['po.index', 'grn.index', 'purchase-returns.index', 'purchase-bills.index', 'payment.index', 'reports.ap_dp', 'reports.ap_subledger', 'reports.ar_dp', 'reports.ar_subledger', 'jurnal.index', 'buku-besar.index'] as $name) {
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

    public function test_ar_submenu_opens_only_for_ar_routes(): void
    {
        foreach (['dashboard', 'so.index', 'invoice.index', 'reports.ap_dp', 'reports.ap_subledger', 'reports.ar_dp', 'reports.ar_subledger'] as $name) {
            $xpath = $this->renderNavigation($name);
            $active = in_array($name, ['reports.ar_dp', 'reports.ar_subledger'], true);
            $toggle = $xpath->query('//a[@href="#menuArPiutang"]')->item(0);
            $this->assertSame($active ? 'true' : 'false', $toggle->getAttribute('aria-expanded'), $name);
            $this->assertSame($active, in_array('active', explode(' ', $toggle->getAttribute('class')), true), $name);
            $submenu = $xpath->query('//*[@id="menuArPiutang"]')->item(0);
            $this->assertSame($active, in_array('show', explode(' ', $submenu->getAttribute('class')), true), $name);
            if ($active) {
                $this->assertSame('false', $xpath->query('//a[@href="#sectionInflow"]')->item(0)->getAttribute('aria-expanded'), $name);
                $this->assertSame(0, $xpath->query('//*[@id="sectionInflow" and contains(concat(" ", normalize-space(@class), " "), " show ")]')->length, $name);
            }
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

    public function test_reorganized_modules_open_the_correct_section(): void
    {
        $this->actingAs(new \App\Models\User(['name' => 'Admin', 'role' => 'ADMIN']));
        foreach ([
            'product.index' => 'sectionMaster',
            'divisi.index' => 'sectionMaster',
            'budgeting.index' => 'sectionAkuntansi',
            'payment-category.index' => 'sectionAkuntansi',
            'company.edit' => 'sectionAdministration',
            'platform.company.edit' => 'sectionAdministration',
            'users.index' => 'sectionAdministration',
            'logs.index' => 'sectionAdministration',
            'warehouse.inbound' => 'sectionWarehouse',
            'so.index' => 'sectionInflow',
            'mfg.reports.bom-actual' => 'sectionManufaktur',
        ] as $name => $section) {
            $xpath = $this->renderNavigation($name);
            $this->assertSame(1, $xpath->query('//*[@id="'.$section.'"]//a[@href="'.route($name).'"]')->length, $name);
            $this->assertSame(1, $xpath->query('//aside//a[@href="'.route($name).'" and contains(concat(" ", normalize-space(@class), " "), " active ")]')->length, $name);
            $this->assertSame(1, $xpath->query('//aside//a[contains(concat(" ", normalize-space(@class), " "), " section-toggle ") and @aria-expanded="true"]')->length, $name);
            $this->assertSame('true', $xpath->query('//a[@href="#'.$section.'"]')->item(0)->getAttribute('aria-expanded'), $name);
        }
    }

    public function test_admin_visibility_and_section_order_are_preserved(): void
    {
        config(['customs.enabled' => false, 'customs.reports_enabled' => false]);
        foreach (['ADMIN' => 1, 'STAFF' => 0] as $role => $expected) {
            $this->actingAs(new \App\Models\User(['name' => 'User', 'role' => $role]));
            $xpath = $this->renderNavigation('dashboard');
            foreach (['users.index', 'logs.index'] as $name) {
                $this->assertSame($expected, $xpath->query('//*[@id="sectionAdministration"]//a[@href="'.route($name).'"]')->length);
            }
            $this->assertSame(1, $xpath->query('//*[@id="sectionAdministration"]//a[@href="'.route('platform.company.edit').'"]')->length);
            $sections = [];
            foreach ($xpath->query('//aside//a[contains(concat(" ", normalize-space(@class), " "), " section-toggle ")]') as $toggle) {
                $sections[] = $toggle->getAttribute('href');
            }
            $this->assertSame(['#sectionInflow', '#sectionOutflow', '#sectionWarehouse', '#sectionManufaktur', '#sectionAkuntansi', '#sectionMaster', '#sectionAdministration'], $sections);
        }
    }

    public function test_sidebar_collapse_targets_and_ids_are_unique(): void
    {
        config(['customs.enabled' => true]);
        $xpath = $this->renderNavigation('dashboard');
        foreach ($xpath->query('//aside//a[@data-bs-toggle="collapse"]') as $toggle) {
            $id = substr($toggle->getAttribute('href'), 1);
            $this->assertSame(1, $xpath->query('//*[@id="'.$id.'"]')->length, $id);
        }
        foreach (['id', 'en', 'zh_CN'] as $locale) {
            foreach (['operations_navigation', 'sales_navigation', 'inventory_navigation', 'finance_group_navigation', 'compliance_navigation', 'settings_navigation', 'administration_navigation', 'company_party_navigation', 'planning_navigation', 'production_navigation', 'materials_navigation'] as $key) {
                $this->assertTrue(app('translator')->hasForLocale('erp.'.$key, $locale), $locale.':'.$key);
            }
        }
    }

    public function test_customs_menu_visibility_follows_the_module_flag(): void
    {
        config(['customs.reports_enabled' => false]);
        $this->assertTrue(Route::has('customs.index'));
        $this->assertTrue(Route::has('customs-reports.index'));

        foreach ([false, true] as $enabled) {
            config(['customs.enabled' => $enabled]);
            $xpath = $this->renderNavigation('dashboard');
            $this->assertSame($enabled ? 1 : 0, $xpath->query('//*[@id="sectionCustoms"]')->length);

            foreach (['customs.index', 'customs-reports.index'] as $name) {
                $this->assertSame($enabled ? 1 : 0, $xpath->query('//*[@id="sectionCustoms"]//a[@href="'.route($name).'"]')->length, $name);
            }
        }
    }

    public function test_manual_customs_reports_are_available_without_h2h(): void
    {
        config(['customs.enabled' => false, 'customs.reports_enabled' => true]);
        $xpath = $this->renderNavigation('customs-reports.index');

        $this->assertSame(1, $xpath->query('//*[@id="sectionCustoms"]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="menuLaporanCeisa"]//a[@href="'.route('customs-reports.index').'"]')->length);
        $this->assertSame(0, $xpath->query('//*[@id="sectionCustoms"]//a[@href="'.route('customs.index').'"]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="menuInswTracking"]')->length);
        $this->assertSame('true', $xpath->query('//a[@href="#sectionCustoms"]')->item(0)->getAttribute('aria-expanded'));

        $this->actingAs(\App\Models\User::factory()->create());
        $this->get(route('customs-reports.index'))->assertOk();
        $this->assertFalse(config('customs.enabled'));
    }

    public function test_customs_reports_do_not_activate_the_documents_link(): void
    {
        config(['customs.enabled' => true]);
        foreach (['PEMASUKAN', 'PENGELUARAN', 'MUTASI_BAHAN_BAKU', 'WIP', 'MUTASI_BARANG_JADI', 'MUTASI_BARANG_MODAL', 'MUTASI_REJECT'] as $type) {
            $xpath = $this->renderNavigation('customs-reports.create', ['report_type' => $type]);
            $this->assertSame(1, $xpath->query('//*[@id="menuLaporanCeisa"]//a[contains(concat(" ", normalize-space(@class), " "), " active ")]')->length, $type);
            $this->assertSame(0, $xpath->query('//*[@id="sectionCustoms"]//a[@href="'.route('customs.index').'" and contains(concat(" ", normalize-space(@class), " "), " active ")]')->length, $type);
            $this->assertSame('true', $xpath->query('//a[@href="#sectionCustoms"]')->item(0)->getAttribute('aria-expanded'));
        }
    }
}
