@php
    $logo = app(\App\Settings\ColorSettings::class)->logo;
@endphp

@if(!is_null($logo) && \Illuminate\Support\Facades\Storage::disk('public')->exists($logo))
    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($logo) }}?v={{ \Illuminate\Support\Facades\Storage::disk('public')->lastModified($logo) }}"
         alt="{{ config('app.name') }}"
         class="h-8 max-w-full object-contain"/>
@else
    <span class="text-2xl font-semibold tracking-tight">{{ config('app.name') }}</span>
@endif
