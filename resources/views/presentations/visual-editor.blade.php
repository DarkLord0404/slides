@php
$editorData = [
    'id' => $presentation->id,
    'title' => $presentation->title,
    'edit_url' => route('presentations.edit', $presentation),
    'theme' => $presentation->theme ?: 'koqoi',
    'theme_settings' => $presentation->theme_settings ?? [],
    'theme_url' => route('presentations.theme', $presentation),
    'csrf' => csrf_token(),
    'slides' => $presentation->slides->map(function ($slide) {
        $elements = data_get($slide->design, 'elements', []);
        if (empty($elements)) {
            $elements = array_values(array_filter([
                $slide->title ? ['id' => 'legacy-title-'.$slide->id, 'type' => 'text', 'x' => 110, 'y' => 155, 'width' => 900, 'height' => 120, 'rotation' => 0, 'text' => $slide->title, 'fill' => data_get($slide->design, 'title_color', '#102a2e'), 'fontSize' => 68, 'fontFamily' => 'Arial'] : null,
                $slide->body ? ['id' => 'legacy-body-'.$slide->id, 'type' => 'text', 'x' => 115, 'y' => 315, 'width' => 880, 'height' => 210, 'rotation' => 0, 'text' => $slide->body, 'fill' => data_get($slide->design, 'body_color', '#536568'), 'fontSize' => 30, 'fontFamily' => 'Arial'] : null,
            ]));
        }
        return [
            'id' => $slide->id,
            'position' => $slide->position,
            'title' => $slide->title,
            'elements' => $elements,
            'design' => $slide->design ?? [],
            'background_url' => $slide->background_path ? asset('storage/'.$slide->background_path) : null,
            'save_url' => route('slides.canvas', $slide),
            'activity_url' => route('activities.store', $slide),
            'activities' => $slide->activities->map(fn ($activity) => [
                'id' => $activity->id,
                'type' => $activity->type,
                'question' => $activity->question,
                'options' => $activity->options ?? [],
                'delete_url' => route('activities.destroy', $activity),
            ])->values(),
        ];
    })->values(),
];
@endphp
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>Editor visual · {{ $presentation->title }}</title>@vite(['resources/js/visual-editor.tsx'])</head><body><div id="visual-editor" data-presentation='@json($editorData)'></div><details class="visual-theme"><summary>◐ Tema</summary><form method="post" enctype="multipart/form-data" action="{{ route('presentations.theme',$presentation) }}">@csrf @method('PUT')<label>Estilo<select name="theme"><option value="koqoi" @selected($presentation->theme==='koqoi' || !$presentation->theme)>Koqoi · aros</option><option value="minimal" @selected($presentation->theme==='minimal')>Minimalista</option><option value="ocean" @selected($presentation->theme==='ocean')>Océano</option><option value="sunset" @selected($presentation->theme==='sunset')>Atardecer</option><option value="forest" @selected($presentation->theme==='forest')>Bosque</option><option value="night" @selected($presentation->theme==='night')>Noche</option><option value="custom" @selected($presentation->theme==='custom')>Personalizado</option></select></label><div><label>Fondo<input type="color" name="background_color" value="{{ data_get($presentation->theme_settings,'background_color','#fffdf8') }}"></label><label>Título<input type="color" name="title_color" value="{{ data_get($presentation->theme_settings,'title_color','#102a2e') }}"></label><label>Texto<input type="color" name="body_color" value="{{ data_get($presentation->theme_settings,'body_color','#536568') }}"></label><label>Acento<input type="color" name="accent_color" value="{{ data_get($presentation->theme_settings,'accent_color','#ff6846') }}"></label></div><label>Decoración<select name="decoration"><option value="circle">Aros</option><option value="none">Sin decoración</option></select></label><label>Imagen<input type="file" name="background_image" accept="image/*"></label><button>Aplicar a todas</button></form></details></body></html>
