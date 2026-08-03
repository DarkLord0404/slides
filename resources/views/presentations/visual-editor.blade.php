@php
$editorData = [
    'id' => $presentation->id,
    'title' => $presentation->title,
    'edit_url' => route('presentations.edit', $presentation),
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
            'save_url' => route('slides.canvas', $slide),
        ];
    })->values(),
];
@endphp
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>Editor visual · {{ $presentation->title }}</title>@vite(['resources/js/visual-editor.tsx'])</head><body><div id="visual-editor" data-presentation='@json($editorData)'></div></body></html>
