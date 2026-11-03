<?php

namespace App\Controllers;

use App\Models\JottCollectionModel;
use App\Models\JottModel;

class Dashboard extends BaseController
{
    public function index(): string
    {
        $jotts = (new JottModel())->feed();

        return view('dashboard/index', [
            'jotts'       => $jotts,
            'memberships' => (new JottCollectionModel())->forJotts(array_map('intval', array_column($jotts, 'id'))),
        ]);
    }
}
