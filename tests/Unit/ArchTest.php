<?php

declare(strict_types=1);

arch('every source file declares strict types')
    ->expect('Beziworld\Cpanel')
    ->toUseStrictTypes();

arch('no debugging statements are left behind')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r', 'die'])
    ->not->toBeUsed();

arch('data objects are immutable')
    ->expect('Beziworld\Cpanel\Data')
    ->toBeReadonly();

arch('exceptions share the package base exception')
    ->expect('Beziworld\Cpanel\Exceptions')
    ->toExtend('Beziworld\Cpanel\Exceptions\CpanelException')
    ->ignoring('Beziworld\Cpanel\Exceptions\CpanelException');
