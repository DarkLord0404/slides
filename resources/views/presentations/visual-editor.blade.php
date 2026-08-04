@php
$selectedSlideId = request()->integer('slide');
$editorSlides = $selectedSlideId ? $presentation->slides->where('id', $selectedSlideId) : $presentation->slides;
$editorData = [
    'id' => $presentation->id,
    'title' => $presentation->title,
    'edit_url' => route('presentations.index'),
    'slide_url' => route('slides.store', $presentation),
    'reorder_url' => route('slides.reorder', $presentation),
    'background_url' => route('presentations.background', $presentation),
    'theme' => $presentation->theme ?: 'koqoi',
    'theme_settings' => $presentation->theme_settings ?? [],
    'theme_url' => route('presentations.theme', $presentation),
    'csrf' => csrf_token(),
    'slides' => $editorSlides->values()->map(function ($slide, $index) {
        $elements = data_get($slide->design, 'elements', []);
        if ($index === 0 && empty($elements)) {
            $elements = array_values(array_filter([
                $slide->title ? ['id' => 'legacy-title-'.$slide->id, 'type' => 'text', 'x' => 110, 'y' => 155, 'width' => 900, 'height' => 120, 'rotation' => 0, 'text' => $slide->title, 'fill' => data_get($slide->design, 'title_color', '#102a2e'), 'fontSize' => 68, 'fontFamily' => 'Arial'] : null,
                $slide->body ? ['id' => 'legacy-body-'.$slide->id, 'type' => 'text', 'x' => 115, 'y' => 315, 'width' => 880, 'height' => 210, 'rotation' => 0, 'text' => $slide->body, 'fill' => data_get($slide->design, 'body_color', '#536568'), 'fontSize' => 30, 'fontFamily' => 'Arial'] : null,
            ]));
        }
        return [
            'id' => $slide->id,
            'position' => $slide->position,
            'title' => $slide->title,
            'elements' => $index === 0 ? $elements : [],
            'loaded' => $index === 0,
            'load_url' => route('slides.canvas.load', $slide),
            'thumbnail_url' => route('slides.thumbnail', $slide),
            'save_url' => route('slides.canvas', $slide),
            'delete_url' => route('slides.destroy', $slide),
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
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>Editor visual · {{ $presentation->title }}</title>@vite(['resources/js/visual-editor.tsx'])</head><body><div id="visual-editor" data-presentation='@json($editorData)'></div></body></html>
