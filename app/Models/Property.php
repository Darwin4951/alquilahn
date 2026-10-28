<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Property extends Model
{
    public const TYPES = ['casa' => 'Casa completa', 'apartamento' => 'Apartamento', 'cuarto' => 'Cuarto / Habitación'];
    protected $fillable = ['title', 'type', 'price', 'department', 'city', 'colonia', 'address', 'description', 'whatsapp', 'is_available', 'images'];
    protected function casts(): array { return ['price' => 'decimal:2', 'images' => 'array', 'is_available' => 'boolean']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function whatsappUrl(): string
    {
        return 'https://wa.me/504'.$this->whatsapp.'?text='.rawurlencode('Hola, me interesa el anuncio "'.$this->title.'" en '.$this->colonia.', '.$this->city.'. ¿Sigue disponible? '.route('properties.show', $this));
    }
}
