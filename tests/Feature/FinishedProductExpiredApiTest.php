<?php

namespace Tests\Feature;

use Tests\TestCase;

class FinishedProductExpiredApiTest extends TestCase
{
    public function test_expired_endpoint_returns_expected_structure()
    {
        // Disable middleware to simplify test environment
        $this->withoutMiddleware();

        // Prepare fake response from the service
        $fakeResponse = [
            'data' => [
                [
                    'id' => 1,
                    'finished_product_name' => 'Test Product',
                    'batch_number' => 'BATCH-1',
                    'quantity' => 100,
                    'remaining_quantity' => 50,
                    'production_date' => '2024-01-01',
                    'expiry_date' => '2024-05-01',
                    'days_since_expiry' => 30,
                    'status' => 'منتهية الصلاحية',
                ]
            ],
            'summary' => [
                'total_expired_batches' => 1,
                'total_quantity' => 100,
                'total_remaining_quantity' => 50,
            ]
        ];

        // Mock the service and bind to the container
        $mock = $this->createMock(\App\Services\FinishedProductWarehouseService::class);
        $mock->method('اتلاف')->willReturn($fakeResponse);
        $this->app->instance(\App\Services\FinishedProductWarehouseService::class, $mock);

        $response = $this->getJson('/api/finished-products/expired');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Expired batches retrieved successfully',
                'data' => $fakeResponse['data'],
                'summary' => $fakeResponse['summary'],
            ]);
    }
}
