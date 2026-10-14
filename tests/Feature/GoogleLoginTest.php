<?php

use App\Models\Mailbox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;

uses(RefreshDatabase::class);

function fakeGoogleUser(array $attributes = [], bool $emailVerified = true): void
{
    $googleUser = GoogleUser::fake([
        'id' => 'google-123',
        'name' => 'John Lewis',
        'email' => 'john@example.com',
        'token' => 'gmail-access-token',
        'refreshToken' => 'gmail-refresh-token',
        'approvedScopes' => ['openid', 'email', 'profile', Mailbox::GMAIL_READONLY_SCOPE],
        ...$attributes,
    ])->setRaw(['email_verified' => $emailVerified]);

    Socialite::fake('google', $googleUser);
}

test('the login page links to google', function () {
    Socialite::fake('google');

    $this->get(route('google.redirect'))->assertRedirect();
});

test('an existing user signs in with google and gets linked', function () {
    $user = User::factory()->create(['email' => 'john@example.com', 'rank' => 2]);
    fakeGoogleUser();

    $this->get(route('google.callback'))->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->google_id)->toBe('google-123')
        ->and($user->mailbox)
        ->email->toBe('john@example.com')
        ->access_token->toBe('gmail-access-token')
        ->refresh_token->toBe('gmail-refresh-token');
});

test('google sign-in is refused without gmail access', function () {
    User::factory()->create(['email' => 'john@example.com', 'rank' => 2]);
    fakeGoogleUser(['approvedScopes' => ['openid', 'email', 'profile']]);

    $this->get(route('google.callback'))->assertSessionHasErrors('email');

    $this->assertGuest();
    expect(Mailbox::count())->toBe(0);
});

test('a later sign-in keeps the stored refresh token when google sends none', function () {
    $user = User::factory()->create(['email' => 'john@example.com', 'rank' => 2]);
    $user->mailbox()->create(['email' => 'john@example.com', 'access_token' => 'old', 'refresh_token' => 'kept-refresh-token']);
    fakeGoogleUser(['refreshToken' => null]);

    $this->get(route('google.callback'));

    expect($user->mailbox()->sole())
        ->access_token->toBe('gmail-access-token')
        ->refresh_token->toBe('kept-refresh-token');
});

test('a new google account is created as pending and not signed in', function () {
    fakeGoogleUser();

    $this->get(route('google.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('status');

    $this->assertGuest();
    expect(User::firstWhere('email', 'john@example.com'))
        ->google_id->toBe('google-123')
        ->isPending()->toBeTrue()
        ->mailbox->not->toBeNull();
});

test('a pending user stays signed out on later google sign-ins', function () {
    User::factory()->create(['email' => 'john@example.com', 'google_id' => 'google-123', 'rank' => null]);
    fakeGoogleUser();

    $this->get(route('google.callback'))->assertRedirect(route('login'));

    $this->assertGuest();
});

test('unverified google emails are refused', function () {
    User::factory()->create(['email' => 'john@example.com', 'rank' => 1]);
    fakeGoogleUser(emailVerified: false);

    $this->get(route('google.callback'))->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('pending users cannot sign in with a password', function () {
    User::factory()->create(['email' => 'john@example.com', 'password' => 'secret-pass', 'rank' => null]);

    $this->post(route('login'), ['email' => 'john@example.com', 'password' => 'secret-pass'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('pending users are listed for admins to approve', function () {
    $pending = User::factory()->create(['rank' => null]);

    $this->actingAs(User::factory()->create(['rank' => 1]))
        ->get(route('users.index'))
        ->assertSee($pending->email)
        ->assertSee('Pending');
});

test('logging in with google asks for notification permission', function () {
    User::factory()->create(['email' => 'john@example.com', 'rank' => 2]);
    fakeGoogleUser();

    $this->get(route('google.callback'))->assertSessionHas('promptNotifications', true);
});
