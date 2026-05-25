<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'book_id',
        'change',
        'reason',
    ];

    public function book()
    {
        return $this->belongsTo(Book::class);
    }
}
