<?php
/**
 * AIP Tracker - Home Page / Entry Point
 */

require_once 'config/config.php';

// If user is logged in, redirect to dashboard
if (Helpers::isLoggedIn()) {
    Helpers::redirect('/dashboard.php');
}

// Otherwise redirect to login
Helpers::redirect('/auth/login.php');
?>