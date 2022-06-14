<?php


namespace App\Macros;

use Illuminate\Database\Query\Builder;

class SearchMacros
{
    public function one(){
        Builder::macro('multipleWhere', function ($attributes, $needle) {
            return $this->where(function (Builder $query) use ($attributes,$needle) {
                foreach (array_wrap($attributes) as $attribute) {
                    $query->where($attribute, 'LIKE', "%{$needle}%");
                }
            });
        });
    }
}
