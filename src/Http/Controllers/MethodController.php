<?php

namespace Upsoftware\Svarium\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Upsoftware\Svarium\Http\Requests\LoginMethodRequest;

class MethodController extends Controller
{
    public function getAvailableMethods(User $user)
    {
        return [
            [
                'id' => 'app',
                'disabled' => $user->google2fa_secret ? false : true,
                'label' => __('svarium::messages.Google Authenticator App'),
                'description' => __('svarium::messages.The Google Authenticator app is available on all platforms, including iOS and Android'),
            ],
            [
                'id' => 'sms',
                'disabled' => true,
                'label' => __('svarium::messages.SMS message'),
                'description' => __('messages.SMS message to the registered phone number'),
            ],
            [
                'id' => 'email',
                'disabled' => false,
                'label' => __('svarium::messages.Email message'),
                'description' => __('svarium::messages.Email message to the registered email address'),
            ],
        ];
    }

    public function init($type, $userAuth)
    {
        $data = [];
        $userAuth = get_model('user_auth')::byHash($userAuth);
        $data['session'] = $userAuth->hash;
        $data['verificationMethods'] = $this->getAvailableMethods($userAuth->user);

        return inertia('Auth/Method', $data);
    }

    public function set(LoginMethodRequest $request, $type, $userAuth)
    {
        $userAuth = get_model('user_auth')::byHash($userAuth);
        $methodName = 'send' . ucfirst($request->method);
        if (method_exists($userAuth, $methodName)) {
            $userAuth->{$methodName}($type);
        }
        return redirect()->route('panel.auth.verification', ['type' => $type, 'userAuth' => $userAuth->hash]);
    }
}
