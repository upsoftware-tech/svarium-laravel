<?php

namespace Upsoftware\Svarium\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class VerificationController extends Controller
{
    public function init($type, $userAuth) {
        $data['session'] = $userAuth;
        $data['type'] = $type;
        $data['remember'] = $type === 'login';

        return inertia('Auth/Verification', $data);
    }

    public function set(Request $request, $type, $userAuth)
    {
        $userAuthItem = get_model('user_auth')::byHash($userAuth);

        if (!$userAuthItem || !$userAuthItem->verifyCode($request->code)) {
            throw ValidationException::withMessages([
                'code' => [__('svarium::messages.Invalid verification code')],
            ]);
        }

        if ($type === 'login') {
            $loginController = new LoginController();
            return $loginController->loginUser($request, $userAuthItem->user);
        } else if ($type === 'reset') {
            return redirect()->route('panel.auth.reset.password', ['userAuth' => $userAuthItem->hash]);
        }
    }
}
