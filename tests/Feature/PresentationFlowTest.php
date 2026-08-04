<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\LiveSession;
use App\Models\Participant;
use App\Models\Presentation;
use App\Models\Response;
use App\Models\Slide;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PresentationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_renders(): void
    {
        $this->get('/')->assertOk()->assertSee('Koqoi Slides');
    }

    public function test_creator_can_build_and_start_a_presentation(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/presentaciones', ['title' => 'Demo interactiva', 'description' => 'Prueba'])->assertRedirect();
        $presentation = Presentation::first();
        $this->assertSame($user->id, $presentation->user_id);
        $this->assertCount(1, $presentation->slides);
        $this->actingAs($user)->post('/diapositivas/'.$presentation->slides->first()->id.'/actividades', ['type' => 'multiple_choice', 'question' => '¿Cuál eliges?', 'options_text' => "A\nB"])->assertRedirect();
        $this->assertDatabaseHas('activities', ['question' => '¿Cuál eliges?']);
        $this->actingAs($user)->post('/presentaciones/'.$presentation->id.'/iniciar')->assertRedirect();
        $this->assertDatabaseHas('live_sessions', ['presentation_id' => $presentation->id, 'status' => 'live']);
    }

    public function test_participant_can_join_and_answer(): void
    {
        $user = User::factory()->create();
        $presentation = Presentation::create(['user_id' => $user->id, 'title' => 'Demo']);
        $slide = Slide::create(['presentation_id' => $presentation->id, 'position' => 1, 'title' => 'Pregunta']);
        $activity = Activity::create(['slide_id' => $slide->id, 'type' => 'open_text', 'question' => '¿Qué opinas?']);
        LiveSession::create(['presentation_id' => $presentation->id, 'active_slide_id' => $slide->id, 'code' => '123456', 'status' => 'live']);
        $this->post('/unirse', ['code' => '123456', 'name' => 'Ada'])->assertRedirect('/participar/123456');
        $participant = Participant::first();
        $this->withSession(['participant_token' => $participant->token])->post('/actividades/'.$activity->id.'/responder', ['answer' => 'Muy útil'])->assertRedirect();
        $this->assertDatabaseHas('responses', ['answer' => 'Muy útil']);
    }

    public function test_audience_can_open_direct_link_and_qr_without_account(): void
    {
        $user = User::factory()->create();
        $presentation = Presentation::create(['user_id' => $user->id, 'title' => 'Demo']);
        $slide = Slide::create(['presentation_id' => $presentation->id, 'position' => 1]);
        LiveSession::create(['presentation_id' => $presentation->id, 'active_slide_id' => $slide->id, 'code' => '654321', 'status' => 'live']);
        $this->get('/j/654321')->assertOk()->assertSee('No necesitas registrarte')->assertSee('654321');
        $this->get('/qr/654321.svg')->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
    }

    public function test_invalid_audience_code_returns_to_home_with_message(): void
    {
        $this->get('/j/123456')->assertRedirect('/')->assertSessionHasErrors('code');
    }

    public function test_creator_cannot_edit_another_presentation(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $presentation = Presentation::create(['user_id' => $owner->id, 'title' => 'Privada']);
        $this->actingAs($intruder)->get('/presentaciones/'.$presentation->id.'/editar')->assertForbidden();
    }

    public function test_presenter_can_change_the_active_slide(): void
    {
        $user = User::factory()->create();
        $presentation = Presentation::create(['user_id' => $user->id, 'title' => 'Escenario']);
        $first = Slide::create(['presentation_id' => $presentation->id, 'position' => 1, 'title' => 'Uno']);
        $second = Slide::create(['presentation_id' => $presentation->id, 'position' => 2, 'title' => 'Dos']);
        $live = LiveSession::create(['presentation_id' => $presentation->id, 'active_slide_id' => $first->id, 'code' => '777888', 'status' => 'live']);
        $this->actingAs($user)->putJson('/sesiones/'.$live->id.'/diapositiva', ['slide_id' => $second->id])->assertOk();
        $this->assertDatabaseHas('live_sessions', ['id' => $live->id, 'active_slide_id' => $second->id]);
        $this->get('/estado/777888')->assertOk()->assertJson(['active_slide_id' => $second->id]);
    }

    public function test_presenter_stage_marks_long_content_for_visual_clamping(): void
    {
        $user = User::factory()->create();
        $presentation = Presentation::create(['user_id' => $user->id, 'title' => 'Contenido extenso']);
        $slide = Slide::create(['presentation_id' => $presentation->id, 'position' => 1, 'title' => 'Resumen', 'body' => str_repeat('Contenido ', 100)]);
        $live = LiveSession::create(['presentation_id' => $presentation->id, 'active_slide_id' => $slide->id, 'code' => '991122', 'status' => 'live']);
        $this->actingAs($user)->get('/sesiones/'.$live->id)->assertOk()->assertSee('stage-content is-long', false);
    }

    public function test_presenter_can_end_session_and_audience_sees_completion(): void
    {
        $user = User::factory()->create();
        $presentation = Presentation::create(['user_id' => $user->id, 'title' => 'Cierre']);
        $slide = Slide::create(['presentation_id' => $presentation->id, 'position' => 1]);
        $live = LiveSession::create(['presentation_id' => $presentation->id, 'active_slide_id' => $slide->id, 'code' => '445566', 'status' => 'live']);
        $participant = $live->participants()->create(['token' => (string) Str::uuid(), 'name' => 'Ada', 'last_seen_at' => now()]);
        $this->actingAs($user)->post('/sesiones/'.$live->id.'/terminar')->assertRedirect('/sesiones/'.$live->id);
        $this->assertDatabaseHas('live_sessions', ['id' => $live->id, 'status' => 'ended']);
        $this->withSession(['participant_token' => $participant->token])->get('/participar/445566')->assertOk()->assertSee('Gracias por participar');
    }

    public function test_slide_layout_and_text_limits_are_enforced(): void
    {
        $user = User::factory()->create();
        $presentation = Presentation::create(['user_id' => $user->id, 'title' => 'Diseños']);
        $slide = Slide::create(['presentation_id' => $presentation->id, 'position' => 1]);
        $this->actingAs($user)->put('/diapositivas/'.$slide->id, ['title' => 'Título', 'body' => 'Contenido breve', 'layout' => 'split'])->assertRedirect();
        $this->assertSame('split', data_get($slide->fresh()->design, 'layout'));
        $this->actingAs($user)->put('/diapositivas/'.$slide->id, ['title' => str_repeat('T', 121), 'body' => 'Texto', 'layout' => 'content'])->assertSessionHasErrors('title');
    }

    public function test_presenter_selects_one_active_interaction_at_a_time(): void
    {
        $user = User::factory()->create();
        $presentation = Presentation::create(['user_id' => $user->id, 'title' => 'Secuencia']);
        $slide = Slide::create(['presentation_id' => $presentation->id, 'position' => 1, 'title' => 'Mensaje']);
        $first = Activity::create(['slide_id' => $slide->id, 'type' => 'open_text', 'question' => 'Primera']);
        $second = Activity::create(['slide_id' => $slide->id, 'type' => 'open_text', 'question' => 'Segunda']);
        $live = LiveSession::create(['presentation_id' => $presentation->id, 'active_slide_id' => $slide->id, 'code' => '112233', 'status' => 'live']);
        $participant = $live->participants()->create(['token' => (string) Str::uuid(), 'name' => 'Lin', 'last_seen_at' => now()]);

        $this->actingAs($user)->putJson('/sesiones/'.$live->id.'/interaccion', ['activity_id' => $second->id])->assertOk()->assertJson(['active_activity_id' => $second->id]);
        $this->withSession(['participant_token' => $participant->token])->get('/participar/112233')->assertOk()->assertSee('Segunda')->assertDontSee('Primera');
        $this->actingAs($user)->putJson('/sesiones/'.$live->id.'/interaccion', ['activity_id' => $first->id])->assertOk();
    }

    public function test_creator_can_edit_an_existing_interaction(): void
    {
        $user = User::factory()->create();
        $presentation = Presentation::create(['user_id' => $user->id, 'title' => 'Edición']);
        $slide = Slide::create(['presentation_id' => $presentation->id, 'position' => 1]);
        $activity = Activity::create(['slide_id' => $slide->id, 'type' => 'open_text', 'question' => 'Original']);
        $this->actingAs($user)->put('/actividades/'.$activity->id, ['type' => 'multiple_choice', 'question' => 'Actualizada', 'options_text' => "Uno\nDos"])->assertRedirect();
        $this->assertDatabaseHas('activities', ['id' => $activity->id, 'type' => 'multiple_choice', 'question' => 'Actualizada']);
        $this->assertSame(['Uno', 'Dos'], $activity->fresh()->options);
    }

    public function test_creator_can_customize_a_slide_background(): void
    {
        $user = User::factory()->create();
        $presentation = Presentation::create(['user_id' => $user->id, 'title' => 'Fondos']);
        $slide = Slide::create(['presentation_id' => $presentation->id, 'position' => 1]);
        $this->actingAs($user)->put('/diapositivas/'.$slide->id, ['title' => 'Tema', 'body' => 'Contenido', 'layout' => 'cover', 'background_style' => 'custom', 'background_mode' => 'custom', 'background_color' => '#123456', 'title_color' => '#ffffff', 'body_color' => '#eeeeee', 'accent_color' => '#ff0000', 'question_background_color' => '#222222', 'question_text_color' => '#fafafa', 'decoration' => 'none'])->assertRedirect();
        $this->assertSame('custom', data_get($slide->fresh()->design, 'background_style'));
        $this->assertSame('#123456', data_get($slide->fresh()->design, 'background_color'));
        $this->assertSame('#ffffff', data_get($slide->fresh()->design, 'title_color'));
        $this->assertSame('none', data_get($slide->fresh()->design, 'decoration'));
    }

    public function test_presentation_mode_exits_to_editor_without_logging_out(): void
    {
        $user = User::factory()->create();
        $presentation = Presentation::create(['user_id' => $user->id, 'title' => 'En vivo']);
        $slide = Slide::create(['presentation_id' => $presentation->id, 'position' => 1, 'title' => 'Inicio']);
        $live = LiveSession::create(['presentation_id' => $presentation->id, 'active_slide_id' => $slide->id, 'code' => '445566', 'status' => 'live']);
        $this->actingAs($user)->get('/sesiones/'.$live->id)->assertOk()->assertSee('Salir de presentación')->assertDontSee('Cerrar sesión');
    }

    public function test_audience_keeps_its_answer_visibly_selected(): void
    {
        $user = User::factory()->create();
        $presentation = Presentation::create(['user_id' => $user->id, 'title' => 'Votación']);
        $slide = Slide::create(['presentation_id' => $presentation->id, 'position' => 1, 'title' => 'Pregunta']);
        $activity = Activity::create(['slide_id' => $slide->id, 'type' => 'multiple_choice', 'question' => '¿Cuál?', 'options' => ['Uno', 'Dos']]);
        $live = LiveSession::create(['presentation_id' => $presentation->id, 'active_slide_id' => $slide->id, 'active_activity_id' => $activity->id, 'code' => '778899', 'status' => 'live']);
        $participant = $live->participants()->create(['token' => (string) Str::uuid(), 'name' => 'Ana', 'last_seen_at' => now()]);
        Response::create(['activity_id' => $activity->id, 'participant_id' => $participant->id, 'answer' => 'Dos']);
        $this->withSession(['participant_token' => $participant->token])->get('/participar/778899')->assertOk()->assertSee('✓ RESPONDIDA')->assertSee('✓ Tu respuesta')->assertSee('Actualizar respuesta')->assertSee('value="Dos" checked', false);
    }

    public function test_creator_can_reorder_and_delete_slides(): void
    {
        $user = User::factory()->create();
        $presentation = Presentation::create(['user_id' => $user->id, 'title' => 'Orden']);
        $first = Slide::create(['presentation_id' => $presentation->id, 'position' => 1, 'title' => 'Primera']);
        $second = Slide::create(['presentation_id' => $presentation->id, 'position' => 2, 'title' => 'Segunda']);
        $third = Slide::create(['presentation_id' => $presentation->id, 'position' => 3, 'title' => 'Tercera']);
        $this->actingAs($user)->putJson('/presentaciones/'.$presentation->id.'/diapositivas/orden', ['slide_ids' => [$third->id, $first->id, $second->id]])->assertOk();
        $this->assertSame(1, $third->fresh()->position);
        $this->actingAs($user)->delete('/diapositivas/'.$first->id)->assertRedirect();
        $this->assertDatabaseMissing('slides', ['id' => $first->id]);
        $this->assertSame([1, 2], $presentation->slides()->orderBy('position')->pluck('position')->all());
    }

    public function test_editor_contains_live_canvas_and_reorder_controls(): void
    {
        $user = User::factory()->create();
        $presentation = Presentation::create(['user_id' => $user->id, 'title' => 'Editor']);
        Slide::create(['presentation_id' => $presentation->id, 'position' => 1, 'title' => 'Lienzo']);
        $this->actingAs($user)->get('/presentaciones/'.$presentation->id.'/editar')->assertOk()->assertSee('visual-editor')->assertSee('activity_url');
    }

    public function test_creator_can_delete_an_interaction(): void
    {
        $user = User::factory()->create();
        $presentation = Presentation::create(['user_id' => $user->id, 'title' => 'Limpiar']);
        $slide = Slide::create(['presentation_id' => $presentation->id, 'position' => 1]);
        $activity = Activity::create(['slide_id' => $slide->id, 'type' => 'open_text', 'question' => 'Eliminarme']);
        $this->actingAs($user)->delete('/actividades/'.$activity->id)->assertRedirect();
        $this->assertDatabaseMissing('activities', ['id' => $activity->id]);
    }

    public function test_creator_can_edit_presentation_metadata_and_dashboard_has_metrics(): void
    {
        $user = User::factory()->create();
        $presentation = Presentation::create(['user_id' => $user->id, 'title' => 'Antes']);
        Slide::create(['presentation_id' => $presentation->id, 'position' => 1, 'title' => 'Portada']);
        $this->actingAs($user)->put('/presentaciones/'.$presentation->id, ['title' => 'Después', 'description' => 'Descripción editable'])->assertRedirect();
        $this->actingAs($user)->get('/presentaciones')->assertOk()->assertSee('Descripción editable')->assertSee('presentaciones')->assertSee('respuestas');
    }

    public function test_participant_can_like_the_active_slide_once(): void
    {
        $user = User::factory()->create();
        $presentation = Presentation::create(['user_id' => $user->id, 'title' => 'Likes']);
        $slide = Slide::create(['presentation_id' => $presentation->id, 'position' => 1]);
        $live = LiveSession::create(['presentation_id' => $presentation->id, 'active_slide_id' => $slide->id, 'code' => '991122', 'status' => 'live']);
        $participant = $live->participants()->create(['token' => (string) Str::uuid(), 'name' => 'Luz', 'last_seen_at' => now()]);
        $this->withSession(['participant_token' => $participant->token])->post('/diapositivas/'.$slide->id.'/reaccionar')->assertRedirect();
        $this->withSession(['participant_token' => $participant->token])->post('/diapositivas/'.$slide->id.'/reaccionar')->assertRedirect();
        $this->assertDatabaseCount('slide_reactions', 1);
        $this->get('/estado/991122')->assertOk()->assertJson(['likes' => 1]);
    }

    public function test_visual_editor_opens_and_autosaves_canvas_elements(): void
    {
        $user = User::factory()->create();
        $presentation = Presentation::create(['user_id' => $user->id, 'title' => 'Lienzo libre']);
        $slide = Slide::create(['presentation_id' => $presentation->id, 'position' => 1, 'title' => 'Título existente', 'body' => 'Contenido existente']);
        $this->actingAs($user)->get('/presentaciones/'.$presentation->id.'/lienzo')->assertOk()->assertSee('visual-editor')->assertSee('legacy-title-'.$slide->id)->assertSee('legacy-body-'.$slide->id);
        $elements = [['id' => 'one', 'type' => 'text', 'x' => 10, 'y' => 20, 'width' => 300, 'height' => 80, 'rotation' => 0, 'text' => 'Hola', 'fill' => '#000000', 'fontSize' => 40]];
        $this->actingAs($user)->putJson('/diapositivas/'.$slide->id.'/lienzo', ['elements' => $elements])->assertOk();
        $this->assertSame('Hola', data_get($slide->fresh()->design, 'elements.0.text'));
        $this->actingAs($user)->getJson('/diapositivas/'.$slide->id.'/lienzo')->assertOk()->assertJsonPath('elements.0.text', 'Hola');
        $this->actingAs(User::factory()->create())->getJson('/diapositivas/'.$slide->id.'/lienzo')->assertForbidden();
    }

    public function test_editor_only_embeds_the_first_slide_canvas(): void
    {
        $user = User::factory()->create();
        $presentation = Presentation::create(['user_id' => $user->id, 'title' => 'Carga rápida']);
        Slide::create(['presentation_id' => $presentation->id, 'position' => 1, 'design' => ['elements' => [['id' => 'first', 'type' => 'text', 'text' => 'Contenido inicial']]]]);
        $second = Slide::create(['presentation_id' => $presentation->id, 'position' => 2, 'design' => ['elements' => [['id' => 'second', 'type' => 'text', 'text' => 'CONTENIDO-PESADO-OCULTO']]]]);
        $this->actingAs($user)->get('/presentaciones/'.$presentation->id.'/editar')
            ->assertOk()->assertSee('Contenido inicial')->assertDontSee('CONTENIDO-PESADO-OCULTO')->assertSee('"loaded":false', false);
    }

    public function test_classic_and_visual_editors_share_slide_content(): void
    {
        $user = User::factory()->create();
        $presentation = Presentation::create(['user_id' => $user->id, 'title' => 'Sincronizada']);
        $slide = Slide::create(['presentation_id' => $presentation->id, 'position' => 1, 'title' => 'Antes', 'body' => 'Texto', 'design' => ['elements' => [
            ['id' => 'legacy-title-1', 'type' => 'text', 'text' => 'Antes', 'x' => 10, 'y' => 10, 'width' => 300, 'height' => 80, 'rotation' => 0, 'fill' => '#000000'],
        ]]]);
        $slide->update(['design' => ['elements' => [['id' => 'legacy-title-'.$slide->id, 'type' => 'text', 'text' => 'Antes', 'x' => 10, 'y' => 10, 'width' => 300, 'height' => 80, 'rotation' => 0, 'fill' => '#000000']]]]);
        $this->actingAs($user)->put('/diapositivas/'.$slide->id, ['title' => 'Desde clásico', 'body' => 'Texto nuevo', 'background_style' => 'ivory', 'background_mode' => 'preset', 'background_color' => '#fffdf8', 'title_color' => '#112233', 'body_color' => '#445566', 'accent_color' => '#ff6846', 'question_background_color' => '#102a2e', 'question_text_color' => '#ffffff', 'decoration' => 'circle'])->assertRedirect();
        $this->assertSame('Desde clásico', data_get($slide->fresh()->design, 'elements.0.text'));
        $elements = $slide->fresh()->design['elements'];
        $elements[0]['text'] = 'Desde lienzo';
        $this->actingAs($user)->putJson('/diapositivas/'.$slide->id.'/lienzo', ['elements' => $elements])->assertOk();
        $this->assertSame('Desde lienzo', $slide->fresh()->title);
    }

    public function test_theme_is_shared_without_removing_canvas_objects(): void
    {
        $user = User::factory()->create();
        $presentation = Presentation::create(['user_id' => $user->id, 'title' => 'Temas']);
        $slide = Slide::create(['presentation_id' => $presentation->id, 'position' => 1, 'title' => 'Portada', 'design' => ['elements' => [['id' => 'free-object', 'type' => 'rect', 'x' => 1, 'y' => 1, 'width' => 20, 'height' => 20, 'rotation' => 0, 'fill' => '#000000']]]]);
        $this->actingAs($user)->put('/presentaciones/'.$presentation->id.'/tema', ['theme' => 'ocean'])->assertRedirect();
        $this->assertSame('ocean', $presentation->fresh()->theme);
        $this->assertSame('#dff7f5', $presentation->fresh()->theme_settings['background_color']);
        $this->assertTrue(collect($slide->fresh()->design['elements'])->contains(fn ($element) => $element['id'] === 'free-object'));
        $this->assertTrue(collect($slide->fresh()->design['elements'])->contains(fn ($element) => $element['id'] === 'theme-background'));
        $this->assertTrue(collect($slide->fresh()->design['elements'])->contains(fn ($element) => $element['id'] === 'theme-ring-1'));
    }

    public function test_opening_theme_url_redirects_to_the_editor(): void
    {
        $user = User::factory()->create();
        $presentation = Presentation::create(['user_id' => $user->id, 'title' => 'Tema seguro']);

        $this->actingAs($user)->get('/presentaciones/'.$presentation->id.'/tema')
            ->assertRedirect('/presentaciones/'.$presentation->id.'/editar');
        $this->actingAs(User::factory()->create())->get('/presentaciones/'.$presentation->id.'/tema')
            ->assertForbidden();
    }
}
