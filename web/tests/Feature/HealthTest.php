<?php

declare(strict_types=1);

use function Pest\Laravel\get;

it('answers the health route', function (): void {
    get('/up')->assertOk();
});
