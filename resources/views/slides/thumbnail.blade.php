<svg xmlns="http://www.w3.org/2000/svg" width="320" height="180" viewBox="0 0 1280 720">
@foreach($elements as $element)
@if(($element['type'] ?? '') === 'rect')
<rect x="{{ $element['x'] ?? 0 }}" y="{{ $element['y'] ?? 0 }}" width="{{ $element['width'] ?? 0 }}" height="{{ $element['height'] ?? 0 }}" fill="{{ $element['fill'] ?? '#ffffff' }}" transform="rotate({{ $element['rotation'] ?? 0 }} {{ ($element['x'] ?? 0)+(($element['width'] ?? 0)/2) }} {{ ($element['y'] ?? 0)+(($element['height'] ?? 0)/2) }})"/>
@elseif(($element['type'] ?? '') === 'ellipse')
<ellipse cx="{{ ($element['x'] ?? 0)+(($element['width'] ?? 0)/2) }}" cy="{{ ($element['y'] ?? 0)+(($element['height'] ?? 0)/2) }}" rx="{{ ($element['width'] ?? 0)/2 }}" ry="{{ ($element['height'] ?? 0)/2 }}" fill="none" stroke="{{ $element['fill'] ?? '#ff6846' }}" stroke-width="{{ $element['strokeWidth'] ?? 30 }}"/>
@elseif(($element['type'] ?? '') === 'image' && !empty($element['src']))
<image href="{{ $element['src'] }}" x="{{ $element['x'] ?? 0 }}" y="{{ $element['y'] ?? 0 }}" width="{{ $element['width'] ?? 0 }}" height="{{ $element['height'] ?? 0 }}" preserveAspectRatio="xMidYMid slice"/>
@elseif(($element['type'] ?? '') === 'text')
<foreignObject x="{{ $element['x'] ?? 0 }}" y="{{ $element['y'] ?? 0 }}" width="{{ $element['width'] ?? 100 }}" height="{{ $element['height'] ?? 50 }}"><div xmlns="http://www.w3.org/1999/xhtml" style="color:{{ $element['fill'] ?? '#102a2e' }};font:{{ str_contains($element['fontStyle'] ?? '', 'italic') ? 'italic' : 'normal' }} {{ str_contains($element['fontStyle'] ?? '', 'bold') ? '700' : '400' }} {{ $element['fontSize'] ?? 40 }}px/{{ 1.15 }} '{{ $element['fontFamily'] ?? 'Arial' }}';white-space:pre-wrap;overflow:hidden">{{ $element['text'] ?? '' }}</div></foreignObject>
@endif
@endforeach
</svg>
