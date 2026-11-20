<form role="search" method="get" class="search-form" action="{{ home_url('/') }}">
  <label>
    <span class="sr-only">
      {{ _x('Cerca:', 'label', 'sage') }}
    </span>

    <input
      type="search"
      placeholder="{!! esc_attr_x('Cerca &hellip;', 'placeholder', 'sage') !!}"
      value="{{ get_search_query() }}"
      name="s">
  </label>

  <button>{{ _x('Cerca', 'submit button', 'sage') }}</button>
</form>
