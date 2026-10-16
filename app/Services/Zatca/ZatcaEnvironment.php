<?php

namespace App\Services\Zatca;

/**
 * Explicit ZATCA environment (sandbox / simulation / production) from
 * config/zatca.php. Production requires both environment=production and
 * production_enabled=true; APP_ENV plays no part.
 */
final class ZatcaEnvironment
{
    public const SANDBOX = 'sandbox';

    public const SIMULATION = 'simulation';

    public const PRODUCTION = 'production';

    public const ALL = [self::SANDBOX, self::SIMULATION, self::PRODUCTION];

    public function current(): string
    {
        $environment = (string) config('zatca.environment', self::SANDBOX);
        if (! in_array($environment, self::ALL, true)) {
            throw ZatcaException::configuration('invalid_environment', __('zatca.err_invalid_environment', ['environment' => $environment]));
        }

        return $environment;
    }

    public function isProduction(): bool
    {
        return $this->current() === self::PRODUCTION;
    }

    /** Refuses production unless it has been deliberately enabled. */
    public function assertUsable(): string
    {
        $environment = $this->current();
        if ($environment === self::PRODUCTION && ! (bool) config('zatca.production_enabled', false)) {
            throw ZatcaException::configuration('production_disabled', __('zatca.err_production_disabled'));
        }

        return $environment;
    }

    public function baseUrl(?string $environment = null): string
    {
        $environment ??= $this->current();
        $url = (string) config('zatca.endpoints.'.$environment, '');
        if ($url === '' || ! str_starts_with($url, 'https://')) {
            throw ZatcaException::configuration('missing_base_url', __('zatca.err_missing_base_url', ['environment' => $environment]));
        }

        return rtrim($url, '/');
    }

    /** Certificate template name written into the CSR (SDK CSR configuration samples per environment; confirm on the Developer Portal). */
    public function certificateTemplateName(?string $environment = null): string
    {
        return match ($environment ?? $this->current()) {
            self::PRODUCTION => 'ZATCA-Code-Signing',
            self::SIMULATION => 'PREZATCA-Code-Signing',
            default => 'TSTZATCA-Code-Signing',
        };
    }

    public function label(?string $environment = null): string
    {
        return __('zatca.env_'.($environment ?? $this->current()));
    }
}
