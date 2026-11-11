<?php
declare(strict_types=1);

namespace Enlixo;

use Enlixo\Ai\GeminiImageClient;
use Enlixo\Core\Clock;
use Enlixo\Core\Crypto;
use Enlixo\Core\Database;
use Enlixo\Core\SystemClock;
use Enlixo\Entitlements\EntitlementService;
use Enlixo\Entitlements\PdoEntitlementRepository;
use Enlixo\Http\Client\CurlHttpClient;
use Enlixo\Platform\PlatformBilling;
use Enlixo\Platform\PlatformCatalog;
use Enlixo\Platform\PlatformDashboard;
use Enlixo\Platform\PlatformSettings;
use Enlixo\Recordings\PdoRecordingRepository;
use Enlixo\Recordings\RecordingAccessService;
use Enlixo\Recordings\RecordingTypeRepository;
use Enlixo\Roles\PdoAuditLogger;
use Enlixo\Roles\PdoRoleRepository;
use Enlixo\Roles\PermissionService;
use Enlixo\Roles\RoleService;
use Enlixo\Scheduling\PdoTimetableRepository;
use Enlixo\Scheduling\ScheduleService;
use Enlixo\Scheduling\Zoom\ZoomProviderResolver;
use Enlixo\Scheduling\Zoom\ZoomWebhookVerifier;

/** Composition root: builds each service once, on first use. */
final class App
{
    private array $instances = [];

    public function __construct(public readonly array $config, private readonly ?string $clientIp = null)
    {
    }

    private function once(string $id, callable $factory): mixed
    {
        return $this->instances[$id] ??= $factory();
    }

    public function db(): Database { return $this->once('db', fn () => new Database($this->config['db'])); }
    public function clock(): Clock { return $this->once('clock', fn () => new SystemClock()); }

    public function crypto(): ?Crypto
    {
        return $this->once('crypto', fn () => $this->config['app']['key'] !== '' ? new Crypto($this->config['app']['key']) : null);
    }

    public function geminiImages(): GeminiImageClient
    {
        $c = $this->config['ai'] ?? [];
        return $this->once('gemini', fn () => new GeminiImageClient(new CurlHttpClient(90), (string) ($c['gemini_api_key'] ?? ''), (string) ($c['gemini_image_model'] ?? 'gemini-2.5-flash-image')));
    }

    public function entitlements(): EntitlementService
    {
        return $this->once('ent', fn () => new EntitlementService(new PdoEntitlementRepository($this->db())));
    }

    public function permissions(): PermissionService
    {
        return $this->once('perm', fn () => new PermissionService(new PdoRoleRepository($this->db()), $this->entitlements()));
    }

    public function roles(): RoleService
    {
        return $this->once('roles', fn () => new RoleService(new PdoRoleRepository($this->db()), $this->permissions(), $this->entitlements(), new PdoAuditLogger($this->db(), $this->clientIp)));
    }

    public function recordings(): RecordingAccessService
    {
        $c = $this->config['recordings'];
        return $this->once('rec', fn () => new RecordingAccessService(
            new PdoRecordingRepository($this->db()), $this->entitlements(), $this->clock(),
            $c['heartbeat_seconds'], $c['heartbeat_grace_seconds'], $c['default_multiplier'], $c['warn_at_ratio'],
        ));
    }

    /** Settings -> Recording Types (class recordings and pre-recorded classes). */
    public function recordingTypes(): RecordingTypeRepository
    {
        return $this->once('rectypes', fn () => new RecordingTypeRepository($this->db(), $this->crypto()));
    }

    public function schedule(): ScheduleService
    {
        return $this->once('sched', fn () => new ScheduleService(
            new PdoTimetableRepository($this->db()),
            new ZoomProviderResolver($this->db(), new CurlHttpClient(), $this->entitlements(), $this->crypto(), $this->config['zoom']),
            $this->crypto() ?? throw new \RuntimeException('Set APP_KEY before scheduling Zoom meetings (start URLs are stored encrypted).'),
            $this->clock(),
        ));
    }

    /** Platform billing (admin side): tutor subscriptions, invoices, payments. */
    public function platformCatalog(): PlatformCatalog { return $this->once('pcat', fn () => new PlatformCatalog($this->db())); }
    public function platformSettings(): PlatformSettings { return $this->once('pset', fn () => new PlatformSettings($this->db())); }

    public function today(): string
    {
        return $this->clock()->now()->setTimezone(new \DateTimeZone($this->config['app']['timezone']))->format('Y-m-d');
    }

    public function platformBilling(): PlatformBilling
    {
        return $this->once('pbill', fn () => new PlatformBilling($this->db(), $this->platformCatalog(), $this->platformSettings(), dirname(__DIR__) . '/assets/uploads/users-payment-slips', $this->today()));
    }

    public function platformDashboard(): PlatformDashboard
    {
        return $this->once('pdash', fn () => new PlatformDashboard($this->db(), $this->platformBilling(), $this->platformCatalog(), $this->today()));
    }

    public function zoomWebhooks(): ZoomWebhookVerifier
    {
        return $this->once('zwh', fn () => new ZoomWebhookVerifier($this->config['zoom']['webhook_secret']));
    }
}
