<?php

namespace Config;

use App\Libraries\Api\ApiClient;
use App\Libraries\Auth\CurrentUser;
use App\Libraries\Delivery\CurlTransport;
use App\Libraries\Delivery\DnsResolver;
use App\Libraries\Delivery\WebhookTransport;
use App\Libraries\Tenancy\TenantContext;
use CodeIgniter\Config\BaseService;

/**
 * Services Configuration file.
 *
 * Services are simply other classes/libraries that the system uses
 * to do its job. This is used by CodeIgniter to allow the core of the
 * framework to be swapped out easily without affecting the usage within
 * the rest of your application.
 *
 * This file holds any application-specific services, or service overrides
 * that you might need. An example has been included with the general
 * method format you should use for your service methods. For more examples,
 * see the core Services file at system/Config/Services.php.
 */
class Services extends BaseService
{
    public static function webhookTransport(bool $getShared = true): WebhookTransport
    {
        if ($getShared) {
            return static::getSharedInstance('webhookTransport');
        }

        return new CurlTransport();
    }

    public static function dnsResolver(bool $getShared = true): DnsResolver
    {
        if ($getShared) {
            return static::getSharedInstance('dnsResolver');
        }

        return new DnsResolver();
    }

    public static function apiClient(bool $getShared = true): ApiClient
    {
        if ($getShared) {
            return static::getSharedInstance('apiClient');
        }

        return new ApiClient();
    }

    public static function currentUser(bool $getShared = true): CurrentUser
    {
        if ($getShared) {
            return static::getSharedInstance('currentUser');
        }

        return new CurrentUser();
    }

    public static function tenantContext(bool $getShared = true): TenantContext
    {
        if ($getShared) {
            return static::getSharedInstance('tenantContext');
        }

        return new TenantContext();
    }

    /*
     * public static function example($getShared = true)
     * {
     *     if ($getShared) {
     *         return static::getSharedInstance('example');
     *     }
     *
     *     return new \CodeIgniter\Example();
     * }
     */
}
