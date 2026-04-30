@php
    $shareText = urlencode(__('Check out SharpAPI — AI-powered REST APIs. Use my link:'));
    $linkEnc = urlencode($link ?? '');
@endphp
<a x-bind:href="`https://twitter.com/intent/tweet?text=${encodeURIComponent('{{ __('Check out SharpAPI — use my link:') }} ' + link)}`" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-md bg-gray-700 px-2.5 py-1 text-[11px] font-medium text-white transition hover:bg-gray-600">
    <svg class="size-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
    {{ __('Twitter') }}
</a>
<a x-bind:href="`https://www.linkedin.com/sharing/share-offsite/?url=${encodeURIComponent(link)}`" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-md bg-gray-700 px-2.5 py-1 text-[11px] font-medium text-white transition hover:bg-gray-600">
    <svg class="size-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M20.45 20.45h-3.55v-5.57c0-1.33-.02-3.04-1.85-3.04-1.85 0-2.13 1.45-2.13 2.94v5.67H9.36V9h3.41v1.56h.05c.48-.91 1.65-1.86 3.39-1.86 3.62 0 4.29 2.38 4.29 5.48zM5.34 7.43A2.06 2.06 0 1 1 5.34 3.3a2.06 2.06 0 0 1 0 4.13zM7.12 20.45H3.56V9h3.56z"/></svg>
    {{ __('LinkedIn') }}
</a>
<a x-bind:href="`mailto:?subject=${encodeURIComponent('{{ __('SharpAPI invite') }}')}&body=${encodeURIComponent('{{ __('Try SharpAPI with my link:') }} ' + link)}`" class="inline-flex items-center gap-1.5 rounded-md bg-gray-700 px-2.5 py-1 text-[11px] font-medium text-white transition hover:bg-gray-600">
    {{ __('Email') }}
</a>
<a x-bind:href="`https://wa.me/?text=${encodeURIComponent('{{ __('Try SharpAPI with my link:') }} ' + link)}`" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-md bg-gray-700 px-2.5 py-1 text-[11px] font-medium text-white transition hover:bg-gray-600">
    {{ __('WhatsApp') }}
</a>
