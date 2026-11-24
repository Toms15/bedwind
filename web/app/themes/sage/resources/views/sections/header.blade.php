{{-- $customLogo and $isFrontPage (App composer) are lazy variables, always truthy: call them to get the value. --}}
@php($logo = $customLogo())

<header class="banner">
  @if ($logo !== '')
    <div class="brand [&_.custom-logo]:h-auto [&_.custom-logo]:max-h-20 [&_.custom-logo]:w-auto">
      {!! $logo !!}
    </div>
  @else
    <a class="brand no-underline" href="{{ home_url('/') }}" rel="home"
      @if ($isFrontPage()) aria-current="page" @endif>
      {{ $siteName }}
    </a>
  @endif

  @if (has_nav_menu('primary_navigation'))
    <nav class="nav-primary [&_a]:no-underline" aria-label="{{ __('Menu principale', 'sage') }}">
      {!! wp_nav_menu(['theme_location' => 'primary_navigation', 'menu_class' => 'nav', 'echo' => false]) !!}
    </nav>
  @endif
</header>
