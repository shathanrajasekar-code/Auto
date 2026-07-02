<?php
/**
 * Namma AutoParts - Admin Panel Auth Checker
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';

// Enforce login and admin role
require_role('admin');
