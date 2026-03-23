# API: Logowanie i OTP (pełny flow)

Ten dokument opisuje kompletny proces logowania przez API w Svarium:
- login `email + password`,
- decyzja czy OTP jest wymagane,
- wysłanie kodu OTP,
- weryfikacja kodu OTP,
- wydanie tokenu API,
- zapis urządzenia (`devices`, `device_user`).

## Zakres i implementacja

Aktualny flow opiera się na kontrolerach:
- `Upsoftware\Svarium\Http\Controllers\ApiAuthLoginController`
- `Upsoftware\Svarium\Http\Controllers\ApiAuthOtpSendController`
- `Upsoftware\Svarium\Http\Controllers\ApiAuthOtpVerifyController`

## Endpointy

Domyślny prefix API to `api/v1` (`config('upsoftware.api.prefix')`):

1. `POST /api/v1/auth/login`
2. `POST /api/v1/auth/otp/{userAuth}/send`
3. `POST /api/v1/auth/otp/{userAuth}/verify`

Ważne:
- endpointy auth API są rejestrowane bez `ValidateCsrfToken`, więc dla tych endpointów nie wymagamy CSRF,
- middleware API bierze się z `upsoftware.middleware.api`,
- `LocaleMiddleware` jest dokładany do grupy API auth.

## Nagłówki i język odpowiedzi

Minimalnie:

```http
Accept: application/json
Content-Type: application/json
```

Wymuszenie języka odpowiedzi:
- nagłówek `X-Svarium-Locale: pl`,
- albo `_locale` w query/body.

## Szybki scenariusz end-to-end

1. Wywołujesz `POST /api/v1/auth/login`.
2. Jeżeli dostaniesz `status=authenticated`, masz token i kończysz flow.
3. Jeżeli dostaniesz `status=otp_required`, wyślij kod przez `otp_send_url`.
4. Zweryfikuj kod przez `otp_verify_url`.
5. Przy poprawnej weryfikacji dostajesz `status=authenticated` i token.

## 1) Login: `POST /api/v1/auth/login`

### Request

Wymagane pola:
- `email` (email),
- `password` (string).

Opcjonalne:
- `device_name` (string, max 191),
- `device_uuid` (uuid).

Przykład:

```json
{
  "email": "jan@example.com",
  "password": "haslo123",
  "device_name": "mobile_550e8400-e29b-41d4-a716-446655440000",
  "device_uuid": "550e8400-e29b-41d4-a716-446655440000"
}
```

### Odpowiedź 200: `authenticated` (OTP niepotrzebne)

```json
{
  "status": "authenticated",
  "token": "1|abc123...",
  "requires_otp": false,
  "user": {
    "id": 1,
    "name": "Jan Kowalski",
    "email": "jan@example.com",
    "email_verified_at": "2026-01-15T10:00:00Z",
    "roles": [
      { "id": 1, "name": "Administrator", "guard_name": "web" }
    ],
    "institutions": [
      {
        "id": 1,
        "hash": "abc123hash",
        "short_name": "PPP1",
        "name": "Poradnia Psychologiczno-Pedagogiczna Nr 1",
        "sio": null,
        "default": true
      }
    ]
  }
}
```

### Odpowiedź 200: `otp_required` (OTP wymagane)

```json
{
  "status": "otp_required",
  "token": null,
  "requires_otp": true,
  "otp_token": "q52Aol9YDJbrNP4aleG63dzynRAwQmv0r8XV2pYZx0W9goKkMLE7qj15VM30kPnE",
  "otp_url": "https://example.com/api/v1/auth/otp/{token}/send",
  "otp_resend_url": "https://example.com/api/v1/auth/otp/{token}/send",
  "otp_resend_after": 60,
  "otp_verify_url": "https://example.com/api/v1/auth/otp/{token}/verify",
  "otp_methods": [
    {
      "id": "email",
      "disabled": false,
      "label": "Email message",
      "description": "Email message to the registered email address"
    }
  ]
}
```

### Odpowiedź 422: `invalid` (błędne dane logowania)

```json
{
  "status": "invalid",
  "message": "The given data was invalid.",
  "errors": {
    "email": ["Nieprawidłowe dane logowania"]
  }
}
```

## 2) Wysyłka OTP: `POST /api/v1/auth/otp/{userAuth}/send`

Endpoint do pierwszej wysyłki lub ponownej wysyłki kodu.

### Request

Wymagane:
- `type`: `login | register | reset`,
- `method`: `app | sms | email`.

Przykład:

```json
{
  "type": "login",
  "method": "email"
}
```

### Odpowiedź 200: `otp_code_sent`

```json
{
  "status": "otp_code_sent",
  "requires_otp": true,
  "otp_token": "....",
  "otp_send_url": "https://example.com/api/v1/auth/otp/{token}/send",
  "otp_resend_url": "https://example.com/api/v1/auth/otp/{token}/send",
  "otp_verify_url": "https://example.com/api/v1/auth/otp/{token}/verify",
  "method": "email",
  "otp_resend_after": 60,
  "message": "A new verification code has been sent."
}
```

### Odpowiedź 200: `otp_code_active`

Gdy kod już istnieje i jest aktywny:

```json
{
  "status": "otp_code_active",
  "requires_otp": true,
  "otp_token": "....",
  "otp_send_url": "https://example.com/api/v1/auth/otp/{token}/send",
  "otp_resend_url": "https://example.com/api/v1/auth/otp/{token}/send",
  "otp_verify_url": "https://example.com/api/v1/auth/otp/{token}/verify",
  "method": "email",
  "retry_after": 42,
  "otp_resend_after": 42,
  "message": "Verification code is already active."
}
```

### Odpowiedź 429: `otp_rate_limited`

```json
{
  "status": "otp_rate_limited",
  "requires_otp": true,
  "otp_token": "....",
  "otp_send_url": "https://example.com/api/v1/auth/otp/{token}/send",
  "otp_resend_url": "https://example.com/api/v1/auth/otp/{token}/send",
  "otp_verify_url": "https://example.com/api/v1/auth/otp/{token}/verify",
  "method": "email",
  "retry_after": 42,
  "otp_resend_after": 42,
  "message": "Too many resend requests. Try again in 42 seconds."
}
```

### Typowe 422 na `send`

- niepoprawny lub wygasły `userAuth` (token sesji OTP),
- `type` nie pasuje do tokenu OTP,
- `method` jest niedozwolona globalnie lub niedostępna dla usera.

## 3) Weryfikacja OTP: `POST /api/v1/auth/otp/{userAuth}/verify`

### Request

Wymagane:
- `type`: `login | register | reset`,
- `code`: string.

Opcjonalne:
- `remember`: boolean (używane dla `type=login`),
- `device_name`: string,
- `device_uuid`: uuid.

Przykład:

```json
{
  "type": "login",
  "code": "123456",
  "remember": true,
  "device_name": "mobile_550e8400-e29b-41d4-a716-446655440000",
  "device_uuid": "550e8400-e29b-41d4-a716-446655440000"
}
```

### Odpowiedź 200: `authenticated` (`login` / `register`)

```json
{
  "status": "authenticated",
  "token": "1|abc123...",
  "requires_otp": false,
  "user": {
    "id": 1,
    "name": "Jan Kowalski",
    "email": "jan@example.com",
    "email_verified_at": "2026-01-15T10:00:00Z",
    "roles": [
      { "id": 1, "name": "Administrator", "guard_name": "web" }
    ]
  }
}
```

Uwagi:
- dla `type=register` ustawiane jest `email_verified_at`,
- payload `user` z `verify` nie zawiera `institutions` (to jest zwracane przez endpoint login).

### Odpowiedź 200: `otp_verified` (`reset`)

```json
{
  "status": "otp_verified",
  "requires_otp": false,
  "otp_token": "....",
  "message": "Verification code accepted."
}
```

### Odpowiedź 422: `invalid` (zły kod)

```json
{
  "status": "invalid",
  "requires_otp": true,
  "otp_token": "....",
  "otp_resend_url": "https://example.com/api/v1/auth/otp/{token}/send",
  "otp_resend_after": 60,
  "message": "Invalid verification code",
  "errors": {
    "code": ["Invalid verification code"]
  }
}
```

### Odpowiedź 429: `otp_locked` (za dużo błędnych prób)

```json
{
  "status": "otp_locked",
  "requires_otp": true,
  "otp_token": "....",
  "otp_resend_url": "https://example.com/api/v1/auth/otp/{token}/send",
  "otp_resend_after": 60,
  "retry_after": 900,
  "message": "Too many invalid attempts. Try again in 900 seconds.",
  "errors": {
    "code": ["Too many invalid attempts. Try again in 900 seconds."]
  }
}
```

## Mapa statusów

- `invalid` – niepoprawne dane wejściowe lub kod OTP.
- `otp_required` – login poprawny, ale trzeba przejść przez OTP.
- `otp_code_sent` – kod OTP został wysłany.
- `otp_code_active` – aktywny kod już istnieje.
- `otp_rate_limited` – limit ponowień wysyłki.
- `otp_locked` – blokada po wielu błędnych kodach.
- `otp_verified` – OTP poprawny dla flow resetu.
- `authenticated` – flow zakończony, wydany token API.

## Token API (driver)

Driver wybierasz przez `config('upsoftware.api.auth.driver')`:
- `sanctum` – zwracany `plainTextToken`,
- `jwt` – zwracany token JWT (`auth(guard)->login($user)`),
- `passport` – zwracany access token,
- `custom` – własny handler `createToken($user, $deviceName)`.

## Zapis urządzenia (`devices`, `device_user`)

Po `authenticated` (z loginu bez OTP albo po verify) system:
- tworzy/aktualizuje rekord urządzenia (`device_uuid`, `ip`, `device_type=api`, `data` z `device_name`, `user_agent`),
- tworzy/aktualizuje powiązanie user-urządzenie w `device_user` i zapisuje `verified_at`.

Jeżeli nie podasz `device_uuid`, UUID jest generowany automatycznie.

## Konfiguracja, która wpływa na login API

Najważniejsze klucze:
- `upsoftware.api.enabled`
- `upsoftware.api.prefix`
- `upsoftware.api.auth.driver`
- `upsoftware.api.auth.guard`
- `upsoftware.middleware.api`
- `upsoftware.auth.otp.enabled`
- `upsoftware.auth.otp.methods`
- `upsoftware.auth.otp.token_ttl_minutes`
- `upsoftware.auth.otp.resend_seconds`
- `upsoftware.auth.otp.verification.max_failed_attempts`
- `upsoftware.auth.otp.verification.lock_minutes`
- `upsoftware.auth.otp.allow_user_disable`
- `upsoftware.auth.otp.default_enabled`
- `upsoftware.auth.otp.show_all_methods`

## Przykład klienta (minimalny)

1. `POST /api/v1/auth/login`
- jeśli `authenticated` -> zapisz `token`,
- jeśli `otp_required` -> przejdź do kroku 2.

2. `POST /api/v1/auth/otp/{userAuth}/send`
- wybierz `method` i wyślij kod.

3. `POST /api/v1/auth/otp/{userAuth}/verify`
- jeśli `authenticated` -> zapisz `token`,
- jeśli `otp_verified` (`reset`) -> kontynuuj flow resetu hasła.

## Powiązane

- [AuthLoginService (logowanie + OTP)](./auth-login-service.md)
- [Konfiguracja `config/upsoftware.php`](./config-upsoftware.md)
