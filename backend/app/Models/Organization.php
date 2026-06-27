<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
    ];

    /**
     * Auto-generate slug from name when creating/updating.
     */
    protected static function booted(): void
    {
        static::creating(function (Organization $organization) {
            if (empty($organization->slug)) {
                $organization->slug = Str::slug($organization->name);
            }
        });

        static::updating(function (Organization $organization) {
            if ($organization->isDirty('name') && ! $organization->isDirty('slug')) {
                $organization->slug = Str::slug($organization->name);
            }
        });
    }

    /**
     * All users belonging to this organization (admins, agents, customers).
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
