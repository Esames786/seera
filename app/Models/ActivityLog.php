<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ActivityLog extends Model
{
    protected $fillable = [
        'user_id', 'user_name', 'module', 'action', 'description',
        'old_value', 'new_value', 'ip_address', 'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function record(Request $request, string $module, string $action, ?string $description = null, string $status = 'success'): self
    {
        return static::create([
            'user_id' => $request->user()?->id,
            'user_name' => $request->user()?->name ?? 'System',
            'module' => $module,
            'action' => $action,
            'description' => $description,
            'ip_address' => $request->ip(),
            'status' => $status,
        ]);
    }
}
