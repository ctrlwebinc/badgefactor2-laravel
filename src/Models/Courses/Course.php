<?php

namespace Ctrlweb\BadgeFactor2\Models\Courses;

use Ctrlweb\BadgeFactor2\Models\Badges\BadgePage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Course extends Model
{
    use HasFactory;
    use HasTranslations;

    protected $translatable = [
        'title',
        'description',
    ];

    protected $fillable = [
        'title',
        'description',
        'type',
        'duration',
        'url',
        'autoevaluation_form_url',
        'badge_page_id',
        'course_category_id',
        'course_group_id',
        'regular_price',
    ];

    protected $with = ['badgePage'];

    public function generateCourseLink()
    {
        $url = $this->url;
    }

    public function courseCategory()
    {
        return $this->belongsTo(CourseCategory::class);
    }

    public function courseGroup()
    {
        return $this->belongsTo(CourseGroup::class);
    }

    public function badgePage()
    {
        return $this->belongsTo(BadgePage::class);
    }

    public function targetAudiences()
    {
        return $this->belongsToMany(TargetAudience::class);
    }

    public function technicalRequirements()
    {
        return $this->belongsToMany(TechnicalRequirement::class);
    }

    public function price(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->regular_price
        );
    }

    public function carts(): BelongsToMany
    {
        return $this->belongsToMany(Cart::class, 'cart_product');
    }
}
