<?php

namespace Upsoftware\Svarium\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class LogoutController extends Controller
{
    public function __invoke(Request $request): RedirectResponse {

        activity('logout')
            ->causedBy($request->user())
            ->withProperties([
                'tenant_id' => tenant() ? tenant()->id : null,
                'role_id' => null,
            ])->log('logout');

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('panel.auth.login')->with(['success' => 'Zostałeś poprawnie wylogowany']);
    }
}
