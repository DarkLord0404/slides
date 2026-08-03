<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\LiveSession;
use App\Models\Presentation;
use App\Models\Slide;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PresentationController extends Controller
{
    private function owned(Presentation $presentation): void
    {
        abort_unless($presentation->user_id === auth()->id(), 403);
    }

    public function index()
    {
        return view('presentations.index', ['presentations' => auth()->user()->presentations()->withCount('slides')->latest()->get()]);
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
        $presentation->load('slides.activities');

        return view('presentations.edit', compact('presentation'));
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
            'background_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
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
        $slide->update([
            'title' => $data['title'] ?? null,
            'body' => $data['body'] ?? null,
            'background_path' => $backgroundPath,
            'design' => [
                ...($slide->design ?? []),
                'layout' => $data['layout'] ?? 'content',
                'background_style' => $data['background_style'] ?? 'ivory',
                'background_color' => $data['background_color'] ?? '#fffdf8',
            ],
        ]);

        return back()->with('ok', 'Diapositiva guardada.');
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
        $session->load('presentation.slides.activities.responses', 'participants');

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
