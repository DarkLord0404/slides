<x-layouts.app title="Koqoi Slides — Presentaciones que escuchan">
<link rel="stylesheet" href="/landing.css?v=2">

<div class="landing">
    <section class="landing-hero">
        <div class="hero-orbit orbit-one"></div><div class="hero-orbit orbit-two"></div>
        <div class="hero-copy reveal is-visible">
            <span class="landing-pill"><i></i> Interacción en tiempo real</span>
            <h1>No presentes para una audiencia. <em>Presenta con ella.</em></h1>
            <p>Crea diapositivas que preguntan, escuchan y reaccionan. Todos participan desde su teléfono, sin descargar nada y sin registrarse.</p>
            <div class="hero-actions">
                <a class="landing-primary" href="{{ auth()->check() ? route('presentations.index') : route('register') }}">Crear mi presentación <span>→</span></a>
                <a class="landing-secondary" href="#como-funciona"><i>▶</i> Ver cómo funciona</a>
            </div>
            <div class="hero-proof"><div class="avatar-stack"><span>AT</span><span>LM</span><span>JR</span><span>+</span></div><p><b>Sin límites aburridos.</b><br>Presenta, pregunta y aprende.</p></div>
        </div>

        <div class="product-scene reveal is-visible" data-parallax>
            <div class="scene-top"><span><i></i> EN VIVO</span><b>KOQOI</b><small><strong data-count="42">0</strong> conectados</small></div>
            <div class="scene-slide">
                <span class="scene-label">PREGUNTA 01</span>
                <h2>¿Qué hace que una idea se quede contigo?</h2>
                <div class="poll-list">
                    <div style="--score:82%"><span>Una historia poderosa</span><b>82%</b></div>
                    <div style="--score:64%"><span>Poder participar</span><b>64%</b></div>
                    <div style="--score:41%"><span>Un gran diseño</span><b>41%</b></div>
                </div>
                <div class="scene-ring"></div>
            </div>
            <div class="scene-foot"><span>Únete en <b>slides.koqoi.com</b></span><strong>847 219</strong></div>
            <div class="reaction reaction-a">🔥 <b>24</b></div><div class="reaction reaction-b">💡 <b>18</b></div>
        </div>
    </section>

    <section class="trust-strip reveal">
        <p>UNA SOLA HERRAMIENTA PARA</p><div><span>Clases</span><i></i><span>Talleres</span><i></i><span>Eventos</span><i></i><span>Equipos</span><i></i><span>Conferencias</span></div>
    </section>

    <section id="como-funciona" class="landing-section workflow">
        <div class="section-heading reveal"><span class="eyebrow">ASÍ DE FÁCIL</span><h2>De una idea a una sala participando en minutos.</h2><p>Construye la historia, comparte un código y deja que las respuestas transformen la conversación.</p></div>
        <div class="workflow-grid">
            <article class="reveal"><span>01</span><div class="workflow-icon editor-icon"><i></i><i></i><i></i></div><h3>Diseña libremente</h3><p>Usa la edición asistida o entra al lienzo avanzado para mover textos, imágenes, formas y capas.</p></article>
            <article class="reveal"><span>02</span><div class="workflow-icon phone-icon"><i>847<br>219</i></div><h3>Comparte un código</h3><p>Tu audiencia entra con un enlace o QR. Sin cuentas, instalaciones ni pasos innecesarios.</p></article>
            <article class="reveal"><span>03</span><div class="workflow-icon pulse-icon"><i></i><i></i><i></i></div><h3>Mira cómo cobra vida</h3><p>Votos, palabras, preguntas y reacciones aparecen mientras todos responden.</p></article>
        </div>
    </section>

    <section class="experience reveal">
        <div class="experience-copy"><span class="eyebrow">TODOS TIENEN VOZ</span><h2>La sala deja de mirar.<br><em>Empieza a participar.</em></h2><p>Encuestas, nubes de palabras, preguntas abiertas y verdadero o falso. Activa una interacción cuando la conversación la necesite.</p><ul><li><i>✓</i> Respuestas simultáneas</li><li><i>✓</i> Resultados visibles al instante</li><li><i>✓</i> Reacciones y “Me gusta”</li><li><i>✓</i> Historial documentado</li></ul></div>
        <div class="word-stage"><div class="word-head"><span><i></i> 68 respuestas</span><b>NUBE DE PALABRAS</b></div><div class="word-cloud"><strong>participar</strong><span>ideas</span><em>escuchar</em><b>conectar</b><small>aprender</small><i>crear</i><u>equipo</u><mark>conversar</mark></div><div class="word-live">Las palabras crecen con cada respuesta</div></div>
    </section>

    <section class="modes landing-section">
        <div class="section-heading reveal"><span class="eyebrow">TU MANERA DE CREAR</span><h2>Simple cuando quieres avanzar.<br>Potente cuando quieres diseñar.</h2></div>
        <div class="mode-switcher reveal">
            <div class="mode-tabs"><button class="active" data-mode="assisted">Edición asistida</button><button data-mode="advanced">Lienzo avanzado</button></div>
            <div class="mode-demo">
                <div class="mode-sidebar"><span></span><span></span><span></span><span></span></div>
                <div class="mode-canvas assisted active"><small>DIAPOSITIVA 01</small><h3>Empieza con una gran pregunta</h3><p>El diseño se organiza automáticamente para que siempre se vea bien.</p><i></i></div>
                <div class="mode-canvas advanced"><div class="selection-box">Mueve cada idea<div></div><i></i><i></i><i></i><i></i></div><span class="shape-one"></span><span class="shape-two"></span><small>1280 × 720</small></div>
                <div class="mode-pages"><span></span><span></span><span></span><span></span></div>
            </div>
            <div class="mode-copy"><div data-copy="assisted" class="active"><b>Enfócate en el mensaje</b><p>Edita título, contenido, tema e interacciones con una estructura clara y rápida.</p></div><div data-copy="advanced"><b>Controla cada detalle</b><p>Posiciona objetos, cambia tamaños, organiza capas y construye composiciones propias.</p></div></div>
        </div>
    </section>

    <section id="join" class="join-stage reveal">
        <div><span class="landing-pill light"><i></i> Participa ahora</span><h2>¿Ya tienes un código?</h2><p>Entra a la sesión desde cualquier dispositivo. No necesitas crear una cuenta.</p><div class="join-steps"><span><b>1</b> Escribe el código</span><span><b>2</b> Pon tu nombre</span><span><b>3</b> Participa</span></div></div>
        <form method="post" action="{{ route('join') }}">@csrf<div class="join-form-head"><span>ENTRAR A UNA PRESENTACIÓN</span><i>↗</i></div><label>Código de acceso<input name="code" inputmode="numeric" maxlength="6" placeholder="000 000" value="{{ old('code') }}" required></label><label>Tu nombre<input name="name" maxlength="80" placeholder="¿Cómo te llamas?" value="{{ old('name') }}" required></label><button>Entrar a la sesión <span>→</span></button><small>Conectarte es gratis y no requiere registro.</small></form>
    </section>

    <section class="final-cta reveal"><div class="cta-ring"></div><span class="eyebrow">TU PRÓXIMA PRESENTACIÓN</span><h2>Menos monólogo.<br><em>Más conversación.</em></h2><p>Crea una experiencia que tu audiencia quiera recordar.</p><a href="{{ auth()->check() ? route('presentations.index') : route('register') }}">Comenzar ahora <span>→</span></a></section>
</div>
<script defer src="/landing.js?v=2"></script>
</x-layouts.app>
