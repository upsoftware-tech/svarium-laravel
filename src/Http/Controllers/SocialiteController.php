<?php

namespace Upsoftware\Svarium\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;

class SocialiteController extends Controller
{
    public function redirect($provider)
    {
        return Socialite::driver($provider)->redirect();
    }

    public function callback($provider)
    {
        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Exception $e) {
            return redirect()->route('login')->with('error', 'Nie udało się pobrać danych od dostawcy.');
        }

        $existingSocialIdentity = SocialIdentity::where('provider_name', $provider)
            ->where('provider_id', $socialUser->getId())
            ->first();

        if ($existingSocialIdentity) {
            Auth::login($existingSocialIdentity->user);
            return redirect()->intended('/');
        }

        $user = User::where('email', $socialUser->getEmail())->first();

        if (! $user) {
            return redirect()->route('login')->with('error', 'Brak użytkownika powiązanego z tym adresem e-mail. Logowanie nie powiodło się. Zaloguj się e-mailem i hasłem');
        }

        DB::beginTransaction();
        try {
            $user->socialIdentities()->create([
                'provider_name' => $provider,
                'provider_id' => $socialUser->getId(),
                'avatar' => $socialUser->getAvatar(),
            ]);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('login')->with('error', 'Wystąpił błąd podczas logowania za pomocą Social Medii.');
        }

        Auth::login($user);

        return redirect()->intended('/exit');
    }
}
