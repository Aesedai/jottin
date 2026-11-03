<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');
$routes->get('health', 'Health::index');

$routes->get('login', 'Auth::loginForm');
$routes->post('login', 'Auth::login');
$routes->get('signup', 'Auth::signupForm');
$routes->post('signup', 'Auth::signup');
$routes->post('logout', 'Auth::logout', ['filter' => 'auth']);

$routes->get('dashboard', 'Dashboard::index', ['filter' => 'auth']);
$routes->get('account/password', 'Account::passwordForm', ['filter' => 'auth']);
$routes->post('account/password', 'Account::changePassword', ['filter' => 'auth']);

$routes->get('members', 'Members::index', ['filter' => ['auth', 'role:owner']]);
$routes->post('members', 'Members::create', ['filter' => ['auth', 'role:owner']]);
$routes->post('members/(:num)/remove', 'Members::remove/$1', ['filter' => ['auth', 'role:owner']]);
