<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Property extends Model
{
    public const TYPES = ['casa' => 'Casa completa', 'apartamento' => 'Apartamento', 'cuarto' => 'Cuarto / Habitación', 'local' => 'Local comercial', 'terreno' => 'Terreno', 'vehiculo' => 'Vehículo'];
    public const OPERATIONS = ['alquiler' => 'Alquiler', 'venta' => 'Venta'];
    public const VEHICLE_FIELDS = ['vehicle_type', 'brand', 'vehicle_model', 'year', 'mileage', 'transmission', 'fuel', 'vehicle_condition'];
    public const EXTRA_FIELDS = ['operation', 'area', 'area_unit', 'is_sold', 'vehicle_type', 'brand', 'vehicle_model', 'year', 'mileage', 'transmission', 'fuel', 'vehicle_condition'];
    protected $attributes = ['operation' => 'alquiler', 'is_sold' => false];
    protected $fillable = ['operation', 'area', 'area_unit', 'is_sold', 'vehicle_type', 'brand', 'vehicle_model', 'year', 'mileage', 'transmission', 'fuel', 'vehicle_condition', 'title', 'type', 'price', 'department', 'city', 'colonia', 'address', 'description', 'whatsapp', 'is_available', 'images'];
    protected function casts(): array { return ['is_sold' => 'boolean', 'area' => 'decimal:2', 'price' => 'decimal:2', 'images' => 'array', 'is_available' => 'boolean']; }
    public function isSale(): bool { return $this->operation === 'venta'; }
    public function statusLabel(): string { return $this->is_sold ? 'Vendido' : ($this->is_available ? 'Disponible' : ($this->isSale() ? 'No disponible' : 'No disponible / Alquilado')); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function whatsappUrl(): string
    {
        return 'https://wa.me/504'.$this->whatsapp.'?text='.rawurlencode('Hola, me interesa el anuncio "'.$this->title.'" en '.$this->colonia.', '.$this->city.'. ¿Sigue disponible? '.route('properties.show', $this));
    }
}
