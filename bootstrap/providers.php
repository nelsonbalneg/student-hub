<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use Opcodes\LogViewer\LogViewerServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    LogViewerServiceProvider::class,
];
