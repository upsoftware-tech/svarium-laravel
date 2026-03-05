# Komponent Pin (PHP + Vue)

Komponent `Pin` służy do wprowadzania kodów OTP / kodów weryfikacyjnych jako pola wielo-slotowego.

## Lokalizacja

- PHP: `Upsoftware\Svarium\UI\Components\Pin`
- Vue: `packages/svarium-npm/src/components/pin/Pin.vue`

## Podstawowe użycie (PHP)

```php
use Upsoftware\Svarium\UI\Components\Pin;

Pin::make('code')
    ->label(__('Verification code'))
    ->maxlength(6);
```

## API (PHP)

`Pin` dziedziczy po `FieldComponent`, więc działa z:

- `->label(...)`
- `->hint(...)`
- `->required()`, `->rules(...)`, itd.

Dodatkowe metody specyficzne dla `Pin`:

- `->maxlength(int $length)`  
  Ustawia długość kodu (minimalnie 1).

- `->pattern(string $pattern)`  
  Ustawia pattern wejścia. Obsługiwane skróty:
  - `digits`
  - `chars`
  - `digits_and_chars`
  - `alnum`
  Możesz też podać własny regex string.

- `->onlyDigits()`  
  Skrót dla `->pattern('digits')`

- `->onlyChars()`  
  Skrót dla `->pattern('chars')`

- `->onlyDigitsAndChars()`  
  Skrót dla `->pattern('digits_and_chars')`

## Patterny w Vue (`vue-input-otp`)

Komponent mapuje pattern na stałe:

- `REGEXP_ONLY_DIGITS = "^\\d+$"`
- `REGEXP_ONLY_CHARS = "^[a-zA-Z]+$"`
- `REGEXP_ONLY_DIGITS_AND_CHARS = "^[a-zA-Z0-9]+$"`

Przykład (idea po stronie Vue):

```vue
<InputOTP :maxlength="6" :pattern="REGEXP_ONLY_DIGITS_AND_CHARS" />
```

## Zachowanie długości (`maxlength`)

- `maxlength(5)` -> renderuje 5 pól
- dla wartości parzystych `>= 6` komponent dzieli pola na dwie grupy (np. `6 = 3 + 3`, `8 = 4 + 4`)

## Przykłady

Tylko cyfry:

```php
Pin::make('code')
    ->label(__('Verification code'))
    ->maxlength(6)
    ->onlyDigits();
```

Tylko litery:

```php
Pin::make('token')
    ->maxlength(8)
    ->onlyChars();
```

Cyfry + litery:

```php
Pin::make('otp')
    ->maxlength(6)
    ->onlyDigitsAndChars();
```

Własny regex:

```php
Pin::make('custom')
    ->maxlength(6)
    ->pattern('^[A-F0-9]+$');
```

## Przykład w `VerificationOperation`

```php
Pin::make('code')
    ->label(__('Verification code'))
    ->maxlength(6)
    ->onlyDigits();
```
