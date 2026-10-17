@props(['class' => ''])
<a href="{{ route('home') }}" {{ $attributes->merge(['class' => 'group inline-flex items-center gap-3 '.$class]) }} aria-label="{{ settings('company.trading_name') }}, home">
    <img src="{{ asset('images/brand/mark-gold.png') }}" alt="" class="size-9 transition-transform duration-700 group-hover:rotate-45" width="36" height="36">
    <span class="flex flex-col leading-none">
        <span class="text-[.62rem] font-semibold tracking-[.32em] text-mist">BLACK &amp; WHITE</span>
        <span class="mt-1 font-display text-[1.45rem] font-semibold tracking-[.18em] text-bone">OPS</span>
    </span>
</a>
