<?php

use App\Contracts\Tool;

arch('contracts are interfaces')
    ->expect('App\Contracts')
    ->toBeInterfaces();

arch('enums are enums')
    ->expect('App\Enums')
    ->toBeEnums();

arch('every tool implements the Tool contract')
    ->expect('App\Ai\Tools')
    ->toImplement(Tool::class);

arch('models never reach into the AI or agent layers')
    ->expect('App\Models')
    ->not->toUse(['App\Ai', 'App\Agents']);

arch('no debugging leftovers')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();