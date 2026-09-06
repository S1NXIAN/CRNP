<?php

namespace App\Core;

/**
 * Model — lightweight ActiveRecord-style base for Firebase RTDB entities.
 *
 * Each subclass sets $table (the Firebase path) and optionally $fillable.
 * Static helpers wrap getDB() so page files stay thin:
 *
 *   Product::find($id);
 *   $order = new Order($data); $order->save();
 */
abstract class Model
{
    /** Firebase path node (e.g. 'orders', 'products'). Subclasses MUST set this. */
    protected static string $table = '';

    /** Fields that mass-assignment will accept (empty = allow all). */
    protected static array $fillable = [];

    /** Raw attribute bag (keyed by Firebase field name). */
    protected array $attributes = [];

    /** Firebase push key (set after insert). */
    protected ?string $key = null;

    /** Per-request cache for raw() to avoid duplicate cURL calls. */
    private static array $rawCache = [];

    /* ------------------------------------------------------------------ */
    /*  Construction                                                       */
    /* ------------------------------------------------------------------ */

    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    /* ------------------------------------------------------------------ */
    /*  DB accessor (shared firebaseRDB instance via global getDB())        */
    /* ------------------------------------------------------------------ */

    protected static function db(): \firebaseRDB
    {
        return \getDB();
    }

    /* ------------------------------------------------------------------ */
    /*  Finders                                                            */
    /* ------------------------------------------------------------------ */

    /** Find a single record by its Firebase key. Returns null when missing. */
    public static function find(string $key): ?static
    {
        $row = static::db()->retrieve('/' . static::$table . '/' . $key);
        if (!is_array($row)) {
            return null;
        }
        $model = new static($row);
        $model->key = $key;
        return $model;
    }

    /** Retrieve raw rows (arrays) — for pages that still iterate raw data. */
    public static function raw(): array
    {
        $table = static::$table;
        if (!isset(self::$rawCache[$table])) {
            self::$rawCache[$table] = cache_file_get('model_raw_' . $table, 30, function () use ($table) {
                $raw = static::db()->retrieve('/' . $table);
                return \is_array($raw) ? $raw : [];
            });
        }
        return self::$rawCache[$table];
    }

    /* ------------------------------------------------------------------ */
    /*  Indexed queries (server-side; need database.rules.json .indexOn)   */
    /*  Firebase allows ONE orderBy per request: compound filters issue    */
    /*  one query per value and refine the rest in PHP.                    */
    /* ------------------------------------------------------------------ */

    /** Equality query on an indexed field. Returns rows keyed by Firebase key. */
    public static function where(string $field, string $value): array
    {
        $rows = static::db()->retrieve('/' . static::$table, $field, \firebaseRDB::EQUAL, $value);
        return \is_array($rows) ? $rows : [];
    }

    /** Multi-value equality (RTDB has no OR): one indexed query per value, merged. */
    public static function whereAny(string $field, array $values): array
    {
        $out = [];
        $seen = [];
        foreach ($values as $value) {
            $value = (string) $value;
            if (isset($seen[$value])) {
                continue;
            }
            $seen[$value] = true;
            foreach (static::where($field, $value) as $k => $row) {
                $out[$k] = $row;
            }
        }
        return $out;
    }

    /** Ordered range on an indexed field (e.g. created_at month slice). */
    public static function whereRange(string $field, string $start, string $end): array
    {
        $rows = static::db()->retrieve('/' . static::$table, $field, null, null, ['startAt' => $start, 'endAt' => $end]);
        return \is_array($rows) ? $rows : [];
    }

    /** Latest $limit rows by $field, newest first (indexed orderBy + limitToLast). */
    public static function recentBy(string $field, int $limit = 8): array
    {
        $rows = static::db()->retrieve('/' . static::$table, $field, null, null, ['limitToLast' => $limit]);
        if (!\is_array($rows)) {
            return [];
        }
        uasort($rows, function ($a, $b) use ($field) {
            return strcmp((string) ($b[$field] ?? ''), (string) ($a[$field] ?? ''));
        });
        return $rows;
    }

    /** Return the latest $limit rows by a date field, newest first (raw arrays). */
    public static function recentLimited(string $dateField, int $limit = 50): array
    {
        return static::recentBy($dateField, $limit);
    }

    /** Paginate raw rows: returns ['data' => ..., 'page' => ..., 'perPage' => ..., 'total' => ...]. */
    public static function paginate(int $page = 1, int $perPage = 50): array
    {
        $all = static::raw();
        $total = count($all);
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;
        $data = array_slice($all, $offset, $perPage, true);
        return [
            'data'    => $data,
            'page'    => $page,
            'perPage' => $perPage,
            'total'   => $total,
            'pages'   => max(1, (int) ceil($total / $perPage)),
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Persist                                                            */
    /* ------------------------------------------------------------------ */

    /** Insert into Firebase. Sets $this->key and returns it. */
    public function save(): string
    {
        $data = $this->toFillableArray();
        if ($this->key !== null) {
            static::db()->update('/' . static::$table, $this->key, $data);
            static::clearRawCache();
            return $this->key;
        }
        $newKey = static::db()->insert('/' . static::$table, $data);
        $this->key = $newKey;
        static::clearRawCache();
        return $newKey;
    }

    /** Partial update (PATCH) of specific fields. */
    public function update(array $attrs): void
    {
        foreach ($attrs as $k => $v) {
            $this->attributes[$k] = $v;
        }
        if ($this->key !== null) {
            static::db()->update('/' . static::$table, $this->key, $attrs);
            static::clearRawCache();
        }
    }

    /** Delete this record from Firebase. */
    public function delete(): void
    {
        if ($this->key !== null) {
            static::db()->delete('/' . static::$table, $this->key);
            $this->key = null;
            static::clearRawCache();
        }
    }

    /** Bust the per-request + file cache for this table. */
    public static function clearRawCache(): void
    {
        $table = static::$table;
        unset(self::$rawCache[$table]);
        cache_file_forget('model_raw_' . $table);
    }

    /* ------------------------------------------------------------------ */
    /*  Attribute access                                                   */
    /* ------------------------------------------------------------------ */

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /** Allow $order->status syntax (read-only). */
    public function __get(string $name): mixed
    {
        return $this->attributes[$name] ?? null;
    }

    /** Allow isset($order->status). */
    public function __isset(string $name): bool
    {
        return isset($this->attributes[$name]);
    }

    /* ------------------------------------------------------------------ */
    /*  Array / JSON export                                                */
    /* ------------------------------------------------------------------ */

    public function toArray(): array
    {
        return $this->attributes;
    }

    /* ------------------------------------------------------------------ */
    /*  Internal helpers                                                   */
    /* ------------------------------------------------------------------ */

    protected function fill(array $attrs): void
    {
        $fillable = static::$fillable;
        foreach ($attrs as $k => $v) {
            if (empty($fillable) || in_array($k, $fillable, true)) {
                $this->attributes[$k] = $v;
            }
        }
    }

    /** Return only fillable fields (or all if $fillable is empty). */
    protected function toFillableArray(): array
    {
        $fillable = static::$fillable;
        if (empty($fillable)) {
            return $this->attributes;
        }
        return array_intersect_key($this->attributes, array_flip($fillable));
    }
}
