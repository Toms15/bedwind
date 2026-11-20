<article @php(post_class('h-entry'))>
  <header>
    <h1 class="p-name">
      {!! $title !!}
    </h1>

    @include('partials.entry-meta')
  </header>

  <div class="e-content">
    @php(the_content())
  </div>

  <x-share class="mt-8" :link="get_permalink()" :title="get_the_title()" />

  @if ($pagination())
    <footer>
      <nav class="page-nav" aria-label="{{ __('Navigazione pagine', 'sage') }}">
        {!! $pagination !!}
      </nav>
    </footer>
  @endif

  @php(comments_template())
</article>
