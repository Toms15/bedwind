@extends('layouts.app')

@section('content')
  @include('partials.page-header')

  @if (!have_posts())
    <x-ui.alert type="warning">
      {{ __('Spiacenti, nessun risultato trovato.', 'sage') }}
    </x-ui.alert>

    {!! get_search_form(false) !!}
  @endif

  @while (have_posts())
    @php(the_post())
    @includeFirst(['partials.content-' . get_post_type(), 'partials.content'])
  @endwhile

  {!! get_the_posts_navigation() !!}
@endsection

@section('sidebar')
  @include('sections.sidebar')
@endsection
