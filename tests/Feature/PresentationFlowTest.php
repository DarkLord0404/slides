<?php
namespace Tests\Feature;

use App\Models\{Activity,LiveSession,Participant,Presentation,Slide,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PresentationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_renders(): void
    {
        $this->get('/')->assertOk()->assertSee('Koqoi Slides');
    }

 public function test_creator_can_build_and_start_a_presentation():void
 {
  $user=User::factory()->create();
  $this->actingAs($user)->post('/presentaciones',['title'=>'Demo interactiva','description'=>'Prueba'])->assertRedirect();
  $presentation=Presentation::first();
  $this->assertSame($user->id,$presentation->user_id);
  $this->assertCount(1,$presentation->slides);
  $this->actingAs($user)->post('/diapositivas/'.$presentation->slides->first()->id.'/actividades',['type'=>'multiple_choice','question'=>'¿Cuál eliges?','options_text'=>"A\nB"])->assertRedirect();
  $this->assertDatabaseHas('activities',['question'=>'¿Cuál eliges?']);
  $this->actingAs($user)->post('/presentaciones/'.$presentation->id.'/iniciar')->assertRedirect();
  $this->assertDatabaseHas('live_sessions',['presentation_id'=>$presentation->id,'status'=>'live']);
 }

 public function test_participant_can_join_and_answer():void
 {
  $user=User::factory()->create();
  $presentation=Presentation::create(['user_id'=>$user->id,'title'=>'Demo']);
  $slide=Slide::create(['presentation_id'=>$presentation->id,'position'=>1,'title'=>'Pregunta']);
  $activity=Activity::create(['slide_id'=>$slide->id,'type'=>'open_text','question'=>'¿Qué opinas?']);
  LiveSession::create(['presentation_id'=>$presentation->id,'active_slide_id'=>$slide->id,'code'=>'123456','status'=>'live']);
  $this->post('/unirse',['code'=>'123456','name'=>'Ada'])->assertRedirect('/participar/123456');
  $participant=Participant::first();
  $this->withSession(['participant_token'=>$participant->token])->post('/actividades/'.$activity->id.'/responder',['answer'=>'Muy útil'])->assertRedirect();
  $this->assertDatabaseHas('responses',['answer'=>'Muy útil']);
 }

 public function test_audience_can_open_direct_link_and_qr_without_account():void
 {
  $user=User::factory()->create();
  $presentation=Presentation::create(['user_id'=>$user->id,'title'=>'Demo']);
  $slide=Slide::create(['presentation_id'=>$presentation->id,'position'=>1]);
  LiveSession::create(['presentation_id'=>$presentation->id,'active_slide_id'=>$slide->id,'code'=>'654321','status'=>'live']);
  $this->get('/j/654321')->assertOk()->assertSee('No necesitas registrarte')->assertSee('654321');
  $this->get('/qr/654321.svg')->assertOk()->assertHeader('Content-Type','image/svg+xml');
 }

 public function test_invalid_audience_code_returns_to_home_with_message():void
 {
  $this->get('/j/123456')->assertRedirect('/')->assertSessionHasErrors('code');
 }

 public function test_creator_cannot_edit_another_presentation():void
 {
  $owner=User::factory()->create();$intruder=User::factory()->create();
  $presentation=Presentation::create(['user_id'=>$owner->id,'title'=>'Privada']);
  $this->actingAs($intruder)->get('/presentaciones/'.$presentation->id.'/editar')->assertForbidden();
 }
}
