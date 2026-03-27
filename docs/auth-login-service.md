# AuthLoginService

Serwis centralizuje logikę logowania:
- walidacja login/hasło,
- sprawdzenie uprawnień użytkownika w tenant,
- obsługa zaufanego urządzenia (remembered browser),
- decyzja OTP (`wymagane` / `wyłączone przez użytkownika`),
- finalne logowanie i zwrot URL przekierowania.

Klasa:
- `Upsoftware\Svarium\Services\Auth\AuthLoginService`

## Statusy

- `AuthLoginService::STATUS_INVALID`
- `AuthLoginService::STATUS_OTP_REQUIRED`
- `AuthLoginService::STATUS_AUTHENTICATED`

## Przykład użycia

```php
use Upsoftware\Svarium\Services\Auth\AuthLoginService;

$service = app(AuthLoginService::class);

$result = $service->attempt($request, (string) $request->email, (string) $request->password);

if ($result['status'] === AuthLoginService::STATUS_INVALID) {
    // błędny login/hasło
}

if ($result['status'] === AuthLoginService::STATUS_OTP_REQUIRED) {
    return redirect()->to($result['otp_url']);
}

if ($result['status'] === AuthLoginService::STATUS_AUTHENTICATED) {
    return redirect()->to($result['redirect_url']);
}
```

## Sprawdzenie OTP dla usera

```php
$requiresOtp = $service->requiresOtp($user);
$otpDisabledByUser = $service->isOtpDisabledByUser($user);
$otpGloballyEnabled = $service->isOtpGloballyEnabled();
$allowedMethods = $service->allowedOtpMethods(); // ['email', 'sms', 'app']
$canDisableOtp = $service->canUserDisableOtp();
```

## Bypass tenanta dla superadmin/admin

Login może omijać standardowe sprawdzanie tenanta dla ról systemowych (`role_key`), zgodnie z konfiguracją:

```php
'auth' => [
    'tenant_bypass_role_keys' => ['superadmin', 'admin'],
    'tenant_bypass_scope' => 'all_tenants', // all_tenants | tenant
],
```

- `all_tenants` – użytkownik z taką rolą zaloguje się na każdej domenie tenantowej.
- `tenant` – bypass działa tylko, jeśli rola jest przypięta do bieżącego tenanta (lub globalnie przez `tenant_id = null`).

W API auth (login + OTP verify) bypass wpływa także na payload tenantów:
- `user.institutions` oraz `user.tenant` zwracają pełną listę tenantów.

## Włączenie/wyłączenie OTP dla użytkownika

```php
// false => wyłączenie OTP przez usera (jeśli polityka globalna na to pozwala)
$ok = $service->setUserOtpEnabled($user, false);
```

## Polityka OTP w konfiguracji

`config/upsoftware.php`:

```php
'auth' => [
    'otp' => [
        'enabled' => true, // globalny włącznik OTP
        'methods' => ['email', 'sms', 'app'], // dozwolone metody
        'allow_user_disable' => true, // czy user może wyłączyć OTP na swoim koncie
        'default_enabled' => true, // domyślny stan dla usera bez settingu otp_status
    ],
],
```

Możesz to też ustawić z UI: `/svarium/configuration` -> zakładka `Auth`:
- `OTP enabled (1|0)`
- `OTP methods (comma separated: email,sms,app)`
- `Allow user disable OTP (1|0)`
- `OTP default enabled for user (1|0)`

## Kolejność działania OTP

Przy `attempt()` serwis podejmuje decyzję w tej kolejności:

1. Weryfikacja login/hasło i roli w tenant.
2. Sprawdzenie „remembered browser”.
3. Decyzja OTP:
   - jeśli `auth.otp.enabled = false` -> OTP pomijane,
   - jeśli brak dostępnej metody OTP dla usera -> OTP pomijane,
   - jeśli `allow_user_disable = false` -> OTP zawsze wymagane,
   - w pozostałych przypadkach brane jest `otp_status` usera (fallback: `default_enabled`).
4. Jeśli OTP wymagane -> status `STATUS_OTP_REQUIRED` + `otp_url`.
5. Jeśli OTP niewymagane -> logowanie `Auth::login()` i `STATUS_AUTHENTICATED`.

## Kiedy metoda OTP jest „dostępna”

- `email`: user ma e-mail i model `user_auth` ma metodę `sendEmail`.
- `sms`: user ma telefon i model `user_auth` ma metodę `sendSms`.
- `app`: user ma sekret aplikacji (`google2fa_secret`/`otp_app_secret`/`two_factor_secret`) i model `user_auth` ma metodę `sendApp`.

Jeśli metoda jest dozwolona w configu, ale technicznie niedostępna dla usera, nie będzie aktywna.

## Własne logowanie z AuthLoginService (Controller/API)

Przykład własnego endpointu logowania (web lub API):

```php
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Upsoftware\Svarium\Services\Auth\AuthLoginService;

class CustomLoginController
{
    public function __invoke(Request $request, AuthLoginService $service)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $result = $service->attempt(
            $request,
            (string) $request->input('email'),
            (string) $request->input('password')
        );

        if ($result['status'] === AuthLoginService::STATUS_INVALID) {
            throw ValidationException::withMessages([
                'email' => ['Nieprawidłowy e-mail lub hasło.'],
            ]);
        }

        if ($result['status'] === AuthLoginService::STATUS_OTP_REQUIRED) {
            return response()->json([
                'otp_required' => true,
                'otp_url' => $result['otp_url'],
            ], 202);
        }

        return response()->json([
            'authenticated' => true,
            'redirect_url' => $result['redirect_url'],
        ]);
    }
}
```

## Jak user może wyłączyć/włączyć OTP na koncie

Własny endpoint ustawień konta:

```php
use Upsoftware\Svarium\Services\Auth\AuthLoginService;

$ok = $service->setUserOtpEnabled(auth()->user(), false);
```

Gdy `allow_user_disable = false`, wyłączenie zwróci `false` i ustawienie usera nie zmieni się.
