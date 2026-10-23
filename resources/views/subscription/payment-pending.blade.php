<x-guest-layout>
    <div class="text-center">
        <div class="text-4xl mb-4">⏳</div>
        <h2 class="text-xl font-bold text-gray-800 mb-2">Payment Under Review</h2>
        <p class="text-gray-600 text-sm mb-6">
            Thank you for submitting your payment proof. We are reviewing it and will activate your account shortly — usually within a few hours.
        </p>

        <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 text-sm text-amber-800 mb-6">
            <p class="font-medium mb-1">Need help? Contact us:</p>
            <p>📞 WhatsApp / Call: <strong>0327-9754246</strong></p>
        </div>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="text-sm text-gray-500 underline">Sign out</button>
        </form>
    </div>
</x-guest-layout>
