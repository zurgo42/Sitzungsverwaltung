<?php
/**
 * logout.php - Benutzer abmelden
 */

require_once __DIR__ . '/../src/Session.php';

Session::logout();
Session::start();
Session::success('Du wurdest abgemeldet.');
Session::redirect('index.php');
