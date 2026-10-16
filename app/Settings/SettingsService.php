<?php

namespace App\Settings;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Database-backed business settings: registry defaults -> stored value -> caller default.
 * Writes validate against the Registry, are audited, and invalidate the shared cache (not a per-process static).
 * A broken or missing store never throws on a request path: the registry default is returned.
 */
class SettingsService
{
    public const CACHE_KEY = 'settings.values.v1';

    private ?array $memo = null;

    public function all(): array
    {
        if ($this->memo !== null) {
            return $this->memo;
        }

        try {
            return $this->memo = Cache::rememberForever(self::CACHE_KEY, fn () => SystemSetting::query()
                ->get(['key', 'value'])
                ->mapWithKeys(fn (SystemSetting $s) => [$s->key => $this->decode($s->value)])
                ->all());
        } catch (\Throwable) {
            return $this->memo = [];
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $stored = $this->all()[$key] ?? null;
        if ($stored !== null) {
            return $stored;
        }

        return $default ?? (Registry::definition($key)['default'] ?? null);
    }

    /** Raw write (legacy API, no audit). Prefer update(). */
    public function set(string $key, mixed $value, ?string $group = null, ?int $userId = null): void
    {
        SystemSetting::query()->updateOrCreate(['key' => $key], array_filter([
            'value' => $this->encode($value), 'group' => $group, 'updated_by' => $userId,
        ], fn ($v, $k) => $k === 'value' || $v !== null, ARRAY_FILTER_USE_BOTH));
        $this->flush();
    }

    /**
     * Validated, audited write of registry keys.
     *
     * @param array<string, mixed> $values key => raw input
     * @return array<string, array{old: mixed, new: mixed}> what actually changed
     */
    public function update(array $values, ?User $by = null, ?string $ip = null): array
    {
        $rules = [];
        foreach ($values as $key => $_) {
            $def = Registry::definition($key);
            if (! $def || $def['type'] === 'file') {
                throw new \InvalidArgumentException("Setting [{$key}] is not manageable.");
            }
            $rules[$key] = $def['rules'];
        }

        $validated = Validator::make($values, $rules)->validate();
        $changes = [];

        DB::transaction(function () use ($validated, $by, $ip, &$changes) {
            foreach ($validated as $key => $value) {
                $def = Registry::definition($key);
                $value = $this->cast($def['type'], $value);
                $old = $this->all()[$key] ?? null;

                if ($this->same($old, $value) || ($old === null && $this->same($value, $def['default']))) {
                    continue;
                }

                SystemSetting::query()->updateOrCreate(['key' => $key], [
                    'value' => $this->encode($value), 'group' => $def['group'], 'updated_by' => $by?->id,
                ]);
                DB::table('setting_audits')->insert([
                    'key' => $key, 'group' => $def['group'],
                    'old_value' => $old === null ? null : $this->encode($old), 'new_value' => $value === null ? null : $this->encode($value),
                    'user_id' => $by?->id, 'ip' => $ip, 'created_at' => now(),
                ]);
                $changes[$key] = ['old' => $old, 'new' => $value];
            }
        });

        $this->flush();

        if ($changes) {
            activity('settings')->causedBy($by)->withProperties(['keys' => array_keys($changes)])->log('Settings updated');
        }

        return $changes;
    }

    public function flush(): void
    {
        $this->memo = null;
        Cache::forget(self::CACHE_KEY);
    }

    private function cast(string $type, mixed $value): mixed
    {
        return match ($type) {
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'int' => $value === null || $value === '' ? null : (int) $value,
            'user' => $value === null || $value === '' ? null : (int) $value,
            default => is_string($value) && trim($value) === '' ? null : (is_string($value) ? trim($value) : $value),
        };
    }

    private function same(mixed $a, mixed $b): bool
    {
        return $this->encode($a) === $this->encode($b);
    }

    private function encode(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return is_array($value) || is_bool($value) || is_int($value) || is_float($value)
            ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value;
    }

    private function decode(?string $value): mixed
    {
        if ($value === null) {
            return null;
        }
        $json = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $json : $value;
    }
}
