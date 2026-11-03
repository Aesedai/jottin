<?php

namespace Tests\Support\Models;

use App\Models\TenantScopedModel;

class ScopedNoteModel extends TenantScopedModel
{
    protected $table         = 'scoped_notes';
    protected $allowedFields = ['body'];
    protected $returnType    = 'array';
}
