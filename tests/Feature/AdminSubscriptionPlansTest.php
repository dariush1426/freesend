<?php

namespace Tests\Feature;

use App\Models\Setting;
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
