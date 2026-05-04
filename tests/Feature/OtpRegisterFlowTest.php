<?php

namespace Tests\Feature;

use App\Models\SmsOtp;
use App\Models\User;
use App\Support\Sms\SmsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class OtpRegisterFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_otp_registration_is_split_into_mobile_code_and_profile_steps(): void
    {
        Mockery::mock('alias:'.SmsManager::class)
            ->shouldReceive('sendOtp')
            ->once()
            ->with('09123456789', Mockery::type('string'))
            ->andReturnNull();

        $this->get(route('otp.register'))
            ->assertOk()
            ->assertSee('name="mobile"', false)
            ->assertDontSee('name="otp_code"', false)
            ->assertDontSee('name="username"', false);

        $this->post(route('otp.register.request'), [
            'mobile' => '09123456789',
        ])
            ->assertRedirect(route('otp.register'))
            ->assertSessionHas('otp_register_mobile', '09123456789')
            ->assertSessionMissing('otp_register_verified_mobile');

        $this->get(route('otp.register'))
            ->assertOk()
            ->assertSee('name="otp_code"', false)
            ->assertDontSee('name="username"', false);

        SmsOtp::create([
            'mobile' => '09123456789',
            'purpose' => 'register',
            'code_hash' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(2),
        ]);

        $this->post(route('otp.register.verify'), [
            'mobile' => '09123456789',
            'otp_code' => '123456',
        ])
            ->assertRedirect(route('otp.register'))
            ->assertSessionHas('otp_register_verified_mobile', '09123456789');

        $this->assertDatabaseCount('users', 0);

        $this->get(route('otp.register'))
            ->assertOk()
            ->assertSee('name="username"', false)
            ->assertSee('name="email"', false)
            ->assertSee('name="password"', false)
            ->assertDontSee('name="otp_code"', false);

        $this->post(route('otp.register.complete'), [
            'username' => 'otptest',
            'email' => 'otp@example.com',
            'full_name' => 'OTP Tester',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'username' => 'otptest',
            'email' => 'otp@example.com',
            'mobile' => '09123456789',
        ]);

        $user = User::query()->where('username', 'otptest')->firstOrFail();

        $this->assertNotNull($user->mobile_verified_at);
    }
}
