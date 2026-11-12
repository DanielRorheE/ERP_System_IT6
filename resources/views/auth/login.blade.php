<x-guest-layout>
    <x-auth-session-status class="mb-4" :status="session('status')" />

    @unless ($pinConfigured)
        <p class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800" role="status">
            Owner sign-in is not configured. Set a six-digit <code>MANAGER_SHARED_PIN</code> in your local <code>.env</code> file.
        </p>
    @endunless

    <form class="auth-form" method="POST" action="{{ route('login') }}">
        @csrf

        <div class="auth-form-group">
            <x-input-label for="pin" :value="__('6-digit owner PIN')" />
            <x-text-input
                id="pin"
                class="auth-input mt-1 block w-full text-center font-mono text-2xl tracking-[0.7em]"
                type="password"
                name="pin"
                placeholder="••••••"
                required
                autofocus
                inputmode="numeric"
                pattern="[0-9]{6}"
                minlength="6"
                maxlength="6"
                autocomplete="one-time-code"
                aria-describedby="pin-help"
            />
            <p id="pin-help" class="mt-2 text-center text-xs text-stone-500">Enter your shared owner PIN.</p>
            <x-input-error :messages="$errors->get('pin')" class="mt-2" />
        </div>

        <button class="auth-submit" type="submit">{{ __('Sign in to workspace') }} <span aria-hidden="true">→</span></button>
    </form>
</x-guest-layout>
