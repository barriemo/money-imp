<h1>Executive Actions</h1>

@foreach ($actions as $action)

    <a href="{{ route('executive-actions.show', $action) }}">
        <h2>
            [{{ $action->score }}]
            {{ $action->title }}
        </h2>

        <p>
            {{ $action->description }}
        </p>

    </a>

@endforeach