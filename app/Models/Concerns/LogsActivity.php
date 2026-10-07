<?php

namespace App\Models\Concerns;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Writes an audit-trail row whenever the model is created, updated or
 * deleted: who did it, when, and the old and new values.
 *
 * Models may define activityLabel(): string for a readable description.
 */
trait LogsActivity
{
    /** Columns never written to the trail. */
    private static array $activityIgnored = ['created_at', 'updated_at', 'password', 'remember_token'];

    public static function bootLogsActivity(): void
    {
        static::created(fn (Model $model) => $model->writeActivity('created', self::cleanAttributes($model->getAttributes())));

        static::updated(function (Model $model) {
            $changes = [];

            foreach (self::cleanAttributes($model->getChanges()) as $key => $new) {
                $changes[$key] = ['old' => $model->getRawOriginal($key), 'new' => $new];
            }

            if ($changes !== []) {
                $model->writeActivity('updated', $changes);
            }
        });

        static::deleted(fn (Model $model) => $model->writeActivity('deleted', self::cleanAttributes($model->getRawOriginal())));
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    protected function writeActivity(string $event, array $changes): void
    {
        ActivityLog::create([
            'user_id' => Auth::id(),
            'event' => $event,
            'subject_type' => static::class,
            'subject_id' => $this->getKey(),
            'description' => mb_substr($this->describeForActivity(), 0, 255),
            'changes' => $changes === [] ? null : $changes,
            'ip_address' => app()->runningInConsole() ? null : Request::ip(),
        ]);
    }

    protected function describeForActivity(): string
    {
        return method_exists($this, 'activityLabel')
            ? $this->activityLabel()
            : class_basename($this).' #'.$this->getKey();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private static function cleanAttributes(array $attributes): array
    {
        return array_diff_key($attributes, array_flip(self::$activityIgnored));
    }
}
