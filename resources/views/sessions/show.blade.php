@php
    $slides = $session->presentation->slides;
    $activeIndex = max(0, $slides->search(fn($slide) => $slide->id === $session->active_slide_id));
    $displayMode = request()->boolean('display');
    $totalResponses = $slides->sum(fn($slide) => $slide->activities->sum(fn($activity) => $activity->responses->count()));
@endphp
<x-layouts.app :title="'Presentando · '.$session->presentation->title" :stage="$displayMode">
@if($session->status === 'ended')
<section class="session-ended"><span>SESIÓN FINALIZADA</span><h1>{{ $session->presentation->title }}</h1><p>La participación fue cerrada y todas las respuestas quedaron documentadas.</p><div class="ended-stats"><div><b>{{ $session->participants->count() }}</b><small>PARTICIPANTES</small></div><div><b>{{ $totalResponses }}</b><small>RESPUESTAS</small></div><div><b>{{ $slides->count() }}</b><small>DIAPOSITIVAS</small></div></div><div class="actions"><a class="btn" href="{{ route('presentations.edit',$session->presentation) }}">Volver al editor</a><a class="ghost" href="{{ route('presentations.index') }}">Mis presentaciones</a></div></section>
@else
<div class="presenter-shell {{ $displayMode ? 'display-mode' : '' }}" data-index="{{ $activeIndex }}">
    @unless($displayMode)<header class="presenter-toolbar"><div><span class="broadcast-dot"></span><b>EN DIRECTO</b><span>{{ $session->presentation->title }}</span></div><div class="toolbar-actions"><button class="tool-btn" id="open-display">↗ Segunda pantalla</button><button class="tool-btn" id="fullscreen">⛶ Pantalla completa</button></div></header>@endunless
    <section class="stage-wrap"><div class="presentation-stage">
        @foreach($slides as $index => $slide)
        @php($layout = data_get($slide->design, 'layout', $slide->activities->isNotEmpty() ? 'question' : 'content'))
        <article class="stage-slide layout-{{ $layout }} {{ $index === $activeIndex ? 'is-active' : '' }} {{ $slide->activities->isNotEmpty() ? 'has-question' : '' }}" data-slide-id="{{ $slide->id }}">
            <div class="stage-accent"></div><span class="stage-kicker">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }} / {{ str_pad($slides->count(), 2, '0', STR_PAD_LEFT) }}</span>
            <div class="stage-content {{ mb_strlen($slide->body ?? '') > 700 ? 'is-long' : '' }}"><h1>{{ $slide->title ?: 'Sin título' }}</h1>@if($slide->body)<p>{{ $slide->body }}</p>@endif</div>
            @foreach($slide->activities as $activity)<div class="stage-question"><small>{{ ['multiple_choice'=>'ENCUESTA','open_text'=>'RESPUESTA ABIERTA','word_cloud'=>'NUBE DE PALABRAS','true_false'=>'VERDADERO O FALSO'][$activity->type] }}</small><h2>{{ $activity->question }}</h2>
                @if(in_array($activity->type,['multiple_choice','true_false']))<div class="stage-bars">@foreach($activity->options ?? [] as $option)@php($count=$activity->responses->where('answer',$option)->count()) @php($total=max(1,$activity->responses->count()))<div><span style="--value:{{ round($count/$total*100) }}%"><i>{{ $option }}</i></span><b>{{ $count }}</b></div>@endforeach</div>
                @else<div class="stage-answers">@forelse($activity->responses->take(12) as $response)<span>{{ $response->answer }}</span>@empty<em>Esperando respuestas…</em>@endforelse</div>@endif<footer>{{ $activity->responses->count() }} respuestas recibidas</footer></div>@endforeach
        </article>@endforeach
    </div></section>
    <footer class="presenter-console">
        <div class="console-nav"><button id="prev">←</button><b id="position">{{ $activeIndex + 1 }}</b><span>/ {{ $slides->count() }}</span><button id="next">→</button></div>
        <div class="console-live"><span><i></i><b id="connected-count">{{ $session->participants->count() }}</b> conectados</span><span><b>{{ $totalResponses }}</b> respuestas</span></div>
        <div class="console-code"><span>CÓDIGO</span><strong>{{ $session->code }}</strong></div>
        @unless($displayMode)<img src="{{ route('session.qr',$session) }}" alt="QR de acceso"><form method="post" action="{{ route('sessions.end',$session) }}" onsubmit="return confirm('¿Terminar la sesión? La audiencia ya no podrá responder.')">@csrf<button class="end-session">Terminar sesión</button></form>@endunless
    </footer>
</div>
<script>
(()=>{const shell=document.querySelector('.presenter-shell'),slides=[...document.querySelectorAll('.stage-slide')],pos=document.querySelector('#position'),connected=document.querySelector('#connected-count');let index=Number(shell.dataset.index)||0,ticks=0;const csrf=document.querySelector('meta[name="csrf-token"]').content;
const show=async(next,sync=true)=>{index=Math.max(0,Math.min(slides.length-1,next));slides.forEach((s,i)=>s.classList.toggle('is-active',i===index));pos.textContent=index+1;if(sync)await fetch('{{ route('sessions.slide',$session) }}',{method:'PUT',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},body:JSON.stringify({slide_id:slides[index].dataset.slideId})});};
document.querySelector('#prev')?.addEventListener('click',()=>show(index-1));document.querySelector('#next')?.addEventListener('click',()=>show(index+1));document.querySelector('#fullscreen')?.addEventListener('click',()=>document.documentElement.requestFullscreen());document.querySelector('#open-display')?.addEventListener('click',()=>window.open('{{ route('sessions.show',$session) }}?display=1','koqoi-display','popup,width=1600,height=900'));addEventListener('keydown',e=>{if(['ArrowRight','PageDown',' '].includes(e.key)){e.preventDefault();show(index+1)}if(['ArrowLeft','PageUp'].includes(e.key)){e.preventDefault();show(index-1)}if(e.key==='f')document.documentElement.requestFullscreen()});
setInterval(async()=>{const r=await fetch('{{ route('session.state',$session->code) }}');if(!r.ok)return;const s=await r.json();if(connected)connected.textContent=s.participants;if(s.status==='ended')location.reload();const current=Number(slides[index]?.dataset.slideId);if({{ $displayMode ? 'true' : 'false' }}&&(s.active_slide_id!==current||++ticks%3===0))location.reload()},2500)})();
</script>
@endif
</x-layouts.app>
