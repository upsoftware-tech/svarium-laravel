<?php

namespace Upsoftware\Svarium\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Jenssegers\Agent\Agent;
use Upsoftware\Svarium\Facades\DeviceTracker;
use App\Models\User;
use Upsoftware\Svarium\Notifications\LoginFromNewDeviceNotify;

class LoginController extends Controller
{
    public function init(Request $request) {
        if (Auth::check()) {
            return redirect('/');
        }

        $data = get_model('setting')::getSettingGlobal('login.config', []);
        return show('Auth/Login', $data);
    }

    public function loginUser(Request $request, User $user) {

        $remember = $request->has('remember') && ($request->remember === true || $request->remember === "true");
        Auth::login($user);

        $device = DeviceTracker::detectFindAndUpdate();

        if ($device) {
            DeviceTracker::flagCurrentAsVerified();

            if ($device->wasRecentlyCreated) {
                $user->notify(new LoginFromNewDeviceNotify(device()));
            }

            if ($remember) {
                $agent = new Agent();

                $browserId = Str::uuid()->toString();
                $savedBrowser = $user->getSetting('remembered_browsers', [], central_connection());

                if (!isset($savedBrowser[$browserId])) {
                    $savedBrowser[$browserId] = [];
                    $savedBrowser[$browserId] = device();
                    $user->setSetting(['remembered_browsers' => $savedBrowser]);
                }

                Cookie::queue(
                    Str::of(env('APP_NAME'))->slug('_') . '_browser_id',
                    $browserId,
                    60 * 24 * 365 * 5,
                    null,
                    null,
                    true,
                    true
                );
            }
        }

        $browser_id = null;
        $cookie_name = Str::of(env('APP_NAME'))->slug('_') . '_browser_id';
        if ($request->cookie($cookie_name)) {
            $browser_id = $request->cookie($cookie_name);
            if (!isset($savedBrowser[$browser_id])) {
                $browser_id = null;
            }
        }

        activity('login')
            ->causedBy($user)
            ->withProperties(array_merge([
                'tenant_id' => tenant() ? tenant()->id : null,
                'role_id' => null,
                'browser_id' => $browser_id,
            ], device()))->log('login');

        return redirect()->intended('/');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'min:8'],
        ]);

        $user = User::where('email', $request->email)->first();
        $has_role = false;
        $tenant_id = null;
        if ($user) {
            if (tenant() && tenant()->id) {
                $tenant_id = tenant()->id;
            }
            $queryRole = get_model('model_has_role')::where('model_id', $user->id)->where('model_type', 'App\Models\User')->where('status', 1);
            if (config('tenancy.enabled', false)) {
                $queryRole->where('tenant_id', $tenant_id);
            }
            $has_role = $queryRole->count() > 0;
        }
        if (! $user || ! $has_role || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('svarium::validation.Invalid email address or password')],
            ]);
        }

        $connection = null;
        if (config('tenancy.enabled', false)) {
            $connection = 'central';
        }

        $cookie_name = Str::of(env('APP_NAME'))->slug('_') . '_browser_id';
        if ($request->cookie($cookie_name)) {
            $browser_id = $request->cookie($cookie_name);
            $savedBrowser = $user->getSetting('remembered_browsers', [], central_connection());
            if (isset($savedBrowser[$browser_id])) {
                return $this->loginUser($request, $user);
            }
        }
        if ($user->getSetting('otp_status', true, $connection) === true) {
            $userAuth = get_model('user_auth')::setToken($user, 'login');
            return redirect()->route('panel.auth.method', ['type' => 'login', 'userAuth' => $userAuth->hash]);
        } else {
            return $this->loginUser($request, $user);
        }
    }
}
