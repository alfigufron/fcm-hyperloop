<?php


namespace App\Utils;


use Illuminate\Support\Collection;

class Sort
{
    public static function sortbymulti(){
        /**
         * Sort By Multi Macro
         * @source https://www.jjanusch.com/2017/05/laravel-collection-macros-adding-a-sortbymuti-function
         */
        Collection::macro('sortByMulti', function (array $keys) {
            $currentIndex = 0;
            $keys = array_map(
                function ($key, $sort) {
                    return ['key' => $key, 'sort' => $sort];
                },
                array_keys($keys),
                $keys
            );
            $sortBy = function (\Illuminate\Support\Collection $collection) use (&$currentIndex,$keys,&$sortBy) {
                if ($currentIndex >= count($keys)) {
                    return $collection;
                }
                $key = $keys[$currentIndex]['key'];
                $sort = $keys[$currentIndex]['sort'];
                $sortFunc = $sort === 'DESC' ? 'sortByDesc' : 'sortBy';
                $currentIndex++;
                return $collection
                    ->$sortFunc($key)
                    ->groupBy($key)
                    ->map($sortBy)
                    ->ungroup();
            };
            return $sortBy($this);
        });
    }

    public static function ungroup(){
        /**
         * Ungroup Previously Grouped Collection
         */
        Collection::macro('ungroup', function () {
            $newCollection = \Illuminate\Support\Collection::make([]);
            /** @var \Illuminate\Support\Collection $this */
            $this->each(function ($item) use (&$newCollection) {
                $newCollection = $newCollection->merge($item);
            });
            return $newCollection;
        });
    }
}
