{{--
  Componente avviso.

  Props:
  - type (string|null)    Variante: 'success', 'caution', 'warning' o info (default).
  - message (string|null) Testo dell'avviso, sempre escapato. Se assente si usa lo
                          slot: per contenuto HTML fidato passare il markup nello slot.
  - role (string|null)    'status' (default) o 'alert'. 'alert' è una live region
                          assertiva: passarlo esplicitamente solo per messaggi
                          inseriti dinamicamente, non per testo statico.

  Senza message né slot il componente non renderizza nulla.

  Colori: testo black-950 su sfondo *-400, contrasto ≥ 6.3:1 su tutte le varianti.
  Gli attributi extra (class, id, ...) vengono uniti al contenitore.
--}}
{{-- blade-formatter-disable --}}
@props([
  'type' => null,
  'message' => null,
  'role' => null,
])

@php
  $variant = match ($type) {
    'success' => 'bg-green-400',
    'caution' => 'bg-yellow-400',
    'warning' => 'bg-red-400',
    default => 'bg-indigo-400',
  };

  $role = in_array($role, ['alert', 'status'], true) ? $role : 'status';

  $content = filled($message) ? $message : $slot;

  $attributes = $attributes
    ->class(['px-2 py-1 text-black-950', $variant])
    ->merge(['role' => $role]);
@endphp
{{-- blade-formatter-enable --}}

@if (filled(trim((string) $content)))
  <div {{ $attributes }}>
    {{ $content }}
  </div>
@endif
