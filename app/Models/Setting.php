<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Tiny key/value store for admin settings. Use Setting::bool()/int() and Setting::put(). */
class Setting extends Model
{
    public $timestamps = false;
    public $incrementing = false;
    protected $primaryKey = 'key';
    protected $keyType = 'string';
    protected $fillable = ['key', 'value'];

    /** @var array<string, string|null>|null */
    private static ?array $memo = null;

    private static function all(): array
    {
        return self::$memo ??= self::query()->pluck('value', 'key')->all();
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        return self::all()[$key] ?? $default;
    }

    public static function bool(string $key, bool $default): bool
    {
        $v = self::get($key);

        return $v === null ? $default : $v === '1';
    }

    public static function int(string $key, int $default): int
    {
        $v = self::get($key);

        return $v === null ? $default : (int) $v;
    }

    public static function put(string $key, string|int|bool $value): void
    {
        self::query()->updateOrInsert(['key' => $key], ['value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value]);
        self::$memo = null;
    }

    public static function forget(): void
    {
        self::$memo = null;
    }
}
