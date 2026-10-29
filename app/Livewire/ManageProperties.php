<?php
namespace App\Livewire;
use App\Models\Property;
use App\Support\Honduras;
use App\Services\PropertyImages;
use App\Exceptions\ImageStorageNotConfigured;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
class ManageProperties extends Component
{
    use WithFileUploads, WithPagination, AuthorizesRequests;
    #[Locked] public ?int $editingId = null;
    #[Locked] public array $existingImages = [];
    public string $title = '';
    public string $type = 'apartamento';
    public string $operation = 'alquiler';
    public bool $is_sold = false;
    public string $area = '';
    public string $area_unit = 'm2';
    public string $vehicle_type = '';
    public string $brand = '';
    public string $vehicle_model = '';
    public string $year = '';
    public string $mileage = '';
    public string $transmission = '';
    public string $fuel = '';
    public string $vehicle_condition = '';
    public string $price = '';
    public string $department = '';
    public string $city = '';
    public string $colonia = '';
    public string $address = '';
    public string $description = '';
    public string $whatsapp = '';
    public bool $is_available = true;
    public array $photos = [];
    public array $pendingPhotos = [];
    public function updatedPendingPhotos(): void
    {
        $this->resetValidation(['photos', 'pendingPhotos']);
        try {
            $this->validate([
                'pendingPhotos' => ['array', 'max:8'],
                'pendingPhotos.*' => $this->rules()['photos.*'],
            ]);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->pendingPhotos = [];
            $this->dispatch('photos-processed', failed: true);
            throw $exception;
        }
        if (count($this->existingImages) + count($this->photos) + count($this->pendingPhotos) > 8) {
            $this->pendingPhotos = [];
            $this->dispatch('photos-processed', failed: true);
            $this->addError('photos', 'Puedes agregar hasta 8 fotos en total. Quita alguna antes de agregar más.');
            return;
        }
        $this->photos = array_merge($this->photos, $this->pendingPhotos);
        $this->pendingPhotos = [];
        $this->dispatch('photos-processed', failed: false);
    }
    public function removePhoto(int $index): void
    {
        unset($this->photos[$index]);
        $this->photos = array_values($this->photos);
        $this->resetValidation('photos');
    }
    public function mount(): void { abort_unless(Auth::check(), 403); }
    public function updatedDepartment(): void { $this->city = ''; $this->resetValidation('city'); }
    protected function rules(): array
    {
        $vehicle = $this->type === 'vehiculo';
        $land = $this->type === 'terreno';
        $extra = ['operation' => ['required', Rule::in(['alquiler', 'venta']), Rule::notIn(($vehicle || $land) ? ['alquiler'] : [])], 'is_sold' => ['boolean'],
            'area' => [$land ? 'required' : 'exclude', 'numeric', 'min:0.01', 'max:999999999.99'],
            'area_unit' => [$land ? 'required' : 'exclude', Rule::in(['m2', 'v2'])]];
        foreach (Property::VEHICLE_FIELDS as $field) { $extra[$field] = [$vehicle ? 'required' : 'exclude', 'string', 'max:100']; }
        $extra['year'] = [$vehicle ? 'required' : 'exclude', 'integer', 'min:1886', 'max:'.(date('Y') + 1)];
        $extra['mileage'] = [$vehicle ? 'required' : 'exclude', 'integer', 'min:0', 'max:999999999'];
        foreach (['transmission' => ['manual', 'automatica'], 'fuel' => ['gasolina', 'diesel', 'hibrido', 'electrico', 'otro'], 'vehicle_condition' => ['nuevo', 'usado']] as $field => $options) { $extra[$field][] = Rule::in($options); }
        return array_merge($extra, [
            'title' => ['required', 'string', 'min:8', 'max:255'], 'type' => ['required', Rule::in(array_keys(Property::TYPES))],
            'price' => ['required', 'numeric', 'min:1', 'max:99999999.99', 'decimal:0,2'],
            'department' => ['required', Rule::in(Honduras::departments())],
            'city' => ['required', Rule::in(Honduras::cities($this->department))], 'colonia' => [$vehicle ? 'exclude' : 'required', 'string', 'max:255'],
            'address' => [$vehicle ? 'exclude' : 'required', 'string', 'min:10', 'max:2000'], 'description' => ['required', 'string', 'min:20', 'max:5000'],
            'whatsapp' => ['required', 'regex:/^[2389][0-9]{7}$/'], 'is_available' => ['boolean'],
            'photos' => ['array', 'max:8'], 'photos.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=12000,max_height=12000'],
        ]);
    }
    public function updatedType(): void { if (in_array($this->type, ['terreno', 'vehiculo'], true)) { $this->operation = 'venta'; } $this->resetValidation(); }
    public function edit(int $id): void
    {
        $property = Auth::user()->properties()->findOrFail($id);
        $this->authorize('update', $property);
        $this->resetForm();
        $this->editingId = $property->id;
        foreach (['title', 'type', 'price', 'city', 'colonia', 'address', 'description', 'whatsapp', 'is_available'] as $field) { $this->$field = $property->$field; }
        foreach (Property::EXTRA_FIELDS as $field) { $this->$field = $field === 'is_sold' ? (bool) $property->$field : (string) ($property->$field ?? ($field === 'area_unit' ? 'm2' : '')); }
        $this->department = $property->department ?? Honduras::departmentFor($property->city) ?? '';
        $this->existingImages = $property->images;
        $this->dispatch('edit-property');
    }
    public function removeImage(int $index): void
    {
        if (! $this->editingId) { return; }
        $this->authorize('update', Auth::user()->properties()->findOrFail($this->editingId));
        unset($this->existingImages[$index]);
        $this->existingImages = array_values($this->existingImages);
    }
    public function save(PropertyImages $images): void
    {
        abort_unless(Auth::check(), 403);
        $data = $this->validate();
        foreach (Property::VEHICLE_FIELDS as $field) { if ($this->type !== 'vehiculo') { $data[$field] = null; } }
        if ($this->type !== 'terreno') { $data['area'] = $data['area_unit'] = null; }
        if ($this->type === 'vehiculo') { $data['colonia'] = $data['address'] = ''; }
        $data['is_sold'] = $this->operation === 'venta' && $this->is_sold;
        if ($data['is_sold']) { $data['is_available'] = false; }
        if (count($this->existingImages) + count($this->photos) < 1 || count($this->existingImages) + count($this->photos) > 8) {
            $this->addError('photos', 'El anuncio debe tener entre 1 y 8 fotos en total.'); return;
        }
        $property = $this->editingId ? Auth::user()->properties()->findOrFail($this->editingId) : null;
        if ($property) { $this->authorize('update', $property); }
        $oldImages = $property?->images ?? [];
        $uploaded = [];
        try {
            foreach ($this->photos as $photo) { $uploaded[] = $images->upload($photo); }
            unset($data['photos']);
            $data['images'] = array_merge($this->existingImages, $uploaded);
            DB::transaction(function () use ($property, $data) {
                if ($property) { $property->update($data); }
                else { Auth::user()->properties()->create($data); }
            });
        } catch (\Throwable $exception) {
            $images->cleanup($uploaded);
            report($exception);
            if ($exception instanceof ImageStorageNotConfigured) {
                $this->addError('photos', app()->isLocal()
                    ? 'Falta configurar Cloudinary. Completa CLOUDINARY_URL en el archivo .env y ejecuta php artisan config:clear. Tus cambios siguen aquí.'
                    : 'El servicio de fotos no está configurado. Contacta al administrador del sitio. Tus cambios siguen aquí.');
            } else {
                $this->addError('photos', 'No se pudo guardar el anuncio. Tus cambios siguen aquí; inténtalo de nuevo.');
            }
            return;
        }
        $images->cleanup(array_values(array_diff($oldImages, $data['images'])));
        $this->resetForm();
        $this->resetPage();
        session()->flash('status', 'Anuncio guardado.');
    }
    public function markSold(int $id): void
    {
        $property = Auth::user()->properties()->findOrFail($id);
        $this->authorize('update', $property);
        abort_unless($property->isSale(), 422);
        $property->update(['is_sold' => true, 'is_available' => false]);
        if ($this->editingId === $id) { $this->is_sold = true; $this->is_available = false; }
    }
    public function toggleAvailability(int $id): void
    {
        $property = Auth::user()->properties()->findOrFail($id);
        $this->authorize('update', $property);
        // Atomic SQL flip avoids a read/modify/write race on repeated clicks.
        $property->newQuery()->whereKey($id)->where('user_id', Auth::id())->update(['is_sold' => false, 'is_available' => DB::raw('NOT is_available'), 'updated_at' => now()]);
        if ($this->editingId === $id) { $this->is_available = $property->fresh()->is_available; $this->is_sold = false; }
    }
    public function delete(int $id, PropertyImages $images): void
    {
        $property = Auth::user()->properties()->findOrFail($id);
        $this->authorize('delete', $property);
        $urls = $property->images;
        $property->delete();
        $images->cleanup($urls);
        if ($this->editingId === $id) { $this->resetForm(); }
        $this->resetPage();
        session()->flash('status', 'Anuncio eliminado.');
    }
    public function resetForm(): void
    {
        $this->reset(...Property::EXTRA_FIELDS);
        $this->reset('editingId', 'existingImages', 'title', 'type', 'price', 'department', 'city', 'colonia', 'address', 'description', 'whatsapp', 'is_available', 'photos', 'pendingPhotos');
        $this->dispatch('photos-processed', failed: false);
        $this->resetValidation();
    }
    public function render()
    {
        return view('livewire.manage-properties', ['properties' => Auth::user()->properties()->latest('id')->paginate(8), 'departments' => Honduras::departments(), 'cities' => $this->department === '' ? [] : Honduras::cities($this->department), 'types' => Property::TYPES])->layout('layouts.app');
    }
}
