<dl class="smk-review">
    @foreach ($rows as $label => $value)
        <div>
            <dt>{{ $label }}</dt>
            <dd>{{ $value }}</dd>
        </div>
    @endforeach
</dl>
