<x-guest-layout>
    <div class="text-center">
        <div class="text-4xl mb-4">⏰</div>
        <h2 class="text-xl font-bold text-gray-800 mb-2">{{ __('Subscription Expired') }}</h2>
        <p class="text-gray-600 text-sm mb-6">
            {{ __('Your SahulatPOS subscription has expired. Please contact us to renew your plan and continue using the system.') }}
        </p>

        <div class="bg-gray-50 rounded-lg p-4 text-sm text-gray-700 mb-6">
            <p class="font-medium mb-1">{{ __('To renew, contact:') }}</p>
            <p>📞 WhatsApp / Call: <strong>+92 300 0000000</strong></p>
            <p>📧 Email: <strong>info@sahulatpos.com</strong></p>
        </div>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="text-sm text-gray-500 underline">{{ __('Sign out') }}</button>
        </form>
    </div>
</x-guest-layout>
