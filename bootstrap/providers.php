<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use Opcodes\LogViewer\LogViewerServiceProvider;
use StudentHub\EvaluationBuilder\EvaluationBuilderServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    LogViewerServiceProvider::class,
    EvaluationBuilderServiceProvider::class,
];
