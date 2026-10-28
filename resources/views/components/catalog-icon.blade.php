@props(['name' => 'home'])
<svg {{ $attributes->class(['h-5 w-5 shrink-0']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('building')<path d="M5 21V3h14v18M3 21h18M9 7h1m4 0h1M9 11h1m4 0h1M9 15h1m4 0h1M10 21v-3h4v3"/>@break
        @case('bed')<path d="M3 18V7m18 11V7M3 14h18M5 14V9h14v5M7 9V6h10v3M3 18v2m18-2v2"/>@break
        @case('shop')<path d="M4 10v11h16V10M3 10l2-7h14l2 7M3 10c0 3 4 3 4 0 0 3 5 3 5 0 0 3 5 3 5 0 0 3 4 3 4 0M9 21v-7h6v7"/>@break
        @case('sofa')<path d="M5 11V7a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v4M5 14h14M5 11a2 2 0 0 0-4 0v7h22v-7a2 2 0 0 0-4 0v3M5 11v3M4 18v3m16-3v3"/>@break
        @case('university')<path d="m2 9 10-5 10 5-10 5-10-5ZM6 11v6c4 3 8 3 12 0v-6M22 9v8"/>@break
        @case('pin')<path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>@break
        @case('search')<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4.5 4.5"/>@break
        @default<path d="m3 10 9-7 9 7M5 9v12h14V9M9 21v-7h6v7"/>
    @endswitch
</svg>
