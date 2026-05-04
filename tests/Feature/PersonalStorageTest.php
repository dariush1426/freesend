<?php

namespace Tests\Feature;

use App\Models\SharedFile;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\PlanPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PersonalStorageTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_save_file_to_personal_storage_when_plan_allows_it(): void
    {
        Storage::fake();

        $user = User::factory()->create([
            'username' => 'storage-user',
            'email' => 'storage@example.com',
        ]);

        $plan = SubscriptionPlan::query()->create([
            'name' => 'Premium Storage',
            'slug' => 'premium-storage',
            'description' => 'Storage enabled plan',
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 10,
            'max_upload_size_mb' => 10,
            'max_storage_mb' => 5,
            'expire_options' => ['default', '24', 'custom', 'never'],
            'allow_public_links' => true,
            'allow_password_protection' => true,
            'allow_custom_expiry' => true,
            'allow_never_expire' => true,
            'allow_personal_storage' => true,
            'allow_team_features' => false,
            'allow_signature_workflow' => false,
            'allow_folders' => false,
            'allow_ai_features' => false,
        ]);

        PlanPolicy::assignPlan($user, $plan);

        $this->actingAs($user)
            ->get(route('files.create', ['destination' => 'storage']))
            ->assertOk()
            ->assertSee(__('ui.send.destination_storage'))
            ->assertSee(__('ui.send.save_to_storage'));

        $this->actingAs($user)
            ->post(route('files.store'), [
                'destination' => 'storage',
                'message' => 'Keep this for later',
                'file' => UploadedFile::fake()->create('personal.pdf', 1024, 'application/pdf'),
            ])
            ->assertRedirect(route('storage.index'));

        $file = SharedFile::query()->where('owner_id', $user->id)->firstOrFail();

        $this->assertTrue($file->is_personal_storage);
        $this->assertNull($file->expires_at);
        $this->assertSame(SharedFile::STATUS_ACTIVE, $file->status);

        $this->actingAs($user)
            ->get(route('storage.index'))
            ->assertOk()
            ->assertSee('personal.pdf');
    }

    public function test_personal_storage_enforces_plan_quota(): void
    {
        Storage::fake();

        $user = User::factory()->create([
            'username' => 'quota-user',
            'email' => 'quota@example.com',
        ]);

        $plan = SubscriptionPlan::query()->create([
            'name' => 'Starter Storage',
            'slug' => 'starter-storage',
            'description' => 'Small storage quota',
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 20,
            'max_upload_size_mb' => 10,
            'max_storage_mb' => 1,
            'expire_options' => ['default', '24'],
            'allow_public_links' => false,
            'allow_password_protection' => false,
            'allow_custom_expiry' => false,
            'allow_never_expire' => false,
            'allow_personal_storage' => true,
            'allow_team_features' => false,
            'allow_signature_workflow' => false,
            'allow_folders' => false,
            'allow_ai_features' => false,
        ]);

        PlanPolicy::assignPlan($user, $plan);

        SharedFile::query()->create([
            'owner_id' => $user->id,
            'is_personal_storage' => true,
            'original_name' => 'existing.pdf',
            'stored_name' => 'existing.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size' => 900 * 1024,
            'storage_path' => 'files/2026/05/existing.pdf',
            'checksum' => 'abc',
            'expires_at' => null,
            'status' => SharedFile::STATUS_ACTIVE,
            'security_scan_status' => SharedFile::SECURITY_SCAN_CLEAN,
        ]);

        $this->actingAs($user)
            ->post(route('files.store'), [
                'destination' => 'storage',
                'file' => UploadedFile::fake()->create('too-much.pdf', 300, 'application/pdf'),
            ])
            ->assertSessionHasErrors('file');
    }
}
