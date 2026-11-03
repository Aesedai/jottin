<?php

namespace App\Controllers;

use App\Models\AuditLogModel;

class Audit extends BaseController
{
    public function index(): string
    {
        return view('audit/index', ['entries' => (new AuditLogModel())->recent()]);
    }
}
