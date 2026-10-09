<?php

namespace Tests\Feature;

use App\Exports\ProductExport;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class ProductImportExportAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_csv_can_update_master_without_overwriting_inventory(): void
    {
        $this->withoutMiddleware();
        $product = Product::create(['sku' => 'SKU-ROUND', 'name' => "Original\nQuoted, product", 'variation' => 'Blue',
            'category_name' => 'Test', 'unit' => 'PCS', 'sell_price' => 1250.50, 'stock_quantity' => 7, 'average_cost' => 100]);
        $csv = Excel::raw(new ProductExport(Product::query()), \Maatwebsite\Excel\Excel::CSV);
        $product->update(['name' => 'Changed', 'sell_price' => 1, 'stock_quantity' => 9, 'average_cost' => 200]);
        $this->post(route('product.import'), ['file_csv' => UploadedFile::fake()->createWithContent('products.csv', $csv)])
            ->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseHas('products', ['sku' => 'SKU-ROUND', 'name' => "Original\nQuoted, product", 'sell_price' => 1250.50,
            'variation' => 'Blue', 'category_name' => 'Test', 'stock_quantity' => 9, 'average_cost' => 200]);
    }

    public function test_unknown_header_and_truncated_rows_are_rejected_without_partial_writes(): void
    {
        $this->withoutMiddleware();
        foreach (["SKU;Name\nBAD;Bad\n", implode(';', (new ProductExport(Product::query()))->headings())."\nNEW;New;Blue;Test;10;0;0;PCS\nBROKEN;Bad\n"] as $csv) {
            $this->post(route('product.import'), ['file_csv' => UploadedFile::fake()->createWithContent('products.csv', $csv)])
                ->assertRedirect()->assertSessionHas('error');
            $this->assertDatabaseCount('products', 0);
        }
    }

    public function test_invalid_price_rolls_back_prior_master_changes(): void
    {
        $this->withoutMiddleware();
        $csv = implode(';', (new ProductExport(Product::query()))->headings())
            ."\nNEW;New;Blue;Test;10;0;0;PCS\nBAD;Bad;Blue;Test;-1;0;0;PCS\n";
        $this->post(route('product.import'), ['file_csv' => UploadedFile::fake()->createWithContent('products.csv', $csv)])
            ->assertRedirect()->assertSessionHas('error');
        $this->assertDatabaseCount('products', 0);
    }
}