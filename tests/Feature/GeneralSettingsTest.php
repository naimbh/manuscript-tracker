<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class GeneralSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_account_is_admin_and_can_change_site_settings(): void
    {
        $admin = User::factory()->create();
        $other = User::factory()->create();
        $this->assertTrue($admin->fresh()->is_admin);
        $this->assertFalse($other->fresh()->is_admin);

        $this->actingAs($other)->get('/settings/general')->assertForbidden();

        Volt::actingAs($admin)->test('settings.general')
            ->set('name', 'Lab Paper Desk')->set('tagline', '')->set('registration', false)->set('timezone', 'Asia/Dhaka')
            ->call('save')->assertHasNoErrors();

        Setting::applyToConfig();
        $this->assertSame('Lab Paper Desk', config('app.name'));
        $this->assertSame('', config('tracker.tagline'));
        $this->assertFalse(config('tracker.registration'));
        $this->assertSame('Asia/Dhaka', config('app.timezone'));

        auth()->logout();
        $this->get('/register')->assertNotFound();
        $this->get('/login')->assertDontSee('Sign up')->assertSee('Lab Paper Desk');
    }
}
