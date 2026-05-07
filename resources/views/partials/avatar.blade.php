{{--
    Reusable user avatar partial.
    Props:
      $user   — User model instance
      $size   — Tailwind size classes, e.g. 'w-10 h-10' (default)
      $rounded — Tailwind rounded class, e.g. 'rounded-full' (default) or 'rounded-xl'
--}}
@php
    $size    = $size    ?? 'w-10 h-10';
    $rounded = $rounded ?? 'rounded-full';
    $avatar  = method_exists($user, 'avatarUrl') ? $user->avatarUrl() : $user->avatar_url;
@endphp

<div
    class="{{ $size }} {{ $rounded }} overflow-hidden bg-indigo-100 dark:bg-indigo-900/40 flex items-center justify-center shrink-0">
    @if($avatar)
        <img src="{{ $avatar }}" alt="{{ $user->name }}" class="w-full h-full object-cover">
    @else
        <span class="font-bold text-indigo-600 dark:text-indigo-400 text-sm leading-none select-none">
            {{ strtoupper(substr($user->name, 0, 1)) }}
        </span>
    @endif
</div>
