<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Testimonial extends Model { protected $fillable = ['name','month','quote','avatar_url','is_demo','active']; protected $casts = ['is_demo'=>'boolean','active'=>'boolean']; }

