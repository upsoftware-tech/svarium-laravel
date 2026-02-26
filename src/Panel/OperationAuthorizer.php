<?php

namespace Upsoftware\Svarium\Panel;

class OperationAuthorizer
{
    public function authorize(Operation $operation, PanelContext $context): void
    {
        if (!$operation->authorize($context)) {
            throw new AuthorizationException();
        }
    }
}
