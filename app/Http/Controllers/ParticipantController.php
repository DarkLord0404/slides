<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\LiveSession;
use App\Models\Participant;
use App\Models\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;

class ParticipantController extends Controller
{
    public function joinPage(string $code)
    {
        $live = LiveSession::where('code', $code)->where('status', 'live')->with('presentation')->firstOrFail();

        return view('sessions.join', compact('live'));
    }

    public function qr(LiveSession $session)
    {
        abort_unless($session->status === 'live', 404);
        $result = (new SvgWriter)->write(new QrCode(
            data: route('join.show', $session->code),
            size: 360,
            margin: 12,
        ));

        return response($result->getString(), 200, [
            'Content-Type' => $result->getMimeType(),
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    public function join(Request $request)
    {
        $data = $request->validate(['code' => ['required', 'digits:6'], 'name' => ['required', 'string', 'max:80']]);
        $live = LiveSession::where('code', $data['code'])->where('status', 'live')->first();
        if (! $live) {
            return back()->withErrors(['code' => 'No encontramos una sesión activa con ese código.'])->onlyInput('code', 'name');
        }
        $participant = $live->participants()->create(['token' => (string) Str::uuid(), 'name' => $data['name'], 'last_seen_at' => now()]);
        $request->session()->put('participant_token', $participant->token);

        return redirect()->route('participate', $live->code);
    }

    public function participate(string $code)
    {
        $live = LiveSession::where('code', $code)->with('activeSlide.activities')->firstOrFail();
        $participant = Participant::where('token', session('participant_token'))->where('live_session_id', $live->id)->first();
        if (! $participant) {
            return redirect('/')->withErrors(['code' => 'Vuelve a ingresar tu nombre para participar.']);
        }
        $participant->update(['last_seen_at' => now()]);

        return view('sessions.participate', compact('live', 'participant'));
    }

    public function answer(Request $request, Activity $activity)
    {
        $participant = Participant::where('token', session('participant_token'))->with('session')->firstOrFail();
        abort_unless($activity->slide->presentation_id === $participant->session->presentation_id && $participant->session->status === 'live', 403);
        $data = $request->validate(['answer' => ['required', 'string', 'max:2000']]);
        Response::updateOrCreate(['activity_id' => $activity->id, 'participant_id' => $participant->id], ['answer' => $data['answer']]);

        return back()->with('answered', '¡Respuesta registrada!');
    }
}
