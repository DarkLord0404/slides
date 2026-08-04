<x-layouts.app title="Mi estudio · Koqoi Slides">
<link rel="stylesheet" href="/app-workspace.css?v=1">
@php
    $totalSessions = $presentations->sum('sessions_count');
    $totalSlides = $presentations->sum('slides_count');
    $totalResponses = $presentations->sum(fn ($presentation) => $presentation->slides->sum(fn ($slide) => $slide->activities->sum(fn ($activity) => $activity->responses->count())));
@endphp
<div class="studio-page dashboard-page">
    <header class="studio-welcome">
        <div><span class="studio-kicker"><i></i> TU ESTUDIO</span><h1>Hola, {{ explode(' ', auth()->user()->name)[0] }}.</h1><p>¿Qué conversación quieres provocar hoy?</p></div>
        <button class="studio-create" onclick="document.getElementById('new').showModal()"><i>＋</i><span><b>Nueva presentación</b><small>Empieza desde una idea</small></span></button>
    </header>
    <section class="studio-summary">
        <article><i>▧</i><div><strong>{{ $presentations->count() }}</strong><span>Presentaciones</span></div></article>
        <article><i>▶</i><div><strong>{{ $totalSessions }}</strong><span>Sesiones realizadas</span></div></article>
        <article><i>✦</i><div><strong>{{ $totalResponses }}</strong><span>Respuestas recibidas</span></div></article>
        <article class="summary-note"><span>{{ $totalSlides }} diapositivas listas para cobrar vida.</span><a href="#decks">Ver biblioteca ↓</a></article>
    </section>
    <section id="decks" class="library-head"><div><span class="studio-kicker">BIBLIOTECA</span><h2>Tus presentaciones</h2></div><div class="library-filter"><button class="active">Recientes</button><button>Todas</button></div></section>
    <section class="studio-grid">
        <button class="new-deck-card" onclick="document.getElementById('new').showModal()"><i>＋</i><b>Crear una presentación</b><span>Elige un tema y empieza a construir</span></button>
        @foreach($presentations as $presentation)
            @php($cover=$presentation->slides->first())
            @php($responseCount=$presentation->slides->sum(fn($slide)=>$slide->activities->sum(fn($activity)=>$activity->responses->count())))
            <article class="studio-deck">
                <a class="studio-cover background-{{ data_get($cover?->design,'background_style','ivory') }} mode-{{ data_get($cover?->design,'background_mode',$cover?->background_path?'custom':'preset') }} {{ $cover?->background_path?'has-background-image':'' }}" href="{{ route('presentations.edit',$presentation) }}" style="--slide-color:{{ data_get($cover?->design,'background_color','#fffdf8') }};--title-color:{{ data_get($cover?->design,'title_color','#102a2e') }};--accent-color:{{ data_get($cover?->design,'accent_color','#ff6846') }};{{ $cover?->background_path?"--slide-image:url('".asset('storage/'.$cover->background_path)."')":'' }}"><span>01</span><h3>{{ $cover?->title ?: $presentation->title }}</h3><i></i><div class="cover-hover">Abrir estudio <b>→</b></div></a>
                <div class="studio-deck-copy"><div><h3>{{ $presentation->title }}</h3><p>{{ $presentation->description ?: 'Sin descripción todavía' }}</p></div><span class="deck-menu">•••</span></div>
                <div class="studio-deck-foot"><span>{{ $presentation->slides_count }} diap.</span><span>{{ $responseCount }} respuestas</span><form method="post" action="{{ route('presentations.start',$presentation) }}">@csrf<button title="Presentar">▶</button></form></div>
            </article>
        @endforeach
    </section>
    @if($presentations->isEmpty())<div class="studio-empty"><i>✦</i><h2>Tu primera historia empieza aquí</h2><p>Crea una presentación y convierte cada diapositiva en una conversación.</p><button onclick="document.getElementById('new').showModal()">Crear ahora</button></div>@endif
</div>
<dialog id="new" class="studio-dialog"><form method="post" action="{{ route('presentations.store') }}">@csrf<span class="studio-kicker">NUEVO PROYECTO</span><h2>¿Qué vas a presentar?</h2><label>Título<input name="title" placeholder="Ej. Taller de innovación" required autofocus></label><label>Descripción <small>Opcional</small><textarea name="description" rows="3" placeholder="Una frase para reconocerla después"></textarea></label><div class="actions"><button type="button" class="ghost" onclick="this.closest('dialog').close()">Cancelar</button><button class="btn">Crear presentación</button></div></form></dialog>
</x-layouts.app>
