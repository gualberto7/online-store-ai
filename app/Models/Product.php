<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsVector;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property list<float>|null $search_embedding
 * @property string|null $search_embedding_profile
 * @property string|null $search_content_hash
 * @property int $search_version
 * @property int $id
 * @property int|null $category_id
 * @property-read Category|null $category
 * @property-read Collection<int, ProductImage> $images
 * @property int $team_id
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 * @property-read Team $team
 * @property-read Collection<int, ProductVariant> $variants
 */
#[Fillable(['name', 'description', 'is_active', 'category_id'])]
class Product extends Model
{
    protected $hidden = ['search_embedding', 'search_embedding_profile', 'search_content_hash', 'search_indexed_at', 'search_version'];

    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return HasMany<ProductImage, $this> */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('id');
    }

    /** @return HasMany<ProductVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /** @param Builder<Product> $query */
    #[Scope]
    protected function publiclyVisible(Builder $query): void
    {
        $query->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('category_id')->orWhereHas('category', fn ($query) => $query->where('is_active', true)))
            ->whereHas('variants', fn ($query) => $query->where('is_active', true));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'search_embedding' => AsVector::class, 'search_version' => 'integer', 'search_indexed_at' => 'immutable_datetime'];
    }
}
