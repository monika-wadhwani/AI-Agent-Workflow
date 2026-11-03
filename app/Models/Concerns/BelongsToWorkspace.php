<?php

namespace App\Models\Concerns;

use App\Models\Workspace;
use App\Support\CurrentWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

trait BelongsToWorkspace
{
    public static function bootBelongsToWorkspace(): void
    {
        static::addGlobalScope('workspace', function (Builder $builder) {
            $id = app(CurrentWorkspace::class)->id();

            if ($id === null) {
                $builder->whereRaw('1 = 0');

                return;
            }

            $builder->where($builder->getModel()->getTable().'.workspace_id', $id);
        });

        static::creating(function (Model $model) {
            if ($model->workspace_id !== null) {
                return;
            }

            $id = app(CurrentWorkspace::class)->id();

            if ($id === null) {
                throw new LogicException('No current workspace is set; cannot create '.class_basename($model).'.');
            }

            $model->workspace_id = $id;
        });
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}