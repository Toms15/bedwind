<div class="acf-block-placeholder border border-slate-400 p-6 flex items-start justify-start gap-3 flex-col">
  @if(!empty($block))
    <span class="bg-slate-400 w-auto inline-flex items-center justify-center h-6 px-2 rounded text-small text-black font-body font-medium">
      {{ $block['title'] }}
    </span>
    <span class="text-small text-black font-body font-medium">
      {{ $block['description'] }}
    </span>
  @endif
  @if(!empty($title))
    <span class="text-base text-black font-body font-normal">
      {{ wp_strip_all_tags($title) }}
    </span>
  @else
    <span class="text-base text-slate-500 font-body font-normal">
      {{ __('Nessun titolo impostato per questa sezione.', 'sage') }}
    </span>
  @endif
  <button
    type="button"
    class="components-button is-primary"
    data-acf-expanded-editor
  >
    {{ __('Modifica blocco', 'sage') }}
  </button>
</div>
