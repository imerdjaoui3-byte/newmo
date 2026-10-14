<?php

use App\Models\Mailbox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['rank' => 3]);
});

test('users only see their own inbox', function () {
    User::factory()->create()->mailbox()->create(['email' => 'someone-else@example.com', 'access_token' => 'token']);

    $this->actingAs($this->user)
        ->get(route('mailbox.show'))
        ->assertOk()
        ->assertSee('No inbox connected')
        ->assertDontSee('someone-else@example.com');
});

test('connecting asks google for read-only gmail access', function () {
    Socialite::fake('google');

    $this->actingAs($this->user)->get(route('mailbox.connect'))->assertRedirect();
});

test('the callback stores encrypted tokens for the user inbox', function () {
    Socialite::fake('google', GoogleUser::fake([
        'email' => 'orders@example.com',
        'token' => 'access-token',
        'refreshToken' => 'refresh-token',
        'expiresIn' => 3600,
        'approvedScopes' => ['https://www.googleapis.com/auth/gmail.readonly'],
    ]));

    $this->actingAs($this->user)->get(route('mailbox.callback'))->assertRedirect(route('mailbox.show'));

    $mailbox = Mailbox::sole();

    expect($mailbox)
        ->email->toBe('orders@example.com')
        ->refresh_token->toBe('refresh-token')
        ->user_id->toBe($this->user->id)
        ->and($mailbox->getRawOriginal('refresh_token'))->not->toBe('refresh-token');
});

test('the inbox is not connected without the gmail scope', function () {
    Socialite::fake('google', GoogleUser::fake(['approvedScopes' => ['email']]));

    $this->actingAs($this->user)->get(route('mailbox.callback'))->assertSessionHasErrors('mailbox');

    expect(Mailbox::count())->toBe(0);
});

test('the mailbox page lists the latest emails', function () {
    $this->user->mailbox()->create(['email' => 'orders@example.com', 'access_token' => 'token', 'expires_at' => now()->addHour()]);

    Http::fake([
        'gmail.googleapis.com/gmail/v1/users/me/messages?*' => Http::response(['messages' => [['id' => 'abc']]]),
        'gmail.googleapis.com/gmail/v1/users/me/messages/abc*' => Http::response([
            'snippet' => 'You received $40.00',
            'payload' => ['headers' => [
                ['name' => 'Subject', 'value' => 'INTERAC e-Transfer'],
                ['name' => 'From', 'value' => 'notify@payments.interac.ca'],
            ]],
        ]),
    ]);

    $this->actingAs($this->user)
        ->get(route('mailbox.show'))
        ->assertOk()
        ->assertSee('INTERAC e-Transfer')
        ->assertSee('You received $40.00');
});

test('an expired access token is refreshed before reading', function () {
    $mailbox = $this->user->mailbox()->create([
        'email' => 'orders@example.com',
        'access_token' => 'old-token',
        'refresh_token' => 'refresh-token',
        'expires_at' => now()->subMinute(),
    ]);

    Http::fake(['oauth2.googleapis.com/token' => Http::response(['access_token' => 'new-token', 'expires_in' => 3600])]);

    expect($mailbox->freshAccessToken())->toBe('new-token')
        ->and($mailbox->fresh()->expires_at->isFuture())->toBeTrue();
});

test('disconnecting revokes access and forgets the inbox', function () {
    $this->user->mailbox()->create(['email' => 'orders@example.com', 'access_token' => 'token', 'refresh_token' => 'refresh-token']);
    Http::fake();

    $this->actingAs($this->user)->delete(route('mailbox.destroy'))->assertRedirect(route('mailbox.show'));

    Http::assertSent(fn ($request) => str_contains($request->url(), 'oauth2.googleapis.com/revoke'));
    expect(Mailbox::count())->toBe(0);
});
