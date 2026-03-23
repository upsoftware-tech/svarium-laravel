<?php

namespace Upsoftware\Svarium\Console\Commands;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class UserResetCommand extends CoreCommand
{
    protected bool $generatedPassword = false;

    protected $signature = 'svarium:user.reset
        {--user= : ID lub adres e-mail użytkownika}
        {--password= : Nowe hasło użytkownika}
        {--random-password : Wygeneruj losowe hasło}';

    protected $description = 'Resetuje hasło użytkownika na podstawie ID lub e-maila';

    public function handle(): int
    {
        try {
            $userModelClass = $this->resolveUserModelClass();
            $this->validateUserStorage($userModelClass);

            $user = $this->resolveUser($userModelClass);
            $password = $this->resolvePassword();

            $this->updateUserPassword($user, $password);

            $this->info('Hasło użytkownika zostało zresetowane.');
            $this->line('Użytkownik: '.$this->userDisplay($user));
            $this->line('ID: '.(string) $user->getKey());
            $this->line('E-mail: '.trim((string) ($user->getAttribute('email') ?? '')));

            if ($this->generatedPassword) {
                $this->line('Nowe hasło: '.$password);
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @param class-string<Model> $userModelClass
     */
    protected function validateUserStorage(string $userModelClass): void
    {
        /** @var Model $prototype */
        $prototype = new $userModelClass();
        $table = $prototype->getTable();

        if (! Schema::hasTable($table)) {
            throw new RuntimeException("Tabela użytkowników [{$table}] nie istnieje.");
        }

        if (! Schema::hasColumn($table, 'email')) {
            throw new RuntimeException("Tabela [{$table}] nie ma kolumny email.");
        }

        if (! Schema::hasColumn($table, 'password')) {
            throw new RuntimeException("Tabela [{$table}] nie ma kolumny password.");
        }
    }

    /**
     * @param class-string<Model> $userModelClass
     */
    protected function resolveUser(string $userModelClass): Model
    {
        $identifier = trim((string) $this->option('user'));

        while (true) {
            if ($identifier === '') {
                if (! $this->input->isInteractive()) {
                    throw new RuntimeException('Podaj użytkownika przez --user={id|email}.');
                }

                $identifier = trim((string) $this->ask('Wpisz adres e-mail lub ID użytkownika'));
                continue;
            }

            $user = $this->findUserByIdentifier($userModelClass, $identifier);
            if ($user instanceof Model) {
                return $user;
            }

            if (! $this->input->isInteractive()) {
                throw new RuntimeException("Nie znaleziono użytkownika [{$identifier}].");
            }

            $this->error("Nie znaleziono użytkownika [{$identifier}].");
            $identifier = '';
        }
    }

    /**
     * @param class-string<Model> $userModelClass
     */
    protected function findUserByIdentifier(string $userModelClass, string $identifier): ?Model
    {
        /** @var Model $prototype */
        $prototype = new $userModelClass();
        $table = $prototype->getTable();
        $keyName = $prototype->getKeyName();

        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        if (is_numeric($identifier)) {
            $found = $userModelClass::query()
                ->where($keyName, (int) $identifier)
                ->first();

            if ($found instanceof Model) {
                return $found;
            }
        }

        if (Schema::hasColumn($table, 'email')) {
            $found = $userModelClass::query()
                ->where('email', Str::lower($identifier))
                ->first();

            if ($found instanceof Model) {
                return $found;
            }
        }

        return null;
    }

    protected function resolvePassword(): string
    {
        $optionPassword = trim((string) $this->option('password'));
        $randomPassword = (bool) $this->option('random-password');

        if ($optionPassword !== '') {
            return $this->validatePasswordStrength($optionPassword);
        }

        if ($randomPassword) {
            $this->generatedPassword = true;

            return Str::random(16);
        }

        if (! $this->input->isInteractive()) {
            throw new RuntimeException('Podaj hasło przez --password albo użyj --random-password.');
        }

        while (true) {
            $password = (string) $this->secret('Wpisz nowe hasło lub zostaw puste jako losowe');
            $password = trim($password);

            if ($password === '') {
                $this->generatedPassword = true;

                return Str::random(16);
            }

            try {
                return $this->validatePasswordStrength($password);
            } catch (RuntimeException $exception) {
                $this->warn($exception->getMessage());
            }
        }
    }

    protected function validatePasswordStrength(string $password): string
    {
        if (mb_strlen($password) < 8) {
            throw new RuntimeException('Hasło musi mieć co najmniej 8 znaków.');
        }

        return $password;
    }

    protected function updateUserPassword(Model $user, string $plainPassword): void
    {
        $user->setAttribute('password', Hash::make($plainPassword));

        try {
            $table = $user->getTable();
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'remember_token')) {
                $user->setAttribute('remember_token', Str::random(60));
            }
        } catch (Throwable) {
            // ignore remember token refresh when schema is unavailable
        }

        if (! $user->save()) {
            throw new RuntimeException('Nie udało się zapisać nowego hasła użytkownika.');
        }
    }

    /**
     * @return class-string<Model>
     */
    protected function resolveUserModelClass(): string
    {
        try {
            if (function_exists('get_model')) {
                $model = get_model('user');
                if (is_string($model) && trim($model) !== '' && class_exists($model) && is_subclass_of($model, Model::class)) {
                    return $model;
                }
            }
        } catch (Throwable) {
            // fallback below
        }

        $trackingUserModel = config('upsoftware.tracking.user_model', config('upsoftware.user_model'));
        if (is_string($trackingUserModel) && trim($trackingUserModel) !== '' && class_exists($trackingUserModel) && is_subclass_of($trackingUserModel, Model::class)) {
            return $trackingUserModel;
        }

        return $this->resolveModelClass('upsoftware.models.user', \Upsoftware\Svarium\Models\User::class);
    }

    /**
     * @param string|class-string<Model>|null $fallback
     * @return class-string<Model>
     */
    protected function resolveModelClass(string $configKey, string|null $fallback = null): string
    {
        $model = config($configKey, $fallback);

        if (! is_string($model) || trim($model) === '' || ! class_exists($model)) {
            throw new RuntimeException("Model pod kluczem [{$configKey}] nie istnieje.");
        }

        if (! is_subclass_of($model, Model::class)) {
            throw new RuntimeException("Model [{$model}] nie dziedziczy po Eloquent Model.");
        }

        return $model;
    }

    protected function userDisplay(Model $user): string
    {
        $id = (string) ($user->getKey() ?? '');
        $email = trim((string) ($user->getAttribute('email') ?? ''));
        $name = trim((string) ($user->getAttribute('name') ?? ''));

        if ($name !== '' && $email !== '') {
            return "{$name} <{$email}> [id:{$id}]";
        }

        if ($email !== '') {
            return "{$email} [id:{$id}]";
        }

        if ($name !== '') {
            return "{$name} [id:{$id}]";
        }

        return "id:{$id}";
    }
}
