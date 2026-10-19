<?php

declare(strict_types=1);

arch('application code uses strict types', function (): void {
    expect('App')->toUseStrictTypes();
});

arch('debugging helpers are not left in the code', function (): void {
    expect(['dd', 'dump', 'ddd', 'ray', 'var_dump'])->not->toBeUsed();
});
