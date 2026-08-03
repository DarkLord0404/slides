<x-layouts.app :title="'Sesión terminada · '.$live->presentation->title">
<section class="session-ended audience-ended"><span>SESIÓN FINALIZADA</span><h1>Gracias por participar, {{ $participant->name }}.</h1><p>El presentador cerró <b>{{ $live->presentation->title }}</b>. Tus respuestas quedaron guardadas.</p><a class="btn" href="/">Volver al inicio</a></section>
</x-layouts.app>
