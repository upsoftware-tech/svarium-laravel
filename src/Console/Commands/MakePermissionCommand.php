<?php

namespace Upsoftware\Svarium\Console\Commands;

use Illuminate\Console\Command;
use Upsoftware\Svarium\Models\Role;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class MakePermissionCommand extends Command
{
    protected $signature = 'svarium:permission';
    protected $description = 'Tworzy podstawowe ustawienia uprawnień';

    public function handle() {
        $roles = multiselect('Jakie role chcesz utworzyć w systemie?', ['Superadministrator', 'Administrator']);
        foreach($roles as $role) {
            Role::updateOrCreate([
                'name' => $role,
                'guard_name' => 'web'
            ]);
        }

        $newRole = false;
        while (!$newRole) {
            if (!confirm('Czy chcesz dodać kolejną rolę?', false, 'Tak', 'Nie')) {
                $newRole = true;
            } else {
                $role = [];
                foreach (locales() as $locale) {
                    $role[$locale["value"]] = text("Nazwa roli (".$locale["label"].")");
                }
                $guard_name = select('Przestrzeń', ['web' => 'Front / Web', 'api' => 'Api', 'panel' => 'Panel']);

                Role::updateOrCreate([
                    'name' => $role,
                    'guard_name' => $guard_name
                ]);
            }
        }
    }
}
