<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

it('provisions users/{uid} on a first sign in and signs the customer in', function (): void {
    $this->travelTo(new DateTimeImmutable('2026-10-06 04:12:23', new DateTimeZone('UTC')));

    Socialite::fake('google', SocialiteUser::fake([
        'id' => '110082736',
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'avatar' => 'https://example.com/ada.jpg',
    ]));

    $this->get(route('auth.google.callback'))->assertRedirect(route('home'));

    $this->assertAuthenticated();

    expect(rtdb()->get('users/110082736'))->toMatchArray([
        'sub' => '110082736',
        'email' => 'ada@example.com',
        'name' => 'Ada Lovelace',
        'avatar' => 'https://example.com/ada.jpg',
        'createdAt' => '2026-10-06T04:12:23Z',
    ]);
});

it('reuses the existing users/{uid} node on a returning sign in', function (): void {
    rtdb()->seed('users/110082736', [
        'sub' => '110082736',
        'email' => 'ada@example.com',
        'name' => 'Ada Lovelace',
        'avatar' => 'https://example.com/ada.jpg',
        'createdAt' => '2026-10-01T00:00:00Z',
    ]);

    Socialite::fake('google', SocialiteUser::fake([
        'id' => '110082736',
        'name' => 'Ada Lovelace',
    ]));

    $this->get(route('auth.google.callback'))->assertRedirect(route('home'));

    expect(rtdb()->get('users/110082736')['createdAt'])->toBe('2026-10-01T00:00:00Z')
        ->and(array_keys(rtdb()->get('users')))->toHaveCount(1);
});

it('keys the account by Google sub, never by email', function (): void {
    Socialite::fake('google', SocialiteUser::fake(['id' => 'sub-a', 'email' => 'same@example.com']));
    $this->get(route('auth.google.callback'));

    Socialite::fake('google', SocialiteUser::fake(['id' => 'sub-b', 'email' => 'same@example.com']));
    $this->get(route('auth.google.callback'));

    expect(array_keys(rtdb()->get('users')))->toHaveCount(2)
        ->and(rtdb()->get('users/sub-a'))->toMatchArray(['sub' => 'sub-a', 'email' => 'same@example.com'])
        ->and(rtdb()->get('users/sub-b'))->toMatchArray(['sub' => 'sub-b', 'email' => 'same@example.com']);
});

it('lets a visitor browse without signing in', function (): void {
    $this->get('/')->assertOk()->assertSee('Sign in with Google');

    $this->assertGuest();
});

it('prompts the Google button on a gated action while signed out', function (): void {
    Route::middleware('auth')->get('/favorite-action', fn(): string => 'favorited');

    $this->get('/favorite-action')->assertRedirect(route('login'));

    $this->get(route('login'))->assertOk()->assertSee('Sign in with Google');
});

it('returns the customer to the gated action after signing in', function (): void {
    Route::middleware('auth')->get('/favorite-action', fn(): string => 'favorited');

    $this->get('/favorite-action')->assertRedirect(route('login'));

    Socialite::fake('google', SocialiteUser::fake(['id' => '110082736']));
    $this->get(route('auth.google.callback'))->assertRedirect('/favorite-action');

    $this->get('/favorite-action')->assertOk()->assertSee('favorited');
});

it('remembers the session across requests once signed in', function (): void {
    Socialite::fake('google', SocialiteUser::fake(['id' => '110082736', 'name' => 'Ada Lovelace']));

    $this->get(route('auth.google.callback'));

    $this->assertAuthenticated();

    $this->get('/')->assertOk()->assertSee('Ada Lovelace');
});

it('signs the customer out again on sign out', function (): void {
    Socialite::fake('google', SocialiteUser::fake(['id' => '110082736']));
    $this->get(route('auth.google.callback'));
    $this->assertAuthenticated();

    $this->post(route('logout'))->assertRedirect(route('home'));

    $this->assertGuest();
});

it('ships no password, OTP, or email-verification screen', function (): void {
    $uris = collect(Route::getRoutes()->getRoutes())
        ->map(fn($route): string => $route->uri())
        ->implode(' ');

    expect($uris)->not->toContain('password')
        ->and($uris)->not->toContain('otp')
        ->and($uris)->not->toContain('verify');

    $this->get(route('login'))->assertOk()
        ->assertDontSee('type="password"', false)
        ->assertDontSee('One-time', false)
        ->assertDontSee('verification code', false);
});

it('starts a stateful PKCE Google redirect', function (): void {
    $response = $this->get(route('auth.google.redirect'));

    $response->assertRedirect();

    $location = (string) $response->headers->get('Location');

    expect($location)->toStartWith('https://accounts.google.com/o/oauth2/auth?')
        ->and($location)->toContain('code_challenge=')
        ->and($location)->toContain('code_challenge_method=S256')
        ->and($location)->toContain('state=')
        ->and($location)->toContain('scope=openid');

    $response->assertSessionHas('state');
    $response->assertSessionHas('code_verifier');
});
