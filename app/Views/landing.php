<?php $this->setVar('title', 'Jottin'); ?>
<?php $this->setVar('mainClass', 'landing'); ?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<h1 class="hero-title">Jot it down. Let your tools take it from there.</h1>
<p class="hero-copy">Write a quick note, and hashtags file it into collections. Each collection can send its jotts to your own automations.</p>
<p><a class="button primary" href="/signup">Create a workspace</a></p>
<?= $this->endSection() ?>
