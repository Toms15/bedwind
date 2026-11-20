@php(the_content())

@if ($pagination())
  <nav class="page-nav" aria-label="{{ __('Navigazione pagine', 'sage') }}">
    {!! $pagination !!}
  </nav>
@endif
