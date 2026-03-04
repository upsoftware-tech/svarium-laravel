# UI konfiguracji Svarium

Od teraz podstawową konfigurację możesz ustawić przez panel UI, bez uruchamiania komend interaktywnych.

## Dostępne strony

- `svarium/install` – szybkie akcje instalacyjne
- `svarium/configuration` – pełna konfiguracja panel/API/rejestracji/tenancy

Adres zależy od panelu:
- panel z prefiksem `admin`: `/admin/svarium/install`, `/admin/svarium/configuration`
- panel `noPrefix()`: `/svarium/install`, `/svarium/configuration`

## Co robi `/svarium/install`

Szybkie akcje:
- `Run migrate`
- `Run optimize:clear`
- `Run native:install`
- `Install tenant DB config` (`svarium:tenant.install --no-interaction`)  
  Komenda pyta też o włączenie tenancy/domen oraz opcjonalne migracje tabel.
- przejście do pełnej konfiguracji

## Co robi `/svarium/configuration`

Strona zapisuje kluczowe ustawienia:
- panel (`name`, `prefix/no_prefix`, `route_prefix`)
- języki (`app locale`, `fallback locale`)
- API (`enabled`, `prefix`, `driver`, `guard`)
- rejestracja (`enabled`, `auto_login`, `activation mode`)
- OTP (`enabled`, `methods`, `allow user disable`, `default enabled`)
- tenancy (`enabled`, `mode`, `domains enabled`, `central domains`)
- owner tenancy (`enabled`, `type column`, `id column`, `owner map alias=Model`)
- profil tenanta (`enabled`, `table`, `foreign key`, `model`)
- mapowanie domen tenant (`model_has_domains` + `domain_id`)

Dodatkowe opcje po zapisie:
- uruchomienie migracji
- uruchomienie `native:install`
- uruchomienie `optimize:clear`
- instalacja połączeń tenant DB

## Gdzie zapisywana jest konfiguracja

- `config/upsoftware.php`
- `.env` (`SVARIUM_PANEL_NAME`, `SVARIUM_API_DRIVER`, `SVARIUM_TENANCY_ENABLED`, `SVARIUM_TENANCY_MODE`, `SVARIUM_TENANCY_DOMAINS_ENABLED`)
- jeśli brak `app/Svarium/panels.php`, plik zostanie utworzony automatycznie

## Uwagi

- Operacje UI są rejestrowane dla wszystkich paneli (`$panels = '*'`).
- Jeśli panel ma middleware `auth`, strony UI również wymagają logowania.
