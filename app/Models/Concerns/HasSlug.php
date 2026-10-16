<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Fills the "slug" column from "name" when it is left empty, keeping it unique.
 */
trait HasSlug
{
    protected static function bootHasSlug(): void
    {
        static::saving(function (self $model) {
            if (blank($model->slug)) {
                $model->slug = $model->uniqueSlug(Str::slug($model->name) ?: 'item');
            }
        });
    }

    protected function uniqueSlug(string $base): string
    {
        $slug = $base;
        $suffix = 2;

        while (static::query()->where('slug', $slug)->whereKeyNot($this->getKey())->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
