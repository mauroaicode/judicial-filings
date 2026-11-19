@if(count($items) === 0)
    —
@elseif(count($items) === 1)
    {{ $items[0]['name'] }}
    @if(!empty($items[0]['identification']))
        <span class="muted">({{ $items[0]['identification'] }})</span>
    @endif
@else
    <ul class="subject-list">
        @foreach($items as $item)
            <li>
                {{ $item['name'] }}
                @if(!empty($item['identification']))
                    <span class="muted">({{ $item['identification'] }})</span>
                @endif
            </li>
        @endforeach
    </ul>
@endif
