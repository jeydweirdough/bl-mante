{{--
    Stands in for the payment provider's hosted page.

    Deliberately styled as somewhere else entirely: this is not part of the
    hotel site, and with a real provider configured the guest would be on a
    different domain. There are no card fields, because in a redirect checkout
    they belong to the provider and never reach this system.
--}}
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Secure checkout — simulator</title>
    @vite(['resources/css/app.css'])
</head>
<body class="h-full bg-zinc-900 text-zinc-100 font-sans antialiased">
    <div class="min-h-full flex items-center justify-center p-4">
        <div class="w-full max-w-md">
            <div class="flex items-center justify-between mb-4">
                <p class="text-sm font-semibold tracking-widest uppercase text-zinc-400">Payment simulator</p>
                <span class="rounded-full bg-amber-400/10 px-2.5 py-0.5 text-xs font-medium text-amber-300 ring-1 ring-inset ring-amber-400/30">
                    Mock provider
                </span>
            </div>

            <div class="rounded-2xl bg-zinc-800 p-6 shadow-xl ring-1 ring-white/10">
                <p class="text-sm text-zinc-400">{{ $session['description'] }}</p>

                <p class="mt-2 text-4xl font-bold tracking-tight">
                    {{ \App\Support\Money::format($session['amount_cents']) }}
                </p>

                <dl class="mt-6 space-y-2 text-sm border-t border-white/10 pt-4">
                    <div class="flex justify-between">
                        <dt class="text-zinc-400">Merchant</dt>
                        <dd>{{ config('hotel.name') }}</dd>
                    </div>
                    @if ($session['customer_name'])
                        <div class="flex justify-between">
                            <dt class="text-zinc-400">Cardholder</dt>
                            <dd>{{ $session['customer_name'] }}</dd>
                        </div>
                    @endif
                    <div class="flex justify-between">
                        <dt class="text-zinc-400">Reference</dt>
                        <dd class="font-mono text-xs">{{ $reference }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-zinc-400">Session expires</dt>
                        <dd>{{ \Carbon\Carbon::parse($session['expires_at'])->format('H:i') }}</dd>
                    </div>
                </dl>

                @if ($session['status'] !== 'pending')
                    <div class="mt-6 rounded-lg bg-white/5 px-4 py-3 text-sm">
                        This session is already <strong>{{ $session['status'] }}</strong>.
                        <a href="{{ $session['return_url'] }}" class="underline">Return to the hotel</a>.
                    </div>
                @else
                    <div class="mt-6 space-y-3">
                        <form method="POST" action="{{ route('payments.mock.approve', $reference) }}">
                            @csrf
                            <button type="submit" class="w-full rounded-lg bg-emerald-500 px-4 py-3 text-sm font-semibold text-emerald-950 hover:bg-emerald-400">
                                Approve payment
                            </button>
                        </form>

                        <form method="POST" action="{{ route('payments.mock.cancel', $reference) }}">
                            @csrf
                            <button type="submit" class="w-full rounded-lg border border-white/20 px-4 py-3 text-sm font-semibold text-zinc-200 hover:bg-white/5">
                                Cancel and go back
                            </button>
                        </form>
                    </div>

                    <p class="mt-4 text-xs text-zinc-500">
                        No card details are collected here, and none would be in the real flow either —
                        they would be entered on the provider's own page.
                    </p>
                @endif
            </div>
        </div>
    </div>
</body>
</html>
