<?php

use App\Http\Controllers\{AuthController, ParticipantController, PresentationController};
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::middleware('guest')->group(function () {
    Route::get('/ingresar', [AuthController::class, 'loginForm'])->name('login');
    Route::post('/ingresar', [AuthController::class, 'login']);
    Route::get('/registro', [AuthController::class, 'registerForm'])->name('register');
    Route::post('/registro', [AuthController::class, 'register']);
});
Route::post('/salir', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/presentaciones', [PresentationController::class, 'index'])->name('presentations.index');
    Route::post('/presentaciones', [PresentationController::class, 'store'])->name('presentations.store');
    Route::get('/presentaciones/{presentation}/editar', [PresentationController::class, 'edit'])->name('presentations.edit');
    Route::post('/presentaciones/{presentation}/diapositivas', [PresentationController::class, 'addSlide'])->name('slides.store');
    Route::put('/presentaciones/{presentation}/diapositivas/orden', [PresentationController::class, 'reorderSlides'])->name('slides.reorder');
    Route::put('/diapositivas/{slide}', [PresentationController::class, 'updateSlide'])->name('slides.update');
    Route::delete('/diapositivas/{slide}', [PresentationController::class, 'deleteSlide'])->name('slides.destroy');
    Route::post('/diapositivas/{slide}/actividades', [PresentationController::class, 'addActivity'])->name('activities.store');
    Route::put('/actividades/{activity}', [PresentationController::class, 'updateActivity'])->name('activities.update');
    Route::delete('/actividades/{activity}', [PresentationController::class, 'deleteActivity'])->name('activities.destroy');
    Route::post('/presentaciones/{presentation}/iniciar', [PresentationController::class, 'start'])->name('presentations.start');
    Route::get('/sesiones/{session}', [PresentationController::class, 'showSession'])->name('sessions.show');
    Route::put('/sesiones/{session}/diapositiva', [PresentationController::class, 'changeSlide'])->name('sessions.slide');
    Route::put('/sesiones/{session}/interaccion', [PresentationController::class, 'changeActivity'])->name('sessions.activity');
    Route::post('/sesiones/{session}/terminar', [PresentationController::class, 'endSession'])->name('sessions.end');
});

Route::post('/unirse', [ParticipantController::class, 'join'])->name('join');
Route::get('/j/{code}', [ParticipantController::class, 'joinPage'])->whereNumber('code')->name('join.show');
Route::get('/qr/{session:code}.svg', [ParticipantController::class, 'qr'])->name('session.qr');
Route::get('/estado/{code}', [ParticipantController::class, 'state'])->whereNumber('code')->name('session.state');
Route::get('/participar/{code}', [ParticipantController::class, 'participate'])->name('participate');
Route::post('/actividades/{activity}/responder', [ParticipantController::class, 'answer'])->name('answer');
