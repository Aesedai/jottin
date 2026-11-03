<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * The tenants table is the root of isolation, so it has no tenant_id of its own.
 * Only sign-up and session resolution should use this model.
 */
class TenantModel extends Model
{
    protected $table         = 'tenants';
    protected $allowedFields = ['name'];
    protected $useTimestamps = true;
    protected $returnType    = 'array';
}
