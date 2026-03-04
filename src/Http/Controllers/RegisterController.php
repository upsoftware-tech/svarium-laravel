<?php

namespace Upsoftware\Svarium\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Throwable;
use Upsoftware\Svarium\Panel\Panel;
use Upsoftware\Svarium\Panel\PanelRegistry;
use Upsoftware\Svarium\UI\Component;
use Upsoftware\Svarium\UI\Components\Block;
use Upsoftware\Svarium\UI\Components\FieldComponent;
use Upsoftware\Svarium\UI\Components\Flex;
use Upsoftware\Svarium\UI\Components\Form\Input as FormInput;

class RegisterController extends Controller
{
    public function init(Request $request)
    {
        if (Auth::check()) {
            return redirect('/');
        }

        $config = $this->resolveConfig();

        if (! ($config['enabled'] ?? true)) {
            return redirect()->to($this->panelHomeUrl());
        }

        $schema = $this->resolveSchemaComponents($config);
        $fields = $this->collectFieldComponents($schema);
        $this->assertBaseInputs($fields);

        $pageProps = [
            'title' => $config['title'] ?? __('Create account'),
            'subtitle' => $config['subtitle'] ?? __('Fill in the form to create your account'),
            'submitLabel' => $config['submitLabel'] ?? __('Create account'),
            'loginLabel' => $config['loginLabel'] ?? __('Already have an account?'),
            'loginLinkLabel' => $config['loginLinkLabel'] ?? __('Sign in'),
            'loginLink' => $config['loginLink'] ?? 'panel.auth.login',
            'action' => $config['action'] ?? 'panel.auth.register.set',
            'layout' => $config['layout'] ?? 'CleanLayout',
            'schema' => $this->serializeComponents($schema),
            'fields' => $config['fields'] ?? [],
            'component' => $config['component'] ?? null,
            'wrap' => $config['wrap'] ?? null,
            'layout_enabled' => (bool) ($config['layout_enabled'] ?? true),
            'skip_main_layout' => (bool) ($config['skip_main_layout'] ?? false),
        ];

        return show('Svarium', [
            'tree' => $this->buildRegisterTree($pageProps),
            'meta' => [
                'auth' => [
                    'screen' => 'register',
                ],
            ],
        ]);
    }

    public function register(Request $request)
    {
        $config = $this->resolveConfig();

        if (! ($config['enabled'] ?? true)) {
            return redirect()->to($this->panelHomeUrl());
        }

        $schema = $this->resolveSchemaComponents($config);
        $fields = $this->collectFieldComponents($schema);
        $this->assertBaseInputs($fields);

        $rules = $this->validationRulesFromFields($fields, $config);
        $validated = $request->validate($rules, [
            'password.regex' => __('The password must contain lowercase and uppercase letters, a number, and a special character.'),
        ]);

        $user = $this->createUser($validated, $request, $config);

        if (! $user instanceof Authenticatable) {
            throw new \RuntimeException('Registration handler must return an authenticatable user.');
        }

        $this->runRegisteredHooks($user, $request, $config, $validated);

        $activationResponse = $this->handleActivation($user, $request, $config, $validated);
        if ($activationResponse !== null) {
            return $activationResponse;
        }

        if (($config['auto_login'] ?? true) === true) {
            Auth::login($user);
        }

        $successMessage = (string) ($config['success_message'] ?? __('Account has been created.'));

        if (($config['auto_login'] ?? true) === true) {
            return redirect()->to($this->redirectTarget($config))
                ->with(['success' => $successMessage]);
        }

        $loginRoute = (string) ($config['login_redirect_route'] ?? 'panel.auth.login');

        return redirect()->route($loginRoute)
            ->with(['success' => $successMessage]);
    }

    protected function resolveConfig(): array
    {
        $fromConfig = config('upsoftware.auth.register', []);
        if (! is_array($fromConfig)) {
            $fromConfig = [];
        }

        $fromSettings = $this->safe(
            fn () => get_model('setting')::getSettingGlobal('register.config', []),
            []
        );

        if (! is_array($fromSettings)) {
            $fromSettings = [];
        }

        $fromPanel = $this->resolvePanelRegistrationConfig();

        $fields = array_merge(
            $this->defaultFields(),
            isset($fromConfig['fields']) && is_array($fromConfig['fields']) ? $fromConfig['fields'] : [],
            isset($fromSettings['fields']) && is_array($fromSettings['fields']) ? $fromSettings['fields'] : [],
            isset($fromPanel['fields']) && is_array($fromPanel['fields']) ? $fromPanel['fields'] : [],
        );

        return [
            ...$this->defaultConfig(),
            ...$fromConfig,
            ...$fromSettings,
            ...$fromPanel,
            'fields' => $this->normalizeFields($fields),
        ];
    }

    protected function defaultConfig(): array
    {
        return [
            'enabled' => true,
            'auto_login' => true,
            'redirect_to' => '/',
            'success_message' => __('Account has been created.'),
            'password_rules' => [
                'required',
                'string',
                'min:8',
            ],
            'layout' => 'CleanLayout',
            'layout_enabled' => true,
            'skip_main_layout' => false,
            'component' => null,
            'wrap' => null,
            'activation' => [
                'mode' => 'none', // none | email_code | email_link | custom
                'verification_route' => 'panel.auth.verification',
                'verification_type' => 'register',
                'custom_handler' => null,
            ],
            'events' => [
                'dispatch_registered' => true,
                'dispatch' => [],
                'listeners' => [],
            ],
            'schema' => null,
            'fields' => $this->defaultFields(),
        ];
    }

    protected function defaultFields(): array
    {
        return [
            [
                'name' => 'email',
                'label' => __('Email address'),
                'type' => 'email',
                'required' => true,
                'autocomplete' => 'email',
                'rules' => ['required', 'email'],
            ],
            [
                'name' => 'password',
                'label' => __('Password'),
                'type' => 'password',
                'required' => true,
                'autocomplete' => 'new-password',
            ],
            [
                'name' => 'company',
                'label' => __('Company'),
                'type' => 'text',
                'required' => false,
                'autocomplete' => 'organization',
            ],
        ];
    }

    protected function normalizeFields(array $fields): array
    {
        $normalized = [];
        $seen = [];

        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }

            $name = trim((string) ($field['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            if (isset($seen[$name])) {
                $normalized[$seen[$name]] = [
                    ...$normalized[$seen[$name]],
                    ...$field,
                    'name' => $name,
                ];

                continue;
            }

            $seen[$name] = count($normalized);
            $normalized[] = [
                'name' => $name,
                'type' => (string) ($field['type'] ?? 'text'),
                'label' => (string) ($field['label'] ?? ucfirst(str_replace('_', ' ', $name))),
                'placeholder' => (string) ($field['placeholder'] ?? ''),
                'hint' => (string) ($field['hint'] ?? ''),
                'required' => (bool) ($field['required'] ?? false),
                'autocomplete' => (string) ($field['autocomplete'] ?? ''),
                'rules' => $field['rules'] ?? null,
            ];
        }

        return $normalized;
    }

    protected function resolveSchemaComponents(array $config): array
    {
        $schema = $config['schema'] ?? null;

        if ($schema === null) {
            return $this->defaultSchemaComponents();
        }

        if (is_string($schema)) {
            if (! class_exists($schema)) {
                throw new \RuntimeException(
                    "Register schema class [{$schema}] was not found. ".
                    'Use full class name, e.g. App\\Svarium\\Schemas\\RegisterSchema::class.'
                );
            }

            $instance = app($schema);

            if (method_exists($instance, 'build')) {
                $schema = $instance->build($config);
            } elseif (method_exists($instance, 'schema')) {
                $schema = $instance->schema($config);
            } elseif (is_callable($instance)) {
                $schema = $instance($config);
            }
        } elseif (is_callable($schema)) {
            $schema = $schema($config);
        }

        if ($schema instanceof Component) {
            return [$schema];
        }

        if (! is_array($schema)) {
            throw new \RuntimeException(
                'Register schema must return Component or array<Component>. '.
                'Received: '.get_debug_type($schema)
            );
        }

        $components = [];

        foreach ($schema as $component) {
            if (! $component instanceof Component) {
                throw new \RuntimeException('Register schema array may contain only Svarium components.');
            }

            $components[] = $component;
        }

        return $components;
    }

    protected function defaultSchemaComponents(): array
    {
        return [
            Flex::make()
                ->direction('col')
                ->gap(4)
                ->children([
                    Block::make()->children([
                        FormInput::make('email')
                            ->label('Email')
                            ->required()
                            ->email()
                            ->prop('type', 'email'),
                    ]),
                    Block::make()->children([
                        FormInput::make('password')
                            ->label('Password')
                            ->required()
                            ->prop('type', 'password'),
                    ]),
                    Block::make()->children([
                        FormInput::make('company')
                            ->label('Company'),
                    ]),
                ]),
        ];
    }

    /**
     * @param  array<Component>  $components
     * @return array<string, FieldComponent>
     */
    protected function collectFieldComponents(array $components): array
    {
        $fields = [];

        $walk = function (array $nodes) use (&$walk, &$fields): void {
            foreach ($nodes as $node) {
                if (! $node instanceof Component) {
                    continue;
                }

                if ($node instanceof FieldComponent) {
                    $name = trim((string) $node->getName());
                    if ($name !== '') {
                        $fields[$name] = $node;
                    }
                }

                foreach ($this->readComponentChildren($node) as $child) {
                    $walk([$child]);
                }

                foreach ($this->readComponentSlots($node) as $slotChildren) {
                    $walk($slotChildren);
                }
            }
        };

        $walk($components);

        return $fields;
    }

    protected function readComponentChildren(Component $component): array
    {
        $children = $this->readComponentProperty($component, 'children');

        if (! is_array($children)) {
            return [];
        }

        return array_values(array_filter($children, fn ($item) => $item instanceof Component));
    }

    protected function readComponentSlots(Component $component): array
    {
        $slots = $this->readComponentProperty($component, 'slots');

        if (! is_array($slots)) {
            return [];
        }

        $result = [];

        foreach ($slots as $slotChildren) {
            if ($slotChildren instanceof Component) {
                $result[] = [$slotChildren];

                continue;
            }

            if (is_array($slotChildren)) {
                $result[] = array_values(array_filter($slotChildren, fn ($item) => $item instanceof Component));
            }
        }

        return $result;
    }

    protected function readComponentProperty(Component $component, string $property): mixed
    {
        $reader = function (string $prop) {
            return $this->{$prop} ?? null;
        };

        $bound = $reader->bindTo($component, $component);

        return $bound($property);
    }

    protected function assertBaseInputs(array $fields): void
    {
        $missing = [];

        foreach (['email', 'password'] as $requiredName) {
            if (! isset($fields[$requiredName])) {
                $missing[] = $requiredName;

                continue;
            }

            if (! $fields[$requiredName] instanceof FormInput) {
                $missing[] = "{$requiredName} (must be Form Input component)";
            }
        }

        if ($missing === []) {
            return;
        }

        throw new \RuntimeException(
            'Register schema must contain Input components with names: email and password. Missing: '.implode(', ', $missing)
        );
    }

    protected function serializeComponents(array $components): array
    {
        return array_values(array_map(
            static fn (Component $component) => $component->toArray(),
            $components
        ));
    }

    protected function validationRulesFromFields(array $fields, array $config): array
    {
        $rules = [];

        foreach ($fields as $name => $field) {
            $fieldRules = $field->getValidationRules();

            if ($name === 'email') {
                $rules['email'] = $this->mergeRules(
                    ['required', 'email', $this->emailUniqueRule()],
                    $fieldRules
                );

                continue;
            }

            if ($name === 'password') {
                $passwordRules = is_array($config['password_rules'] ?? null)
                    ? $config['password_rules']
                    : ['required', 'string', 'min:8'];

                $rules['password'] = $this->mergeRules($passwordRules, $fieldRules);

                continue;
            }

            if ($name === 'password_confirmation' && $fieldRules === []) {
                $rules['password_confirmation'] = ['same:password'];

                continue;
            }

            if ($fieldRules !== []) {
                $rules[$name] = $fieldRules;
            }
        }

        return $rules;
    }

    protected function mergeRules(array ...$sets): array
    {
        $merged = [];

        foreach ($sets as $rules) {
            foreach ($rules as $rule) {
                if (is_string($rule)) {
                    if (in_array($rule, $merged, true)) {
                        continue;
                    }
                }

                $merged[] = $rule;
            }
        }

        return $merged;
    }

    protected function emailUniqueRule(): Rule
    {
        $userClass = get_model('user');
        $user = app($userClass);
        $table = $user->getTable();
        $connection = $user->getConnectionName();

        $uniqueTable = $connection
            ? "{$connection}.{$table}"
            : $table;

        return Rule::unique($uniqueTable, 'email');
    }

    protected function createUser(array $validated, Request $request, array $config): Authenticatable
    {
        $creator = $config['creator'] ?? null;

        if ($creator !== null) {
            $resolved = $this->resolveCreator($creator);
            $user = $resolved($validated, $request, $config);

            if (! $user instanceof Authenticatable) {
                throw new \RuntimeException('Custom registration creator must return an authenticatable user.');
            }

            return $user;
        }

        $userClass = get_model('user');

        /** @var Model&Authenticatable $user */
        $user = app($userClass);

        $payload = $validated;
        unset($payload['password_confirmation']);

        $attributes = $this->extractPersistableAttributes($user, $payload);

        $user->forceFill($attributes);
        $user->save();

        $afterCreate = $config['after_create'] ?? null;
        if ($afterCreate !== null) {
            $hook = $this->resolveCreator($afterCreate);
            $hook($user, $request, $config, $validated);
        }

        return $user;
    }

    protected function extractPersistableAttributes(Model $model, array $payload): array
    {
        $table = $model->getTable();
        $connection = $model->getConnectionName();

        try {
            $columns = Schema::connection($connection)->getColumnListing($table);
            $columnsLookup = array_fill_keys($columns, true);
        } catch (Throwable) {
            return $payload;
        }

        $attributes = [];

        foreach ($payload as $key => $value) {
            if (isset($columnsLookup[$key])) {
                $attributes[$key] = $value;
            }
        }

        if (! array_key_exists('email', $attributes) && array_key_exists('email', $payload)) {
            $attributes['email'] = $payload['email'];
        }

        if (! array_key_exists('password', $attributes) && array_key_exists('password', $payload)) {
            $attributes['password'] = $payload['password'];
        }

        return $attributes;
    }

    protected function resolveCreator(mixed $creator): callable
    {
        if (is_string($creator) && class_exists($creator)) {
            $instance = app($creator);

            if (is_callable($instance)) {
                return $instance;
            }
        }

        if (is_callable($creator)) {
            return $creator;
        }

        throw new \RuntimeException('Invalid registration creator. Use callable or invokable class.');
    }

    protected function runRegisteredHooks(
        Authenticatable $user,
        Request $request,
        array $config,
        array $validated
    ): void {
        $events = $config['events'] ?? [];
        if (! is_array($events)) {
            $events = [];
        }

        if (($events['dispatch_registered'] ?? true) === true) {
            event(new Registered($user));
        }

        foreach ((array) ($events['dispatch'] ?? []) as $eventDefinition) {
            if (is_string($eventDefinition) && class_exists($eventDefinition)) {
                try {
                    event(new $eventDefinition($user, $request, $validated, $config));
                } catch (\ArgumentCountError) {
                    event(new $eventDefinition($user));
                }
            }
        }

        foreach ((array) ($events['listeners'] ?? []) as $listener) {
            try {
                $callback = $this->resolveCreator($listener);
                $callback($user, $request, $config, $validated);
            } catch (Throwable) {
                // Listener failures should not break registration flow.
            }
        }
    }

    protected function handleActivation(
        Authenticatable $user,
        Request $request,
        array $config,
        array $validated
    ): mixed {
        $activation = $config['activation'] ?? [];
        if (! is_array($activation)) {
            $activation = [];
        }

        $mode = strtolower(trim((string) ($activation['mode'] ?? 'none')));

        if ($mode === '' || $mode === 'none') {
            return null;
        }

        if (in_array($mode, ['code', 'email_code'], true)) {
            $userAuth = get_model('user_auth')::setToken($user, 'register');
            $userAuth->sendEmail('register');

            $route = (string) ($activation['verification_route'] ?? 'panel.auth.verification');
            $type = (string) ($activation['verification_type'] ?? 'register');

            return redirect()->route($route, [
                'type' => $type,
                'userAuth' => $userAuth->hash,
            ])->with([
                'success' => __('Verification code has been sent to your email address.'),
            ]);
        }

        if (in_array($mode, ['link', 'email_link'], true)) {
            if (method_exists($user, 'sendEmailVerificationNotification')) {
                $user->sendEmailVerificationNotification();
            }

            $route = (string) ($activation['link_sent_redirect_route'] ?? ($config['login_redirect_route'] ?? 'panel.auth.login'));

            return redirect()->route($route)
                ->with(['success' => __('Verification link has been sent to your email address.')]);
        }

        if ($mode === 'custom') {
            $handler = $activation['custom_handler'] ?? null;
            if ($handler === null) {
                return null;
            }

            $callback = $this->resolveCreator($handler);

            return $callback($user, $request, $config, $validated);
        }

        return null;
    }

    protected function resolvePanelRegistrationConfig(): array
    {
        $panel = $this->resolvePanel();

        if (! $panel instanceof Panel) {
            return [];
        }

        if (! method_exists($panel, 'getRegistrationConfig')) {
            return [];
        }

        $config = $panel->getRegistrationConfig();

        return is_array($config) ? $config : [];
    }

    protected function resolvePanel(): ?Panel
    {
        $registry = app(PanelRegistry::class);
        $panels = $registry->all();

        if ($panels === []) {
            return null;
        }

        $path = trim((string) request()->path(), '/');
        $segment = explode('/', $path)[0] ?? null;

        foreach ($panels as $panel) {
            if (! $panel instanceof Panel) {
                continue;
            }

            if ($panel->prefix !== null && trim($panel->prefix, '/') === (string) $segment) {
                return $panel;
            }
        }

        $noPrefixPanels = array_values(array_filter($panels, fn ($panel) => $panel instanceof Panel && $panel->prefix === null));
        if (count($noPrefixPanels) === 1) {
            return $noPrefixPanels[0];
        }

        $configuredName = trim((string) config('upsoftware.panel.name', ''));
        if ($configuredName !== '') {
            $configuredPanel = $registry->get($configuredName);
            if ($configuredPanel instanceof Panel) {
                return $configuredPanel;
            }
        }

        $first = reset($panels);

        return $first instanceof Panel ? $first : null;
    }

    protected function redirectTarget(array $config): string
    {
        $routeName = $config['redirect_route'] ?? null;
        if (is_string($routeName) && $routeName !== '') {
            try {
                return route($routeName);
            } catch (Throwable) {
                // fallback below
            }
        }

        $target = $config['redirect_to'] ?? '/';
        if (! is_string($target) || trim($target) === '') {
            return '/';
        }

        return $target;
    }

    protected function panelHomeUrl(): string
    {
        $panel = $this->resolvePanel();

        if (! $panel instanceof Panel) {
            return '/';
        }

        $prefix = trim((string) $panel->prefix, '/');

        if ($prefix === '') {
            return '/';
        }

        return '/'.$prefix;
    }

    protected function buildRegisterTree(array $pageProps): array
    {
        $rootLayoutRaw = (string) ($pageProps['layout'] ?? 'CleanLayout');
        $layoutEnabled = (bool) ($pageProps['layout_enabled'] ?? $pageProps['layoutEnabled'] ?? true);
        $skipMainLayout = (bool) ($pageProps['skip_main_layout'] ?? $pageProps['skipMainLayout'] ?? false);
        if ($skipMainLayout) {
            $layoutEnabled = false;
        }
        $rootSlot = trim((string) ($pageProps['layoutSlot'] ?? 'body'));
        $wrapperLayoutRaw = trim((string) ($pageProps['wrapperLayout'] ?? ''));
        $wrapperSlot = trim((string) ($pageProps['wrapperSlot'] ?? 'default'));

        $registerNode = $this->buildRegisterNode($pageProps);

        if (! $layoutEnabled) {
            $nodes = [$registerNode];

            if (trim($rootLayoutRaw) !== '') {
                $mainLayoutNode = $this->makeLayoutNode($rootLayoutRaw, $pageProps);
                $this->injectRegisterNodeIntoLayoutSlot($mainLayoutNode, $rootSlot, $registerNode);
                $extractedNodes = $this->extractLayoutTargetNodes($mainLayoutNode, $rootSlot);

                if ($extractedNodes !== []) {
                    $nodes = $extractedNodes;
                }
            }

            if ($wrapperLayoutRaw !== '') {
                $wrapperNode = $this->makeLayoutNode($wrapperLayoutRaw, $pageProps);
                $this->attachNodesToWrapper($wrapperNode, $nodes, $wrapperSlot);
                $nodes = [$wrapperNode];
            }

            return $this->applyWrapDefinitions($nodes, $pageProps['wrap'] ?? null);
        }

        $rootNode = $this->makeLayoutNode($rootLayoutRaw, $pageProps);

        if ($wrapperLayoutRaw === '' && $this->layoutTargetHasContent($rootNode, $rootSlot)) {
            $this->injectRegisterNodeIntoLayoutSlot($rootNode, $rootSlot, $registerNode);
            return $this->applyWrapDefinitions([$rootNode], $pageProps['wrap'] ?? null);
        }

        if ($wrapperLayoutRaw !== '') {
            $wrapperNode = $this->makeLayoutNode($wrapperLayoutRaw, $pageProps);
            $this->attachNodeToLayout($wrapperNode, $registerNode, $wrapperSlot);
            $this->attachNodeToLayout($rootNode, $wrapperNode, $rootSlot);
        } else {
            $this->attachNodeToLayout($rootNode, $registerNode, $rootSlot);
        }

        return $this->applyWrapDefinitions([$rootNode], $pageProps['wrap'] ?? null);
    }

    protected function buildRegisterNode(array $pageProps): array
    {
        $component = trim((string) ($pageProps['component'] ?? ''));

        if ($component === '' || strtolower($component) === 'auto') {
            return $this->buildRegisterFormNode($pageProps);
        }

        return [
            'type' => $component,
            'props' => $this->buildRegisterNodeProps($component, $pageProps),
            'children' => [],
            'slots' => [],
        ];
    }

    protected function layoutTargetHasContent(array $layoutNode, string $slot): bool
    {
        $normalized = strtolower(trim($slot));

        if ($normalized === '' || $normalized === 'default') {
            return count(array_filter((array) ($layoutNode['children'] ?? []), static fn ($node) => is_array($node))) > 0;
        }

        $slots = $layoutNode['slots'] ?? [];
        if (! is_array($slots)) {
            return false;
        }

        $slotChildren = $slots[$slot] ?? null;

        return is_array($slotChildren) && count(array_filter($slotChildren, static fn ($node) => is_array($node))) > 0;
    }

    protected function buildRegisterFormNode(array $pageProps): array
    {
        $schema = $pageProps['schema'] ?? [];
        if (! is_array($schema)) {
            $schema = [];
        }

        $submitLabel = (string) ($pageProps['submitLabel'] ?? __('Create account'));

        return [
            'type' => 'Form',
            'props' => [
                'action' => $pageProps['action'] ?? 'panel.auth.register.set',
            ],
            'children' => [
                ...$schema,
                [
                    'type' => 'ButtonSubmit',
                    'props' => [
                        'label' => $submitLabel,
                        'class' => 'w-full',
                    ],
                    'children' => [],
                    'slots' => [],
                ],
            ],
            'slots' => [],
        ];
    }

    protected function buildRegisterNodeProps(string $component, array $pageProps): array
    {
        if ($component === 'BlockFormRegister') {
            return [
                'action' => $pageProps['action'] ?? 'panel.auth.register.set',
                'submitLabel' => $pageProps['submitLabel'] ?? __('Create account'),
                'schema' => $pageProps['schema'] ?? [],
                'fields' => $pageProps['fields'] ?? [],
            ];
        }

        return $pageProps;
    }

    protected function resolveLayoutComponentName(string $layout): string
    {
        $normalized = trim($layout);

        if ($normalized === '') {
            return 'CleanLayout';
        }

        if (str_contains($normalized, '\\')) {
            return class_basename($normalized);
        }

        return $normalized;
    }

    protected function makeLayoutNode(string $layout, array $pageProps = []): array
    {
        $componentName = $this->resolveLayoutComponentName($layout);
        $layoutProps = $this->extractLayoutPropsFromPageProps($pageProps);

        if ($layout !== '' && str_contains($layout, '\\') && class_exists($layout)) {
            $instance = app($layout);

            if ($instance instanceof Component) {
                foreach ($layoutProps as $key => $value) {
                    $instance->prop($key, $value);
                }

                $node = $instance->toArray();
                $node['type'] = $node['type'] ?? $componentName;
                $node['props'] = is_array($node['props'] ?? null)
                    ? [...$node['props'], ...$layoutProps]
                    : $layoutProps;
                $node['children'] = is_array($node['children'] ?? null) ? $node['children'] : [];
                $node['slots'] = is_array($node['slots'] ?? null) ? $node['slots'] : [];

                return $node;
            }
        }

        return [
            'type' => $componentName,
            'props' => $layoutProps,
            'children' => [],
            'slots' => [],
        ];
    }

    protected function extractLayoutPropsFromPageProps(array $pageProps): array
    {
        $result = [];

        if (isset($pageProps['title']) && is_string($pageProps['title']) && trim($pageProps['title']) !== '') {
            $result['title'] = trim($pageProps['title']);
        }

        if (isset($pageProps['subtitle']) && is_string($pageProps['subtitle']) && trim($pageProps['subtitle']) !== '') {
            $result['subtitle'] = trim($pageProps['subtitle']);
        }

        return $result;
    }

    protected function attachNodeToLayout(array &$layoutNode, array $childNode, string $slot): void
    {
        $normalized = trim($slot);

        if ($normalized === '' || strtolower($normalized) === 'default') {
            $children = is_array($layoutNode['children'] ?? null) ? $layoutNode['children'] : [];
            $children[] = $childNode;
            $layoutNode['children'] = $children;

            return;
        }

        $slots = is_array($layoutNode['slots'] ?? null) ? $layoutNode['slots'] : [];
        $slotChildren = is_array($slots[$normalized] ?? null) ? $slots[$normalized] : [];
        $slotChildren[] = $childNode;
        $slots[$normalized] = $slotChildren;
        $layoutNode['slots'] = $slots;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function extractLayoutTargetNodes(array $layoutNode, string $slot): array
    {
        $normalized = strtolower(trim($slot));

        if ($normalized === '' || $normalized === 'default') {
            $children = $layoutNode['children'] ?? [];

            if (! is_array($children)) {
                return [];
            }

            return array_values(array_filter($children, static fn ($node) => is_array($node)));
        }

        $slots = $layoutNode['slots'] ?? [];

        if (! is_array($slots)) {
            return [];
        }

        $slotChildren = $slots[$slot] ?? [];

        if (! is_array($slotChildren)) {
            return [];
        }

        return array_values(array_filter($slotChildren, static fn ($node) => is_array($node)));
    }

    protected function injectRegisterNodeIntoLayoutSlot(array &$layoutNode, string $slot, array $registerNode): bool
    {
        $normalized = strtolower(trim($slot));

        if ($normalized === '' || $normalized === 'default') {
            $children = $layoutNode['children'] ?? [];

            if (! is_array($children)) {
                return false;
            }

            $injected = $this->injectRegisterNodeIntoNodeList($children, $registerNode);
            $layoutNode['children'] = $children;

            return $injected;
        }

        $slots = $layoutNode['slots'] ?? [];

        if (! is_array($slots)) {
            return false;
        }

        $slotChildren = $slots[$slot] ?? [];

        if (! is_array($slotChildren)) {
            return false;
        }

        $injected = $this->injectRegisterNodeIntoNodeList($slotChildren, $registerNode);
        $slots[$slot] = $slotChildren;
        $layoutNode['slots'] = $slots;

        return $injected;
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     */
    protected function injectRegisterNodeIntoNodeList(array &$nodes, array $registerNode): bool
    {
        foreach ($nodes as &$node) {
            if (! is_array($node)) {
                continue;
            }

            $type = strtolower(trim((string) ($node['type'] ?? '')));
            if ($type === 'body') {
                $node = $registerNode;
                return true;
            }

            if ($this->injectRegisterNodeIntoNode($node, $registerNode)) {
                return true;
            }
        }

        return false;
    }

    protected function injectRegisterNodeIntoNode(array &$node, array $registerNode): bool
    {
        $children = $node['children'] ?? null;
        if (is_array($children) && $this->injectRegisterNodeIntoNodeList($children, $registerNode)) {
            $node['children'] = $children;
            return true;
        }

        $slots = $node['slots'] ?? null;

        if (! is_array($slots)) {
            return false;
        }

        foreach ($slots as $slotName => $slotChildren) {
            if (! is_array($slotChildren)) {
                continue;
            }

            if ($this->injectRegisterNodeIntoNodeList($slotChildren, $registerNode)) {
                $slots[$slotName] = $slotChildren;
                $node['slots'] = $slots;
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     * @return array<int, array<string, mixed>>
     */
    protected function applyWrapDefinitions(array $nodes, mixed $definitions): array
    {
        $wraps = $this->normalizeWrapDefinitions($definitions);

        if ($wraps === []) {
            return $nodes;
        }

        $current = $nodes;

        foreach (array_reverse($wraps) as $definition) {
            $wrapperNode = $this->makeWrapperNode($definition);

            if ($wrapperNode === null) {
                continue;
            }

            $slot = $this->extractWrapSlot($definition);
            $this->attachNodesToWrapper($wrapperNode, $current, $slot);
            $current = [$wrapperNode];
        }

        return $current;
    }

    /**
     * @return array<int, mixed>
     */
    protected function normalizeWrapDefinitions(mixed $definitions): array
    {
        if ($definitions === null) {
            return [];
        }

        if (is_array($definitions)) {
            if ($this->isAssocArray($definitions)) {
                return [$definitions];
            }

            return array_values($definitions);
        }

        return [$definitions];
    }

    protected function makeWrapperNode(mixed $definition): ?array
    {
        if (is_string($definition)) {
            return $this->makeLayoutNode($definition);
        }

        if ($definition instanceof Component) {
            return $definition->toArray();
        }

        if (is_object($definition) && method_exists($definition, 'toArray')) {
            $array = $definition->toArray();
            return is_array($array) ? $array : null;
        }

        if (! is_array($definition)) {
            return null;
        }

        $node = $definition;

        if (isset($node['component']) && ! isset($node['type'])) {
            $node['type'] = $node['component'];
        }

        if (! isset($node['type']) || ! is_string($node['type']) || trim($node['type']) === '') {
            return null;
        }

        unset($node['slot']);

        $node['props'] = is_array($node['props'] ?? null) ? $node['props'] : [];
        $node['children'] = is_array($node['children'] ?? null) ? $node['children'] : [];
        $node['slots'] = is_array($node['slots'] ?? null) ? $node['slots'] : [];

        return $node;
    }

    protected function extractWrapSlot(mixed $definition): string
    {
        if (is_array($definition) && isset($definition['slot']) && is_string($definition['slot'])) {
            $slot = trim($definition['slot']);
            return $slot === '' ? 'default' : $slot;
        }

        return 'default';
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     */
    protected function attachNodesToWrapper(array &$wrapperNode, array $nodes, string $slot): void
    {
        $normalized = strtolower(trim($slot));

        if ($normalized === '' || $normalized === 'default') {
            $children = is_array($wrapperNode['children'] ?? null) ? $wrapperNode['children'] : [];
            $wrapperNode['children'] = [...$children, ...$nodes];

            return;
        }

        $slots = is_array($wrapperNode['slots'] ?? null) ? $wrapperNode['slots'] : [];
        $slotChildren = is_array($slots[$slot] ?? null) ? $slots[$slot] : [];
        $slots[$slot] = [...$slotChildren, ...$nodes];
        $wrapperNode['slots'] = $slots;
    }

    protected function isAssocArray(array $array): bool
    {
        if ($array === []) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }

    protected function safe(callable $callback, mixed $fallback = null): mixed
    {
        try {
            return $callback();
        } catch (Throwable) {
            return $fallback;
        }
    }
}
