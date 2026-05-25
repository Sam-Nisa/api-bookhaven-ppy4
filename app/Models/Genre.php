<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Genre extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'parent_id',
        'image',
    ];

    protected $casts = [
        'parent_id' => 'integer',
    ];

    // Parent relationship
    public function parent()
    {
        return $this->belongsTo(Genre::class, 'parent_id');
    }

    // Children/Subgenres relationship (both names for compatibility)
    public function children()
    {
        return $this->hasMany(Genre::class, 'parent_id');
    }

    public function subgenres()
    {
        return $this->hasMany(Genre::class, 'parent_id');
    }

    // Books relationship (if you have a books table)
    public function books()
    {
        return $this->hasMany(\App\Models\Book::class);
    }
}