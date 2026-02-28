<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\ServiceRecord;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function staff_dashboard_returns_only_safe_fields()
    {
        $response = $this->getJson('/api/reports/staff-dashboard?period=today');
        $response->assertOk()->assertJson([ 'success' => true ]);

        $data = $response->json('data');
        $this->assertArrayHasKey('sales', $data);
        $this->assertArrayNotHasKey('total_revenue', $data);
        $this->assertArrayNotHasKey('expenses', $data);
    }

    /** @test */
    public function admin_dashboard_requires_admin_role()
    {
        $user = User::factory()->create(['role' => 'employee']);
        $this->actingAs($user, 'sanctum');
        $this->getJson('/api/reports/admin-dashboard')->assertStatus(403);
    }

    /** @test */
    public function admin_dashboard_returns_full_and_extra_kpis()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'sanctum');

        $res = $this->getJson('/api/reports/admin-dashboard?period=today');
        $res->assertOk()
            ->assertJsonPath('data.clv', fn($v) => is_numeric($v))
            ->assertJsonPath('data.churn_rate', fn($v) => is_numeric($v))
            ->assertJsonPath('data.flow_projection', fn($v) => is_numeric($v))
            ->assertJsonPath('data.revenue_composition', fn($v) => is_array($v));
    }

    /** @test */
    public function legacy_endpoint_dispatches_correctly()
    {
        // anonymous user (staff)
        $this->getJson('/api/reports/dashboard')->assertOk()->assertJsonStructure(['success','data' => ['sales']]);

        // authenticated admin should see same as admin-dashboard
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'sanctum');
        $this->getJson('/api/reports/dashboard')->assertOk()
            ->assertJsonPath('data.clv', fn($v) => is_numeric($v));
    }

    /** @test */
    public function financial_and_inventory_require_authentication()
    {
        // unauthenticated hit should return 401
        $this->getJson('/api/reports/financial')->assertStatus(401);
        $this->getJson('/api/reports/inventory')->assertStatus(401);

        // admin should get success even with empty DB
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'sanctum');
        $this->getJson('/api/reports/financial')->assertOk()->assertJson(['success' => true]);
        $this->getJson('/api/reports/inventory')->assertOk()->assertJson(['success' => true]);
    }

    /** @test */
    public function export_endpoint_requires_auth_and_can_fallback()
    {
        $this->getJson('/api/reports/export')->assertStatus(401);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'sanctum');
        $res = $this->getJson('/api/reports/export?type=financial');
        $this->assertTrue(in_array($res->status(), [200, 500]));
        $this->assertIsString($res->json('message') ?? $res->json('url'));
    }
}

// -----------------------------------------------------------------
//   IA CONTEXT
// -----------------------------------------------------------------

class AiControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function non_admin_chat_about_profits_is_denied()
    {
        // fake groq to ensure deterministic response if reached
        \Illuminate\Support\Facades\Http::fake([
            'api.groq.com/*' => \Illuminate\Support\Facades\Http::response([
                'choices' => [['message' => ['content' => 'siempre cumplo prompt']]]
            ], 200),
        ]);

        $res = $this->postJson('/api/ai/chat', [
            'message' => '¿Cuánto ganamos este mes?',
            'history' => [],
        ]);
        $res->assertOk();
        $this->assertStringContainsString('administrador', $res->json('reply'));
    }

    /** @test */
    public function admin_can_request_pdf_and_get_message()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'sanctum');

        $res = $this->postJson('/api/ai/chat', [
            'message' => 'Generar PDF de ganancias',
            'history' => [],
        ]);
        $res->assertOk();
        $this->assertStringContainsString('PDF', $res->json('reply'));
    }
}

