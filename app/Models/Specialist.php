<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Specialist extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
        'display_name',
        'slug',
        'title',
        'specialty',
        'bio',
        'years_of_experience',
        'city',
        'country',
        'consultation_fee',
        'currency',
        'rating_avg',
        'reviews_count',
        'avatar',
        'whatsapp_join_mode',
        'whatsapp_number',
        'is_active',
        'metadata',
        'name',
        'session_fee',
        'specializations',
        'languages',
        'is_available',
    ];

    protected $casts = [
        'is_active'        => 'bool',
        'metadata'         => 'array',
        'consultation_fee' => 'float',
        'rating_avg'       => 'float',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $specialist): void {
            $displayName = trim((string) ($specialist->display_name ?: $specialist->name));

            if ($displayName !== '') {
                [$firstName, $lastName] = self::splitName($displayName);

                $specialist->first_name = trim((string) ($specialist->first_name ?: $firstName));
                $specialist->last_name = trim((string) ($specialist->last_name ?? $lastName));
                $specialist->display_name = $displayName;
            }

            if ($specialist->last_name === null) {
                $specialist->last_name = '';
            }

            if (blank($specialist->slug) && filled($specialist->display_name)) {
                $specialist->slug = self::generateUniqueSlug($specialist->display_name, $specialist->id);
            }

            if (filled($specialist->title)) {
                $specialist->specialty = $specialist->title;
            } elseif (blank($specialist->specialty)) {
                $specialist->specialty = data_get($specialist->metadata, 'specializations.0')
                    ?: 'General Specialist';
            }

            if (blank($specialist->currency)) {
                $specialist->currency = 'USD';
            }

            if ($specialist->consultation_fee === null) {
                $specialist->consultation_fee = 0;
            }
        });
    }

    public function getNameAttribute(): string
    {
        return trim((string) ($this->display_name ?: implode(' ', array_filter([$this->first_name, $this->last_name]))));
    }

    public function setNameAttribute(?string $value): void
    {
        $name = trim((string) $value);

        if ($name === '') {
            return;
        }

        [$firstName, $lastName] = self::splitName($name);

        $this->attributes['first_name'] = $firstName;
        $this->attributes['last_name'] = $lastName;
        $this->attributes['display_name'] = $name;
    }

    public function getSessionFeeAttribute(): float
    {
        return (float) ($this->consultation_fee ?? 0);
    }

    public function setSessionFeeAttribute($value): void
    {
        $this->attributes['consultation_fee'] = $value === null || $value === '' ? 0 : $value;
    }

    public function getSpecializationsAttribute(): array
    {
        return $this->metadataValue('specializations', []);
    }

    public function setSpecializationsAttribute($value): void
    {
        $items = self::normalizeList($value);

        $this->mergeMetadata(['specializations' => $items]);

        if (($this->attributes['specialty'] ?? null) === null && blank($this->attributes['title'] ?? null) && $items !== []) {
            $this->attributes['specialty'] = $items[0];
        }
    }

    public function getLanguagesAttribute(): array
    {
        return $this->metadataValue('languages', []);
    }

    public function setLanguagesAttribute($value): void
    {
        $this->mergeMetadata(['languages' => self::normalizeList($value)]);
    }

    public function getIsAvailableAttribute(): bool
    {
        return (bool) $this->metadataValue('is_available', true);
    }

    public function setIsAvailableAttribute($value): void
    {
        $this->mergeMetadata(['is_available' => (bool) $value]);
    }

    public function availabilities()
    {
        return $this->hasMany(SpecialistAvailability::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function reviews()
    {
        return $this->hasMany(SpecialistReview::class);
    }

    private function metadataValue(string $key, $default = null)
    {
        return data_get($this->normalizedMetadata(), $key, $default);
    }

    private function mergeMetadata(array $values): void
    {
        $this->attributes['metadata'] = json_encode(
            array_merge($this->normalizedMetadata(), $values),
            JSON_UNESCAPED_UNICODE
        );
    }

    private function normalizedMetadata(): array
    {
        $metadata = $this->attributes['metadata'] ?? $this->metadata ?? [];

        if (is_string($metadata)) {
            $decoded = json_decode($metadata, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($metadata) ? $metadata : [];
    }

    private static function normalizeList($value): array
    {
        $items = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_filter(array_map(
            static fn($item) => trim((string) $item),
            $items
        )));
    }

    private static function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $firstName = (string) array_shift($parts);
        $lastName = trim(implode(' ', $parts));

        return [$firstName, $lastName];
    }

    private static function generateUniqueSlug(string $displayName, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($displayName);
        $baseSlug = $baseSlug !== '' ? $baseSlug : Str::lower(Str::random(10));
        $slug = $baseSlug;
        $counter = 2;

        while (static::query()
            ->when($ignoreId, fn($query) => $query->whereKeyNot($ignoreId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
