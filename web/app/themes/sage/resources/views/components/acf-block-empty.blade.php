<div class="acf-block-placeholder border border-slate-400 bg-[repeating-linear-gradient(-45deg,_rgb(144_161_185_/_50%)_0,_rgb(144_161_185_/_50%)_4px,_transparent_0,_transparent_50%)] bg-[size:12px_12px] p-6 flex items-start justify-start gap-3 flex-col">
  @if(!empty($block))
    <span class="bg-slate-400 w-auto inline-flex items-center justify-center h-6 px-2 rounded text-small text-black font-body font-medium">
      {{ $block['title'] }}
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
