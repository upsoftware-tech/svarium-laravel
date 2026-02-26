<?php

namespace Upsoftware\Svarium\Support;

use Winter\LaravelConfigWriter\EnvFile;

class QuotedEnvFile extends EnvFile
{
    protected function castValue($value): string
    {
        $value = parent::castValue($value);

        if (!is_string($value)) {
            return $value;
        }

        if (preg_match('/\s|#|=|\$/', $value)) {
            $this->forceQuoted = true;
        }

        return $value;
    }

    protected bool $forceQuoted = false;

    public function set($key, $value = null)
    {
        $this->forceQuoted = false;

        parent::set($key, $value);

        if ($this->forceQuoted) {
            foreach ($this->ast as $i => $item) {
                if ($item['token'] === $this->lexer::T_ENV && $item['value'] === $key) {
                    if (isset($this->ast[$i + 1])) {
                        $this->ast[$i + 1]['token'] = $this->lexer::T_QUOTED_VALUE;
                    }
                }
            }
        }

        return $this;
    }
}
