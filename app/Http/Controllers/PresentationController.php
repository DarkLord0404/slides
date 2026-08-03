<?php

namespace App\Http\Controllers;

use App\Models\LiveSession;
use App\Models\Presentation;
use App\Models\Slide;
use Illuminate\Http\Request;

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
        $data = $request->validate(['title' => ['nullable', 'string', 'max:180'], 'body' => ['nullable', 'string', 'max:4000']]);
        $presentation->slides()->create([...$data, 'position' => ($presentation->slides()->max('position') ?? 0) + 1]);

        return back()->with('ok', 'Diapositiva agregada.');
    }

    public function updateSlide(Request $request, Slide $slide)
    {
        $this->owned($slide->presentation);
        $slide->update($request->validate(['title' => ['nullable', 'string', 'max:180'], 'body' => ['nullable', 'string', 'max:4000']]));

        return back()->with('ok', 'Diapositiva guardada.');
    }

    public function addActivity(Request $request, Slide $slide)
    {
        $this->owned($slide->presentation);
        $data = $request->validate(['type' => ['required', 'in:multiple_choice,open_text,word_cloud,true_false'], 'question' => ['required', 'string', 'max:500'], 'options_text' => ['nullable', 'string', 'max:2000']]);
        $options = collect(preg_split('/\R/', $data['options_text'] ?? ''))->map(fn ($x) => trim($x))->filter()->values()->all();
        if ($data['type'] === 'true_false') {
            $options = ['Verdadero', 'Falso'];
        }
        $slide->activities()->create(['type' => $data['type'], 'question' => $data['question'], 'options' => $options]);

        return back()->with('ok', 'Actividad agregada.');
    }

    public function start(Presentation $presentation)
    {
        $this->owned($presentation);
        do {
            $code = (string) random_int(100000, 999999);
        } while (LiveSession::where('code', $code)->exists());
        $session = $presentation->sessions()->create(['code' => $code, 'status' => 'live', 'started_at' => now(), 'active_slide_id' => $presentation->slides()->value('id')]);

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
        $session->update(['active_slide_id' => $slide->id]);

        return response()->json(['active_slide_id' => $slide->id]);
    }
}
