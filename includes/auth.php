<?php
// Check if session is not already started
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

// Core authentication functions
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

function redirectIfNotLoggedIn($redirectUrl = 'login.php') {
    if (!isLoggedIn()) {
        header("Location: " . $redirectUrl);
        exit();
    }
}

function isAuthorized($allowedRoles = []) {
    if (!isLoggedIn()) {
        return false;
    }
    
    if (empty($allowedRoles)) {
        return true;
    }
    
    return in_array($_SESSION['role'], $allowedRoles);
}

function redirectBasedOnRole() {
    if (isLoggedIn()) {
        $base_url = $GLOBALS['base_url'] ?? '';
        header("Location: " . $base_url . "/dashboard.php");
        exit();
    }
}

function getUserRole() {
    return $_SESSION['role'] ?? null;
}

function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

// Permission-based functions
function canRegisterMothers() {
    return isAuthorized(['admin', 'midwife']);
}

function canRegisterBirths() {
    return isAuthorized(['admin', 'mother', 'midwife']);
}

function canRegisterOwnProfile() {
    return isAuthorized(['mother']);
}

function canRegisterOwnBirth() {
    return isAuthorized(['mother']);
}

// Role check functions
function isAdmin() {
    return isAuthorized(['admin']);
}

function isMidwife() {
    return isAuthorized(['midwife']);
}

function isMother() {
    return isAuthorized(['mother']);
}

function isHealthWorker() {
    return isAuthorized(['bhw']);
}

function isNutritionScholar() {
    return isAuthorized(['bns']);
}