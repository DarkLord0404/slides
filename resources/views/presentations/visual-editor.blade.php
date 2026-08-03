@php
$editorData = [
    'id' => $presentation->id,
    'title' => $presentation->title,
    'edit_url' => route('presentations.edit', $presentation),
    'slides' => $presentation->slides->map(function ($slide) {
        return [
            'id' => $slide->id,
            'position' => $slide->position,
            'title' => $slide->title,
            'elements' => data_get($slide->design, 'elements', []),
            'save_url' => route('slides.canvas', $slide),
        ];
    })->values(),
];
@endphp
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>Editor visual · {{ $presentation->title }}</title>@vite(['resources/js/visual-editor.tsx'])</head><body><div id="visual-editor" data-presentation='@json($editorData)'></div></body></html>
