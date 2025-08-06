<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class Announce extends Model
{
    //

    use HasFactory;

    protected $fillable = [
        'title',
        'category',
        'link',
        'imgUrl',
    ];
}
