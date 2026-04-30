@extends(config('affiliate.layout'))

@section('title', __('Affiliate Program'))

@section('content')
<div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">

    @if (session('status'))
        <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-emerald-900 shadow-sm dark:border-emerald-800/60 dark:bg-emerald-950/40 dark:text-emerald-200">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-6 rounded-xl border border-rose-200 bg-rose-50 px-5 py-4 text-rose-900 shadow-sm dark:border-rose-800/60 dark:bg-rose-950/40 dark:text-rose-200">
            <ul class="list-disc pl-5 space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php
        // A stub partner row may exist (just a share-code) before the user applies.
        // Distinguish "has applied" by `applied_at IS NOT NULL`.
        $hasPartner = $partner !== null;
        $hasApplied = $hasPartner && $partner->applied_at !== null;
        $isApproved = $hasPartner && $partner->status === 'approved';
        $isPending = $hasApplied && $partner->status === 'pending';
        $isRejected = $hasApplied && $partner->status === 'rejected';
        $isSuspended = $hasPartner && $partner->status === 'suspended';
        $isStubOnly = $hasPartner && ! $hasApplied && ! $isApproved && ! $isSuspended;
    @endphp

    {{-- Hero (compact, high-contrast) --}}
    <div class="relative overflow-hidden rounded-2xl bg-gray-900 p-5 text-white shadow-lg sm:p-6">
        <div class="grid grid-cols-1 gap-5 md:grid-cols-5 md:items-center">
            <div class="md:col-span-2">
                <div class="inline-flex items-center gap-2 rounded-full bg-emerald-500/20 px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-emerald-300">
                    <span class="size-1.5 rounded-full bg-emerald-400"></span>
                    {{ __('Affiliate Program') }}
                </div>
                <h1 class="mt-2 text-2xl font-bold leading-tight">{{ __('Earn :pct% lifetime revenue share', ['pct' => (int) $revenue_share_pct]) }}</h1>
                <p class="mt-1.5 text-sm text-gray-300">{{ __('Refer developers and businesses. As long as they pay, you keep earning.') }}</p>
            </div>
            <div class="md:col-span-3 rounded-xl bg-gray-800 p-3">
                <div class="text-[10px] font-medium uppercase tracking-wider text-gray-400">{{ __('Your share link') }}</div>
                <div x-data="affiliateCopyLink({{ Js::from($affiliate_link) }})" class="mt-1.5">
                    <div class="flex items-center gap-2 rounded-lg bg-gray-900 p-1.5 ring-1 ring-gray-700">
                        <input
                            readonly
                            :value="link"
                            class="w-full bg-transparent px-2 py-1 text-xs font-mono text-white focus:outline-none"
                        />
                        <button
                            type="button"
                            @click="copy()"
                            class="rounded-md bg-emerald-500 px-3 py-1 text-xs font-semibold text-white transition hover:bg-emerald-400"
                            x-text="copied ? '{{ __('Copied!') }}' : '{{ __('Copy') }}'"
                        ></button>
                    </div>
                    <div class="mt-2 flex flex-wrap gap-1.5 text-xs">
                        @include('affiliate::partials.share-buttons')
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- State branches --}}
    @if (! $hasPartner || $isStubOnly)
        @include('affiliate::partials.state-no-partner', [
            'eligible' => $eligible_to_apply,
            'referrals_needed' => $referrals_needed,
            'min_referred_users' => $min_referred_users,
            'referred_count' => $referred_count,
        ])
    @elseif ($isPending)
        @include('affiliate::partials.state-pending')
    @elseif ($isRejected)
        @include('affiliate::partials.state-rejected', ['reason' => $partner->rejection_reason])
    @elseif ($isSuspended)
        @include('affiliate::partials.state-suspended')
    @elseif ($isApproved)
        @include('affiliate::partials.state-approved', [
            'partner' => $partner,
            'stats' => $stats,
            'payoutRequests' => $payout_requests,
            'currency' => $currency,
        ])
    @endif

</div>

@push('scripts')
<script>
    function affiliateCopyLink(initial) {
        return {
            link: initial ?? '',
            copied: false,
            async copy() {
                if (! this.link) { return; }
                try {
                    await navigator.clipboard.writeText(this.link);
                    this.copied = true;
                    setTimeout(() => { this.copied = false; }, 1800);
                } catch (e) {
                    const el = document.createElement('textarea');
                    el.value = this.link;
                    document.body.appendChild(el);
                    el.select();
                    document.execCommand('copy');
                    document.body.removeChild(el);
                    this.copied = true;
                    setTimeout(() => { this.copied = false; }, 1800);
                }
            }
        }
    }
</script>
@endpush
@endsection
