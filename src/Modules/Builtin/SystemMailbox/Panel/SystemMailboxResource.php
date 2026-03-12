<?php

namespace Upsoftware\Svarium\Modules\Builtin\SystemMailbox\Panel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\MailManager;
use Throwable;
use Upsoftware\Svarium\Http\RedirectResult;
use Upsoftware\Svarium\Modules\Builtin\Support\AuthorizesResourcePermissions;
use Upsoftware\Svarium\Modules\Builtin\SystemMailbox\Tables\SystemMailboxTable;
use Upsoftware\Svarium\Models\SystemMailbox;
use Upsoftware\Svarium\Panel\PanelContext;
use Upsoftware\Svarium\Panel\Resource;
use Upsoftware\Svarium\UI\Components\Button;
use Upsoftware\Svarium\Panel\Table\Table;
use Upsoftware\Svarium\Panel\Table\TableBuilder;
use Upsoftware\Svarium\UI\Components\Form\Input;
use Upsoftware\Svarium\UI\Components\Form\Select;
use Upsoftware\Svarium\UI\Components\Repeater;
use Upsoftware\Svarium\UI\Components\Toggle;

class SystemMailboxResource extends Resource
{
    use AuthorizesResourcePermissions;

    protected static ?string $slug = 'system/mailboxes';

    public static function model(): string
    {
        $configured = config('upsoftware.models.system_mailbox');

        if (is_string($configured) && $configured !== '' && class_exists($configured)) {
            return $configured;
        }

        return SystemMailbox::class;
    }

    public function fields(): array
    {
        return [
            'name' => __('Name'),
            'status' => __('Status'),
            'is_default' => __('Default'),
            'scope_type' => __('Scope'),
            'scope_id' => __('Scope ID'),
            'driver' => __('Driver'),
            'host' => __('Host'),
            'port' => __('Port'),
            'encryption' => __('Encryption'),
            'username' => __('Username'),
            'password' => __('Password'),
            'from_name' => __('From name'),
            'from_email' => __('From email'),
            'reply_to_email' => __('Reply-to email'),
            'config' => __('Additional configuration'),
            'updated_at' => __('Updated at'),
        ];
    }

    public function form(?Model $record = null): array
    {
        return [
            Input::make('name')
                ->label(__('Name'))
                ->required(),

            Toggle::make('status')
                ->label(__('Status'))
                ->value($record ? (bool) ($record->status ?? true) : true),

            Toggle::make('is_default')
                ->label(__('Default'))
                ->hint(__('Use this mailbox as default for selected scope.'))
                ->value($record ? (bool) ($record->is_default ?? false) : false),

            Select::make('scope_type')
                ->label(__('Scope'))
                ->required()
                ->options([
                    ['value' => 'global', 'label' => __('Global')],
                    ['value' => 'tenant', 'label' => __('Tenant')],
                    ['value' => 'domain', 'label' => __('Domain')],
                    ['value' => 'panel', 'label' => __('Panel')],
                ])
                ->value($record ? (string) ($record->scope_type ?? 'global') : 'global'),

            Input::make('scope_id')
                ->label(__('Scope ID'))
                ->type('number')
                ->min(1)
                ->nullable()
                ->value($record ? (string) ($record->scope_id ?? '') : ''),

            Select::make('driver')
                ->label(__('Driver'))
                ->required()
                ->options([
                    ['value' => 'smtp', 'label' => 'SMTP'],
                    ['value' => 'ses', 'label' => 'Amazon SES'],
                    ['value' => 'mailgun', 'label' => 'Mailgun'],
                    ['value' => 'postmark', 'label' => 'Postmark'],
                    ['value' => 'sendmail', 'label' => 'Sendmail'],
                    ['value' => 'log', 'label' => 'Log'],
                ])
                ->value($record ? (string) ($record->driver ?? 'smtp') : 'smtp'),

            Input::make('host')
                ->label(__('Host'))
                ->nullable(),

            Input::make('port')
                ->label(__('Port'))
                ->type('number')
                ->nullable()
                ->value($record ? (string) ($record->port ?? '') : ''),

            Select::make('encryption')
                ->label(__('Encryption'))
                ->options([
                    ['value' => '__none__', 'label' => __('None')],
                    ['value' => 'tls', 'label' => 'TLS'],
                    ['value' => 'ssl', 'label' => 'SSL'],
                    ['value' => 'starttls', 'label' => 'STARTTLS'],
                ])
                ->value($record && trim((string) ($record->encryption ?? '')) !== ''
                    ? (string) $record->encryption
                    : '__none__'),

            Input::make('username')
                ->label(__('Username'))
                ->nullable(),

            Input::make('password')
                ->label(__('Password'))
                ->type('password')
                ->nullable()
                ->hint(__('Leave empty to keep current password.')),

            Input::make('from_name')
                ->label(__('From name'))
                ->nullable(),

            Input::make('from_email')
                ->label(__('From email'))
                ->type('email')
                ->email()
                ->nullable(),

            Input::make('reply_to_email')
                ->label(__('Reply-to email'))
                ->type('email')
                ->email()
                ->nullable(),

            Repeater::make('config')
                ->label(__('Additional configuration'))
                ->mode('key')
                ->labels(__('Attribute'), __('Value'))
                ->addLabel(__('Add item'))
                ->removeLabel(__('Remove'))
                ->values($record ? $this->formatConfigForRepeater($record->getAttribute('config')) : []),
        ];
    }

    public function formActions(PanelContext $context, ?Model $record = null): array
    {
        return [
            Button::make(__('Test connection'))
                ->type('submit')
                ->name('_action')
                ->value('test_connection')
                ->variant('outline')
                ->icon('lucide:plug-zap'),
        ];
    }

    public function handleFormAction(
        PanelContext $context,
        string $action,
        array $data,
        ?Model $record = null
    ): ?RedirectResult {
        if ($action !== 'test_connection') {
            return null;
        }

        $redirect = trim((string) $context->request()->path(), '/');
        if ($redirect === '') {
            $redirect = '/';
        }

        try {
            $config = $this->buildTransportConfigFromData($data, $record);
            $this->validateRequiredTransportConfig($config);

            $transport = app(MailManager::class)->createSymfonyTransport($config);
            if ($transport instanceof \Symfony\Component\Mailer\Transport\Smtp\SmtpTransport) {
                $transport->start();
                $transport->stop();
            }

            return RedirectResult::to($redirect)
                ->success(__('Connection test passed.'));
        } catch (Throwable $e) {
            $message = trim((string) $e->getMessage());
            if ($message === '') {
                $message = __('Unknown error');
            }

            return RedirectResult::to($redirect)
                ->error(__('Connection test failed: :message', [
                    'message' => $message,
                ]));
        }
    }

    public function table(): TableBuilder
    {
        return Table::make(SystemMailboxTable::class);
    }

    public function beforeSave(Model $model, array &$data): void
    {
        $data['status'] = $this->toBool($data['status'] ?? true);
        $data['is_default'] = $this->toBool($data['is_default'] ?? false);

        $scopeId = trim((string) ($data['scope_id'] ?? ''));
        $data['scope_id'] = $scopeId !== '' ? (int) $scopeId : null;

        $port = trim((string) ($data['port'] ?? ''));
        $data['port'] = $port !== '' ? (int) $port : null;

        $encryption = strtolower(trim((string) ($data['encryption'] ?? '')));
        if ($encryption === '' || $encryption === '__none__' || $encryption === 'none' || $encryption === 'null') {
            $data['encryption'] = null;
        }

        $password = trim((string) ($data['password'] ?? ''));
        if ($password === '') {
            unset($data['password']);
        }

        $data['config'] = $this->normalizeAdditionalConfigInput($data['config'] ?? null);
    }

    public function afterSave(Model $model): void
    {
        if (! $this->toBool($model->getAttribute('is_default'))) {
            return;
        }

        $query = $model::query()
            ->whereKeyNot($model->getKey())
            ->where('scope_type', (string) $model->getAttribute('scope_type'));

        $scopeId = $model->getAttribute('scope_id');
        if ($scopeId === null || $scopeId === '') {
            $query->whereNull('scope_id');
        } else {
            $query->where('scope_id', (int) $scopeId);
        }

        $query->update(['is_default' => false]);
    }

    public function canList(PanelContext $context): bool
    {
        return $this->canResourceAction($context, 'list');
    }

    public function canCreate(PanelContext $context): bool
    {
        return $this->canResourceAction($context, 'create');
    }

    public function canEdit(PanelContext $context): bool
    {
        return $this->canResourceAction($context, 'edit');
    }

    public function canDelete(PanelContext $context): bool
    {
        return $this->canResourceAction($context, 'delete');
    }

    public function canDuplicate(PanelContext $context): bool
    {
        return false;
    }

    public function canPreview(PanelContext $context): bool
    {
        return false;
    }

    public function canImport(PanelContext $context): bool
    {
        return false;
    }

    protected function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'on', 'yes'], true);
    }

    protected function formatConfigForRepeater(mixed $config): array
    {
        if (is_string($config)) {
            $decoded = json_decode($config, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $config = $decoded;
            }
        }

        if (! is_array($config)) {
            return [];
        }

        $rows = [];

        foreach ($config as $key => $value) {
            if (is_int($key) && is_array($value)) {
                if (array_key_exists('key', $value)) {
                    $dynamicKey = trim((string) ($value['key'] ?? ''));
                    if ($dynamicKey === '') {
                        continue;
                    }

                    $rows[] = [
                        $dynamicKey => $this->stringifyConfigValue($value['value'] ?? ''),
                    ];
                    continue;
                }

                foreach ($value as $innerKey => $innerValue) {
                    $normalizedKey = trim((string) $innerKey);
                    if ($normalizedKey === '') {
                        continue;
                    }

                    $rows[] = [
                        $normalizedKey => $this->stringifyConfigValue($innerValue),
                    ];
                }

                continue;
            }

            $normalizedKey = trim((string) $key);
            if ($normalizedKey === '') {
                continue;
            }

            $rows[] = [
                $normalizedKey => $this->stringifyConfigValue($value),
            ];
        }

        return array_values($rows);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function buildTransportConfigFromData(array $data, ?Model $record = null): array
    {
        $driver = strtolower(trim((string) ($data['driver'] ?? 'smtp')));
        if ($driver === '') {
            $driver = 'smtp';
        }

        $config = [
            'transport' => $driver,
        ];

        $host = trim((string) ($data['host'] ?? ''));
        if ($host !== '') {
            $config['host'] = $host;
        }

        $port = (int) (trim((string) ($data['port'] ?? '')) ?: 0);
        if ($port > 0) {
            $config['port'] = $port;
        }

        $encryption = strtolower(trim((string) ($data['encryption'] ?? '')));
        if ($encryption === '__none__' || $encryption === 'none' || $encryption === 'null') {
            $encryption = '';
        }
        if ($encryption !== '') {
            $config['scheme'] = in_array($encryption, ['ssl', 'smtps'], true) ? 'smtps' : 'smtp';
        }

        $username = trim((string) ($data['username'] ?? ''));
        if ($username !== '') {
            $config['username'] = $username;
        }

        $password = trim((string) ($data['password'] ?? ''));
        if ($password === '' && $record instanceof Model) {
            $password = trim((string) ($record->getAttribute('password') ?? ''));
        }

        if ($password !== '') {
            $config['password'] = $password;
        }

        $additional = $this->normalizeAdditionalConfigInput($data['config'] ?? null);

        if (is_array($additional) && $additional !== []) {
            $config = array_replace_recursive($config, $additional);
        }

        return $config;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function validateRequiredTransportConfig(array $config): void
    {
        $driver = strtolower(trim((string) ($config['transport'] ?? 'smtp')));

        if ($driver !== 'smtp') {
            return;
        }

        $host = trim((string) ($config['host'] ?? ''));
        if ($host === '') {
            throw new \RuntimeException(__('SMTP host is required for connection test.'));
        }
    }

    protected function normalizeAdditionalConfigInput(mixed $config): ?array
    {
        if (is_string($config)) {
            $trimmed = trim($config);
            if ($trimmed === '') {
                return null;
            }

            $decoded = json_decode($trimmed, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $config = $decoded;
            } else {
                return null;
            }
        }

        if (! is_array($config) || $config === []) {
            return null;
        }

        $normalized = [];

        foreach ($config as $outerKey => $outerValue) {
            if (is_array($outerValue)) {
                if (array_key_exists('key', $outerValue)) {
                    $dynamicKey = trim((string) ($outerValue['key'] ?? ''));
                    if ($dynamicKey === '') {
                        continue;
                    }

                    $normalized[$dynamicKey] = $this->castConfigValue($outerValue['value'] ?? null);
                    continue;
                }

                foreach ($outerValue as $innerKey => $innerValue) {
                    $dynamicKey = trim((string) $innerKey);
                    if ($dynamicKey === '') {
                        continue;
                    }

                    $normalized[$dynamicKey] = $this->castConfigValue($innerValue);
                }

                continue;
            }

            if (! is_int($outerKey)) {
                $dynamicKey = trim((string) $outerKey);
                if ($dynamicKey !== '') {
                    $normalized[$dynamicKey] = $this->castConfigValue($outerValue);
                }
            }
        }

        return $normalized !== [] ? $normalized : null;
    }

    protected function stringifyConfigValue(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_array($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE);
            return is_string($encoded) ? $encoded : '';
        }

        if ($value === null) {
            return '';
        }

        return (string) $value;
    }

    protected function castConfigValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value) || is_int($value) || is_float($value) || is_array($value)) {
            return $value;
        }

        $string = trim((string) $value);

        if ($string === '') {
            return '';
        }

        $lower = strtolower($string);
        if (in_array($lower, ['true', 'false'], true)) {
            return $lower === 'true';
        }

        if (preg_match('/^-?\d+$/', $string) === 1) {
            return (int) $string;
        }

        if (preg_match('/^-?\d+\.\d+$/', $string) === 1) {
            return (float) $string;
        }

        if ((str_starts_with($string, '{') && str_ends_with($string, '}'))
            || (str_starts_with($string, '[') && str_ends_with($string, ']'))) {
            $decoded = json_decode($string, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return $string;
    }
}
