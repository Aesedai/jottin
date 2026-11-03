<?php

namespace App\Controllers;

use App\Libraries\Jotts\FeedPage;

class Dashboard extends BaseController
{
    public function index(): string
    {
        return view('dashboard/index', FeedPage::data());
    }
}
