<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\FinishedProduct;
use App\Models\ProductionOrder;
use Illuminate\Support\Facades\Hash;
use Database\Seeders\RoleSeeder;

class BatchLifecycleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * اختبار دورة حياة دفعة الإنتاج: إنشاء -> إرسال -> استلام
     */
    public function test_full_lifecycle_of_finished_product_batch()
    {
        // ==========================================
        // 0. زرع الأدوار والصلاحيات
        // ==========================================
        $this->seed(RoleSeeder::class);


        // ==========================================
        // 1. تجهيز البيانات
        // ==========================================


        // إنشاء موظف الإنتاج
        $productionWorker = User::forceCreate([
            'name' => 'Production Worker',
            'email' => 'worker@test.com',
            'phone' => '0911111111',
            'is_verified' => true,
            'password' => Hash::make('password'),
        ]);

        $productionWorker->assignRole('production_employee');



        // إنشاء أمين المستودع
        $warehouseManager = User::forceCreate([
            'name' => 'Storekeeper',
            'email' => 'storekeeper@test.com',
            'phone' => '0922222222',
            'is_verified' => true,
            'password' => Hash::make('password'),
        ]);

        $warehouseManager->assignRole('product_storekeeper');



        // إنشاء المنتج النهائي
        // size رقمي حسب migration
        $product = FinishedProduct::forceCreate([
            'name' => 'Test Dairy Product',
            'size' => 1,
            'unit' => 'L',
        ]);



        // إنشاء أمر الإنتاج
        // user_id إجباري حسب migration
        $order = ProductionOrder::forceCreate([
            'order_number' => '001',
            'user_id' => $productionWorker->id,
            'finished_product_id' => $product->id,
            'quantity' => 500,
        ]);



        // بيانات إنشاء الدفعة
        $payload = [
            'finished_product_id' => $product->id,
            'production_order_id' => $order->id,
            'quantity' => 100,
            'production_date' => '2026-07-21',
            'expiry_type' => 'month',
            'expiry_value' => 6
        ];



        // ==========================================
        // 2. اختبار دورة حياة الدفعة
        // ==========================================



        // المرحلة الأولى: إنشاء الدفعة
        $createResponse = $this
            ->actingAs($productionWorker, 'sanctum')
            ->postJson('/api/finished-product-batches', $payload);


        $createResponse->assertStatus(201);


        $batchId = $createResponse->json('batch.id');



        $this->assertDatabaseHas('finished_product_batches', [
            'id' => $batchId,
            'status' => 'created',
            'remaining_quantity' => 0,
        ]);




        // المرحلة الثانية: إرسال الدفعة
        $sendResponse = $this
            ->actingAs($productionWorker, 'sanctum')
            ->postJson("/api/finished-product-batches/{$batchId}/send");


        $sendResponse->assertStatus(200);



        $this->assertDatabaseHas('finished_product_batches', [
            'id' => $batchId,
            'status' => 'sent',
            'remaining_quantity' => 0,
        ]);




        // المرحلة الثالثة: استلام الدفعة في المستودع
        $receiveResponse = $this
            ->actingAs($warehouseManager, 'sanctum')
            ->postJson("/api/finished-product-batches/{$batchId}/receive");


        $receiveResponse->assertStatus(200);



        $this->assertDatabaseHas('finished_product_batches', [
            'id' => $batchId,
            'status' => 'received',
            'remaining_quantity' => 100,
        ]);
    }
}