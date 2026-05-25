<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuthorRequest extends Model
{
    protected $fillable = [
        'user_id',
        'full_name',
        'email',
        'short_bio',
        'reason',
        'experience',
        'id_card_path',
        'portfolio_path',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
