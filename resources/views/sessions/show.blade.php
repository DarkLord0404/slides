@php
    $slides = $session->presentation->slides;
    $activeIndex = max(0, $slides->search(fn($slide) => $slide->id === $session->active_slide_id));
    $displayMode = request()->boolean('display');
@endphp
<x-layouts.app :title="'Presentando · '.$session->presentation->title" :stage="$displayMode">
<div class="presenter-shell {{ $displayMode ? 'display-mode' : '' }}" data-index="{{ $activeIndex }}">
    @unless($displayMode)
    <header class="presenter-toolbar"><div><span class="broadcast-dot"></span><b>EN DIRECTO</b><span>{{ $session->presentation->title }}</span></div><div class="toolbar-actions"><span class="people-count">{{ $session->participants->count() }} audiencia</span><button class="tool-btn" id="open-display">↗ Segunda pantalla</button><button class="tool-btn" id="fullscreen">⛶ Pantalla completa</button></div></header>
    @endunless
    <section class="stage-wrap"><div class="presentation-stage">
        @foreach($slides as $index => $slide)
        <article class="stage-slide {{ $index === $activeIndex ? 'is-active' : '' }} {{ $slide->activities->isNotEmpty() ? 'has-question' : '' }}" data-slide-id="{{ $slide->id }}">
            <div class="stage-accent"></div><span class="stage-kicker">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }} / {{ str_pad($slides->count(), 2, '0', STR_PAD_LEFT) }}</span>
            <div class="stage-content {{ mb_strlen($slide->body ?? '') > 700 ? 'is-long' : '' }}"><h1>{{ $slide->title ?: 'Sin título' }}</h1>@if($slide->body)<p>{{ $slide->body }}</p>@endif</div>
            @foreach($slide->activities as $activity)
            <div class="stage-question"><small>{{ ['multiple_choice'=>'ENCUESTA','open_text'=>'RESPUESTA ABIERTA','word_cloud'=>'NUBE DE PALABRAS','true_false'=>'VERDADERO O FALSO'][$activity->type] }}</small><h2>{{ $activity->question }}</h2>
                @if(in_array($activity->type,['multiple_choice','true_false']))<div class="stage-bars">@foreach($activity->options ?? [] as $option)@php($count=$activity->responses->where('answer',$option)->count()) @php($total=max(1,$activity->responses->count()))<div><span style="--value:{{ round($count/$total*100) }}%"><i>{{ $option }}</i></span><b>{{ $count }}</b></div>@endforeach</div>
                @else<div class="stage-answers">@forelse($activity->responses->take(12) as $response)<span>{{ $response->answer }}</span>@empty<em>Esperando respuestas…</em>@endforelse</div>@endif
                <footer>{{ $activity->responses->count() }} respuestas recibidas</footer>
            </div>
            @endforeach
        </article>
        @endforeach
    </div></section>
    <footer class="presenter-controls"><button id="prev">←</button><div><b id="position">{{ $activeIndex + 1 }}</b><span>/ {{ $slides->count() }}</span></div><button id="next">→</button></footer>
    @unless($displayMode)<aside class="audience-dock"><img src="{{ route('session.qr',$session) }}" alt="QR de acceso"><div><small>LA AUDIENCIA ENTRA CON</small><strong>{{ $session->code }}</strong><a href="{{ route('join.show',$session->code) }}" target="_blank">slides.koqoi.com/j/{{ $session->code }}</a></div></aside>@endunless
</div>
<script>
(()=>{const shell=document.querySelector('.presenter-shell'),slides=[...document.querySelectorAll('.stage-slide')],pos=document.querySelector('#position');let index=Number(shell.dataset.index)||0;const csrf=document.querySelector('meta[name="csrf-token"]').content;
const show=async(next,sync=true)=>{index=Math.max(0,Math.min(slides.length-1,next));slides.forEach((s,i)=>s.classList.toggle('is-active',i===index));pos.textContent=index+1;if(sync)await fetch('{{ route('sessions.slide',$session) }}',{method:'PUT',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},body:JSON.stringify({slide_id:slides[index].dataset.slideId})});};
document.querySelector('#prev')?.addEventListener('click',()=>show(index-1));document.querySelector('#next')?.addEventListener('click',()=>show(index+1));document.querySelector('#fullscreen')?.addEventListener('click',()=>document.documentElement.requestFullscreen());document.querySelector('#open-display')?.addEventListener('click',()=>window.open('{{ route('sessions.show',$session) }}?display=1','koqoi-display','popup,width=1600,height=900'));
addEventListener('keydown',e=>{if(['ArrowRight','PageDown',' '].includes(e.key)){e.preventDefault();show(index+1)}if(['ArrowLeft','PageUp'].includes(e.key)){e.preventDefault();show(index-1)}if(e.key==='f')document.documentElement.requestFullscreen()});@if($displayMode)setInterval(()=>location.reload(),3000);@endif})();
</script>
</x-layouts.app>
