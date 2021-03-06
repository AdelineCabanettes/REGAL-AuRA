@extends('app')

@section('content')

  @include('groups.tabs')
  <div class="tab_content">

    <div class="action">
      <h2 class="name">
        <a href="{{ route('groups.actions.index', [$group->id]) }}">{{trans('messages.actions')}}</a> <i class="fa fa-angle-right"></i>
        {{ $action->name }} <a href="{{ route('groups.actions.edit', [$group->id, $action->id]) }}" class="btn btn-primary btn-xs">{{trans('messages.edit')}}</a>
      </h2>

      <div class="meta">{{trans('messages.started_by')}} <span class="user"><a href="{{ route('users.show', [$action->user->id]) }}">{{ $action->user->name}}</a></span>, {{trans('messages.in')}} <a href="{{ route('groups.actions.index', [$group->id]) }}">{{ $action->group->name}}</a> {{ $action->created_at->diffForHumans()}} </div>

      <h4>{{trans('messages.what')}} ?</h4>

      <p class="body">
        {!! filter($action->body) !!}
      </p>

      <h4>{{trans('messages.when')}} ?</h4>
      <p>{{trans('messages.begins')}} : {{$action->start->format('d/m/Y H:i')}}</p>
      <p>{{trans('messages.ends')}} : {{$action->stop->format('d/m/Y H:i')}}</p>

      <h4>{{trans('messages.where')}} ?</h4>
      <p>{{$action->location}}</p>

      @if ($action->revisionHistory->count() > 0)
        <a class="btn btn-primary btn-xs" href="{{route('groups.actions.history', [$group->id, $action->id])}}"><i class="fa fa-history"></i> {{trans('messages.show_history')}}</a>
      @endif

      @can('delete', $action)
        <a class="btn btn-primary btn-xs" href="{{ route('groups.actions.deleteconfirm', [$group->id, $action->id]) }}"><i class="fa fa-trash"></i>
          {{trans('messages.delete')}}
        </a>
      @endcan

    </div>

  </div>


@endsection
