<?php

namespace Upsoftware\Svarium\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Upsoftware\Svarium\Models\UserAuth;
use Upsoftware\Svarium\Notifications\UserChangePasswordNotify;

class ResetPasswordController extends Controller
{
    public function init(UserAuth $userAuth) {
        $data = [];
        $data['session'] = $userAuth->hash;

        return inertia('Auth/ResetPassword', $data);
    }

    public function reset(Request $request, $userAuth) {
        $request->validate([
            'password' => [
                'required',
                'string',
                'min:12',
                'regex:/[a-z]/',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
                'regex:/[^A-Za-z0-9]/',
            ],
            'password_confirmation' => ['required', 'same:password']
        ], [
            'password.regex' => 'The password must contain lowercase and uppercase letters, a number, and a special character.',
        ]);

        $userAuth = get_model('user_auth')::byHash($userAuth);
        $user = $userAuth->user;
        try {
            $user->update(['password' => $request->password]);
            $user->notify(new UserChangePasswordNotify());
            return redirect()->route('panel.auth.login')->with(['success' => __('The password has been changed.')]);
        } catch (\Exception $exception) {
            return back()->with(['error' => 'An error occurred while changing your password. Please try again later.', 'message' => $exception->getMessage()]);
        }
    }
}
