<?php

namespace Filament\Resources\Pages\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Illuminate\Support\HtmlString;

trait InteractsWithRecord
{
    public $record;

    protected function resolveRecord($key): Model
    {
        $record = static::getResource()::resolveRecordRouteBinding($key);

        if ($record === null) {
            throw (new ModelNotFoundException())->setModel($this->getModel(), [$key]);
        }

        return $record;
    }

    public function getRecord(): Model
    {
        return $this->record;
    }

    public function getRecordTitle(): string | HtmlString
    {
        $resource = static::getResource();

        if (! $resource::hasRecordTitle()) {
            return Str::headline($resource::getModelLabel());
        }

        return $resource::getRecordTitle($this->getRecord());
    }
}
