<?php
namespace App\Livewire;
use App\Models\Property;
use App\Support\Honduras;
use App\Support\Seo;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
class PropertyCatalog extends Component
{
    use WithPagination;
    #[Url(except: '')] public string $department = '';
    #[Url(except: '')] public string $city = '';
    #[Url(except: '')] public string $search = '';
    #[Url(except: '')] public string $type = '';
    #[Url(except: '')] public string $minPrice = '';
    #[Url(except: '')] public string $maxPrice = '';
    #[Url(except: true)] public bool $onlyAvailable = true;
    #[Url(except: '')] public string $q = '';
    public string $keywords = '';
    public array $filters = [];
    private const FILTER_FIELDS = ['department', 'city', 'search', 'type', 'minPrice', 'maxPrice', 'onlyAvailable'];
    public function mount(): void { $this->filters = $this->only(self::FILTER_FIELDS); $this->keywords = $this->q; }
    public function searchKeywords(): void
    {
        $this->validate(['keywords' => ['nullable', 'string', 'max:150']]);
        $keywords = trim($this->keywords);
        // A new general search starts independently of the sidebar filters.
        $this->clearFilters();
        $this->q = $this->keywords = $keywords;
    }
    public function updatedFilters($value, $key): void
    {
        if ($key === 'department') { $this->filters['city'] = ''; }
    }
    public function searchProperties(): void
    {
        $this->resetValidation();
        $data = validator($this->filters, $this->filterRules($this->filters))->validate();
        foreach (self::FILTER_FIELDS as $field) { $this->$field = $data[$field]; }
        $this->resetPage();
    }
    public function searchAll(): void
    {
        $this->resetValidation();
        $data = validator(array_merge($this->filters, ['keywords' => $this->keywords]), array_merge(
            $this->filterRules($this->filters), ['keywords' => ['nullable', 'string', 'max:150']]
        ))->validate();
        foreach (self::FILTER_FIELDS as $field) { $this->$field = $data[$field]; }
        $this->q = trim($data['keywords']);
        $this->resetPage();
    }
    public function updated($name): void { if ($name === 'department') { $this->city = ''; } $this->resetPage(); }
    public function clearFilters(): void
    {
        $this->reset('department', 'city', 'search', 'type', 'minPrice', 'maxPrice', 'onlyAvailable', 'q', 'keywords');
        $this->filters = $this->only(self::FILTER_FIELDS);
        $this->resetValidation();
        $this->resetPage();
    }
    protected function filterRules(array $values): array
    {
        $rules = ['city' => ['nullable', 'string', 'max:100'], 'search' => ['nullable', 'string', 'max:150'], 'type' => ['nullable', Rule::in(array_keys(Property::TYPES))], 'minPrice' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'], 'maxPrice' => ['nullable', 'numeric', 'min:0', 'max:99999999.99']];
        if (($values['minPrice'] ?? '') !== '' && is_numeric($values['minPrice'] ?? null)) { $rules['maxPrice'][] = 'gte:minPrice'; }
        $rules['department'] = ['nullable', Rule::in(Honduras::departments())];
        $rules['city'] = ['nullable', Rule::in(Honduras::cities((string) ($values['department'] ?? '')))];
        $rules['onlyAvailable'] = ['required', 'boolean'];
        return $rules;
    }
    public function render()
    {
        $values = $this->only(self::FILTER_FIELDS);
        $values['q'] = $this->q;
        $validator = validator($values, array_merge($this->filterRules($values), ['q' => ['nullable', 'string', 'max:150']]));
        if ($validator->fails()) { $this->setErrorBag($validator->errors()); }
        $query = Property::query()->select(['id', 'title', 'type', 'price', 'department', 'city', 'colonia', 'address', 'description', 'whatsapp', 'is_available', 'images', 'created_at', 'updated_at']);
        if ($validator->fails()) { $query->whereRaw('1 = 0'); }
        else {
            $query->when($this->onlyAvailable, fn ($q) => $q->where('is_available', true))
                ->when($this->department !== '', fn ($q) => $q->where('department', $this->department))
                ->when($this->city !== '', fn ($q) => $q->where('city', $this->city))
                ->when($this->type !== '', fn ($q) => $q->where('type', $this->type))
                ->when($this->minPrice !== '', fn ($q) => $q->where('price', '>=', $this->minPrice))
                ->when($this->maxPrice !== '', fn ($q) => $q->where('price', '<=', $this->maxPrice));
            $words = preg_split('/\s+/u', trim($this->q), -1, PREG_SPLIT_NO_EMPTY);
            $ignored = ['en', 'de', 'del', 'la', 'el', 'los', 'las', 'un', 'una', 'y', 'cerca', 'alquiler', 'alquilar', 'renta', 'rentar'];
            $typeWords = ['casa' => 'casa', 'casas' => 'casa', 'apartamento' => 'apartamento', 'apartamentos' => 'apartamento', 'apto' => 'apartamento', 'aptos' => 'apartamento', 'cuarto' => 'cuarto', 'cuartos' => 'cuarto', 'habitacion' => 'cuarto', 'habitaciones' => 'cuarto'];
            foreach (array_unique($words ?: []) as $word) {
                $normalized = Str::lower(Str::ascii($word));
                if (in_array($normalized, $ignored, true)) { continue; }
                $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], Str::lower($word)).'%';
                // Every word must match, but words may occur in different fields.
                $query->where(function ($match) use ($term, $normalized, $typeWords) {
                    foreach (['title', 'description', 'colonia', 'address', 'city', 'department'] as $column) {
                        $match->orWhereRaw("LOWER($column) LIKE ? ESCAPE '!'", [$term]);
                    }
                    if (isset($typeWords[$normalized])) { $match->orWhere('type', $typeWords[$normalized]); }
                });
            }
            if (trim($this->search) !== '') {
                // Treat SQL LIKE wildcards as literal input; ! is an explicit portable escape.
                $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim($this->search)).'%';
                $query->where(fn ($q) => $q->whereRaw("colonia LIKE ? ESCAPE '!'", [$term])->orWhereRaw("address LIKE ? ESCAPE '!'", [$term])->orWhereRaw("title LIKE ? ESCAPE '!'", [$term]));
            }
        }
        $draftDepartment = (string) ($this->filters['department'] ?? '');
        $properties = $query->latest('id')->paginate(12)->appends(Seo::canonicalParameters($values));
        $seo = Seo::catalog($values, $properties, ! $validator->fails());
        $this->dispatch('seo-updated', seo: $seo);
        return view('livewire.property-catalog', ['properties' => $properties, 'seo' => $seo, 'departments' => Honduras::departments(), 'cityGroups' => $draftDepartment === '' ? Honduras::groups() : [$draftDepartment => Honduras::cities($draftDepartment)], 'draftDepartment' => $draftDepartment, 'types' => Property::TYPES])->layout('layouts.app', ['seo' => $seo]);
    }
}
