<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\SharedFile;
use App\Models\SubscriptionOrder;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use App\Support\PlanPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminSubscriptionPlansTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_update_and_assign_a_subscription_plan(): void
    {
        $admin = User::factory()->create([
            'username' => 'admin',
            'email' => 'admin@example.com',
            'is_admin' => true,
        ]);

        $member = User::factory()->create([
            'username' => 'member',
            'email' => 'member@example.com',
        ]);

        $this->actingAs($admin)->get(route('admin.plans.create'))->assertOk();
        $this->actingAs($admin)->get(route('admin.subscribers.index'))->assertOk();

        $this->actingAs($admin)
            ->post(route('admin.plans.store'), [
                'name' => 'Premium',
                'slug' => 'premium',
                'description' => 'Premium plan',
                'sort_order' => 10,
                'price_amount' => 450000,
                'duration_value' => 1,
                'duration_unit' => 'month',
                'max_upload_size_mb' => 128,
                'max_storage_mb' => 4096,
                'max_team_members' => 8,
                'expire_options' => ['default', '24', 'custom', 'never'],
                'is_active' => '1',
                'allow_public_links' => '1',
                'allow_password_protection' => '1',
                'allow_custom_expiry' => '1',
                'allow_never_expire' => '1',
                'allow_personal_storage' => '1',
                'allow_team_features' => '1',
                'allow_signature_workflow' => '1',
                'allow_folders' => '1',
                'allow_ai_features' => '1',
            ])
            ->assertRedirect();

        $plan = SubscriptionPlan::query()->where('slug', 'premium')->firstOrFail();

        $this->assertSame(['default', '24', 'custom', 'never'], $plan->expire_options);
        $this->assertTrue($plan->allow_team_features);
        $this->assertSame(450000, $plan->price_amount);
        $this->assertSame(1, $plan->duration_value);
        $this->assertSame('month', $plan->duration_unit);

        $this->actingAs($admin)
            ->patch(route('admin.plans.update', $plan), [
                'name' => 'Premium Plus',
                'slug' => 'premium-plus',
                'description' => 'Updated premium plan',
                'sort_order' => 20,
                'price_amount' => 890000,
                'duration_value' => 45,
                'duration_unit' => 'day',
                'max_upload_size_mb' => 256,
                'max_storage_mb' => 8192,
                'max_team_members' => 12,
                'expire_options' => ['default', '24', 'custom'],
                'is_active' => '1',
                'allow_public_links' => '1',
                'allow_password_protection' => '1',
                'allow_custom_expiry' => '1',
                'allow_personal_storage' => '1',
                'allow_team_features' => '1',
                'allow_signature_workflow' => '1',
                'allow_folders' => '1',
            ])
            ->assertRedirect();

        $plan->refresh();

        $this->assertSame('Premium Plus', $plan->name);
        $this->assertSame('premium-plus', $plan->slug);
        $this->assertSame(['default', '24', 'custom'], $plan->expire_options);
        $this->assertFalse($plan->allow_never_expire);
        $this->assertSame(890000, $plan->price_amount);
        $this->assertSame(45, $plan->duration_value);
        $this->assertSame('day', $plan->duration_unit);

        $this->actingAs($admin)
            ->post(route('admin.subscribers.assign'), [
                'user_id' => $member->id,
                'plan_id' => $plan->id,
                'notes' => 'Manual assignment',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('user_subscriptions', [
            'user_id' => $member->id,
            'plan_id' => $plan->id,
            'status' => UserSubscription::STATUS_ACTIVE,
            'notes' => 'Manual assignment',
        ]);

        $subscription = UserSubscription::query()
            ->where('user_id', $member->id)
            ->where('plan_id', $plan->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertNotNull($subscription->ends_at);
        $this->assertSame(
            45,
            $subscription->starts_at->diffInDays($subscription->ends_at),
        );
    }

    public function test_file_send_uses_plan_limits_and_capabilities(): void
    {
        $sender = User::factory()->create([
            'username' => 'sender',
            'email' => 'sender@example.com',
        ]);

        $receiver = User::factory()->create([
            'username' => 'receiver',
            'email' => 'receiver@example.com',
        ]);

        $restrictedPlan = SubscriptionPlan::query()->create([
            'name' => 'Starter',
            'slug' => 'starter',
            'description' => 'Restricted starter plan',
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 50,
            'max_upload_size_mb' => 1,
            'max_storage_mb' => null,
            'expire_options' => ['default', '1'],
            'allow_public_links' => false,
            'allow_password_protection' => false,
            'allow_custom_expiry' => false,
            'allow_never_expire' => false,
            'allow_personal_storage' => false,
            'allow_team_features' => false,
            'max_team_members' => null,
            'allow_signature_workflow' => false,
            'allow_folders' => false,
            'allow_ai_features' => false,
        ]);

        PlanPolicy::assignPlan($sender, $restrictedPlan);

        $this->actingAs($sender)
            ->get(route('files.create'))
            ->assertOk()
            ->assertSee(__('ui.send.password_disabled_by_plan'))
            ->assertSee(__('ui.send.public_link_disabled_by_plan'))
            ->assertSee(__('ui.send.max_size_value', ['size' => 1]));

        $this->actingAs($sender)
            ->post(route('files.store'), [
                'receiver' => $receiver->username,
                'message' => 'Small file but password blocked',
                'file' => UploadedFile::fake()->create('tiny.pdf', 256, 'application/pdf'),
                'expire_option' => 'default',
                'download_password' => 'secret123',
                'download_password_confirmation' => 'secret123',
            ])
            ->assertSessionHasErrors('download_password');

        $this->actingAs($sender)
            ->post(route('files.store'), [
                'receiver' => $receiver->username,
                'message' => 'Oversized file',
                'file' => UploadedFile::fake()->create('large.pdf', 2048, 'application/pdf'),
                'expire_option' => 'default',
            ])
            ->assertSessionHasErrors('file');
    }

    public function test_multi_recipient_send_requires_sender_personal_storage_plan(): void
    {
        $sender = User::factory()->create([
            'username' => 'plain-sender',
            'email' => 'plain-sender@example.com',
        ]);

        $receiverA = User::factory()->create(['username' => 'receiver-a']);
        $receiverB = User::factory()->create(['username' => 'receiver-b']);

        $restrictedPlan = SubscriptionPlan::query()->create([
            'name' => 'No Storage',
            'slug' => 'no-storage',
            'description' => 'No personal storage',
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 10,
            'max_upload_size_mb' => 20,
            'max_storage_mb' => null,
            'expire_options' => ['default', '24'],
            'allow_public_links' => true,
            'allow_password_protection' => true,
            'allow_custom_expiry' => false,
            'allow_never_expire' => false,
            'allow_personal_storage' => false,
            'allow_team_features' => false,
            'allow_signature_workflow' => false,
            'allow_folders' => false,
            'allow_ai_features' => false,
        ]);

        PlanPolicy::assignPlan($sender, $restrictedPlan);

        $this->actingAs($sender)
            ->post(route('files.store'), [
                'receiver' => $receiverA->username.','.$receiverB->username,
                'message' => 'Two recipients',
                'file' => UploadedFile::fake()->create('shared.pdf', 256, 'application/pdf'),
                'expire_option' => '24',
            ])
            ->assertSessionHasErrors('receiver');
    }

    public function test_never_expire_single_send_moves_file_into_receiver_storage_when_receiver_allows_it_even_if_sender_plan_does_not(): void
    {
        $sender = User::factory()->create([
            'username' => 'sender-never',
            'email' => 'sender-never@example.com',
        ]);

        $receiver = User::factory()->create([
            'username' => 'receiver-never',
            'email' => 'receiver-never@example.com',
        ]);

        $senderPlan = SubscriptionPlan::query()->create([
            'name' => 'Sender Basic',
            'slug' => 'sender-basic',
            'description' => 'No direct no-expiry on sender plan',
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 11,
            'max_upload_size_mb' => 20,
            'max_storage_mb' => null,
            'expire_options' => ['default', '24'],
            'allow_public_links' => true,
            'allow_password_protection' => true,
            'allow_custom_expiry' => false,
            'allow_never_expire' => false,
            'allow_personal_storage' => false,
            'allow_team_features' => false,
            'allow_signature_workflow' => false,
            'allow_folders' => false,
            'allow_ai_features' => false,
        ]);

        $receiverPlan = SubscriptionPlan::query()->create([
            'name' => 'Receiver Storage',
            'slug' => 'receiver-storage',
            'description' => 'Receiver personal storage',
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 12,
            'max_upload_size_mb' => 20,
            'max_storage_mb' => 5,
            'expire_options' => ['default', '24', 'never'],
            'allow_public_links' => true,
            'allow_password_protection' => true,
            'allow_custom_expiry' => false,
            'allow_never_expire' => true,
            'allow_personal_storage' => true,
            'allow_team_features' => false,
            'allow_signature_workflow' => false,
            'allow_folders' => false,
            'allow_ai_features' => false,
        ]);

        PlanPolicy::assignPlan($sender, $senderPlan);
        PlanPolicy::assignPlan($receiver, $receiverPlan);
        $receiver->forceFill(['allow_receive_no_expiry' => true])->save();

        $this->actingAs($sender)
            ->post(route('files.store'), [
                'receiver' => $receiver->username,
                'message' => 'Store this for me forever',
                'file' => UploadedFile::fake()->create('forever.pdf', 256, 'application/pdf'),
                'expire_option' => 'never',
            ])
            ->assertRedirect(route('conversations.show', $receiver));

        $sharedFile = SharedFile::query()->latest('id')->firstOrFail();

        $this->assertSame($receiver->id, $sharedFile->owner_id);
        $this->assertTrue($sharedFile->is_personal_storage);
        $this->assertNull($sharedFile->expires_at);
    }

    public function test_never_expire_single_send_is_blocked_when_receiver_storage_has_no_capacity(): void
    {
        $sender = User::factory()->create([
            'username' => 'sender-full',
            'email' => 'sender-full@example.com',
        ]);

        $receiver = User::factory()->create([
            'username' => 'receiver-full',
            'email' => 'receiver-full@example.com',
        ]);

        $senderPlan = SubscriptionPlan::query()->create([
            'name' => 'Sender Plain',
            'slug' => 'sender-plain-plan',
            'description' => 'No sender fallback',
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 13,
            'max_upload_size_mb' => 20,
            'max_storage_mb' => null,
            'expire_options' => ['default', '24'],
            'allow_public_links' => true,
            'allow_password_protection' => true,
            'allow_custom_expiry' => false,
            'allow_never_expire' => false,
            'allow_personal_storage' => false,
            'allow_team_features' => false,
            'allow_signature_workflow' => false,
            'allow_folders' => false,
            'allow_ai_features' => false,
        ]);

        $receiverPlan = SubscriptionPlan::query()->create([
            'name' => 'Receiver Tiny Storage',
            'slug' => 'receiver-tiny-storage',
            'description' => 'Receiver tiny personal storage',
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 14,
            'max_upload_size_mb' => 20,
            'max_storage_mb' => 1,
            'expire_options' => ['default', '24', 'never'],
            'allow_public_links' => true,
            'allow_password_protection' => true,
            'allow_custom_expiry' => false,
            'allow_never_expire' => true,
            'allow_personal_storage' => true,
            'allow_team_features' => false,
            'allow_signature_workflow' => false,
            'allow_folders' => false,
            'allow_ai_features' => false,
        ]);

        PlanPolicy::assignPlan($sender, $senderPlan);
        PlanPolicy::assignPlan($receiver, $receiverPlan);
        $receiver->forceFill(['allow_receive_no_expiry' => true])->save();

        SharedFile::query()->create([
            'owner_id' => $receiver->id,
            'is_personal_storage' => true,
            'original_name' => 'existing.bin',
            'stored_name' => 'existing.bin',
            'mime_type' => 'application/octet-stream',
            'extension' => 'bin',
            'size' => 1024 * 1024,
            'storage_path' => 'files/2026/05/existing.bin',
            'checksum' => hash('sha256', 'existing'),
            'expires_at' => null,
            'status' => SharedFile::STATUS_ACTIVE,
            'security_scan_status' => SharedFile::SECURITY_SCAN_CLEAN,
        ]);

        $this->actingAs($sender)
            ->post(route('files.store'), [
                'receiver' => $receiver->username,
                'message' => 'Try forever',
                'file' => UploadedFile::fake()->create('overflow.pdf', 256, 'application/pdf'),
                'expire_option' => 'never',
            ])
            ->assertSessionHasErrors('expire_option');
    }

    public function test_never_expire_single_send_falls_back_to_sender_storage_when_receiver_has_not_enabled_it(): void
    {
        $sender = User::factory()->create([
            'username' => 'sender-pref',
            'email' => 'sender-pref@example.com',
        ]);

        $receiver = User::factory()->create([
            'username' => 'receiver-pref',
            'email' => 'receiver-pref@example.com',
            'allow_receive_no_expiry' => false,
        ]);

        $senderPlan = SubscriptionPlan::query()->create([
            'name' => 'Sender Never Pref',
            'slug' => 'sender-never-pref',
            'description' => 'Can retain no-expiry in sender storage',
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 15,
            'max_upload_size_mb' => 20,
            'max_storage_mb' => 10,
            'expire_options' => ['default', '24', 'never'],
            'allow_public_links' => true,
            'allow_password_protection' => true,
            'allow_custom_expiry' => false,
            'allow_never_expire' => true,
            'allow_personal_storage' => true,
            'allow_team_features' => false,
            'allow_signature_workflow' => false,
            'allow_folders' => false,
            'allow_ai_features' => false,
        ]);

        $receiverPlan = SubscriptionPlan::query()->create([
            'name' => 'Receiver Storage Pref',
            'slug' => 'receiver-storage-pref',
            'description' => 'Receiver personal storage',
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 16,
            'max_upload_size_mb' => 20,
            'max_storage_mb' => 5,
            'expire_options' => ['default', '24', 'never'],
            'allow_public_links' => true,
            'allow_password_protection' => true,
            'allow_custom_expiry' => false,
            'allow_never_expire' => true,
            'allow_personal_storage' => true,
            'allow_team_features' => false,
            'allow_signature_workflow' => false,
            'allow_folders' => false,
            'allow_ai_features' => false,
        ]);

        PlanPolicy::assignPlan($sender, $senderPlan);
        PlanPolicy::assignPlan($receiver, $receiverPlan);

        $this->actingAs($sender)
            ->post(route('files.store'), [
                'receiver' => $receiver->username,
                'message' => 'Try forever but not opted in',
                'file' => UploadedFile::fake()->create('preference.pdf', 256, 'application/pdf'),
                'expire_option' => 'never',
            ])
            ->assertRedirect(route('conversations.show', $receiver));

        $sharedFile = SharedFile::query()->latest('id')->firstOrFail();

        $this->assertSame($sender->id, $sharedFile->owner_id);
        $this->assertTrue($sharedFile->is_personal_storage);
        $this->assertNull($sharedFile->expires_at);
    }

    public function test_user_lookup_reports_receiver_no_expiry_capability_when_profile_setting_is_enabled(): void
    {
        $sender = User::factory()->create([
            'username' => 'lookup-sender',
            'email' => 'lookup-sender@example.com',
        ]);

        $receiver = User::factory()->create([
            'username' => 'lookup-receiver',
            'email' => 'lookup-receiver@example.com',
            'allow_receive_no_expiry' => true,
        ]);

        $receiverPlan = SubscriptionPlan::query()->create([
            'name' => 'Lookup Receiver Storage',
            'slug' => 'lookup-receiver-storage',
            'description' => 'Receiver personal storage for lookup',
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 17,
            'max_upload_size_mb' => 20,
            'max_storage_mb' => 5,
            'expire_options' => ['default', '24', 'never'],
            'allow_public_links' => true,
            'allow_password_protection' => true,
            'allow_custom_expiry' => false,
            'allow_never_expire' => true,
            'allow_personal_storage' => true,
            'allow_team_features' => false,
            'allow_signature_workflow' => false,
            'allow_folders' => false,
            'allow_ai_features' => false,
        ]);

        PlanPolicy::assignPlan($receiver, $receiverPlan);

        $this->actingAs($sender)
            ->getJson(route('users.lookup', ['q' => $receiver->username]))
            ->assertOk()
            ->assertJsonPath('users.0.username', $receiver->username)
            ->assertJsonPath('users.0.capabilities.allow_never_expire', true)
            ->assertJsonPath('users.0.capabilities.receiver_prefers_no_expiry', true);
    }

    public function test_user_can_view_upgrade_page_and_start_paid_purchase(): void
    {
        $user = User::factory()->create([
            'username' => 'subscriber',
            'email' => 'subscriber@example.com',
            'mobile' => '09121234567',
        ]);

        $plan = SubscriptionPlan::query()->create([
            'name' => 'Business',
            'slug' => 'business',
            'description' => 'Business plan',
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 10,
            'price_amount' => 990000,
            'duration_value' => 3,
            'duration_unit' => 'month',
            'expire_options' => ['default', '24', 'custom'],
            'allow_public_links' => true,
            'allow_password_protection' => true,
            'allow_custom_expiry' => true,
            'allow_never_expire' => false,
            'allow_personal_storage' => true,
            'allow_team_features' => true,
            'max_team_members' => 5,
            'allow_signature_workflow' => false,
            'allow_folders' => true,
            'allow_ai_features' => false,
        ]);

        $this->actingAs($user)
            ->get(route('subscriptions.upgrade'))
            ->assertOk()
            ->assertSee($plan->name)
            ->assertSee(__('ui.subscriptions.available_plans'))
            ->assertSee(__('ui.subscriptions.choose_plan'));

        Setting::setValue('zibal_enabled', 'true');
        Setting::setValue('zibal_test_mode', 'true');

        Http::fake([
            'https://gateway.zibal.ir/v1/request' => Http::response([
                'result' => 100,
                'message' => 'success',
                'trackId' => 'TRACK-123',
            ], 200),
        ]);

        $this->actingAs($user)
            ->post(route('subscriptions.purchase', $plan))
            ->assertRedirect('https://gateway.zibal.ir/start/TRACK-123');

        $order = SubscriptionOrder::query()->where('user_id', $user->id)->firstOrFail();
        $payment = SubscriptionPayment::query()->where('order_id', $order->id)->firstOrFail();

        $this->assertSame(SubscriptionOrder::STATUS_REDIRECTED, $order->status);
        $this->assertSame(SubscriptionPayment::STATUS_REDIRECTED, $payment->status);
        $this->assertSame('TRACK-123', $payment->track_id);
    }

    public function test_plan_policy_resolves_subscription_end_date_from_duration(): void
    {
        $plan = SubscriptionPlan::query()->create([
            'name' => 'Quarterly',
            'slug' => 'quarterly',
            'description' => 'Quarterly plan',
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 20,
            'price_amount' => 1200000,
            'duration_value' => 3,
            'duration_unit' => 'month',
            'expire_options' => ['default', '24', 'custom'],
            'allow_public_links' => true,
            'allow_password_protection' => true,
            'allow_custom_expiry' => true,
            'allow_never_expire' => false,
            'allow_personal_storage' => false,
            'allow_team_features' => false,
            'allow_signature_workflow' => false,
            'allow_folders' => false,
            'allow_ai_features' => false,
        ]);

        $startAt = Carbon::create(2026, 5, 3, 10, 0, 0);
        $endsAt = PlanPolicy::resolveSubscriptionEndDate($plan, $startAt);

        $this->assertNotNull($endsAt);
        $this->assertTrue($endsAt->equalTo($startAt->copy()->addMonths(3)));
    }
}
