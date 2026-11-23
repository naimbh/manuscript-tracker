<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public string $name = '';
    public string $email = '';

    /** The cropped photo (512 × 512 PNG or WebP), uploaded by the crop dialog. */
    public $photo = null;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],

            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($user->id)
            ],
        ]);

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->dispatch('profile-updated', name: $user->name);
    }

    /**
     * Store the cropped profile photo in private storage. Returns true when it was saved.
     */
    public function savePhoto(): bool
    {
        $this->validate(
            ['photo' => ['required', 'file', 'mimes:png,webp', 'max:2048', 'dimensions:width=512,height=512']],
            [
                'photo.required' => 'Choose a photo first.',
                'photo.file' => 'Choose a PNG, JPEG or WebP image.',
                'photo.mimes' => 'Choose a PNG, JPEG or WebP image.',
                'photo.max' => 'That photo is too large (max 2 MB).',
                'photo.dimensions' => 'The photo must be 512 × 512 pixels.',
            ],
        );

        $user = Auth::user();
        $ext = $this->photo->guessExtension() === 'webp' ? 'webp' : 'png';
        $user->setAvatar($this->photo->storeAs('avatars', Str::random(40).'.'.$ext, 'local'));
        $this->photo = null;
        $this->dispatch('avatar-updated', url: $user->avatarUrl());

        return true;
    }

    /**
     * Remove the profile photo; the initials are shown again.
     */
    public function removePhoto(): void
    {
        Auth::user()->setAvatar(null);
        $this->dispatch('avatar-updated', url: null);
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout heading="Profile" subheading="Update your name and email address">
        <div class="mt-6" x-data="avatarPhoto" role="group" aria-labelledby="photo-label">
            <flux:label id="photo-label">{{ __('Photo') }}</flux:label>
            <div
                class="mt-2 -ml-2 flex items-center gap-4 rounded-2xl p-2 transition"
                :class="dragOver && 'bg-indigo-50 outline-2 outline-dashed outline-indigo-500 dark:bg-indigo-500/10'"
                x-on:dragenter.prevent="dragOver = true"
                x-on:dragover.prevent="dragOver = true"
                x-on:dragleave="if (! $el.contains($event.relatedTarget)) dragOver = false"
                x-on:drop.prevent="dropped($event)"
            >
                <button
                    type="button"
                    class="group relative shrink-0 rounded-full focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500"
                    aria-label="{{ auth()->user()->avatar_path ? __('Change photo') : __('Upload photo') }}"
                    x-on:click="choose()"
                >
                    <x-ui.avatar :user="auth()->user()" size="size-16" />
                    <span class="pointer-events-none absolute inset-[2px] flex items-center justify-center rounded-full bg-zinc-950/45 text-white opacity-0 transition group-hover:opacity-100 group-focus-visible:opacity-100">
                        <flux:icon.camera variant="mini" class="size-5" />
                    </span>
                </button>
                <div class="min-w-0 space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <flux:button size="sm" icon="arrow-up-tray" data-photo-upload x-on:click="choose()">{{ __('Upload photo…') }}</flux:button>
                        @if (auth()->user()->avatar_path)
                            <flux:button size="sm" variant="ghost" icon="trash" wire:click="removePhoto" wire:confirm="{{ __('Remove your photo? Your initials will be shown instead.') }}">{{ __('Remove') }}</flux:button>
                        @endif
                    </div>
                    <flux:text size="sm">{{ __('PNG, JPEG or WebP, up to 10 MB. You can also drop an image here.') }}</flux:text>
                </div>
            </div>
            <input type="file" x-ref="file" class="sr-only" tabindex="-1" aria-hidden="true" accept="image/png,image/jpeg,image/webp,image/heic,image/heif,.png,.jpg,.jpeg,.webp,.heic,.heif" x-on:change="picked($event)" />
            <div x-show="error" x-cloak role="alert" aria-live="polite" class="mt-3 text-sm font-medium text-red-500 dark:text-red-400">
                <flux:icon.exclamation-triangle variant="mini" class="inline" /> <span x-text="error"></span>
            </div>

            <flux:modal name="avatar-crop" class="w-full max-w-[26rem]" :dismissible="false" x-on:close="closed()">
                <div class="space-y-5">
                    <div class="pr-8">
                        <flux:heading size="lg">{{ __('Crop your photo') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('Drag to move it. Scroll, pinch or use the slider to zoom.') }}</flux:text>
                    </div>

                    <div wire:ignore class="relative mx-auto aspect-square w-full max-w-[22rem] overflow-hidden rounded-xl bg-zinc-100 ring-1 ring-zinc-200 dark:bg-zinc-900 dark:ring-zinc-700">
                        <canvas
                            id="avatar-crop-stage"
                            tabindex="0"
                            role="img"
                            aria-label="{{ __('Photo crop area') }}"
                            aria-describedby="avatar-crop-keys"
                            class="block size-full cursor-grab touch-none outline-none select-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-inset active:cursor-grabbing"
                        ></canvas>
                    </div>
                    <p id="avatar-crop-keys" class="sr-only">{{ __('Use the arrow keys to move the photo (hold Shift for bigger steps), and plus or minus to zoom.') }}</p>

                    <div class="flex items-center gap-2">
                        <flux:button size="sm" variant="ghost" square icon="minus" aria-label="{{ __('Zoom out') }}" x-bind:disabled="zoom <= minZoom" x-on:click="zoomBy(-0.25)" />
                        <label class="sr-only" for="avatar-crop-zoom">{{ __('Zoom') }}</label>
                        <input
                            id="avatar-crop-zoom"
                            type="range"
                            step="0.01"
                            :min="minZoom"
                            :max="maxZoom"
                            :value="zoom"
                            :aria-valuetext="zoom.toFixed(1) + '×'"
                            class="h-1.5 flex-1 cursor-pointer accent-indigo-600 dark:accent-indigo-400"
                            x-on:input="setZoom($event.target.valueAsNumber)"
                        />
                        <flux:button size="sm" variant="ghost" square icon="plus" aria-label="{{ __('Zoom in') }}" x-bind:disabled="zoom >= maxZoom" x-on:click="zoomBy(0.25)" />
                        <span class="w-10 text-right text-xs text-zinc-500 tabular-nums dark:text-zinc-400" aria-hidden="true" x-text="zoom.toFixed(1) + '×'">1.0×</span>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <flux:button size="sm" variant="subtle" icon="arrow-path" x-on:click="rotate()">{{ __('Rotate 90°') }}</flux:button>
                        <flux:button size="sm" variant="subtle" icon="arrow-uturn-left" x-on:click="reset()">{{ __('Reset') }}</flux:button>
                    </div>

                    <flux:error name="photo" />
                    <div x-show="uploadError" x-cloak role="alert" class="text-sm font-medium text-red-500 dark:text-red-400">
                        <flux:icon.exclamation-triangle variant="mini" class="inline" /> <span x-text="uploadError"></span>
                    </div>

                    <div class="flex items-center gap-2">
                        <flux:spacer />
                        <flux:modal.close><flux:button variant="ghost" x-bind:disabled="saving">{{ __('Cancel') }}</flux:button></flux:modal.close>
                        <flux:button variant="primary" x-on:click="save()" x-bind:disabled="saving">{{ __('Save') }}</flux:button>
                    </div>
                </div>
            </flux:modal>
        </div>

        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <flux:input wire:model="name" label="{{ __('Name') }}" type="text" name="name" required autofocus autocomplete="name" />

            <div>
                <flux:input wire:model="email" label="{{ __('Email') }}" type="email" name="email" required autocomplete="email" />

                @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail &&! auth()->user()->hasVerifiedEmail())
                    <div>
                        <p class="mt-2 text-sm text-gray-800">
                            {{ __('Your email address is unverified.') }}

                            <button
                                wire:click.prevent="resendVerificationNotification"
                                class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-hidden focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                            >
                                {{ __('Click here to re-send the verification email.') }}
                            </button>
                        </p>

                        @if (session('status') === 'verification-link-sent')
                            <p class="mt-2 text-sm font-medium text-green-600">
                                {{ __('A new verification link has been sent to your email address.') }}
                            </p>
                        @endif
                    </div>
                @endif
            </div>

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full">{{ __('Save') }}</flux:button>
                </div>

                <x-action-message class="me-3" on="profile-updated">
                    {{ __('Saved.') }}
                </x-action-message>
            </div>
        </form>

        <livewire:settings.delete-user-form />
    </x-settings.layout>
</section>
