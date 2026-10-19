<?php

namespace App\Modules\Catalog;

use App\Modules\Catalog\Search\CourseSearchEngine;
use App\Modules\Catalog\Search\DatabaseCourseSearch;
use App\Support\Modules\ModuleServiceProvider;

class CatalogServiceProvider extends ModuleServiceProvider
{
    protected function modulePath(): string
    {
        return __DIR__;
    }

    public function register(): void
    {
        $this->app->bind(CourseSearchEngine::class, DatabaseCourseSearch::class);
    }
}
