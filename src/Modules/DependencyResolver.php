<?php

namespace Upsoftware\Svarium\Modules;

use RuntimeException;
class DependencyResolver
{
    public function resolve(ModuleRegistry $modules, ActivationRegistry $activation): bool
    {
        $changed = false;

        foreach ($activation->all() as $moduleClass) {

            $module = $modules->getByClass($moduleClass);

            if (!$module) {
                continue;
            }

            foreach ($module->requires() as $required) {

                if (!$modules->has($required)) {
                    throw new RuntimeException(
                        "Module {$moduleClass} requires missing module {$required}"
                    );
                }

                if (!$activation->isEnabled($required)) {
                    $activation->enable($required);
                    $changed = true;
                }
            }
        }

        return $changed;
    }
}
