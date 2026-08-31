<h1>
    {{ $action->title }}
</h1>

<p>
    {{ $action->description }}
</p>

<h3>Priority</h3>

<p>
    {{ $action->score }}
</p>

<h3>Recommended action</h3>

<p>
    {{ $action->recommended_action }}
</p>

<h3>Evidence</h3>

<pre>
{{ json_encode(
    $action->evidence,
    JSON_PRETTY_PRINT
) }}
</pre>