<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ArchiveInvoicedOrdersTest extends TestCase
{
    use RefreshDatabase;

    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.d365.tenant_id' => 'test-tenant',
            'services.d365.client_id' => 'test-client',
            'services.d365.client_secret' => 'test-secret',
            'services.d365.resource' => 'https://test.dynamics.com',
        ]);

        $this->vendor = new Vendor([
            'name' => 'Test Vendor',
            'vendor_code' => 'V-ARCH',
            'type' => 'fabric',
            'is_active' => true,
        ]);
        $this->vendor->id = (string) Str::uuid();
        $this->vendor->save();
    }

    private function makePo(string $poNumber, string $orderDate, string $status = 'pending'): PurchaseOrder
    {
        $po = new PurchaseOrder([
            'po_number' => $poNumber,
            'vendor_id' => $this->vendor->id,
            'status' => $status,
            'order_date' => $orderDate,
            'currency' => 'IDR',
        ]);
        $po->id = (string) Str::uuid();
        $po->save();

        return $po;
    }

    public function test_archives_old_pos_that_are_invoiced_in_d365()
    {
        $this->withoutExceptionHandling();

        $old = $this->makePo('MPG/PO/2501/00001', '2025-01-15');
        $recent = $this->makePo('MPG/PO/2509/00002', '2026-09-01');
        $oldButNotInvoiced = $this->makePo('MPG/PO/2501/00003', '2025-01-15');

        Http::fake([
            'https://login.microsoftonline.com/*' => Http::response([
                'access_token' => 'mock-token',
                'expires_in' => 3600,
            ], 200),
            'https://test.dynamics.com/data/PurchaseOrderHeadersV2*' => Http::response([
                'value' => [
                    ['PurchaseOrderNumber' => 'MPG/PO/2501/00001', 'PurchaseOrderStatus' => 'Invoiced'],
                    ['PurchaseOrderNumber' => 'MPG/PO/2501/00003', 'PurchaseOrderStatus' => 'Backorder'],
                ],
            ], 200),
        ]);

        $exitCode = Artisan::call('d365:archive-invoiced-orders', [
            '--months' => 8,
            '--company' => 'mpg',
        ]);

        if ($exitCode !== 0) {
            echo "ARTISAN OUTPUT:\n".Artisan::output()."\n";
        }
        $this->assertEquals(0, $exitCode);

        // Old + invoiced -> archived.
        $this->assertNotNull($old->fresh()->archived_at);

        // Not yet 8 months old -> never even checked, stays unarchived.
        $this->assertNull($recent->fresh()->archived_at);

        // Old but still Backorder in D365 -> left alone.
        $this->assertNull($oldButNotInvoiced->fresh()->archived_at);

        // The recent PO's number should never have been sent to D365 at all.
        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'PurchaseOrderHeadersV2')) {
                return true; // ignore the token request
            }

            return ! str_contains($request->url(), 'MPG%2FPO%2F2509%2F00002')
                && ! str_contains(urldecode($request->url()), 'MPG/PO/2509/00002');
        });
    }

    public function test_dry_run_reports_but_does_not_archive()
    {
        $this->withoutExceptionHandling();

        $old = $this->makePo('MPG/PO/2501/00010', '2025-01-15');

        Http::fake([
            'https://login.microsoftonline.com/*' => Http::response([
                'access_token' => 'mock-token',
                'expires_in' => 3600,
            ], 200),
            'https://test.dynamics.com/data/PurchaseOrderHeadersV2*' => Http::response([
                'value' => [
                    ['PurchaseOrderNumber' => 'MPG/PO/2501/00010', 'PurchaseOrderStatus' => 'Invoiced'],
                ],
            ], 200),
        ]);

        $exitCode = Artisan::call('d365:archive-invoiced-orders', [
            '--months' => 8,
            '--company' => 'mpg',
            '--dry-run' => true,
        ]);

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('would archive', Artisan::output());
        $this->assertNull($old->fresh()->archived_at);
    }

    public function test_already_archived_pos_are_not_rechecked()
    {
        $this->withoutExceptionHandling();

        $already = $this->makePo('MPG/PO/2501/00020', '2025-01-15');
        $already->archived_at = now();
        $already->save();

        Http::fake([
            'https://login.microsoftonline.com/*' => Http::response([
                'access_token' => 'mock-token',
                'expires_in' => 3600,
            ], 200),
            'https://test.dynamics.com/data/PurchaseOrderHeadersV2*' => Http::response(['value' => []], 200),
        ]);

        $exitCode = Artisan::call('d365:archive-invoiced-orders', [
            '--months' => 8,
            '--company' => 'mpg',
        ]);

        $this->assertEquals(0, $exitCode);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'PurchaseOrderHeadersV2'));
    }
}
