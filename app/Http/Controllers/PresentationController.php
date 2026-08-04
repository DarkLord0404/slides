<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\LiveSession;
use App\Models\Presentation;
use App\Models\Slide;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PresentationController extends Controller
{
    private function owned(Presentation $presentation): void
    {
        abort_unless($presentation->user_id === auth()->id(), 403);
    }

    public function index()
    {
        return view('presentations.index', ['presentations' => auth()->user()->presentations()->with('slides.activities.responses')->withCount(['slides', 'sessions'])->latest()->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:150'], 'description' => ['nullable', 'string', 'max:1000']]);
        $presentation = auth()->user()->presentations()->create($data);
        $presentation->slides()->create(['position' => 1, 'title' => 'Tu primera diapositiva', 'body' => 'Agrega contenido y una actividad interactiva.']);

        return redirect()->route('presentations.edit', $presentation);
    }

    public function edit(Presentation $presentation)
    {
        $this->owned($presentation);
        $this->loadEditorSlides($presentation);

        return view('presentations.visual-editor', compact('presentation'));
    }

    public function visualEditor(Presentation $presentation)
    {
        $this->owned($presentation);
        $this->loadEditorSlides($presentation);

        return view('presentations.visual-editor', compact('presentation'));
    }

    private function loadEditorSlides(Presentation $presentation): void
    {
        $slides = $presentation->slides()
            ->select(['id', 'presentation_id', 'position', 'title', 'body'])
            ->with('activities')
            ->orderBy('position')
            ->get();
        $requestedId = request()->integer('slide');
        $activeId = $requestedId ?: $slides->first()?->id;
        if ($activeId) {
            $activeSlide = $presentation->slides()->with('activities')->findOrFail($activeId);
            $slides = $slides->map(fn ($slide) => $slide->id === $activeSlide->id ? $activeSlide : $slide);
        }
        $presentation->setRelation('slides', $slides);
    }

    public function saveCanvas(Request $request, Slide $slide)
    {
        $this->owned($slide->presentation);
        $data = $request->validate(['elements' => ['present', 'array', 'max:200']]);
        $titleElement = collect($data['elements'])->firstWhere('id', 'legacy-title-'.$slide->id);
        $bodyElement = collect($data['elements'])->firstWhere('id', 'legacy-body-'.$slide->id);
        $slide->update([
            'title' => $titleElement['text'] ?? $slide->title,
            'body' => $bodyElement['text'] ?? $slide->body,
            'design' => [...($slide->design ?? []), 'elements' => $data['elements']],
        ]);

        return response()->json(['saved' => true, 'saved_at' => now()->toIso8601String()]);
    }

    public function loadCanvas(Slide $slide)
    {
        $this->owned($slide->presentation);
        $elements = data_get($slide->design, 'elements', []);
        if (empty($elements)) {
            $elements = array_values(array_filter([
                $slide->title ? ['id' => 'legacy-title-'.$slide->id, 'type' => 'text', 'x' => 110, 'y' => 155, 'width' => 900, 'height' => 120, 'rotation' => 0, 'text' => $slide->title, 'fill' => data_get($slide->design, 'title_color', '#102a2e'), 'fontSize' => 68, 'fontFamily' => 'Arial'] : null,
                $slide->body ? ['id' => 'legacy-body-'.$slide->id, 'type' => 'text', 'x' => 115, 'y' => 315, 'width' => 880, 'height' => 210, 'rotation' => 0, 'text' => $slide->body, 'fill' => data_get($slide->design, 'body_color', '#536568'), 'fontSize' => 30, 'fontFamily' => 'Arial'] : null,
            ]));
        }

        return response()->json(['elements' => $elements]);
    }

    public function slideThumbnail(Slide $slide)
    {
        $this->owned($slide->presentation);

        return response()
            ->view('slides.thumbnail', ['elements' => data_get($slide->design, 'elements', [])])
            ->header('Content-Type', 'image/svg+xml')
            ->header('Cache-Control', 'private, max-age=15');
    }

    public function updatePresentation(Request $request, Presentation $presentation)
    {
        $this->owned($presentation);
        $presentation->update($request->validate(['title' => ['required', 'string', 'max:150'], 'description' => ['nullable', 'string', 'max:1000']]));

        return back()->with('ok', 'Información de la presentación actualizada.');
    }

    public function themeSettings(Presentation $presentation)
    {
        $this->owned($presentation);

        return redirect()->route('presentations.edit', $presentation);
    }

    public function addSlide(Request $request, Presentation $presentation)
    {
        $this->owned($presentation);
        $data = $request->validate(['title' => ['nullable', 'string', 'max:120'], 'body' => ['nullable', 'string', 'max:900']]);
        $presentation->slides()->create([...$data, 'position' => ($presentation->slides()->max('position') ?? 0) + 1]);

        return back()->with('ok', 'Diapositiva agregada.');
    }

    public function updateSlide(Request $request, Slide $slide)
    {
        $this->owned($slide->presentation);
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['nullable', 'string', 'max:900'],
            'layout' => ['nullable', 'in:cover,content,split,question'],
            'background_style' => ['nullable', 'in:ivory,ocean,sunset,forest,night,custom'],
            'background_mode' => ['nullable', 'in:preset,custom'],
            'background_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'title_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'body_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'question_background_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'question_text_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'decoration' => ['nullable', 'in:circle,none'],
            'background_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_background_image' => ['nullable', 'boolean'],
        ]);
        $backgroundPath = $slide->background_path;
        if ($request->boolean('remove_background_image') && $backgroundPath) {
            Storage::disk('public')->delete($backgroundPath);
            $backgroundPath = null;
        }
        if ($request->hasFile('background_image')) {
            if ($backgroundPath) {
                Storage::disk('public')->delete($backgroundPath);
            }
            $backgroundPath = $request->file('background_image')->store('slide-backgrounds', 'public');
        }
        $elements = collect(data_get($slide->design, 'elements', []))->map(function ($element) use ($data, $slide) {
            if (($element['id'] ?? null) === 'legacy-title-'.$slide->id) {
                $element['text'] = $data['title'] ?? '';
                $element['fill'] = $data['title_color'] ?? '#102a2e';
            }
            if (($element['id'] ?? null) === 'legacy-body-'.$slide->id) {
                $element['text'] = $data['body'] ?? '';
                $element['fill'] = $data['body_color'] ?? '#536568';
            }

            return $element;
        })->values()->all();
        $slide->update([
            'title' => $data['title'] ?? null,
            'body' => $data['body'] ?? null,
            'background_path' => $backgroundPath,
            'design' => [
                ...($slide->design ?? []),
                'layout' => $data['layout'] ?? 'content',
                'background_style' => $data['background_style'] ?? 'ivory',
                'background_mode' => $data['background_mode'] ?? 'preset',
                'background_color' => $data['background_color'] ?? '#fffdf8',
                'title_color' => $data['title_color'] ?? '#102a2e',
                'body_color' => $data['body_color'] ?? '#536568',
                'accent_color' => $data['accent_color'] ?? '#ff6846',
                'question_background_color' => $data['question_background_color'] ?? '#102a2e',
                'question_text_color' => $data['question_text_color'] ?? '#ffffff',
                'decoration' => $data['decoration'] ?? 'circle',
                'elements' => $elements,
            ],
        ]);

        return back()->with('ok', 'Diapositiva guardada.');
    }

    public function updateTheme(Request $request, Presentation $presentation)
    {
        $this->owned($presentation);
        $data = $request->validate([
            'theme' => ['required', 'in:koqoi,minimal,ocean,sunset,forest,night,custom'],
            'background_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'title_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'body_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'decoration' => ['nullable', 'in:circle,none'],
            'background_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_background_image' => ['nullable', 'boolean'],
        ]);
        $presets = [
            'koqoi' => ['background_style' => 'ivory', 'background_color' => '#fffdf8', 'title_color' => '#102a2e', 'body_color' => '#536568', 'accent_color' => '#ff6846', 'decoration' => 'circle'],
            'minimal' => ['background_style' => 'custom', 'background_color' => '#ffffff', 'title_color' => '#171717', 'body_color' => '#525252', 'accent_color' => '#007f7b', 'decoration' => 'none'],
            'ocean' => ['background_style' => 'ocean', 'background_color' => '#dff7f5', 'title_color' => '#073b4c', 'body_color' => '#275d66', 'accent_color' => '#06b6d4', 'decoration' => 'circle'],
            'sunset' => ['background_style' => 'sunset', 'background_color' => '#fff0e6', 'title_color' => '#5b2133', 'body_color' => '#7a4050', 'accent_color' => '#f97346', 'decoration' => 'circle'],
            'forest' => ['background_style' => 'forest', 'background_color' => '#eaf4e2', 'title_color' => '#173d2b', 'body_color' => '#41634f', 'accent_color' => '#72a276', 'decoration' => 'none'],
            'night' => ['background_style' => 'night', 'background_color' => '#111827', 'title_color' => '#ffffff', 'body_color' => '#d1d5db', 'accent_color' => '#a78bfa', 'decoration' => 'circle'],
        ];
        $settings = $data['theme'] === 'custom'
            ? ['background_style' => 'custom', 'background_color' => $data['background_color'] ?? '#fffdf8', 'title_color' => $data['title_color'] ?? '#102a2e', 'body_color' => $data['body_color'] ?? '#536568', 'accent_color' => $data['accent_color'] ?? '#ff6846', 'decoration' => $data['decoration'] ?? 'none']
            : $presets[$data['theme']];
        $imagePath = data_get($presentation->theme_settings, 'background_path');
        if ($request->boolean('remove_background_image') && $imagePath) {
            Storage::disk('public')->delete($imagePath);
            $imagePath = null;
        }
        if ($request->hasFile('background_image')) {
            if ($imagePath) {
                Storage::disk('public')->delete($imagePath);
            }
            $imagePath = $request->file('background_image')->store('presentation-themes', 'public');
        }
        $settings['background_path'] = $imagePath;
        DB::transaction(function () use ($presentation, $data, $settings) {
            $presentation->update(['theme' => $data['theme'], 'theme_settings' => $settings]);
            foreach ($presentation->slides as $slide) {
                $elements = collect(data_get($slide->design, 'elements', []))->reject(fn ($element) => in_array($element['id'] ?? null, ['theme-background', 'theme-background-image'], true) || str_starts_with($element['id'] ?? '', 'theme-ring-'))->map(function ($element) use ($slide, $settings) {
                    if (($element['id'] ?? null) === 'legacy-title-'.$slide->id) {
                        $element['fill'] = $settings['title_color'];
                    }
                    if (($element['id'] ?? null) === 'legacy-body-'.$slide->id) {
                        $element['fill'] = $settings['body_color'];
                    }

                    return $element;
                });
                $backgrounds = collect([['id' => 'theme-background', 'type' => 'rect', 'x' => 0, 'y' => 0, 'width' => 1280, 'height' => 720, 'rotation' => 0, 'fill' => $settings['background_color']]]);
                if ($settings['background_path']) {
                    $backgrounds->push(['id' => 'theme-background-image', 'type' => 'image', 'x' => 0, 'y' => 0, 'width' => 1280, 'height' => 720, 'rotation' => 0, 'src' => Storage::disk('public')->url($settings['background_path'])]);
                }
                if ($settings['decoration'] === 'circle') {
                    $backgrounds->push(['id' => 'theme-ring-1', 'type' => 'ellipse', 'x' => 1030, 'y' => -170, 'width' => 430, 'height' => 430, 'rotation' => 0, 'fill' => $settings['accent_color'], 'strokeWidth' => 42]);
                    $backgrounds->push(['id' => 'theme-ring-2', 'type' => 'ellipse', 'x' => -170, 'y' => 535, 'width' => 340, 'height' => 340, 'rotation' => 0, 'fill' => $settings['accent_color'], 'strokeWidth' => 34]);
                }
                if (! $elements->contains(fn ($element) => ($element['id'] ?? null) === 'legacy-title-'.$slide->id) && $slide->title) {
                    $elements->push(['id' => 'legacy-title-'.$slide->id, 'type' => 'text', 'x' => 110, 'y' => 155, 'width' => 900, 'height' => 120, 'rotation' => 0, 'text' => $slide->title, 'fill' => $settings['title_color'], 'fontSize' => 68, 'fontFamily' => 'Arial']);
                }
                if (! $elements->contains(fn ($element) => ($element['id'] ?? null) === 'legacy-body-'.$slide->id) && $slide->body) {
                    $elements->push(['id' => 'legacy-body-'.$slide->id, 'type' => 'text', 'x' => 115, 'y' => 315, 'width' => 880, 'height' => 210, 'rotation' => 0, 'text' => $slide->body, 'fill' => $settings['body_color'], 'fontSize' => 30, 'fontFamily' => 'Arial']);
                }
                $elements = $backgrounds->concat($elements)->values()->all();
                $slide->update(['design' => [...($slide->design ?? []), ...$settings, 'background_mode' => $settings['background_path'] ? 'custom' : 'preset', 'elements' => $elements]]);
            }
        });

        return back()->with('ok', 'Tema aplicado a toda la presentación.');
    }

    public function reorderSlides(Request $request, Presentation $presentation)
    {
        $this->owned($presentation);
        $data = $request->validate(['slide_ids' => ['required', 'array', 'min:1'], 'slide_ids.*' => ['required', 'integer', 'distinct']]);
        abort_unless($presentation->slides()->pluck('id')->sort()->values()->all() === collect($data['slide_ids'])->sort()->values()->all(), 422);
        DB::transaction(function () use ($data, $presentation) {
            foreach ($data['slide_ids'] as $index => $slideId) {
                $presentation->slides()->whereKey($slideId)->update(['position' => $index + 1]);
            }
        });

        return response()->json(['saved' => true]);
    }

    public function applyBackground(Request $request, Presentation $presentation)
    {
        $this->owned($presentation);
        $data = $request->validate([
            'background_elements' => ['present', 'array', 'min:1', 'max:8'],
            'background_elements.*.id' => ['required', 'string', 'regex:/^theme-/'],
            'background_elements.*.type' => ['required', 'in:rect,ellipse'],
            'background_elements.*.x' => ['required', 'numeric'],
            'background_elements.*.y' => ['required', 'numeric'],
            'background_elements.*.width' => ['required', 'numeric', 'min:1'],
            'background_elements.*.height' => ['required', 'numeric', 'min:1'],
            'background_elements.*.rotation' => ['nullable', 'numeric'],
            'background_elements.*.fill' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'background_elements.*.strokeWidth' => ['nullable', 'numeric', 'min:0'],
        ]);
        DB::transaction(function () use ($presentation, $data) {
            foreach ($presentation->slides as $slide) {
                $content = collect(data_get($slide->design, 'elements', []))
                    ->reject(fn ($element) => str_starts_with($element['id'] ?? '', 'theme-'));
                $slide->update(['design' => [
                    ...($slide->design ?? []),
                    'background_color' => $data['background_elements'][0]['fill'],
                    'elements' => collect($data['background_elements'])->concat($content)->values()->all(),
                ]]);
            }
        });

        return response()->json(['saved' => true]);
    }

    public function deleteSlide(Slide $slide)
    {
        $this->owned($slide->presentation);
        abort_if($slide->presentation->slides()->count() <= 1, 422, 'La presentación debe conservar al menos una diapositiva.');
        $presentation = $slide->presentation;
        if ($slide->background_path) {
            Storage::disk('public')->delete($slide->background_path);
        }
        $slide->delete();
        foreach ($presentation->slides()->orderBy('position')->get() as $index => $remainingSlide) {
            $remainingSlide->update(['position' => $index + 1]);
        }

        return back()->with('ok', 'Diapositiva eliminada.');
    }

    public function addActivity(Request $request, Slide $slide)
    {
        $this->owned($slide->presentation);
        $data = $request->validate(['type' => ['required', 'in:multiple_choice,open_text,word_cloud,true_false'], 'question' => ['required', 'string', 'max:220'], 'options_text' => ['nullable', 'string', 'max:500']]);
        $options = collect(preg_split('/\R/', $data['options_text'] ?? ''))->map(fn ($x) => trim($x))->filter()->values()->all();
        if ($data['type'] === 'true_false') {
            $options = ['Verdadero', 'Falso'];
        }
        $slide->activities()->create(['type' => $data['type'], 'question' => $data['question'], 'options' => $options]);

        return back()->with('ok', 'Actividad agregada.');
    }

    public function updateActivity(Request $request, Activity $activity)
    {
        $this->owned($activity->slide->presentation);
        $data = $request->validate(['type' => ['required', 'in:multiple_choice,open_text,word_cloud,true_false'], 'question' => ['required', 'string', 'max:220'], 'options_text' => ['nullable', 'string', 'max:500']]);
        $options = collect(preg_split('/\R/', $data['options_text'] ?? ''))->map(fn ($x) => trim($x))->filter()->values()->all();
        if ($data['type'] === 'true_false') {
            $options = ['Verdadero', 'Falso'];
        }
        $activity->update(['type' => $data['type'], 'question' => $data['question'], 'options' => $options]);

        return back()->with('ok', 'Interacción actualizada.');
    }

    public function deleteActivity(Activity $activity)
    {
        $this->owned($activity->slide->presentation);
        $activity->delete();

        return back()->with('ok', 'Interacción eliminada.');
    }

    public function start(Presentation $presentation)
    {
        $this->owned($presentation);
        $presentation->sessions()->where('status', 'live')->update(['status' => 'ended', 'ended_at' => now()]);
        do {
            $code = (string) random_int(100000, 999999);
        } while (LiveSession::where('code', $code)->exists());
        $firstSlide = $presentation->slides()->first();
        $session = $presentation->sessions()->create(['code' => $code, 'status' => 'live', 'started_at' => now(), 'active_slide_id' => $firstSlide?->id, 'active_activity_id' => null]);

        return redirect()->route('sessions.show', $session);
    }

    public function showSession(LiveSession $session)
    {
        $this->owned($session->presentation);
        $session->load('presentation.slides.activities.responses', 'presentation.slides.reactions', 'participants');

        return view('sessions.show', compact('session'));
    }

    public function changeSlide(Request $request, LiveSession $session)
    {
        $this->owned($session->presentation);
        $data = $request->validate(['slide_id' => ['required', 'integer']]);
        $slide = $session->presentation->slides()->findOrFail($data['slide_id']);
        $session->update(['active_slide_id' => $slide->id, 'active_activity_id' => null]);

        return response()->json(['active_slide_id' => $slide->id, 'active_activity_id' => $session->active_activity_id]);
    }

    public function changeActivity(Request $request, LiveSession $session)
    {
        $this->owned($session->presentation);
        $data = $request->validate(['activity_id' => ['nullable', 'integer']]);
        $activityId = $data['activity_id'] ?? null;
        if ($activityId === null) {
            $session->update(['active_activity_id' => null]);
        } else {
            $activity = Activity::where('slide_id', $session->active_slide_id)->findOrFail($activityId);
            $session->update(['active_activity_id' => $activity->id]);
        }

        return response()->json(['active_activity_id' => $session->active_activity_id]);
    }

    public function endSession(LiveSession $session)
    {
        $this->owned($session->presentation);
        $session->update(['status' => 'ended', 'ended_at' => now()]);

        return redirect()->route('sessions.show', $session)->with('ok', 'La sesión terminó correctamente.');
    }
}
