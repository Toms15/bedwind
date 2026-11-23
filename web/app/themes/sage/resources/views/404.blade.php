@extends('layouts.app')

@section('content')
  @include('partials.page-header')

  @if (!have_posts())
    <x-ui.alert type="warning">
      {{ __('Spiacenti, la pagina che stai cercando non esiste.', 'sage') }}
    </x-ui.alert>

    {!! get_search_form(false) !!}
  @endif
@endsection
