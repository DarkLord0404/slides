<?php
namespace Tests\Feature;

use App\Models\{Activity,LiveSession,Participant,Presentation,Response,Slide,User};
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

 public function test_presenter_can_change_the_active_slide():void
 {
  $user=User::factory()->create();
  $presentation=Presentation::create(['user_id'=>$user->id,'title'=>'Escenario']);
  $first=Slide::create(['presentation_id'=>$presentation->id,'position'=>1,'title'=>'Uno']);
  $second=Slide::create(['presentation_id'=>$presentation->id,'position'=>2,'title'=>'Dos']);
  $live=LiveSession::create(['presentation_id'=>$presentation->id,'active_slide_id'=>$first->id,'code'=>'777888','status'=>'live']);
  $this->actingAs($user)->putJson('/sesiones/'.$live->id.'/diapositiva',['slide_id'=>$second->id])->assertOk();
  $this->assertDatabaseHas('live_sessions',['id'=>$live->id,'active_slide_id'=>$second->id]);
  $this->get('/estado/777888')->assertOk()->assertJson(['active_slide_id'=>$second->id]);
 }

 public function test_presenter_stage_marks_long_content_for_visual_clamping():void
 {
  $user=User::factory()->create();
  $presentation=Presentation::create(['user_id'=>$user->id,'title'=>'Contenido extenso']);
  $slide=Slide::create(['presentation_id'=>$presentation->id,'position'=>1,'title'=>'Resumen','body'=>str_repeat('Contenido ',100)]);
  $live=LiveSession::create(['presentation_id'=>$presentation->id,'active_slide_id'=>$slide->id,'code'=>'991122','status'=>'live']);
  $this->actingAs($user)->get('/sesiones/'.$live->id)->assertOk()->assertSee('stage-content is-long',false);
 }

 public function test_presenter_can_end_session_and_audience_sees_completion():void
 {
  $user=User::factory()->create();
  $presentation=Presentation::create(['user_id'=>$user->id,'title'=>'Cierre']);
  $slide=Slide::create(['presentation_id'=>$presentation->id,'position'=>1]);
  $live=LiveSession::create(['presentation_id'=>$presentation->id,'active_slide_id'=>$slide->id,'code'=>'445566','status'=>'live']);
  $participant=$live->participants()->create(['token'=>(string)\Illuminate\Support\Str::uuid(),'name'=>'Ada','last_seen_at'=>now()]);
  $this->actingAs($user)->post('/sesiones/'.$live->id.'/terminar')->assertRedirect('/sesiones/'.$live->id);
  $this->assertDatabaseHas('live_sessions',['id'=>$live->id,'status'=>'ended']);
  $this->withSession(['participant_token'=>$participant->token])->get('/participar/445566')->assertOk()->assertSee('Gracias por participar');
 }

 public function test_slide_layout_and_text_limits_are_enforced():void
 {
  $user=User::factory()->create();
  $presentation=Presentation::create(['user_id'=>$user->id,'title'=>'Diseños']);
  $slide=Slide::create(['presentation_id'=>$presentation->id,'position'=>1]);
  $this->actingAs($user)->put('/diapositivas/'.$slide->id,['title'=>'Título','body'=>'Contenido breve','layout'=>'split'])->assertRedirect();
  $this->assertSame('split',data_get($slide->fresh()->design,'layout'));
  $this->actingAs($user)->put('/diapositivas/'.$slide->id,['title'=>str_repeat('T',121),'body'=>'Texto','layout'=>'content'])->assertSessionHasErrors('title');
 }

 public function test_presenter_selects_one_active_interaction_at_a_time():void
 {
  $user=User::factory()->create();
  $presentation=Presentation::create(['user_id'=>$user->id,'title'=>'Secuencia']);
  $slide=Slide::create(['presentation_id'=>$presentation->id,'position'=>1,'title'=>'Mensaje']);
  $first=Activity::create(['slide_id'=>$slide->id,'type'=>'open_text','question'=>'Primera']);
  $second=Activity::create(['slide_id'=>$slide->id,'type'=>'open_text','question'=>'Segunda']);
  $live=LiveSession::create(['presentation_id'=>$presentation->id,'active_slide_id'=>$slide->id,'code'=>'112233','status'=>'live']);
  $participant=$live->participants()->create(['token'=>(string)\Illuminate\Support\Str::uuid(),'name'=>'Lin','last_seen_at'=>now()]);

  $this->actingAs($user)->putJson('/sesiones/'.$live->id.'/interaccion',['activity_id'=>$second->id])->assertOk()->assertJson(['active_activity_id'=>$second->id]);
  $this->withSession(['participant_token'=>$participant->token])->get('/participar/112233')->assertOk()->assertSee('Segunda')->assertDontSee('Primera');
  $this->actingAs($user)->putJson('/sesiones/'.$live->id.'/interaccion',['activity_id'=>$first->id])->assertOk();
 }

 public function test_creator_can_edit_an_existing_interaction():void
 {
  $user=User::factory()->create();
  $presentation=Presentation::create(['user_id'=>$user->id,'title'=>'Edición']);
  $slide=Slide::create(['presentation_id'=>$presentation->id,'position'=>1]);
  $activity=Activity::create(['slide_id'=>$slide->id,'type'=>'open_text','question'=>'Original']);
  $this->actingAs($user)->put('/actividades/'.$activity->id,['type'=>'multiple_choice','question'=>'Actualizada','options_text'=>"Uno\nDos"])->assertRedirect();
  $this->assertDatabaseHas('activities',['id'=>$activity->id,'type'=>'multiple_choice','question'=>'Actualizada']);
  $this->assertSame(['Uno','Dos'],$activity->fresh()->options);
 }

 public function test_creator_can_customize_a_slide_background():void
 {
  $user=User::factory()->create();
  $presentation=Presentation::create(['user_id'=>$user->id,'title'=>'Fondos']);
  $slide=Slide::create(['presentation_id'=>$presentation->id,'position'=>1]);
  $this->actingAs($user)->put('/diapositivas/'.$slide->id,['title'=>'Tema','body'=>'Contenido','layout'=>'cover','background_style'=>'custom','background_color'=>'#123456'])->assertRedirect();
  $this->assertSame('custom',data_get($slide->fresh()->design,'background_style'));
  $this->assertSame('#123456',data_get($slide->fresh()->design,'background_color'));
 }

 public function test_presentation_mode_exits_to_editor_without_logging_out():void
 {
  $user=User::factory()->create();
  $presentation=Presentation::create(['user_id'=>$user->id,'title'=>'En vivo']);
  $slide=Slide::create(['presentation_id'=>$presentation->id,'position'=>1,'title'=>'Inicio']);
  $live=LiveSession::create(['presentation_id'=>$presentation->id,'active_slide_id'=>$slide->id,'code'=>'445566','status'=>'live']);
  $this->actingAs($user)->get('/sesiones/'.$live->id)->assertOk()->assertSee('Salir de presentación')->assertDontSee('Cerrar sesión');
 }

 public function test_audience_keeps_its_answer_visibly_selected():void
 {
  $user=User::factory()->create();
  $presentation=Presentation::create(['user_id'=>$user->id,'title'=>'Votación']);
  $slide=Slide::create(['presentation_id'=>$presentation->id,'position'=>1,'title'=>'Pregunta']);
  $activity=Activity::create(['slide_id'=>$slide->id,'type'=>'multiple_choice','question'=>'¿Cuál?','options'=>['Uno','Dos']]);
  $live=LiveSession::create(['presentation_id'=>$presentation->id,'active_slide_id'=>$slide->id,'active_activity_id'=>$activity->id,'code'=>'778899','status'=>'live']);
  $participant=$live->participants()->create(['token'=>(string)\Illuminate\Support\Str::uuid(),'name'=>'Ana','last_seen_at'=>now()]);
  Response::create(['activity_id'=>$activity->id,'participant_id'=>$participant->id,'answer'=>'Dos']);
  $this->withSession(['participant_token'=>$participant->token])->get('/participar/778899')->assertOk()->assertSee('✓ RESPONDIDA')->assertSee('✓ Tu respuesta')->assertSee('Actualizar respuesta')->assertSee('value="Dos" checked',false);
 }
}
