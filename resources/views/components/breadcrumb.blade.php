@props(['links'])

<nav class="d-flex mb-0" aria-label="breadcrumb">
    <ol class="breadcrumb mb-0" style="font-size: 0.85rem; font-weight: 600;">
        {{-- Link Statis ke Home / Dashboard --}}
        <li class="breadcrumb-item">
            <a href="{{ route('dashboard') }}" class="text-decoration-none text-primary">
                <i class="fa-solid fa-home me-1"></i> {{ __('erp.breadcrumb_home') }}
            </a>
        </li>
        
        {{-- Loop Dinamis untuk Path Menu --}}
        @foreach($links as $label => $url)
            @if(!$loop->last && !empty($url) && $url !== '#')
                <li class="breadcrumb-item">
                    <a href="{{ $url }}" class="text-decoration-none text-primary">{{ $label }}</a>
                </li>
            @elseif(!$loop->last && $url === '#')
                <li class="breadcrumb-item text-muted">{{ $label }}</li>
            @else
                <li class="breadcrumb-item active text-secondary" aria-current="page">{{ $label }}</li>
            @endif
        @endforeach
    </ol>
</nav>
