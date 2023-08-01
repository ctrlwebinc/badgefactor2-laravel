<?php

namespace Ctrlweb\BadgeFactor2\Models\Courses;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Responsible extends Model
{
    use HasFactory;
    use HasTranslations;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'image',
    ];

    protected $translatable = [
        'slug',
        'description',
    ];

    public function courseGroups()
    {
        return $this->belongsToMany(CourseGroup::class);
    }
}
