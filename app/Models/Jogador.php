<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Jogador extends Model
{
    use HasFactory;
    protected $fillable = [
        'wa_id',
        'name',
        'microtime',
        'duracao',
        'tentativas',
        'num_sorteado',
        'finalizado'
    ];
}
