<?php

namespace Tests\Support\Models;

class MisconfiguredNoteModel extends ScopedNoteModel
{
    protected $allowedFields = ['body', 'tenant_id'];
}
