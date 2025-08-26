<header class="banner">
  <a class="brand" href="{{ home_url('/') }}">
    {!! $siteName !!}
  </a>

  <p class="text-3xl text-red-500 font-bold bg-red-100">Test</p>

  @if (has_nav_menu('primary_navigation'))
    <nav class="nav-primary" aria-label="{{ wp_get_nav_menu_name('primary_navigation') }}">
      {!! wp_nav_menu(['theme_location' => 'primary_navigation', 'menu_class' => 'nav', 'echo' => false]) !!}
    </nav>
  @endif
</header>
