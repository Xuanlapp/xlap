<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.turnstile.enabled' => false]);
    }

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response
            ->assertOk()
            ->assertSeeVolt('pages.auth.login');
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $component = Volt::test('pages.auth.login')
            ->set('form.login', $user->email)
            ->set('form.password', 'password')
            ->set('form.startedAt', now()->subSeconds(2)->timestamp);

        $component->call('login');

        $component
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
    }

    public function test_users_can_authenticate_using_username(): void
    {
        $user = User::factory()->create(['username' => 'login_user']);

        $component = Volt::test('pages.auth.login')
            ->set('form.login', 'login_user')
            ->set('form.password', 'password')
            ->set('form.startedAt', now()->subSeconds(2)->timestamp);

        $component->call('login');

        $component
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_users_can_authenticate_using_the_post_login_route(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'login' => $user->email,
            'password' => 'password',
            'started_at' => now()->subSeconds(2)->timestamp,
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
    }

    public function test_turnstile_token_is_required_when_enabled(): void
    {
        config([
            'services.turnstile.enabled' => true,
            'services.turnstile.site_key' => 'site-key',
            'services.turnstile.secret_key' => 'secret-key',
        ]);

        Http::fake([
            'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
                'success' => false,
                'error-codes' => ['missing-input-response'],
            ]),
        ]);

        $user = User::factory()->create();

        $component = Volt::test('pages.auth.login')
            ->set('form.login', $user->email)
            ->set('form.password', 'password')
            ->set('form.startedAt', now()->subSeconds(2)->timestamp);

        $component->call('login');

        $component
            ->assertHasErrors(['form.turnstileToken'])
            ->assertHasNoErrors(['form.login', 'form.password'])
            ->assertNoRedirect();

        $this->assertGuest();
    }

    public function test_users_can_authenticate_when_turnstile_verification_passes(): void
    {
        config([
            'services.turnstile.enabled' => true,
            'services.turnstile.site_key' => 'site-key',
            'services.turnstile.secret_key' => 'secret-key',
        ]);

        Http::fake([
            'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
                'success' => true,
            ]),
        ]);

        $user = User::factory()->create();

        $component = Volt::test('pages.auth.login')
            ->set('form.login', $user->email)
            ->set('form.password', 'password')
            ->set('form.startedAt', now()->subSeconds(2)->timestamp)
            ->set('form.turnstileToken', 'valid-token');

        $component->call('login');

        $component
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $component = Volt::test('pages.auth.login')
            ->set('form.login', $user->email)
            ->set('form.password', 'wrong-password')
            ->set('form.startedAt', now()->subSeconds(2)->timestamp);

        $component->call('login');

        $component
            ->assertHasErrors(['form.password'])
            ->assertHasNoErrors(['form.login', 'form.turnstileToken'])
            ->assertNoRedirect();

        $this->assertGuest();
    }

    public function test_login_honeypot_blocks_automated_submissions(): void
    {
        $user = User::factory()->create();

        $component = Volt::test('pages.auth.login')
            ->set('form.login', $user->email)
            ->set('form.password', 'password')
            ->set('form.website', 'https://spam.test')
            ->set('form.startedAt', now()->subSeconds(2)->timestamp);

        $component->call('login');

        $component
            ->assertHasErrors(['form.turnstileToken'])
            ->assertNoRedirect();

        $this->assertGuest();
    }

    public function test_login_blocks_submissions_that_are_too_fast(): void
    {
        $user = User::factory()->create();

        $component = Volt::test('pages.auth.login')
            ->set('form.login', $user->email)
            ->set('form.password', 'password')
            ->set('form.startedAt', now()->timestamp);

        $component->call('login');

        $component
            ->assertHasErrors(['form.turnstileToken'])
            ->assertNoRedirect();

        $this->assertGuest();
    }

    public function test_navigation_menu_can_be_rendered(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->get('/dashboard');

        $response
            ->assertOk()
            ->assertSeeVolt('layout.navigation');
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Volt::test('layout.navigation');

        $component->call('logout');

        $component
            ->assertHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
    }
}
