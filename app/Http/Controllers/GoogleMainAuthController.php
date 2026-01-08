<?php

namespace App\Http\Controllers;

use App\Models\GoogleAccount;
use App\Services\GoogleMainService;
use Laravel\Socialite\Facades\Socialite;

class GoogleMainAuthController
{
    public function redirect()
    {
        return Socialite::driver('google')
            ->redirectUrl(config('services.google_main.redirect'))
            ->scopes([
                // OpenID básico
                'openid',
                'https://www.googleapis.com/auth/userinfo.email',
                'https://www.googleapis.com/auth/userinfo.profile',

                // Cursos
                'https://www.googleapis.com/auth/classroom.courses',
                'https://www.googleapis.com/auth/classroom.courses.readonly',

                // Atividades
                'https://www.googleapis.com/auth/classroom.coursework.students',
                'https://www.googleapis.com/auth/classroom.coursework.students.readonly',
                'https://www.googleapis.com/auth/classroom.coursework.me',
                'https://www.googleapis.com/auth/classroom.coursework.me.readonly',

                // Entregas (SOMENTE readonly existe)
                'https://www.googleapis.com/auth/classroom.student-submissions.students.readonly',
                'https://www.googleapis.com/auth/classroom.student-submissions.me.readonly',

                // Pessoas
                'https://www.googleapis.com/auth/classroom.rosters',
                'https://www.googleapis.com/auth/classroom.rosters.readonly',

                // Perfil
                'https://www.googleapis.com/auth/classroom.profile.emails',
                'https://www.googleapis.com/auth/classroom.profile.photos',

                // Responsáveis
                'https://www.googleapis.com/auth/classroom.guardianlinks.students',
                'https://www.googleapis.com/auth/classroom.guardianlinks.students.readonly',

                // Notificações
                'https://www.googleapis.com/auth/classroom.push-notifications',

                // Drive (obrigatório pro Classroom)
                'https://www.googleapis.com/auth/drive',
                'https://www.googleapis.com/auth/drive.readonly',
            ])
            ->with([
                'access_type' => 'offline',
                'prompt' => 'consent',
            ])
            ->redirect();
    }

    public function callback()
    {
        $googleUser = Socialite::driver('google')
            ->redirectUrl(config('services.google_main.redirect'))
            ->stateless()
            ->user();

        app(GoogleMainService::class)->salvarTokens(
            googleId: $googleUser->id,
            email: $googleUser->email,
            accessToken: $googleUser->token,
            refreshToken: $googleUser->refreshToken,
            expiresIn: $googleUser->expiresIn
        );


        return redirect()->route('filament.admin.pages.configuracoes');
    }
}
