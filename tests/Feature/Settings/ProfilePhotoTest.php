<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ProfilePhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_cropped_photo_is_saved_served_and_replaced(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['name' => 'Ada Lovelace']);
        $this->actingAs($user);

        $this->get('/settings/profile')->assertOk()->assertSee('Upload photo…')->assertDontSee('wire:click="removePhoto"', false);

        Volt::test('settings.profile')
            ->set('photo', UploadedFile::fake()->image('avatar.png', 512, 512))
            ->call('savePhoto')
            ->assertHasNoErrors()
            ->assertReturned(true)
            ->assertDispatched('avatar-updated', url: $user->fresh()->avatarUrl());

        $first = $user->fresh()->avatar_path;
        $this->assertMatchesRegularExpression('#^avatars/[A-Za-z0-9]{40}\.png$#', $first);
        Storage::disk('local')->assertExists($first);

        $this->get($user->fresh()->avatarUrl())->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get('/settings/profile')->assertSee($user->fresh()->avatarUrl(), false)->assertSee('Remove');

        // A new photo replaces the old file.
        Volt::test('settings.profile')
            ->set('photo', UploadedFile::fake()->image('avatar.png', 512, 512))
            ->call('savePhoto')
            ->assertHasNoErrors();
        $second = $user->fresh()->avatar_path;
        $this->assertNotSame($first, $second);
        Storage::disk('local')->assertMissing($first);
        Storage::disk('local')->assertExists($second);

        Volt::test('settings.profile')->call('removePhoto')->assertDispatched('avatar-updated', url: null);
        $this->assertNull($user->fresh()->avatar_path);
        Storage::disk('local')->assertMissing($second);
        $this->get(route('profile.photo'))->assertNotFound();
    }

    public function test_photos_are_validated(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create());

        Volt::test('settings.profile')
            ->set('photo', UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'))
            ->call('savePhoto')
            ->assertHasErrors(['photo'])
            ->assertSee('Choose a PNG, JPEG or WebP image.');

        Volt::test('settings.profile')
            ->set('photo', UploadedFile::fake()->image('avatar.png', 600, 400))
            ->call('savePhoto')
            ->assertHasErrors(['photo'])
            ->assertSee('The photo must be 512 × 512 pixels.');

        Volt::test('settings.profile')
            ->set('photo', UploadedFile::fake()->image('avatar.png', 512, 512)->size(3000))
            ->call('savePhoto')
            ->assertHasErrors(['photo' => 'max']);

        $this->assertNull(auth()->user()->fresh()->avatar_path);
    }

    public function test_the_photo_route_needs_a_signed_in_user_and_serves_only_their_photo(): void
    {
        Storage::fake('local');
        $this->get(route('profile.photo'))->assertRedirect(route('login'));

        $owner = User::factory()->create();
        Storage::disk('local')->put('avatars/owner.png', 'png-bytes');
        $owner->setAvatar('avatars/owner.png');

        $this->actingAs(User::factory()->create())->get(route('profile.photo'))->assertNotFound();
        $this->actingAs($owner)->get(route('profile.photo'))->assertOk();
    }

    public function test_deleting_the_account_deletes_the_photo(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        Storage::disk('local')->put('avatars/me.png', 'png-bytes');
        $user->setAvatar('avatars/me.png');

        $user->delete();

        Storage::disk('local')->assertMissing('avatars/me.png');
    }
}
