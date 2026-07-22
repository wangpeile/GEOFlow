<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Support\AdminWeb;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminFeatureVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_profile_hides_secondary_entries_and_keeps_knowledge_inputs_visible(): void
    {
        config([
            'geoflow.admin_features.analytics_and_leads' => false,
            'geoflow.admin_features.enterprise_knowledge' => true,
            'geoflow.admin_features.url_import' => true,
            'geoflow.admin_features.theme_replication' => false,
            'geoflow.admin_features.update_center' => false,
            'geoflow.admin_features.api_tokens' => false,
        ]);

        $admin = $this->createSuperAdmin();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee(route('admin.analytics'), false)
            ->assertDontSee(AdminWeb::routePath('admin.system-updates.index'), false)
            ->assertDontSee(route('admin.api-tokens.index'), false);

        $this->get(route('admin.site-settings.index'))
            ->assertOk()
            ->assertDontSee(route('admin.site-settings.theme-replications.create'), false);

        $this->get(route('admin.materials.index'))
            ->assertOk()
            ->assertSee(route('admin.enterprise-knowledge.create'), false)
            ->assertSee(route('admin.url-import'), false)
            ->assertSee(route('admin.url-import.history'), false);

        $this->get(route('admin.analytics'))->assertOk();
    }

    public function test_hidden_entries_can_be_restored_with_configuration(): void
    {
        config([
            'geoflow.admin_features.analytics_and_leads' => true,
            'geoflow.admin_features.theme_replication' => true,
            'geoflow.admin_features.update_center' => true,
            'geoflow.admin_features.api_tokens' => true,
        ]);

        $admin = $this->createSuperAdmin('restored_features_admin');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.analytics'), false)
            ->assertSee(AdminWeb::routePath('admin.system-updates.index'), false)
            ->assertSee(route('admin.api-tokens.index'), false);

        $this->get(route('admin.site-settings.index'))
            ->assertOk()
            ->assertSee(route('admin.site-settings.theme-replications.create'), false);
    }

    private function createSuperAdmin(string $username = 'feature_visibility_admin'): Admin
    {
        return Admin::query()->create([
            'username' => $username,
            'password' => 'secret-123',
            'email' => $username.'@example.com',
            'display_name' => 'Feature Visibility Admin',
            'role' => 'super_admin',
            'status' => 'active',
        ]);
    }
}
