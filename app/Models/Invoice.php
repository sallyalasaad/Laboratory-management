<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class Invoice extends Model
{

    use HasFactory;

    protected $fillable = ['sale_id','user_id','total_amount','invoice_date'];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }
}
