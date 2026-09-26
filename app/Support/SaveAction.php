<?php

namespace App\Support;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * One save workflow for every CRUD form (the Employee screen is the reference):
 *
 *   Save          `_save_action=stay`   keep working on the record just saved
 *   Save & New    `_save_action=new`    open a fresh create form (only where a
 *                                       screen enables it; repeatable data entry)
 *   Save & Close  default               back to the origin the user came from,
 *                                       else the list
 *   Cancel        no request            back to the origin, else the list
 *
 * "Origin" is the `_return_to` hidden field (seeded from `?return_to=` on the
 * create/edit page). Only a relative admin path is honoured, so the field can
 * never send the user off-site. Business actions (approve, post, pay, receive,
 * finalize, process, dispatch) are not save actions and never use this helper.
 */
final class SaveAction
{
    public const STAY = 'stay';

    public const NEW = 'new';

    public const CLOSE = 'close';

    public const FIELD = '_save_action';

    public const RETURN_FIELD = '_return_to';

    /** The action the form asked for; anything unknown means "close". */
    public static function from(Request $request): string
    {
        $action = (string) $request->input(self::FIELD, self::CLOSE);

        return in_array($action, [self::STAY, self::NEW, self::CLOSE], true) ? $action : self::CLOSE;
    }

    /**
     * Where to go after a successful save.
     *
     * @param  array{stay: string, close: string, new?: string}  $destinations  Absolute URLs; omit `new` to disable Save & New.
     */
    public static function redirect(Request $request, array $destinations): RedirectResponse
    {
        $action = self::from($request);

        if ($action === self::STAY) {
            return redirect()->to($destinations['stay']);
        }

        if ($action === self::NEW && isset($destinations['new'])) {
            $new = $destinations['new'];
            if ($origin = self::returnTo($request)) {
                $new .= (str_contains($new, '?') ? '&' : '?').'return_to='.rawurlencode($origin);
            }

            return redirect()->to($new);
        }

        return redirect()->to(self::returnTo($request) ?? $destinations['close']);
    }

    /**
     * The origin the form should close to: the `_return_to` field or the
     * `?return_to=` query, when it is a relative path inside the admin area.
     */
    public static function returnTo(?Request $request = null): ?string
    {
        $request ??= request();
        $candidate = (string) ($request->input(self::RETURN_FIELD) ?: $request->query('return_to', ''));

        return self::isSafePath($candidate) ? $candidate : null;
    }

    /** Cancel target for a form: the origin when known, otherwise the given list URL. */
    public static function cancelUrl(string $fallback, ?Request $request = null): string
    {
        return self::returnTo($request) ?? $fallback;
    }

    public static function isSafePath(string $path): bool
    {
        if ($path === '' || strlen($path) > 2000) {
            return false;
        }

        // Relative, single leading slash (no protocol-relative "//host"), inside /admin, no control characters.
        return Str::startsWith($path, '/admin')
            && ! Str::startsWith($path, '//')
            && ! str_contains($path, '\\')
            && preg_match('/[\x00-\x1F\x7F]/', $path) !== 1;
    }
}
